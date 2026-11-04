<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-extrabold text-teal-800">🏷️ Categorías</h2>
</div>

<?php if (isset($_SESSION['exito'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl p-4 mb-5">
        ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
    </div>
    <?php unset($_SESSION['exito']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Formulario nueva categoría -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-700 mb-4">➕ Nueva categoría</h3>
            <form method="POST" action="<?= URL_BASE ?>/?url=categorias/guardar" class="space-y-3">
                <div>
                    <label class="block text-sm font-bold text-slate-600 mb-1">Nombre <span class="text-red-500">*</span></label>
                    <input type="text" name="nombre" required
                           placeholder="Ej: Sillas, Mesas, Manteles..."
                           class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
                </div>
                <button type="submit"
                        class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 rounded-xl transition text-sm">
                    💾 Crear categoría
                </button>
            </form>
        </div>
    </div>

    <!-- Listado -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 font-bold text-slate-700">
                Categorías registradas (<?= count($categorias) ?>)
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-slate-500">Nombre</th>
                        <th class="px-4 py-3 text-center text-slate-500">Productos</th>
                        <th class="px-4 py-3 text-right text-slate-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($categorias)): ?>
                        <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">Todavía no hay categorías.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categorias as $c): ?>
                        <tr class="hover:bg-teal-50">
                            <td class="px-4 py-3 font-bold text-slate-800">
                                🏷️ <?= htmlspecialchars($c['nombre']) ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="bg-teal-100 text-teal-700 text-xs font-bold px-2 py-1 rounded-full">
                                    <?= $c['total_productos'] ?> producto<?= $c['total_productos'] != 1 ? 's' : '' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= URL_BASE ?>/?url=categorias/editar/<?= $c['id'] ?>"
                                   class="text-teal-600 hover:underline text-xs font-bold mr-3">✏️ Editar</a>
                                <?php if ($c['total_productos'] == 0): ?>
                                    <a href="<?= URL_BASE ?>/?url=categorias/eliminar/<?= $c['id'] ?>"
                                       onclick="return confirm('¿Eliminar esta categoría?')"
                                       class="text-red-500 hover:underline text-xs font-bold">🗑️ Eliminar</a>
                                <?php else: ?>
                                    <span class="text-slate-300 text-xs">🗑️ Eliminar</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
