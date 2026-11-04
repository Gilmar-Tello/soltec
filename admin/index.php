<?php

// ============================================================
//  Panel Maestro SOLTEC — punto de entrada
//  admin.soltec.click  ->  public_html/admin
// ============================================================

date_default_timezone_set('America/Guatemala');

define('RAIZ', __DIR__);
define('URL_BASE', 'https://admin.soltec.click');

// Carpeta privada: /home/u923763167/privado  (fuera de public_html)
define('PRIVADO', dirname(RAIZ, 2) . '/privado');

// ── Seguridad: errores ocultos, cookie endurecida, CSRF ────
require_once RAIZ . '/config/seguridad.php';
require_once RAIZ . '/config/totp.php';
require_once RAIZ . '/config/funciones.php';
configurarErrores();
enviarCabecerasSeguridad();
iniciarSesionSegura('SOLTEC_PANEL');

// ── Expiracion por inactividad ─────────────────────────────
define('SESSION_TIMEOUT', 1800); // 30 minutos

if (isset($_SESSION['panel_admin_id'])) {
    $ultimaActividad = $_SESSION['ultima_actividad'] ?? 0;
    if ((time() - $ultimaActividad) > SESSION_TIMEOUT) {
        cerrarSesionSegura();
        iniciarSesionSegura('SOLTEC_PANEL');
        $_SESSION['error'] = 'Tu sesion expiro por inactividad.';
        header('Location: ' . URL_BASE . '/?url=auth/login');
        exit;
    }
}
$_SESSION['ultima_actividad'] = time();

// ── Credenciales: siempre desde la carpeta privada ─────────
require_once PRIVADO . '/admin_base_datos.php';

require_once RAIZ . '/rutas/web.php';
