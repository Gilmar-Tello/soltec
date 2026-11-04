<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>
<?php
$estados = ['' => 'Todas', 'pendiente' => 'Pendiente', 'completada' => 'Completada', 'rechazada' => 'Rechazada'];
$colores = [
    ''           => 'bg-slate-100 text-slate-700',
    'pendiente'  => 'bg-amber-100 text-amber-700',
    'completada' => 'bg-emerald-100 text-emerald-700',
    'rechazada'  => 'bg-red-100 text-red-700',
];
$filtroActual = $_GET['estado'] ?? '';
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h2 class="text-xl sm:text-2xl font-extrabold text-teal-800">🔄 Transferencias</h2>
    <a href="<?= URL_BASE ?>/?url=transferencias/nueva"
       class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 py-2.5 rounded-xl transition text-sm">
        ➕ Nueva Transferencia
    </a>
</div>

<?php if (isset($_SESSION['exito'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl p-4 mb-5">
        ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
    </div>
    <?php unset($_SESSION['exito']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- Filtros: en celular se deslizan de lado -->
<div class="flex gap-2 mb-5 overflow-x-auto pb-1 -mx-1 px-1">
    <?php foreach ($estados as $val => $etiqueta): ?>
        <a href="<?= URL_BASE ?>/?url=transferencias/index&estado=<?= $val ?>"
           class="shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-xs font-bold transition
                  <?= $filtroActual === $val ? $colores[$val] . ' ring-2 ring-offset-1 ring-current' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
            <?= $etiqueta ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($transferencias)): ?>
    <div class="bg-white rounded-2xl shadow px-4 py-10 text-center text-slate-400">No hay transferencias registradas.</div>
<?php else: ?>

<!-- Celular y tablet: tarjetas -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:hidden">
    <?php foreach ($transferencias as $t): ?>
    <div class="bg-white rounded-2xl shadow p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="font-extrabold text-slate-800 truncate"><?= htmlspecialchars($t['producto_nombre']) ?></div>
                <div class="text-sm text-slate-600 mt-0.5">
                    <?= htmlspecialchars($t['sucursal_origen']) ?> <span class="text-teal-600 font-bold">→</span> <?= htmlspecialchars($t['sucursal_destino']) ?>
                </div>
            </div>
            <span class="shrink-0 bg-teal-100 text-teal-700 text-sm font-extrabold px-2.5 py-1 rounded-full"><?= (int) $t['cantidad'] ?> ud.</span>
        </div>
        <div class="flex items-center justify-between gap-2 mt-3 text-xs text-slate-400">
            <span><?= htmlspecialchars($t['usuario_nombre']) ?> · <?= fechaHora($t['fecha']) ?></span>
            <span class="<?= $colores[$t['estado']] ?? '' ?> font-bold px-2 py-1 rounded-full capitalize"><?= htmlspecialchars($t['estado']) ?></span>
        </div>
        <?php if ($t['estado'] === 'pendiente'): ?>
        <div class="grid grid-cols-2 gap-2 mt-3">
            <a href="<?= URL_BASE ?>/?url=transferencias/completar/<?= (int) $t['id'] ?>"
               onclick="return confirm('¿Aprobar esta transferencia? Se moverán las unidades entre sucursales.')"
               class="text-center bg-emerald-50 text-emerald-700 font-bold py-2.5 rounded-xl text-sm">✅ Aprobar</a>
            <a href="<?= URL_BASE ?>/?url=transferencias/rechazar/<?= (int) $t['id'] ?>"
               onclick="return confirm('¿Rechazar esta transferencia?')"
               class="text-center bg-red-50 text-red-600 font-bold py-2.5 rounded-xl text-sm">✕ Rechazar</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- PC: tabla -->
<div class="hidden lg:block bg-white rounded-2xl shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-slate-500">Producto</th>
                <th class="px-4 py-3 text-left text-slate-500">Origen</th>
                <th class="px-4 py-3 text-left text-slate-500">Destino</th>
                <th class="px-4 py-3 text-center text-slate-500">Cantidad</th>
                <th class="px-4 py-3 text-left text-slate-500">Solicitado por</th>
                <th class="px-4 py-3 text-left text-slate-500">Fecha</th>
                <th class="px-4 py-3 text-center text-slate-500">Estado</th>
                <th class="px-4 py-3 text-right text-slate-500">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($transferencias as $t): ?>
            <tr class="hover:bg-teal-50">
                <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($t['producto_nombre']) ?></td>
                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($t['sucursal_origen']) ?></td>
                <td class="px-4 py-3 text-slate-600">→ <?= htmlspecialchars($t['sucursal_destino']) ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="bg-teal-100 text-teal-700 text-xs font-bold px-2 py-1 rounded-full whitespace-nowrap"><?= (int) $t['cantidad'] ?> ud.</span>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs"><?= htmlspecialchars($t['usuario_nombre']) ?></td>
                <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap"><?= fechaHora($t['fecha']) ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="<?= $colores[$t['estado']] ?? '' ?> text-xs font-bold px-2 py-1 rounded-full capitalize"><?= htmlspecialchars($t['estado']) ?></span>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <?php if ($t['estado'] === 'pendiente'): ?>
                        <a href="<?= URL_BASE ?>/?url=transferencias/completar/<?= (int) $t['id'] ?>"
                           onclick="return confirm('¿Aprobar esta transferencia? Se moverán las unidades entre sucursales.')"
                           class="text-emerald-600 hover:underline text-xs font-bold mr-2">✅ Aprobar</a>
                        <a href="<?= URL_BASE ?>/?url=transferencias/rechazar/<?= (int) $t['id'] ?>"
                           onclick="return confirm('¿Rechazar esta transferencia?')"
                           class="text-red-500 hover:underline text-xs font-bold">✕ Rechazar</a>
                    <?php else: ?>
                        <span class="text-slate-300 text-xs">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
