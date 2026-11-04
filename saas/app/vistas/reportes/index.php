<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-extrabold text-teal-800">📈 Reportes</h2>
</div>

<!-- Filtros de fecha -->
<form method="GET" action="<?= URL_BASE ?>/" class="bg-white rounded-2xl shadow p-4 mb-6 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="url" value="reportes/index">
    <div>
        <label class="block text-xs font-bold text-slate-500 mb-1">Desde</label>
        <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>"
               class="px-3 py-2 border-2 border-slate-200 rounded-xl text-sm focus:border-teal-500 focus:outline-none">
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-500 mb-1">Hasta</label>
        <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>"
               class="px-3 py-2 border-2 border-slate-200 rounded-xl text-sm focus:border-teal-500 focus:outline-none">
    </div>
    <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 py-2 rounded-xl text-sm transition">
        🔍 Filtrar
    </button>
    <a href="<?= URL_BASE ?>/?url=reportes/index" class="text-slate-500 text-sm hover:underline py-2">Mes actual</a>
</form>

<!-- ── 1. Resumen del período ─────────────────────────────────── -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-4 text-center">
        <div class="text-2xl font-extrabold text-slate-700"><?= $resumenReservas['total'] ?? 0 ?></div>
        <div class="text-xs text-slate-500 mt-1">Total reservas</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center border-l-4 border-amber-400">
        <div class="text-2xl font-extrabold text-amber-500"><?= $resumenReservas['reservadas'] ?? 0 ?></div>
        <div class="text-xs text-slate-500 mt-1">Reservadas</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center border-l-4 border-teal-500">
        <div class="text-2xl font-extrabold text-teal-600"><?= $resumenReservas['entregadas'] ?? 0 ?></div>
        <div class="text-xs text-slate-500 mt-1">Entregadas</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center border-l-4 border-emerald-400">
        <div class="text-2xl font-extrabold text-emerald-500"><?= $resumenReservas['devueltas'] ?? 0 ?></div>
        <div class="text-xs text-slate-500 mt-1">Devueltas</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center border-l-4 border-slate-300">
        <div class="text-2xl font-extrabold text-slate-400"><?= $resumenReservas['canceladas'] ?? 0 ?></div>
        <div class="text-xs text-slate-500 mt-1">Canceladas</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center border-l-4 border-teal-600">
        <div class="text-lg font-extrabold text-teal-700">Q<?= number_format($resumenReservas['ingresos_total'] ?? 0, 2) ?></div>
        <div class="text-xs text-slate-500 mt-1">Ingresos</div>
    </div>
</div>

