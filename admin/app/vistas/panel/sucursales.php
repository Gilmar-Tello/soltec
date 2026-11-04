<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=panel/funciones/<?= (int) $cliente['id'] ?>" class="text-sol-acento font-bold text-xl" aria-label="Volver">←</a>
        <div>
            <h2 class="text-2xl font-extrabold text-sol-oscuro">🏬 Sucursales</h2>
            <div class="text-sm text-sol-gris">
                <?= htmlspecialchars($cliente['nombre_negocio']) ?> · plan <?= htmlspecialchars(etiquetaPlan($cliente['plan'])) ?>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">⚠️ <?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['exito'])): ?>
        <div class="bg-green-50 border border-green-300 text-green-800 rounded-xl p-4 mb-5">✅ <?= htmlspecialchars($_SESSION['exito']) ?></div>
        <?php unset($_SESSION['exito']); ?>
    <?php endif; ?>

    <?php if (!$migrado): ?>
        <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-5">
            Falta un paso en la base de datos del POS: ejecuta <code>migracion_pos_sucursales_planes_2026-10-28.sql</code> en phpMyAdmin y recarga.
        </div>
    <?php else:
        $activas = 0; foreach ($lista as $s) if ($s['activo']) $activas++;
    ?>
        <div class="bg-sol-suave border border-sol-borde rounded-xl p-4 mb-5 text-sm text-sol-medio">
            💰 Cada sucursal activa suma <strong>Q<?= SUCURSAL_PRECIO_MES ?> al mes</strong> a este cliente.
            Hoy: <strong><?= $activas ?></strong> activa<?= $activas === 1 ? '' : 's' ?> = <strong>Q<?= $activas * SUCURSAL_PRECIO_MES ?></strong> al mes.
            La sucursal <em>Principal</em> es el negocio tal como ya está y no cuenta.
        </div>

        <?php if (!$funcionActiva): ?>
            <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-4 mb-5 text-sm">
                La función <strong>Sucursales</strong> está apagada para este cliente. Enciéndela en
                <a class="underline font-bold" href="<?= URL_BASE ?>/?url=panel/funciones/<?= (int) $cliente['id'] ?>">Funciones</a>
                antes de crear sucursales.
            </div>
        <?php endif; ?>

        <div class="tarjeta-sol p-6 mb-5">
            <div class="font-extrabold text-sol-oscuro mb-3">Nueva sucursal</div>
            <form method="POST" action="<?= URL_BASE ?>/?url=panel/guardarSucursal" class="space-y-3">
                <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                <input type="hidden" name="sistema" value="pos">
                <div>
                    <label class="etiqueta-sol" for="s_nombre">Nombre <span class="text-red-500">*</span></label>
                    <input type="text" id="s_nombre" name="nombre" required maxlength="100" class="campo-sol" placeholder="ej. Sucursal Jalapa Centro">
                </div>
                <div>
                    <label class="etiqueta-sol" for="s_dir">Dirección</label>
                    <input type="text" id="s_dir" name="direccion" maxlength="255" class="campo-sol">
                </div>
                <div>
                    <label class="etiqueta-sol" for="s_tel">Teléfono</label>
                    <input type="text" id="s_tel" name="telefono" maxlength="20" class="campo-sol">
                </div>
                <button type="submit" class="btn-sol px-5 py-2.5 text-sm" <?= $funcionActiva ? '' : 'disabled style="opacity:.5"' ?>>Crear sucursal</button>
            </form>
        </div>

        <div class="tarjeta-sol p-6">
            <div class="font-extrabold text-sol-oscuro mb-3">Sucursales del cliente</div>
            <?php if (empty($lista)): ?>
                <p class="text-sm text-sol-gris">Todavía no tiene sucursales.</p>
            <?php else: ?>
                <div class="divide-y divide-sol-borde">
                <?php foreach ($lista as $s): ?>
                    <div class="py-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="font-bold text-sol-oscuro flex items-center gap-2">
                                <?= htmlspecialchars($s['nombre']) ?>
                                <?php if ($s['activo']): ?>
                                    <span class="bg-green-100 text-green-700 text-[11px] font-bold px-2 py-0.5 rounded-full">✅ Activa</span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-600 text-[11px] font-bold px-2 py-0.5 rounded-full">Desactivada</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-sol-gris">
                                <?= htmlspecialchars(trim(($s['direccion'] ?? '') . ' ' . ($s['telefono'] ? '· ' . $s['telefono'] : ''))) ?>
                            </div>
                        </div>
                        <form method="POST" action="<?= URL_BASE ?>/?url=panel/estadoSucursal">
                            <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                            <input type="hidden" name="sistema" value="pos">
                            <input type="hidden" name="sucursal_id" value="<?= (int) $s['id'] ?>">
                            <input type="hidden" name="activo" value="<?= $s['activo'] ? 0 : 1 ?>">
                            <button type="submit" class="px-3 py-1.5 text-xs font-bold rounded-lg border <?= $s['activo'] ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-green-200 text-green-700 hover:bg-green-50' ?>">
                                <?= $s['activo'] ? 'Desactivar' : 'Reactivar' ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
                </div>
                <p class="text-xs text-sol-gris mt-3">Al desactivar una sucursal se conservan sus ventas e informes; sus usuarios pasan a trabajar en la Principal hasta que la reactives.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
