<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<?php
$esEdicion = isset($cliente);
$volverAReserva = isset($volverAReserva) && $volverAReserva;
$urlVolver = $volverAReserva ? URL_BASE . '/?url=reservas/nueva' : URL_BASE . '/?url=clientes/index';
?>

<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= $urlVolver ?>" class="text-teal-700 font-bold text-xl">←</a>
        <h2 class="text-2xl font-extrabold text-teal-800">
            <?= $esEdicion ? '✏️ Editar Cliente' : '➕ Nuevo Cliente' ?>
        </h2>
    </div>

    <?php if ($volverAReserva): ?>
        <div class="bg-teal-50 border border-teal-200 text-teal-700 rounded-xl p-3 mb-5 text-sm">
            💡 Una vez creado el cliente, regresarás al formulario de reserva para buscarlo.
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST"
          action="<?= URL_BASE ?>/?url=clientes/<?= $esEdicion ? 'actualizar' : 'guardar' ?>"
          class="bg-white rounded-2xl shadow p-6 space-y-4">

        <?php if ($esEdicion): ?>
            <input type="hidden" name="id" value="<?= $cliente['id'] ?>">
        <?php endif; ?>

        <?php if ($volverAReserva): ?>
            <input type="hidden" name="volver_a_reserva" value="1">
        <?php endif; ?>

        <div>
            <label class="block text-sm font-bold text-slate-600 mb-1">Nombre completo <span class="text-red-500">*</span></label>
            <input type="text" name="nombre_completo" required
                   value="<?= htmlspecialchars($cliente['nombre_completo'] ?? '') ?>"
                   class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-600 mb-1">Teléfono <span class="text-red-500">*</span></label>
            <input type="text" name="telefono" required
                   value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>"
                   placeholder="00000000"
                   class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-600 mb-1">Dirección</label>
            <input type="text" name="direccion"
                   value="<?= htmlspecialchars($cliente['direccion'] ?? '') ?>"
                   class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-6 py-3 rounded-xl transition">
                💾 <?= $esEdicion ? 'Guardar cambios' : 'Crear cliente' ?>
            </button>
            <a href="<?= $urlVolver ?>"
               class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-6 py-3 rounded-xl transition">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
