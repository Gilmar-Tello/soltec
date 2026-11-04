<?php

class AuthControlador {

    private function db() { return obtenerConexion(); }

    public function login() {
        if (isset($_SESSION['usuario_id'])) {
            header('Location: ' . URL_BASE . '/?url=dashboard/index');
            exit;
        }
        include RAIZ . '/app/vistas/auth/login.php';
    }

    public function entrar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        $usuarioIngresado = trim($_POST['usuario'] ?? '');
        $contrasena       = (string) ($_POST['contrasena'] ?? '');

        if ($usuarioIngresado === '' || $contrasena === '') {
            $_SESSION['error'] = 'Ingresa tu usuario y contrasena.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        // ── Freno a la fuerza bruta: 5 fallos = 15 minutos de espera ──
        $minutos = loginBloqueado($usuarioIngresado);
        if ($minutos > 0) {
            $_SESSION['error'] = "Demasiados intentos fallidos. Proba de nuevo en $minutos minuto(s).";
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        // ── Multi-tenant ────────────────────────────────────────
        // El usuario puede repetirse entre negocios distintos, asi que
        // se traen todas las coincidencias y gana la que valide la
        // contrasena. Con LIMIT 1 el segundo negocio nunca entraria.
        $stmt = $this->db()->prepare(
            "SELECT u.*, n.nombre_negocio, n.activo AS negocio_activo
             FROM usuarios u
             INNER JOIN negocios n ON n.id = u.tenant_id
             WHERE u.usuario = :usuario"
        );
        $stmt->execute([':usuario' => $usuarioIngresado]);
        $candidatos = $stmt->fetchAll();

        $coinciden = array_values(array_filter(
            $candidatos,
            fn($fila) => password_verify($contrasena, $fila['contrasena'])
        ));
        // Si la misma clave sirve en dos negocios con ese usuario, no se adivina a cual entrar
        if (count($coinciden) > 1) {
            error_log('[SOLTEC brix] Login ambiguo: el usuario "' . $usuarioIngresado . '" existe con la misma contrasena en varios negocios.');
            $_SESSION['error'] = 'Esta cuenta necesita revision. Contacta a soporte.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }
        $user = $coinciden[0] ?? null;

        if (!$user) {
            registrarIntentoFallido($usuarioIngresado);
            $_SESSION['error'] = 'Usuario o contrasena incorrectos.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        if (!$user['activo']) {
            $_SESSION['error'] = 'Tu usuario esta desactivado.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        if (!$user['negocio_activo']) {
            $_SESSION['error'] = 'Este negocio esta suspendido. Contacta a soporte.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        limpiarIntentos($usuarioIngresado);

        // ID de sesion nuevo: uno robado antes del login ya no sirve
        regenerarSesion();

        $_SESSION['usuario_id']     = $user['id'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_rol']    = $user['rol'];
        $_SESSION['tenant_id']      = $user['tenant_id'];
        $_SESSION['nombre_negocio'] = $user['nombre_negocio'];

        header('Location: ' . URL_BASE . '/?url=dashboard/index');
        exit;
    }

    public function salir() {
        cerrarSesionSegura();
        header('Location: ' . URL_BASE . '/?url=auth/login');
        exit;
    }
}
