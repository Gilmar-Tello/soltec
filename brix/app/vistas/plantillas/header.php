<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$urlActual = $_GET['url'] ?? '';
function navActivo($rutas, $urlActual) {
    foreach ((array)$rutas as $r) {
        if (strpos($urlActual, $r) === 0) return true;
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($tituloPagina ?? 'Brix') ?> — Brix</title>
    <?php include RAIZ . '/app/vistas/plantillas/tema.php'; ?>
    <style>
        /* El menu en si no se desliza: se desliza SOLO la lista de opciones, y el
           bloque de abajo (usuario + Cerrar sesion) queda siempre a la vista. */
        .sidebar { transition: transform .25s ease; overflow: hidden; }
        .sidebar nav { overflow-y: auto; min-height: 0; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
        .sidebar .pie-menu { flex-shrink: 0; padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px)); }
        @media (min-width: 769px) {
            .sidebar { position: sticky; top: 0; height: 100vh; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 50; top: 0; height: 100vh; height: 100dvh; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
            .sidebar.abierto { transform: translateX(0); box-shadow: 0 0 40px rgba(0,0,0,.45); }
            /* Con el menu abierto la pagina de atras NO se mueve: el menu se desliza por su cuenta */
            html.menu-abierto, html.menu-abierto body { overflow: hidden; }
            #velo { touch-action: none; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex text-slate-800">

<aside class="sidebar w-60 bg-slate-900 text-slate-300 flex flex-col" id="sidebar">
    <div class="px-6 py-5 border-b border-slate-800">
        <div class="text-white font-extrabold text-xl tracking-tight">Brix</div>
        <div class="text-slate-400 text-xs mt-0.5"><?= htmlspecialchars($_SESSION['nombre_negocio'] ?? '') ?></div>
    </div>

    <nav class="flex-1 py-3 text-sm">
        <?php
        $items = [
            ['dashboard/index', 'dashboard', 'Dashboard'],
            ['caja/index',      'caja',      'Libro de caja'],
            ['ingresos/index',  'ingresos',  'Ingresos'],
            ['egresos/index',   'egresos',   'Egresos'],
            ['productos/index', 'productos', 'Productos'],
        ];
        foreach ($items as [$url, $base, $label]):
            $on = navActivo($base, $urlActual);
        ?>
        <a href="<?= URL_BASE ?>/?url=<?= $url ?>"
           class="block px-6 py-3 border-l-2 <?= $on ? 'border-indigo-400 bg-slate-800 text-white' : 'border-transparent hover:bg-slate-800/60 hover:text-white' ?> transition">
            <?= $label ?>
        </a>
        <?php endforeach; ?>

        <div class="px-6 pt-5 pb-1 text-[11px] uppercase tracking-wider text-slate-500">Configuración</div>
        <?php
        $sub = [
            ['categorias/index',   'categorias', 'Categorías'],
            ['unidades/index',     'unidades',   'Unidades de medida'],
            ['productos/cuadre',   'productos/cuadre', 'Cuadre de inventario'],
            ['configuracion/index','configuracion', 'Datos del negocio'],
            ['perfil/index',       'perfil',        'Mi contraseña'],
        ];
        foreach ($sub as [$url, $base, $label]):
            $on = navActivo($base, $urlActual);
        ?>
        <a href="<?= URL_BASE ?>/?url=<?= $url ?>"
           class="block px-6 py-3 border-l-2 <?= $on ? 'border-indigo-400 bg-slate-800 text-white' : 'border-transparent hover:bg-slate-800/60 hover:text-white' ?> transition">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="pie-menu px-6 pt-4 border-t border-slate-800">
        <div class="text-xs text-slate-400 mb-2 truncate"><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? '') ?></div>
        <a href="<?= URL_BASE ?>/?url=auth/salir"
           class="block text-center rounded-md border border-slate-600 hover:bg-slate-800 py-2.5 text-sm font-semibold text-white transition">Cerrar sesión</a>
    </div>
</aside>

<!-- Fondo oscuro del menu en telefono: tocarlo cierra el menu -->
<div id="velo" class="hidden fixed inset-0 bg-black/50 z-40 md:hidden" onclick="cerrarMenu()"></div>

<div class="flex-1 flex flex-col min-h-screen min-w-0">
    <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-2 flex items-center justify-between gap-3 sticky top-0 z-30">
        <button onclick="alternarMenu()" aria-label="Abrir menú"
                class="md:hidden text-slate-700 text-2xl leading-none -ml-2 px-3 py-2">≡</button>
        <h1 class="text-base font-bold text-slate-900 truncate flex-1"><?= htmlspecialchars($tituloPagina ?? 'Brix') ?></h1>
        <div class="text-sm text-slate-400 hidden sm:block"><?= date('d/m/Y') ?></div>
    </header>
    <main class="flex-1 p-6 max-w-6xl w-full">

    <?php if (!empty($_SESSION['exito'])): ?>
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <?= htmlspecialchars($_SESSION['exito']) ?>
        </div>
        <?php unset($_SESSION['exito']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
