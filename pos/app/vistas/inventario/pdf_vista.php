<?php
// Verificar sesión activa
if (!isset($_SESSION['usuario_id'])) {
    echo '<p style="padding:20px;color:red;">Sesión expirada. Por favor recargá la página.</p>';
    exit;
}
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
            .print-shadow { box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-6">

    <!-- Barra superior (no se imprime) -->
    <div class="no-print flex items-center justify-between bg-white rounded-2xl shadow px-6 py-4 mb-6 max-w-5xl mx-auto">
        <span class="text-gray-400 text-sm">📄 Vista previa</span>
        <h1 class="font-extrabold text-green-800 text-lg"><?= $tituloPagina ?></h1>
        <button onclick="window.print()"
                class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl transition">
            🖨️ Descargar / Imprimir PDF
        </button>
    </div>

    <!-- Documento PDF -->
    <div class="bg-white max-w-5xl mx-auto rounded-2xl shadow print-shadow p-8">

        <!-- Encabezado del reporte -->
        <div class="flex items-center justify-between border-b-2 border-green-600 pb-4 mb-6">
            <div class="flex items-center gap-3">
                <?php if ($config['logo']): ?>
                    <img src="<?= URL_BASE ?>/<?= htmlspecialchars($config['logo']) ?>" alt="Logo"
                         class="w-12 h-12 object-cover rounded-xl">
                <?php endif; ?>
                <div>
                    <div class="text-2xl font-extrabold text-green-800"><?= htmlspecialchars($config['nombre_negocio']) ?></div>
                <?php if (!empty($config['nombre_sucursal'])): ?><div class="text-base font-bold text-gray-700"><?= htmlspecialchars($config['nombre_sucursal']) ?></div><?php endif; ?>
                    <div class="text-gray-500 text-sm"><?= htmlspecialchars($config['direccion'] ?? '') ?></div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xl font-bold text-gray-800"><?= $tituloPagina ?></div>
                <div class="text-gray-400 text-sm">Generado: <?= date('d/m/Y H:i') ?></div>
                <div class="text-gray-400 text-sm">Usuario: <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></div>
            </div>
        </div>

        <!-- Totales rápidos -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-green-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-extrabold text-green-700"><?= count($productos) ?></div>
                <div class="text-xs text-gray-500 mt-1">
                    <?php if ($tipoPdf === 'productos') echo 'Total productos';
                    elseif ($tipoPdf === 'stock_bajo') echo 'Con stock bajo';
                    else echo 'Próximos a vencer'; ?>
                </div>
            </div>
            <?php if ($tipoPdf === 'productos'): ?>
                <?php
                $totalStock = array_sum(array_column($productos, 'stock_actual'));
                $totalValor = array_sum(array_map(fn($p) => $p['stock_actual'] * $p['precio_venta_real'], $productos));
                ?>
                <div class="bg-blue-50 rounded-xl p-4 text-center">
                    <div class="text-2xl font-extrabold text-blue-700"><?= number_format($totalStock, 0) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Unidades en stock</div>
                </div>
                <div class="bg-purple-50 rounded-xl p-4 text-center">
                    <div class="text-2xl font-extrabold text-purple-700">Q<?= number_format($totalValor, 2) ?></div>
                    <div class="text-xs text-gray-500 mt-1">Valor del inventario</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tabla de productos -->
        <?php if (empty($productos)): ?>
            <div class="text-center py-12 text-gray-400 text-lg">
                ✅ No hay productos en esta categoría de reporte.
            </div>
        <?php else: ?>
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-green-700 text-white">
                    <th class="px-3 py-2 text-left">Código</th>
                    <th class="px-3 py-2 text-left">Producto</th>
                    <th class="px-3 py-2 text-left">Categoría</th>
                    <th class="px-3 py-2 text-right">Precio</th>
                    <th class="px-3 py-2 text-right">Stock</th>
                    <?php if ($tipoPdf === 'vencimientos'): ?>
                        <th class="px-3 py-2 text-center">Vence</th>
                        <th class="px-3 py-2 text-center">Días</th>
                    <?php elseif ($tipoPdf === 'productos'): ?>
                        <th class="px-3 py-2 text-right">Valor</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $i => $p): ?>
                    <?php
                    $fila = $i % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                    $dias = $p['dias_restantes'] ?? null;
                    $alertaColor = '';
                    if ($tipoPdf === 'vencimientos') {
                        if ($dias <= 7)  $alertaColor = 'bg-red-50';
                        elseif ($dias <= 15) $alertaColor = 'bg-orange-50';
                        else $alertaColor = 'bg-yellow-50';
                    }
                    if ($tipoPdf === 'stock_bajo') $alertaColor = 'bg-red-50';
                    $filaFinal = $alertaColor ?: $fila;
                    ?>
                    <tr class="<?= $filaFinal ?> border-b border-gray-100">
                        <td class="px-3 py-2 font-mono text-xs text-gray-500"><?= htmlspecialchars($p['codigo'] ?? '—') ?></td>
                        <td class="px-3 py-2 font-bold text-gray-800"><?= htmlspecialchars($p['nombre']) ?></td>
                        <td class="px-3 py-2 text-gray-600"><?= htmlspecialchars($p['categoria']) ?></td>
                        <td class="px-3 py-2 text-right font-bold">Q<?= number_format($p['precio_venta_real'], 2) ?></td>
                        <td class="px-3 py-2 text-right <?= $tipoPdf === 'stock_bajo' ? 'text-red-600 font-bold' : 'text-gray-700' ?>">
                            <?= number_format($p['stock_actual'], 2) ?> <?= $p['unidad'] ?>
                        </td>
                        <?php if ($tipoPdf === 'vencimientos'): ?>
                            <td class="px-3 py-2 text-center text-gray-700">
                                <?= date('d/m/Y', strtotime($p['fecha_vencimiento'])) ?>
                            </td>
                            <td class="px-3 py-2 text-center font-bold <?= $dias <= 7 ? 'text-red-600' : ($dias <= 15 ? 'text-orange-600' : 'text-yellow-600') ?>">
                                <?= $dias ?> días
                            </td>
                        <?php elseif ($tipoPdf === 'productos'): ?>
                            <td class="px-3 py-2 text-right text-purple-700 font-bold">
                                Q<?= number_format($p['stock_actual'] * $p['precio_venta_real'], 2) ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Pie del documento -->
        <div class="border-t border-gray-200 mt-6 pt-4 text-xs text-gray-400 text-center">
            POS GT — SISTEMAS GT &copy; <?= date('Y') ?> · Reporte generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
        </div>
    </div>

</body>
</html>