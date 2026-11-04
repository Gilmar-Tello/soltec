<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=panel/clientes" class="text-sol-acento font-bold text-xl" aria-label="Volver">←</a>
        <div>
            <h2 class="text-2xl font-extrabold text-sol-oscuro">⚙️ Funciones</h2>
            <div class="text-sm text-sol-gris">
                <?= htmlspecialchars($cliente['nombre_negocio']) ?> ·
                <?= htmlspecialchars($GLOBALS['SISTEMAS_SOLTEC'][$sistema]['nombre'] ?? $sistema) ?>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['exito'])): ?>
        <div class="bg-green-50 border border-green-300 text-green-800 rounded-xl p-4 mb-5">
            ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
        </div>
        <?php unset($_SESSION['exito']); ?>
    <?php endif; ?>

    <?php if (!$catalogo): ?>
        <div class="tarjeta-sol p-6 text-center text-sol-gris">
            Este sistema todavía no tiene funciones opcionales.
        </div>

    <?php elseif (!$migrado): ?>
        <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-5">
            Falta un paso en la base de datos de este sistema: ejecuta
            <code>migracion_funciones_negocio_2026-10-27.sql</code> y
            <code>migracion_pos_mejoras_2026-10-28.sql</code> en phpMyAdmin y recarga esta página.
        </div>

    <?php else: ?>
        <div class="bg-sol-suave border border-sol-borde rounded-xl p-4 mb-5 text-sm text-sol-medio">
            💡 Las funciones están <strong>apagadas por defecto</strong>. Enciende solo las que el cliente contrató;
            el cambio se aplica en el sistema del cliente en pocos segundos. Cada cambio queda en la bitácora.
        </div>

        <form method="POST" action="<?= URL_BASE ?>/?url=panel/guardarFunciones" class="space-y-3">
            <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
            <input type="hidden" name="sistema" value="<?= htmlspecialchars($sistema) ?>">

            <?php foreach ($catalogo as $clave => $def):
                $disponible = !empty($def['disponible']);
                $encendida  = !empty($activas[$clave]);
                $conParam   = ($def['parametro'] ?? '') === 'negocio_brix';
            ?>
            <div class="tarjeta-sol p-5 <?= $disponible ? '' : 'opacity-60' ?>">
                <label class="flex items-start gap-4 <?= $disponible ? 'cursor-pointer' : '' ?>">
                    <input type="checkbox" name="f[<?= htmlspecialchars($clave) ?>]" value="1"
                           class="mt-1 w-5 h-5 accent-sol-acento"
                           <?= $encendida ? 'checked' : '' ?> <?= $disponible ? '' : 'disabled' ?>>
                    <div class="flex-1">
                        <div class="font-extrabold text-sol-oscuro flex flex-wrap items-center gap-2">
                            <span><?= htmlspecialchars($def['icono'] ?? '⚙️') ?></span>
                            <?= htmlspecialchars($def['nombre']) ?>
                            <?php if (!empty($def['costo_extra'])): ?>
                                <span class="bg-amber-100 text-amber-800 text-[11px] font-bold px-2 py-0.5 rounded-full">💰 Costo extra<?= !empty($def['precio']) ? ' · ' . htmlspecialchars($def['precio']) : '' ?></span>
                            <?php endif; ?>
                            <?php if (!$disponible): ?>
                                <span class="bg-gray-100 text-gray-600 text-[11px] font-bold px-2 py-0.5 rounded-full">Próximamente</span>
                            <?php elseif ($encendida): ?>
                                <span class="bg-green-100 text-green-700 text-[11px] font-bold px-2 py-0.5 rounded-full">✅ Encendida</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-sol-gris mt-1"><?= htmlspecialchars($def['descripcion']) ?></p>
                    </div>
                </label>

                <?php if (($def['enlace_admin'] ?? '') === 'sucursales' && $disponible): ?>
                    <div class="mt-4 ml-9 rounded-lg border border-sol-borde bg-sol-suave p-3 text-sm flex flex-wrap items-center justify-between gap-3">
                        <div class="text-sol-medio">
                            <?php if ($sucursalesInfo !== null): ?>
                                Sucursales activas: <strong><?= (int) $sucursalesInfo['activas'] ?></strong>
                                (de <?= (int) $sucursalesInfo['total'] ?>) ·
                                suman <strong>Q<?= (int) $sucursalesInfo['activas'] * SUCURSAL_PRECIO_MES ?></strong> al mes
                            <?php else: ?>
                                Enciende la función y guarda para crear sucursales.
                            <?php endif; ?>
                        </div>
                        <a href="<?= URL_BASE ?>/?url=panel/sucursales/<?= (int) $cliente['id'] ?>"
                           class="px-3 py-1.5 text-xs font-bold rounded-lg bg-white border border-sol-borde text-sol-acento hover:bg-sol-borde">🏬 Administrar sucursales</a>
                    </div>
                <?php endif; ?>

                <?php if ($conParam && $disponible): ?>
                    <div class="mt-4 ml-9">
                        <label for="p_<?= htmlspecialchars($clave) ?>" class="etiqueta-sol">Negocio de Brix al que se envían los movimientos</label>
                        <select id="p_<?= htmlspecialchars($clave) ?>" name="p[<?= htmlspecialchars($clave) ?>]" class="campo-sol">
                            <option value="">— Elige el negocio —</option>
                            <?php foreach ($negociosBrix as $nb): ?>
                                <option value="<?= (int) $nb['id'] ?>" <?= ((string) ($valores[$clave] ?? '') === (string) $nb['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nb['nombre_negocio']) ?> (#<?= (int) $nb['id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-sol-gris mt-1">
                            Es el negocio del mismo cliente en Brix. Cada cierre de caja y cada compra recibida del POS
                            llegan a su libro de caja. Hace falta una tarea programada (cron) activa en hPanel.
                        </p>

                        <?php if ($colaEstado !== null && $clave === 'sync_brix'): ?>
                            <div class="mt-3 rounded-lg border border-sol-borde bg-sol-suave p-3 text-sm">
                                <div class="font-bold text-sol-oscuro mb-1">Estado del envío</div>
                                <div class="flex flex-wrap gap-4 text-sol-medio">
                                    <span>⏳ Pendientes: <strong><?= (int) $colaEstado['pendiente'] ?></strong></span>
                                    <span>✅ Enviados: <strong><?= (int) $colaEstado['enviado'] ?></strong></span>
                                    <span class="<?= $colaEstado['error'] ? 'text-red-600' : '' ?>">⚠️ Con error: <strong><?= (int) $colaEstado['error'] ?></strong></span>
                                </div>
                                <?php if (!empty($colaEstado['ultimo_error'])): ?>
                                    <div class="text-xs text-red-600 mt-2">
                                        Último error (<?= htmlspecialchars($colaEstado['ultimo_error']['referencia']) ?>):
                                        <?= htmlspecialchars($colaEstado['ultimo_error']['ultimo_error']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="pt-2">
                <button type="submit" class="btn-sol px-5 py-2.5 text-sm">Guardar cambios</button>
            </div>
        </form>

        <?php if ($sistema === 'pos' && isset($catalogo['sync_brix']) && $colaEstado !== null): ?>
            <form method="POST" action="<?= URL_BASE ?>/?url=panel/sincronizarBrix" class="mt-4">
                <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                <input type="hidden" name="sistema" value="pos">
                <button type="submit" class="px-4 py-2 text-sm font-bold rounded-lg border border-sol-borde text-sol-medio hover:bg-sol-suave">
                    🔄 Sincronizar ahora
                </button>
                <span class="text-xs text-sol-gris ml-2">Envía a Brix lo pendiente sin esperar a la tarea programada.</span>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
