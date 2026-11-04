<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-sol-oscuro">🏢 Clientes</h2>
        <p class="text-sol-gris text-sm mt-1"><?= count($clientes) ?> negocios registrados</p>
    </div>
    <a href="<?= URL_BASE ?>/?url=panel/nuevoCliente" class="btn-sol px-5 py-3 text-sm">
        ➕ Nuevo Cliente
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

<form method="GET" class="tarjeta-sol p-4 mb-6 flex gap-3">
    <input type="hidden" name="url" value="panel/clientes">
    <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>"
           placeholder="🔍 Buscar por nombre, slug o email..."
           class="campo-sol flex-1">
    <button type="submit" class="btn-sol px-5 py-2 text-sm">
        Buscar
    </button>
</form>

<div class="tarjeta-sol overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-sol-medio text-white">
                <tr>
                    <th class="px-4 py-3 text-left">Negocio</th>
                    <th class="px-4 py-3 text-left">Contacto</th>
                    <th class="px-4 py-3 text-left">Plan</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-left">Fecha alta</th>
                    <th class="px-4 py-3 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sol-borde">
                <?php if (empty($clientes)): ?>
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No hay clientes registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($clientes as $c): ?>
                    <tr class="hover:bg-sol-suave/60 transition <?= !$c['activo'] ? 'opacity-60' : '' ?>">
                        <td class="px-4 py-3">
                            <div class="font-bold text-sol-texto"><?= htmlspecialchars($c['nombre_negocio']) ?></div>
                            <div class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($c['slug']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-sol-gris">
                            <?= htmlspecialchars($c['telefono'] ?? '—') ?>
                            <?php if ($c['email_contacto']): ?>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($c['email_contacto']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php
                            $colorPlan = match($c['plan']) {
                                'premium' => 'bg-purple-100 text-purple-700',
                                'estandar' => 'bg-blue-100 text-blue-700',
                                default => 'bg-gray-100 text-gray-600'
                            };
                            ?>
                            <span class="<?= $colorPlan ?> text-xs font-bold px-2 py-1 rounded-full capitalize">
                                <?= htmlspecialchars(etiquetaPlan($c['plan'])) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($c['activo']): ?>
                                <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ Activo</span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full">🚫 Suspendido</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sol-gris text-xs"><?= date('d/m/Y', strtotime($c['creado_en'])) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex justify-center gap-2 flex-wrap">
                                <a href="<?= URL_BASE ?>/?url=panel/editarCliente/<?= $c['id'] ?>"
                                   class="bg-sol-suave hover:bg-sol-borde text-sol-acento text-xs font-bold px-3 py-1 rounded-lg transition">
                                    ✏️ Editar
                                </a>
                                <?php if (in_array(sistemaActivo(), $sistemasConReset, true)): ?>
                                <a href="<?= URL_BASE ?>/?url=panel/accesoAdmin/<?= $c['id'] ?>"
                                   class="bg-sol-suave hover:bg-sol-borde text-sol-medio text-xs font-bold px-3 py-1 rounded-lg transition">
                                    🔑 Acceso
                                </a>
                                <?php endif; ?>
                                <?php if (catalogoFunciones(sistemaActivo())): ?>
                                <a href="<?= URL_BASE ?>/?url=panel/funciones/<?= $c['id'] ?>"
                                   class="bg-sol-suave hover:bg-sol-borde text-sol-medio text-xs font-bold px-3 py-1 rounded-lg transition">
                                    ⚙️ Funciones
                                </a>
                                <?php endif; ?>
                                <a href="<?= URL_BASE ?>/?url=panel/toggleActivo/<?= $c['id'] ?>"
                                   onclick="return confirm(<?= htmlspecialchars(jsonJs(($c['activo'] ? '¿Suspender' : '¿Reactivar') . ' a ' . $c['nombre_negocio'] . '?'), ENT_QUOTES, 'UTF-8') ?>)"
                                   class="<?= $c['activo'] ? 'bg-red-100 hover:bg-red-200 text-red-700' : 'bg-green-100 hover:bg-green-200 text-green-700' ?> text-xs font-bold px-3 py-1 rounded-lg transition">
                                    <?= $c['activo'] ? '🚫 Suspender' : '✅ Activar' ?>
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
