<?php

$ruta = $_GET['url'] ?? 'auth/login';
$ruta = rtrim($ruta, '/');
$partes = explode('/', $ruta);

$modulo    = $partes[0] ?? 'auth';
$accion    = $partes[1] ?? 'login';
$parametro = $partes[2] ?? null;

// ── Proteccion CSRF ──────────────────────────────────────────
// Todo POST, y las acciones por enlace que modifican datos, tienen
// que venir del propio sistema. Un <img src="...toggleActivo/3">
// puesto en un correo ya no puede suspender a un cliente.
$verbosDeEscritura = [
    'guardar', 'guardarcliente', 'actualizar', 'actualizarcliente',
    'eliminar', 'borrar', 'crear', 'activar', 'desactivar',
    'toggleactivo', 'toggleactiva', 'salir', 'restableceradmin',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    || isset($_GET['sistema'])   // ?sistema=... cambia el sistema activo guardado en la sesion
    || in_array(strtolower($accion), $verbosDeEscritura, true)) {
    exigirMismoSitio();
}

// ── Entrada saneada: solo letras en modulo y accion, ID numerico ──
if (!preg_match('/^[A-Za-z]+$/', $modulo) || !preg_match('/^[A-Za-z]+$/', $accion)
    || ($parametro !== null && !ctype_digit((string) $parametro))) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:20px;">
        <h2>404 — Pagina no encontrada</h2>
    </div>');
}
if ($parametro !== null) $parametro = (int) $parametro;

// Rutas protegidas — requieren sesion de admin del panel
// (la landing publica ya no vive aqui: esta en soltec.click)
$rutasProtegidas = ['panel', 'servicios'];

if (in_array($modulo, $rutasProtegidas)) {
    if (!isset($_SESSION['panel_admin_id'])) {
        header('Location: ' . URL_BASE . '/?url=auth/login');
        exit;
    }
}

$controladores = [
    'auth'      => 'AuthControlador',
    'panel'     => 'PanelControlador',
    'servicios' => 'ServiciosControlador',
];

if (!isset($controladores[$modulo])) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:20px;">
        <h2>404 — Pagina no encontrada</h2>
    </div>');
}

$nombreControlador  = $controladores[$modulo];
$archivoControlador = RAIZ . '/app/controladores/' . $nombreControlador . '.php';

if (!file_exists($archivoControlador)) {
    http_response_code(500);
    error_log('[SOLTEC admin] Controlador no encontrado: ' . $archivoControlador);
    die('<div style="font-family:sans-serif;padding:20px;">
        <h2>Error interno</h2>
    </div>');
}

require_once $archivoControlador;

$controlador = new $nombreControlador();

// is_callable: impide invocar metodos privados (db, resolverSistema...)
// escribiendo su nombre en la URL.
if (!is_callable([$controlador, $accion])) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:20px;">
        <h2>404 — Accion no encontrada</h2>
    </div>');
}

$controlador->$accion($parametro);
