<?php include RAIZ . '/app/vistas/plantillas/header.php';
function num($n){ return rtrim(rtrim(number_format((float)$n,2),'0'),'.'); }
?>

<div class="mb-4">
    <a href="<?= URL_BASE ?>/?url=productos/index" class="text-sm text-slate-500 hover:underline">← Volver a productos</a>
    <h2 class="text-lg font-semibold text-slate-900 mt-1">Receta de "<?= htmlspecialchars($compuesto['nombre']) ?>"</h2>
    <p class="text-sm text-slate-500">Definí de qué productos y en qué cantidad se compone. Al <strong>vender</strong> este producto se descuentan estos componentes del inventario.</p>
</div>

<!-- Estado actual: stock de componentes y cuántos se pueden hacer -->
<?php if (!empty($componentes)): ?>
<div class="bg-white rounded-lg border border-slate-200 p-5 mb-4">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-slate-900">Estado actual</h3>
        <div class="text-sm">
            Podés hacer <span class="text-xl font-bold <?= (int)$disponible > 0 ? 'text-emerald-600' : 'text-amber-600' ?>"><?= (int)$disponible ?></span>
            <span class="text-slate-500">unidad(es) de "<?= htmlspecialchars($compuesto['nombre']) ?>"</span>
        </div>
    </div>
    <table class="w-full text-sm">
        <thead class="text-slate-500 text-xs uppercase tracking-wide border-b border-slate-200">
            <tr>
                <th class="text-left py-2">Componente</th>
                <th class="text-right py-2">Requiere (por unidad)</th>
                <th class="text-right py-2">Stock actual</th>
                <th class="text-right py-2">Alcanza para</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($componentes as $c):
                $alcanza = (float)$c['cantidad'] > 0 ? floor((float)$c['stock'] / (float)$c['cantidad']) : 0;
            ?>
            <tr>
                <td class="py-2 text-slate-700"><?= htmlspecialchars($c['nombre']) ?></td>
                <td class="py-2 text-right"><?= num($c['cantidad']) ?> <?= htmlspecialchars($c['unidad'] ?? '') ?></td>
                <td class="py-2 text-right text-slate-700"><?= num($c['stock']) ?></td>
                <td class="py-2 text-right <?= $alcanza <= (int)$disponible ? 'text-amber-600 font-medium' : 'text-slate-500' ?>"><?= (int)$alcanza ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="text-xs text-slate-400 mt-2">El límite lo marca el componente que "alcanza para" menos unidades.</p>
</div>
<?php endif; ?>

<form method="POST" action="<?= URL_BASE ?>/?url=productos/guardarcomponentes" class="bg-white rounded-lg border border-slate-200 p-5 max-w-2xl">
    <h3 class="text-sm font-semibold text-slate-900 mb-3">Editar receta</h3>
    <input type="hidden" name="compuesto_id" value="<?= $compuesto['id'] ?>">

    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end mb-3">
        <div class="sm:col-span-7 relative">
            <label class="block text-xs text-slate-500 mb-1">Producto componente</label>
            <input type="text" id="buscar-comp" autocomplete="off" placeholder="Escribí para buscar…"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
            <div id="sug-comp" class="hidden absolute z-20 mt-1 w-full max-h-60 overflow-y-auto bg-white border border-slate-200 rounded-md shadow-lg"></div>
        </div>
        <div class="sm:col-span-3">
            <label class="block text-xs text-slate-500 mb-1">Cantidad</label>
            <input type="number" step="0.01" min="0" id="in-cant" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <button type="button" onclick="agregar()" class="w-full rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium py-2">Agregar</button>
        </div>
    </div>

    <table class="w-full text-sm">
        <thead class="text-slate-500 text-xs uppercase tracking-wide border-b border-slate-200">
            <tr><th class="text-left py-2">Componente</th><th class="text-right py-2">Cantidad por unidad</th><th></th></tr>
        </thead>
        <tbody id="tb" class="divide-y divide-slate-100">
            <tr id="vacia"><td colspan="3" class="py-6 text-center text-slate-400">Sin componentes todavía.</td></tr>
        </tbody>
    </table>

    <div class="mt-4">
        <button type="submit" class="rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2.5">Guardar receta</button>
    </div>
    <p class="text-xs text-slate-400 mt-2">Al guardar se reemplaza la receta completa.</p>
