<?php
$configNegocio = configEfectiva(sucursalTrabajo());   // logo y nombre: los de la sucursal en vista (o los del negocio)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $tituloPagina ?? 'POS GT' ?> — <?= htmlspecialchars($configNegocio['nombre_negocio'] ?? 'POS GT') ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏪</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="<?= URL_BASE ?>/app/public/vendor/tailwind-3.4.17.js"></script>
    <style>
        body { font-family: 'Nunito', sans-serif; }
        .sidebar { transition: transform 0.3s ease; }
        /* Mismo corte que Tailwind "md" (768px) para que botón ☰ y sidebar no se contradigan */
        @media (min-width: 768px) {
            .sidebar {
                position: sticky;
                top: 0;
                height: 100vh;
                height: 100dvh;
                align-self: flex-start;
            }
        }
        @media (max-width: 767.98px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed; z-index: 50; top: 0; bottom: 0; left: 0;
                height: 100vh;
                height: 100dvh;           /* alto visible real en iOS/Android (sin la barra del navegador) */
                min-height: 0 !important;  /* anula min-h-screen, que impedía el scroll interno */
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
                padding-bottom: env(safe-area-inset-bottom);
            }
            .sidebar.abierto { transform: translateX(0); }
            /* En móvil el scroll lo maneja el sidebar completo, no solo el nav */
            .sidebar nav { flex: none; overflow: visible; }
            body.menu-abierto { overflow: hidden; }
            /* Evita el zoom automático de iOS al enfocar inputs (< 16px) */
            input, select, textarea { font-size: 16px !important; }
        }
    </style>
    <script>window.URL_BASE_JS = "<?= URL_BASE ?>";</script>
</head>
<body class="bg-green-50 min-h-screen flex">

