<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<?php
    $suspendido = $estado !== null && (int) $estado['estado'] === 0;
    $dias       = ($estado !== null && $estado['dias'] !== null) ? (int) $estado['dias'] : null;

    if ($dias === null)      { $relativo = 'Sin fecha de pago: los usuarios no ven ningún aviso.'; }
    elseif ($dias > 0)       { $relativo = 'Faltan ' . $dias . ' día' . ($dias === 1 ? '' : 's'); }
    elseif ($dias === 0)     { $relativo = 'Vence hoy (vale hasta las 23:59)'; }
    else                     { $relativo = 'Venció hace ' . abs($dias) . ' día' . (abs($dias) === 1 ? '' : 's'); }
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-extrabold text-sol-oscuro"><?= $servicio['icono'] ?> <?= htmlspecialchars($servicio['nombre']) ?></h2>
        <div class="text-sm text-sol-gris">Servicio mensual · <?= htmlspecialchars($servicio['sitio']) ?></div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">⚠️ <?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['exito'])): ?>
        <div class="bg-green-50 border border-green-300 text-green-800 rounded-xl p-4 mb-5">✅ <?= htmlspecialchars($_SESSION['exito']) ?></div>
        <?php unset($_SESSION['exito']); ?>
    <?php endif; ?>

    <?php if ($problema !== ''): ?>
        <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-5 text-sm">
            <?= htmlspecialchars($problema) ?>
        </div>
    <?php else: ?>

        <!-- Estado actual -->
        <div class="tarjeta-sol p-6 mb-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-xs text-sol-gris font-bold uppercase tracking-wide">Estado del acceso</div>
                    <?php if ($suspendido): ?>
                        <div class="mt-1 inline-block bg-red-100 text-red-700 font-extrabold px-3 py-1 rounded-full">⛔ Suspendido</div>
                    <?php else: ?>
                        <div class="mt-1 inline-block bg-green-100 text-green-700 font-extrabold px-3 py-1 rounded-full">✅ Habilitado</div>
                    <?php endif; ?>
                </div>
                <div class="text-right">
                    <div class="text-xs text-sol-gris font-bold uppercase tracking-wide">Fecha de pago</div>
                    <div class="mt-1 font-extrabold text-sol-oscuro">
                        <?= $estado['fecha_pago'] ? date('d/m/Y', strtotime($estado['fecha_pago'])) : '—' ?>
                    </div>
                    <div class="text-xs <?= ($dias !== null && $dias < 0) ? 'text-red-600 font-bold' : 'text-sol-gris' ?>">
                        <?= htmlspecialchars($relativo) ?>
                    </div>
                </div>
            </div>

            <?php if ($suspendido): ?>
                <div class="mt-4 text-sm text-sol-medio bg-sol-suave border border-sol-borde rounded-lg p-3">
                    Suspendido desde <strong><?= $estado['suspendido_desde'] ? date('d/m/Y H:i', strtotime($estado['suspendido_desde'])) : '—' ?></strong>
                    <?php if (!empty($estado['motivo'])): ?>
                        · Motivo: <?= htmlspecialchars($estado['motivo']) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Habilitar / suspender -->
            <div class="mt-5 pt-5 border-t border-sol-borde">
                <?php if ($suspendido): ?>
                    <form method="POST" action="<?= URL_BASE ?>/?url=servicios/cambiarEstado"
                          onsubmit="return confirm('¿Habilitar el acceso de <?= htmlspecialchars($servicio['nombre'], ENT_QUOTES) ?>? Sus usuarios podrán volver a iniciar sesión.');">
                        <input type="hidden" name="servicio" value="<?= htmlspecialchars($servicioClave) ?>">
                        <input type="hidden" name="estado" value="1">
                        <button type="submit" class="btn-sol px-5 py-2.5 text-sm">✅ Habilitar acceso</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="<?= URL_BASE ?>/?url=servicios/cambiarEstado" class="space-y-3"
                          onsubmit="return confirm('¿Suspender el acceso de <?= htmlspecialchars($servicio['nombre'], ENT_QUOTES) ?>? Nadie podrá entrar ni seguir trabajando hasta que lo habilites.');">
                        <input type="hidden" name="servicio" value="<?= htmlspecialchars($servicioClave) ?>">
                        <input type="hidden" name="estado" value="0">
                        <div>
                            <label class="etiqueta-sol" for="motivo">Motivo (opcional)</label>
                            <input type="text" id="motivo" name="motivo" maxlength="255" class="campo-sol" placeholder="Pago pendiente">
                        </div>
                        <button type="submit" class="px-5 py-2.5 text-sm font-bold rounded-lg border border-red-300 text-red-700 hover:bg-red-50">⛔ Suspender acceso</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Fecha de pago -->
        <div class="tarjeta-sol p-6 mb-5">
            <div class="font-extrabold text-sol-oscuro mb-3">Próxima fecha de pago</div>
            <form method="POST" action="<?= URL_BASE ?>/?url=servicios/guardarFecha" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="servicio" value="<?= htmlspecialchars($servicioClave) ?>">
                <div>
                    <label class="etiqueta-sol" for="fecha_pago">Fecha</label>
                    <input type="date" id="fecha_pago" name="fecha_pago" class="campo-sol"
                           value="<?= htmlspecialchars((string) ($estado['fecha_pago'] ?? '')) ?>">
                </div>
                <button type="submit" class="btn-sol px-5 py-2.5 text-sm">Guardar fecha</button>
            </form>
            <p class="text-xs text-sol-gris mt-3">
                La fecha vale hasta las 23:59 de ese día. Dejarla vacía quita el aviso.
            </p>
        </div>

        <!-- Qué ven sus usuarios -->
        <div class="bg-sol-suave border border-sol-borde rounded-xl p-4 text-sm text-sol-medio">
            <div class="font-bold mb-1">Qué ven sus usuarios</div>
            <ul class="list-disc pl-5 space-y-1">
                <li>Desde <?= (int) $avisoDias ?> días antes: aviso “Faltan N días para su fecha de pago”.</li>
                <li>El día del pago: “Su pago vence hoy”.</li>
                <li>Pasada la fecha, y mientras no lo suspendas: aviso de pago pendiente.</li>
                <li>Suspendido: nadie inicia sesión y quien ya estaba dentro es sacado en su siguiente acción.</li>
            </ul>
            <p class="mt-2 text-xs">Tú decides a mano cuándo suspender: pasar la fecha no corta el servicio por sí solo.</p>
        </div>
    <?php endif; ?>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
