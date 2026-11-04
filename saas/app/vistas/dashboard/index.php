<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-teal-800">📊 Dashboard</h2>
        <?php if (esTrabajador() && !empty($_SESSION['sucursal_id'])): ?>
            <p class="text-xs text-slate-400 mt-0.5">
                Mostrando datos de tu sucursal asignada
            </p>
        <?php else: ?>
            <p class="text-xs text-slate-400 mt-0.5">Vista general — todas las sucursales</p>
        <?php endif; ?>
    </div>
    <a href="<?= URL_BASE ?>/?url=reservas/nueva"
       class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 py-2.5 rounded-xl transition text-sm">
        ➕ Nueva reserva
    </a>
</div>

<!-- Tarjetas principales -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-5 text-center border-l-4 border-teal-500">
        <div class="text-2xl font-extrabold text-teal-700"><?= $resumenInventario['total_productos'] ?? 0 ?></div>
        <div class="text-xs text-slate-500 mt-1">Productos</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center border-l-4 border-emerald-400">
        <div class="text-2xl font-extrabold text-emerald-500"><?= $resumenInventario['unidades_disponibles'] ?? 0 ?></div>
        <div class="text-xs text-slate-500 mt-1">Disponibles</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center border-l-4 border-amber-400">
        <div class="text-2xl font-extrabold text-amber-500"><?= $resumenInventario['unidades_en_uso'] ?? 0 ?></div>
        <div class="text-xs text-slate-500 mt-1">En uso</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center border-l-4 border-violet-400">
        <div class="text-2xl font-extrabold text-violet-500"><?= $reservasHoy ?? 0 ?></div>
        <div class="text-xs text-slate-500 mt-1">Reservas hoy</div>
    </div>
</div>

<!-- Sucursales activas — solo admin -->
<?php if (esAdmin() && $totalSucursales !== null): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-2xl font-extrabold text-teal-700"><?= $totalSucursales ?></div>
        <div class="text-xs text-slate-500 mt-1">Sucursales activas</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-2xl font-extrabold text-slate-600"><?= $resumenInventario['unidades_totales'] ?? 0 ?></div>
        <div class="text-xs text-slate-500 mt-1">Unidades totales en inventario</div>
    </div>
</div>
<?php endif; ?>

<!-- Próximas recolecciones -->
<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 font-bold text-slate-700 flex items-center justify-between">
        <span>🚚 Próximas recolecciones</span>
        <a href="<?= URL_BASE ?>/?url=reservas/index&estado=entregado"
           class="text-teal-600 text-sm font-bold hover:underline">Ver todas →</a>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-2 text-left text-slate-500">Cliente</th>
                <?php if (esAdmin()): ?>
                    <th class="px-4 py-2 text-left text-slate-500">Sucursal</th>
                <?php endif; ?>
                <th class="px-4 py-2 text-left text-slate-500">Fecha recolección</th>
                <th class="px-4 py-2 text-left text-slate-500">Teléfono</th>
                <th class="px-4 py-2 text-right text-slate-500">Acción</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($proximasRecolecciones)): ?>
                <tr><td colspan="<?= esAdmin() ? 5 : 4 ?>" class="px-4 py-8 text-center text-slate-400">
                    No hay recolecciones pendientes.
                </td></tr>
            <?php else: ?>
                <?php foreach ($proximasRecolecciones as $r): ?>
                <tr class="hover:bg-teal-50">
                    <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($r['nombre_completo']) ?></td>
                    <?php if (esAdmin()): ?>
                        <td class="px-4 py-3 text-slate-500 text-xs"><?= htmlspecialchars($r['sucursal_nombre']) ?></td>
                    <?php endif; ?>
                    <td class="px-4 py-3 text-slate-500 text-xs">
                        <?php if ($r['fecha_recoleccion_programada']): ?>
                            <?php
                                // Dias contados sobre fechas puras (ver config/fechas.php):
                                // nada de horas ni zonas horarias de por medio.
                                $diff  = diasDesdeHoy($r['fecha_recoleccion_programada']);
                                $color = $diff < 0 ? 'text-red-500' : ($diff === 0 ? 'text-orange-500' : 'text-slate-500');
                                $label = $diff < 0 ? 'Vencida' : ($diff === 0 ? 'Hoy' : 'En ' . $diff . ' día' . ($diff > 1 ? 's' : ''));
                            ?>
                            <span class="<?= $color ?> font-bold">
                                <?= fechaCorta($r['fecha_recoleccion_programada']) ?>
                                <span class="text-[10px] ml-1">(<?= $label ?>)</span>
                            </span>
                        <?php else: ?>
                            <span class="text-slate-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($r['telefono']) ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= URL_BASE ?>/?url=reservas/detalle/<?= $r['id'] ?>"
                           class="text-teal-600 hover:underline text-xs font-bold">Ver →</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
