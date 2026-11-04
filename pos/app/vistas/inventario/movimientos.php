<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= URL_BASE ?>/?url=inventario/index"
       class="text-green-700 hover:text-green-900 font-bold text-xl">←</a>
    <div>
        <h2 class="text-2xl font-extrabold text-green-800">📋 Historial de Movimientos</h2>
        <p class="text-gray-500 text-sm"><?= htmlspecialchars($producto['nombre']) ?></p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">Fecha</th>
                    <th class="px-4 py-3 text-left">Usuario</th>
                    <th class="px-4 py-3 text-left">Tipo</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3 text-right">Antes</th>
                    <th class="px-4 py-3 text-right">Después</th>
                    <th class="px-4 py-3 text-left">Motivo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($movimientos)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                            No hay movimientos registrados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movimientos as $m): ?>
                        <tr class="hover:bg-green-50 transition">
                            <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                                <?= date('d/m/Y H:i', strtotime($m['creado_en'])) ?>
                            </td>
                            <td class="px-4 py-3 font-600 text-gray-700">
                                <?= htmlspecialchars($m['usuario_nombre']) ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($m['tipo'] === 'entrada'): ?>
                                    <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">
                                        📥 Entrada
                                    </span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full">
                                        📤 Salida
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right font-bold <?= $m['tipo'] === 'entrada' ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $m['tipo'] === 'entrada' ? '+' : '-' ?><?= number_format($m['cantidad'], 2) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-500">
                                <?= number_format($m['stock_anterior'], 2) ?>
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-gray-800">
                                <?= number_format($m['stock_nuevo'], 2) ?>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <?= htmlspecialchars($m['motivo'] ?? '—') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>