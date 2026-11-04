<?php

class UsuarioControlador {

    private function db() { return obtenerConexion(); }

    private function tenantId(): int {
        return (int) $_SESSION['tenant_id'];
    }

    // ── Listado ────────────────────────────────────────────────
    public function index() {
        $stmt = $this->db()->prepare(
            "SELECT u.*, s.nombre AS sucursal_nombre
             FROM usuarios u
             LEFT JOIN sucursales s ON s.id = u.sucursal_id
             WHERE u.tenant_id = :tid
             ORDER BY u.rol ASC, u.nombre ASC"
        );
        $stmt->execute([':tid' => $this->tenantId()]);
        $usuarios = $stmt->fetchAll();

        $tituloPagina = 'Usuarios';
        include RAIZ . '/app/vistas/usuarios/index.php';
    }

    // ── Formulario nuevo ────────────────────────────────────────
    public function nuevo() {
        $sucursales = $this->obtenerSucursales();
        $tituloPagina = 'Nuevo Usuario';
        include RAIZ . '/app/vistas/usuarios/form.php';
    }

    // ── Guardar ─────────────────────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
        }

        $nombre      = trim($_POST['nombre'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $password    = trim($_POST['password'] ?? '');
        $rol         = $_POST['rol'] ?? 'trabajador';
        $sucursalId  = intval($_POST['sucursal_id'] ?? 0) ?: null;

        if (!$nombre || !$email || !$password) {
            $_SESSION['error'] = 'Nombre, correo y contraseña son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=usuarios/nuevo'); exit;
        }
        if (!in_array($rol, ['admin_negocio', 'trabajador'], true)) {
            $_SESSION['error'] = 'El rol seleccionado no es válido.';
            header('Location: ' . URL_BASE . '/?url=usuarios/nuevo'); exit;
        }
        if ($errorClave = validarContrasenaNueva($password, [$email, $nombre, $_SESSION['nombre_negocio'] ?? ''])) {
            $_SESSION['error'] = $errorClave;
            header('Location: ' . URL_BASE . '/?url=usuarios/nuevo'); exit;
        }

        // Email único por tenant
        $stmt = $this->db()->prepare(
            "SELECT id FROM usuarios WHERE tenant_id = :tid AND email = :email"
        );
        $stmt->execute([':tid' => $this->tenantId(), ':email' => $email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Ya existe un usuario con ese correo en este negocio.';
            header('Location: ' . URL_BASE . '/?url=usuarios/nuevo'); exit;
        }

        $this->db()->prepare(
            "INSERT INTO usuarios (tenant_id, sucursal_id, nombre, email, password, rol, activo)
             VALUES (:tid, :sid, :nombre, :email, :pass, :rol, 1)"
        )->execute([
            ':tid'    => $this->tenantId(),
            ':sid'    => $sucursalId,
            ':nombre' => $nombre,
            ':email'  => $email,
            ':pass'   => password_hash($password, PASSWORD_DEFAULT),
            ':rol'    => $rol,
        ]);

        $_SESSION['exito'] = "Usuario '$nombre' creado correctamente.";
        header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
    }

    // ── Editar ─────────────────────────────────────────────────
    public function editar($id) {
        $stmt = $this->db()->prepare(
            "SELECT * FROM usuarios WHERE id = :id AND tenant_id = :tid"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $_SESSION['error'] = 'Usuario no encontrado.';
            header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
        }

        $sucursales = $this->obtenerSucursales();
        $tituloPagina = 'Editar — ' . $usuario['nombre'];
        include RAIZ . '/app/vistas/usuarios/form.php';
    }

    // ── Actualizar ─────────────────────────────────────────────
    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
        }

        $id         = intval($_POST['id']);
        $nombre     = trim($_POST['nombre'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $rol        = $_POST['rol'] ?? 'trabajador';
        $sucursalId = intval($_POST['sucursal_id'] ?? 0) ?: null;
        $password   = trim($_POST['password'] ?? '');

        if (!$nombre || !$email) {
            $_SESSION['error'] = 'Nombre y correo son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=usuarios/editar/' . $id); exit;
        }
        if (!in_array($rol, ['admin_negocio', 'trabajador'], true)) {
            $_SESSION['error'] = 'El rol seleccionado no es válido.';
            header('Location: ' . URL_BASE . '/?url=usuarios/editar/' . $id); exit;
        }
        if ($password !== '' && ($errorClave = validarContrasenaNueva($password, [$email, $nombre, $_SESSION['nombre_negocio'] ?? '']))) {
            $_SESSION['error'] = $errorClave;
            header('Location: ' . URL_BASE . '/?url=usuarios/editar/' . $id); exit;
        }

        // Email único excluyendo el actual
        $stmt = $this->db()->prepare(
            "SELECT id FROM usuarios WHERE tenant_id = :tid AND email = :email AND id != :id"
        );
        $stmt->execute([':tid' => $this->tenantId(), ':email' => $email, ':id' => $id]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Ya existe otro usuario con ese correo.';
            header('Location: ' . URL_BASE . '/?url=usuarios/editar/' . $id); exit;
        }

        // Actualiza con o sin nueva contraseña
        if ($password) {
            $this->db()->prepare(
                "UPDATE usuarios SET nombre=:nombre, email=:email, rol=:rol,
                 sucursal_id=:sid, password=:pass WHERE id=:id AND tenant_id=:tid"
            )->execute([
                ':nombre' => $nombre, ':email' => $email, ':rol' => $rol,
                ':sid'    => $sucursalId,
                ':pass'   => password_hash($password, PASSWORD_DEFAULT),
                ':id'     => $id, ':tid'    => $this->tenantId(),
            ]);
        } else {
            $this->db()->prepare(
                "UPDATE usuarios SET nombre=:nombre, email=:email, rol=:rol,
                 sucursal_id=:sid WHERE id=:id AND tenant_id=:tid"
            )->execute([
                ':nombre' => $nombre, ':email' => $email, ':rol' => $rol,
                ':sid'    => $sucursalId,
                ':id'     => $id, ':tid'    => $this->tenantId(),
            ]);
        }

        $_SESSION['exito'] = 'Usuario actualizado correctamente.';
        header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
    }

    // ── Activar / Desactivar ──────────────────────────────────
    public function toggleActivo($id) {
        // Evita que el admin se desactive a sí mismo
        if ($id == $_SESSION['usuario_id']) {
            $_SESSION['error'] = 'No podés desactivar tu propio usuario.';
            header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
        }

        $stmt = $this->db()->prepare(
            "SELECT activo, nombre FROM usuarios WHERE id = :id AND tenant_id = :tid"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $_SESSION['error'] = 'Usuario no encontrado.';
            header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
        }

        $nuevo = $usuario['activo'] ? 0 : 1;
        $this->db()->prepare(
            "UPDATE usuarios SET activo = :a WHERE id = :id AND tenant_id = :tid"
        )->execute([':a' => $nuevo, ':id' => $id, ':tid' => $this->tenantId()]);

        $_SESSION['exito'] = $nuevo
            ? "'{$usuario['nombre']}' fue reactivado."
            : "'{$usuario['nombre']}' fue desactivado.";
        header('Location: ' . URL_BASE . '/?url=usuarios/index'); exit;
    }

    private function obtenerSucursales(): array {
        $stmt = $this->db()->prepare(
            "SELECT id, nombre FROM sucursales WHERE tenant_id = :tid AND activa = 1 ORDER BY nombre"
        );
        $stmt->execute([':tid' => $this->tenantId()]);
        return $stmt->fetchAll();
    }
}