<!-- ── 2. Productos más alquilados + Clientes frecuentes ─────── -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    <!-- Productos top -->
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 font-bold text-slate-700">🏆 Productos más alquilados</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left text-slate-500">#</th>
                    <th class="px-4 py-2 text-left text-slate-500">Producto</th>
                    <th class="px-4 py-2 text-center text-slate-500">Veces</th>
                    <th class="px-4 py-2 text-right text-slate-500">Ingresos</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($productosTop)): ?>
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Sin datos en este período.</td></tr>
                <?php else: ?>
                    <?php foreach ($productosTop as $i => $p): ?>
                    <tr class="hover:bg-teal-50">
                        <td class="px-4 py-2.5 text-slate-400 text-xs"><?= $i + 1 ?></td>
                        <td class="px-4 py-2.5 font-bold text-slate-800"><?= htmlspecialchars($p['nombre']) ?></td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="bg-teal-100 text-teal-700 text-xs font-bold px-2 py-0.5 rounded-full">
                                <?= $p['veces_alquilado'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right font-bold text-slate-700">Q<?= number_format($p['ingresos_generados'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Clientes frecuentes -->
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 font-bold text-slate-700">👥 Clientes frecuentes</div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left text-slate-500">Cliente</th>
                    <th class="px-4 py-2 text-center text-slate-500">Reservas</th>
                    <th class="px-4 py-2 text-right text-slate-500">Total gastado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($clientesFrecuentes)): ?>
                    <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">Sin datos en este período.</td></tr>
                <?php else: ?>
                    <?php foreach ($clientesFrecuentes as $c): ?>
                    <tr class="hover:bg-teal-50">
                        <td class="px-4 py-2.5">
                            <div class="font-bold text-slate-800"><?= htmlspecialchars($c['nombre_completo']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($c['telefono']) ?></div>
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-0.5 rounded-full">
                                <?= $c['total_reservas'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right font-bold text-slate-700">Q<?= number_format($c['total_gastado'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── 3. Ingresos por sucursal ───────────────────────────────── -->
<div class="bg-white rounded-2xl shadow overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-slate-100 font-bold text-slate-700">🏬 Ingresos por sucursal</div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-2 text-left text-slate-500">Sucursal</th>
                <th class="px-4 py-2 text-center text-slate-500">Reservas</th>
                <th class="px-4 py-2 text-right text-slate-500">Ingresos</th>
                <th class="px-4 py-2 text-right text-slate-500">% del total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($ingresosSucursal)): ?>
                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Sin datos en este período.</td></tr>
            <?php else:
                $totalGeneral = array_sum(array_column($ingresosSucursal, 'ingresos'));
            ?>
                <?php foreach ($ingresosSucursal as $s): ?>
                <tr class="hover:bg-teal-50">
                    <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($s['sucursal']) ?></td>
                    <td class="px-4 py-3 text-center text-slate-600"><?= $s['total_reservas'] ?></td>
                    <td class="px-4 py-3 text-right font-bold text-teal-700">Q<?= number_format($s['ingresos'], 2) ?></td>
                    <td class="px-4 py-3 text-right text-slate-500">
                        <?= $totalGeneral > 0 ? number_format(($s['ingresos'] / $totalGeneral) * 100, 1) : 0 ?>%
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="bg-teal-50 font-extrabold">
                    <td class="px-4 py-3 text-teal-800">TOTAL</td>
                    <td class="px-4 py-3 text-center text-teal-800"><?= array_sum(array_column($ingresosSucursal, 'total_reservas')) ?></td>
                    <td class="px-4 py-3 text-right text-teal-800">Q<?= number_format($totalGeneral, 2) ?></td>
                    <td class="px-4 py-3 text-right text-teal-800">100%</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ── 4. Inventario por sucursal ─────────────────────────────── -->
<div class="bg-white rounded-2xl shadow overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-slate-100 font-bold text-slate-700">📦 Inventario por sucursal</div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-2 text-left text-slate-500">Sucursal</th>
                <th class="px-4 py-2 text-center text-slate-500">Productos</th>
                <th class="px-4 py-2 text-center text-slate-500">Unidades totales</th>
                <th class="px-4 py-2 text-center text-slate-500">Disponibles</th>
                <th class="px-4 py-2 text-center text-slate-500">En uso</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($inventarioSucursal)): ?>
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Sin inventario registrado.</td></tr>
            <?php else: ?>
                <?php foreach ($inventarioSucursal as $s): ?>
                <tr class="hover:bg-teal-50">
                    <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($s['sucursal']) ?></td>
                    <td class="px-4 py-3 text-center text-slate-600"><?= $s['total_productos'] ?></td>
                    <td class="px-4 py-3 text-center text-slate-600"><?= $s['unidades_totales'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-emerald-600 font-bold"><?= $s['unidades_disponibles'] ?></span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-amber-600 font-bold"><?= $s['unidades_en_uso'] ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ── 5. Historial de alquileres ─────────────────────────────── -->
<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 font-bold text-slate-700">
        📋 Historial de alquileres
        <span class="text-xs text-slate-400 font-normal ml-2">(últimos 20 del período)</span>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-2 text-left text-slate-500">#</th>
                <th class="px-4 py-2 text-left text-slate-500">Cliente</th>
                <th class="px-4 py-2 text-left text-slate-500">Sucursal</th>
                <th class="px-4 py-2 text-left text-slate-500">Fecha reserva</th>
                <th class="px-4 py-2 text-left text-slate-500">Fecha uso</th>
                <th class="px-4 py-2 text-center text-slate-500">Estado</th>
                <th class="px-4 py-2 text-right text-slate-500">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($historial)): ?>
                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Sin alquileres en este período.</td></tr>
            <?php else:
                $coloresEstado = [
                    'reservado' => 'bg-amber-100 text-amber-700',
                    'entregado' => 'bg-teal-100 text-teal-700',
                    'devuelto'  => 'bg-emerald-100 text-emerald-700',
                    'cancelado' => 'bg-slate-200 text-slate-600',
                ];
            ?>
                <?php foreach ($historial as $r): ?>
                <tr class="hover:bg-teal-50">
                    <td class="px-4 py-2.5 text-slate-400 text-xs">
                        <a href="<?= URL_BASE ?>/?url=reservas/detalle/<?= $r['id'] ?>" class="text-teal-600 hover:underline">#<?= $r['id'] ?></a>
                    </td>
                    <td class="px-4 py-2.5 font-bold text-slate-800"><?= htmlspecialchars($r['nombre_completo']) ?></td>
                    <td class="px-4 py-2.5 text-slate-600"><?= htmlspecialchars($r['sucursal_nombre']) ?></td>
                    <td class="px-4 py-2.5 text-slate-500"><?= fechaCorta($r['fecha_reservacion']) ?></td>
                    <td class="px-4 py-2.5 text-slate-500"><?= fechaCorta($r['fecha_uso']) ?></td>
                    <td class="px-4 py-2.5 text-center">
                        <span class="<?= $coloresEstado[$r['estado']] ?? '' ?> text-xs font-bold px-2 py-0.5 rounded-full capitalize">
                            <?= $r['estado'] ?>
                        </span>
                    </td>
                    <td class="px-4 py-2.5 text-right font-bold text-slate-700">Q<?= number_format($r['total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
