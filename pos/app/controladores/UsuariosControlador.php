<?php

class UsuariosControlador {

    private function db() { return obtenerConexion(); }

    // ── Validaciones comunes de usuario (devuelve mensaje o '') ──
    private function validarDatosUsuario(string $usuario, string $contrasena, int $rolId, bool $contrasenaOpcional = false): string {
        if (!preg_match('/^[\p{L}0-9._ -]{3,40}$/u', $usuario)) {
            return 'El usuario debe tener entre 3 y 40 caracteres (letras, números, espacio, punto o guion).';
        }
        if (!($contrasenaOpcional && $contrasena === '')) {
            $errorClave = validarContrasenaNueva($contrasena, [$usuario, $_SESSION['tenant_nombre'] ?? '']);
            if ($errorClave !== '') return $errorClave;
        }
        $st = $this->db()->prepare("SELECT id FROM roles WHERE id=:id");
        $st->execute([':id' => $rolId]);
        if (!$st->fetch()) return 'El rol seleccionado no existe.';
        return '';
    }

    // ── Cupo de usuarios del plan (usuarios ACTIVOS). Devuelve el mensaje de error o '' ──
    private function errorCupoUsuarios(int $tid): string {
        $limite = planLimiteUsuarios();
        if ($limite === null) return '';
        $st = $this->db()->prepare("SELECT COUNT(*) FROM usuarios WHERE tenant_id=:t AND activo=1");
        $st->execute([':t' => $tid]);
        if ((int) $st->fetchColumn() >= $limite) {
            return 'Tu plan ' . planNombre() . ' incluye ' . $limite . ' usuario' . ($limite === 1 ? '' : 's')
                 . '. Para agregar más, mira las opciones en "Funciones avanzadas".';
        }
        return '';
    }

    // ── Sucursal del usuario (solo si el negocio tiene la funcion y la sucursal es una suya y activa) ──
    private function asignarSucursal(int $usuarioId, int $tid): void {
        if (!sucursalesHabilitadas()) return;
        $s = (int) ($_POST['sucursal_id'] ?? 0);
        $valor = isset(sucursalesDelNegocio()[$s]) ? $s : null;   // 0 / invalida = Principal
        $this->db()->prepare("UPDATE usuarios SET sucursal_id=:s WHERE id=:id AND tenant_id=:t")
             ->execute([':s' => $valor, ':id' => $usuarioId, ':t' => $tid]);
    }

    // ── Solo administrador puede acceder ──────────────────────
    private function soloAdmin() {
        if ($_SESSION['usuario_rol_id'] != 1) {
            $_SESSION['error'] = 'No tenés permisos para acceder a esta sección.';
            header('Location: ' . URL_BASE . '/?url=pos/index'); exit;
        }
    }

    // ── Listado ───────────────────────────────────────────────
    public function index() {
        $this->soloAdmin();
        $tid = tenantActual();
        $stmt = $this->db()->prepare(
            "SELECT u.*, r.nombre AS rol_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             WHERE u.tenant_id = :tid
             ORDER BY u.nombre ASC");
        $stmt->execute([':tid' => $tid]);
        $usuarios = $stmt->fetchAll();
        $tituloPagina = 'Usuarios';
        include RAIZ . '/app/vistas/usuarios/lista.php';
    }

    // ── Nuevo usuario ─────────────────────────────────────────
    public function nuevo() {
        $this->soloAdmin();
        if ($errorCupo = $this->errorCupoUsuarios(tenantActual())) {
            $_SESSION['error'] = $errorCupo;
            header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
        }
        $roles        = $this->db()->query("SELECT * FROM roles ORDER BY nombre")->fetchAll();
        $usuario      = null;
        $tituloPagina = 'Nuevo Usuario';
        include RAIZ . '/app/vistas/usuarios/formulario.php';
    }

    // ── Guardar usuario ───────────────────────────────────────
    public function guardar() {
        $this->soloAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
        }
        $tid = tenantActual();
        if ($errorCupo = $this->errorCupoUsuarios($tid)) {
            $_SESSION['error'] = $errorCupo;
            header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
        }

        $nombre    = trim($_POST['nombre'] ?? '');
        $usuario   = trim($_POST['usuario'] ?? '');
        $contrasena = trim($_POST['contrasena'] ?? '');
        $rol_id    = intval($_POST['rol_id'] ?? 0);

        if (!$nombre || !$usuario || !$contrasena || !$rol_id) {
            $_SESSION['error'] = 'Todos los campos son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=usuarios/nuevo'); exit;
        }
        if ($error = $this->validarDatosUsuario($usuario, $contrasena, $rol_id)) {
            $_SESSION['error'] = $error;
            header('Location: ' . URL_BASE . '/?url=usuarios/nuevo'); exit;
        }

