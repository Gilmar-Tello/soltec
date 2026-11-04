<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>
<?php $claseInput = 'w-full px-4 py-2.5 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-base sm:text-sm disabled:bg-slate-50 disabled:text-slate-400'; ?>

<div class="max-w-xl mx-auto">
<div class="flex items-center gap-3 mb-5">
    <a href="<?= URL_BASE ?>/?url=bajas/index" class="text-teal-700 font-bold text-xl p-1">←</a>
    <h2 class="text-xl sm:text-2xl font-extrabold text-teal-800">🗑️ Registrar Baja</h2>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="bg-orange-50 border border-orange-200 rounded-xl p-4 mb-5 text-sm text-orange-800">
    ⚠️ <strong>Atención:</strong> una baja reduce permanentemente las unidades del inventario.
    Solo se pueden dar de baja unidades que estén en bodega y que <strong>no estén comprometidas</strong> para reservas futuras.
</div>

<form method="POST" action="<?= URL_BASE ?>/?url=bajas/guardar"
      class="bg-white rounded-2xl shadow p-4 sm:p-6 space-y-4">

    <div>
        <label for="sucursalSelect" class="block text-sm font-bold text-slate-600 mb-1">Sucursal <span class="text-red-500">*</span></label>
        <select id="sucursalSelect" required class="<?= $claseInput ?>">
            <option value="">Seleccioná una sucursal</option>
            <?php foreach ($sucursales as $s): ?>
                <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="productoSelect" class="block text-sm font-bold text-slate-600 mb-1">Producto <span class="text-red-500">*</span></label>
        <select name="inventario_id" id="productoSelect" required disabled class="<?= $claseInput ?>">
            <option value="">Primero seleccioná una sucursal</option>
        </select>
        <div id="infoDisponible" class="hidden mt-1 text-xs">
            <span class="text-slate-500">Se pueden dar de baja:</span>
            <span id="cantDisponible" class="font-bold text-teal-700"></span>
            <div id="infoLimita" class="hidden text-amber-600"></div>
        </div>
    </div>

    <div>
        <label for="cantidadInput" class="block text-sm font-bold text-slate-600 mb-1">Cantidad a dar de baja <span class="text-red-500">*</span></label>
        <input type="number" name="cantidad" id="cantidadInput" min="1" value="1" required inputmode="numeric"
               class="<?= $claseInput ?>">
    </div>

    <div>
        <label for="motivo" class="block text-sm font-bold text-slate-600 mb-1">Motivo <span class="text-red-500">*</span></label>
        <input type="text" name="motivo" id="motivo" required maxlength="255"
               placeholder="Ej: Deterioro por uso, robo, obsolescencia..." class="<?= $claseInput ?>">
    </div>

    <div>
        <label for="fechaBaja" class="block text-sm font-bold text-slate-600 mb-1">Fecha de baja</label>
        <input type="date" name="fecha_baja" id="fechaBaja" value="<?= hoyGt() ?>" class="<?= $claseInput ?>">
    </div>

    <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
        <a href="<?= URL_BASE ?>/?url=bajas/index"
           class="text-center bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-6 py-3 rounded-xl transition">
            Cancelar
        </a>
        <button type="submit"
                class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-6 py-3 rounded-xl transition">
            🗑️ Registrar baja
        </button>
    </div>
</form>
</div>

<script>
const URL_BASE = '<?= URL_BASE ?>';
let inventarioData = [];
const productoSelect = document.getElementById('productoSelect');

document.getElementById('sucursalSelect').addEventListener('change', function () {
    const sucursalId = this.value;
    document.getElementById('infoDisponible').classList.add('hidden');

    if (!sucursalId) {
        productoSelect.innerHTML = '<option value="">Primero seleccioná una sucursal</option>';
        productoSelect.disabled = true;
        return;
    }

    productoSelect.disabled = true;
    productoSelect.innerHTML = '<option value="">Cargando...</option>';
    fetch(`${URL_BASE}/?url=bajas/inventarioajax&sucursal_id=${sucursalId}`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            inventarioData = data;
            productoSelect.innerHTML = '';
            if (!data.length) {
                productoSelect.appendChild(new Option('Sin productos en esta sucursal', ''));
                return;
            }
            productoSelect.appendChild(new Option('Seleccioná un producto', ''));
            data.forEach((p, i) => {
                const n = parseInt(p.retirables) || 0;
                // new Option escapa el texto solo
                const opt = new Option(`${p.nombre} — se pueden dar de baja: ${n} (total ${p.cantidad_total})`, p.id);
                opt.dataset.i = i;
                opt.disabled = n === 0;
                productoSelect.appendChild(opt);
            });
            productoSelect.disabled = false;
        }).catch(() => {
            productoSelect.innerHTML = '<option value="">No se pudieron cargar los productos</option>';
        });
});

productoSelect.addEventListener('change', function () {
    const p = inventarioData[this.options[this.selectedIndex]?.dataset?.i];
    const info = document.getElementById('infoDisponible');
    const cant = document.getElementById('cantidadInput');
    if (!p) { info.classList.add('hidden'); cant.removeAttribute('max'); return; }

    const n = parseInt(p.retirables) || 0;
    document.getElementById('cantDisponible').textContent = n;
    const limita = document.getElementById('infoLimita');
    limita.textContent = p.limita_fecha ? `El resto está reservado para el ${p.limita_fecha}.` : '';
    limita.classList.toggle('hidden', !p.limita_fecha);
    info.classList.remove('hidden');
    cant.max = n;
    if ((parseInt(cant.value) || 0) > n) cant.value = n;
});
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
