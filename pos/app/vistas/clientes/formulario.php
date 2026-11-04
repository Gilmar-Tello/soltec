<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=clientes/index" class="text-green-700 font-bold text-xl">←</a>
        <h2 class="text-2xl font-extrabold text-green-800">
            <?= $cliente ? '✏️ Editar Cliente' : '➕ Nuevo Cliente' ?>
        </h2>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow p-6">
        <form method="POST"
              action="<?= URL_BASE ?>/?url=<?= $cliente ? 'clientes/actualizar' : 'clientes/guardar' ?>">
            <?php if ($cliente): ?>
                <input type="hidden" name="id" value="<?= $cliente['id'] ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-700 text-gray-600 mb-1">Nombre completo <span class="text-red-500">*</span></label>
                    <input type="text" name="nombre" required
                           value="<?= htmlspecialchars($cliente['nombre'] ?? '') ?>"
                           placeholder="Nombre del cliente"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Teléfono</label>
                    <input type="text" name="telefono"
                           value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>"
                           placeholder="55551234"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">NIT</label>
                    <input type="text" name="nit"
                           value="<?= htmlspecialchars($cliente['nit'] ?? 'CF') ?>"
                           placeholder="CF o número de NIT"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-700 text-gray-600 mb-1">Dirección</label>
                    <input type="text" name="direccion"
                           value="<?= htmlspecialchars($cliente['direccion'] ?? '') ?>"
                           placeholder="Zona, municipio, departamento"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-700 text-gray-600 mb-1">Límite de crédito (Q)</label>
                    <input type="number" name="limite_credito" step="0.01" min="0"
                           value="<?= $cliente['limite_credito'] ?? '0.00' ?>"
                           placeholder="0.00 = sin crédito"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                    <p class="text-xs text-gray-400 mt-1">Dejá en 0 si el cliente solo compra al contado.</p>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 rounded-xl transition">
                    💾 <?= $cliente ? 'Actualizar' : 'Guardar Cliente' ?>
                </button>
                <a href="<?= URL_BASE ?>/?url=clientes/index"
                   class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-6 py-3 rounded-xl transition">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>