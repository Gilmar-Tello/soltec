<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-green-800">🏭 Proveedores</h2>
        <p class="text-gray-500 text-sm mt-1"><?= count($proveedores) ?> proveedores registrados</p>
    </div>
    <a href="<?= URL_BASE ?>/?url=proveedores/nuevo"
       class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-3 rounded-xl transition text-sm text-center">
        ➕ Nuevo Proveedor
    </a>
</div>

<?php if (isset($_SESSION['exito'])): ?>
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-xl p-4 mb-5">
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

<!-- Buscador -->
<form method="GET" class="bg-white rounded-2xl shadow p-4 mb-6 flex gap-3">
    <input type="hidden" name="url" value="proveedores/index">
    <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>"
           placeholder="🔍 Buscar por nombre, contacto o teléfono..."
           class="flex-1 px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
    <button type="submit"
            class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl text-sm transition">
        Buscar
    </button>
    <?php if ($busqueda): ?>
        <a href="<?= URL_BASE ?>/?url=proveedores/index"
           class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-5 py-2 rounded-xl text-sm transition">
            Limpiar
        </a>
    <?php endif; ?>
</form>

<!-- Tabla -->
<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">Proveedor</th>
                    <th class="px-4 py-3 text-left">Contacto</th>
                    <th class="px-4 py-3 text-left">Teléfono</th>
                    <th class="px-4 py-3 text-center">Órdenes</th>
                    <th class="px-4 py-3 text-center">Pendientes</th>
                    <th class="px-4 py-3 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($proveedores)): ?>
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No hay proveedores registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($proveedores as $p): ?>
                    <tr class="hover:bg-green-50 transition">
                        <td class="px-4 py-3">
                            <div class="font-bold text-gray-800"><?= htmlspecialchars($p['nombre']) ?></div>
                            <?php if ($p['direccion']): ?>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($p['direccion']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($p['contacto'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($p['telefono'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-center text-gray-700 font-bold"><?= $p['total_ordenes'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($p['ordenes_pendientes'] > 0): ?>
                                <span class="bg-yellow-100 text-yellow-700 text-xs font-bold px-2 py-1 rounded-full">
                                    ⏳ <?= $p['ordenes_pendientes'] ?>
                                </span>
                            <?php else: ?>
                                <span class="text-gray-300 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-center gap-2 flex-wrap">
                                <a href="<?= URL_BASE ?>/?url=proveedores/nuevaOrden/<?= $p['id'] ?>"
                                   class="bg-green-100 hover:bg-green-200 text-green-700 text-xs font-bold px-3 py-1 rounded-lg transition">
                                    🛒 Orden
                                </a>
                                <a href="<?= URL_BASE ?>/?url=proveedores/historial/<?= $p['id'] ?>"
                                   class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-1 rounded-lg transition">
                                    📋 Historial
                                </a>
                                <a href="<?= URL_BASE ?>/?url=proveedores/editar/<?= $p['id'] ?>"
                                   class="bg-blue-100 hover:bg-blue-200 text-blue-700 text-xs font-bold px-3 py-1 rounded-lg transition">
                                    ✏️ Editar
                                </a>
                                <a href="<?= URL_BASE ?>/?url=proveedores/desactivar/<?= $p['id'] ?>"
                                   onclick="return confirm('¿Desactivar este proveedor?')"
                                   class="bg-red-100 hover:bg-red-200 text-red-700 text-xs font-bold px-3 py-1 rounded-lg transition">
                                    🚫
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>