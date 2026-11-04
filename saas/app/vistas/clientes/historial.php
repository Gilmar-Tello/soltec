<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= URL_BASE ?>/?url=clientes/index" class="text-teal-700 font-bold text-xl">←</a>
    <div>
        <h2 class="text-2xl font-extrabold text-teal-800">📋 <?= htmlspecialchars($cliente['nombre_completo']) ?></h2>
        <p class="text-sm text-slate-500"><?= htmlspecialchars($cliente['telefono']) ?> <?= $cliente['direccion'] ? '· ' . htmlspecialchars($cliente['direccion']) : '' ?></p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 font-bold text-slate-700">
        Historial de reservas (<?= count($reservas) ?>)
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-slate-500">Fecha reserva</th>
                <th class="px-4 py-3 text-left text-slate-500">Fecha uso</th>
                <th class="px-4 py-3 text-left text-slate-500">Sucursal</th>
                <th class="px-4 py-3 text-center text-slate-500">Estado</th>
                <th class="px-4 py-3 text-right text-slate-500">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($reservas)): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Este cliente todavía no tiene reservas.</td></tr>
            <?php else: ?>
                <?php
                $coloresEstado = [
                    'reservado' => 'bg-amber-100 text-amber-700',
                    'entregado' => 'bg-teal-100 text-teal-700',
                    'devuelto'  => 'bg-emerald-100 text-emerald-700',
                    'cancelado' => 'bg-slate-200 text-slate-600',
                ];
                ?>
                <?php foreach ($reservas as $r): ?>
                <tr class="hover:bg-teal-50">
                    <td class="px-4 py-3 text-slate-600"><?= fechaCorta($r['fecha_reservacion']) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= fechaCorta($r['fecha_uso']) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($r['sucursal_nombre']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="<?= $coloresEstado[$r['estado']] ?? 'bg-slate-100 text-slate-600' ?> text-xs font-bold px-2 py-1 rounded-full capitalize">
                            <?= htmlspecialchars($r['estado']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-bold text-slate-700">Q<?= number_format($r['total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
