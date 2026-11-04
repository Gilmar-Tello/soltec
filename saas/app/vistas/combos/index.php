<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-extrabold text-teal-800">🎁 Combos</h2>
    <a href="<?= URL_BASE ?>/?url=combos/nuevo"
       class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 py-2.5 rounded-xl transition text-sm">
        ➕ Nuevo Combo
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

<div class="bg-teal-50 border border-teal-200 rounded-xl p-4 mb-5 text-sm text-teal-800">
    💡 Los combos son paquetes predefinidos de productos que podés ofrecer rápidamente al crear una reserva. Solo aparecen si todos sus productos tienen disponibilidad en la sucursal seleccionada.
</div>

<?php if (empty($combos)): ?>
    <div class="bg-white rounded-2xl shadow p-12 text-center text-slate-400">
        <div class="text-4xl mb-3">🎁</div>
        <p class="font-bold">Todavía no hay combos registrados.</p>
        <p class="text-sm mt-1">Creá tu primer combo para ofrecer paquetes a tus clientes.</p>
        <a href="<?= URL_BASE ?>/?url=combos/nuevo"
           class="inline-block mt-4 bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 py-2.5 rounded-xl text-sm transition">
            ➕ Crear primer combo
        </a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($combos as $c): ?>
        <div class="bg-white rounded-2xl shadow p-5 <?= !$c['activo'] ? 'opacity-50' : '' ?>">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <h3 class="font-extrabold text-slate-800"><?= htmlspecialchars($c['nombre']) ?></h3>
                    <?php if ($c['descripcion']): ?>
                        <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($c['descripcion']) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($c['activo']): ?>
                    <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-0.5 rounded-full">Activo</span>
                <?php else: ?>
                    <span class="bg-slate-200 text-slate-500 text-xs font-bold px-2 py-0.5 rounded-full">Inactivo</span>
                <?php endif; ?>
            </div>
            <div class="text-xs text-slate-400 mb-4">
                <?= $c['total_productos'] ?> producto<?= $c['total_productos'] != 1 ? 's' : '' ?>
            </div>
            <div class="flex gap-2">
                <a href="<?= URL_BASE ?>/?url=combos/editar/<?= $c['id'] ?>"
                   class="flex-1 text-center bg-teal-50 hover:bg-teal-100 text-teal-700 font-bold py-2 rounded-xl text-xs transition">
                    ✏️ Editar
                </a>
                <a href="<?= URL_BASE ?>/?url=combos/toggleActivo/<?= $c['id'] ?>"
                   class="flex-1 text-center <?= $c['activo'] ? 'bg-orange-50 hover:bg-orange-100 text-orange-600' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700' ?> font-bold py-2 rounded-xl text-xs transition">
                    <?= $c['activo'] ? '🚫 Desactivar' : '✅ Activar' ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
