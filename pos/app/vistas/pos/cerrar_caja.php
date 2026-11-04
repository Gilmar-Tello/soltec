<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-md mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=pos/index" class="text-green-700 font-bold text-xl">←</a>
        <h2 class="text-2xl font-extrabold text-green-800">🔒 Cerrar Caja</h2>
    </div>

    <?php if (esAdminConSucursales()): ?>
        <div class="bg-green-100 text-green-800 font-bold rounded-xl px-4 py-2 mb-4 text-center">🏬 Caja de: <?= htmlspecialchars(nombreSucursalTrabajo()) ?></div>
    <?php endif; ?>

    <!-- Resumen del turno -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow p-4 text-center">
            <div class="text-3xl font-extrabold text-green-700"><?= $resumen['total_ventas'] ?></div>
            <div class="text-xs text-gray-500 mt-1">Ventas realizadas</div>
        </div>
        <div class="bg-white rounded-2xl shadow p-4 text-center">
            <div class="text-2xl font-extrabold text-green-700">Q<?= number_format($resumen['total_monto'], 2) ?></div>
            <div class="text-xs text-gray-500 mt-1">Total vendido</div>
        </div>
        <div class="bg-white rounded-2xl shadow p-4 text-center">
            <div class="text-lg font-bold text-gray-700">Q<?= number_format($caja['monto_inicial'], 2) ?></div>
            <div class="text-xs text-gray-500 mt-1">Monto inicial</div>
        </div>
        <div class="bg-white rounded-2xl shadow p-4 text-center">
            <div class="text-lg font-bold text-green-700">Q<?= number_format($caja['monto_inicial'] + $resumen['total_monto'], 2) ?></div>
            <div class="text-xs text-gray-500 mt-1">Esperado en caja</div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow p-6">
        <form method="POST" action="<?= URL_BASE ?>/?url=pos/cerrarCaja">
            <div class="mb-5">
                <label class="block text-sm font-700 text-gray-600 mb-2">💵 Monto real contado en caja (Q)</label>
                <input type="number" name="monto_final" step="0.01" min="0" required
                       value="<?= $caja['monto_inicial'] + $resumen['total_monto'] ?>"
                       class="w-full px-4 py-4 text-2xl text-center border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none font-bold">
            </div>
            <button type="submit"
                    onclick="return confirm('¿Cerrar la caja? No podrás realizar más ventas hasta abrir una nueva.')"
                    class="w-full bg-red-500 hover:bg-red-600 text-white font-extrabold py-4 rounded-xl text-lg transition">
                🔒 Confirmar Cierre de Caja
            </button>
        </form>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>