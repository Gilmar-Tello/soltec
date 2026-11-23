<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<script src="<?= URL_BASE ?>/app/public/vendor/chart-4.4.7.umd.js"></script>

<!-- Encabezado + filtro -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h2 class="text-2xl font-extrabold text-green-800">📊 Informes</h2>
    <div class="flex flex-wrap gap-3 items-end">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <input type="hidden" name="url" value="informes/index">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Desde</label>
                <input type="date" name="desde" value="<?= $desde ?>"
                       class="px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                <input type="date" name="hasta" value="<?= $hasta ?>"
                       class="px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
            </div>
            <?php if ($hayRanking): ?>
            <div>
                <label class="block text-xs text-gray-500 mb-1" for="filtroSucursal">Sucursal</label>
                <select name="sucursal" id="filtroSucursal"
                        class="px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                    <option value="">Todas</option>
                    <option value="0" <?= $sucursalSel === '0' ? 'selected' : '' ?>><?= htmlspecialchars(NOMBRE_SUCURSAL_PRINCIPAL) ?></option>
                    <?php foreach (sucursalesDelNegocio() as $sid => $snombre): ?>
                        <option value="<?= (int) $sid ?>" <?= $sucursalSel === (string) $sid ? 'selected' : '' ?>><?= htmlspecialchars($snombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl text-sm transition">
                Filtrar
            </button>
        </form>
        <button onclick="abrirPdf('<?= URL_BASE ?>/?url=informes/pdf&desde=<?= $desde ?>&hasta=<?= $hasta ?><?= $hayRanking ? '&sucursal=' . urlencode((string) $sucursalSel) : '' ?>')"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition">
            📄 Generar PDF
        </button>
    </div>
</div>

<!-- Modal PDF -->
<div id="modalPdf" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
            <span class="font-bold text-gray-700">Vista previa del informe</span>
            <button onclick="cerrarPdf()" class="text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
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
document.getElementById('modalPdf').addEventListener('click', function(e) {
    if (e.target === this) cerrarPdf();
});
</script>

<?php if ($hayRanking): ?>
<!-- Que sucursal vende mas (todas las sucursales, en el periodo elegido) -->
<div class="bg-white rounded-2xl shadow p-5 mb-6">
    <h3 class="font-extrabold text-green-800 mb-3">🏬 Ventas por sucursal</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-xs text-gray-500 uppercase">
                <tr>
                    <th class="text-left py-2 pr-2">#</th>
                    <th class="text-left py-2 px-2">Sucursal</th>
                    <th class="text-right py-2 px-2">Ventas</th>
                    <th class="text-right py-2 px-2">Total vendido</th>
                    <th class="text-right py-2 px-2">Ticket prom.</th>
                    <th class="text-right py-2 px-2">Ganancia</th>
                    <th class="text-right py-2 px-2">% del total</th>
                    <th class="py-2 pl-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($ranking as $i => $r): ?>
                <tr class="<?= ($sucursalSel !== null && (string) $r['id'] === $sucursalSel) ? 'bg-green-50' : '' ?>">
                    <td class="py-2 pr-2 text-gray-500"><?= $i === 0 && $r['monto'] > 0 ? '🏆' : ($i + 1) ?></td>
                    <td class="py-2 px-2 font-bold text-gray-800"><?= htmlspecialchars($r['nombre']) ?></td>
                    <td class="py-2 px-2 text-right"><?= (int) $r['ventas'] ?></td>
                    <td class="py-2 px-2 text-right font-bold text-green-700">Q<?= number_format($r['monto'], 2) ?></td>
                    <td class="py-2 px-2 text-right text-gray-600">Q<?= number_format($r['ticket'], 2) ?></td>
                    <td class="py-2 px-2 text-right text-gray-600">Q<?= number_format($r['ganancia'], 2) ?></td>
                    <td class="py-2 px-2 text-right text-gray-600"><?= number_format($r['pct'], 1) ?>%</td>
                    <td class="py-2 pl-2 text-right">
                        <a href="<?= URL_BASE ?>/?url=informes/index&desde=<?= $desde ?>&hasta=<?= $hasta ?>&sucursal=<?= (int) $r['id'] ?>"
                           class="text-blue-600 hover:underline text-xs font-bold whitespace-nowrap">Ver su informe →</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($sucursalNombre !== null): ?>
        <p class="text-xs text-gray-500 mt-3">
            Mostrando abajo solo: <strong><?= htmlspecialchars($sucursalNombre) ?></strong> ·
            <a class="text-blue-600 hover:underline" href="<?= URL_BASE ?>/?url=informes/index&desde=<?= $desde ?>&hasta=<?= $hasta ?>">ver todas</a>
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Tarjetas resumen -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-3xl font-extrabold text-green-700"><?= $resumen['total_ventas'] ?></div>
        <div class="text-xs text-gray-500 mt-1">Ventas realizadas</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-2xl font-extrabold text-green-700">Q<?= number_format($resumen['monto_total'], 2) ?></div>
        <div class="text-xs text-gray-500 mt-1">Total vendido</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-2xl font-extrabold text-blue-600">Q<?= number_format($resumen['ticket_promedio'], 2) ?></div>
        <div class="text-xs text-gray-500 mt-1">Ticket promedio</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-2xl font-extrabold text-red-600">Q<?= number_format($resumen['credito_pendiente'], 2) ?></div>
        <div class="text-xs text-gray-500 mt-1">Crédito pendiente</div>
    </div>
</div>

<!-- Tarjetas de ganancias -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-orange-50 border-2 border-orange-200 rounded-2xl shadow p-5 text-center">
        <div class="text-2xl font-extrabold text-orange-600">Q<?= number_format($resumen['costo_total'], 2) ?></div>
        <div class="text-xs text-gray-500 mt-1">Costo de mercadería vendida</div>
    </div>
    <div class="bg-emerald-50 border-2 border-emerald-300 rounded-2xl shadow p-5 text-center">
        <div class="text-2xl font-extrabold <?= $resumen['ganancia_liquida'] >= 0 ? 'text-emerald-700' : 'text-red-600' ?>">
            Q<?= number_format($resumen['ganancia_liquida'], 2) ?>
        </div>
        <div class="text-xs text-gray-500 mt-1">💰 Ganancia líquida</div>
    </div>
    <div class="bg-blue-50 border-2 border-blue-200 rounded-2xl shadow p-5 text-center">
        <div class="text-2xl font-extrabold text-blue-700"><?= number_format($resumen['margen_pct'], 1) ?>%</div>
        <div class="text-xs text-gray-500 mt-1">Margen de ganancia</div>
    </div>
</div>

<!-- Gráficas -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">

    <!-- Ventas por día -->
    <div class="bg-white rounded-2xl shadow p-5">
        <h3 class="font-bold text-gray-700 mb-4">📈 Ventas por día</h3>
        <?php if (empty($ventasPorDia)): ?>
            <div class="text-center text-gray-400 py-10">Sin datos en este período</div>
        <?php else: ?>
            <canvas id="graficaVentas" height="200"></canvas>
        <?php endif; ?>
    </div>

    <!-- Top productos (dona) -->
    <div class="bg-white rounded-2xl shadow p-5">
        <h3 class="font-bold text-gray-700 mb-4">🏆 Productos más vendidos</h3>
        <?php if (empty($topProductos)): ?>
            <div class="text-center text-gray-400 py-10">Sin datos en este período</div>
        <?php else: ?>
            <canvas id="graficaProductos" height="200"></canvas>
        <?php endif; ?>
    </div>

</div>

<!-- Ventas por tipo de pago -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
    <div class="bg-white rounded-2xl shadow p-5">
        <h3 class="font-bold text-gray-700 mb-4">💳 Ventas por tipo de pago</h3>
        <?php if (empty($porTipoPago)): ?>
            <div class="text-center text-gray-400 py-10">Sin datos en este período</div>
        <?php else: ?>
            <canvas id="graficaPago" height="200"></canvas>
        <?php endif; ?>
    </div>

    <!-- Tabla top productos -->
    <div class="bg-white rounded-2xl shadow p-5">
        <h3 class="font-bold text-gray-700 mb-4">📋 Detalle productos más vendidos</h3>
        <?php if (empty($topProductos)): ?>
            <div class="text-center text-gray-400 py-10">Sin datos en este período</div>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-3 py-2 text-left">#</th>
                    <th class="px-3 py-2 text-left">Producto</th>
                    <th class="px-3 py-2 text-right">Cantidad</th>
                    <th class="px-3 py-2 text-right">Monto</th>
                    <th class="px-3 py-2 text-right">Ganancia</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($topProductos as $i => $p): ?>
                <tr class="hover:bg-green-50">
                    <td class="px-3 py-2 text-gray-400 font-bold"><?= $i+1 ?></td>
                    <td class="px-3 py-2 font-bold text-gray-800"><?= htmlspecialchars($p['nombre']) ?></td>
                    <td class="px-3 py-2 text-right text-gray-600"><?= number_format($p['total_cantidad'], 2) ?></td>
                    <td class="px-3 py-2 text-right font-bold text-green-700">Q<?= number_format($p['total_monto'], 2) ?></td>
                    <td class="px-3 py-2 text-right font-bold <?= $p['ganancia'] >= 0 ? 'text-blue-600' : 'text-red-600' ?>">Q<?= number_format($p['ganancia'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php
// Preparar datos para JS
$diasLabels   = jsonJs(array_map(fn($v) => date('d/m', strtotime($v['dia'])), $ventasPorDia));
$diasMontos   = jsonJs(array_map(fn($v) => round($v['monto_total'], 2), $ventasPorDia));
$prodLabels   = jsonJs(array_map(fn($p) => $p['nombre'], $topProductos));
$prodCants    = jsonJs(array_map(fn($p) => round($p['total_cantidad'], 2), $topProductos));
$pagoLabels   = jsonJs(array_map(fn($p) => ucfirst($p['tipo_pago']), $porTipoPago));
$pagoMontos   = jsonJs(array_map(fn($p) => round($p['monto_total'], 2), $porTipoPago));
?>

<script>
const coloresProductos = [
    '#2563eb','#1b4bc0','#4db8ff','#415a77','#a3c0e3',
    '#1b263b','#dbe9fb','#0ea5e9','#8b5cf6','#f59e0b'
];
const coloresPago = ['#16a34a','#0ea5e9','#f59e0b'];

<?php if (!empty($ventasPorDia)): ?>
new Chart(document.getElementById('graficaVentas'), {
    type: 'bar',
    data: {
        labels: <?= $diasLabels ?>,
        datasets: [{
            label: 'Total vendido (Q)',
            data: <?= $diasMontos ?>,
            backgroundColor: '#2563eb',
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'Q' + v } }
        }
    }
});
<?php endif; ?>

<?php if (!empty($topProductos)): ?>
new Chart(document.getElementById('graficaProductos'), {
    type: 'doughnut',
    data: {
        labels: <?= $prodLabels ?>,
        datasets: [{
            data: <?= $prodCants ?>,
            backgroundColor: coloresProductos,
            borderWidth: 2,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 } } }
        }
    }
});
<?php endif; ?>

<?php if (!empty($porTipoPago)): ?>
new Chart(document.getElementById('graficaPago'), {
    type: 'doughnut',
    data: {
        labels: <?= $pagoLabels ?>,
        datasets: [{
            data: <?= $pagoMontos ?>,
            backgroundColor: coloresPago,
            borderWidth: 2,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { callbacks: { label: ctx => ' Q' + ctx.parsed.toFixed(2) } }
        }
    }
});
<?php endif; ?>
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>