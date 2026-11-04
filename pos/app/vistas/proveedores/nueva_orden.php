<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=proveedores/index" class="text-green-700 font-bold text-xl">←</a>
        <div>
            <h2 class="text-2xl font-extrabold text-green-800">🛒 Nueva Orden de Compra</h2>
            <p class="text-gray-500 text-sm"><?= htmlspecialchars($proveedor['nombre']) ?></p>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (empty($productos)): ?>
        <div class="bg-orange-50 border border-orange-300 text-orange-700 rounded-xl p-4 mb-5">
            ⚠️ Este proveedor no tiene productos asignados todavía. Andá a <b>Inventario → Editar producto</b> y seleccioná este proveedor en el campo correspondiente para que aparezca acá.
        </div>
    <?php elseif (empty($productosBajoStock)): ?>
        <div class="bg-blue-50 border border-blue-200 text-blue-700 rounded-xl p-4 mb-5">
            ℹ️ Ningún producto de este proveedor está con stock bajo por ahora. Podés buscar y agregar productos manualmente si igual querés hacer una orden.
        </div>
    <?php else: ?>
        <div class="bg-orange-50 border border-orange-300 text-orange-800 rounded-xl p-4 mb-5">
            📦 Se precargaron <b><?= count($productosBajoStock) ?></b> producto(s) de este proveedor con stock bajo. Solo agregá la cantidad a pedir (y ajustá el precio de compra si cambió).
        </div>
    <?php endif; ?>

    <datalist id="listaProductos">
        <?php foreach ($productos as $prod): ?>
            <option value="<?= htmlspecialchars($prod['nombre']) ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <form method="POST" action="<?= URL_BASE ?>/?url=proveedores/guardarOrden" id="formOrden">
        <input type="hidden" name="proveedor_id" value="<?= $proveedor['id'] ?>">

        <!-- Productos -->
        <div class="bg-white rounded-2xl shadow p-6 mb-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-gray-700">Productos a ordenar</h3>
                <button type="button" onclick="agregarFila()"
                        class="bg-green-100 hover:bg-green-200 text-green-700 font-bold px-4 py-2 rounded-xl text-sm transition">
                    ➕ Agregar producto
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="tablaProductos">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-gray-600">Producto</th>
                            <th class="px-3 py-2 text-left text-gray-600">Stock actual</th>
                            <th class="px-3 py-2 text-left text-gray-600">Cantidad</th>
                            <th class="px-3 py-2 text-left text-gray-600">Precio unitario (Q)</th>
                            <th class="px-3 py-2 text-right text-gray-600">Subtotal</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="filas">
                        <?php if (empty($productosBajoStock)): ?>
                        <tr id="fila-0" class="fila border-b border-gray-100">
                            <td class="px-3 py-2">
                                <input type="text" list="listaProductos" autocomplete="off"
                                       placeholder="Buscar producto de este proveedor..."
                                       oninput="buscarProducto(this, 0)"
                                       class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                                <input type="hidden" name="productos[]" id="producto-id-0" value="">
                            </td>
                            <td class="px-3 py-2 text-gray-500 text-xs" id="stock-0">—</td>
                            <td class="px-3 py-2">
                                <input type="number" name="cantidades[]" min="0" step="0.01" value="0"
                                       oninput="calcularSubtotal(0)"
                                       class="w-24 px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" name="precios[]" min="0" step="0.01" value="0"
                                       oninput="calcularSubtotal(0)"
                                       onchange="this.dataset.tocado = '1'"
                                       id="precio-0"
                                       class="w-28 px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                            </td>
                            <td class="px-3 py-2 text-right font-bold text-gray-700" id="sub-0">Q0.00</td>
                            <td class="px-3 py-2"></td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($productosBajoStock as $i => $p):
                            $cantSugerida = max(1, floatval($p['stock_minimo']) - floatval($p['stock_actual']));
                        ?>
                        <tr id="fila-<?= $i ?>" class="fila border-b border-gray-100 bg-orange-50/40">
                            <td class="px-3 py-2">
                                <input type="text" list="listaProductos" autocomplete="off"
                                       value="<?= htmlspecialchars($p['nombre']) ?>"
                                       oninput="buscarProducto(this, <?= $i ?>)"
                                       class="w-full px-3 py-2 border-2 border-green-300 rounded-xl focus:border-green-500 focus:outline-none text-sm font-bold">
                                <input type="hidden" name="productos[]" id="producto-id-<?= $i ?>" value="<?= $p['id'] ?>">
                            </td>
                            <td class="px-3 py-2 text-red-600 font-bold text-xs" id="stock-<?= $i ?>">
                                <?= number_format($p['stock_actual'], 2) ?> ⚠️
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" name="cantidades[]" min="0" step="0.01" value="<?= $cantSugerida ?>"
                                       oninput="calcularSubtotal(<?= $i ?>)"
                                       class="w-24 px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" name="precios[]" min="0" step="0.01" value="<?= $p['precio_compra'] ?>"
                                       oninput="calcularSubtotal(<?= $i ?>)"
                                       onchange="this.dataset.tocado = '1'"
                                       id="precio-<?= $i ?>"
                                       class="w-28 px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                            </td>
                            <td class="px-3 py-2 text-right font-bold text-gray-700" id="sub-<?= $i ?>">Q0.00</td>
                            <td class="px-3 py-2">
                                <button type="button" onclick="document.getElementById('fila-<?= $i ?>').remove(); actualizarTotal();"
                                        class="text-red-400 hover:text-red-600 font-bold text-lg">✕</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total -->
            <div class="flex justify-end mt-4 pt-4 border-t border-gray-100">
                <div class="text-right">
                    <div class="text-gray-500 text-sm">Total de la orden</div>
                    <div class="text-2xl font-extrabold text-green-700" id="totalOrden">Q0.00</div>
                </div>
            </div>
        </div>

        <!-- Notas -->
        <div class="bg-white rounded-2xl shadow p-6 mb-5">
            <label class="block text-sm font-700 text-gray-600 mb-2">Notas (opcional)</label>
            <textarea name="notas" rows="2" placeholder="Instrucciones especiales, fecha de entrega esperada..."
                      class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm resize-none"></textarea>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 rounded-xl transition">
                💾 Crear Orden de Compra
            </button>
            <a href="<?= URL_BASE ?>/?url=proveedores/index"
               class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-6 py-3 rounded-xl transition">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script>
