<?php
class ConfiguracionControlador {
    private function db() { return obtenerConexion(); }

    // ── Solo administrador puede modificar ────────────────────
    private function soloAdmin() {
        if ($_SESSION['usuario_rol_id'] != 1) {
            $_SESSION['error'] = 'Solo el administrador puede modificar la configuración.';
            header('Location: ' . URL_BASE . '/?url=pos/index'); exit;
        }
    }

    // ── El administrador elige que sucursal ve y administra (POST desde el selector del menu) ──
    // Solo cambia la sesion; ningun dato se modifica. Vuelve al inicio del mismo modulo para no quedar
    // en una pantalla de edicion de un registro que pertenece a la otra sucursal.
    public function cambiarSucursal() {
        $this->soloAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=pos/index'); exit;
        }
        $modulo     = (string) ($_POST['modulo'] ?? '');
        $permitidos = ['pos', 'cotizaciones', 'inventario', 'clientes', 'proveedores', 'informes', 'configuracion', 'usuarios'];
        $volver     = in_array($modulo, $permitidos, true) ? $modulo . '/index' : 'pos/index';

        if (!cambiarSucursalTrabajo((int) ($_POST['sucursal'] ?? 0))) {
            $_SESSION['error'] = 'No se pudo cambiar de sucursal.';
        }
        header('Location: ' . URL_BASE . '/?url=' . $volver); exit;
    }

    // ── Funciones avanzadas (informativo): fragmento que se muestra en el cuadro del menu ──
    // El cliente solo VE su plan, los planes y las funciones extra con su precio; no puede encender nada
    // (lo habilita SOLTEC desde el panel maestro).
    public function funcionesAvanzadas() {
        $this->soloAdmin();
        $tid     = tenantActual();
        $negocio = (string) ($_SESSION['tenant_nombre'] ?? '');

        $planes     = catalogoPlanes();
        $planActual = planNegocio();
        $enlacesPlan = [];
        foreach ($planes as $clave => $p) $enlacesPlan[$clave] = enlaceWhatsappPlan($negocio, $p['nombre']);

        $st = $this->db()->prepare("SELECT COUNT(*) FROM usuarios WHERE tenant_id=:t AND activo=1");
        $st->execute([':t' => $tid]);
        $usuariosActivos = (int) $st->fetchColumn();
        $limiteUsuarios  = planLimiteUsuarios();

        $sucursalesActivas = 0;
        try {
            $s = $this->db()->prepare("SELECT COUNT(*) FROM sucursales WHERE tenant_id=:t AND activo=1");
            $s->execute([':t' => $tid]);
            $sucursalesActivas = (int) $s->fetchColumn();
        } catch (Throwable $e) { /* la tabla aun no existe: sin sucursales */ }

        $items = [];
        foreach (catalogoFuncionesCliente() as $clave => $def) {
            $items[] = [
                'nombre'      => $def['nombre'],
                'icono'       => $def['icono'],
                'descripcion' => $def['descripcion'],
                'precio'      => $def['precio'],
                'activa'      => funcionActiva($clave),
                'enlace'      => enlaceWhatsappFuncion($negocio, $def['nombre']),
            ];
        }
        header('Content-Type: text/html; charset=utf-8');
        include RAIZ . '/app/vistas/configuracion/funciones_avanzadas.php';
    }

    // ── Ver / Editar configuración ────────────────────────────
    public function index() {
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT * FROM configuracion_negocio WHERE tenant_id=:tid LIMIT 1");
        $stmt->execute([':tid' => $tid]);
        $config = $stmt->fetch();

        if (!$config) {
            // Crear registro por defecto si no existe para este tenant
            $nombreDefault = $_SESSION['tenant_nombre'] ?? 'Mi Negocio';
            $this->db()->prepare("INSERT INTO configuracion_negocio (tenant_id, nombre_negocio) VALUES (:tid, :n)")
                 ->execute([':tid' => $tid, ':n' => $nombreDefault]);
            $stmt->execute([':tid' => $tid]);
            $config = $stmt->fetch();
        }
        $tituloPagina = 'Configuración';
        // Si se esta viendo una sucursal, se muestra el formulario de SUS datos de comprobante
        $configSucursal = null;
        $sucVista = sucursalTrabajo();
        if ($sucVista > 0 && sucursalesTienenDatos()) {
            $sq = $this->db()->prepare("SELECT id, nombre, direccion, telefono, nit, logo FROM sucursales WHERE id=:id AND tenant_id=:tid");
            $sq->execute([':id' => $sucVista, ':tid' => $tid]);
            $configSucursal = $sq->fetch() ?: null;
        }
        include RAIZ . '/app/vistas/configuracion/index.php';
    }

    // ── Guardar configuración ──────────────────────────────────
    public function guardar() {
        $this->soloAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
        }
        $tid     = tenantActual();
        $id      = intval($_POST['id'] ?? 0);
        $nombre  = trim($_POST['nombre_negocio'] ?? '');
        $dir     = trim($_POST['direccion'] ?? '');
        $tel     = trim($_POST['telefono'] ?? '');
        $nit     = trim($_POST['nit'] ?? '');

        // Si el formulario es de una SUCURSAL (direccion, telefono, NIT y logo propios), se guarda aparte
        $destinoSucursal = intval($_POST['sucursal_destino'] ?? 0);
        if ($destinoSucursal > 0) {
            $this->guardarSucursal($tid, $destinoSucursal);
            return;
        }

        if (!$nombre) {
            $_SESSION['error'] = 'El nombre del negocio es obligatorio.';
            header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
        }

        // Verificar que esta configuración pertenezca realmente a este tenant
        $check = $this->db()->prepare("SELECT id FROM configuracion_negocio WHERE id=:id AND tenant_id=:tid");
        $check->execute([':id' => $id, ':tid' => $tid]);
        if (!$check->fetch()) {
            $_SESSION['error'] = 'Configuración no encontrada.';
            header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
        }

        $logoPath = $this->logoSubido($tid);

        if ($logoPath) {
            $this->db()->prepare("UPDATE configuracion_negocio SET
                nombre_negocio=:n, direccion=:d, telefono=:t, nit=:nit, logo=:logo WHERE id=:id AND tenant_id=:tid")
                ->execute([':n'=>$nombre,':d'=>$dir,':t'=>$tel,':nit'=>$nit,':logo'=>$logoPath,':id'=>$id,':tid'=>$tid]);
        } else {
            $this->db()->prepare("UPDATE configuracion_negocio SET
                nombre_negocio=:n, direccion=:d, telefono=:t, nit=:nit WHERE id=:id AND tenant_id=:tid")
                ->execute([':n'=>$nombre,':d'=>$dir,':t'=>$tel,':nit'=>$nit,':id'=>$id,':tid'=>$tid]);
        }
        $_SESSION['exito'] = 'Configuración actualizada correctamente.';
        header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
    }

    // ── Logo subido en el formulario: devuelve la ruta nueva, o null si no subieron ninguno.
    // Si el archivo no es valido, avisa y vuelve a la pantalla (no regresa).
    private function logoSubido(int $tid): ?string {
        if (empty($_FILES['logo']['name'])) return null;
        $extensionesValidas = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        // Además de la extensión, se revisa que el archivo sea realmente una imagen
        $infoImagen = ($_FILES['logo']['error'] === UPLOAD_ERR_OK) ? @getimagesize($_FILES['logo']['tmp_name']) : false;
        $mimesValidos = ['image/jpeg', 'image/png', 'image/webp'];
        if (in_array($ext, $extensionesValidas, true) && $_FILES['logo']['size'] <= 2 * 1024 * 1024
            && $infoImagen && in_array($infoImagen['mime'], $mimesValidos, true)) {
            // La extension sale del tipo REAL de la imagen, no del nombre que puso quien la subio
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$infoImagen['mime']];
            // Cada tenant tiene su propia subcarpeta de logos para evitar mezclas
            $nombreArchivo  = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $carpetaDestino = RAIZ . '/app/public/img/tenant_' . $tid;
            if (!is_dir($carpetaDestino)) {
                mkdir($carpetaDestino, 0755, true);
            }
            $rutaDestino = $carpetaDestino . '/' . $nombreArchivo;
            if (guardarLogoSeguro($_FILES['logo']['tmp_name'], $rutaDestino, $infoImagen['mime'])) {
                return 'app/public/img/tenant_' . $tid . '/' . $nombreArchivo;
            }
            $_SESSION['error'] = 'No se pudo subir el logo. Verificá permisos de la carpeta.';
            header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
        }
        $_SESSION['error'] = 'El logo debe ser JPG, PNG o WEBP y pesar menos de 2MB.';
        header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
    }

    // ── Datos del comprobante de UNA sucursal (direccion, telefono, NIT y logo). Lo que se deje vacío usa lo del negocio.
    // Solo se guarda la sucursal que el administrador tiene en pantalla (evita guardar en otra por una pestaña vieja).
    private function guardarSucursal(int $tid, int $destino): void {
        $volver = URL_BASE . '/?url=configuracion/index';
        $sucursales = sucursalesDelNegocio();
        if (!sucursalesTienenDatos() || $destino !== sucursalTrabajo() || !isset($sucursales[$destino])) {
            $_SESSION['error'] = 'No se pudo guardar: la sucursal en pantalla cambió o ya no está activa. Abre Configuración otra vez.';
            header('Location: ' . $volver); exit;
        }
        $limpio = function ($v, int $max) {
            $v = trim((string) $v);
            return $v === '' ? null : mb_substr($v, 0, $max);
        };
        $dir  = $limpio($_POST['direccion'] ?? '', 255);
        $tel  = $limpio($_POST['telefono'] ?? '', 50);
        $nit  = $limpio($_POST['nit'] ?? '', 50);
        $logo = $this->logoSubido($tid);
        $quitarLogo = !empty($_POST['quitar_logo']);

        $sql = "UPDATE sucursales SET direccion=:d, telefono=:t, nit=:nit"
             . ($logo !== null ? ', logo=:logo' : ($quitarLogo ? ', logo=NULL' : ''))
             . " WHERE id=:id AND tenant_id=:tid";
        $params = [':d' => $dir, ':t' => $tel, ':nit' => $nit, ':id' => $destino, ':tid' => $tid];
        if ($logo !== null) $params[':logo'] = $logo;
        $this->db()->prepare($sql)->execute($params);

        $_SESSION['exito'] = 'Datos de la sucursal ' . $sucursales[$destino] . ' actualizados. Lo que dejes vacío usa los datos del negocio.';
        header('Location: ' . $volver); exit;
    }
}