<!-- SIDEBAR -->
<aside class="sidebar w-64 bg-green-800 text-white flex flex-col min-h-screen overflow-y-auto" id="sidebar">

    <!-- Logo -->
    <div class="p-5 border-b border-green-700">
        <div class="flex items-center gap-3">
            <?php if (!empty($configNegocio['logo'])): ?>
                <img src="<?= URL_BASE ?>/<?= htmlspecialchars($configNegocio['logo']) ?>" alt="Logo"
                     class="w-10 h-10 object-cover rounded-xl">
            <?php else: ?>
                <span class="text-3xl">🏪</span>
            <?php endif; ?>
            <div class="min-w-0">
                <div class="font-extrabold text-lg leading-tight truncate">
                    <?= htmlspecialchars($configNegocio['nombre_negocio'] ?? 'POS GT') ?>
                </div>
                <div class="text-green-300 text-xs">Punto de Venta</div>
            </div>
        </div>
    </div>

    <!-- Usuario -->
    <div class="px-5 py-4 border-b border-green-700 bg-green-900">
        <div class="text-green-300 text-xs mb-1">Sesión activa</div>
        <div class="font-bold text-sm"><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></div>
        <div class="text-green-400 text-xs"><?= htmlspecialchars($_SESSION['usuario_rol']) ?></div>
    </div>

    <?php if (esAdminConSucursales()): ?>
    <!-- Sucursal que el administrador esta viendo y administrando -->
    <form method="post" action="<?= URL_BASE ?>/?url=configuracion/cambiarSucursal"
          class="px-5 py-3 border-b border-green-700 bg-green-900">
        <label for="selectorSucursal" class="text-green-300 text-xs mb-1 block">🏬 Sucursal que administras</label>
        <?php $sucActual = sucursalTrabajo(); ?>
        <select id="selectorSucursal" name="sucursal" data-actual="<?= (int) $sucActual ?>"
                onchange="if (!navigator.onLine) { alert('Sin conexión: no se puede cambiar de sucursal. Inténtalo cuando vuelva el internet.'); this.value = this.dataset.actual; return; } this.form.submit()"
                class="w-full rounded-lg bg-white text-gray-800 text-sm font-bold py-2 px-2">
            <option value="0" <?= $sucActual === 0 ? 'selected' : '' ?>><?= htmlspecialchars(NOMBRE_SUCURSAL_PRINCIPAL) ?></option>
            <?php foreach (sucursalesDelNegocio() as $idSuc => $nomSuc): ?>
                <option value="<?= (int) $idSuc ?>" <?= $sucActual === (int) $idSuc ? 'selected' : '' ?>><?= htmlspecialchars($nomSuc) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="modulo" value="<?= htmlspecialchars(explode('/', $_GET['url'] ?? '')[0]) ?>">
    </form>
    <?php endif; ?>

    <!-- Menú -->
    <nav class="flex-1 py-4 overflow-y-auto">
        <?php
        $urlActual = $_GET['url'] ?? '';
        $todoElMenu = [
            ['url' => 'pos/index',           'icono' => '🛍️',  'nombre' => 'Punto de Venta',  'modulo' => 'pos'],
            ['url' => 'cotizaciones/index',   'icono' => '📝',  'nombre' => 'Cotizaciones',     'modulo' => 'pos'],
            ['url' => 'inventario/index',     'icono' => '📦',  'nombre' => 'Inventario',       'modulo' => 'inventario'],
            ['url' => 'proveedores/index',    'icono' => '🏭',  'nombre' => 'Proveedores',      'modulo' => 'proveedores'],
            ['url' => 'clientes/index',       'icono' => '👥',  'nombre' => 'Clientes',         'modulo' => 'clientes'],
            ['url' => 'informes/index',       'icono' => '📊',  'nombre' => 'Informes',         'modulo' => 'informes'],
            ['url' => 'configuracion/index',  'icono' => '⚙️',  'nombre' => 'Configuración',    'modulo' => 'configuracion'],
        ];
        // Filtrar el menú según los permisos reales del rol
        $menu = array_filter($todoElMenu, fn($item) => tienePermiso($item['modulo']));
        // Solo admin ve el módulo de usuarios
        if ($_SESSION['usuario_rol_id'] == 1) {
            $menu[] = ['url' => 'usuarios/index', 'icono' => '👤', 'nombre' => 'Usuarios', 'modulo' => 'usuarios'];
        }
        foreach ($menu as $item):
            $activo = ($urlActual === $item['url']) ? 'bg-green-600' : 'hover:bg-green-700';
            // Modulo que el plan no incluye: el administrador lo ve con candado (abre "Funciones avanzadas"); los demas no lo ven
            if (!planPermiteModulo($item['modulo'])):
                if ($_SESSION['usuario_rol_id'] != 1) continue;
        ?>
            <button type="button" data-abrir-funciones title="No incluido en tu plan"
                    class="w-full flex items-center gap-3 px-5 py-3 text-sm font-600 text-green-300 hover:bg-green-700 transition text-left">
                <span class="text-xl"><?= $item['icono'] ?></span>
                <?= $item['nombre'] ?>
                <span class="ml-auto text-xs">🔒</span>
            </button>
        <?php continue; endif; ?>
            <a href="<?= URL_BASE ?>/?url=<?= $item['url'] ?>"
               class="flex items-center gap-3 px-5 py-3 text-sm font-600 <?= $activo ?> transition">
                <span class="text-xl"><?= $item['icono'] ?></span>
                <?= $item['nombre'] ?>
            </a>
        <?php endforeach; ?>
        <?php if ($_SESSION['usuario_rol_id'] == 1): ?>
            <!-- Funciones avanzadas: solo informativo; las habilita SOLTEC -->
            <button type="button" id="btnFuncionesAvanzadas"
                    class="w-full flex items-center gap-3 px-5 py-3 text-sm font-600 hover:bg-green-700 transition text-left">
                <span class="text-xl">✨</span> Funciones avanzadas
            </button>
        <?php endif; ?>
    </nav>

    <!-- Perfil -->
    <div class="px-4 pb-2">
        <a href="<?= URL_BASE ?>/?url=usuarios/perfil"
           class="flex items-center gap-3 px-4 py-2 rounded-xl hover:bg-green-700 text-green-200 text-sm transition">
            👤 Mi Perfil
        </a>
    </div>

    <!-- Cerrar sesión -->
    <div class="p-4 border-t border-green-700">
        <a href="<?= URL_BASE ?>/?url=auth/salir"
           class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-bold transition">
            🚪 Cerrar sesión
        </a>
    </div>
