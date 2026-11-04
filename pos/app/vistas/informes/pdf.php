<?php
if (!isset($_SESSION['usuario_id'])) { echo '<p style="padding:20px;color:red;">Sesión expirada.</p>'; exit; }
$config = configEfectiva(sucursalTrabajo());   // datos del encabezado: los de la sucursal en vista (o los del negocio)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tituloPagina ?> — POS GT</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="<?= URL_BASE ?>/app/public/vendor/tailwind-3.4.17.js"></script>
    <script src="<?= URL_BASE ?>/app/public/vendor/chart-4.4.7.umd.js"></script>
    <style>
        body { font-family: 'Nunito', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            canvas { max-width: 100% !important; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-6">

    <!-- Barra superior -->
    <div class="no-print flex items-center justify-between bg-white rounded-2xl shadow px-6 py-4 mb-6 max-w-5xl mx-auto">
        <span class="text-gray-400 text-sm">📄 Vista previa</span>
        <h1 class="font-extrabold text-green-800"><?= $tituloPagina ?></h1>
        <button onclick="window.print()"
                class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl transition">
            🖨️ Descargar / Imprimir PDF
        </button>
    </div>

    <!-- Documento -->
    <div class="bg-white max-w-5xl mx-auto rounded-2xl shadow p-8">

        <!-- Encabezado -->
        <div class="flex items-center justify-between border-b-2 border-green-600 pb-4 mb-6">
            <div class="flex items-center gap-3">
                <?php if ($config['logo']): ?>
                    <img src="<?= URL_BASE ?>/<?= htmlspecialchars($config['logo']) ?>" alt="Logo" class="w-12 h-12 object-cover rounded-xl">
                <?php endif; ?>
                <div>
                    <div class="text-2xl font-extrabold text-green-800"><?= htmlspecialchars($config['nombre_negocio']) ?></div>
                <?php if (!empty($config['nombre_sucursal'])): ?><div class="text-base font-bold text-gray-700"><?= htmlspecialchars($config['nombre_sucursal']) ?></div><?php endif; ?>
                    <div class="text-gray-500 text-sm"><?= htmlspecialchars($config['direccion'] ?? '') ?></div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xl font-bold text-gray-800">Informe de Ventas</div>
                <div class="text-gray-400 text-sm">Período: <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?></div>
                <?php if (!empty($sucursalNombre)): ?>
                    <div class="text-gray-500 text-sm font-bold">Sucursal: <?= htmlspecialchars($sucursalNombre) ?></div>
                <?php endif; ?>
                <div class="text-gray-400 text-sm">Generado: <?= date('d/m/Y H:i') ?> · <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></div>
            </div>
        </div>

        <!-- Tarjetas resumen -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
            <div class="bg-green-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-extrabold text-green-700"><?= $resumen['total_ventas'] ?></div>
                <div class="text-xs text-gray-500 mt-1">Ventas realizadas</div>
            </div>
            <div class="bg-blue-50 rounded-xl p-4 text-center">
                <div class="text-xl font-extrabold text-blue-700">Q<?= number_format($resumen['monto_total'], 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">Total vendido</div>
            </div>
            <div class="bg-purple-50 rounded-xl p-4 text-center">
                <div class="text-xl font-extrabold text-purple-700">Q<?= number_format($resumen['ticket_promedio'], 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">Ticket promedio</div>
            </div>
            <div class="bg-red-50 rounded-xl p-4 text-center">
                <div class="text-xl font-extrabold text-red-600">Q<?= number_format($resumen['credito_pendiente'], 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">Crédito pendiente</div>
            </div>
        </div>

        <!-- Tarjetas de ganancias -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-orange-50 border-2 border-orange-200 rounded-xl p-4 text-center">
                <div class="text-xl font-extrabold text-orange-600">Q<?= number_format($resumen['costo_total'], 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">Costo de mercadería vendida</div>
            </div>
            <div class="bg-green-50 border-2 border-green-300 rounded-xl p-4 text-center">
                <div class="text-xl font-extrabold <?= $resumen['ganancia_liquida'] >= 0 ? 'text-green-700' : 'text-red-600' ?>">
                    Q<?= number_format($resumen['ganancia_liquida'], 2) ?>
                </div>
                <div class="text-xs text-gray-500 mt-1">💰 Ganancia líquida</div>
            </div>
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 text-center">
                <div class="text-xl font-extrabold text-blue-700"><?= number_format($resumen['margen_pct'], 1) ?>%</div>
                <div class="text-xs text-gray-500 mt-1">Margen de ganancia</div>
            </div>
        </div>

        <!-- Gráficas -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
            <div>
                <h3 class="font-bold text-gray-700 mb-3">📈 Ventas por día</h3>
                <?php if (empty($ventasPorDia)): ?>
                    <div class="text-center text-gray-400 py-8 bg-gray-50 rounded-xl">Sin datos</div>
                <?php else: ?>
                    <canvas id="graficaVentas" height="220"></canvas>
                <?php endif; ?>
            </div>
            <div>
                <h3 class="font-bold text-gray-700 mb-3">🏆 Productos más vendidos</h3>
                <?php if (empty($topProductos)): ?>
                    <div class="text-center text-gray-400 py-8 bg-gray-50 rounded-xl">Sin datos</div>
                <?php else: ?>
                    <canvas id="graficaProductos" height="220"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tipo de pago -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
            <div>
                <h3 class="font-bold text-gray-700 mb-3">💳 Por tipo de pago</h3>
                <?php if (empty($porTipoPago)): ?>
                    <div class="text-center text-gray-400 py-8 bg-gray-50 rounded-xl">Sin datos</div>
                <?php else: ?>
                    <canvas id="graficaPago" height="220"></canvas>
                <?php endif; ?>
            </div>

            <!-- Tabla tipo de pago -->
            <div>
                <h3 class="font-bold text-gray-700 mb-3">📋 Resumen por tipo de pago</h3>
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-green-700 text-white">
                            <th class="px-3 py-2 text-left">Tipo</th>
                            <th class="px-3 py-2 text-right">Ventas</th>
                            <th class="px-3 py-2 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($porTipoPago as $p): ?>
                        <tr class="border-b border-gray-100">
                            <td class="px-3 py-2 font-bold text-gray-700"><?= ucfirst($p['tipo_pago']) ?></td>
                            <td class="px-3 py-2 text-right text-gray-600"><?= $p['total_ventas'] ?></td>
                            <td class="px-3 py-2 text-right font-bold text-green-700">Q<?= number_format($p['monto_total'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabla top productos -->
        <h3 class="font-bold text-gray-700 mb-3">🏆 Top 10 productos más vendidos</h3>
        <?php if (empty($topProductos)): ?>
            <div class="text-center text-gray-400 py-8 bg-gray-50 rounded-xl mb-6">Sin datos en este período</div>
        <?php else: ?>
        <table class="w-full text-sm border-collapse mb-8">
            <thead>
                <tr class="bg-green-700 text-white">
                    <th class="px-3 py-2 text-left">#</th>
                    <th class="px-3 py-2 text-left">Producto</th>
                    <th class="px-3 py-2 text-right">Cantidad vendida</th>
                    <th class="px-3 py-2 text-right">Monto total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topProductos as $i => $p): ?>
                <tr class="<?= $i%2===0?'bg-white':'bg-gray-50' ?> border-b border-gray-100">
                    <td class="px-3 py-2 font-bold text-gray-400"><?= $i+1 ?></td>
                    <td class="px-3 py-2 font-bold text-gray-800"><?= htmlspecialchars($p['nombre']) ?></td>
                    <td class="px-3 py-2 text-right text-gray-600"><?= number_format($p['total_cantidad'], 2) ?></td>
                    <td class="px-3 py-2 text-right font-bold text-green-700">Q<?= number_format($p['total_monto'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Tabla ventas por día -->
        <?php if (!empty($ventasPorDia)): ?>
        <h3 class="font-bold text-gray-700 mb-3">📅 Ventas por día</h3>
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-green-700 text-white">
                    <th class="px-3 py-2 text-left">Fecha</th>
                    <th class="px-3 py-2 text-right">Nº Ventas</th>
                    <th class="px-3 py-2 text-right">Monto total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ventasPorDia as $i => $v): ?>
                <tr class="<?= $i%2===0?'bg-white':'bg-gray-50' ?> border-b border-gray-100">
                    <td class="px-3 py-2 text-gray-700"><?= date('d/m/Y', strtotime($v['dia'])) ?></td>
                    <td class="px-3 py-2 text-right text-gray-600"><?= $v['total_ventas'] ?></td>
                    <td class="px-3 py-2 text-right font-bold text-green-700">Q<?= number_format($v['monto_total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="border-t border-gray-200 mt-6 pt-4 text-xs text-gray-400 text-center">
            POS GT — SISTEMAS GT &copy; <?= date('Y') ?> · Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
        </div>
    </div>
</body>
</html>

<?php
// Gráficas en el PDF
$diasLabels = jsonJs(array_map(fn($v) => date('d/m', strtotime($v['dia'])), $ventasPorDia));
$diasMontos = jsonJs(array_map(fn($v) => round($v['monto_total'], 2), $ventasPorDia));
$prodLabels = jsonJs(array_map(fn($p) => $p['nombre'], $topProductos));
$prodCants  = jsonJs(array_map(fn($p) => round($p['total_cantidad'], 2), $topProductos));
$pagoLabels = jsonJs(array_map(fn($p) => ucfirst($p['tipo_pago']), $porTipoPago));
$pagoMontos = jsonJs(array_map(fn($p) => round($p['monto_total'], 2), $porTipoPago));
?>
<script>
<?php if (!empty($ventasPorDia)): ?>
new Chart(document.getElementById('graficaVentas'), {
    type: 'bar',
    data: {
        labels: <?= $diasLabels ?>,
        datasets: [{ label: 'Total (Q)', data: <?= $diasMontos ?>, backgroundColor: '#16a34a', borderRadius: 6 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => 'Q'+v } } } }
});
<?php endif; ?>
<?php if (!empty($topProductos)): ?>
new Chart(document.getElementById('graficaProductos'), {
    type: 'doughnut',
    data: { labels: <?= $prodLabels ?>, datasets: [{ data: <?= $prodCants ?>, backgroundColor: ['#16a34a','#15803d','#22c55e','#4ade80','#86efac','#166534','#dcfce7','#0ea5e9','#8b5cf6','#f59e0b'], borderWidth: 2 }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
});
<?php endif; ?>
<?php if (!empty($porTipoPago)): ?>
new Chart(document.getElementById('graficaPago'), {
    type: 'doughnut',
    data: { labels: <?= $pagoLabels ?>, datasets: [{ data: <?= $pagoMontos ?>, backgroundColor: ['#16a34a','#0ea5e9','#f59e0b'], borderWidth: 2 }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: ctx => ' Q'+ctx.parsed.toFixed(2) } } } }
});
<?php endif; ?>
</script>