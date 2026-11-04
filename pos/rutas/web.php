<?php

// Obtener la ruta actual
$ruta = $_GET['url'] ?? 'auth/login';
$ruta = rtrim($ruta, '/');
$partes = explode('/', $ruta);

$modulo     = $partes[0] ?? 'auth';
$accion     = $partes[1] ?? 'index';
$parametro  = $partes[2] ?? null;

// ── Protección CSRF ──────────────────────────────────────────
// Todo POST, y las acciones por enlace que modifican datos, deben venir del propio sistema.
$accionesGetQueModifican = [
    'clientes/desactivar', 'cotizaciones/eliminar', 'proveedores/desactivar', 'proveedores/recibirOrden',
    'inventario/eliminar', 'inventario/eliminarCategoria', 'inventario/eliminarUnidad',
    'usuarios/toggleActivo', 'auth/salir',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' || in_array("$modulo/$accion", $accionesGetQueModifican, true)) {
    exigirMismoSitio();
}

// Solo letras en módulo y acción; el parámetro debe ser un ID numérico
if (!preg_match('/^[A-Za-z]+$/', $modulo) || !preg_match('/^[A-Za-z]+$/', $accion)
    || ($parametro !== null && !ctype_digit((string) $parametro))) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:20px;"><h2>404 — Página no encontrada</h2>
        <a href="' . URL_BASE . '">← Volver al inicio</a></div>');
}
if ($parametro !== null) $parametro = (int) $parametro;

// Rutas protegidas (requieren sesión iniciada)
$rutasProtegidas = ['pos', 'cotizaciones', 'inventario', 'clientes', 'proveedores', 'informes', 'configuracion', 'usuarios'];

if (in_array($modulo, $rutasProtegidas)) {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ' . URL_BASE . '/?url=auth/login');
        exit;
    }
    // Validar permiso por módulo (el administrador siempre pasa)
    // 'usuarios' y 'configuracion/guardar' quedan reservados al admin desde sus propios controladores
    // 'cotizaciones' reutiliza el permiso de 'pos' (se valida dentro del propio controlador)
    if ($modulo !== 'pos' && $modulo !== 'cotizaciones' && !tienePermiso($modulo)) {
        $_SESSION['error'] = 'No tenés permisos para acceder a esa sección.';
        header('Location: ' . URL_BASE . '/?url=pos/index');
        exit;
    }
    // Plan contratado: el modulo tiene que estar incluido (se valida aqui, no solo en el menu)
    if (!planPermiteModulo($modulo)) {
        $_SESSION['error'] = 'Esa sección no está incluida en tu plan (' . planNombre() . '). Mira las opciones en "Funciones avanzadas".';
        header('Location: ' . URL_BASE . '/?url=pos/index');
        exit;
    }
}

// Mapeo de módulos a controladores
$controladores = [
    'auth'          => 'AuthControlador',
    'pos'           => 'PosControlador',
    'cotizaciones'  => 'CotizacionesControlador',
    'inventario'    => 'InventarioControlador',
    'clientes'      => 'ClientesControlador',
    'proveedores'   => 'ProveedoresControlador',
    'informes'      => 'InformesControlador',
    'configuracion' => 'ConfiguracionControlador',
    'usuarios'      => 'UsuariosControlador',
];

if (!isset($controladores[$modulo])) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:20px;">
        <h2>404 — Página no encontrada</h2>
        <a href="' . URL_BASE . '">← Volver al inicio</a>
    </div>');
}

$nombreControlador = $controladores[$modulo];
$archivoControlador = RAIZ . '/app/controladores/' . $nombreControlador . '.php';

if (!file_exists($archivoControlador)) {
    die('<div style="font-family:sans-serif;padding:20px;color:orange;">
        <h2>⚠️ Controlador no encontrado</h2>
    </div>');
}

require_once $archivoControlador;

$controlador = new $nombreControlador();

// is_callable: impide invocar métodos privados (db, cajaActiva...) desde la URL
if (!is_callable([$controlador, $accion])) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:20px;color:orange;">
        <h2>⚠️ Acción no encontrada</h2>
        <a href="' . URL_BASE . '">← Volver al inicio</a>
    </div>');
}

$controlador->$accion($parametro);