</aside>

<?php if ($_SESSION['usuario_rol_id'] == 1): ?>
<!-- Cuadro "Funciones avanzadas" (se llena al abrirlo) -->
<div id="modalFunciones" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col" role="dialog" aria-modal="true" aria-labelledby="tituloFunciones">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 id="tituloFunciones" class="font-extrabold text-green-800 text-lg">✨ Funciones avanzadas</h3>
            <button type="button" id="cerrarFunciones" class="text-gray-400 hover:text-red-500 text-2xl font-bold leading-none" aria-label="Cerrar">&times;</button>
        </div>
        <div id="cuerpoFunciones" class="p-5 overflow-y-auto"></div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('modalFunciones'), cuerpo = document.getElementById('cuerpoFunciones');
    var btn = document.getElementById('btnFuncionesAvanzadas'), cerrar = document.getElementById('cerrarFunciones');
    if (!modal || !btn) return;
    function abrir() {
        modal.classList.remove('hidden');
        cuerpo.innerHTML = '<p class="text-sm text-gray-400">Cargando…</p>';
        fetch('<?= URL_BASE ?>/?url=configuracion/funcionesAvanzadas', { credentials: 'same-origin' })
            .then(function (r) {
                if (r.redirected || !r.ok) { window.location.reload(); return null; }   // sesion vencida
                return r.text();
            })
            .then(function (html) { if (html !== null) cuerpo.innerHTML = html; })
            .catch(function () { cuerpo.innerHTML = '<p class="text-sm text-red-600">Necesitas internet para ver esta información.</p>'; });
    }
    function cerrarModal() { modal.classList.add('hidden'); }
    document.querySelectorAll('#btnFuncionesAvanzadas, [data-abrir-funciones]').forEach(function (b) { b.addEventListener('click', abrir); });
    cerrar.addEventListener('click', cerrarModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) cerrarModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrarModal(); });
})();
</script>
<?php endif; ?>

<!-- CONTENIDO PRINCIPAL -->
<div class="flex-1 flex flex-col min-h-screen min-w-0">

    <!-- TOPBAR -->
    <header class="bg-white shadow-sm px-3 md:px-6 py-3 md:py-4 flex items-center justify-between gap-2 sticky top-0 z-40">
        <!-- Botón hamburguesa móvil -->
        <button type="button" onclick="toggleMenu()" aria-label="Abrir menú"
                class="md:hidden text-green-700 text-2xl font-bold px-2 -ml-2">☰</button>

        <h1 class="text-base md:text-lg font-bold text-green-800 truncate"><?= $tituloPagina ?? 'POS GT' ?></h1>

        <div class="flex items-center gap-2 text-xs md:text-sm text-gray-500 whitespace-nowrap">
            <?php if (esAdminConSucursales()): ?>
                <!-- Aviso fijo: en que sucursal esta trabajando el administrador -->
                <span class="bg-green-100 text-green-800 font-bold px-2 py-1 rounded-lg">🏬 <?= htmlspecialchars(nombreSucursalTrabajo()) ?></span>
            <?php endif; ?>
            <span class="<?= esAdminConSucursales() ? 'hidden sm:inline' : '' ?>"><?= date('d/m/Y') ?></span>
        </div>
    </header>

    <!-- ÁREA DE CONTENIDO -->
    <main class="flex-1 p-3 md:p-6">