const productosData = <?= jsonJs(array_map(fn($p) => [
    'id' => $p['id'], 'nombre' => $p['nombre'],
    'stock' => $p['stock_actual'], 'precio' => $p['precio_compra']
], $productos)) ?>;

let filaIndex = <?= max(0, count($productosBajoStock) - 1) ?>;

// Al cargar, calcular subtotales de las filas precargadas con stock bajo
document.addEventListener('DOMContentLoaded', function () {
    <?php foreach ($productosBajoStock as $i => $p): ?>
    calcularSubtotal(<?= $i ?>);
    <?php endforeach; ?>
});

function buscarProducto(input, idx) {
    const nombre = input.value.trim().toLowerCase();
    const match  = productosData.find(p => p.nombre.toLowerCase() === nombre);
    const hiddenId = document.getElementById('producto-id-' + idx);

    if (match) {
        hiddenId.value = match.id;
        document.getElementById('stock-' + idx).textContent = match.stock;
        const precioInput = document.getElementById('precio-' + idx);
        // Solo autocompleta el precio si el usuario todavía no lo tocó a mano
        if (precioInput && !precioInput.dataset.tocado) {
            precioInput.value = match.precio;
        }
        input.classList.remove('border-red-300');
        input.classList.add('border-green-400');
    } else {
        hiddenId.value = '';
        document.getElementById('stock-' + idx).textContent = '—';
        input.classList.remove('border-green-400');
        if (nombre) input.classList.add('border-red-300');
    }
    calcularSubtotal(idx);
}

function calcularSubtotal(idx) {
    const fila = document.getElementById('fila-' + idx);
    const cant  = parseFloat(fila.querySelector('input[name="cantidades[]"]').value) || 0;
    const precio = parseFloat(fila.querySelector('input[name="precios[]"]').value) || 0;
    const sub = cant * precio;
    document.getElementById('sub-' + idx).textContent = 'Q' + sub.toFixed(2);
    actualizarTotal();
}

function actualizarTotal() {
    let total = 0;
    document.querySelectorAll('[id^="sub-"]').forEach(el => {
        total += parseFloat(el.textContent.replace('Q','')) || 0;
    });
    document.getElementById('totalOrden').textContent = 'Q' + total.toFixed(2);
}

function agregarFila() {
    filaIndex++;
    const idx = filaIndex;
    const fila = `
    <tr id="fila-${idx}" class="fila border-b border-gray-100">
        <td class="px-3 py-2">
            <input type="text" list="listaProductos" autocomplete="off"
                   placeholder="Buscar producto de este proveedor..."
                   oninput="buscarProducto(this, ${idx})"
                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
            <input type="hidden" name="productos[]" id="producto-id-${idx}" value="">
        </td>
        <td class="px-3 py-2 text-gray-500 text-xs" id="stock-${idx}">—</td>
        <td class="px-3 py-2">
            <input type="number" name="cantidades[]" min="0" step="0.01" value="0"
                   oninput="calcularSubtotal(${idx})"
                   class="w-24 px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
        </td>
        <td class="px-3 py-2">
            <input type="number" name="precios[]" min="0" step="0.01" value="0"
                   oninput="calcularSubtotal(${idx})" id="precio-${idx}"
                   onchange="this.dataset.tocado = '1'"
                   class="w-28 px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
        </td>
        <td class="px-3 py-2 text-right font-bold text-gray-700" id="sub-${idx}">Q0.00</td>
        <td class="px-3 py-2">
            <button type="button" onclick="document.getElementById('fila-${idx}').remove(); actualizarTotal();"
                    class="text-red-400 hover:text-red-600 font-bold text-lg">✕</button>
        </td>
    </tr>`;
    document.getElementById('filas').insertAdjacentHTML('beforeend', fila);
}
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>