<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h2 class="text-xl sm:text-2xl font-extrabold text-teal-800">🏬 Sucursales</h2>
    <a href="<?= URL_BASE ?>/?url=sucursales/nueva"
       class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 py-2.5 rounded-xl transition text-sm">
        ➕ Nueva Sucursal
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

<?php if (empty($sucursales)): ?>
    <div class="bg-white rounded-2xl shadow px-4 py-10 text-center text-slate-400">Todavía no hay sucursales registradas.</div>
<?php else: ?>

<!-- Celular y tablet: tarjetas. PC: tabla. -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:hidden">
    <?php foreach ($sucursales as $s): $esVitrina = ($s['tipo'] ?? 'operativa') === 'vitrina'; ?>
    <div class="bg-white rounded-2xl shadow p-4 <?= $s['activa'] ? '' : 'opacity-60' ?>">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="font-extrabold text-slate-800 truncate"><?= htmlspecialchars($s['nombre']) ?></div>
                <div class="text-xs mt-0.5 <?= $esVitrina ? 'text-violet-600' : 'text-slate-400' ?> font-bold">
                    <?= $esVitrina ? '🪟 Vitrina → ' . htmlspecialchars($s['bodega_nombre'] ?? 'sin bodega') : '🏭 Operativa' ?>
                </div>
            </div>
            <span class="shrink-0 text-xs font-bold px-2 py-1 rounded-full <?= $s['activa'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>">
                <?= $s['activa'] ? 'Activa' : 'Inactiva' ?>
            </span>
        </div>
        <div class="text-sm text-slate-500 mt-2 space-y-0.5">
            <div>📍 <?= htmlspecialchars($s['direccion'] ?: '—') ?></div>
            <div>📞 <?= htmlspecialchars($s['telefono'] ?: '—') ?></div>
        </div>
        <div class="grid grid-cols-2 gap-2 mt-3">
            <a href="<?= URL_BASE ?>/?url=sucursales/editar/<?= (int) $s['id'] ?>"
               class="text-center bg-teal-50 text-teal-700 font-bold py-2.5 rounded-xl text-sm">✏️ Editar</a>
            <a href="<?= URL_BASE ?>/?url=sucursales/toggleActiva/<?= (int) $s['id'] ?>"
               onclick="return confirm('¿<?= $s['activa'] ? 'Desactivar' : 'Reactivar' ?> esta sucursal?')"
               class="text-center bg-orange-50 text-orange-600 font-bold py-2.5 rounded-xl text-sm">
                <?= $s['activa'] ? '🚫 Desactivar' : '✅ Reactivar' ?>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="hidden lg:block bg-white rounded-2xl shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-slate-500">Nombre</th>
                <th class="px-4 py-3 text-left text-slate-500">Tipo</th>
                <th class="px-4 py-3 text-left text-slate-500">Dirección</th>
                <th class="px-4 py-3 text-left text-slate-500">Teléfono</th>
                <th class="px-4 py-3 text-center text-slate-500">Estado</th>
                <th class="px-4 py-3 text-right text-slate-500">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($sucursales as $s): $esVitrina = ($s['tipo'] ?? 'operativa') === 'vitrina'; ?>
            <tr class="hover:bg-teal-50">
                <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($s['nombre']) ?></td>
                <td class="px-4 py-3 text-xs font-bold whitespace-nowrap <?= $esVitrina ? 'text-violet-600' : 'text-slate-500' ?>">
                    <?= $esVitrina ? '🪟 Vitrina → ' . htmlspecialchars($s['bodega_nombre'] ?? 'sin bodega') : '🏭 Operativa' ?>
                </td>
                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($s['direccion'] ?: '—') ?></td>
                <td class="px-4 py-3 text-slate-600 whitespace-nowrap"><?= htmlspecialchars($s['telefono'] ?: '—') ?></td>
                <td class="px-4 py-3 text-center">
                    <span class="text-xs font-bold px-2 py-1 rounded-full <?= $s['activa'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>">
                        <?= $s['activa'] ? 'Activa' : 'Inactiva' ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <a href="<?= URL_BASE ?>/?url=sucursales/editar/<?= (int) $s['id'] ?>"
                       class="text-teal-600 hover:underline text-xs font-bold mr-3">✏️ Editar</a>
                    <a href="<?= URL_BASE ?>/?url=sucursales/toggleActiva/<?= (int) $s['id'] ?>"
                       onclick="return confirm('¿<?= $s['activa'] ? 'Desactivar' : 'Reactivar' ?> esta sucursal?')"
                       class="text-orange-600 hover:underline text-xs font-bold">
                        <?= $s['activa'] ? '🚫 Desactivar' : '✅ Reactivar' ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
