<?php

// ============================================================
//  Brix SOLTEC — punto de entrada (brix.soltec.click)
// ============================================================

date_default_timezone_set('America/Guatemala');

define('RAIZ', __DIR__);
define('URL_BASE', 'https://brix.soltec.click');

// Carpeta privada: /home/u923763167/privado  (fuera de public_html)
define('PRIVADO', dirname(RAIZ, 2) . '/privado');

// ── Seguridad: errores ocultos, cookie endurecida, CSRF ────
require_once RAIZ . '/config/seguridad.php';
configurarErrores();
enviarCabecerasSeguridad();
iniciarSesionSegura('SOLTEC_BRIX');

// ── Credenciales: privadas si existen, locales si es XAMPP ──
$archivoBD = PRIVADO . '/brix_base_datos.php';
require_once file_exists($archivoBD) ? $archivoBD : RAIZ . '/config/base_datos.php';

// ── Sesion vigente: inactividad + usuario y negocio siguen activos ──
exigirSesionVigente();

require_once RAIZ . '/rutas/web.php';
