<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-extrabold text-teal-800">👥 Clientes</h2>
    <a href="<?= URL_BASE ?>/?url=clientes/nuevo"
       class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 py-2.5 rounded-xl transition text-sm">
        ➕ Nuevo Cliente
    </a>
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

<form method="GET" action="<?= URL_BASE ?>/" class="mb-5 flex gap-2">
    <input type="hidden" name="url" value="clientes/index">
    <input type="text" name="busqueda" placeholder="Buscar por nombre o teléfono..."
           value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
           class="flex-1 px-4 py-2.5 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
    <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white font-bold px-5 rounded-xl text-sm">Buscar</button>
</form>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-slate-500">Nombre</th>
                <th class="px-4 py-3 text-left text-slate-500">Teléfono</th>
                <th class="px-4 py-3 text-left text-slate-500">Dirección</th>
                <th class="px-4 py-3 text-right text-slate-500">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($clientes)): ?>
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No se encontraron clientes.</td></tr>
            <?php else: ?>
                <?php foreach ($clientes as $c): ?>
                <tr class="hover:bg-teal-50">
                    <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($c['nombre_completo']) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($c['telefono']) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($c['direccion'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= URL_BASE ?>/?url=clientes/historial/<?= $c['id'] ?>"
                           class="text-slate-600 hover:underline text-xs font-bold mr-3">📋 Historial</a>
                        <a href="<?= URL_BASE ?>/?url=clientes/editar/<?= $c['id'] ?>"
                           class="text-teal-600 hover:underline text-xs font-bold">✏️ Editar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
