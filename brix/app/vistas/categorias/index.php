<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-900">Categorías</h2>
    <p class="text-sm text-slate-500">Clasificación de los productos</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Form -->
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-3"><?= $editar ? 'Editar categoría' : 'Nueva categoría' ?></h3>
        <form method="POST" action="<?= URL_BASE ?>/?url=categorias/<?= $editar ? 'actualizar' : 'guardar' ?>" class="space-y-3">
            <?php if ($editar): ?><input type="hidden" name="id" value="<?= $editar['id'] ?>"><?php endif; ?>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Nombre</label>
                <input type="text" name="nombre" required value="<?= htmlspecialchars($editar['nombre'] ?? '') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Descripción (opcional)</label>
                <input type="text" name="descripcion" value="<?= htmlspecialchars($editar['descripcion'] ?? '') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium px-4 py-2"><?= $editar ? 'Guardar cambios' : 'Crear' ?></button>
                <?php if ($editar): ?>
                    <a href="<?= URL_BASE ?>/?url=categorias/index" class="rounded-md border border-slate-300 text-slate-600 text-sm px-4 py-2">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Lista -->
    <div class="lg:col-span-2 bg-white rounded-lg border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-4 py-3">Nombre</th>
                    <th class="text-left px-4 py-3">Descripción</th>
                    <th class="text-center px-4 py-3">Productos</th>
                    <th class="text-center px-4 py-3">Estado</th>
                    <th class="text-right px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($categorias)): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Sin categorías.</td></tr>
                <?php else: foreach ($categorias as $c): ?>
                    <tr class="hover:bg-slate-50 <?= $c['activo'] ? '' : 'opacity-50' ?>">
                        <td class="px-4 py-3 text-slate-800 font-medium"><?= htmlspecialchars($c['nombre']) ?></td>
                        <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($c['descripcion'] ?? '') ?></td>
                        <td class="px-4 py-3 text-center text-slate-500"><?= (int)$c['n_productos'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs <?= $c['activo'] ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $c['activo'] ? 'Activa' : 'Inactiva' ?></span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="<?= URL_BASE ?>/?url=categorias/index&editar=<?= $c['id'] ?>" class="text-indigo-600 hover:underline text-xs mr-3">Editar</a>
                            <a href="<?= URL_BASE ?>/?url=categorias/toggle/<?= $c['id'] ?>" class="text-slate-500 hover:underline text-xs"><?= $c['activo'] ? 'Desactivar' : 'Activar' ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
