<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<h2 class="text-2xl font-extrabold text-sol-oscuro mb-6">📊 Dashboard</h2>

<?php if (isset($_SESSION['exito'])): ?>
    <div class="bg-green-50 border border-green-300 text-green-800 rounded-xl p-4 mb-5">
        ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
    </div>
    <?php unset($_SESSION['exito']); ?>
<?php endif; ?>

<!-- Tarjetas resumen -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
    <div class="cifra-sol p-5 text-center">
        <div class="text-4xl font-black text-sol-celeste leading-tight"><?= $resumen['total_tenants'] ?? 0 ?></div>
        <div class="text-xs text-sol-hielo mt-1">Clientes totales</div>
    </div>
    <div class="cifra-sol p-5 text-center">
        <div class="text-4xl font-black text-sol-celeste leading-tight"><?= $resumen['activos'] ?? 0 ?></div>
        <div class="text-xs text-sol-hielo mt-1">✅ Activos</div>
    </div>
    <div class="cifra-sol p-5 text-center">
        <div class="text-4xl font-black text-red-300 leading-tight"><?= $resumen['inactivos'] ?? 0 ?></div>
        <div class="text-xs text-sol-hielo mt-1">🚫 Suspendidos</div>
    </div>
    <a href="<?= URL_BASE ?>/?url=panel/nuevoCliente"
       class="btn-sol rounded-xl shadow-sol p-5 flex items-center justify-center">
        ➕ Nuevo Cliente
    </a>
</div>

<!-- Distribución de planes -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <div class="tarjeta-sol p-5 text-center">
        <div class="text-2xl font-black text-sol-claro"><?= $resumen['plan_basico'] ?? 0 ?></div>
        <div class="text-xs text-sol-gris mt-1 font-semibold">Plan Básico</div>
    </div>
    <div class="tarjeta-sol p-5 text-center">
        <div class="text-2xl font-black text-sol-acento"><?= $resumen['plan_estandar'] ?? 0 ?></div>
        <div class="text-xs text-sol-gris mt-1 font-semibold">Plan Medio</div>
    </div>
    <div class="tarjeta-sol p-5 text-center">
        <div class="text-2xl font-black text-purple-600"><?= $resumen['plan_premium'] ?? 0 ?></div>
        <div class="text-xs text-sol-gris mt-1 font-semibold">Plan Premium</div>
    </div>
</div>

<!-- Clientes recientes -->
<div class="tarjeta-sol overflow-hidden">
    <div class="px-5 py-4 border-b border-sol-borde font-extrabold text-sol-oscuro flex items-center justify-between">
        <span>Clientes recientes</span>
        <a href="<?= URL_BASE ?>/?url=panel/clientes" class="text-sol-acento text-sm font-bold hover:underline">Ver todos →</a>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-sol-suave">
            <tr>
                <th class="px-4 py-2 text-left text-sol-claro">Negocio</th>
                <th class="px-4 py-2 text-left text-sol-claro">Plan</th>
                <th class="px-4 py-2 text-center text-sol-claro">Estado</th>
                <th class="px-4 py-2 text-left text-sol-claro">Fecha alta</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-sol-borde">
            <?php if (empty($recientes)): ?>
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Todavía no hay clientes registrados.</td></tr>
            <?php else: ?>
                <?php foreach ($recientes as $r): ?>
                <tr class="hover:bg-sol-suave/60 transition">
                    <td class="px-4 py-3 font-bold text-sol-texto"><?= htmlspecialchars($r['nombre_negocio']) ?></td>
                    <td class="px-4 py-3 text-sol-gris capitalize"><?= htmlspecialchars(etiquetaPlan($r['plan'])) ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($r['activo']): ?>
                            <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">Activo</span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full">Suspendido</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sol-gris text-xs"><?= date('d/m/Y', strtotime($r['creado_en'])) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
