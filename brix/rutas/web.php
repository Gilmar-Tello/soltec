<?php

$ruta = $_GET['url'] ?? 'auth/login';
$ruta = rtrim($ruta, '/');
$partes = explode('/', $ruta);

$modulo    = $partes[0] ?? 'auth';
$accion    = $partes[1] ?? 'login';
$parametro = $partes[2] ?? null;

// ── Proteccion CSRF ──────────────────────────────────────────
// Todo POST, y las acciones por enlace que modifican datos, tienen
// que venir del propio sistema.
$verbosDeEscritura = [
    'guardar', 'actualizar', 'eliminar', 'borrar', 'crear',
    'toggleactivo', 'toggleactiva', 'activar', 'desactivar', 'toggle', 'salir',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    || in_array(strtolower($accion), $verbosDeEscritura, true)) {
    exigirMismoSitio();
}

// ── Entrada saneada: solo letras en modulo y accion, ID numerico ──
if (!preg_match('/^[A-Za-z]+$/', $modulo) || !preg_match('/^[A-Za-z]+$/', $accion)
    || ($parametro !== null && !ctype_digit((string) $parametro))) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:20px;">
        <h2>404 — Pagina no encontrada</h2>
        <a href="' . URL_BASE . '">Volver al inicio</a>
    </div>');
}
if ($parametro !== null) $parametro = (int) $parametro;

// Rutas protegidas — requieren sesion de usuario del negocio
$rutasProtegidas = ['dashboard', 'caja', 'ingresos', 'egresos', 'productos',
                    'categorias', 'unidades', 'configuracion', 'perfil'];

if (in_array($modulo, $rutasProtegidas)) {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ' . URL_BASE . '/?url=auth/login');
        exit;
    }
}

$controladores = [
    'auth'          => 'AuthControlador',
    'dashboard'     => 'DashboardControlador',
    'caja'          => 'CajaControlador',
    'ingresos'      => 'IngresoControlador',
    'egresos'       => 'EgresoControlador',
    'productos'     => 'ProductoControlador',
    'categorias'    => 'CategoriaControlador',
    'unidades'      => 'UnidadControlador',
    'configuracion' => 'ConfiguracionControlador',
    'perfil'        => 'PerfilControlador',
];

if (!isset($controladores[$modulo])) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:20px;">
        <h2>404 — Pagina no encontrada</h2>
        <a href="' . URL_BASE . '">Volver al inicio</a>
    </div>');
}

$nombreControlador  = $controladores[$modulo];
$archivoControlador = RAIZ . '/app/controladores/' . $nombreControlador . '.php';

if (!file_exists($archivoControlador)) {
    http_response_code(500);
    error_log('[SOLTEC brix] Controlador no encontrado: ' . $archivoControlador);
    die('<div style="font-family:sans-serif;padding:20px;">
        <h2>Error interno</h2>
    </div>');
}

require_once $archivoControlador;

$controlador = new $nombreControlador();

// is_callable: impide invocar metodos privados escribiendo su nombre en la URL
if (!is_callable([$controlador, $accion])) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:20px;">
        <h2>404 — Accion no encontrada</h2>
        <a href="' . URL_BASE . '">Volver al inicio</a>
    </div>');
}

$controlador->$accion($parametro);
