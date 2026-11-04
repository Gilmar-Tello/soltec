<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<?php
function q($n) { return 'Q' . number_format((float)$n, 2); }
?>

<!-- Balance del mes -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <div class="text-xs uppercase tracking-wide text-slate-500">Ingresos del mes</div>
        <div class="text-2xl font-bold text-emerald-600 mt-1"><?= q($ingresosMes) ?></div>
        <div class="text-xs text-slate-400 mt-1">Hoy: <?= q($ingresosHoy) ?></div>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <div class="text-xs uppercase tracking-wide text-slate-500">Egresos del mes</div>
        <div class="text-2xl font-bold text-red-600 mt-1"><?= q($egresosMes) ?></div>
        <div class="text-xs text-slate-400 mt-1">Hoy: <?= q($egresosHoy) ?></div>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <div class="text-xs uppercase tracking-wide text-slate-500">Balance del mes</div>
        <div class="text-2xl font-bold mt-1 <?= $balanceMes >= 0 ? 'text-slate-900' : 'text-red-600' ?>"><?= q($balanceMes) ?></div>
        <div class="text-xs text-slate-400 mt-1">Hoy: <?= q($balanceHoy) ?></div>
    </div>
</div>

<!-- Inventario -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <div class="text-xs uppercase tracking-wide text-slate-500">Productos activos</div>
        <div class="text-2xl font-bold text-slate-900 mt-1"><?= (int)$totalProductos ?></div>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <div class="text-xs uppercase tracking-wide text-slate-500">Valor inventario (costo)</div>
        <div class="text-2xl font-bold text-slate-900 mt-1"><?= q($valorInventario) ?></div>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <div class="text-xs uppercase tracking-wide text-slate-500">Stock bajo</div>
        <div class="text-2xl font-bold <?= $stockBajo > 0 ? 'text-amber-600' : 'text-slate-900' ?> mt-1"><?= (int)$stockBajo ?></div>
    </div>
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <div class="text-xs uppercase tracking-wide text-slate-500">Por vencer (30 días)</div>
        <div class="text-2xl font-bold <?= $porVencer > 0 ? 'text-amber-600' : 'text-slate-900' ?> mt-1"><?= (int)$porVencer ?></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- Última pérdida de cuadre -->
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <div class="text-sm font-semibold text-slate-900 mb-2">Último cuadre de inventario</div>
        <?php if ($ultimoCuadre): ?>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold <?= (float)$ultimoCuadre['porcentaje_perdida'] > 0 ? 'text-red-600' : 'text-emerald-600' ?>">
                    <?= number_format((float)$ultimoCuadre['porcentaje_perdida'], 2) ?>%
                </span>
                <span class="text-sm text-slate-500">de pérdida sobre el costo</span>
            </div>
            <div class="text-xs text-slate-400 mt-1">
                Registrado el <?= date('d/m/Y H:i', strtotime($ultimoCuadre['creado_en'])) ?>
            </div>
        <?php else: ?>
            <div class="text-sm text-slate-500">Todavía no hay cuadres registrados.</div>
            <a href="<?= URL_BASE ?>/?url=productos/cuadre" class="text-sm text-indigo-600 hover:underline mt-2 inline-block">Hacer un cuadre →</a>
        <?php endif; ?>
    </div>

    <!-- Próximos a vencer -->
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <div class="text-sm font-semibold text-slate-900 mb-2">Próximos a vencer</div>
        <?php if (empty($proximosVencer)): ?>
            <div class="text-sm text-slate-500">Ningún producto próximo a vencer.</div>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($proximosVencer as $p):
                    $dias = (strtotime($p['fecha_vencimiento']) - strtotime(date('Y-m-d'))) / 86400;
                ?>
                <li class="py-2 flex items-center justify-between text-sm">
                    <span class="text-slate-700"><?= htmlspecialchars($p['nombre']) ?></span>
                    <span class="<?= $dias < 0 ? 'text-red-600' : ($dias <= 7 ? 'text-amber-600' : 'text-slate-500') ?>">
                        <?= date('d/m/Y', strtotime($p['fecha_vencimiento'])) ?>
                        <?= $dias < 0 ? '(vencido)' : '' ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="flex flex-wrap gap-3 mt-6">
    <a href="<?= URL_BASE ?>/?url=ingresos/nuevo" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium px-4 py-2">Registrar ingreso</a>
    <a href="<?= URL_BASE ?>/?url=egresos/nuevo" class="rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2">Registrar egreso</a>
    <a href="<?= URL_BASE ?>/?url=productos/nuevo" class="rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2">Nuevo producto</a>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