        // El login es uno solo para todos los negocios: el usuario debe ser único en todo el sistema
        $stmt = $this->db()->prepare("SELECT id FROM usuarios WHERE usuario=:u");
        $stmt->execute([':u' => $usuario]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "El usuario '$usuario' ya está en uso. Elegí otro (por ejemplo agregando el nombre del negocio).";
            header('Location: ' . URL_BASE . '/?url=usuarios/nuevo'); exit;
        }

        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $this->db()->prepare("INSERT INTO usuarios (tenant_id,nombre,usuario,contrasena,rol_id) VALUES(:tid,:n,:u,:c,:r)")
             ->execute([':tid'=>$tid,':n'=>$nombre,':u'=>$usuario,':c'=>$hash,':r'=>$rol_id]);
        $this->asignarSucursal((int) $this->db()->lastInsertId(), $tid);

        $_SESSION['exito'] = "Usuario '$nombre' creado correctamente.";
        header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
    }

    // ── Editar usuario ────────────────────────────────────────
    public function editar($id) {
        $this->soloAdmin();
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT * FROM usuarios WHERE id=:id AND tenant_id=:tid");
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $usuario = $stmt->fetch();
        if (!$usuario) { header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit; }

        $roles        = $this->db()->query("SELECT * FROM roles ORDER BY nombre")->fetchAll();
        $tituloPagina = 'Editar Usuario';
        include RAIZ . '/app/vistas/usuarios/formulario.php';
    }

    // ── Actualizar usuario ────────────────────────────────────
    public function actualizar() {
        $this->soloAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
        }
        $tid = tenantActual();

        $id      = intval($_POST['id']);
        $nombre  = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $rol_id  = intval($_POST['rol_id'] ?? 0);
        $contrasena = trim($_POST['contrasena'] ?? '');

        if (!$nombre || !$usuario || !$rol_id) {
            $_SESSION['error'] = 'Nombre, usuario y rol son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=usuarios/editar/' . $id); exit;
        }
        if ($error = $this->validarDatosUsuario($usuario, $contrasena, $rol_id, true)) {
            $_SESSION['error'] = $error;
            header('Location: ' . URL_BASE . '/?url=usuarios/editar/' . $id); exit;
        }
        // El administrador no puede quitarse a sí mismo el rol de administrador (se quedaría fuera)
        if ($id == $_SESSION['usuario_id'] && $rol_id != 1) {
            $_SESSION['error'] = 'No podés quitarte a vos mismo el rol de administrador.';
            header('Location: ' . URL_BASE . '/?url=usuarios/editar/' . $id); exit;
        }

        // Usuario único en todo el sistema (excepto el mismo registro)
        $stmt = $this->db()->prepare("SELECT id FROM usuarios WHERE usuario=:u AND id != :id");
        $stmt->execute([':u'=>$usuario, ':id'=>$id]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "El usuario '$usuario' ya está en uso.";
            header('Location: ' . URL_BASE . '/?url=usuarios/editar/' . $id); exit;
        }

        if ($contrasena) {
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            $this->db()->prepare("UPDATE usuarios SET nombre=:n,usuario=:u,contrasena=:c,rol_id=:r WHERE id=:id AND tenant_id=:tid")
                 ->execute([':n'=>$nombre,':u'=>$usuario,':c'=>$hash,':r'=>$rol_id,':id'=>$id,':tid'=>$tid]);
        } else {
            $this->db()->prepare("UPDATE usuarios SET nombre=:n,usuario=:u,rol_id=:r WHERE id=:id AND tenant_id=:tid")
                 ->execute([':n'=>$nombre,':u'=>$usuario,':r'=>$rol_id,':id'=>$id,':tid'=>$tid]);
        }

        $this->asignarSucursal($id, $tid);
        $_SESSION['exito'] = 'Usuario actualizado correctamente.';
        header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
    }

    // ── Activar / Desactivar ──────────────────────────────────
    public function toggleActivo($id) {
        $this->soloAdmin();
        $tid = tenantActual();

        if ($id == $_SESSION['usuario_id']) {
            $_SESSION['error'] = 'No podés desactivarte a vos mismo.';
            header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
        }

        $stmt = $this->db()->prepare("SELECT activo FROM usuarios WHERE id=:id AND tenant_id=:tid");
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $actual = $stmt->fetchColumn();
        if ($actual === false) {
            $_SESSION['error'] = 'Usuario no encontrado.';
            header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
        }
        $nuevo  = $actual ? 0 : 1;
        if ($nuevo === 1 && ($errorCupo = $this->errorCupoUsuarios($tid))) {
            $_SESSION['error'] = $errorCupo;
            header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
        }

        $this->db()->prepare("UPDATE usuarios SET activo=:a WHERE id=:id AND tenant_id=:tid")
             ->execute([':a'=>$nuevo, ':id'=>$id, ':tid'=>$tid]);

        $_SESSION['exito'] = $nuevo ? 'Usuario activado.' : 'Usuario desactivado.';
        header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
    }

    // ── Mi perfil (cambiar contraseña propia) ─────────────────
    public function perfil() {
        $tid = tenantActual();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $actual  = trim($_POST['contrasena_actual'] ?? '');
            $nueva   = trim($_POST['contrasena_nueva'] ?? '');
            $repetir = trim($_POST['repetir'] ?? '');

            $stmt = $this->db()->prepare("SELECT contrasena, usuario FROM usuarios WHERE id=:id AND tenant_id=:tid");
            $stmt->execute([':id' => $_SESSION['usuario_id'], ':tid' => $tid]);
            $fila = $stmt->fetch();
            $hash = $fila['contrasena'] ?? '';
            $errorClave = validarContrasenaNueva($nueva, [$fila['usuario'] ?? '', $_SESSION['tenant_nombre'] ?? '']);

            if (!$hash || !password_verify($actual, $hash)) {
                $_SESSION['error'] = 'La contraseña actual es incorrecta.';
            } elseif ($nueva !== $repetir) {
                $_SESSION['error'] = 'Las contraseñas nuevas no coinciden.';
            } elseif ($nueva === $actual) {
                $_SESSION['error'] = 'La contraseña nueva debe ser distinta de la actual.';
            } elseif ($errorClave !== '') {
                $_SESSION['error'] = $errorClave;
            } else {
                $this->db()->prepare("UPDATE usuarios SET contrasena=:c WHERE id=:id AND tenant_id=:tid")
                     ->execute([':c'=>password_hash($nueva, PASSWORD_DEFAULT), ':id'=>$_SESSION['usuario_id'], ':tid'=>$tid]);
                $_SESSION['exito'] = 'Contraseña actualizada correctamente.';
            }
            header('Location: ' . URL_BASE . '/?url=usuarios/perfil'); exit;
        }

        $stmt = $this->db()->prepare(
            "SELECT u.*, r.nombre AS rol_nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=:id AND u.tenant_id=:tid");
        $stmt->execute([':id' => $_SESSION['usuario_id'], ':tid' => $tid]);
        $usuario      = $stmt->fetch();
        $tituloPagina = 'Mi Perfil';
        include RAIZ . '/app/vistas/usuarios/perfil.php';
    }
}