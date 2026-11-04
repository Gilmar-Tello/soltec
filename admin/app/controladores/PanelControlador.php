<?php

class PanelControlador {

    /**
     * Resuelve y guarda el sistema activo (?sistema=pos|alquileres).
     * Si no viene en la URL, usa el que ya estaba en sesión (o 'pos' por defecto).
     */
    private function resolverSistema(): string {
        $sistemas = array_keys($GLOBALS['SISTEMAS_SOLTEC']);

        if (isset($_GET['sistema']) && in_array($_GET['sistema'], $sistemas, true)) {
            $_SESSION['panel_sistema'] = $_GET['sistema'];
        }

        return sistemaActivo();
    }

    private function db() { return obtenerConexion($this->resolverSistema()); }

    private function tablaTenants(): string {
        $sistema = $this->resolverSistema();
        return $GLOBALS['SISTEMAS_SOLTEC'][$sistema]['tabla_tenants'];
    }

    // ── Dashboard ─────────────────────────────────────────────
    public function index() {
        $tabla = $this->tablaTenants();
        $stmt = $this->db()->query(
            "SELECT
                COUNT(*) AS total_tenants,
                SUM(CASE WHEN activo=1 THEN 1 ELSE 0 END) AS activos,
                SUM(CASE WHEN activo=0 THEN 1 ELSE 0 END) AS inactivos,
                SUM(CASE WHEN plan='basico' THEN 1 ELSE 0 END) AS plan_basico,
                SUM(CASE WHEN plan='estandar' THEN 1 ELSE 0 END) AS plan_estandar,
                SUM(CASE WHEN plan='premium' THEN 1 ELSE 0 END) AS plan_premium
             FROM `$tabla`"
        );
        $resumen = $stmt->fetch();

        $recientes = $this->db()->query(
            "SELECT * FROM `$tabla` ORDER BY creado_en DESC LIMIT 5"
        )->fetchAll();

        $tituloPagina = 'Dashboard';
        include RAIZ . '/app/vistas/panel/dashboard.php';
    }

    // ── Listado de clientes (tenants) ─────────────────────────
    public function clientes() {
        $tabla = $this->tablaTenants();
        $busqueda = trim($_GET['busqueda'] ?? '');
        $sql = "SELECT * FROM `$tabla` WHERE 1=1";
        $params = [];
        if ($busqueda) {
            $sql .= " AND (nombre_negocio LIKE :b OR slug LIKE :b2 OR email_contacto LIKE :b3)";
            $params[':b']  = "%$busqueda%";
            $params[':b2'] = "%$busqueda%";
            $params[':b3'] = "%$busqueda%";
        }
        $sql .= " ORDER BY creado_en DESC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $clientes = $stmt->fetchAll();
        $sistemasConReset = self::SISTEMAS_CON_RESTABLECER;

        $tituloPagina = 'Clientes';
        include RAIZ . '/app/vistas/panel/lista_clientes.php';
    }

    // ── Formulario nuevo cliente ──────────────────────────────
    public function nuevoCliente() {
        $tituloPagina = 'Nuevo Cliente';
        include RAIZ . '/app/vistas/panel/nuevo_cliente.php';
    }

