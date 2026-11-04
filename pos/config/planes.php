<?php
// ============================================================
//  PLANES del POS (Basico / Medio / Premium).
//  Los precios y lo que incluye cada plan se cambian SOLO aqui.
//  El valor guardado en la base es: basico | estandar (= "Medio") | premium.
//
//  Reglas:
//   - Los modulos que el plan no incluye se bloquean en el servidor (no basta con esconder el boton)
//     y el administrador los ve en el menu con un candado.
//   - 'usuarios' = cuantos usuarios ACTIVOS puede tener el negocio (null = ilimitados).
//   - Si no se puede leer el plan (error de base de datos) se deja pasar todo: nadie se queda sin sistema.
// ============================================================

const PLAN_ETIQUETAS_MODULOS = [
    'pos'           => 'Punto de venta',
    'inventario'    => 'Inventario',
    'clientes'      => 'Clientes (crédito y saldo a favor)',
    'cotizaciones'  => 'Cotizaciones',
    'proveedores'   => 'Proveedores y órdenes de compra',
    'informes'      => 'Informes completos',
];

function catalogoPlanes(): array {
    return [
        'basico' => [
            'nombre'   => 'Básico',
            'precio'   => 150,
            'usuarios' => 1,
            'modulos'  => ['pos', 'inventario', 'configuracion', 'usuarios'],
        ],
        'estandar' => [
            'nombre'   => 'Medio',
            'precio'   => 250,
            'usuarios' => 3,
            'modulos'  => ['pos', 'inventario', 'configuracion', 'usuarios', 'clientes', 'cotizaciones', 'proveedores', 'informes'],
        ],
        'premium' => [
            'nombre'   => 'Premium',
            'precio'   => 300,
            'usuarios' => null,
            'modulos'  => ['pos', 'inventario', 'configuracion', 'usuarios', 'clientes', 'cotizaciones', 'proveedores', 'informes'],
        ],
    ];
}

// Plan del negocio de la sesion. Se guarda 60 s en la sesion para no consultar en cada pantalla.
function planNegocio(): string {
    $c = $_SESSION['plan_cache'] ?? null;
    if (is_array($c) && isset($c['p'], $c['t']) && (time() - (int) $c['t']) < 60) return (string) $c['p'];

    $plan = 'premium';   // si no se puede leer, no se deja a nadie sin acceso
    try {
        $st = obtenerConexion()->prepare("SELECT plan FROM tenants WHERE id = :t");
        $st->execute([':t' => (int) ($_SESSION['tenant_id'] ?? 0)]);
        $p = $st->fetchColumn();
        $plan = isset(catalogoPlanes()[$p]) ? (string) $p : 'estandar';
    } catch (Throwable $e) {
        error_log('[SOLTEC pos] No se pudo leer el plan: ' . $e->getMessage());
    }
    $_SESSION['plan_cache'] = ['p' => $plan, 't' => time()];
    return $plan;
}

function planNombre(): string        { return catalogoPlanes()[planNegocio()]['nombre']; }
function planLimiteUsuarios(): ?int  { return catalogoPlanes()[planNegocio()]['usuarios']; }

// Modulos que no aparecen en ningun plan (auth, etc.) no se bloquean
function planPermiteModulo(string $modulo): bool {
    $todos = [];
    foreach (catalogoPlanes() as $p) $todos = array_merge($todos, $p['modulos']);
    if (!in_array($modulo, $todos, true) && !array_key_exists($modulo, PLAN_ETIQUETAS_MODULOS)) return true;
    return in_array($modulo, catalogoPlanes()[planNegocio()]['modulos'], true);
}

// Ventas a credito / con saldo de clientes: van con el modulo de Clientes
function planPermiteCredito(): bool { return planPermiteModulo('clientes'); }

// Precios de las funciones extra (se venden aparte, en cualquier plan)
const PRECIO_FOTOS_PRODUCTOS = 75;    // Q al mes
const PRECIO_SYNC_BRIX       = 100;   // Q al mes
const PRECIO_POR_SUCURSAL    = 100;   // Q al mes, por cada sucursal
