<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<?php $esEdicion = isset($combo); ?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= URL_BASE ?>/?url=combos/index" class="text-teal-700 font-bold text-xl">←</a>
    <h2 class="text-2xl font-extrabold text-teal-800">
        <?= $esEdicion ? '✏️ Editar Combo' : '🎁 Nuevo Combo' ?>
    </h2>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Datos del combo -->
    <div>
        <form method="POST"
              action="<?= URL_BASE ?>/?url=combos/<?= $esEdicion ? 'actualizar' : 'guardar' ?>"
              id="formCombo"
              class="bg-white rounded-2xl shadow p-6 space-y-4">

            <?php if ($esEdicion): ?>
                <input type="hidden" name="id" value="<?= $combo['id'] ?>">
            <?php endif; ?>

            <div>
                <label class="block text-sm font-bold text-slate-600 mb-1">Nombre del combo <span class="text-red-500">*</span></label>
                <input type="text" name="nombre" required
                       value="<?= htmlspecialchars($combo['nombre'] ?? '') ?>"
                       placeholder="Ej: Combo Boda 100 personas"
                       class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-600 mb-1">Descripción</label>
                <textarea name="descripcion" rows="2"
                          placeholder="Descripción del combo para referencia interna"
                          class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm"><?= htmlspecialchars($combo['descripcion'] ?? '') ?></textarea>
            </div>

            <!-- Productos seleccionados (contenedor dinámico) -->
            <div>
                <label class="block text-sm font-bold text-slate-600 mb-2">Productos del combo <span class="text-red-500">*</span></label>
                <div id="lineasCombo" class="space-y-2 mb-3">
                    <?php if ($esEdicion && !empty($productosCombo)): ?>
                        <?php foreach ($productosCombo as $i => $p): ?>
                        <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2" data-index="<?= $i ?>">
                            <input type="hidden" name="productos[<?= $i ?>][inventario_id]" value="<?= $p['inventario_id'] ?>">
                            <span class="flex-1 text-sm font-bold text-slate-700"><?= htmlspecialchars($p['producto_nombre']) ?></span>
                            <span class="text-teal-600 text-xs">Q<?= number_format($p['precio_alquiler'], 2) ?></span>
                            <input type="number" name="productos[<?= $i ?>][cantidad]"
                                   min="1" value="<?= $p['cantidad'] ?>"
                                   class="w-16 px-2 py-1 border border-slate-300 rounded-lg text-sm text-center focus:border-teal-500 focus:outline-none">
                            <button type="button" onclick="quitarLinea(this)"
                                    class="text-red-400 hover:text-red-600 font-bold">✕</button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div id="sinProductos" class="<?= ($esEdicion && !empty($productosCombo)) ? 'hidden' : '' ?> text-xs text-slate-400 text-center py-3">
                    Buscá productos para agregarlos al combo.
                </div>
            </div>

            <button type="submit"
                    class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 rounded-xl transition">
                💾 <?= $esEdicion ? 'Guardar cambios' : 'Crear combo' ?>
            </button>
        </form>
    </div>

    <!-- Buscador de productos para el combo -->
    <div class="bg-white rounded-2xl shadow p-6">
        <h3 class="font-bold text-slate-700 mb-3">🔍 Agregar productos</h3>

        <!-- Selector de sucursal de referencia -->
        <div class="mb-3">
            <label class="block text-xs font-bold text-slate-500 mb-1">Sucursal de referencia</label>
            <select id="sucursalRef"
                    class="w-full px-3 py-2 border-2 border-slate-200 rounded-xl text-sm focus:border-teal-500 focus:outline-none">
                <option value="">Seleccioná sucursal para buscar productos</option>
                <?php foreach ($sucursales as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="relative mb-3">
            <input type="text" id="buscarProductoCombo"
                   placeholder="Buscar producto por nombre..."
                   disabled autocomplete="off"
                   class="w-full px-4 py-2.5 border-2 border-slate-200 rounded-xl text-sm focus:border-teal-500 focus:outline-none disabled:bg-slate-50 disabled:text-slate-400">
            <div id="resultadosCombo" class="hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden z-20 max-h-60 overflow-y-auto"></div>
        </div>

        <p class="text-xs text-slate-400">Los productos del combo no están atados a una sucursal específica. La sucursal de referencia es solo para buscar. Al usar el combo en una reserva, el sistema verificará disponibilidad en la sucursal elegida.</p>
    </div>
</div>

<script>
// Todo texto que viene del servidor se escapa antes de meterlo al HTML
function esc(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
const URL_BASE = '<?= URL_BASE ?>';
let lineaIndex = <?= ($esEdicion && !empty($productosCombo)) ? count($productosCombo) : 0 ?>;
const productosEnCombo = new Set(<?= jsonJs(array_column($productosCombo ?? [], 'inventario_id')) ?>);

document.getElementById('sucursalRef').addEventListener('change', function() {
    const input = document.getElementById('buscarProductoCombo');
    input.disabled = !this.value;
    input.placeholder = this.value ? 'Buscar producto por nombre...' : 'Seleccioná una sucursal primero';
    if (!this.value) document.getElementById('resultadosCombo').classList.add('hidden');
});

let timeout;
document.getElementById('buscarProductoCombo').addEventListener('input', function() {
    clearTimeout(timeout);
    const q = this.value.trim();
    const sucursal = document.getElementById('sucursalRef').value;
    if (!sucursal || q.length < 1) { document.getElementById('resultadosCombo').classList.add('hidden'); return; }

    timeout = setTimeout(() => {
        fetch(`${URL_BASE}/?url=reservas/inventarioajax&sucursal_id=${sucursal}&q=${encodeURIComponent(q)}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                const div = document.getElementById('resultadosCombo');
                if (!data.length) {
                    div.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400">No se encontraron productos.</div>';
                    div.classList.remove('hidden'); return;
                }
                div.innerHTML = data.map(p =>
                    `<div onclick="agregarAlCombo(${parseInt(p.id)}, ${esc(JSON.stringify(p.nombre))}, ${parseFloat(p.precio_alquiler) || 0})"
                          class="px-4 py-2.5 cursor-pointer hover:bg-teal-50 text-sm border-b border-slate-100 last:border-0 ${productosEnCombo.has(p.id) ? 'opacity-40 pointer-events-none' : ''}">
                        <span class="font-bold">${esc(p.nombre)}</span>
                        <span class="text-teal-600 ml-2 text-xs">Q${parseFloat(p.precio_alquiler).toFixed(2)}</span>
                        ${productosEnCombo.has(p.id) ? '<span class="text-xs text-slate-400 ml-1">(ya en combo)</span>' : ''}
                    </div>`
                ).join('');
                div.classList.remove('hidden');
            });
    }, 300);
});

function agregarAlCombo(id, nombre, precio) {
    if (productosEnCombo.has(id)) return;
    productosEnCombo.add(id);
    document.getElementById('resultadosCombo').classList.add('hidden');
    document.getElementById('buscarProductoCombo').value = '';
    document.getElementById('sinProductos').classList.add('hidden');

    const div = document.createElement('div');
    div.className = 'flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2';
    div.dataset.index = lineaIndex;
    div.innerHTML = `
        <input type="hidden" name="productos[${lineaIndex}][inventario_id]" value="${id}">
        <span class="flex-1 text-sm font-bold text-slate-700">${esc(nombre)}</span>
        <span class="text-teal-600 text-xs">Q${parseFloat(precio).toFixed(2)}</span>
        <input type="number" name="productos[${lineaIndex}][cantidad]"
               min="1" value="1"
               class="w-16 px-2 py-1 border border-slate-300 rounded-lg text-sm text-center focus:border-teal-500 focus:outline-none">
        <button type="button" onclick="quitarLinea(this, ${id})"
                class="text-red-400 hover:text-red-600 font-bold">✕</button>
    `;
    document.getElementById('lineasCombo').appendChild(div);
    lineaIndex++;
}

function quitarLinea(btn, id) {
    const linea = btn.closest('[data-index]');
    if (id) productosEnCombo.delete(id);
    else {
        const hidden = linea.querySelector('input[type=hidden]');
        if (hidden) productosEnCombo.delete(parseInt(hidden.value));
    }
    linea.remove();
    if (document.getElementById('lineasCombo').children.length === 0) {
        document.getElementById('sinProductos').classList.remove('hidden');
    }
}

document.addEventListener('click', e => {
    if (!e.target.closest('#resultadosCombo') && e.target !== document.getElementById('buscarProductoCombo'))
        document.getElementById('resultadosCombo').classList.add('hidden');
});
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