</form>

<script>
// Componentes ya en la receta
let comps = <?= jsonJs(array_map(function($c){
    return ['id'=>(string)$c['componente_id'], 'nombre'=>$c['nombre'], 'cant'=>(float)$c['cantidad']];
}, $componentes)) ?>;

// Catálogo de productos simples que pueden ser componentes
const catalogo = <?= jsonJs(array_map(function($d){
    return ['id'=>(string)$d['id'], 'nombre'=>$d['nombre']];
}, $disponibles)) ?>;

let seleccionadoComp = null;
const buscar  = document.getElementById('buscar-comp');
const cajaSug = document.getElementById('sug-comp');

function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function filtrar() {
    seleccionadoComp = null;
    const q = buscar.value.trim().toLowerCase();
    if (q === '') { cajaSug.classList.add('hidden'); cajaSug.innerHTML=''; return; }
    const matches = catalogo.filter(p => p.nombre.toLowerCase().includes(q)).slice(0, 12);
    if (matches.length === 0) {
        cajaSug.innerHTML = '<div class="px-3 py-2 text-sm text-slate-400">Sin coincidencias</div>';
    } else {
        cajaSug.innerHTML = matches.map(p =>
            `<div class="px-3 py-2 text-sm hover:bg-slate-100 cursor-pointer" onclick="elegir('${p.id}')">${esc(p.nombre)}</div>`).join('');
    }
    cajaSug.classList.remove('hidden');
}

buscar.addEventListener('input', filtrar);
buscar.addEventListener('keydown', function(e){
    if (e.key === 'Enter') {
        e.preventDefault();
        const matches = catalogo.filter(p => p.nombre.toLowerCase().includes(buscar.value.trim().toLowerCase()));
        if (matches.length > 0) elegir(matches[0].id);
    }
});

function elegir(id) {
    seleccionadoComp = catalogo.find(p => p.id === id);
    if (!seleccionadoComp) return;
    buscar.value = seleccionadoComp.nombre;
    cajaSug.classList.add('hidden');
    document.getElementById('in-cant').focus();
}

document.addEventListener('click', function(e){
    if (e.target !== buscar && !cajaSug.contains(e.target)) cajaSug.classList.add('hidden');
});

function agregar() {
    if (!seleccionadoComp) { alert('Buscá y seleccioná un producto de la lista.'); return; }
    const cant = parseFloat(document.getElementById('in-cant').value || '0');
    if (cant <= 0) { alert('Ingresá una cantidad válida.'); return; }
    if (comps.some(c => c.id === seleccionadoComp.id)) { alert('Ese componente ya está en la receta.'); return; }
    comps.push({ id: seleccionadoComp.id, nombre: seleccionadoComp.nombre, cant: cant });
    document.getElementById('in-cant').value = '';
    buscar.value = '';
    seleccionadoComp = null;
    buscar.focus();
    render();
}

function quitar(i) { comps.splice(i,1); render(); }

function render() {
    const tb = document.getElementById('tb');
    tb.innerHTML = '';
    if (comps.length === 0) {
        tb.innerHTML = '<tr id="vacia"><td colspan="3" class="py-6 text-center text-slate-400">Sin componentes todavía.</td></tr>';
        return;
    }
    comps.forEach((c, i) => {
        tb.innerHTML += `
            <tr>
                <td class="py-2 text-slate-700">${esc(c.nombre)}
                    <input type="hidden" name="componentes[${i}][componente_id]" value="${c.id}">
                    <input type="hidden" name="componentes[${i}][cantidad]" value="${c.cant}">
                </td>
                <td class="py-2 text-right">${c.cant}</td>
                <td class="py-2 text-right"><button type="button" onclick="quitar(${i})" class="text-red-500 hover:text-red-700 text-xs">Quitar</button></td>
            </tr>`;
    });
}
render();
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
