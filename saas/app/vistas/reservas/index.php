<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>
<?php
// ============================================================
// RESERVAS — listado
// En celular (la mayoría: repartidores) cada reserva es una
// tarjeta que se toca completa para abrirla. En PC, tabla.
// ============================================================
$estados = ['' => 'Todas', 'reservado' => 'Reservado', 'entregado' => 'Entregado', 'devuelto' => 'Devuelto', 'cancelado' => 'Cancelado'];
$colores = [
    ''          => 'bg-slate-100 text-slate-700',
    'reservado' => 'bg-amber-100 text-amber-700',
    'entregado' => 'bg-teal-100 text-teal-700',
    'devuelto'  => 'bg-emerald-100 text-emerald-700',
    'cancelado' => 'bg-slate-200 text-slate-600',
];
$filtroActual = $_GET['estado'] ?? '';

// Lo que el repartidor tiene que hacer con esta reserva, si es urgente
if (!function_exists('avisoReparto')) {
function avisoReparto(array $r): ?array {
    // La hora (si la reserva la tiene) se suma al aviso: el mismo día
    // puede haber entrega, uso y recolección, y lo que importa es cuándo.
    if ($r['estado'] === 'reservado') {
        $fechaEnt = !empty($r['fecha_entrega']) ? $r['fecha_entrega'] : $r['fecha_uso'];
        $dias = diasDesdeHoy($fechaEnt);
        if ($dias === null || $dias > 1) return null;
        $h = !empty($r['hora_entrega']) ? ' · ' . horaCorta($r['hora_entrega']) : '';
        if ($dias < 0)   return ['⚠️ Entrega atrasada',        'bg-red-100 text-red-700'];
        if ($dias === 0) return ['📦 Entregar hoy' . $h,       'bg-orange-100 text-orange-700'];
        return ['📦 Entregar mañana' . $h, 'bg-amber-50 text-amber-700'];
    }
    if ($r['estado'] === 'entregado' && !empty($r['fecha_recoleccion_programada'])) {
        $dias = diasDesdeHoy($r['fecha_recoleccion_programada']);
        if ($dias === null || $dias > 1) return null;
        $h = !empty($r['hora_recoleccion']) ? ' · ' . horaCorta($r['hora_recoleccion']) : '';
        if ($dias < 0)   return ['⚠️ Recolección atrasada',    'bg-red-100 text-red-700'];
        if ($dias === 0) return ['🔄 Recoger hoy' . $h,        'bg-orange-100 text-orange-700'];
        return ['🔄 Recoger mañana' . $h, 'bg-amber-50 text-amber-700'];
    }
    return null;
}
}
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h2 class="text-xl sm:text-2xl font-extrabold text-teal-800">📅 Reservas</h2>
    <a href="<?= URL_BASE ?>/?url=reservas/nueva"
       class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 py-2.5 rounded-xl transition text-sm">
        ➕ Nueva Reserva
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

<!-- Filtros: en celular se deslizan de lado en vez de amontonarse -->
<div class="flex gap-2 mb-5 overflow-x-auto pb-1 -mx-1 px-1">
    <?php foreach ($estados as $val => $etiqueta): ?>
        <a href="<?= URL_BASE ?>/?url=reservas/index&estado=<?= $val ?>"
           class="shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-xs font-bold <?= $filtroActual === $val ? $colores[$val] . ' ring-2 ring-offset-1 ring-current' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
            <?= $etiqueta ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($reservas)): ?>
    <div class="bg-white rounded-2xl shadow px-4 py-10 text-center text-slate-400">No hay reservas registradas.</div>
<?php else: ?>

