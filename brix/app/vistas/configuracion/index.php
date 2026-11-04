<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-900">Datos del negocio</h2>
    <p class="text-sm text-slate-500">Esta información aparece en los comprobantes.</p>
</div>

<form method="POST" action="<?= URL_BASE ?>/?url=configuracion/guardar" enctype="multipart/form-data" class="bg-white rounded-lg border border-slate-200 p-6 max-w-2xl">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Nombre del negocio *</label>
            <input type="text" name="nombre_negocio" required value="<?= htmlspecialchars($negocio['nombre_negocio'] ?? '') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Dirección</label>
            <input type="text" name="direccion" value="<?= htmlspecialchars($negocio['direccion'] ?? '') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Teléfono</label>
            <input type="text" name="telefono" value="<?= htmlspecialchars($negocio['telefono'] ?? '') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">NIT</label>
            <input type="text" name="nit" value="<?= htmlspecialchars($negocio['nit'] ?? '') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Logo</label>
            <?php if (!empty($negocio['logo'])): ?>
                <div class="mb-2">
                    <img src="<?= URL_BASE ?>/<?= htmlspecialchars($negocio['logo']) ?>" alt="Logo" class="h-20 rounded border border-slate-200 bg-white p-1 object-contain">
                </div>
            <?php endif; ?>
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:text-white file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-800">
            <p class="text-xs text-slate-400 mt-1">JPG, PNG, WEBP o GIF. Máximo 2 MB. Dejalo vacío para conservar el actual.</p>
        </div>
    </div>

    <div class="mt-6">
        <button type="submit" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold px-5 py-2.5">Guardar cambios</button>
    </div>
</form>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
