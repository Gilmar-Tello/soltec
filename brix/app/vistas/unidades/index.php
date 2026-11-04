<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-900">Unidades de medida</h2>
    <p class="text-sm text-slate-500">Unidad, libra, litro, caja, etc.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="bg-white rounded-lg border border-slate-200 p-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-3"><?= $editar ? 'Editar unidad' : 'Nueva unidad' ?></h3>
        <form method="POST" action="<?= URL_BASE ?>/?url=unidades/<?= $editar ? 'actualizar' : 'guardar' ?>" class="space-y-3">
            <?php if ($editar): ?><input type="hidden" name="id" value="<?= $editar['id'] ?>"><?php endif; ?>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Nombre</label>
                <input type="text" name="nombre" required value="<?= htmlspecialchars($editar['nombre'] ?? '') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Ej. Libra">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Abreviatura</label>
                <input type="text" name="abreviatura" required maxlength="10" value="<?= htmlspecialchars($editar['abreviatura'] ?? '') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Ej. lb">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium px-4 py-2"><?= $editar ? 'Guardar cambios' : 'Crear' ?></button>
                <?php if ($editar): ?>
                    <a href="<?= URL_BASE ?>/?url=unidades/index" class="rounded-md border border-slate-300 text-slate-600 text-sm px-4 py-2">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-lg border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-4 py-3">Nombre</th>
                    <th class="text-left px-4 py-3">Abreviatura</th>
                    <th class="text-center px-4 py-3">Productos</th>
                    <th class="text-center px-4 py-3">Estado</th>
                    <th class="text-right px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($unidades)): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Sin unidades.</td></tr>
                <?php else: foreach ($unidades as $u): ?>
                    <tr class="hover:bg-slate-50 <?= $u['activo'] ? '' : 'opacity-50' ?>">
                        <td class="px-4 py-3 text-slate-800 font-medium"><?= htmlspecialchars($u['nombre']) ?></td>
                        <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($u['abreviatura']) ?></td>
                        <td class="px-4 py-3 text-center text-slate-500"><?= (int)$u['n_productos'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs <?= $u['activo'] ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $u['activo'] ? 'Activa' : 'Inactiva' ?></span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="<?= URL_BASE ?>/?url=unidades/index&editar=<?= $u['id'] ?>" class="text-indigo-600 hover:underline text-xs mr-3">Editar</a>
                            <a href="<?= URL_BASE ?>/?url=unidades/toggle/<?= $u['id'] ?>" class="text-slate-500 hover:underline text-xs"><?= $u['activo'] ? 'Desactivar' : 'Activar' ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
