<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-green-800">👤 Usuarios</h2>
        <p class="text-gray-500 text-sm mt-1"><?= count($usuarios) ?> usuarios registrados</p>
    </div>
    <a href="<?= URL_BASE ?>/?url=usuarios/nuevo"
       class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-3 rounded-xl transition text-sm text-center">
        ➕ Nuevo Usuario
    </a>
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

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">Usuario</th>
                    <th class="px-4 py-3 text-left">Rol</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($usuarios as $u): ?>
                <tr class="hover:bg-green-50 transition <?= !$u['activo'] ? 'opacity-50' : '' ?>">
                    <td class="px-4 py-3">
                        <div class="font-bold text-gray-800"><?= htmlspecialchars($u['nombre']) ?></div>
                        <?php if ($u['id'] == $_SESSION['usuario_id']): ?>
                            <div class="text-xs text-green-500">← Tú</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 font-mono text-gray-600"><?= htmlspecialchars($u['usuario']) ?></td>
                    <td class="px-4 py-3">
                        <?php
                        $rolColor = match($u['rol_id']) {
                            1 => 'bg-purple-100 text-purple-700',
                            2 => 'bg-blue-100 text-blue-700',
                            3 => 'bg-yellow-100 text-yellow-700',
                            default => 'bg-gray-100 text-gray-600'
                        };
                        $rolIcon = match($u['rol_id']) {
                            1 => '👑', 2 => '🛍️', 3 => '📦', default => '👤'
                        };
                        ?>
                        <span class="<?= $rolColor ?> text-xs font-bold px-2 py-1 rounded-full">
                            <?= $rolIcon ?> <?= htmlspecialchars($u['rol_nombre']) ?>
                        </span>
                        <?php if (sucursalesHabilitadas()): ?>
                            <div class="text-xs text-gray-500 mt-1">🏬 <?= htmlspecialchars(nombresSucursales()[(int) ($u['sucursal_id'] ?? 0)] ?? NOMBRE_SUCURSAL_PRINCIPAL) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($u['activo']): ?>
                            <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ Activo</span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full">🚫 Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-center gap-2">
                            <a href="<?= URL_BASE ?>/?url=usuarios/editar/<?= $u['id'] ?>"
                               class="bg-blue-100 hover:bg-blue-200 text-blue-700 text-xs font-bold px-3 py-1 rounded-lg transition">
                                ✏️ Editar
                            </a>
                            <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
                            <a href="<?= URL_BASE ?>/?url=usuarios/toggleActivo/<?= $u['id'] ?>"
                               onclick="return confirm('<?= $u['activo'] ? '¿Desactivar' : '¿Activar' ?> este usuario?')"
                               class="<?= $u['activo'] ? 'bg-red-100 hover:bg-red-200 text-red-700' : 'bg-green-100 hover:bg-green-200 text-green-700' ?> text-xs font-bold px-3 py-1 rounded-lg transition">
                                <?= $u['activo'] ? '🚫 Desactivar' : '✅ Activar' ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>