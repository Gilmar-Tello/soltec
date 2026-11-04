<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>
<?php $claseInput = 'w-full px-4 py-2.5 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-base sm:text-sm disabled:bg-slate-50 disabled:text-slate-400'; ?>

<div class="max-w-xl mx-auto">
<div class="flex items-center gap-3 mb-5">
    <a href="<?= URL_BASE ?>/?url=transferencias/index" class="text-teal-700 font-bold text-xl p-1">←</a>
    <h2 class="text-xl sm:text-2xl font-extrabold text-teal-800">🔄 Nueva Transferencia</h2>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="bg-teal-50 border border-teal-200 rounded-xl p-4 mb-5 text-sm text-teal-800">
    💡 La transferencia queda <strong>pendiente</strong> hasta que un administrador la apruebe.
    Solo se pueden mover unidades que estén en bodega y que <strong>no estén comprometidas</strong> para reservas futuras.
</div>

<form method="POST" action="<?= URL_BASE ?>/?url=transferencias/guardar"
      class="bg-white rounded-2xl shadow p-4 sm:p-6 space-y-5">

    <!-- Sucursal origen -->
    <div>
        <label for="sucursalOrigen" class="block text-sm font-bold text-slate-600 mb-1">Sucursal origen <span class="text-red-500">*</span></label>
        <select id="sucursalOrigen" required class="<?= $claseInput ?>">
            <option value="">Seleccioná la sucursal origen</option>
            <?php foreach ($sucursales as $s): ?>
                <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="sucursal_origen_id" id="sucursalOrigenHidden">
    </div>

    <!-- Producto -->
    <div>
        <label for="buscarProducto" class="block text-sm font-bold text-slate-600 mb-1">Producto <span class="text-red-500">*</span></label>
        <input type="hidden" name="inventario_id" id="inventarioId">

        <div id="busquedaProducto" class="relative">
            <input type="search" id="buscarProducto" placeholder="Primero seleccioná la sucursal origen..."
                   autocomplete="off" enterkeyhint="search" disabled class="<?= $claseInput ?>">
            <div id="resultadosProducto"
                 class="hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-30 max-h-64 overflow-y-auto"></div>
        </div>

        <div id="productoSeleccionado" class="hidden bg-teal-50 border border-teal-200 rounded-xl p-3">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-bold text-teal-800 text-sm truncate" id="productoNombre"></div>
                    <div class="text-emerald-700 text-xs font-bold">Se pueden mover: <span id="productoMovible"></span></div>
                    <div id="productoLimita" class="hidden text-[11px] text-amber-600"></div>
                </div>
                <button type="button" onclick="limpiarProducto()"
                        class="shrink-0 text-red-500 hover:text-red-700 text-xs font-bold px-2 py-2">✕ cambiar</button>
            </div>
        </div>
    </div>

    <!-- Sucursal destino -->
    <div>
        <label for="sucursalDestino" class="block text-sm font-bold text-slate-600 mb-1">Sucursal destino <span class="text-red-500">*</span></label>
        <select name="sucursal_destino_id" id="sucursalDestino" required class="<?= $claseInput ?>">
            <option value="">Seleccioná la sucursal destino</option>
            <?php foreach ($sucursales as $s): ?>
                <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Cantidad -->
    <div>
        <label for="cantidadInput" class="block text-sm font-bold text-slate-600 mb-1">Cantidad a transferir <span class="text-red-500">*</span></label>
        <input type="number" name="cantidad" id="cantidadInput" min="1" value="1" required inputmode="numeric"
               class="<?= $claseInput ?>">
    </div>

    <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
        <a href="<?= URL_BASE ?>/?url=transferencias/index"
           class="text-center bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-6 py-3 rounded-xl transition">
            Cancelar
        </a>
        <button type="submit"
                class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-6 py-3 rounded-xl transition">
            🔄 Solicitar transferencia
        </button>
    </div>
</form>
</div>

<script>
const URL_BASE = '<?= URL_BASE ?>';
let timeoutProducto;
let encontrados = [];

