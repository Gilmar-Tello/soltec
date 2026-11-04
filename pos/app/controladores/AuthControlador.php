<?php

class AuthControlador {

    // Mostrar formulario de login
    public function login() {
        if (isset($_SESSION['usuario_id'])) {
            header('Location: ' . URL_BASE . '/?url=pos/index');
            exit;
        }
        // El login es previo a saber el tenant, así que se muestra branding genérico
        $config = null;
        include RAIZ . '/app/vistas/auth/login.php';
    }

    // Procesar el formulario de login
    public function entrar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        $usuario    = trim($_POST['usuario'] ?? '');
        $contrasena = trim($_POST['contrasena'] ?? '');

        if (empty($usuario) || empty($contrasena)) {
            $_SESSION['error'] = 'Por favor ingresá tu usuario y contraseña.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        // Límite de intentos: 5 fallos → 15 minutos de espera
        $minutos = loginBloqueado($usuario);
        if ($minutos > 0) {
            $_SESSION['error'] = "Demasiados intentos fallidos. Esperá $minutos minuto(s) e intentá de nuevo.";
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        $db  = obtenerConexion();
        // El nombre de usuario solo era único dentro de cada negocio, así que puede haber
        // varios "admin". Antes se tomaba el primero (LIMIT 1): un usuario podía quedar sin
        // poder entrar, o entrar al negocio equivocado si la contraseña coincidía.
        // Ahora se revisan todos y solo se entra si la contraseña coincide con UNO solo.
        $sql = "SELECT u.*, r.nombre AS rol_nombre, t.nombre_negocio, t.activo AS tenant_activo
                FROM usuarios u
                JOIN roles r ON u.rol_id = r.id
                JOIN tenants t ON u.tenant_id = t.id
                WHERE u.usuario = :usuario AND u.activo = 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([':usuario' => $usuario]);
        $coinciden = array_values(array_filter(
            $stmt->fetchAll(),
            fn($u) => password_verify($contrasena, $u['contrasena'])
        ));

        if (count($coinciden) === 0) {
            registrarIntentoFallido($usuario);
            $_SESSION['error'] = 'Usuario o contraseña incorrectos.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }
        if (count($coinciden) > 1) {
            error_log('[POS GT] Login ambiguo: el usuario "' . $usuario . '" existe con la misma contraseña en varios negocios.');
            $_SESSION['error'] = 'Este usuario necesita revisión. Contactá a soporte.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }
        $user = $coinciden[0];

        if (!$user['tenant_activo']) {
            $_SESSION['error'] = 'Esta cuenta de negocio está suspendida por falta de pago u otro inconveniente. Contactá a soporte +502 54925395.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        // Fecha de pago (la fija el panel maestro): vale hasta las 23:59 de ese día.
        $diasPago = $this->diasParaPago($db, (int) $user['tenant_id']);
        if ($diasPago !== null && $diasPago < 0) {
            $_SESSION['error'] = 'Esta cuenta de negocio está suspendida por falta de pago u otro inconveniente. Contactá a soporte +502 54925395.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        limpiarIntentos($usuario);

        // Nuevo ID de sesión al entrar: evita que alguien "fije" una sesión robada
        session_regenerate_id(true);

        // Guardar datos en sesión
        $_SESSION['usuario_id']    = $user['id'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_rol']    = $user['rol_nombre'];
        $_SESSION['usuario_rol_id'] = $user['rol_id'];
        $_SESSION['tenant_id']      = $user['tenant_id'];
        $_SESSION['tenant_nombre']  = $user['nombre_negocio'];

        // Aviso de pago: solo desde 3 días antes; se muestra una vez (plantillas/aviso_pago.php)
        unset($_SESSION['aviso_pago_dias']);
        if ($diasPago !== null && $diasPago <= self::AVISO_PAGO_DIAS) {
            $_SESSION['aviso_pago_dias'] = $diasPago;
        }

        header('Location: ' . URL_BASE . '/?url=pos/index');
        exit;
    }

    // Días que faltan para la fecha de pago del negocio (negativo = ya venció, null = sin fecha).
    // Va en una consulta aparte: si la columna fecha_pago aún no existe en la base, el login no se rompe.
    private const AVISO_PAGO_DIAS = 3;

    private function diasParaPago(PDO $db, int $tenantId): ?int {
        try {
            $st = $db->prepare("SELECT DATEDIFF(fecha_pago, CURDATE()) FROM tenants WHERE id = :t");
            $st->execute([':t' => $tenantId]);
            $dias = $st->fetchColumn();
            return ($dias === false || $dias === null) ? null : (int) $dias;
        } catch (Throwable $e) {
            error_log('[POS GT] No se pudo leer fecha_pago: ' . $e->getMessage());
            return null;
        }
    }

    // Cerrar sesión
    public function salir() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: ' . URL_BASE . '/?url=auth/login');
        exit;
    }
}