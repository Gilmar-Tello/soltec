<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="mb-4">
    <a href="<?= URL_BASE ?>/?url=egresos/index" class="text-sm text-slate-500 hover:underline">← Volver a egresos</a>
    <h2 class="text-lg font-semibold text-slate-900 mt-1">Nuevo egreso</h2>
</div>

<form method="POST" action="<?= URL_BASE ?>/?url=egresos/guardar" id="form-egreso" class="bg-white rounded-lg border border-slate-200 p-5">

    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end mb-3">
        <div class="sm:col-span-5">
            <label class="block text-xs text-slate-500 mb-1">Descripción</label>
            <input type="text" id="lin-desc" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Ej. Compra de mercadería">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Cantidad</label>
            <input type="number" step="0.01" min="0" id="lin-cant" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-3">
            <label class="block text-xs text-slate-500 mb-1">Precio unitario</label>
            <input type="number" step="0.01" min="0" id="lin-precio" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <button type="button" onclick="agregarLinea()" class="w-full rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium py-2">Agregar</button>
        </div>
    </div>

    <table class="w-full text-sm mt-2">
        <thead class="text-slate-500 text-xs uppercase tracking-wide border-b border-slate-200">
            <tr>
                <th class="text-left py-2">Descripción</th>
                <th class="text-right py-2">Cantidad</th>
                <th class="text-right py-2">Precio unit.</th>
                <th class="text-right py-2">Total</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tbody-lineas" class="divide-y divide-slate-100">
            <tr id="fila-vacia"><td colspan="5" class="py-6 text-center text-slate-400">Aún no hay líneas agregadas.</td></tr>
        </tbody>
        <tfoot>
            <tr class="border-t border-slate-200">
                <td colspan="3" class="py-3 text-right font-medium text-slate-600">Total</td>
                <td class="py-3 text-right font-bold text-slate-900" id="td-total">Q0.00</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="mt-3">
        <label class="block text-xs text-slate-500 mb-1">Concepto / nota general (opcional)</label>
        <input type="text" name="concepto" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Ej. Gastos del día">
    </div>

    <div class="mt-4">
        <button type="submit" class="rounded-md bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-5 py-2.5">Registrar egreso</button>
    </div>
</form>

<script>
let lineas = [];
function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmt(n){ return 'Q' + Number(n).toFixed(2); }

function agregarLinea() {
    const desc = document.getElementById('lin-desc').value.trim();
    const cant = parseFloat(document.getElementById('lin-cant').value || '0');
    let precio = parseFloat(document.getElementById('lin-precio').value || '0');
    if (desc === '') { alert('Ingresá una descripción.'); return; }
    if (cant <= 0) { alert('Ingresá una cantidad válida.'); return; }
    if (precio < 0) precio = 0;
    lineas.push({ desc: desc, cant: cant, precio: precio });
    document.getElementById('lin-desc').value = '';
    document.getElementById('lin-cant').value = '';
    document.getElementById('lin-precio').value = '';
    document.getElementById('lin-desc').focus();
    render();
}

function quitar(i) { lineas.splice(i,1); render(); }

function render() {
    const tb = document.getElementById('tbody-lineas');
    document.getElementById('fila-vacia')?.remove();
    tb.innerHTML = '';
    let total = 0;
    lineas.forEach((l, i) => {
        const sub = l.cant * l.precio;
        total += sub;
        tb.innerHTML += `
            <tr>
                <td class="py-2 text-slate-700">${esc(l.desc)}
                    <input type="hidden" name="items[${i}][descripcion]" value="${esc(l.desc)}">
                    <input type="hidden" name="items[${i}][cantidad]" value="${l.cant}">
                    <input type="hidden" name="items[${i}][precio]" value="${l.precio}">
                </td>
                <td class="py-2 text-right">${l.cant}</td>
                <td class="py-2 text-right">${fmt(l.precio)}</td>
                <td class="py-2 text-right">${fmt(sub)}</td>
                <td class="py-2 text-right"><button type="button" onclick="quitar(${i})" class="text-red-500 hover:text-red-700 text-xs">Quitar</button></td>
            </tr>`;
    });
    if (lineas.length === 0) {
        tb.innerHTML = '<tr id="fila-vacia"><td colspan="5" class="py-6 text-center text-slate-400">Aún no hay líneas agregadas.</td></tr>';
    }
    document.getElementById('td-total').textContent = fmt(total);
}

document.getElementById('lin-desc').addEventListener('keydown', function(e){
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('lin-cant').focus(); }
});

document.getElementById('form-egreso').addEventListener('submit', function(e) {
    if (lineas.length === 0) { e.preventDefault(); alert('Agregá al menos una línea.'); }
});
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
