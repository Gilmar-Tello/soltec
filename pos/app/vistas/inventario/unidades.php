<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=inventario/index" class="text-green-700 font-bold text-xl">←</a>
        <h2 class="text-2xl font-extrabold text-green-800">📐 Unidades de Medida</h2>
    </div>

    <?php if (isset($_SESSION['exito'])): ?>
        <div class="bg-green-50 border border-green-300 text-green-800 rounded-xl p-4 mb-5">
            ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
        </div>
        <?php unset($_SESSION['exito']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Formulario nueva unidad -->
    <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <h3 class="font-bold text-gray-700 mb-4">➕ Nueva Unidad de Medida</h3>
        <form method="POST" action="<?= URL_BASE ?>/?url=inventario/unidades" class="flex flex-col gap-3">
            <input type="text" name="nombre" required placeholder="Nombre (ej: Quintal) *"
                   class="px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
            <input type="text" name="abreviatura" required placeholder="Abreviatura (ej: qq) *"
                   class="px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
            <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl transition text-sm self-start">
                💾 Guardar Unidad
            </button>
        </form>
    </div>

    <!-- Listado -->
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">Abreviatura</th>
                    <th class="px-4 py-3 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($unidades as $u): ?>
                <tr class="hover:bg-green-50">
                    <td class="px-4 py-3 text-gray-400"><?= $u['id'] ?></td>

                    <!-- Vista normal -->
                    <td class="px-4 py-3 font-bold text-gray-800 vista-normal-<?= $u['id'] ?>">
                        <?= htmlspecialchars($u['nombre']) ?>
                    </td>
                    <td class="px-4 py-3 vista-normal-<?= $u['id'] ?>">
                        <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-lg text-xs font-bold">
                            <?= htmlspecialchars($u['abreviatura']) ?>
                        </span>
                    </td>

                    <!-- Edición inline (oculta por defecto) -->
                    <td colspan="2" class="px-4 py-2 vista-edicion-<?= $u['id'] ?> hidden">
                        <form method="POST" action="<?= URL_BASE ?>/?url=inventario/actualizarUnidad"
                              class="flex gap-2 flex-wrap">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <input type="text" name="nombre" value="<?= htmlspecialchars($u['nombre']) ?>" required
                                   placeholder="Nombre *"
                                   class="flex-1 px-3 py-1 border-2 border-green-400 rounded-lg text-sm focus:outline-none">
                            <input type="text" name="abreviatura" value="<?= htmlspecialchars($u['abreviatura']) ?>" required
                                   placeholder="Abrev *"
                                   class="w-20 px-3 py-1 border-2 border-gray-200 rounded-lg text-sm focus:outline-none">
                            <button type="submit"
                                    class="bg-green-600 text-white text-xs font-bold px-3 py-1 rounded-lg hover:bg-green-700">
                                ✅ Guardar
                            </button>
                            <button type="button" onclick="cancelarEdicion(<?= $u['id'] ?>)"
                                    class="bg-gray-200 text-gray-700 text-xs font-bold px-3 py-1 rounded-lg hover:bg-gray-300">
                                Cancelar
                            </button>
                        </form>
                    </td>

                    <td class="px-4 py-3 text-center vista-normal-<?= $u['id'] ?>">
                        <div class="flex justify-center gap-2">
                            <button onclick="activarEdicion(<?= $u['id'] ?>)"
                                    class="bg-blue-100 hover:bg-blue-200 text-blue-700 text-xs font-bold px-3 py-1 rounded-lg transition">
                                ✏️ Editar
                            </button>
                            <a href="<?= URL_BASE ?>/?url=inventario/eliminarUnidad/<?= $u['id'] ?>"
                               onclick="return confirm('¿Eliminar esta unidad? Solo se puede si no tiene productos.')"
                               class="bg-red-100 hover:bg-red-200 text-red-700 text-xs font-bold px-3 py-1 rounded-lg transition">
                                🗑️ Eliminar
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function activarEdicion(id) {
    document.querySelectorAll('.vista-normal-' + id).forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.vista-edicion-' + id).forEach(el => el.classList.remove('hidden'));
}
function cancelarEdicion(id) {
    document.querySelectorAll('.vista-normal-' + id).forEach(el => el.classList.remove('hidden'));
    document.querySelectorAll('.vista-edicion-' + id).forEach(el => el.classList.add('hidden'));
}
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>