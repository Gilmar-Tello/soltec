<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>
<?php
$etiquetasEstado = [
    'pendiente'  => ['Pendiente',  'bg-orange-100 text-orange-700'],
    'en_gestion' => ['En gestión', 'bg-sky-100 text-sky-700'],
    'cobrado'    => ['Cobrado',    'bg-emerald-100 text-emerald-700'],
    'perdonado'  => ['Perdonado',  'bg-slate-100 text-slate-600'],
    'repuesto'   => ['Repuesto',   'bg-teal-100 text-teal-700'],
];
$filtros = ['abiertas' => 'Abiertas', 'resueltas' => 'Resueltas', 'todas' => 'Todas'];
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h2 class="text-2xl font-extrabold text-teal-800">⚠️ Incidencias</h2>
    <div class="flex gap-2">
        <?php foreach ($filtros as $clave => $texto): ?>
            <a href="<?= URL_BASE ?>/?url=incidencias/index&estado=<?= $clave ?>"
               class="px-4 py-2 rounded-xl text-sm font-bold transition
                      <?= $filtro === $clave ? 'bg-teal-700 text-white' : 'bg-white text-teal-700 hover:bg-teal-50 shadow' ?>">
                <?= $texto ?>
            </a>
        <?php endforeach; ?>
    </div>
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

<div class="bg-white rounded-2xl shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-slate-500">Reportada</th>
                <th class="px-4 py-3 text-left text-slate-500">Reserva</th>
                <th class="px-4 py-3 text-left text-slate-500">Cliente</th>
                <th class="px-4 py-3 text-left text-slate-500">Producto</th>
                <th class="px-4 py-3 text-center text-slate-500">Cantidad</th>
                <th class="px-4 py-3 text-left text-slate-500">Estado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($incidencias)): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No hay incidencias en esta vista.</td></tr>
            <?php else: ?>
                <?php foreach ($incidencias as $inc):
                    [$textoEstado, $claseEstado] = $etiquetasEstado[$inc['estado']] ?? [$inc['estado'], 'bg-slate-100 text-slate-600'];
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-slate-500">
                        <?= fechaHora($inc['fecha_registro']) ?>
                        <div class="text-xs text-slate-400">por <?= htmlspecialchars($inc['reporta_nombre']) ?></div>
                    </td>
                    <td class="px-4 py-3">
                        <a href="<?= URL_BASE ?>/?url=reservas/detalle/<?= (int) $inc['reserva_id'] ?>"
                           class="text-teal-700 font-bold hover:underline">#<?= (int) $inc['reserva_id'] ?></a>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($inc['sucursal_nombre']) ?></div>
                    </td>
                    <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($inc['nombre_completo']) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($inc['producto_nombre']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-xs font-bold px-2 py-1 rounded-full
                                     <?= $inc['tipo'] === 'perdido' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' ?>">
                            <?= (int) $inc['cantidad'] ?> <?= $inc['tipo'] === 'perdido' ? 'faltante(s)' : 'con daño' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-bold px-2 py-1 rounded-full <?= $claseEstado ?>"><?= $textoEstado ?></span>
                        <?php if ($inc['estado'] === 'cobrado'): ?>
                            <div class="text-xs text-slate-500 mt-1">Q<?= number_format((float) $inc['monto_cobrado'], 2) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= URL_BASE ?>/?url=incidencias/detalle/<?= (int) $inc['id'] ?>"
                           class="bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold px-3 py-2 rounded-lg transition">
                            Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
