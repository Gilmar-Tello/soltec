<?php include RAIZ . '/app/vistas/plantillas/header.php';
function num($n){ return rtrim(rtrim(number_format((float)$n,2),'0'),'.'); }
?>

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-900">Cuadre de inventario</h2>
    <p class="text-sm text-slate-500">Ingresá el conteo físico. Si no coincide con el sistema, se calcula el porcentaje de pérdida sobre el valor al costo.</p>
</div>

<form method="POST" action="<?= URL_BASE ?>/?url=productos/guardarcuadre">
    <div class="bg-white rounded-lg border border-slate-200 overflow-x-auto mb-4">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-4 py-3">Producto</th>
                    <th class="text-right px-4 py-3">Costo unit.</th>
                    <th class="text-right px-4 py-3">Stock sistema</th>
                    <th class="text-right px-4 py-3 w-32">Conteo físico</th>
                    <th class="text-right px-4 py-3">Diferencia</th>
                    <th class="text-right px-4 py-3">Valor dif.</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($productos)): ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No hay productos para cuadrar.</td></tr>
                <?php else: foreach ($productos as $p): ?>
                    <tr data-costo="<?= htmlspecialchars($p['precio_compra']) ?>" data-sistema="<?= htmlspecialchars($p['stock']) ?>">
                        <td class="px-4 py-2">
                            <div class="text-slate-800"><?= htmlspecialchars($p['nombre']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($p['unidad'] ?? '') ?></div>
                        </td>
                        <td class="px-4 py-2 text-right text-slate-500">Q<?= number_format($p['precio_compra'], 2) ?></td>
                        <td class="px-4 py-2 text-right text-slate-700 col-sistema"><?= num($p['stock']) ?></td>
                        <td class="px-4 py-2 text-right">
                            <input type="number" step="0.01" min="0" name="fisico[<?= $p['id'] ?>]"
                                   oninput="recalc(this)"
                                   class="w-28 rounded-md border border-slate-300 px-2 py-1 text-sm text-right">
                        </td>
                        <td class="px-4 py-2 text-right col-dif text-slate-400">—</td>
                        <td class="px-4 py-2 text-right col-valdif text-slate-400">—</td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        <div class="lg:col-span-2 bg-white rounded-lg border border-slate-200 p-5">
            <label class="block text-xs text-slate-500 mb-1">Notas (opcional)</label>
            <textarea name="notas" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Observaciones del conteo…"></textarea>
            <label class="flex items-center gap-2 text-sm text-slate-700 mt-3">
                <input type="checkbox" name="ajustar" value="1" checked class="rounded border-slate-300">
                Ajustar el stock del sistema al conteo físico (registra merma/ajuste)
            </label>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 p-5">
            <div class="text-xs uppercase tracking-wide text-slate-500">Pérdida estimada (costo)</div>
            <div class="text-3xl font-bold text-red-600 mt-1" id="prev-pct">0.00%</div>
            <div class="text-sm text-slate-500 mt-1">Valor faltante: <span id="prev-falt" class="font-medium">Q0.00</span></div>
            <button type="submit" class="mt-4 w-full rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold py-2.5">Registrar cuadre</button>
        </div>
    </div>
</form>

<?php if (!empty($historial)): ?>
<div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 text-sm font-semibold text-slate-900">Cuadres recientes</div>
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
            <tr>
                <th class="text-left px-4 py-3">Fecha</th>
                <th class="text-left px-4 py-3">Usuario</th>
                <th class="text-right px-4 py-3">Valor esperado</th>
                <th class="text-right px-4 py-3">Valor físico</th>
                <th class="text-right px-4 py-3">Pérdida</th>
                <th class="text-right px-4 py-3">%</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($historial as $h): ?>
            <tr>
                <td class="px-4 py-3 text-slate-500"><?= date('d/m/Y H:i', strtotime($h['creado_en'])) ?></td>
                <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($h['usuario_nombre'] ?? '') ?></td>
                <td class="px-4 py-3 text-right text-slate-600">Q<?= number_format($h['valor_esperado'], 2) ?></td>
                <td class="px-4 py-3 text-right text-slate-600">Q<?= number_format($h['valor_fisico'], 2) ?></td>
                <td class="px-4 py-3 text-right text-red-600">Q<?= number_format($h['valor_perdida'], 2) ?></td>
                <td class="px-4 py-3 text-right font-semibold <?= (float)$h['porcentaje_perdida']>0?'text-red-600':'text-emerald-600' ?>"><?= number_format($h['porcentaje_perdida'], 2) ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
function fmt(n){ return 'Q' + Number(n).toFixed(2); }
function recalc(input) {
    const tr = input.closest('tr');
    const costo = parseFloat(tr.dataset.costo || '0');
    const sistema = parseFloat(tr.dataset.sistema || '0');
    if (input.value === '') {
        tr.querySelector('.col-dif').textContent = '—';
        tr.querySelector('.col-valdif').textContent = '—';
    } else {
        const fisico = parseFloat(input.value || '0');
        const dif = fisico - sistema;
        const valDif = dif * costo;
        const cd = tr.querySelector('.col-dif');
        const cv = tr.querySelector('.col-valdif');
        cd.textContent = (dif > 0 ? '+' : '') + dif.toFixed(2);
        cd.className = 'px-4 py-2 text-right col-dif ' + (dif < 0 ? 'text-red-600' : (dif > 0 ? 'text-emerald-600' : 'text-slate-400'));
        cv.textContent = fmt(valDif);
        cv.className = 'px-4 py-2 text-right col-valdif ' + (valDif < 0 ? 'text-red-600' : (valDif > 0 ? 'text-emerald-600' : 'text-slate-400'));
    }
    resumen();
}
function resumen() {
    let esperado = 0, faltante = 0;
    document.querySelectorAll('tbody tr[data-costo]').forEach(tr => {
        const inp = tr.querySelector('input[type=number]');
        if (!inp || inp.value === '') return;
        const costo = parseFloat(tr.dataset.costo || '0');
        const sistema = parseFloat(tr.dataset.sistema || '0');
        const fisico = parseFloat(inp.value || '0');
        esperado += sistema * costo;
        const dif = fisico - sistema;
        if (dif < 0) faltante += Math.abs(dif * costo);
    });
    const pct = esperado > 0 ? (faltante / esperado * 100) : 0;
    document.getElementById('prev-pct').textContent = pct.toFixed(2) + '%';
    document.getElementById('prev-falt').textContent = fmt(faltante);
}
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
