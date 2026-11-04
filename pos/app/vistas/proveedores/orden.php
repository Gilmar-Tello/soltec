<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=proveedores/index" class="text-green-700 font-bold text-xl">←</a>
        <div>
            <h2 class="text-2xl font-extrabold text-green-800">📄 Orden de Compra #<?= $orden['id'] ?></h2>
            <p class="text-gray-500 text-sm"><?= htmlspecialchars($orden['proveedor_nombre']) ?></p>
        </div>
    </div>

    <?php if (isset($_SESSION['exito'])): ?>
        <div class="bg-green-50 border border-green-300 text-green-800 rounded-xl p-4 mb-5">
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

    <!-- Info de la orden -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow p-4 text-center">
            <div class="text-xs text-gray-400 mb-1">Fecha</div>
            <div class="font-bold text-gray-700 text-sm"><?= date('d/m/Y', strtotime($orden['creado_en'])) ?></div>
        </div>
        <div class="bg-white rounded-2xl shadow p-4 text-center">
            <div class="text-xs text-gray-400 mb-1">Estado</div>
            <?php
            $estadoColor = match($orden['estado']) {
                'pendiente' => 'text-yellow-600 bg-yellow-50',
                'recibida'  => 'text-green-600 bg-green-50',
                'cancelada' => 'text-red-600 bg-red-50',
                default     => 'text-gray-600'
            };
            $estadoIcon = match($orden['estado']) {
                'pendiente' => '⏳', 'recibida' => '✅', 'cancelada' => '❌', default => ''
            };
            ?>
            <div class="font-bold <?= $estadoColor ?> px-2 py-1 rounded-lg text-sm">
                <?= $estadoIcon ?> <?= ucfirst($orden['estado']) ?>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow p-4 text-center">
            <div class="text-xs text-gray-400 mb-1">Creada por</div>
            <div class="font-bold text-gray-700 text-sm"><?= htmlspecialchars($orden['usuario_nombre']) ?></div>
        </div>
        <div class="bg-white rounded-2xl shadow p-4 text-center">
            <div class="text-xs text-gray-400 mb-1">Total</div>
            <div class="font-extrabold text-green-700 text-lg">Q<?= number_format($orden['total'], 2) ?></div>
        </div>
    </div>

    <!-- Detalle -->
    <div class="bg-white rounded-2xl shadow overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <span class="font-bold text-gray-700">Productos ordenados</span>
            <a href="<?= URL_BASE ?>/?url=proveedores/pdfOrdenSimple/<?= $orden['id'] ?>"
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition">
                📥 Descargar Lista PDF
            </a>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">Producto</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3 text-right">Precio unit.</th>
                    <th class="px-4 py-3 text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($detalle as $d): ?>
                <tr class="hover:bg-green-50">
                    <td class="px-4 py-3 font-bold text-gray-800"><?= htmlspecialchars($d['producto_nombre']) ?></td>
                    <td class="px-4 py-3 text-right"><?= number_format($d['cantidad'], 2) ?> <?= $d['unidad'] ?></td>
                    <td class="px-4 py-3 text-right">Q<?= number_format($d['precio_unitario'], 2) ?></td>
                    <td class="px-4 py-3 text-right font-bold text-green-700">Q<?= number_format($d['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="bg-green-50">
                    <td colspan="3" class="px-4 py-3 text-right font-bold text-gray-700">Total</td>
                    <td class="px-4 py-3 text-right font-extrabold text-green-700 text-lg">
                        Q<?= number_format($orden['total'], 2) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php if ($orden['notas']): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-4 mb-6 text-sm text-gray-700">
            📝 <b>Notas:</b> <?= htmlspecialchars($orden['notas']) ?>
        </div>
    <?php endif; ?>

    <!-- Acción: recibir orden -->
    <?php if ($orden['estado'] === 'pendiente'): ?>
        <div class="bg-white rounded-2xl shadow p-5 flex items-center justify-between">
            <div>
                <div class="font-bold text-gray-700">¿Ya llegó la mercancía?</div>
                <div class="text-gray-400 text-sm">Al marcar como recibida, el stock se actualiza automáticamente.</div>
            </div>
            <a href="<?= URL_BASE ?>/?url=proveedores/recibirOrden/<?= $orden['id'] ?>"
               onclick="return confirm('¿Confirmar recepción? Esto actualizará el stock de todos los productos.')"
               class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-3 rounded-xl transition">
                ✅ Marcar como Recibida
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>