<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<?php
$esEdicion  = isset($sucursal);
$tipoActual = $sucursal['tipo'] ?? 'operativa';
$claseInput = 'w-full px-4 py-2.5 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-base sm:text-sm';
?>

<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-3 mb-5">
        <a href="<?= URL_BASE ?>/?url=sucursales/index" class="text-teal-700 font-bold text-xl p-1">←</a>
        <h2 class="text-xl sm:text-2xl font-extrabold text-teal-800">
            <?= $esEdicion ? '✏️ Editar Sucursal' : '➕ Nueva Sucursal' ?>
        </h2>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST"
          action="<?= URL_BASE ?>/?url=sucursales/<?= $esEdicion ? 'actualizar' : 'guardar' ?>"
          class="bg-white rounded-2xl shadow p-4 sm:p-6 space-y-5">

        <?php if ($esEdicion): ?>
            <input type="hidden" name="id" value="<?= (int) $sucursal['id'] ?>">
        <?php endif; ?>

        <div>
            <label for="nombre" class="block text-sm font-bold text-slate-600 mb-1">Nombre de la sucursal <span class="text-red-500">*</span></label>
            <input type="text" name="nombre" id="nombre" required maxlength="100"
                   value="<?= htmlspecialchars($sucursal['nombre'] ?? '') ?>"
                   placeholder="Ej: Sucursal Centro" class="<?= $claseInput ?>">
        </div>

        <div>
            <label for="direccion" class="block text-sm font-bold text-slate-600 mb-1">Dirección</label>
            <input type="text" name="direccion" id="direccion" maxlength="255"
                   value="<?= htmlspecialchars($sucursal['direccion'] ?? '') ?>"
                   placeholder="Zona, municipio, departamento" class="<?= $claseInput ?>">
        </div>

        <div>
            <label for="telefono" class="block text-sm font-bold text-slate-600 mb-1">Teléfono</label>
            <input type="tel" name="telefono" id="telefono" maxlength="20" inputmode="tel"
                   value="<?= htmlspecialchars($sucursal['telefono'] ?? '') ?>"
                   placeholder="00000000" class="<?= $claseInput ?>">
        </div>

        <!-- Tipo: ¿tiene su propio producto o solo exhibe y reserva? -->
        <fieldset>
            <legend class="block text-sm font-bold text-slate-600 mb-2">Tipo de sucursal</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="border-2 border-slate-200 rounded-xl p-3 cursor-pointer has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50">
                    <input type="radio" name="tipo" value="operativa" class="mr-2"
                           <?= $tipoActual === 'operativa' ? 'checked' : '' ?> onchange="mostrarBodega()">
                    <span class="font-bold text-sm">🏭 Operativa</span>
                    <span class="block text-xs text-slate-500 mt-1 ml-6">Tiene su propio inventario y despacha sus reservas.</span>
                </label>
                <label class="border-2 border-slate-200 rounded-xl p-3 cursor-pointer has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50">
                    <input type="radio" name="tipo" value="vitrina" class="mr-2"
                           <?= $tipoActual === 'vitrina' ? 'checked' : '' ?> onchange="mostrarBodega()">
                    <span class="font-bold text-sm">🪟 Vitrina</span>
                    <span class="block text-xs text-slate-500 mt-1 ml-6">Solo exhibe y reserva. El producto sale de una bodega.</span>
                </label>
            </div>
        </fieldset>

        <div id="campoBodega" class="<?= $tipoActual === 'vitrina' ? '' : 'hidden' ?>">
            <label for="bodega_id" class="block text-sm font-bold text-slate-600 mb-1">Bodega que despacha <span class="text-red-500">*</span></label>
            <?php if (empty($bodegas)): ?>
                <div class="bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-xl p-3">
                    No hay otra sucursal operativa activa para usar como bodega. Creá o activá una primero.
                </div>
            <?php else: ?>
                <select name="bodega_id" id="bodega_id" class="<?= $claseInput ?>">
                    <option value="">Seleccioná la bodega</option>
                    <?php foreach ($bodegas as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= (int) ($sucursal['bodega_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-400 mt-1">Al reservar en esta vitrina se usa el inventario de esa bodega, y sus repartidores ven la reserva.</p>
            <?php endif; ?>
        </div>

        <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
            <a href="<?= URL_BASE ?>/?url=sucursales/index"
               class="text-center bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-6 py-3 rounded-xl transition">
                Cancelar
            </a>
            <button type="submit"
                    class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-6 py-3 rounded-xl transition">
                💾 <?= $esEdicion ? 'Guardar cambios' : 'Crear sucursal' ?>
            </button>
        </div>
    </form>
</div>

<script>
function mostrarBodega() {
    const esVitrina = document.querySelector('input[name="tipo"]:checked')?.value === 'vitrina';
    document.getElementById('campoBodega').classList.toggle('hidden', !esVitrina);
}
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
