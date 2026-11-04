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
    <style>
        body { font-family: 'Nunito', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-6">

    <div class="no-print flex items-center justify-between bg-white rounded-2xl shadow px-6 py-4 mb-6 max-w-5xl mx-auto">
        <span class="text-gray-400 text-sm">📄 Vista previa</span>
        <h1 class="font-extrabold text-green-800"><?= $tituloPagina ?></h1>
        <button onclick="window.print()"
                class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl transition">
            🖨️ Descargar / Imprimir PDF
        </button>
    </div>

    <div class="bg-white max-w-5xl mx-auto rounded-2xl shadow p-8">

        <div class="flex items-center justify-between border-b-2 border-green-600 pb-4 mb-6">
            <div class="flex items-center gap-3">
                <?php if (!empty($config['logo'])): ?>
                    <img src="<?= URL_BASE ?>/<?= htmlspecialchars($config['logo']) ?>" alt="Logo" class="w-12 h-12 object-cover rounded-xl">
                <?php endif; ?>
                <div>
                    <div class="text-2xl font-extrabold text-green-800"><?= htmlspecialchars($config['nombre_negocio']) ?></div>
                <?php if (!empty($config['nombre_sucursal'])): ?><div class="text-base font-bold text-gray-700"><?= htmlspecialchars($config['nombre_sucursal']) ?></div><?php endif; ?>
                    <div class="text-gray-500 text-sm"><?= htmlspecialchars($config['direccion'] ?? '') ?></div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xl font-bold text-gray-800"><?= htmlspecialchars($tituloPagina) ?></div>
                <div class="text-gray-400 text-sm">Período: <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?></div>
                <div class="text-gray-400 text-sm">Generado: <?= date('d/m/Y H:i') ?> · <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></div>
            </div>
        </div>

        <!-- Resumen -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-green-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-extrabold text-green-700"><?= count($ventas) ?></div>
                <div class="text-xs text-gray-500 mt-1">Ventas totales</div>
            </div>
            <div class="bg-blue-50 rounded-xl p-4 text-center">
                <div class="text-xl font-extrabold text-blue-700">Q<?= number_format($totalPorTipo['efectivo'], 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">💵 Efectivo</div>
            </div>
            <div class="bg-purple-50 rounded-xl p-4 text-center">
                <div class="text-xl font-extrabold text-purple-700">Q<?= number_format($totalPorTipo['tarjeta'], 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">💳 Tarjeta</div>
            </div>
            <div class="bg-orange-50 rounded-xl p-4 text-center">
                <div class="text-xl font-extrabold text-orange-700">Q<?= number_format($totalPorTipo['credito'], 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">📒 Crédito</div>
            </div>
        </div>

        <?php if (empty($ventas)): ?>
            <div class="text-center py-12 text-gray-400 text-lg">No hay ventas en este período.</div>
        <?php else: ?>
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-green-700 text-white">
                    <th class="px-3 py-2 text-left">#</th>
                    <th class="px-3 py-2 text-left">Fecha</th>
                    <th class="px-3 py-2 text-left">Cliente</th>
                    <th class="px-3 py-2 text-left">Cajero</th>
                    <th class="px-3 py-2 text-center">Pago</th>
                    <th class="px-3 py-2 text-left">Nota</th>
                    <th class="px-3 py-2 text-right">Total</th>
                    <th class="px-3 py-2 text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ventas as $i => $v): ?>
                <tr class="<?= $i%2===0?'bg-white':'bg-gray-50' ?> border-b border-gray-100">
                    <td class="px-3 py-2 font-mono text-xs text-gray-500">#<?= $v['id'] ?></td>
                    <td class="px-3 py-2 text-gray-600 text-xs"><?= date('d/m/Y H:i', strtotime($v['creado_en'])) ?></td>
                    <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars($v['cliente_nombre'] ?? 'Consumidor Final') ?></td>
                    <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars($v['cajero']) ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= ucfirst($v['tipo_pago']) ?></td>
                    <td class="px-3 py-2 text-gray-500 text-xs"><?= htmlspecialchars($v['notas'] ?? '—') ?></td>
                    <td class="px-3 py-2 text-right font-bold text-green-700">Q<?= number_format($v['total'], 2) ?></td>
                    <td class="px-3 py-2 text-center">
                        <?php if ($v['estado_pago']==='pagado'): ?>
                            <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅</span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full">⏳</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="bg-green-50">
                    <td colspan="6" class="px-3 py-2 text-right font-extrabold text-gray-700">TOTAL DEL PERÍODO</td>
                    <td class="px-3 py-2 text-right font-extrabold text-green-700 text-base">Q<?= number_format($totalVentas, 2) ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="border-t border-gray-200 mt-6 pt-4 text-xs text-gray-400 text-center">
            POS GT — <?= htmlspecialchars($config['nombre_negocio']) ?> &copy; <?= date('Y') ?> · Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
        </div>
    </div>
</body>
</html>