<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="mb-4">
    <a href="<?= URL_BASE ?>/?url=ingresos/index" class="text-sm text-slate-500 hover:underline">← Volver a ingresos</a>
    <h2 class="text-lg font-semibold text-slate-900 mt-1">Nuevo ingreso</h2>
</div>

<!-- Selector de tipo -->
<div class="inline-flex rounded-md border border-slate-300 bg-white p-1 mb-4">
    <button type="button" id="tab-venta" onclick="mostrarTipo('venta')"
            class="px-4 py-1.5 text-sm rounded font-medium bg-slate-900 text-white">Venta de producto</button>
    <button type="button" id="tab-libre" onclick="mostrarTipo('libre')"
            class="px-4 py-1.5 text-sm rounded font-medium text-slate-600">Ingreso libre</button>
</div>

<!-- VENTA -->
<form method="POST" action="<?= URL_BASE ?>/?url=ingresos/guardar" id="form-venta" class="bg-white rounded-lg border border-slate-200 p-5">
    <input type="hidden" name="tipo" value="venta">

    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end mb-3">
        <div class="sm:col-span-6 relative">
            <label class="block text-xs text-slate-500 mb-1">Producto</label>
            <input type="text" id="buscar-producto" autocomplete="off" placeholder="Escribí para buscar…"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
            <div id="sugerencias" class="hidden absolute z-20 mt-1 w-full max-h-60 overflow-y-auto bg-white border border-slate-200 rounded-md shadow-lg"></div>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Cantidad</label>
            <input type="number" step="0.01" min="0" id="in-cantidad" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Precio</label>
            <input type="number" step="0.01" min="0" id="in-precio" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <button type="button" onclick="agregarLinea()" class="w-full rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium py-2">Agregar</button>
        </div>
    </div>

    <table class="w-full text-sm mt-2">
        <thead class="text-slate-500 text-xs uppercase tracking-wide border-b border-slate-200">
            <tr>
                <th class="text-left py-2">Producto</th>
                <th class="text-right py-2">Cantidad</th>
                <th class="text-right py-2">Precio</th>
                <th class="text-right py-2">Subtotal</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tbody-lineas" class="divide-y divide-slate-100">
            <tr id="fila-vacia"><td colspan="5" class="py-6 text-center text-slate-400">Aún no hay productos agregados.</td></tr>
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
        <label class="block text-xs text-slate-500 mb-1">Concepto / nota (opcional)</label>
        <input type="text" name="concepto" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Ej. Venta mostrador">
    </div>

    <div class="mt-4">
        <button type="submit" class="rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2.5">Registrar venta</button>
    </div>
</form>

<!-- LIBRE -->
<form method="POST" action="<?= URL_BASE ?>/?url=ingresos/guardar" id="form-libre" class="bg-white rounded-lg border border-slate-200 p-5 hidden">
    <input type="hidden" name="tipo" value="libre">

    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end mb-3">
        <div class="sm:col-span-5">
            <label class="block text-xs text-slate-500 mb-1">Descripción</label>
            <input type="text" id="lin-desc" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Ej. Cobro de servicio">
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
            <button type="button" onclick="agregarLibre()" class="w-full rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium py-2">Agregar</button>
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
        <tbody id="tbody-libre" class="divide-y divide-slate-100">
            <tr id="fila-vacia-libre"><td colspan="5" class="py-6 text-center text-slate-400">Aún no hay líneas agregadas.</td></tr>
        </tbody>
        <tfoot>
            <tr class="border-t border-slate-200">
                <td colspan="3" class="py-3 text-right font-medium text-slate-600">Total</td>
                <td class="py-3 text-right font-bold text-slate-900" id="td-total-libre">Q0.00</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="mt-3">
        <label class="block text-xs text-slate-500 mb-1">Concepto / nota general (opcional)</label>
        <input type="text" name="concepto" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Ej. Ingresos varios del día">
    </div>

    <div class="mt-4">
        <button type="submit" class="rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2.5">Registrar ingreso</button>
    </div>
</form>

<script>
// Catálogo de productos cargado desde el servidor
const productos = <?= jsonJs(array_map(function($p){
    return ['id'=>(string)$p['id'], 'nombre'=>$p['nombre'], 'precio'=>(float)$p['precio_venta'], 'stock'=>(float)$p['stock']];
}, $productos)) ?>;

let lineas = [];
let seleccionado = null;

const buscar  = document.getElementById('buscar-producto');
const cajaSug = document.getElementById('sugerencias');

function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmt(n){ return 'Q' + Number(n).toFixed(2); }
function numStock(n){ return (Math.round(Number(n)*100)/100).toString(); }

function mostrarTipo(t) {
    document.getElementById('form-venta').classList.toggle('hidden', t !== 'venta');
    document.getElementById('form-libre').classList.toggle('hidden', t !== 'libre');
    document.getElementById('tab-venta').className = 'px-4 py-1.5 text-sm rounded font-medium ' + (t==='venta' ? 'bg-slate-900 text-white' : 'text-slate-600');
    document.getElementById('tab-libre').className = 'px-4 py-1.5 text-sm rounded font-medium ' + (t==='libre' ? 'bg-slate-900 text-white' : 'text-slate-600');
}

