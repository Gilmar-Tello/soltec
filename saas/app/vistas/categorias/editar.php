<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-md mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=categorias/index" class="text-teal-700 font-bold text-xl">←</a>
        <h2 class="text-2xl font-extrabold text-teal-800">✏️ Editar Categoría</h2>
    </div>

    <form method="POST" action="<?= URL_BASE ?>/?url=categorias/actualizar"
          class="bg-white rounded-2xl shadow p-6 space-y-4">
        <input type="hidden" name="id" value="<?= $categoria['id'] ?>">
        <div>
            <label class="block text-sm font-bold text-slate-600 mb-1">Nombre <span class="text-red-500">*</span></label>
            <input type="text" name="nombre" required
                   value="<?= htmlspecialchars($categoria['nombre']) ?>"
                   class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
        </div>
        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-6 py-3 rounded-xl transition">
                💾 Guardar cambios
            </button>
            <a href="<?= URL_BASE ?>/?url=categorias/index"
               class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-6 py-3 rounded-xl transition">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
