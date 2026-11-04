<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-lg mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=inventario/index"
           class="text-green-700 hover:text-green-900 font-bold text-xl">←</a>
        <h2 class="text-2xl font-extrabold text-green-800">📥 Ajustar Stock</h2>
    </div>

    <!-- Info del producto -->
    <div class="bg-green-50 border-2 border-green-200 rounded-2xl p-5 mb-6">
        <div class="font-extrabold text-green-800 text-lg"><?= htmlspecialchars($producto['nombre']) ?></div>
        <div class="text-gray-500 text-sm mt-1"><?= htmlspecialchars($producto['descripcion'] ?? '') ?></div>
        <div class="mt-3 text-2xl font-extrabold text-green-700">
            Stock actual: <?= number_format($producto['stock_actual'], 2) ?>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow p-6">
        <form method="POST" action="<?= URL_BASE ?>/?url=inventario/guardarAjuste">
            <input type="hidden" name="producto_id" value="<?= $producto['id'] ?>">

            <!-- Tipo -->
            <div class="mb-5">
                <label class="block text-sm font-700 text-gray-600 mb-2">Tipo de movimiento</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-green-400 transition has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                        <input type="radio" name="tipo" value="entrada" checked class="accent-green-600">
                        <div>
                            <div class="font-bold text-gray-800">📥 Entrada</div>
                            <div class="text-xs text-gray-500">Agregar stock</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-red-400 transition has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                        <input type="radio" name="tipo" value="salida" class="accent-red-600">
                        <div>
                            <div class="font-bold text-gray-800">📤 Salida</div>
                            <div class="text-xs text-gray-500">Quitar stock</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Cantidad -->
            <div class="mb-5">
                <label class="block text-sm font-700 text-gray-600 mb-1">Cantidad <span class="text-red-500">*</span></label>
                <input type="number" name="cantidad" step="0.01" min="0.01" required
                       placeholder="Ej: 12"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-base">
            </div>

            <!-- Motivo -->
            <div class="mb-6">
                <label class="block text-sm font-700 text-gray-600 mb-1">Motivo <span class="text-red-500">*</span></label>
                <textarea name="motivo" rows="3" required
                          placeholder="Ej: Se quitó una caja para vender por unidad, producto dañado, compra recibida..."
                          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm resize-none"></textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 rounded-xl transition">
                    ✅ Guardar Ajuste
                </button>
                <a href="<?= URL_BASE ?>/?url=inventario/index"
                   class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-6 py-3 rounded-xl transition">
                    Cancelar
                </a>
            </div>

        </form>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>