// ── Autocompletado del producto ────────────────────────────
function filtrar() {
    seleccionado = null;
    const q = buscar.value.trim().toLowerCase();
    if (q === '') { cajaSug.classList.add('hidden'); cajaSug.innerHTML = ''; return []; }
    const matches = productos.filter(p => p.nombre.toLowerCase().includes(q)).slice(0, 12);
    if (matches.length === 0) {
        cajaSug.innerHTML = '<div class="px-3 py-2 text-sm text-slate-400">Sin coincidencias</div>';
    } else {
        cajaSug.innerHTML = matches.map(p =>
            `<div class="px-3 py-2 text-sm hover:bg-slate-100 cursor-pointer flex justify-between gap-3" onclick="elegir('${p.id}')">
                <span class="text-slate-700">${esc(p.nombre)}</span>
                <span class="text-slate-400 text-xs whitespace-nowrap">stock: ${numStock(p.stock)}</span>
             </div>`).join('');
    }
    cajaSug.classList.remove('hidden');
    return matches;
}

buscar.addEventListener('input', filtrar);
buscar.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const matches = productos.filter(p => p.nombre.toLowerCase().includes(buscar.value.trim().toLowerCase()));
        if (matches.length > 0) elegir(matches[0].id);
    }
});

function elegir(id) {
    seleccionado = productos.find(p => p.id === id);
    if (!seleccionado) return;
    buscar.value = seleccionado.nombre;
    document.getElementById('in-precio').value = seleccionado.precio;
    cajaSug.classList.add('hidden');
    document.getElementById('in-cantidad').focus();
}

// Cerrar sugerencias al hacer clic fuera
document.addEventListener('click', function(e) {
    if (e.target !== buscar && !cajaSug.contains(e.target)) cajaSug.classList.add('hidden');
});

// ── Líneas de la venta ─────────────────────────────────────
function agregarLinea() {
    if (!seleccionado) { alert('Buscá y seleccioná un producto de la lista.'); return; }
    const cant = parseFloat(document.getElementById('in-cantidad').value || '0');
    let precio = parseFloat(document.getElementById('in-precio').value || '0');
    const stock = seleccionado.stock;
    if (cant <= 0) { alert('Ingresá una cantidad válida.'); return; }
    if (precio < 0) precio = 0;

    const yaEnLista = lineas.filter(l => l.id === seleccionado.id).reduce((a,l) => a + l.cant, 0);
    if (cant + yaEnLista > stock) { alert('La cantidad supera el stock disponible ('+numStock(stock)+').'); return; }

    lineas.push({ id: seleccionado.id, nombre: seleccionado.nombre, cant: cant, precio: precio });
    document.getElementById('in-cantidad').value = '';
    document.getElementById('in-precio').value = '';
    buscar.value = '';
    seleccionado = null;
    buscar.focus();
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
                <td class="py-2 text-slate-700">${esc(l.nombre)}
                    <input type="hidden" name="items[${i}][producto_id]" value="${l.id}">
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
        tb.innerHTML = '<tr id="fila-vacia"><td colspan="5" class="py-6 text-center text-slate-400">Aún no hay productos agregados.</td></tr>';
    }
    document.getElementById('td-total').textContent = fmt(total);
}

document.getElementById('form-venta').addEventListener('submit', function(e) {
    if (lineas.length === 0) { e.preventDefault(); alert('Agregá al menos un producto.'); }
});

// ── Ingreso libre por líneas ───────────────────────────────
let lineasLibre = [];

function agregarLibre() {
    const desc = document.getElementById('lin-desc').value.trim();
    const cant = parseFloat(document.getElementById('lin-cant').value || '0');
    let precio = parseFloat(document.getElementById('lin-precio').value || '0');
    if (desc === '') { alert('Ingresá una descripción.'); return; }
    if (cant <= 0) { alert('Ingresá una cantidad válida.'); return; }
    if (precio < 0) precio = 0;
    lineasLibre.push({ desc: desc, cant: cant, precio: precio });
    document.getElementById('lin-desc').value = '';
    document.getElementById('lin-cant').value = '';
    document.getElementById('lin-precio').value = '';
    document.getElementById('lin-desc').focus();
    renderLibre();
}

function quitarLibre(i) { lineasLibre.splice(i,1); renderLibre(); }

function renderLibre() {
    const tb = document.getElementById('tbody-libre');
    document.getElementById('fila-vacia-libre')?.remove();
    tb.innerHTML = '';
    let total = 0;
    lineasLibre.forEach((l, i) => {
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
                <td class="py-2 text-right"><button type="button" onclick="quitarLibre(${i})" class="text-red-500 hover:text-red-700 text-xs">Quitar</button></td>
            </tr>`;
    });
    if (lineasLibre.length === 0) {
        tb.innerHTML = '<tr id="fila-vacia-libre"><td colspan="5" class="py-6 text-center text-slate-400">Aún no hay líneas agregadas.</td></tr>';
    }
    document.getElementById('td-total-libre').textContent = fmt(total);
}

document.getElementById('lin-desc').addEventListener('keydown', function(e){
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('lin-cant').focus(); }
});

document.getElementById('form-libre').addEventListener('submit', function(e) {
    if (lineasLibre.length === 0) { e.preventDefault(); alert('Agregá al menos una línea.'); }
});
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
