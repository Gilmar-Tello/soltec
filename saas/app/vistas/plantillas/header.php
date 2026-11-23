<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tituloPagina ?? 'Panel' ?> — Alquileres SOLTEC</title>
    <?php include RAIZ . '/app/vistas/plantillas/tema.php'; ?>
    <style>
        body { font-family: 'Nunito', sans-serif; }
        .sidebar { transition: transform 0.3s ease; }
        /* 100dvh = alto VISIBLE real. En celular 100vh incluye la barra
           del navegador y el final del menu (Cerrar sesion) quedaba
           escondido debajo de la pantalla. 100vh queda de respaldo
           para navegadores viejos. Mismo corte que md: de Tailwind. */
        @media (min-width: 768px) {
            .sidebar { position: sticky; top: 0; height: 100vh; height: 100dvh; align-self: flex-start; }
        }
        @media (max-width: 767.98px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 50; top: 0; height: 100vh; height: 100dvh; }
            .sidebar.abierto { transform: translateX(0); }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex">

<!-- SIDEBAR -->
<aside class="sidebar w-64 bg-teal-700 text-white flex flex-col" id="sidebar">

    <div class="p-5 border-b border-teal-600 shrink-0">
        <div class="flex items-center gap-3">
            <span class="text-3xl">🪑</span>
            <div>
                <div class="font-extrabold text-lg leading-tight">SOLTEC</div>
                <div class="text-teal-200 text-xs">Alquileres</div>
            </div>
        </div>
    </div>

    <div class="px-5 py-4 border-b border-teal-600 bg-teal-800 shrink-0">
        <div class="text-teal-200 text-xs mb-1"><?= htmlspecialchars($_SESSION['nombre_negocio'] ?? '') ?></div>
        <div class="font-bold text-sm"><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? '') ?></div>
    </div>

    <!-- Solo esta parte se desplaza: el boton de salir queda fijo abajo -->
    <nav class="flex-1 min-h-0 overflow-y-auto overscroll-contain py-4">
        <?php
        $urlActual = $_GET['url'] ?? '';

        // Incidencias abiertas: el aviso para el administrador
        $incidenciasAbiertas = 0;
        if (esAdmin()) {
            try {
                $stmtInc = obtenerConexion()->prepare(
                    "SELECT COUNT(*) FROM incidencias
                     WHERE tenant_id = :tid AND estado IN ('pendiente', 'en_gestion')"
                );
                $stmtInc->execute([':tid' => (int) ($_SESSION['tenant_id'] ?? 0)]);
                $incidenciasAbiertas = (int) $stmtInc->fetchColumn();
            } catch (Throwable $e) {
                error_log('[SOLTEC alquileres] Contador de incidencias: ' . $e->getMessage());
            }
        }

        // Menú base visible para todos
        $menuAdmin = [
            ['url' => 'dashboard/index',      'icono' => '📊', 'nombre' => 'Dashboard'],
            ['url' => 'sucursales/index',     'icono' => '🏬', 'nombre' => 'Sucursales'],
            ['url' => 'categorias/index',     'icono' => '🏷️', 'nombre' => 'Categorías'],
            ['url' => 'inventario/index',     'icono' => '📦', 'nombre' => 'Inventario'],
            ['url' => 'combos/index',         'icono' => '🎁', 'nombre' => 'Combos'],
            ['url' => 'transferencias/index', 'icono' => '🔄', 'nombre' => 'Transferencias'],
            ['url' => 'bajas/index',          'icono' => '🗑️', 'nombre' => 'Bajas'],
            ['url' => 'reservas/index',       'icono' => '📅', 'nombre' => 'Reservas'],
            ['url' => 'incidencias/index',    'icono' => '⚠️', 'nombre' => 'Incidencias', 'badge' => $incidenciasAbiertas],
            ['url' => 'clientes/index',       'icono' => '👥', 'nombre' => 'Clientes'],
            ['url' => 'usuarios/index',       'icono' => '👤', 'nombre' => 'Usuarios'],
            ['url' => 'reportes/index',       'icono' => '📈', 'nombre' => 'Reportes'],
        ];

        // Menú reducido para trabajadores
        $menuTrabajador = [
            ['url' => 'dashboard/index',   'icono' => '📊', 'nombre' => 'Dashboard'],
            ['url' => 'inventario/index',  'icono' => '📦', 'nombre' => 'Inventario'],
            ['url' => 'reservas/index',    'icono' => '📅', 'nombre' => 'Reservas'],
            ['url' => 'clientes/index',    'icono' => '👥', 'nombre' => 'Clientes'],
        ];

        $menu = esAdmin() ? $menuAdmin : $menuTrabajador;

        foreach ($menu as $item):
            $activo = ($urlActual === $item['url']) ? 'bg-teal-600' : 'hover:bg-teal-600/60';
        ?>
            <a href="<?= URL_BASE ?>/?url=<?= $item['url'] ?>"
               class="flex items-center gap-3 px-5 py-3 text-sm font-600 <?= $activo ?> transition">
                <span class="text-xl"><?= $item['icono'] ?></span>
                <?= $item['nombre'] ?>
                <?php if (!empty($item['badge'])): ?>
                    <span class="ml-auto bg-orange-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                        <?= (int) $item['badge'] ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <?php if (esTrabajador()): ?>
            <div class="mx-4 mt-3 px-3 py-2 bg-teal-800 rounded-xl text-xs text-teal-300">
                👷 Modo trabajador
            </div>
        <?php endif; ?>
    </nav>

    <div class="p-4 border-t border-teal-600 shrink-0" style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
        <a href="<?= URL_BASE ?>/?url=auth/salir"
           class="flex items-center gap-3 px-4 py-3 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold transition">
            🚪 Cerrar sesión
        </a>
    </div>
</aside>

<!-- Fondo oscuro detras del menu en celular: tocarlo cierra el menu -->
<div id="fondoMenu" class="hidden fixed inset-0 bg-black/40 z-[45] md:hidden" onclick="alternarMenu()"></div>
<script>
function alternarMenu() {
    const abierto = document.getElementById('sidebar').classList.toggle('abierto');
    document.getElementById('fondoMenu').classList.toggle('hidden', !abierto);
}
</script>

<!-- CONTENIDO -->
<div class="flex-1 flex flex-col min-h-screen min-w-0">
    <header class="bg-white shadow-sm px-6 py-4 flex items-center justify-between sticky top-0 z-40">
        <button type="button" onclick="alternarMenu()" aria-label="Abrir menú"
                class="md:hidden text-teal-700 text-2xl font-bold p-1 -ml-1">☰</button>
        <h1 class="text-lg font-bold text-teal-800"><?= $tituloPagina ?? 'Panel' ?></h1>
        <div class="text-sm text-slate-500"><?= fechaCorta(hoyGt()) ?></div>
    </header>
    <main class="flex-1 p-4 sm:p-6">
