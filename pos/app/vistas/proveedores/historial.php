<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= URL_BASE ?>/?url=proveedores/index" class="text-green-700 font-bold text-xl">←</a>
    <div>
        <h2 class="text-2xl font-extrabold text-green-800">📋 Historial de Órdenes</h2>
        <p class="text-gray-500 text-sm"><?= htmlspecialchars($proveedor['nombre']) ?></p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-green-700 text-white">
            <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Fecha</th>
                <th class="px-4 py-3 text-left">Creada por</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3 text-center">Estado</th>
                <th class="px-4 py-3 text-center">Ver</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (empty($ordenes)): ?>
                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No hay órdenes registradas.</td></tr>
            <?php else: ?>
                <?php foreach ($ordenes as $o):
                    $color = match($o['estado']) {
                        'pendiente' => 'bg-yellow-100 text-yellow-700',
                        'recibida'  => 'bg-emerald-100 text-emerald-700',
                        'cancelada' => 'bg-red-100 text-red-700',
                        default     => ''
                    };
                ?>
                <tr class="hover:bg-green-50">
                    <td class="px-4 py-3 text-gray-400 font-mono">#<?= $o['id'] ?></td>
                    <td class="px-4 py-3 text-gray-600 text-xs"><?= date('d/m/Y H:i', strtotime($o['creado_en'])) ?></td>
                    <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($o['usuario_nombre']) ?></td>
                    <td class="px-4 py-3 text-right font-bold text-green-700">Q<?= number_format($o['total'], 2) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="<?= $color ?> text-xs font-bold px-2 py-1 rounded-full">
                            <?= ucfirst($o['estado']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <a href="<?= URL_BASE ?>/?url=proveedores/orden/<?= $o['id'] ?>"
                           class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-1 rounded-lg transition">
                            👁️ Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>