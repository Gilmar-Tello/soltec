<?php

class AuthControlador {

    // panel_admin vive en la base PROPIA del panel (u923763167_admin).
    // Antes estaba dentro de la base del POS, con lo cual la credencial
    // del POS daba acceso al login maestro. Ya no: ninguna credencial
    // de un producto vendido puede leer ni tocar esta tabla.
    private function db() { return conexionAdmin(); }

    public function login() {
        if (isset($_SESSION['panel_admin_id'])) {
            header('Location: ' . URL_BASE . '/?url=panel/index');
            exit;
        }
        include RAIZ . '/app/vistas/auth/login.php';
    }

    public function entrar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        $usuario    = trim($_POST['usuario'] ?? '');
        $contrasena = (string) ($_POST['contrasena'] ?? '');

        if ($usuario === '' || $contrasena === '') {
            $_SESSION['error'] = 'Por favor ingresa tu usuario y contrasena.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        // ── Freno a la fuerza bruta: 5 fallos = 15 minutos de espera ──
        $minutos = loginBloqueado($usuario);
        if ($minutos > 0) {
            $_SESSION['error'] = "Demasiados intentos fallidos. Proba de nuevo en $minutos minuto(s).";
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        $stmt = $this->db()->prepare("SELECT * FROM panel_admin WHERE usuario = :usuario LIMIT 1");
        $stmt->execute([':usuario' => $usuario]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($contrasena, $admin['contrasena'])) {
            registrarIntentoFallido($usuario);
            $_SESSION['error'] = 'Usuario o contrasena incorrectos.';
            header('Location: ' . URL_BASE . '/?url=auth/login');
            exit;
        }

        limpiarIntentos($usuario);

        // ID de sesion nuevo: uno robado antes del login ya no sirve
        regenerarSesion();

        // ── Segundo paso: si tiene 2FA activo, todavia NO entra al panel ──
        // (las columnas totp_* existen solo despues de correr la migracion; sin ellas, login normal)
        if (!empty($admin['totp_activo']) && !empty($admin['totp_secreto'])) {
            $_SESSION['2fa_pendiente'] = ['id' => (int) $admin['id'], 'hasta' => time() + 300];
            header('Location: ' . URL_BASE . '/?url=auth/verificar');
            exit;
        }

        $this->abrirSesionPanel($admin, !empty($admin['totp_activo']));
        header('Location: ' . URL_BASE . '/?url=panel/index');
        exit;
    }

    private function abrirSesionPanel(array $admin, bool $con2fa): void {
        $_SESSION['panel_admin_id']     = $admin['id'];
        $_SESSION['panel_admin_nombre'] = $admin['nombre'];
        $_SESSION['panel_2fa']          = $con2fa ? 1 : 0;   // solo para mostrar el aviso en el menu
        $_SESSION['ultima_actividad']   = time();
    }

    // Verificacion pendiente valida (5 minutos desde que acerto la contrasena)
    private function pendiente2fa(): ?int {
        $p = $_SESSION['2fa_pendiente'] ?? null;
        if (!is_array($p) || time() > (int) ($p['hasta'] ?? 0)) {
            unset($_SESSION['2fa_pendiente']);
            return null;
        }
        return (int) $p['id'];
    }

    // ── Pantalla del segundo paso ──
    public function verificar() {
        if ($this->pendiente2fa() === null) {
            $_SESSION['error'] = 'La verificación expiró. Ingresa de nuevo.';
            header('Location: ' . URL_BASE . '/?url=auth/login'); exit;
        }
        include RAIZ . '/app/vistas/auth/verificar.php';
    }

    // ── Comprobar el codigo (app de autenticacion o codigo de recuperacion) ──
    public function confirmar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=auth/login'); exit;
        }
        $id = $this->pendiente2fa();
        if ($id === null) {
            $_SESSION['error'] = 'La verificación expiró. Ingresa de nuevo.';
            header('Location: ' . URL_BASE . '/?url=auth/login'); exit;
        }

        // Freno propio: 5 codigos mal = 15 minutos (aparte del freno de la contrasena)
        $llave   = '2fa:' . $id;
        $minutos = loginBloqueado($llave);
        if ($minutos > 0) {
            $_SESSION['error'] = "Demasiados intentos. Prueba de nuevo en $minutos minuto(s).";
            header('Location: ' . URL_BASE . '/?url=auth/verificar'); exit;
        }

        $codigo = trim((string) ($_POST['codigo'] ?? ''));
        $st = $this->db()->prepare("SELECT * FROM panel_admin WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $admin = $st->fetch();

        $ok  = false;
        $via = 'app';
        if ($admin && !empty($admin['totp_secreto'])) {
            if (preg_match('/^\d{6}$/', preg_replace('/\s+/', '', $codigo))) {
                $paso = totpVerificar($admin['totp_secreto'], $codigo, (int) ($admin['totp_ultimo_paso'] ?? 0));
                if ($paso !== null) {
                    // Solo acepta el paso si nadie lo uso antes (evita repetir un codigo espiado)
                    $up = $this->db()->prepare(
                        "UPDATE panel_admin SET totp_ultimo_paso = :p
                         WHERE id = :id AND (totp_ultimo_paso IS NULL OR totp_ultimo_paso < :p2)");
                    $up->execute([':p' => $paso, ':p2' => $paso, ':id' => $id]);
                    $ok = $up->rowCount() === 1;
                }
            } elseif ($codigo !== '') {
                $ok  = usarCodigoRecuperacion($this->db(), $id, $codigo);
                $via = 'recuperacion';
            }
        }

        if (!$ok) {
            registrarIntentoFallido($llave);
            registrarEventoPanel('2fa_fallido', '', $id);
            $_SESSION['error'] = 'Código incorrecto o vencido.';
            header('Location: ' . URL_BASE . '/?url=auth/verificar'); exit;
        }

        limpiarIntentos($llave);
        unset($_SESSION['2fa_pendiente']);
        regenerarSesion();
        $this->abrirSesionPanel($admin, true);
        registrarEventoPanel('login_2fa', 'via=' . $via, $id);
        if ($via === 'recuperacion') {
            $_SESSION['exito'] = 'Entraste con un código de recuperación (ya no sirve). Genera códigos nuevos en Seguridad.';
        }
        header('Location: ' . URL_BASE . '/?url=panel/index');
        exit;
    }

    public function salir() {
        cerrarSesionSegura();
        header('Location: ' . URL_BASE . '/?url=auth/login');
        exit;
    }
}