<!-- ── CELULAR: tarjetas ───────────────────────────────────── -->
<div class="md:hidden space-y-3">
    <?php foreach ($reservas as $r):
        $aviso = avisoReparto($r);
    ?>
    <a href="<?= URL_BASE ?>/?url=reservas/detalle/<?= (int) $r['id'] ?>"
       class="block bg-white rounded-2xl shadow p-4 active:bg-teal-50 transition">
        <div class="flex items-center justify-between gap-2 mb-2">
            <span class="text-xs text-slate-400 font-bold">#<?= (int) $r['id'] ?> · <?= htmlspecialchars($r['sucursal_nombre']) ?></span>
            <span class="<?= $colores[$r['estado']] ?? '' ?> text-xs font-bold px-2 py-1 rounded-full capitalize">
                <?= htmlspecialchars($r['estado']) ?>
            </span>
        </div>

        <div class="font-extrabold text-slate-800 truncate"><?= htmlspecialchars($r['nombre_completo']) ?></div>
        <div class="text-xs text-slate-400"><?= htmlspecialchars($r['telefono']) ?></div>

        <?php if ($aviso): ?>
            <div class="mt-2 inline-block text-xs font-bold px-2 py-1 rounded-lg <?= $aviso[1] ?>"><?= $aviso[0] ?></div>
        <?php endif; ?>

        <div class="flex items-end justify-between gap-3 mt-3 pt-3 border-t border-slate-100">
            <div class="text-xs text-slate-500 space-y-0.5">
                <div>Entrega: <span class="font-bold text-slate-700"><?= fechaCorta(!empty($r['fecha_entrega']) ? $r['fecha_entrega'] : $r['fecha_uso']) ?><?= !empty($r['hora_entrega']) ? ' · ' . horaCorta($r['hora_entrega']) : '' ?></span></div>
                <div>Uso: <span class="font-bold text-slate-700"><?= fechaCorta($r['fecha_uso']) ?><?= !empty($r['hora_uso']) ? ' · ' . horaCorta($r['hora_uso']) : '' ?></span></div>
                <div>Recoger: <span class="font-bold text-slate-700"><?= fechaCorta($r['fecha_recoleccion_programada']) ?><?= !empty($r['hora_recoleccion']) ? ' · ' . horaCorta($r['hora_recoleccion']) : '' ?></span></div>
            </div>
            <div class="text-right">
                <div class="font-extrabold text-slate-800">Q<?= number_format((float) $r['total'], 2) ?></div>
                <div class="text-teal-600 text-xs font-bold">Abrir →</div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── PC / TABLET: tabla ──────────────────────────────────── -->
<div class="hidden md:block bg-white rounded-2xl shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-slate-500">#</th>
                <th class="px-4 py-3 text-left text-slate-500">Cliente</th>
                <th class="px-4 py-3 text-left text-slate-500">Sucursal</th>
                <th class="px-4 py-3 text-left text-slate-500">Entrega</th>
                <th class="px-4 py-3 text-left text-slate-500">Uso</th>
                <th class="px-4 py-3 text-left text-slate-500">Recolección</th>
                <th class="px-4 py-3 text-center text-slate-500">Estado</th>
                <th class="px-4 py-3 text-right text-slate-500">Total</th>
                <th class="px-4 py-3 text-right text-slate-500">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($reservas as $r):
                $aviso = avisoReparto($r);
            ?>
            <tr class="hover:bg-teal-50">
                <td class="px-4 py-3 text-slate-400 text-xs">#<?= (int) $r['id'] ?></td>
                <td class="px-4 py-3 font-bold text-slate-800">
                    <?= htmlspecialchars($r['nombre_completo']) ?>
                    <div class="text-xs text-slate-400 font-normal"><?= htmlspecialchars($r['telefono']) ?></div>
                </td>
                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($r['sucursal_nombre']) ?></td>
                <td class="px-4 py-3 text-slate-600 whitespace-nowrap text-xs">
                    <?= fechaCorta(!empty($r['fecha_entrega']) ? $r['fecha_entrega'] : $r['fecha_uso']) ?>
                    <?php if (!empty($r['hora_entrega'])): ?><div class="text-slate-400"><?= horaCorta($r['hora_entrega']) ?></div><?php endif; ?>
                </td>
                <td class="px-4 py-3 text-slate-600 whitespace-nowrap text-xs">
                    <?= fechaCorta($r['fecha_uso']) ?>
                    <?php if (!empty($r['hora_uso'])): ?><div class="text-slate-400"><?= horaCorta($r['hora_uso']) ?></div><?php endif; ?>
                </td>
                <td class="px-4 py-3 text-slate-500 whitespace-nowrap text-xs">
                    <?= fechaCorta($r['fecha_recoleccion_programada']) ?>
                    <?php if (!empty($r['hora_recoleccion'])): ?><div class="text-slate-400"><?= horaCorta($r['hora_recoleccion']) ?></div><?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="<?= $colores[$r['estado']] ?? '' ?> text-xs font-bold px-2 py-1 rounded-full capitalize">
                        <?= htmlspecialchars($r['estado']) ?>
                    </span>
                    <?php if ($aviso): ?>
                        <div class="mt-1 text-[11px] font-bold whitespace-nowrap <?= $aviso[1] ?> rounded px-1"><?= $aviso[0] ?></div>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-right font-bold text-slate-700 whitespace-nowrap">Q<?= number_format((float) $r['total'], 2) ?></td>
                <td class="px-4 py-3 text-right">
                    <a href="<?= URL_BASE ?>/?url=reservas/detalle/<?= (int) $r['id'] ?>"
                       class="text-teal-600 hover:underline text-xs font-bold whitespace-nowrap">Ver →</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
