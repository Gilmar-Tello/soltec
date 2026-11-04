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

        $email      = trim($_POST['email'] ?? '');
        $contrasena = (string) ($_POST['contrasena'] ?? '');

        if ($email === '' || $contrasena === '') {
            $_SESSION['error'] = 'Por favor ingresa tu correo y contrasena.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        // ── Freno a la fuerza bruta: 5 fallos = 15 minutos de espera ──
        $minutos = loginBloqueado($email);
        if ($minutos > 0) {
            $_SESSION['error'] = "Demasiados intentos fallidos. Proba de nuevo en $minutos minuto(s).";
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        // ── Multi-tenant ────────────────────────────────────────
        // El correo es unico POR NEGOCIO (uniq_email_tenant), asi que
        // dos negocios distintos SI pueden tener el mismo correo.
        // Por eso no se usa LIMIT 1: se traen todas las coincidencias
        // y gana aquella cuya contrasena valide. Sin esto, el segundo
        // cliente con un correo repetido nunca podria entrar.
        $stmt = $this->db()->prepare(
            "SELECT u.*, n.nombre_negocio, n.activo AS negocio_activo
             FROM usuarios u
             INNER JOIN negocios n ON n.id = u.tenant_id
             WHERE u.email = :email"
        );
        $stmt->execute([':email' => $email]);
        $candidatos = $stmt->fetchAll();

        $coinciden = array_values(array_filter(
            $candidatos,
            fn($fila) => password_verify($contrasena, $fila['password'])
        ));
        // Si la misma clave sirve en dos negocios con ese correo, no se adivina a cual entrar
        if (count($coinciden) > 1) {
            error_log('[SOLTEC alquileres] Login ambiguo: el correo "' . $email . '" existe con la misma contrasena en varios negocios.');
            $_SESSION['error'] = 'Esta cuenta necesita revision. Contacta a soporte.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }
        $usuario = $coinciden[0] ?? null;

        if (!$usuario) {
            registrarIntentoFallido($email);
            $_SESSION['error'] = 'Correo o contrasena incorrectos.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        if (!$usuario['activo']) {
            $_SESSION['error'] = 'Tu usuario esta desactivado. Contacta al administrador del negocio.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        if (!$usuario['negocio_activo']) {
            $_SESSION['error'] = 'Este negocio esta suspendido. Contacta a soporte.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        // Fecha de pago (la fija el panel maestro): vale hasta las 23:59 de ese dia.
        $diasPago = $this->diasParaPago((int) $usuario['tenant_id']);
        if ($diasPago !== null && $diasPago < 0) {
            $_SESSION['error'] = 'Este negocio esta suspendido por falta de pago u otro inconveniente. Contacta a soporte +502 54925395.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        limpiarIntentos($email);

        // ID de sesion nuevo: uno robado antes del login ya no sirve
        regenerarSesion();

        // Sesion multi-tenant: todo el sistema filtra por tenant_id desde aqui
        $_SESSION['usuario_id']      = $usuario['id'];
        $_SESSION['usuario_nombre']  = $usuario['nombre'];
        $_SESSION['usuario_rol']     = $usuario['rol'];
        $_SESSION['tenant_id']       = $usuario['tenant_id'];
        $_SESSION['sucursal_id']     = $usuario['sucursal_id'];
        $_SESSION['nombre_negocio']  = $usuario['nombre_negocio'];

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
            error_log('[SOLTEC alquileres] No se pudo leer fecha_pago: ' . $e->getMessage());
            return null;
        }
    }

    public function salir() {
        cerrarSesionSegura();
        header('Location: ' . URL_BASE . '/?url=auth/login');
        exit;
    }
}
