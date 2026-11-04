<?php

// ============================================================
// Sistema de permisos por rol
// Admin negocio: acceso total
// Trabajador: acceso restringido a operaciones del día a día
// ============================================================

// Mapa de permisos: modulo => [acciones permitidas para trabajador]
// Si el módulo no aparece aquí, el trabajador no tiene acceso.
// 'admin_negocio' tiene acceso a todo sin restricción.
// 'usuarios' no aparece aquí → trabajadores nunca pueden acceder.
$GLOBALS['PERMISOS_TRABAJADOR'] = [
    'dashboard'  => ['index'],
    'sucursales' => ['index'],                                          // solo ver
    'inventario' => ['index'],                                          // solo ver/buscar
    'reservas'   => ['index', 'nueva', 'guardar', 'detalle',
                     'editar', 'actualizar',
                     'entregar', 'devolver', 'inspeccion',
                     'confirmardevolucion', 'cancelar',
                     'comprobante', 'inventarioajax'],
    'clientes'   => ['index', 'nuevo', 'guardar', 'editar',
                     'actualizar', 'historial', 'buscarajax'],
    'combos'     => ['ajax'],   // solo consulta AJAX para nueva reserva
    'auth'       => ['login', 'entrar', 'salir'],
];

/**
 * Verifica si el usuario actual puede ejecutar una acción.
 * Redirige con error si no tiene permiso.
 */
function verificarPermiso(string $modulo, string $accion): void {
    $rol = $_SESSION['usuario_rol'] ?? '';

    // Admin negocio tiene acceso total
    if ($rol === 'admin_negocio') return;

    $permisos = $GLOBALS['PERMISOS_TRABAJADOR'];

    // Módulo no permitido para trabajador
    if (!isset($permisos[$modulo])) {
        $_SESSION['error'] = 'No tenés permiso para acceder a esta sección.';
        header('Location: ' . URL_BASE . '/?url=dashboard/index');
        exit;
    }

    // Acción no permitida dentro del módulo
    if (!in_array($accion, $permisos[$modulo], true)) {
        $_SESSION['error'] = 'No tenés permiso para realizar esta acción.';
        header('Location: ' . URL_BASE . '/?url=dashboard/index');
        exit;
    }
}

/**
 * Retorna true si el usuario actual es admin del negocio.
 */
function esAdmin(): bool {
    return ($_SESSION['usuario_rol'] ?? '') === 'admin_negocio';
}

/**
 * Retorna true si el usuario actual es trabajador.
 */
function esTrabajador(): bool {
    return ($_SESSION['usuario_rol'] ?? '') === 'trabajador';
}
