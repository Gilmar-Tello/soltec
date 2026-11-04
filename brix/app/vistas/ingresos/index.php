<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">Ingresos</h2>
        <p class="text-sm text-slate-500">Ventas de producto e ingresos libres</p>
    </div>
    <a href="<?= URL_BASE ?>/?url=ingresos/nuevo" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium px-4 py-2">Nuevo ingreso</a>
</div>

<form method="GET" class="bg-white rounded-lg border border-slate-200 p-4 mb-4 flex flex-wrap items-end gap-3">
    <input type="hidden" name="url" value="ingresos/index">
    <div>
        <label class="block text-xs text-slate-500 mb-1">Desde</label>
        <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
    </div>
    <div>
        <label class="block text-xs text-slate-500 mb-1">Hasta</label>
        <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
    </div>
    <div>
        <label class="block text-xs text-slate-500 mb-1">Tipo</label>
        <select name="tipo" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
            <option value="">Todos</option>
            <option value="venta"  <?= (($_GET['tipo']??'')==='venta')?'selected':'' ?>>Venta</option>
            <option value="libre"  <?= (($_GET['tipo']??'')==='libre')?'selected':'' ?>>Libre</option>
        </select>
    </div>
    <button class="rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-1.5">Filtrar</button>
    <div class="ml-auto text-right">
        <div class="text-xs text-slate-500">Total del periodo</div>
        <div class="text-lg font-bold text-emerald-600">Q<?= number_format($total, 2) ?></div>
    </div>
</form>

<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
            <tr>
                <th class="text-left px-4 py-3">Fecha</th>
                <th class="text-left px-4 py-3">Tipo</th>
                <th class="text-left px-4 py-3">Concepto</th>
                <th class="text-left px-4 py-3">Usuario</th>
                <th class="text-right px-4 py-3">Monto</th>
                <th class="text-right px-4 py-3">Comprobante</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($ingresos)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin ingresos en este periodo.</td></tr>
            <?php else: foreach ($ingresos as $i): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($i['creado_en'])) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-block rounded px-2 py-0.5 text-xs <?= $i['tipo']==='venta' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600' ?>">
                            <?= $i['tipo']==='venta' ? 'Venta' : 'Servicio' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($i['concepto'] ?? '') ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($i['usuario_nombre'] ?? '') ?></td>
                    <td class="px-4 py-3 text-right font-medium text-slate-900">Q<?= number_format($i['monto'], 2) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="<?= URL_BASE ?>/?url=ingresos/comprobante/<?= $i['id'] ?>" class="text-indigo-600 hover:underline text-xs">Ver / Imprimir</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
