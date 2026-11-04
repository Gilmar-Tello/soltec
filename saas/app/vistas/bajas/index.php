<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-extrabold text-teal-800">🗑️ Gestión de Bajas</h2>
    <a href="<?= URL_BASE ?>/?url=bajas/nueva"
       class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-5 py-2.5 rounded-xl transition text-sm">
        ➕ Registrar Baja
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

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-slate-500">Producto</th>
                <th class="px-4 py-3 text-left text-slate-500">Sucursal</th>
                <th class="px-4 py-3 text-center text-slate-500">Cantidad</th>
                <th class="px-4 py-3 text-left text-slate-500">Motivo</th>
                <th class="px-4 py-3 text-left text-slate-500">Fecha</th>
                <th class="px-4 py-3 text-left text-slate-500">Registrado por</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($bajas)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No hay bajas registradas.</td></tr>
            <?php else: ?>
                <?php foreach ($bajas as $b): ?>
                <tr class="hover:bg-orange-50">
                    <td class="px-4 py-3 font-bold text-slate-800"><?= htmlspecialchars($b['producto_nombre']) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($b['sucursal_nombre']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded-full">
                            -<?= $b['cantidad'] ?> ud.
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($b['motivo']) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= fechaCorta($b['fecha_baja']) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($b['usuario_nombre']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
