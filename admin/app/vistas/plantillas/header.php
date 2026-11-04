<?php
// Headers de no-caché — evita que el navegador muestre páginas
// protegidas desde caché al presionar la flecha de retroceso.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d1b2a">
    <title><?= htmlspecialchars($tituloPagina ?? 'Panel') ?> — SOLTEC</title>
    <link rel="icon" href="https://soltec.click/img/logo.png" type="image/png">
    <?php include RAIZ . '/app/vistas/plantillas/tema.php'; ?>
    <style>
        /* El menu en si no se desliza: se desliza SOLO la lista de opciones, y el
           bloque de abajo (Cerrar sesion) queda siempre a la vista. */
        .sidebar { transition: transform 0.3s ease; overflow: hidden; }
        .sidebar nav { overflow-y: auto; min-height: 0; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
        .sidebar .pie-menu { flex-shrink: 0; padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px)); }
        @media (min-width: 769px) {
            .sidebar { position: sticky; top: 0; height: 100vh; align-self: flex-start; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 50; top: 0; height: 100vh; height: 100dvh; overscroll-behavior: contain; }
            .sidebar.abierto { transform: translateX(0); box-shadow: 0 0 40px rgba(0,0,0,.45); }
            /* Con el menu abierto la pagina de atras NO se mueve */
            html.menu-abierto, html.menu-abierto body { overflow: hidden; }
            #velo { touch-action: none; }
        }
    </style>
</head>
<body class="min-h-screen flex">

<!-- SIDEBAR -->
<aside class="sidebar fondo-marca w-64 text-white flex flex-col" id="sidebar">

    <div class="p-5 border-b border-white/10">
        <div class="flex items-center gap-3">
            <img src="https://soltec.click/img/logo.png" alt="Logo de SOLTEC" width="48" height="48"
                 class="w-12 h-auto flex-shrink-0 drop-shadow-lg">
            <div>
                <div class="font-black text-xl tracking-widest leading-tight">SOLTEC</div>
                <div class="text-sol-celeste text-xs font-bold">Panel maestro</div>
            </div>
        </div>
    </div>

    <div class="px-5 py-4 border-b border-white/10 bg-black/20">
        <div class="text-sol-hielo text-xs mb-1">Sesión activa</div>
        <div class="font-bold text-sm truncate"><?= htmlspecialchars($_SESSION['panel_admin_nombre']) ?></div>
    </div>

    <!-- SELECTOR DE SISTEMA -->
    <?php
        $sistemaActual = sistemaActivo();
        $sistemas      = $GLOBALS['SISTEMAS_SOLTEC'];
        $infoActual    = $sistemas[$sistemaActual];
        // Sistemas aún no implementados, solo informativos en el menú
        $sistemasProximamente = [
            ['icono' => '🏫', 'nombre' => 'Colegio'],
            ['icono' => '🏋️', 'nombre' => 'Gym'],
        ];
    ?>
    <div class="px-4 pt-4 pb-2 text-sol-hielo text-xs">Sistema activo</div>
    <div class="px-4 pb-4 relative">
        <button type="button" onclick="document.getElementById('menuSistemas').classList.toggle('hidden')"
                class="w-full flex items-center justify-between gap-2 bg-white/10 hover:bg-white/15 border border-white/20 rounded-lg px-3 py-2.5 text-white text-sm font-bold transition">
            <span class="flex items-center gap-2">
                <span><?= $infoActual['icono'] ?></span>
                <?= htmlspecialchars($infoActual['nombre']) ?>
            </span>
            <span class="text-sol-celeste text-xs">▾</span>
        </button>

        <div id="menuSistemas" class="hidden mt-1.5 bg-sol-oscuro border border-white/15 rounded-lg overflow-hidden">
            <?php foreach ($sistemas as $clave => $info): ?>
                <a href="<?= URL_BASE ?>/?url=panel/index&sistema=<?= $clave ?>"
                   class="flex items-center gap-2 px-3 py-2.5 text-sm <?= $clave === $sistemaActual ? 'bg-sol-acento text-white font-bold' : 'text-sol-hielo hover:bg-white/10' ?>">
                    <span><?= $info['icono'] ?></span>
                    <?= htmlspecialchars($info['nombre']) ?>
                    <?php if ($clave === $sistemaActual): ?>
                        <span class="ml-auto text-white">✓</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            <?php foreach ($sistemasProximamente as $info): ?>
                <div class="flex items-center gap-2 px-3 py-2.5 text-sm text-sol-claro">
                    <span class="opacity-60"><?= $info['icono'] ?></span>
                    <?= htmlspecialchars($info['nombre']) ?>
                    <span class="ml-auto text-[10px] bg-white/10 text-sol-hielo px-2 py-0.5 rounded-full">Pronto</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <nav class="flex-1 py-2 px-3 space-y-1">
        <?php
        $urlActual = $_GET['url'] ?? '';
        $menu = [
            ['url' => 'panel/index',        'icono' => '📊', 'nombre' => 'Dashboard'],
            ['url' => 'panel/clientes',     'icono' => '🏢', 'nombre' => 'Clientes'],
            ['url' => 'panel/nuevoCliente', 'icono' => '➕', 'nombre' => 'Nuevo Cliente'],
            ['url' => 'servicios/ver',      'icono' => '💧', 'nombre' => 'NutriFluidos'],
            ['url' => 'panel/seguridad',    'icono' => '🔐', 'nombre' => 'Seguridad'],
        ];
        foreach ($menu as $item):
            $activo = ($urlActual === $item['url']) ? 'bg-sol-acento shadow-sol' : 'hover:bg-white/10';
        ?>
            <a href="<?= URL_BASE ?>/?url=<?= $item['url'] ?>"
               class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-bold <?= $activo ?> transition">
                <span class="text-xl"><?= $item['icono'] ?></span>
                <?= $item['nombre'] ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="pie-menu px-4 pt-4 border-t border-white/10">
        <a href="<?= URL_BASE ?>/?url=auth/salir"
           class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-white/10 hover:bg-red-600 text-white text-sm font-bold transition">
            🚪 Cerrar sesión
        </a>
    </div>
