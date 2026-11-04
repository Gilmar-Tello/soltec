<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= URL_BASE ?>/?url=reservas/detalle/<?= $reserva['id'] ?>" class="text-teal-700 font-bold text-xl">←</a>
    <div>
        <h2 class="text-2xl font-extrabold text-teal-800">🔍 Inspección de bienes</h2>
        <p class="text-sm text-slate-500">Reserva #<?= $reserva['id'] ?> — <?= htmlspecialchars($reserva['nombre_completo']) ?></p>
    </div>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="bg-teal-50 border border-teal-200 rounded-xl p-4 mb-6 text-sm text-teal-800">
    <strong>Instrucciones:</strong> Para cada producto, anotá cuántas unidades regresaron dañadas y cuántas se perdieron. Las unidades en buen estado se calculan solas: lo que salió menos lo dañado y lo perdido. Solo las buenas regresan al inventario disponible; lo dañado o perdido abre una incidencia para el administrador.
</div>

<?php
    // Fotos tomadas al entregar: sirven para comparar como salio
    // el producto contra como esta regresando.
    $fotosEntrega = array_filter($fotos ?? [], fn($f) => $f['tipo'] === 'entrega');
?>
<?php if ($fotosEntrega): ?>
<div class="bg-white rounded-2xl shadow p-5 mb-6">
    <h3 class="font-bold text-slate-700 text-sm mb-1">📸 Así salió el producto</h3>
    <p class="text-xs text-slate-400 mb-3">Fotos tomadas al momento de la entrega. Tocá una para verla en grande.</p>
    <div class="flex flex-wrap gap-3">
        <?php foreach ($fotosEntrega as $f): ?>
            <div class="text-center">
                <img src="<?= urlImagen($f['ruta']) ?>" loading="lazy" alt="Foto de entrega"
                     onclick="soltecAbrirFoto(this.src, 'Al entregar — <?= htmlspecialchars(fechaHora($f['creado_en']), ENT_QUOTES) ?>')"
                     class="w-24 h-24 object-cover rounded-xl border-2 border-slate-200 cursor-zoom-in hover:ring-2 hover:ring-teal-400 transition">
                <div class="text-[10px] text-slate-400 mt-1"><?= fechaHora($f['creado_en']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<form method="POST" action="<?= URL_BASE ?>/?url=reservas/confirmardevolucion"
      enctype="multipart/form-data" data-comprimir-imagenes>
    <input type="hidden" name="reserva_id" value="<?= $reserva['id'] ?>">

    <div class="space-y-5 mb-6">
        <?php foreach ($detalle as $d): ?>
        <div class="bg-white rounded-2xl shadow p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-extrabold text-slate-800"><?= htmlspecialchars($d['producto_nombre']) ?></h3>
                    <p class="text-xs text-slate-400 mt-0.5">Cantidad reservada: <strong><?= $d['cantidad'] ?></strong></p>
                </div>
                <span class="bg-teal-100 text-teal-700 text-xs font-bold px-3 py-1 rounded-full">
                    <?= $d['cantidad'] ?> unidades
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <!-- Buenas -->
                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3">
                    <label class="block text-xs font-bold text-emerald-700 mb-1">✅ En buen estado</label>
                    <input type="number" name="inspeccion[<?= $d['inventario_id'] ?>][buena]"
                           min="0" max="<?= (int) $d['cantidad'] ?>" value="<?= (int) $d['cantidad'] ?>" readonly tabindex="-1"
                           class="w-full px-3 py-2 border border-emerald-300 rounded-lg text-sm text-center bg-emerald-100 font-bold text-emerald-800 cursor-default">
                    <p class="text-xs text-emerald-600 mt-1">Se calcula solo · regresan al inventario</p>
                </div>

                <!-- Dañadas -->
                <div class="bg-orange-50 border border-orange-200 rounded-xl p-3">
                    <label class="block text-xs font-bold text-orange-700 mb-1">⚠️ Dañadas</label>
                    <input type="number" name="inspeccion[<?= $d['inventario_id'] ?>][danada]"
                           min="0" max="<?= $d['cantidad'] ?>" value="0"
                           class="w-full px-3 py-2 border border-orange-300 rounded-lg text-sm text-center focus:outline-none focus:border-orange-500"
                           oninput="validarFila(<?= $d['inventario_id'] ?>, <?= $d['cantidad'] ?>)">
                    <p class="text-xs text-orange-600 mt-1">Se descuenta del total</p>
                </div>

                <!-- Perdidas -->
                <div class="bg-red-50 border border-red-200 rounded-xl p-3">
                    <label class="block text-xs font-bold text-red-700 mb-1">❌ Perdidas</label>
                    <input type="number" name="inspeccion[<?= $d['inventario_id'] ?>][perdida]"
                           min="0" max="<?= $d['cantidad'] ?>" value="0"
                           class="w-full px-3 py-2 border border-red-300 rounded-lg text-sm text-center focus:outline-none focus:border-red-500"
                           oninput="validarFila(<?= $d['inventario_id'] ?>, <?= $d['cantidad'] ?>)">
                    <p class="text-xs text-red-600 mt-1">Se descuenta del total</p>
                </div>
            </div>

            <!-- Alerta de suma -->
            <div id="alerta-<?= $d['inventario_id'] ?>" class="hidden bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg px-3 py-2 mb-3">
                ⚠️ Dañadas + perdidas suman más de las <strong><?= (int) $d['cantidad'] ?></strong> unidades que salieron.
            </div>

            <!-- Campos adicionales (se muestran si hay dañadas o perdidas) -->
            <div id="extras-<?= $d['inventario_id'] ?>" class="hidden space-y-3 border-t border-slate-100 pt-4 mt-2">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Motivo del daño / pérdida</label>
                    <input type="text" name="inspeccion[<?= $d['inventario_id'] ?>][motivo_dano]"
                           placeholder="Ej: Mantel quemado, silla rota..."
                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-teal-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Responsable (pérdida)</label>
                        <input type="text" name="inspeccion[<?= $d['inventario_id'] ?>][responsable]"
                               placeholder="Nombre del responsable"
                               class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Observaciones</label>
                        <input type="text" name="inspeccion[<?= $d['inventario_id'] ?>][observaciones]"
                               placeholder="Notas adicionales"
                               class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-teal-500">
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Fotos de como regresa el producto -->
    <div class="bg-white rounded-2xl shadow p-5 mb-6">
        <h3 class="font-bold text-slate-700 text-sm mb-1">📷 Foto al recibir el producto</h3>
        <p class="text-xs text-slate-400 mb-3">
            Opcional, hasta 6 fotos. Desde el celular abre la cámara directamente. Queda como respaldo del estado en que regresó.
        </p>
        <input type="file" name="fotos[]" accept="image/*" capture="environment" multiple
               onchange="soltecVistaPrevia(this, 'previewDevolucion')"
               class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl
                      file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700
                      hover:file:bg-emerald-100 cursor-pointer">
        <div id="previewDevolucion" class="flex gap-2 flex-wrap mt-3"></div>
    </div>

    <div class="flex gap-3">
        <button type="submit" id="btnConfirmar"
                class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold px-8 py-3.5 rounded-2xl transition">
            ✅ Confirmar devolución
        </button>
        <a href="<?= URL_BASE ?>/?url=reservas/detalle/<?= $reserva['id'] ?>"
           class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-6 py-3.5 rounded-2xl transition">
            Cancelar
        </a>
    </div>
</form>

<script>
function validarFila(invId, maxCant) {
    const campoBuena = document.querySelector(`input[name="inspeccion[${invId}][buena]"]`);
    const danada  = Math.max(0, parseInt(document.querySelector(`input[name="inspeccion[${invId}][danada]"]`).value)  || 0);
    const perdida = Math.max(0, parseInt(document.querySelector(`input[name="inspeccion[${invId}][perdida]"]`).value) || 0);
    const alerta  = document.getElementById(`alerta-${invId}`);
    const extras  = document.getElementById(`extras-${invId}`);

    // Las buenas son lo que queda: asi la suma siempre cuadra con lo que salio
    const buena = maxCant - danada - perdida;
    campoBuena.value = Math.max(0, buena);

    alerta.classList.toggle('hidden', buena >= 0);
    extras.classList.toggle('hidden', (danada + perdida) === 0);

    const hayError = document.querySelectorAll('[id^="alerta-"]:not(.hidden)').length > 0;
    document.getElementById('btnConfirmar').disabled = hayError;
    document.getElementById('btnConfirmar').classList.toggle('opacity-50', hayError);
    document.getElementById('btnConfirmar').classList.toggle('cursor-not-allowed', hayError);
}
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
