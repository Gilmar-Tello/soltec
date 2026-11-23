<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<!-- Encabezado -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-green-800">📦 Inventario</h2>
        <p class="text-gray-500 text-sm mt-1"><span id="contadorProductos"><?= count($productos) ?></span> productos encontrados</p>
    </div>
    <a href="<?= URL_BASE ?>/?url=inventario/nuevo"
       class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-3 rounded-xl transition text-sm text-center">
        ➕ Nuevo Producto
    </a>
</div>

<!-- Alertas -->
<?php if (isset($_SESSION['exito'])): ?>
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-xl p-4 mb-5">
        ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
    </div>
    <?php unset($_SESSION['exito']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- Botones PDF y configuración -->
<div class="flex flex-wrap gap-3 mb-5">
    <button onclick="abrirPdf('<?= URL_BASE ?>/?url=inventario/pdfProductos')"
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition">
        📄 Reporte de Productos
    </button>
    <button onclick="abrirPdf('<?= URL_BASE ?>/?url=inventario/pdfStockBajo')"
            class="bg-red-500 hover:bg-red-600 text-white font-bold px-4 py-2 rounded-xl text-sm transition">
        ⚠️ Stock Bajo
    </button>
    <button onclick="abrirPdf('<?= URL_BASE ?>/?url=inventario/pdfVencimientos')"
            class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-4 py-2 rounded-xl text-sm transition">
        📅 Próximos a Vencer
    </button>
    <div class="flex-1"></div>
    <a href="<?= URL_BASE ?>/?url=inventario/categorias"
       class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2 rounded-xl text-sm transition">
        🏷️ Categorías
    </a>
    <a href="<?= URL_BASE ?>/?url=inventario/unidades"
       class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2 rounded-xl text-sm transition">
        📐 Unidades
    </a>
</div>

<!-- Modal PDF -->
<div id="modalPdf" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
            <span class="font-bold text-gray-700">Vista previa del reporte</span>
            <button onclick="cerrarPdf()"
                    class="text-gray-400 hover:text-red-500 text-2xl font-bold leading-none">&times;</button>
        </div>
        <iframe id="iframePdf" src="" class="flex-1 w-full rounded-b-2xl border-0"></iframe>
    </div>
</div>

<script>
function abrirPdf(url) {
    document.getElementById('iframePdf').src = url;
    document.getElementById('modalPdf').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function cerrarPdf() {
    document.getElementById('modalPdf').classList.add('hidden');
    document.getElementById('iframePdf').src = '';
    document.body.style.overflow = '';
}
// Cerrar al hacer clic fuera del modal
document.getElementById('modalPdf').addEventListener('click', function(e) {
    if (e.target === this) cerrarPdf();
});
</script>

<!-- Filtros: se aplican solos al escribir o elegir una categoria (sin boton Filtrar) -->
<form id="formFiltros" method="GET" action="" onsubmit="return false;"
      class="bg-white rounded-2xl shadow p-4 mb-6 flex flex-col sm:flex-row gap-3">
    <input type="text" id="filtroBusqueda" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>"
           placeholder="🔍 Buscar por nombre o código..." autocomplete="off"
           aria-label="Buscar por nombre o código"
           class="flex-1 px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
    <select id="filtroCategoria" name="categoria" aria-label="Filtrar por categoría"
            class="px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
        <option value="">Todas las categorías</option>
        <?php foreach ($categorias as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $filtroCateg == $cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="button" id="btnLimpiarFiltros"
            class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-5 py-2 rounded-xl text-sm transition text-center">
        Limpiar
    </button>
</form>

<!-- Tabla -->
<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">Código</th>
                    <th class="px-4 py-3 text-left">Producto</th>
                    <th class="px-4 py-3 text-left">Categoría</th>
                    <th class="px-4 py-3 text-right">P. Compra</th>
                    <th class="px-4 py-3 text-right">P. Mínimo</th>
                    <th class="px-4 py-3 text-right">P. Venta</th>
                    <th class="px-4 py-3 text-right">Stock</th>
                    <th class="px-4 py-3 text-center">Vence</th>
                    <th class="px-4 py-3 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr id="filaSinResultados" class="<?= empty($productos) ? '' : 'hidden' ?>">
                    <td colspan="9" class="px-4 py-10 text-center text-gray-400">No se encontraron productos.</td>
                </tr>
                <?php if (!empty($productos)): ?>
                    <?php foreach ($productos as $p):
                        $stockBajo = $p['stock_actual'] <= $p['stock_minimo'];
                        $diasVence = null;
                        $alertaVence = '';
                        if (!empty($p['fecha_vencimiento'])) {
                            $diasVence = (new DateTime())->diff(new DateTime($p['fecha_vencimiento']))->days;
                            $futuro    = new DateTime($p['fecha_vencimiento']) >= new DateTime();
                            if (!$futuro) $alertaVence = '🔴';
                            elseif ($diasVence <= 7)  $alertaVence = '🔴';
                            elseif ($diasVence <= 15) $alertaVence = '🟠';
                            elseif ($diasVence <= 30) $alertaVence = '🟡';
                        }
                    ?>
                    <tr class="fila-producto hover:bg-green-50 transition <?= $stockBajo ? 'bg-red-50' : '' ?>"
                        data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
                        data-codigo="<?= htmlspecialchars($p['codigo'] ?? '') ?>"
                        data-categoria="<?= (int) $p['categoria_id'] ?>">
                        <td class="px-4 py-3 text-gray-500 font-mono text-xs"><?= htmlspecialchars($p['codigo'] ?? '—') ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <?php if (!empty($mostrarFotos)):
                                    $urlFoto = urlFotoProducto($p['foto'] ?? null, $tidVista); ?>
                                    <?php if ($urlFoto): ?>
                                        <img src="<?= htmlspecialchars($urlFoto) ?>" alt="" loading="lazy"
                                             data-zoom="<?= htmlspecialchars($urlFoto) ?>" data-titulo="<?= htmlspecialchars($p['nombre']) ?>"
                                             class="w-10 h-10 rounded-lg object-cover bg-gray-100 flex-shrink-0">
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-lg bg-gray-100 text-gray-300 flex items-center justify-center flex-shrink-0">🖼️</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-800"><?= htmlspecialchars($p['nombre']) ?></div>
                                    <?php if ($p['descripcion']): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($p['descripcion']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full font-600">
                                <?= htmlspecialchars($p['categoria']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-500">Q<?= number_format($p['precio_compra'], 2) ?></td>
                        <td class="px-4 py-3 text-right text-blue-600">Q<?= number_format($p['precio_minimo'], 2) ?></td>
                        <td class="px-4 py-3 text-right font-bold text-green-700">Q<?= number_format($p['precio_venta_real'], 2) ?></td>
                        <td class="px-4 py-3 text-right <?= $stockBajo ? 'text-red-600 font-bold' : 'text-gray-700' ?>">
                            <?= number_format($p['stock_actual'], 2) ?> <?= $p['unidad'] ?>
                            <?php if ($stockBajo): ?><div class="text-xs text-red-500">⚠️ Stock bajo</div><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center text-xs">
                            <?php if (!empty($p['fecha_vencimiento'])): ?>
                                <?= $alertaVence ?> <?= date('d/m/Y', strtotime($p['fecha_vencimiento'])) ?>
                            <?php else: ?>
                                <span class="text-gray-300">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2 flex-wrap">
                                <a href="<?= URL_BASE ?>/?url=inventario/editar/<?= $p['id'] ?>"
                                   class="bg-blue-100 hover:bg-blue-200 text-blue-700 text-xs font-bold px-3 py-1 rounded-lg transition">✏️ Editar</a>
                                <a href="<?= URL_BASE ?>/?url=inventario/ajustarStock/<?= $p['id'] ?>"
                                   class="bg-yellow-100 hover:bg-yellow-200 text-yellow-700 text-xs font-bold px-3 py-1 rounded-lg transition">📥 Stock</a>
                                <a href="<?= URL_BASE ?>/?url=inventario/movimientos/<?= $p['id'] ?>"
                                   class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-1 rounded-lg transition">📋 Historial</a>
                                <a href="<?= URL_BASE ?>/?url=inventario/eliminar/<?= $p['id'] ?>"
                                   onclick="return confirm(<?= htmlspecialchars(jsonJs('¿Eliminar \'' . $p['nombre'] . '\' del inventario? Ya no aparecerá en el POS ni en las listas, pero su historial de ventas se conserva.'), ENT_QUOTES, 'UTF-8') ?>)"
                                   class="bg-red-100 hover:bg-red-200 text-red-700 text-xs font-bold px-3 py-1 rounded-lg transition">🗑️ Eliminar</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// ── Filtro instantaneo del inventario ─────────────────────
// Busca por nombre o codigo (sin importar mayusculas ni tildes) y por categoria, sin recargar la pagina.
(function () {
    var inputBusqueda = document.getElementById('filtroBusqueda');
    var selCategoria  = document.getElementById('filtroCategoria');
    var btnLimpiar    = document.getElementById('btnLimpiarFiltros');
    var contador      = document.getElementById('contadorProductos');
    var filaVacia     = document.getElementById('filaSinResultados');
    var filas         = Array.prototype.slice.call(document.querySelectorAll('tr.fila-producto'));

    function normalizar(t) {
        return String(t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    }
    // Texto de busqueda de cada fila, calculado una sola vez
    var textos = filas.map(function (f) {
        return normalizar((f.getAttribute('data-nombre') || '') + ' ' + (f.getAttribute('data-codigo') || ''));
    });

    function aplicar() {
        var q   = normalizar(inputBusqueda.value);
        var cat = selCategoria.value;
        var visibles = 0;
        filas.forEach(function (f, i) {
            var ok = (q === '' || textos[i].indexOf(q) !== -1) &&
                     (cat === '' || f.getAttribute('data-categoria') === cat);
            f.classList.toggle('hidden', !ok);
            if (ok) visibles++;
        });
        contador.textContent = visibles;
        filaVacia.classList.toggle('hidden', visibles !== 0);

        // Deja la direccion con los filtros: al recargar o compartir el enlace se conservan
        try {
            var p = new URLSearchParams();
            p.set('url', 'inventario/index');
            if (inputBusqueda.value.trim() !== '') p.set('busqueda', inputBusqueda.value.trim());
            if (cat !== '') p.set('categoria', cat);
            history.replaceState(null, '', '?' + p.toString());
        } catch (e) {}

        var hayFiltro = q !== '' || cat !== '';
        btnLimpiar.classList.toggle('opacity-50', !hayFiltro);
    }

    var espera = null;
    inputBusqueda.addEventListener('input', function () {
        clearTimeout(espera);
        espera = setTimeout(aplicar, 120);
    });
    selCategoria.addEventListener('change', aplicar);
    btnLimpiar.addEventListener('click', function () {
        inputBusqueda.value = '';
        selCategoria.value = '';
        aplicar();
        inputBusqueda.focus();
    });

    aplicar();   // aplica los filtros que vengan en la direccion (recarga o enlace guardado)
})();
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>