</aside>

<!-- Fondo oscuro del menu en telefono: tocarlo cierra el menu -->
<div id="velo" class="hidden fixed inset-0 bg-black/50 z-40 md:hidden" onclick="cerrarMenu()"></div>

<!-- CONTENIDO -->
<div class="flex-1 flex flex-col min-h-screen min-w-0">
    <header class="bg-white border-b border-sol-borde shadow-sol px-4 sm:px-6 py-3 flex items-center justify-between gap-3 sticky top-0 z-30">
        <button onclick="alternarMenu()" aria-label="Abrir menú"
                class="md:hidden text-sol-oscuro text-2xl font-bold leading-none -ml-2 px-3 py-2">☰</button>
        <h1 class="text-lg font-extrabold text-sol-oscuro truncate flex-1"><?= htmlspecialchars($tituloPagina ?? 'Panel') ?></h1>
        <div class="text-sm text-sol-gris font-semibold hidden sm:block"><?= date('d/m/Y') ?></div>
    </header>
    <main class="flex-1 p-4 sm:p-6">
        <?php if (empty($_SESSION['panel_2fa']) && ($_GET['url'] ?? '') !== 'panel/seguridad'): ?>
            <a href="<?= URL_BASE ?>/?url=panel/seguridad"
               class="block bg-amber-50 border border-amber-300 text-amber-900 rounded-xl px-4 py-3 mb-4 text-sm font-semibold hover:bg-amber-100">
                🔐 Activa la verificación en dos pasos: esta cuenta controla a todos tus clientes. →
            </a>
        <?php endif; ?>