    // ── Guardar nuevo cliente (crea tenant + admin + config) ──
    // La estructura de tablas relacionadas difiere por sistema,
    // así que delegamos a un método específico tras crear el registro base.
    public function guardarCliente() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }

        $nombreNegocio = trim($_POST['nombre_negocio'] ?? '');
        $direccion     = trim($_POST['direccion'] ?? '');
        $telefono      = trim($_POST['telefono'] ?? '');
        $emailContacto = trim($_POST['email_contacto'] ?? '');
        $nit           = trim($_POST['nit'] ?? 'CF');
        $plan          = $_POST['plan'] ?? 'basico';
        $usuarioAdmin  = trim($_POST['usuario_admin'] ?? '');
        $contrasena    = trim($_POST['contrasena'] ?? '');

        if (!$nombreNegocio || !$usuarioAdmin || !$contrasena) {
            $_SESSION['error'] = 'Nombre del negocio, usuario y contraseña son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=panel/nuevoCliente'); exit;
        }
        if (!in_array($plan, ['basico', 'estandar', 'premium'], true)) $plan = 'basico';
        if ($errorClave = validarContrasenaNueva($contrasena, [$usuarioAdmin, $nombreNegocio])) {
            $_SESSION['error'] = $errorClave;
            header('Location: ' . URL_BASE . '/?url=panel/nuevoCliente'); exit;
        }

        $datos = compact('nombreNegocio', 'direccion', 'telefono', 'emailContacto', 'nit', 'plan', 'usuarioAdmin', 'contrasena');

        $sistema = $this->resolverSistema();
        if ($sistema === 'alquileres') {
            $this->guardarClienteAlquileres($datos);
        } elseif ($sistema === 'brix') {
            $this->guardarClienteBrix($datos);
        } else {
            $this->guardarClientePos($datos);
        }
    }

    // ── Alta de cliente: esquema Brix ─────────────────────────
    // Crea: negocios -> usuario admin (usuario + contraseña)
    private function guardarClienteBrix(array $d) {
        extract($d);

        $slugBase = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $nombreNegocio), '-'));
        $slug     = $slugBase;
        $contador = 1;
        while (true) {
            $check = $this->db()->prepare("SELECT id FROM negocios WHERE slug = :slug");
            $check->execute([':slug' => $slug]);
            if (!$check->fetch()) break;
            $slug = $slugBase . '-' . $contador;
            $contador++;
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            // 1. Crear el negocio
            $db->prepare("INSERT INTO negocios (nombre_negocio, slug, direccion, telefono, email_contacto, nit, plan, activo)
                VALUES (:nombre, :slug, :dir, :tel, :email, :nit, :plan, 1)")
               ->execute([
                   ':nombre'=>$nombreNegocio, ':slug'=>$slug, ':dir'=>$direccion,
                   ':tel'=>$telefono, ':email'=>$emailContacto, ':nit'=>$nit, ':plan'=>$plan,
               ]);
            $tenantId = $db->lastInsertId();

            // 2. Crear el usuario admin del negocio (login por usuario + contraseña)
            $hashContrasena = password_hash($contrasena, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO usuarios (tenant_id, nombre, usuario, contrasena, rol, activo)
                VALUES (:tid, :nombre, :usuario, :contrasena, 'admin', 1)")
               ->execute([
                   ':tid'=>$tenantId, ':nombre'=>$nombreNegocio, ':usuario'=>$usuarioAdmin, ':contrasena'=>$hashContrasena,
               ]);

            $db->commit();
            $_SESSION['exito'] = "Cliente '$nombreNegocio' creado correctamente. Usuario admin: $usuarioAdmin";
            header('Location: ' . URL_BASE . '/?url=panel/clientes');
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al crear el cliente. Intenta de nuevo.');
            header('Location: ' . URL_BASE . '/?url=panel/nuevoCliente');
        }
        exit;
    }

    // ── Alta de cliente: esquema POS ──────────────────────────
    private function guardarClientePos(array $d) {
        extract($d);

        // Generar slug único a partir del nombre
        $slugBase = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $nombreNegocio), '-'));
        $slug     = $slugBase;
        $contador = 1;
        while (true) {
            $check = $this->db()->prepare("SELECT id FROM tenants WHERE slug = :slug");
            $check->execute([':slug' => $slug]);
            if (!$check->fetch()) break;
            $slug = $slugBase . '-' . $contador;
            $contador++;
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            // 1. Crear el tenant
            $db->prepare("INSERT INTO tenants (nombre_negocio, slug, direccion, telefono, email_contacto, nit, plan, activo)
                VALUES (:nombre, :slug, :dir, :tel, :email, :nit, :plan, 1)")
               ->execute([
                   ':nombre'=>$nombreNegocio, ':slug'=>$slug, ':dir'=>$direccion,
                   ':tel'=>$telefono, ':email'=>$emailContacto, ':nit'=>$nit, ':plan'=>$plan,
               ]);
            $tenantId = $db->lastInsertId();

            // 2. Crear el usuario admin del negocio
            $hashContrasena = password_hash($contrasena, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO usuarios (tenant_id, nombre, usuario, contrasena, rol_id, activo)
                VALUES (:tid, :nombre, :usuario, :contrasena, 1, 1)")
               ->execute([
                   ':tid'=>$tenantId, ':nombre'=>$nombreNegocio, ':usuario'=>$usuarioAdmin, ':contrasena'=>$hashContrasena,
               ]);

            // 3. Crear configuración inicial del negocio
            $db->prepare("INSERT INTO configuracion_negocio (tenant_id, nombre_negocio, direccion, telefono, nit)
                VALUES (:tid, :nombre, :dir, :tel, :nit)")
               ->execute([
                   ':tid'=>$tenantId, ':nombre'=>$nombreNegocio, ':dir'=>$direccion, ':tel'=>$telefono, ':nit'=>$nit,
               ]);

            $db->commit();
            $_SESSION['exito'] = "Cliente '$nombreNegocio' creado correctamente. Usuario admin: $usuarioAdmin";
            header('Location: ' . URL_BASE . '/?url=panel/clientes');
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al crear el cliente. Intenta de nuevo.');
            header('Location: ' . URL_BASE . '/?url=panel/nuevoCliente');
        }
        exit;
    }

    // ── Alta de cliente: esquema Alquileres ───────────────────
    // Crea: negocios -> sucursal principal -> usuario admin_negocio
    private function guardarClienteAlquileres(array $d) {
        extract($d);

        $slugBase = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $nombreNegocio), '-'));
        $slug     = $slugBase;
        $contador = 1;
        while (true) {
            $check = $this->db()->prepare("SELECT id FROM negocios WHERE slug = :slug");
            $check->execute([':slug' => $slug]);
            if (!$check->fetch()) break;
            $slug = $slugBase . '-' . $contador;
            $contador++;
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            // 1. Crear el negocio
            $db->prepare("INSERT INTO negocios (nombre_negocio, slug, direccion, telefono, email_contacto, nit, plan, activo)
                VALUES (:nombre, :slug, :dir, :tel, :email, :nit, :plan, 1)")
               ->execute([
                   ':nombre'=>$nombreNegocio, ':slug'=>$slug, ':dir'=>$direccion,
                   ':tel'=>$telefono, ':email'=>$emailContacto, ':nit'=>$nit, ':plan'=>$plan,
               ]);
            $tenantId = $db->lastInsertId();

            // 2. Crear sucursal principal por defecto
            $db->prepare("INSERT INTO sucursales (tenant_id, nombre, direccion, telefono, activa)
                VALUES (:tid, 'Sucursal Principal', :dir, :tel, 1)")
               ->execute([':tid'=>$tenantId, ':dir'=>$direccion, ':tel'=>$telefono]);
            $sucursalId = $db->lastInsertId();

            // 3. Crear el usuario admin del negocio
            $hashContrasena = password_hash($contrasena, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO usuarios (tenant_id, sucursal_id, nombre, email, password, rol, activo)
                VALUES (:tid, :sid, :nombre, :email, :pass, 'admin_negocio', 1)")
               ->execute([
                   ':tid'=>$tenantId, ':sid'=>$sucursalId, ':nombre'=>$nombreNegocio,
                   ':email'=>$usuarioAdmin, ':pass'=>$hashContrasena,
               ]);

            $db->commit();
            $_SESSION['exito'] = "Cliente '$nombreNegocio' creado correctamente. Usuario admin: $usuarioAdmin";
            header('Location: ' . URL_BASE . '/?url=panel/clientes');
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al crear el cliente. Intenta de nuevo.');
            header('Location: ' . URL_BASE . '/?url=panel/nuevoCliente');
        }
        exit;
    }

    // ── Editar cliente ────────────────────────────────────────
    public function editarCliente($id) {
        $tabla = $this->tablaTenants();
        $stmt = $this->db()->prepare("SELECT * FROM `$tabla` WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $cliente = $stmt->fetch();
        if (!$cliente) { header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit; }

        $tituloPagina = 'Editar — ' . $cliente['nombre_negocio'];
        include RAIZ . '/app/vistas/panel/editar_cliente.php';
    }

    // ── Actualizar cliente ────────────────────────────────────
    public function actualizarCliente() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }

        $id            = intval($_POST['id']);
        $nombreNegocio = trim($_POST['nombre_negocio'] ?? '');
        $direccion     = trim($_POST['direccion'] ?? '');
        $telefono      = trim($_POST['telefono'] ?? '');
        $emailContacto = trim($_POST['email_contacto'] ?? '');
        $nit           = trim($_POST['nit'] ?? 'CF');
        $plan          = $_POST['plan'] ?? 'basico';
        if (!in_array($plan, ['basico', 'estandar', 'premium'], true)) $plan = 'basico';

        if (!$nombreNegocio) {
            $_SESSION['error'] = 'El nombre del negocio es obligatorio.';
            header('Location: ' . URL_BASE . '/?url=panel/editarCliente/' . $id); exit;
        }

        $tabla = $this->tablaTenants();
        $this->db()->prepare("UPDATE `$tabla` SET
            nombre_negocio=:nombre, direccion=:dir, telefono=:tel,
            email_contacto=:email, nit=:nit, plan=:plan WHERE id=:id")
            ->execute([
                ':nombre'=>$nombreNegocio, ':dir'=>$direccion, ':tel'=>$telefono,
                ':email'=>$emailContacto, ':nit'=>$nit, ':plan'=>$plan, ':id'=>$id,
            ]);

        $_SESSION['exito'] = 'Cliente actualizado correctamente.';
        header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
    }

    // ── Activar / Desactivar cliente ──────────────────────────
    public function toggleActivo($id) {
        $tabla = $this->tablaTenants();
        $stmt = $this->db()->prepare("SELECT activo, nombre_negocio FROM `$tabla` WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $tenant = $stmt->fetch();
        if (!$tenant) {
            $_SESSION['error'] = 'Cliente no encontrado.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }

        $nuevo = $tenant['activo'] ? 0 : 1;
        $this->db()->prepare("UPDATE `$tabla` SET activo = :a WHERE id = :id")
             ->execute([':a' => $nuevo, ':id' => $id]);

        $_SESSION['exito'] = $nuevo
            ? "'{$tenant['nombre_negocio']}' fue reactivado."
            : "'{$tenant['nombre_negocio']}' fue suspendido.";
        header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
    }

    // ══════════════════ RESTABLECER ACCESO DEL ADMINISTRADOR ══════════════════
    // Sistemas donde esta funcion ya existe: los tres.
    private const SISTEMAS_CON_RESTABLECER = ['brix', 'pos', 'alquileres'];

    // ── Pantalla: administradores del cliente + formulario de restablecer ──
    public function accesoAdmin($id = null) {
        $sistema = $this->resolverSistema();
        if (!in_array($sistema, self::SISTEMAS_CON_RESTABLECER, true)) {
            $_SESSION['error'] = 'Restablecer el acceso todavía no está disponible para este sistema.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }

        $tid   = (int) $id;
        $tabla = $this->tablaTenants();
        $stmt  = $this->db()->prepare("SELECT * FROM `$tabla` WHERE id = :id");
        $stmt->execute([':id' => $tid]);
        $cliente = $stmt->fetch();
        if (!$cliente) {
            $_SESSION['error'] = 'Cliente no encontrado.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }

        $admins = $this->listarAdmins($sistema, $tid);

        // La contraseña nueva se muestra UNA sola vez y se borra de la sesión
        $claveNueva = $_SESSION['clave_restablecida'] ?? null;
        unset($_SESSION['clave_restablecida']);

        $nombreSistema = $GLOBALS['SISTEMAS_SOLTEC'][$sistema]['nombre'];
        $tituloPagina  = 'Acceso — ' . $cliente['nombre_negocio'];
        include RAIZ . '/app/vistas/panel/acceso_admin.php';
    }

    // ── Acción: poner contraseña nueva (solo POST) ─────────────────────
    public function restablecerAdmin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }

        $sistema = $this->resolverSistema();
        $tid     = intval($_POST['tenant_id'] ?? 0);
        $uid     = intval($_POST['usuario_id'] ?? 0);
        $manual  = $this->normalizarClave($sistema, (string) ($_POST['contrasena'] ?? ''));
        $volver  = URL_BASE . '/?url=panel/accesoAdmin/' . $tid;

        // Cada número de cliente existe en TODOS los sistemas. Si en otra pestaña
        // cambiaste de sistema, el formulario ya no coincide: se cancela.
        if (($_POST['sistema'] ?? '') !== $sistema
            || !in_array($sistema, self::SISTEMAS_CON_RESTABLECER, true)) {
            $_SESSION['error'] = 'El sistema activo cambió mientras tenías esta pantalla abierta. Abre el cliente de nuevo.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }
        if ($tid <= 0 || $uid <= 0) {
            $_SESSION['error'] = 'Datos incompletos.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }
        if ($manual !== '' && ($errorClave = validarContrasenaNueva($manual))) {
            $_SESSION['error'] = $errorClave . ' (o déjala vacía para generar una).';
            header('Location: ' . $volver); exit;
        }

        // El usuario tiene que pertenecer a ESE negocio (la lista ya viene filtrada por tenant_id)
        $admin = null;
        foreach ($this->listarAdmins($sistema, $tid) as $a) {
            if ((int) $a['id'] === $uid) { $admin = $a; break; }
        }
        if (!$admin) {
            $_SESSION['error'] = 'Ese usuario no pertenece a este negocio.';
            header('Location: ' . $volver); exit;
        }

        $clave = $manual !== '' ? $manual : $this->generarClave();
        $hash  = password_hash($clave, PASSWORD_DEFAULT);

        try {
            if (!$this->actualizarClaveAdmin($sistema, $tid, $uid, $hash)) {
                throw new RuntimeException('La actualización no modificó ninguna fila.');
            }
        } catch (Throwable $e) {
            $_SESSION['error'] = 'No se pudo restablecer la contraseña. Intenta de nuevo.';
            error_log('[SOLTEC admin] Restablecer falló: ' . $e->getMessage());
            header('Location: ' . $volver); exit;
        }

        $this->registrarBitacora('restablecer_clave_admin', $sistema, $tid,
            'usuario=' . $admin['acceso'] . ' rol=' . ($admin['rol_nombre'] ?? '-')
            . ($manual !== '' ? ' (clave escrita a mano)' : ' (clave generada)'));

        $_SESSION['clave_restablecida'] = ['usuario_id' => $uid, 'acceso' => $admin['acceso'], 'clave' => $clave];
        header('Location: ' . $volver); exit;
    }

    // ── Helpers (cada sistema tiene sus propias tablas y columnas) ──────
    private function listarAdmins(string $sistema, int $tid): array {
        if ($sistema === 'brix') {
            // Brix: un administrador por negocio
            $st = $this->db()->prepare(
                "SELECT id, nombre, usuario AS acceso, activo, 'administrador' AS rol_nombre
                 FROM usuarios WHERE tenant_id = :t AND rol = 'admin' ORDER BY id");
            $st->execute([':t' => $tid]);
            return $st->fetchAll();
        }
        if ($sistema === 'pos') {
            // POS: todos los usuarios del negocio (administrador, cajero, bodeguero),
            // con el administrador primero
            $st = $this->db()->prepare(
                "SELECT u.id, u.nombre, u.usuario AS acceso, u.activo, r.nombre AS rol_nombre
                 FROM usuarios u JOIN roles r ON r.id = u.rol_id
                 WHERE u.tenant_id = :t
                 ORDER BY (u.rol_id = 1) DESC, u.nombre");
            $st->execute([':t' => $tid]);
            return $st->fetchAll();
        }
        if ($sistema === 'alquileres') {
            // Alquileres: todos los usuarios del negocio; entran con CORREO. El administrador primero.
            $st = $this->db()->prepare(
                "SELECT id, nombre, email AS acceso, activo, REPLACE(rol, '_', ' ') AS rol_nombre
                 FROM usuarios WHERE tenant_id = :t
                 ORDER BY (rol = 'admin_negocio') DESC, nombre");
            $st->execute([':t' => $tid]);
            return $st->fetchAll();
        }
        return [];
    }

    private function actualizarClaveAdmin(string $sistema, int $tid, int $uid, string $hash): bool {
        if ($sistema === 'brix') {
            $st = $this->db()->prepare(
                "UPDATE usuarios SET contrasena = :h WHERE id = :id AND tenant_id = :t AND rol = 'admin'");
            $st->execute([':h' => $hash, ':id' => $uid, ':t' => $tid]);
            return $st->rowCount() === 1;
        }
        if ($sistema === 'pos') {
            $st = $this->db()->prepare(
                "UPDATE usuarios SET contrasena = :h WHERE id = :id AND tenant_id = :t");
            $st->execute([':h' => $hash, ':id' => $uid, ':t' => $tid]);
            return $st->rowCount() === 1;
        }
        if ($sistema === 'alquileres') {
            // OJO: en Alquileres la columna se llama password (no contrasena)
            $st = $this->db()->prepare(
                "UPDATE usuarios SET password = :h WHERE id = :id AND tenant_id = :t");
            $st->execute([':h' => $hash, ':id' => $uid, ':t' => $tid]);
            return $st->rowCount() === 1;
        }
        return false;
    }

    // POS y Alquileres guardan las contraseñas ya recortadas (trim) cuando el administrador del
    // negocio crea o edita usuarios. El panel hace lo mismo, para no guardar nunca una clave
    // con espacios en las puntas (en el POS ni siquiera podria entrar: su login recorta).
    private function normalizarClave(string $sistema, string $clave): string {
        return in_array($sistema, ['pos', 'alquileres'], true) ? trim($clave) : $clave;
    }

    // Contraseña legible: sin 0/O, 1/l/I, que se confunden al dictarlas
    private function generarClave(int $largo = 10): string {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $max = strlen($alfabeto) - 1;
        $clave = '';
        for ($i = 0; $i < $largo; $i++) {
            $clave .= $alfabeto[random_int(0, $max)];
        }
        return $clave;
    }

    // Registro de quién hizo qué. NUNCA se guarda la contraseña.
    // Si la tabla panel_bitacora todavía no existe, solo se escribe al log de PHP.
    private function registrarBitacora(string $accion, string $sistema, int $tid, string $detalle): void {
        $adminId = (int) ($_SESSION['panel_admin_id'] ?? 0);
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
        error_log("[SOLTEC admin] $accion sistema=$sistema tenant=$tid admin=$adminId ip=$ip $detalle");
        try {
            conexionAdmin()->prepare(
                "INSERT INTO panel_bitacora (admin_id, accion, sistema, tenant_id, detalle, ip)
                 VALUES (:a, :ac, :s, :t, :d, :ip)")
                ->execute([':a' => $adminId, ':ac' => $accion, ':s' => $sistema,
                           ':t' => $tid, ':d' => $detalle, ':ip' => $ip]);
        } catch (Throwable $e) {
            error_log('[SOLTEC admin] Bitácora no disponible: ' . $e->getMessage());
        }
    }

    // ══════════════════ FUNCIONES OPCIONALES POR NEGOCIO ══════════════════
    // Las mejoras que se cobran aparte (fotos, sincronización...) viven APAGADAS: aquí se
    // encienden o apagan por negocio. Cada sistema las guarda en su propia base (tabla negocio_funciones).

    // ── Pantalla: funciones de un cliente ──
    public function funciones($id = null) {
        $sistema  = $this->resolverSistema();
        $catalogo = catalogoFunciones($sistema);
        $tid      = intval($id);

        $tabla = $this->tablaTenants();
        $st = $this->db()->prepare("SELECT id, nombre_negocio, activo, plan FROM `$tabla` WHERE id = :id");
        $st->execute([':id' => $tid]);
        $cliente = $st->fetch();
        if (!$cliente) {
            $_SESSION['error'] = 'Cliente no encontrado.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }

        $migrado = true;
        $activas = [];      // clave => activo (0/1)
        $valores = [];      // clave => valor del parametro (ej. id del negocio en Brix)
        if ($catalogo) {
            try {
                $s = $this->db()->prepare("SELECT funcion, activo, valor FROM negocio_funciones WHERE tenant_id = :t");
                $s->execute([':t' => $tid]);
                foreach ($s->fetchAll() as $f) { $activas[$f['funcion']] = (int) $f['activo']; $valores[$f['funcion']] = $f['valor']; }
            } catch (Throwable $e) {
                $migrado = false;   // todavia no se corrio la migracion de ese sistema
                error_log('[SOLTEC admin] negocio_funciones no disponible en ' . $sistema . ': ' . $e->getMessage());
            }
        }

        // Para "Sincronizar con Brix": negocios de Brix disponibles + estado de la cola de este cliente
        $negociosBrix = [];
        $colaEstado   = null;
        if ($migrado && $sistema === 'pos' && isset($catalogo['sync_brix'])) {
            try {
                $negociosBrix = obtenerConexion('brix')->query(
                    "SELECT id, nombre_negocio FROM negocios WHERE activo = 1 ORDER BY nombre_negocio")->fetchAll();
            } catch (Throwable $e) {
                error_log('[SOLTEC admin] No se pudo leer negocios de Brix: ' . $e->getMessage());
            }
            try {
                $q = $this->db()->prepare(
                    "SELECT estado, COUNT(*) AS n FROM sync_brix_cola WHERE tenant_id = :t GROUP BY estado");
                $q->execute([':t' => $tid]);
                $colaEstado = ['pendiente' => 0, 'enviado' => 0, 'error' => 0];
                foreach ($q->fetchAll() as $r) $colaEstado[$r['estado']] = (int) $r['n'];
                $ult = $this->db()->prepare(
                    "SELECT referencia, ultimo_error FROM sync_brix_cola WHERE tenant_id = :t AND estado IN ('pendiente','error') AND ultimo_error IS NOT NULL ORDER BY id DESC LIMIT 1");
                $ult->execute([':t' => $tid]);
                $colaEstado['ultimo_error'] = $ult->fetch() ?: null;
            } catch (Throwable $e) {
                $colaEstado = null;   // falta la migracion de la cola
            }
        }

        // Resumen de sucursales (para la tarjeta de "Sucursales")
        $sucursalesInfo = null;
        if ($migrado && $sistema === 'pos' && isset($catalogo['sucursales'])) {
            try {
                $q = $this->db()->prepare("SELECT SUM(activo = 1) AS activas, COUNT(*) AS total FROM sucursales WHERE tenant_id = :t");
                $q->execute([':t' => $tid]);
                $r = $q->fetch();
                $sucursalesInfo = ['activas' => (int) ($r['activas'] ?? 0), 'total' => (int) ($r['total'] ?? 0)];
            } catch (Throwable $e) { $sucursalesInfo = null; }
        }

        $tituloPagina = 'Funciones — ' . $cliente['nombre_negocio'];
        include RAIZ . '/app/vistas/panel/funciones.php';
    }

    // ── Guardar: enciende/apaga las funciones marcadas ──
    public function guardarFunciones() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }
        $sistema  = $this->resolverSistema();
        $tid      = intval($_POST['id'] ?? 0);
        $catalogo = catalogoFunciones($sistema);
        $volver   = URL_BASE . '/?url=panel/funciones/' . $tid;

        // El numero de cliente existe en todos los sistemas: si cambiaste de sistema en otra pestaña, se cancela
        if (($_POST['sistema'] ?? '') !== $sistema || !$catalogo || $tid <= 0) {
            $_SESSION['error'] = 'El sistema activo cambió mientras tenías esta pantalla abierta. Abre el cliente de nuevo.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }

        $tabla = $this->tablaTenants();
        $st = $this->db()->prepare("SELECT id FROM `$tabla` WHERE id = :id");
        $st->execute([':id' => $tid]);
        if (!$st->fetch()) {
            $_SESSION['error'] = 'Cliente no encontrado.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }

        $marcadas = (isset($_POST['f']) && is_array($_POST['f'])) ? $_POST['f'] : [];
        $params   = (isset($_POST['p']) && is_array($_POST['p'])) ? $_POST['p'] : [];
        $db = $this->db();
        try {
            $actuales = []; $valoresActuales = [];
            $s = $db->prepare("SELECT funcion, activo, valor FROM negocio_funciones WHERE tenant_id = :t");
            $s->execute([':t' => $tid]);
            foreach ($s->fetchAll() as $f) { $actuales[$f['funcion']] = (int) $f['activo']; $valoresActuales[$f['funcion']] = $f['valor']; }

            $cambios  = [];
            $aGuardar = [];
            foreach ($catalogo as $clave => $def) {
                if (empty($def['disponible'])) continue;           // las "Próximamente" no se tocan
                $nuevo = isset($marcadas[$clave]) ? 1 : 0;
                $valor = $valoresActuales[$clave] ?? null;

                // Funciones que necesitan un dato extra (hoy: a qué negocio de Brix se envía)
                if (($def['parametro'] ?? '') === 'negocio_brix') {
                    $idBrix = intval($params[$clave] ?? 0);
                    if ($nuevo === 1) {
                        $existe = false;
                        if ($idBrix > 0) {
                            $b = obtenerConexion('brix')->prepare("SELECT id FROM negocios WHERE id = :id AND activo = 1");
                            $b->execute([':id' => $idBrix]);
                            $existe = (bool) $b->fetchColumn();
                        }
                        if (!$existe) {
                            $_SESSION['error'] = 'Para encender "' . $def['nombre'] . '" elige el negocio de Brix al que se enviarán los movimientos.';
                            header('Location: ' . $volver); exit;
                        }
                        $valor = (string) $idBrix;
                    }
                }

                $cambioActivo = (($actuales[$clave] ?? 0) !== $nuevo);
                $cambioValor  = ($nuevo === 1 && (string) ($valoresActuales[$clave] ?? '') !== (string) $valor);
                if (!$cambioActivo && !$cambioValor) continue;
                $aGuardar[$clave] = [$nuevo, $valor];
                $cambios[$clave]  = [$nuevo, $valor, $cambioActivo];
            }

            $db->beginTransaction();
            $up = $db->prepare(
                "INSERT INTO negocio_funciones (tenant_id, funcion, activo, valor) VALUES (:t, :f, :a, :v)
                 ON DUPLICATE KEY UPDATE activo = VALUES(activo), valor = VALUES(valor)");
            foreach ($aGuardar as $clave => [$nuevo, $valor]) {
                $up->execute([':t' => $tid, ':f' => $clave, ':a' => $nuevo, ':v' => $valor]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[SOLTEC admin] guardarFunciones fallo: ' . $e->getMessage());
            $_SESSION['error'] = 'No se pudieron guardar las funciones. ¿Ya corriste las migraciones SQL de ese sistema?';
            header('Location: ' . $volver); exit;
        }

        foreach ($cambios as $clave => [$nuevo, $valor, $cambioActivo]) {
            $accion = $cambioActivo ? ($nuevo ? 'funcion_activada' : 'funcion_desactivada') : 'funcion_vinculo_cambiado';
            $this->registrarBitacora($accion, $sistema, $tid, $clave . ($valor !== null && $nuevo ? ' → ' . $valor : ''));
        }
        $_SESSION['exito'] = $cambios ? 'Funciones actualizadas.' : 'No había cambios que guardar.';
        header('Location: ' . $volver); exit;
    }

    // ── Enviar a Brix ahora lo que el POS tiene en cola ──
    public function sincronizarBrix() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }
        $tid    = intval($_POST['id'] ?? 0);
        $volver = URL_BASE . '/?url=panel/funciones/' . $tid;
        if (($_POST['sistema'] ?? '') !== 'pos' || $this->resolverSistema() !== 'pos') {
            $_SESSION['error'] = 'Esta acción solo existe para el POS.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }
        try {
            require_once RAIZ . '/config/sync_brix.php';
            $r = sincronizarPosBrix();
            $this->registrarBitacora('sync_brix_manual', 'pos', $tid, "enviados={$r['enviados']} errores={$r['errores']}");
            $_SESSION['exito'] = "Sincronización lista: {$r['enviados']} enviado(s), {$r['errores']} con error.";
        } catch (Throwable $e) {
            error_log('[SOLTEC admin] sincronizarBrix fallo: ' . $e->getMessage());
            $_SESSION['error'] = 'No se pudo sincronizar. ¿Ya corriste las migraciones de Brix y del POS?';
        }
        header('Location: ' . $volver); exit;
    }

    // ══════════════════ SUCURSALES DEL CLIENTE (solo POS) ══════════════════
    // Las crea SOLTEC desde aquí (Q100 al mes por sucursal activa). Requiere la función "Sucursales" encendida.

    private function funcionSucursalesActiva(int $tid): bool {
        $f = $this->db()->prepare("SELECT activo FROM negocio_funciones WHERE tenant_id = :t AND funcion = 'sucursales'");
        $f->execute([':t' => $tid]);
        return (bool) $f->fetchColumn();
    }

    public function sucursales($id = null) {
        $sistema = $this->resolverSistema();
        if ($sistema !== 'pos') {
            $_SESSION['error'] = 'Las sucursales solo existen para el POS.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }
        $tid   = intval($id);
        $tabla = $this->tablaTenants();
        $st = $this->db()->prepare("SELECT id, nombre_negocio, plan FROM `$tabla` WHERE id = :id");
        $st->execute([':id' => $tid]);
        $cliente = $st->fetch();
        if (!$cliente) {
            $_SESSION['error'] = 'Cliente no encontrado.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }

        $migrado = true; $funcionActiva = false; $lista = [];
        try {
            $funcionActiva = $this->funcionSucursalesActiva($tid);
            $l = $this->db()->prepare("SELECT * FROM sucursales WHERE tenant_id = :t ORDER BY activo DESC, nombre");
            $l->execute([':t' => $tid]);
            $lista = $l->fetchAll();
        } catch (Throwable $e) {
            $migrado = false;
            error_log('[SOLTEC admin] sucursales no disponible: ' . $e->getMessage());
        }

        $tituloPagina = 'Sucursales — ' . $cliente['nombre_negocio'];
        include RAIZ . '/app/vistas/panel/sucursales.php';
    }

    public function guardarSucursal() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit; }
        $tid    = intval($_POST['id'] ?? 0);
        $volver = URL_BASE . '/?url=panel/sucursales/' . $tid;
        if (($_POST['sistema'] ?? '') !== 'pos' || $this->resolverSistema() !== 'pos' || $tid <= 0) {
            $_SESSION['error'] = 'El sistema activo cambió mientras tenías esta pantalla abierta. Abre el cliente de nuevo.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }
        $nombre    = trim((string) ($_POST['nombre'] ?? ''));
        $direccion = trim((string) ($_POST['direccion'] ?? ''));
        $telefono  = trim((string) ($_POST['telefono'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 100 || mb_strlen($direccion) > 255 || mb_strlen($telefono) > 20) {
            $_SESSION['error'] = 'Revisa los datos: el nombre es obligatorio (máximo 100 caracteres).';
            header('Location: ' . $volver); exit;
        }
        try {
            if (!$this->funcionSucursalesActiva($tid)) {
                $_SESSION['error'] = 'Primero enciende la función "Sucursales" de este cliente (Funciones) y luego crea sus sucursales.';
                header('Location: ' . $volver); exit;
            }
            $this->db()->prepare("INSERT INTO sucursales (tenant_id, nombre, direccion, telefono) VALUES (:t, :n, :d, :tel)")
                 ->execute([':t' => $tid, ':n' => $nombre, ':d' => ($direccion !== '' ? $direccion : null), ':tel' => ($telefono !== '' ? $telefono : null)]);
        } catch (Throwable $e) {
            $_SESSION['error'] = mensajeError($e, 'No se pudo crear la sucursal. ¿Ya corriste la migración SQL de sucursales?');
            header('Location: ' . $volver); exit;
        }
        $this->registrarBitacora('sucursal_creada', 'pos', $tid, $nombre);
        $_SESSION['exito'] = 'Sucursal "' . $nombre . '" creada. Suma Q' . SUCURSAL_PRECIO_MES . ' al mes a este cliente.';
        header('Location: ' . $volver); exit;
    }

    public function estadoSucursal() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit; }
        $tid    = intval($_POST['id'] ?? 0);
        $sid    = intval($_POST['sucursal_id'] ?? 0);
        $activo = !empty($_POST['activo']) ? 1 : 0;
        $volver = URL_BASE . '/?url=panel/sucursales/' . $tid;
        if (($_POST['sistema'] ?? '') !== 'pos' || $this->resolverSistema() !== 'pos' || $tid <= 0 || $sid <= 0) {
            $_SESSION['error'] = 'Datos incompletos.';
            header('Location: ' . URL_BASE . '/?url=panel/clientes'); exit;
        }
        try {
            if (!$activo) {
                // No se desactiva una sucursal con cajas abiertas: nadie podria cerrarlas despues y su cierre
                // (con el monto contado y el ingreso hacia Brix) se perderia.
                $c = $this->db()->prepare(
                    "SELECT u.nombre, cs.abierta_en
                     FROM caja_sesiones cs JOIN usuarios u ON u.id = cs.usuario_id
                     WHERE cs.tenant_id = :t AND cs.sucursal_id = :s AND cs.estado = 'abierta'
                     ORDER BY cs.abierta_en");
                $c->execute([':t' => $tid, ':s' => $sid]);
                $abiertas = $c->fetchAll();
                if ($abiertas) {
                    $quienes = array_map(fn($f) => $f['nombre'] . ' (desde las ' . date('H:i', strtotime($f['abierta_en'])) . ')', $abiertas);
                    $_SESSION['error'] = 'No se puede desactivar: esta sucursal tiene ' . count($abiertas) . ' caja(s) abierta(s): '
                        . implode(', ', $quienes) . '. Pide que la(s) cierren y vuelve a intentar.';
                    header('Location: ' . $volver); exit;
                }
            }
            $this->db()->prepare("UPDATE sucursales SET activo = :a WHERE id = :id AND tenant_id = :t")
                 ->execute([':a' => $activo, ':id' => $sid, ':t' => $tid]);
        } catch (Throwable $e) {
            $_SESSION['error'] = mensajeError($e, 'No se pudo cambiar el estado de la sucursal.');
            header('Location: ' . $volver); exit;
        }
        $this->registrarBitacora($activo ? 'sucursal_activada' : 'sucursal_desactivada', 'pos', $tid, '#' . $sid);
        $_SESSION['exito'] = $activo ? 'Sucursal activada.' : 'Sucursal desactivada (sus datos se conservan).';
        header('Location: ' . $volver); exit;
    }

    // ══════════════════ SEGURIDAD: VERIFICACIÓN EN DOS PASOS (2FA) ══════════════════

    private function adminActual(): array {
        $st = conexionAdmin()->prepare("SELECT * FROM panel_admin WHERE id = :id LIMIT 1");
        $st->execute([':id' => (int) $_SESSION['panel_admin_id']]);
        return $st->fetch() ?: [];
    }

    private function volverSeguridad(string $tipo, string $mensaje): void {
        $_SESSION[$tipo] = $mensaje;
        header('Location: ' . URL_BASE . '/?url=panel/seguridad'); exit;
    }

    // Cambios sensibles de 2FA: piden la contraseña actual Y un código (o de recuperación)
    // Devuelve '' si todo esta bien, o el mensaje de error.
    private function confirmarIdentidad(array $admin, string $clave, string $codigo, string $llave): string {
        $minutos = loginBloqueado($llave);
        if ($minutos > 0) return "Demasiados intentos. Prueba de nuevo en $minutos minuto(s).";

        $ok = password_verify($clave, $admin['contrasena'] ?? '');
        if ($ok && !empty($admin['totp_activo'])) {
            $ok = false;
            $limpio = preg_replace('/\s+/', '', $codigo);
            if (preg_match('/^\d{6}$/', $limpio)) {
                $paso = totpVerificar($admin['totp_secreto'], $limpio, (int) ($admin['totp_ultimo_paso'] ?? 0));
                if ($paso !== null) {
                    $up = conexionAdmin()->prepare(
                        "UPDATE panel_admin SET totp_ultimo_paso = :p
                         WHERE id = :id AND (totp_ultimo_paso IS NULL OR totp_ultimo_paso < :p2)");
                    $up->execute([':p' => $paso, ':p2' => $paso, ':id' => $admin['id']]);
                    $ok = $up->rowCount() === 1;
                }
            } elseif ($codigo !== '') {
                $ok = usarCodigoRecuperacion(conexionAdmin(), (int) $admin['id'], $codigo);
            }
        }
        if (!$ok) {
            registrarIntentoFallido($llave);
            return 'Contraseña o código incorrectos.';
        }
        limpiarIntentos($llave);
        return '';
    }

    // ── Pantalla de seguridad ──
    public function seguridad() {
        $admin    = $this->adminActual();
        $migrado  = array_key_exists('totp_activo', $admin);
        $activo   = $migrado && !empty($admin['totp_activo']);
        $secretoNuevo = null;
        $uri          = null;
        $restantes    = 0;

        if ($migrado && !$activo) {
            // El secreto nuevo vive solo en la sesion hasta que confirmes con un codigo valido
            if (empty($_SESSION['totp_secreto_nuevo'])) {
                $_SESSION['totp_secreto_nuevo'] = totpGenerarSecreto();
            }
            $secretoNuevo = $_SESSION['totp_secreto_nuevo'];
            $uri = totpUri($secretoNuevo, $admin['usuario']);
        }
        if ($activo) {
            $st = conexionAdmin()->prepare("SELECT COUNT(*) FROM panel_recuperacion WHERE admin_id = :a AND usado_en IS NULL");
            $st->execute([':a' => (int) $admin['id']]);
            $restantes = (int) $st->fetchColumn();
        }

        // Los codigos de recuperacion se muestran UNA sola vez
        $codigosNuevos = $_SESSION['codigos_recuperacion'] ?? null;
        unset($_SESSION['codigos_recuperacion']);

        $tituloPagina = 'Seguridad';
        include RAIZ . '/app/vistas/panel/seguridad.php';
    }

    // ── Activar 2FA: contraseña + primer código de la app ──
    public function activar2fa() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=panel/seguridad'); exit; }
        $admin = $this->adminActual();
        if (!array_key_exists('totp_activo', $admin)) $this->volverSeguridad('error', 'Falta ejecutar la migración de la base de datos (migracion_2fa_panel).');
        if (!empty($admin['totp_activo']))            $this->volverSeguridad('error', 'El 2FA ya está activo.');

        $secreto = (string) ($_SESSION['totp_secreto_nuevo'] ?? '');
        if ($secreto === '') $this->volverSeguridad('error', 'Se perdió el secreto. Vuelve a empezar.');

        $llave   = '2fa-activar:' . (int) $admin['id'];
        $minutos = loginBloqueado($llave);
        if ($minutos > 0) $this->volverSeguridad('error', "Demasiados intentos. Prueba de nuevo en $minutos minuto(s).");

        $paso = totpVerificar($secreto, (string) ($_POST['codigo'] ?? ''), 0);
        if (!password_verify((string) ($_POST['contrasena'] ?? ''), $admin['contrasena']) || $paso === null) {
            registrarIntentoFallido($llave);
            $this->volverSeguridad('error', 'Contraseña o código incorrectos. Revisa que la hora de tu teléfono sea automática.');
        }
        limpiarIntentos($llave);

        $db = conexionAdmin();
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE panel_admin SET totp_secreto = :s, totp_activo = 1, totp_ultimo_paso = :p WHERE id = :id")
               ->execute([':s' => $secreto, ':p' => $paso, ':id' => $admin['id']]);
            $codigos = reemplazarCodigosRecuperacion($db, (int) $admin['id']);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[SOLTEC admin] Activar 2FA falló: ' . $e->getMessage());
            $this->volverSeguridad('error', 'No se pudo activar el 2FA. Verifica que la migración esté aplicada.');
        }

        unset($_SESSION['totp_secreto_nuevo']);
        $_SESSION['panel_2fa'] = 1;
        $_SESSION['codigos_recuperacion'] = $codigos;
        regenerarSesion();
        registrarEventoPanel('2fa_activado');
        $this->volverSeguridad('exito', '2FA activado. Guarda tus códigos de recuperación: no se vuelven a mostrar.');
    }

    // ── Desactivar 2FA: contraseña + código ──
    public function desactivar2fa() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=panel/seguridad'); exit; }
        $admin = $this->adminActual();
        if (empty($admin['totp_activo'])) $this->volverSeguridad('error', 'El 2FA no está activo.');

        $error = $this->confirmarIdentidad($admin, (string) ($_POST['contrasena'] ?? ''), trim((string) ($_POST['codigo'] ?? '')), '2fa-cambio:' . (int) $admin['id']);
        if ($error !== '') $this->volverSeguridad('error', $error);

        $db = conexionAdmin();
        $db->prepare("UPDATE panel_admin SET totp_activo = 0, totp_secreto = NULL, totp_ultimo_paso = NULL WHERE id = :id")
           ->execute([':id' => $admin['id']]);
        $db->prepare("DELETE FROM panel_recuperacion WHERE admin_id = :a")->execute([':a' => $admin['id']]);
        unset($_SESSION['totp_secreto_nuevo']);
        $_SESSION['panel_2fa'] = 0;
        regenerarSesion();
        registrarEventoPanel('2fa_desactivado');
        $this->volverSeguridad('exito', 'Verificación en dos pasos desactivada.');
    }

    // ── Códigos de recuperación nuevos (invalidan los anteriores) ──
    public function regenerarCodigos() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=panel/seguridad'); exit; }
        $admin = $this->adminActual();
        if (empty($admin['totp_activo'])) $this->volverSeguridad('error', 'El 2FA no está activo.');

        $error = $this->confirmarIdentidad($admin, (string) ($_POST['contrasena'] ?? ''), trim((string) ($_POST['codigo'] ?? '')), '2fa-cambio:' . (int) $admin['id']);
        if ($error !== '') $this->volverSeguridad('error', $error);

        $_SESSION['codigos_recuperacion'] = reemplazarCodigosRecuperacion(conexionAdmin(), (int) $admin['id']);
        registrarEventoPanel('2fa_codigos_nuevos');
        $this->volverSeguridad('exito', 'Códigos de recuperación nuevos generados. Los anteriores ya no sirven.');
    }
}