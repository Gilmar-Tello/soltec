<?php

// ============================================================
//  POS GT SOLTEC — punto de entrada (pos.soltec.click)
// ============================================================

// Zona horaria de Guatemala (UTC-6) — corrige fechas de ventas, tickets, etc.
date_default_timezone_set('America/Guatemala');

define('RAIZ', __DIR__);
define('URL_BASE', 'https://pos.soltec.click');

// Carpeta privada: /home/u923763167/privado  (fuera de public_html)
define('PRIVADO', dirname(RAIZ, 2) . '/privado');

// Seguridad: errores ocultos en produccion, cookie de sesion segura
require_once RAIZ . '/config/seguridad.php';
configurarErrores();
enviarCabecerasSeguridad();
iniciarSesionSegura();
exigirSesionNoCaducada();

// Paginas con sesion: que el navegador no las guarde (ni las muestre con la flecha "atras"
// despues de cerrar sesion). Los estaticos (css/js/img) los sirve Apache directo y no cambian.
if (!empty($_SESSION['usuario_id'])) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

// ── Credenciales: privadas si existen, locales si es XAMPP ──
$archivoBD = PRIVADO . '/pos_base_datos.php';
require_once file_exists($archivoBD) ? $archivoBD : RAIZ . '/config/base_datos.php';
require_once RAIZ . '/config/sync_brix.php';
require_once RAIZ . '/config/funciones_catalogo.php';
require_once RAIZ . '/config/planes.php';
require_once RAIZ . '/config/sucursales.php';
require_once RAIZ . '/config/costos.php';

// Cargar el router
require_once RAIZ . '/rutas/web.php';
