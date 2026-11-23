<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

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

<div class="flex items-center gap-3 mb-6">
    <a href="<?= URL_BASE ?>/?url=cotizaciones/index" class="text-green-700 hover:text-green-900 text-2xl">←</a>
    <div class="flex-1">
        <h2 class="text-2xl font-extrabold text-green-800">📝 Cotización #<?= $cotizacion['id'] ?></h2>
        <p class="text-gray-500 text-sm">
            <?= date('d/m/Y H:i', strtotime($cotizacion['creado_en'])) ?> · Por <?= htmlspecialchars($cotizacion['usuario_nombre']) ?>
        </p>
    </div>
    <a href="<?= URL_BASE ?>/?url=cotizaciones/imprimir/<?= $cotizacion['id'] ?>"
       class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2 rounded-xl text-sm transition">
        🖨️ Imprimir
    </a>
</div>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-4 text-center">
        <div class="text-xs text-gray-400 mb-1">Cliente</div>
        <div class="font-bold text-gray-700"><?= htmlspecialchars($cotizacion['cliente_nombre'] ?? 'Sin cliente') ?></div>
        <?php if (!empty($cotizacion['cliente_telefono'])): ?>
        <div class="text-xs text-gray-400"><?= htmlspecialchars($cotizacion['cliente_telefono']) ?></div>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center">
        <div class="text-xs text-gray-400 mb-1">Estado</div>
        <?php if ($cotizacion['estado'] === 'abierta'): ?>
            <span class="bg-blue-100 text-blue-700 text-sm font-bold px-3 py-1 rounded-full">📝 Abierta</span>
        <?php elseif ($cotizacion['estado'] === 'convertida'): ?>
            <span class="bg-emerald-100 text-emerald-700 text-sm font-bold px-3 py-1 rounded-full">✅ Convertida</span>
        <?php else: ?>
            <span class="bg-gray-100 text-gray-500 text-sm font-bold px-3 py-1 rounded-full">🗑️ Eliminada</span>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center">
        <div class="text-xs text-gray-400 mb-1">Descuento</div>
        <div class="font-bold text-gray-700">Q<?= number_format($cotizacion['descuento'], 2) ?></div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center">
        <div class="text-xs text-gray-400 mb-1">Total</div>
        <div class="font-extrabold text-green-700 text-lg">Q<?= number_format($cotizacion['total'], 2) ?></div>
    </div>
</div>

<?php if (!empty($cotizacion['notas'])): ?>
<div class="bg-white rounded-2xl shadow p-4 mb-6 text-sm">
    <span class="text-gray-400">Notas:</span> <?= htmlspecialchars($cotizacion['notas']) ?>
</div>
<?php endif; ?>

<?php if ($cotizacion['estado'] === 'convertida' && $ventaResultante): ?>
<div class="bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-xl p-4 mb-5">
    ✅ Esta cotización ya se convirtió en una venta.
    <a href="<?= URL_BASE ?>/?url=pos/ticket/<?= $ventaResultante ?>" class="underline font-bold">Ver venta #<?= $ventaResultante ?></a>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100 font-bold text-gray-700">Productos cotizados</div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Producto</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3 text-right">Precio cotizado</th>
                    <th class="px-4 py-3 text-right">Precio actual</th>
                    <th class="px-4 py-3 text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($detalle as $d):
                    $cambioPrecio = abs($d['precio_actual'] - $d['precio_unitario']) > 0.001;
                ?>
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-bold text-gray-800"><?= htmlspecialchars($d['producto_nombre']) ?></div>
                        <div class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($d['producto_codigo'] ?? '—') ?></div>
                    </td>
                    <td class="px-4 py-3 text-right"><?= number_format($d['cantidad'], 2) ?> <?= htmlspecialchars($d['unidad']) ?></td>
                    <td class="px-4 py-3 text-right font-bold text-gray-700">Q<?= number_format($d['precio_unitario'], 2) ?></td>
                    <td class="px-4 py-3 text-right <?= $cambioPrecio ? 'font-bold text-orange-500' : 'text-gray-400' ?>">
                        Q<?= number_format($d['precio_actual'], 2) ?>
                        <?php if ($cambioPrecio): ?><div class="text-xs">⚠️ cambió</div><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right font-bold text-green-700">Q<?= number_format($d['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($cotizacion['estado'] === 'abierta'): ?>
<div class="flex flex-wrap gap-3">
    <a href="<?= URL_BASE ?>/?url=pos/venta&cotizacion_id=<?= $cotizacion['id'] ?>"
       class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-3 rounded-xl transition">
        🛒 Convertir en Venta
    </a>
    <a href="<?= URL_BASE ?>/?url=cotizaciones/eliminar/<?= $cotizacion['id'] ?>"
       onclick="return confirm('¿Eliminar esta cotización? Esta acción no se puede deshacer.')"
       class="bg-red-100 hover:bg-red-200 text-red-700 font-bold px-5 py-3 rounded-xl transition">
        🗑️ Eliminar
    </a>
</div>
<?php endif; ?>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
