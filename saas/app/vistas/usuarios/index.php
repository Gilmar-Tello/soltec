<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h2 class="text-xl sm:text-2xl font-extrabold text-teal-800">👤 Usuarios</h2>
    <a href="<?= URL_BASE ?>/?url=usuarios/nuevo"
       class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 py-2.5 rounded-xl transition text-sm">
        ➕ Nuevo Usuario
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

<?php if (empty($usuarios)): ?>
    <div class="bg-white rounded-2xl shadow px-4 py-10 text-center text-slate-400">No hay usuarios registrados.</div>
<?php else: ?>

<!-- Celular y tablet: tarjetas -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:hidden">
    <?php foreach ($usuarios as $u): $esYo = (int) $u['id'] === (int) $_SESSION['usuario_id']; ?>
    <div class="bg-white rounded-2xl shadow p-4 <?= $u['activo'] ? '' : 'opacity-60' ?>">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="font-extrabold text-slate-800 truncate">
                    <?= htmlspecialchars($u['nombre']) ?>
                    <?php if ($esYo): ?><span class="text-xs text-teal-500 font-bold">(tú)</span><?php endif; ?>
                </div>
                <div class="text-sm text-slate-500 truncate"><?= htmlspecialchars($u['email']) ?></div>
            </div>
            <span class="shrink-0 text-xs font-bold px-2 py-1 rounded-full <?= $u['activo'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500' ?>">
                <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
            </span>
        </div>
        <div class="flex flex-wrap items-center gap-2 mt-2 text-xs">
            <span class="font-bold px-2 py-1 rounded-full <?= $u['rol'] === 'admin_negocio' ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-600' ?>">
                <?= $u['rol'] === 'admin_negocio' ? 'Admin' : 'Trabajador' ?>
            </span>
            <span class="text-slate-500">🏬 <?= htmlspecialchars($u['sucursal_nombre'] ?? 'Todas') ?></span>
        </div>
        <div class="grid <?= $esYo ? 'grid-cols-1' : 'grid-cols-2' ?> gap-2 mt-3">
            <a href="<?= URL_BASE ?>/?url=usuarios/editar/<?= (int) $u['id'] ?>"
               class="text-center bg-teal-50 text-teal-700 font-bold py-2.5 rounded-xl text-sm">✏️ Editar</a>
            <?php if (!$esYo): ?>
                <a href="<?= URL_BASE ?>/?url=usuarios/toggleActivo/<?= (int) $u['id'] ?>"
                   onclick="return confirm('¿<?= $u['activo'] ? 'Desactivar' : 'Activar' ?> a este usuario?')"
                   class="text-center font-bold py-2.5 rounded-xl text-sm <?= $u['activo'] ? 'bg-orange-50 text-orange-600' : 'bg-emerald-50 text-emerald-700' ?>">
                    <?= $u['activo'] ? '🚫 Desactivar' : '✅ Activar' ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- PC: tabla -->
<div class="hidden lg:block bg-white rounded-2xl shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-slate-500">Nombre</th>
                <th class="px-4 py-3 text-left text-slate-500">Correo</th>
                <th class="px-4 py-3 text-left text-slate-500">Sucursal</th>
                <th class="px-4 py-3 text-center text-slate-500">Rol</th>
                <th class="px-4 py-3 text-center text-slate-500">Estado</th>
                <th class="px-4 py-3 text-right text-slate-500">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($usuarios as $u): $esYo = (int) $u['id'] === (int) $_SESSION['usuario_id']; ?>
            <tr class="hover:bg-teal-50 <?= $u['activo'] ? '' : 'opacity-50' ?>">
                <td class="px-4 py-3 font-bold text-slate-800">
                    <?= htmlspecialchars($u['nombre']) ?>
                    <?php if ($esYo): ?><span class="text-xs text-teal-500 ml-1">(tú)</span><?php endif; ?>
                </td>
                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($u['email']) ?></td>
                <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($u['sucursal_nombre'] ?? 'Todas') ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="text-xs font-bold px-2 py-1 rounded-full <?= $u['rol'] === 'admin_negocio' ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $u['rol'] === 'admin_negocio' ? 'Admin' : 'Trabajador' ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="text-xs font-bold px-2 py-1 rounded-full <?= $u['activo'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500' ?>">
                        <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <a href="<?= URL_BASE ?>/?url=usuarios/editar/<?= (int) $u['id'] ?>"
                       class="text-teal-600 hover:underline text-xs font-bold mr-3">✏️ Editar</a>
                    <?php if (!$esYo): ?>
                        <a href="<?= URL_BASE ?>/?url=usuarios/toggleActivo/<?= (int) $u['id'] ?>"
                           onclick="return confirm('¿<?= $u['activo'] ? 'Desactivar' : 'Activar' ?> a este usuario?')"
                           class="text-xs font-bold <?= $u['activo'] ? 'text-orange-500' : 'text-emerald-600' ?> hover:underline">
                            <?= $u['activo'] ? '🚫 Desactivar' : '✅ Activar' ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