function esc(t) {
    return String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

document.getElementById('sucursalOrigen').addEventListener('change', function () {
    const sucursalId = this.value;
    document.getElementById('sucursalOrigenHidden').value = sucursalId;
    limpiarProducto();

    // La misma sucursal no puede ser destino
    const dest = document.getElementById('sucursalDestino');
    Array.from(dest.options).forEach(opt => {
        opt.disabled = opt.value !== '' && opt.value === sucursalId;
        if (opt.disabled && dest.value === sucursalId) dest.value = '';
    });

    const input = document.getElementById('buscarProducto');
    input.disabled = !sucursalId;
    input.placeholder = sucursalId ? 'Escribí el nombre del producto...' : 'Primero seleccioná la sucursal origen...';
});

// ── Buscador ───────────────────────────────────────────────────
const inputBuscar = document.getElementById('buscarProducto');
const res = document.getElementById('resultadosProducto');

inputBuscar.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
inputBuscar.addEventListener('input', function () {
    clearTimeout(timeoutProducto);
    const q = this.value.trim();
    const sid = document.getElementById('sucursalOrigenHidden').value;
    if (!sid || q.length < 1) { res.classList.add('hidden'); return; }

    timeoutProducto = setTimeout(() => {
        fetch(`${URL_BASE}/?url=transferencias/inventarioajax&sucursal_id=${sid}&q=${encodeURIComponent(q)}`,
              { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (inputBuscar.value.trim() !== q) return; // respuesta vieja
                encontrados = data;
                res.innerHTML = data.length
                    ? data.map((p, i) => {
                        const movible = parseInt(p.retirables) || 0;
                        return `<button type="button" data-i="${i}" ${movible ? '' : 'disabled'}
                                    class="w-full text-left px-4 py-3 text-sm border-b border-slate-100 last:border-0 hover:bg-teal-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <div class="font-bold text-slate-800">${esc(p.nombre)}</div>
                                    <div class="text-xs">
                                        <span class="text-emerald-700 font-bold">se pueden mover: ${movible}</span>
                                        <span class="text-slate-400 ml-1">· en bodega: ${parseInt(p.cantidad_disponible)}</span>
                                    </div>
                                    ${p.limita_fecha ? `<div class="text-[11px] text-amber-600">el resto está reservado para el ${esc(p.limita_fecha)}</div>` : ''}
                                </button>`;
                    }).join('')
                    : '<div class="px-4 py-3 text-sm text-slate-400">No se encontraron productos en bodega.</div>';
                res.classList.remove('hidden');
            }).catch(() => {});
    }, 300);
});

res.addEventListener('click', e => {
    const b = e.target.closest('[data-i]');
    if (b && !b.disabled) seleccionarProducto(encontrados[b.dataset.i]);
});

function seleccionarProducto(p) {
    const movible = parseInt(p.retirables) || 0;
    document.getElementById('inventarioId').value = p.id;
    document.getElementById('productoNombre').textContent = p.nombre;
    document.getElementById('productoMovible').textContent = movible;
    const limita = document.getElementById('productoLimita');
    limita.textContent = p.limita_fecha ? `El resto está reservado para el ${p.limita_fecha}.` : '';
    limita.classList.toggle('hidden', !p.limita_fecha);
    const cant = document.getElementById('cantidadInput');
    cant.max = movible;
    if ((parseInt(cant.value) || 0) > movible) cant.value = movible;
    document.getElementById('productoSeleccionado').classList.remove('hidden');
    document.getElementById('busquedaProducto').classList.add('hidden');
    res.classList.add('hidden');
}

function limpiarProducto() {
    document.getElementById('inventarioId').value = '';
    inputBuscar.value = '';
    document.getElementById('productoSeleccionado').classList.add('hidden');
    document.getElementById('busquedaProducto').classList.remove('hidden');
    res.classList.add('hidden');
    document.getElementById('cantidadInput').removeAttribute('max');
}

document.addEventListener('click', e => {
    if (!res.contains(e.target) && e.target !== inputBuscar) res.classList.add('hidden');
});
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
