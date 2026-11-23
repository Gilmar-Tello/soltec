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
    <?php include RAIZ . '/app/vistas/plantillas/tema.php'; ?>
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

        <?php
        $totalGeneralEfectivo = array_sum(array_column($sesiones, 'total_efectivo'));
        $totalGeneralTarjeta  = array_sum(array_column($sesiones, 'total_tarjeta'));
        $totalGeneralCredito  = array_sum(array_column($sesiones, 'total_credito'));
        ?>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-green-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-extrabold text-green-700"><?= count($sesiones) ?></div>
                <div class="text-xs text-gray-500 mt-1">Sesiones de caja</div>
            </div>
            <div class="bg-blue-50 rounded-xl p-4 text-center">
                <div class="text-xl font-extrabold text-blue-700">Q<?= number_format($totalGeneralEfectivo, 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">💵 Total efectivo</div>
            </div>
            <div class="bg-purple-50 rounded-xl p-4 text-center">
                <div class="text-xl font-extrabold text-purple-700">Q<?= number_format($totalGeneralTarjeta, 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">💳 Total tarjeta</div>
            </div>
            <div class="bg-orange-50 rounded-xl p-4 text-center">
                <div class="text-xl font-extrabold text-orange-700">Q<?= number_format($totalGeneralCredito, 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">📒 Total crédito</div>
            </div>
        </div>

        <?php if (empty($sesiones)): ?>
            <div class="text-center py-12 text-gray-400 text-lg">No hay sesiones de caja en este período.</div>
        <?php else: ?>
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-green-700 text-white">
                    <th class="px-2 py-2 text-left">Usuario</th>
                    <th class="px-2 py-2 text-left">Apertura</th>
                    <th class="px-2 py-2 text-left">Cierre</th>
                    <th class="px-2 py-2 text-right">Inicial</th>
                    <th class="px-2 py-2 text-right">Efectivo</th>
                    <th class="px-2 py-2 text-right">Tarjeta</th>
                    <th class="px-2 py-2 text-right">Crédito</th>
                    <th class="px-2 py-2 text-right">Final</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sesiones as $i => $s): ?>
                <tr class="<?= $i%2===0?'bg-white':'bg-gray-50' ?> border-b border-gray-100">
                    <td class="px-2 py-2 font-bold text-gray-800 text-xs"><?= htmlspecialchars($s['usuario_nombre']) ?></td>
                    <td class="px-2 py-2 text-gray-600 text-xs"><?= date('d/m/Y H:i', strtotime($s['abierta_en'])) ?></td>
                    <td class="px-2 py-2 text-gray-600 text-xs"><?= $s['cerrada_en'] ? date('d/m/Y H:i', strtotime($s['cerrada_en'])) : '—' ?></td>
                    <td class="px-2 py-2 text-right text-xs">Q<?= number_format($s['monto_inicial'], 2) ?></td>
                    <td class="px-2 py-2 text-right text-emerald-600 font-bold text-xs">Q<?= number_format($s['total_efectivo'], 2) ?></td>
                    <td class="px-2 py-2 text-right text-blue-600 font-bold text-xs">Q<?= number_format($s['total_tarjeta'], 2) ?></td>
                    <td class="px-2 py-2 text-right text-orange-600 font-bold text-xs">Q<?= number_format($s['total_credito'], 2) ?></td>
                    <td class="px-2 py-2 text-right text-xs"><?= $s['monto_final'] !== null ? 'Q'.number_format($s['monto_final'],2) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="border-t border-gray-200 mt-6 pt-4 text-xs text-gray-400 text-center">
            POS GT — <?= htmlspecialchars($config['nombre_negocio']) ?> &copy; <?= date('Y') ?> · Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
        </div>
    </div>
</body>
</html>