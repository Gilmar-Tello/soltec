<?php
if (!isset($_SESSION['usuario_id'])) { echo '<p style="padding:20px;color:red;">Sesión expirada.</p>'; exit; }
$config = obtenerConexion()->prepare("SELECT * FROM configuracion_negocio WHERE tenant_id = :tid LIMIT 1");
$config->execute([':tid' => tenantActual()]);
$config = $config->fetch();
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

    <!-- Barra superior -->
    <div class="no-print flex items-center justify-between bg-white rounded-2xl shadow px-6 py-4 mb-6 max-w-4xl mx-auto">
        <span class="text-gray-400 text-sm">📄 Vista previa</span>
        <h1 class="font-extrabold text-green-800 text-lg"><?= $tituloPagina ?></h1>
        <button onclick="window.print()"
                class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl transition">
            🖨️ Descargar / Imprimir PDF
        </button>
    </div>

    <!-- Documento -->
    <div class="bg-white max-w-4xl mx-auto rounded-2xl shadow p-8">

        <!-- Encabezado -->
        <div class="flex items-center justify-between border-b-2 border-green-600 pb-4 mb-6">
            <div class="flex items-center gap-3">
                <?php if ($config['logo']): ?>
                    <img src="<?= URL_BASE ?>/<?= htmlspecialchars($config['logo']) ?>" alt="Logo" class="w-12 h-12 object-cover rounded-xl">
                <?php endif; ?>
                <div>
                    <div class="text-2xl font-extrabold text-green-800"><?= htmlspecialchars($config['nombre_negocio']) ?></div>
                    <div class="text-gray-500 text-sm"><?= htmlspecialchars($config['direccion'] ?? '') ?></div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xl font-bold text-gray-800">Reporte de Clientes Deudores</div>
                <div class="text-gray-400 text-sm">Generado: <?= date('d/m/Y H:i') ?></div>
                <div class="text-gray-400 text-sm">Por: <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></div>
            </div>
        </div>

        <!-- Resumen -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-red-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-extrabold text-red-600"><?= count($clientes) ?></div>
                <div class="text-xs text-gray-500 mt-1">Clientes con deuda</div>
            </div>
            <div class="bg-orange-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-extrabold text-orange-600">Q<?= number_format($totalDeuda, 2) ?></div>
                <div class="text-xs text-gray-500 mt-1">Deuda total acumulada</div>
            </div>
        </div>

        <!-- Tabla -->
        <?php if (empty($clientes)): ?>
            <div class="text-center py-12 text-gray-400 text-lg">✅ No hay clientes con deuda pendiente.</div>
        <?php else: ?>
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-green-700 text-white">
                    <th class="px-3 py-2 text-left">Cliente</th>
                    <th class="px-3 py-2 text-left">Teléfono</th>
                    <th class="px-3 py-2 text-left">NIT</th>
                    <th class="px-3 py-2 text-center">Ventas pend.</th>
                    <th class="px-3 py-2 text-right">Límite crédito</th>
                    <th class="px-3 py-2 text-right">Deuda total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $i => $c): ?>
                <tr class="<?= $i%2===0?'bg-white':'bg-gray-50' ?> border-b border-gray-100">
                    <td class="px-3 py-2 font-bold text-gray-800"><?= htmlspecialchars($c['nombre']) ?></td>
                    <td class="px-3 py-2 text-gray-600"><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
                    <td class="px-3 py-2 font-mono text-xs text-gray-500"><?= htmlspecialchars($c['nit'] ?? 'CF') ?></td>
                    <td class="px-3 py-2 text-center text-orange-600 font-bold"><?= $c['ventas_pendientes'] ?></td>
                    <td class="px-3 py-2 text-right text-gray-600">
                        <?= $c['limite_credito'] > 0 ? 'Q'.number_format($c['limite_credito'],2) : 'Sin límite' ?>
                    </td>
                    <td class="px-3 py-2 text-right font-extrabold text-red-600">
                        Q<?= number_format($c['deuda_total'], 2) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <!-- Total -->
                <tr class="bg-red-50">
                    <td colspan="5" class="px-3 py-2 text-right font-extrabold text-gray-700">TOTAL DEUDA</td>
                    <td class="px-3 py-2 text-right font-extrabold text-red-600 text-base">
                        Q<?= number_format($totalDeuda, 2) ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="border-t border-gray-200 mt-6 pt-4 text-xs text-gray-400 text-center">
            POS GT — SISTEMAS GT &copy; <?= date('Y') ?> · Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
        </div>
    </div>
</body>
</html>