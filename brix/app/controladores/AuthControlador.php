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

        // Fecha de pago (la fija el panel maestro): vale hasta las 23:59 de ese dia.
        $diasPago = $this->diasParaPago((int) $user['tenant_id']);
        if ($diasPago !== null && $diasPago < 0) {
            $_SESSION['error'] = 'Este negocio esta suspendido por falta de pago u otro inconveniente. Contacta a soporte +502 54925395.';
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

        // Aviso de pago: solo desde 3 dias antes; se muestra una vez (plantillas/aviso_pago.php)
        unset($_SESSION['aviso_pago_dias']);
        if ($diasPago !== null && $diasPago <= self::AVISO_PAGO_DIAS) {
            $_SESSION['aviso_pago_dias'] = $diasPago;
        }

        header('Location: ' . URL_BASE . '/?url=dashboard/index');
        exit;
    }

    // Dias que faltan para la fecha de pago del negocio (negativo = ya vencio, null = sin fecha).
    // Va en una consulta aparte: si la columna fecha_pago aun no existe en la base, el login no se rompe.
    private const AVISO_PAGO_DIAS = 3;

    private function diasParaPago(int $tenantId): ?int {
        try {
            $st = $this->db()->prepare("SELECT DATEDIFF(fecha_pago, CURDATE()) FROM negocios WHERE id = :t");
            $st->execute([':t' => $tenantId]);
            $dias = $st->fetchColumn();
            return ($dias === false || $dias === null) ? null : (int) $dias;
        } catch (Throwable $e) {
            error_log('[SOLTEC brix] No se pudo leer fecha_pago: ' . $e->getMessage());
            return null;
        }
    }

    public function salir() {
        cerrarSesionSegura();
        header('Location: ' . URL_BASE . '/?url=auth/login');
        exit;
    }
}
