<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<script src="<?= URL_BASE ?>/app/public/vendor/html5-qrcode-2.3.8.min.js"></script>

<div class="max-w-2xl mx-auto">

    <!-- Encabezado -->
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=inventario/index"
           class="text-green-700 hover:text-green-900 font-bold text-xl">← </a>
        <h2 class="text-2xl font-extrabold text-green-800">
            <?= $producto ? '✏️ Editar Producto' : '➕ Nuevo Producto' ?>
        </h2>
    </div>

    <!-- Alertas -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Formulario -->
    <div class="bg-white rounded-2xl shadow p-6">
        <form method="POST" enctype="multipart/form-data"
              action="<?= URL_BASE ?>/?url=<?= $producto ? 'inventario/actualizar' : 'inventario/guardar' ?>">

            <?php if ($producto): ?>
                <input type="hidden" name="id" value="<?= $producto['id'] ?>">
            <?php endif; ?>

            <?php if (sucursalesHabilitadas()): ?>
                <!-- Sucursal a la que pertenece el producto (cada sucursal tiene su propio catalogo). Solo aparece si el negocio tiene sucursales -->
                <div class="mb-5">
                    <label class="block text-sm font-700 text-gray-600 mb-1">🏬 Sucursal</label>
                    <?php if ($producto): ?>
                        <div class="px-4 py-2 bg-gray-50 border-2 border-gray-200 rounded-xl text-sm font-bold text-gray-700">
                            <?= htmlspecialchars(nombresSucursales()[(int) ($producto['sucursal_id'] ?? 0)] ?? NOMBRE_SUCURSAL_PRINCIPAL) ?>
                            <span class="text-xs text-gray-400 font-normal">· un producto no cambia de sucursal</span>
                        </div>
                    <?php elseif (esAdminConSucursales()): ?>
                        <?php $sucSel = sucursalTrabajo(); ?>
                        <select name="sucursal_id"
                                class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm font-bold">
                            <option value="0" <?= $sucSel === 0 ? 'selected' : '' ?>><?= htmlspecialchars(NOMBRE_SUCURSAL_PRINCIPAL) ?></option>
                            <?php foreach (sucursalesDelNegocio() as $idSuc => $nomSuc): ?>
                                <option value="<?= (int) $idSuc ?>" <?= $sucSel === (int) $idSuc ? 'selected' : '' ?>><?= htmlspecialchars($nomSuc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <div class="px-4 py-2 bg-gray-50 border-2 border-gray-200 rounded-xl text-sm font-bold text-gray-700">
                            <?= htmlspecialchars(nombreSucursalTrabajo()) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                <!-- Código -->
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Código de barras (opcional)</label>
                    <div class="flex gap-2">
                        <input type="text" name="codigo" id="campoCodigo"
                               value="<?= htmlspecialchars($producto['codigo'] ?? '') ?>"
                               placeholder="Escribí o escaneá el código"
                               onkeydown="if(event.key==='Enter') event.preventDefault();"
                               class="flex-1 px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                        <button type="button" onclick="abrirCamaraProducto()"
                                class="bg-green-600 hover:bg-green-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition whitespace-nowrap">
                            📷
                        </button>
                    </div>
                </div>

                <!-- Nombre -->
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Nombre <span class="text-red-500">*</span></label>
                    <input type="text" name="nombre" required
                           value="<?= htmlspecialchars($producto['nombre'] ?? '') ?>"
                           placeholder="Nombre del producto"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>

                <!-- Categoría -->
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Categoría <span class="text-red-500">*</span></label>
                    <select name="categoria_id" required
                            class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                        <option value="">Seleccioná una categoría</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                <?= ($producto['categoria_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Unidad de medida -->
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Unidad de medida <span class="text-red-500">*</span></label>
                    <select name="unidad_id" required
                            class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                        <option value="">Seleccioná una unidad</option>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= $u['id'] ?>"
                                <?= ($producto['unidad_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['nombre']) ?> (<?= $u['abreviatura'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Proveedor (opcional) -->
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Proveedor (opcional)</label>
                    <select name="proveedor_id"
                            class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                        <option value="">— Sin proveedor —</option>
                        <?php foreach ($proveedores as $pr): ?>
                            <option value="<?= $pr['id'] ?>"
                                <?= ($producto['proveedor_id'] ?? '') == $pr['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pr['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Precio compra -->
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Precio de compra (Q)</label>
                    <input type="number" id="campoPrecioCompra" name="precio_compra" step="0.01" min="0"
                           value="<?= $producto['precio_compra'] ?? '0.00' ?>"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>

                <!-- Precio mínimo -->
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">
                        Precio mínimo (Q)
                        <span class="text-gray-400 font-400">— calculado con 50% de margen, editable</span>
                    </label>
                    <input type="number" id="campoPrecioMinimo" name="precio_minimo" step="0.01" min="0"
                           value="<?= $producto['precio_minimo'] ?? '0.00' ?>"
                           placeholder="0.00"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>

                <!-- Precio venta real -->
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Precio de venta (Q) <span class="text-red-500">*</span></label>
                    <input type="number" name="precio_venta_real" step="0.01" min="0" required
                           value="<?= $producto['precio_venta_real'] ?? '' ?>"
                           placeholder="0.00"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>

                <!-- Stock mínimo -->
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Stock mínimo (alerta)</label>
                    <input type="number" name="stock_minimo" step="0.01" min="0"
                           value="<?= $producto['stock_minimo'] ?? '0' ?>"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>

                <!-- Fecha de vencimiento -->
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Fecha de vencimiento (opcional)</label>
                    <input type="date" name="fecha_vencimiento"
                           value="<?= $producto['fecha_vencimiento'] ?? '' ?>"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>

                <!-- Stock inicial (solo al crear) -->
                <?php if (!$producto): ?>
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Stock inicial</label>
                    <input type="number" name="stock_inicial" step="0.01" min="0" value="0"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>
                <?php endif; ?>

            </div>

            <!-- Descripción -->
            <div class="mt-5">
                <label class="block text-sm font-700 text-gray-600 mb-1">Descripción</label>
                <textarea name="descripcion" rows="2" placeholder="Descripción breve del producto..."
                          class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm resize-none"
                ><?= htmlspecialchars($producto['descripcion'] ?? '') ?></textarea>
            </div>

            <?php if (funcionActiva('fotos_productos')):
                $fotoActual = urlFotoProducto($producto['foto'] ?? null, tenantActual()); ?>
            <!-- Foto del producto (funcion opcional habilitada por el administrador del sistema) -->
            <div class="mt-5">
                <label class="block text-sm font-700 text-gray-600 mb-1">📷 Foto del producto (opcional)</label>
                <div class="flex items-center gap-4">
                    <div class="w-24 h-24 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                        <img id="fotoPrevia" src="<?= htmlspecialchars($fotoActual ?? '') ?>" alt="" data-zoom="" data-titulo="Foto del producto"
                             class="w-full h-full object-cover <?= $fotoActual ? '' : 'hidden' ?>">
                        <span id="fotoSinImagen" class="text-3xl text-gray-300 <?= $fotoActual ? 'hidden' : '' ?>">🖼️</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <input type="file" name="foto" id="campoFoto" accept="image/*"
                               class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-green-100 file:px-4 file:py-2 file:text-sm file:font-bold file:text-green-700 hover:file:bg-green-200">
                        <p class="text-xs text-gray-400 mt-1">En el celular puedes tomarla en el momento. Se reduce sola; no hace falta preparar la imagen.</p>
                        <?php if ($fotoActual): ?>
                            <label class="inline-flex items-center gap-2 text-sm text-red-600 mt-2 cursor-pointer">
                                <input type="checkbox" name="quitar_foto" value="1"> Quitar la foto actual
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <script>
            (function () {
                var campo = document.getElementById('campoFoto'), previa = document.getElementById('fotoPrevia'), vacio = document.getElementById('fotoSinImagen');
                campo.addEventListener('change', function () {
                    var f = campo.files && campo.files[0];
                    if (!f) return;
                    previa.src = URL.createObjectURL(f);
                    previa.classList.remove('hidden'); vacio.classList.add('hidden');
                });
            })();
            </script>
            <?php endif; ?>

            <!-- Botones -->
            <div class="flex gap-3 mt-6">
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 rounded-xl transition">
                    💾 <?= $producto ? 'Actualizar' : 'Guardar Producto' ?>
                </button>
                <a href="<?= URL_BASE ?>/?url=inventario/index"
                   class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-6 py-3 rounded-xl transition">
                    Cancelar
                </a>
            </div>

        </form>
    </div>
</div>

<!-- Modal cámara para código de barras -->
<div id="modalCamaraProducto" class="fixed inset-0 bg-black bg-opacity-80 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <span class="font-bold text-gray-700">📷 Escanear código de barras</span>
            <button onclick="cerrarCamaraProducto()" class="text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
        </div>
        <div id="lectorCamaraProducto" class="w-full"></div>
        <div class="p-3 text-center text-xs text-gray-400">
            Apuntá la cámara al código de barras del empaque
        </div>
    </div>
</div>

<script>
let html5QrCodeProducto = null;

function abrirCamaraProducto() {
    document.getElementById('modalCamaraProducto').classList.remove('hidden');
    html5QrCodeProducto = new Html5Qrcode("lectorCamaraProducto");
    const config = { fps: 10, qrbox: { width: 250, height: 150 } };

    html5QrCodeProducto.start(
        { facingMode: "environment" },
        config,
        (decodedText) => {
            document.getElementById('campoCodigo').value = decodedText;
            if (navigator.vibrate) navigator.vibrate(100);
            cerrarCamaraProducto();
        },
        (errorMessage) => { /* se ignoran frames sin código */ }
    ).catch(err => {
        alert('No se pudo acceder a la cámara: ' + err);
        cerrarCamaraProducto();
    });
}

function cerrarCamaraProducto() {
    if (html5QrCodeProducto) {
        html5QrCodeProducto.stop().then(() => {
            html5QrCodeProducto.clear();
            html5QrCodeProducto = null;
        }).catch(() => {});
    }
    document.getElementById('modalCamaraProducto').classList.add('hidden');
}

// Detectar Enter del lector USB en el campo de código
document.getElementById('campoCodigo').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        // El lector USB ya escribió el valor en el input, solo confirmamos visualmente
        this.classList.remove('border-gray-200');
        this.classList.add('border-green-500');
        setTimeout(() => {
            this.classList.remove('border-green-500');
            this.classList.add('border-gray-200');
        }, 600);
    }
});

// Precio mínimo = precio de compra + 50% de margen, calculado automáticamente
// pero deja de recalcularse en cuanto el usuario edita el campo a mano
// (y no lo toca al editar un producto que ya tenía un mínimo guardado).
let precioMinimoEditadoManualmente = <?= ($producto && floatval($producto['precio_minimo'] ?? 0) > 0) ? 'true' : 'false' ?>;
const campoPrecioCompra  = document.getElementById('campoPrecioCompra');
const campoPrecioMinimo  = document.getElementById('campoPrecioMinimo');

campoPrecioMinimo.addEventListener('input', function() {
    precioMinimoEditadoManualmente = true;
});

campoPrecioCompra.addEventListener('input', function() {
    if (!precioMinimoEditadoManualmente) {
        const compra = parseFloat(this.value) || 0;
        campoPrecioMinimo.value = (compra * 1.5).toFixed(2);
    }
});
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>