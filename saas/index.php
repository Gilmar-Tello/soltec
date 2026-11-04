<?php

// ============================================================
//  Alquileres SOLTEC — punto de entrada (saas.soltec.click)
// ============================================================

date_default_timezone_set('America/Guatemala');

define('RAIZ', __DIR__);
define('URL_BASE', 'https://saas.soltec.click');

// Carpeta privada: /home/u923763167/privado  (fuera de public_html)
define('PRIVADO', dirname(RAIZ, 2) . '/privado');

// ── Seguridad: errores ocultos, cookie endurecida, CSRF ────
require_once RAIZ . '/config/seguridad.php';
configurarErrores();
enviarCabecerasSeguridad();
iniciarSesionSegura('SOLTEC_ALQ');

// ── Credenciales: privadas si existen, locales si es XAMPP ──
$archivoBD = PRIVADO . '/saas_base_datos.php';
require_once file_exists($archivoBD) ? $archivoBD : RAIZ . '/config/base_datos.php';

// ── Sesion vigente: inactividad + usuario y negocio siguen activos ──
exigirSesionVigente();

require_once RAIZ . '/config/permisos.php';

// ── Utilidades compartidas ─────────────────────────────────
// fechas.php:   formato de fechas sin conversion de zona horaria
// imagenes.php: subida y compresion de fotos
// mensajes.php: texto de WhatsApp en un solo lugar
// sucursales.php: de qué sucursal sale el stock (vitrina → su bodega)
// disponibilidad.php: cuántas unidades están libres en un rango de fechas
require_once RAIZ . '/config/fechas.php';
require_once RAIZ . '/config/imagenes.php';
require_once RAIZ . '/config/mensajes.php';
require_once RAIZ . '/config/sucursales.php';
require_once RAIZ . '/config/disponibilidad.php';

require_once RAIZ . '/rutas/web.php';
