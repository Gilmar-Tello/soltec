<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>
<?php
$etiquetasEstado = [
    'pendiente'  => ['Pendiente',  'bg-orange-100 text-orange-700'],
    'en_gestion' => ['En gestión', 'bg-sky-100 text-sky-700'],
    'cobrado'    => ['Cobrado',    'bg-emerald-100 text-emerald-700'],
    'perdonado'  => ['Perdonado',  'bg-slate-100 text-slate-600'],
    'repuesto'   => ['Repuesto',   'bg-teal-100 text-teal-700'],
];
[$textoEstado, $claseEstado] = $etiquetasEstado[$incidencia['estado']] ?? [$incidencia['estado'], 'bg-slate-100 text-slate-600'];
$abierta    = in_array($incidencia['estado'], ['pendiente', 'en_gestion'], true);
$textoTipo  = $incidencia['tipo'] === 'perdido' ? '(faltantes)' : '(con daño)';
$telefono   = preg_replace('/\D/', '', (string) $incidencia['telefono']);
?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-extrabold text-teal-800">⚠️ Incidencia #<?= (int) $incidencia['id'] ?></h2>
    <a href="<?= URL_BASE ?>/?url=incidencias/index" class="text-teal-700 font-bold text-sm hover:underline">← Volver</a>
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

<div class="grid lg:grid-cols-2 gap-6">

    <!-- Lo que reportó el recolector -->
    <div class="bg-white rounded-2xl shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-extrabold text-slate-800">Reporte</h3>
            <span class="text-xs font-bold px-2 py-1 rounded-full <?= $claseEstado ?>"><?= $textoEstado ?></span>
        </div>
        <dl class="text-sm space-y-3">
            <div><dt class="text-slate-400">Producto</dt>
                 <dd class="font-bold text-slate-800"><?= (int) $incidencia['cantidad'] ?> <?= htmlspecialchars($incidencia['producto_nombre']) ?> <?= $textoTipo ?></dd></div>
            <div><dt class="text-slate-400">Reserva</dt>
                 <dd><a href="<?= URL_BASE ?>/?url=reservas/detalle/<?= (int) $incidencia['reserva_id'] ?>"
                        class="text-teal-700 font-bold hover:underline">#<?= (int) $incidencia['reserva_id'] ?></a>
                     — <?= htmlspecialchars($incidencia['sucursal_nombre']) ?>, uso el <?= fechaCorta($incidencia['fecha_uso']) ?>
                     <div class="text-xs text-slate-400">Las fotos de entrega y devolución están en el detalle de la reserva.</div></dd></div>
            <div><dt class="text-slate-400">Reportada</dt>
                 <dd><?= fechaHora($incidencia['fecha_registro']) ?> por <?= htmlspecialchars($incidencia['reporta_nombre']) ?></dd></div>
            <div><dt class="text-slate-400">Observaciones</dt>
                 <dd class="whitespace-pre-line"><?= htmlspecialchars($incidencia['observaciones'] ?? '') ?: '—' ?></dd></div>
        </dl>
    </div>

    <!-- Cliente y contacto -->
    <div class="bg-white rounded-2xl shadow p-6">
        <h3 class="font-extrabold text-slate-800 mb-4">Cliente</h3>
        <p class="font-bold text-slate-800"><?= htmlspecialchars($incidencia['nombre_completo']) ?></p>
        <p class="text-sm text-slate-500 mb-4"><?= htmlspecialchars($incidencia['telefono'] ?? '') ?: 'Sin teléfono' ?></p>

        <?php if ($telefono !== ''): ?>
        <div class="flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars($urlWhatsapp) ?>" target="_blank" rel="noopener"
               class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-4 py-2.5 rounded-xl text-sm transition">
                💬 Escribir por WhatsApp
            </a>
            <a href="tel:<?= htmlspecialchars($telefono) ?>"
               class="bg-sky-500 hover:bg-sky-600 text-white font-bold px-4 py-2.5 rounded-xl text-sm transition">
                📞 Llamar
            </a>
        </div>
        <?php endif; ?>

        <?php if ($incidencia['estado'] === 'pendiente'): ?>
        <form method="POST" action="<?= URL_BASE ?>/?url=incidencias/gestionar/<?= (int) $incidencia['id'] ?>" class="mt-4">
            <button type="submit" class="text-sm font-bold text-sky-700 hover:underline">
                Ya contacté al cliente → marcar en gestión
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($abierta): ?>
<!-- Resolver -->
<form method="POST" action="<?= URL_BASE ?>/?url=incidencias/resolver/<?= (int) $incidencia['id'] ?>"
      class="bg-white rounded-2xl shadow p-6 mt-6"
      onsubmit="return confirm('Una incidencia resuelta ya no se puede modificar. ¿Continuar?');">
    <h3 class="font-extrabold text-slate-800 mb-4">¿Cómo se resolvió?</h3>

    <div class="grid sm:grid-cols-3 gap-3 mb-4">
        <?php foreach (['cobrado' => '💵 Se cobró', 'perdonado' => '🤝 Se perdonó', 'repuesto' => '📦 El cliente repuso'] as $valor => $texto): ?>
            <label class="border-2 border-slate-200 rounded-xl p-3 cursor-pointer has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50">
                <input type="radio" name="resolucion" value="<?= $valor ?>" required class="mr-2"
                       onchange="document.getElementById('campoMonto').classList.toggle('hidden', this.value !== 'cobrado');
                                 document.getElementById('monto').required = (this.value === 'cobrado');">
                <span class="font-bold text-sm"><?= $texto ?></span>
            </label>
        <?php endforeach; ?>
    </div>

    <div id="campoMonto" class="hidden mb-4">
        <label for="monto" class="block text-sm font-bold text-slate-600 mb-1">Monto cobrado (Q)</label>
        <input type="number" name="monto" id="monto" step="0.01" min="0.01"
               class="w-full sm:w-48 border border-slate-300 rounded-xl px-3 py-2">
    </div>

    <p class="text-xs text-slate-500 mb-4">
        Si el cliente repone, las <?= (int) $incidencia['cantidad'] ?> unidades vuelven a sumarse al inventario de
        "<?= htmlspecialchars($incidencia['producto_nombre']) ?>".
    </p>

    <label for="nota_resolucion" class="block text-sm font-bold text-slate-600 mb-1">Qué se acordó con el cliente</label>
    <textarea name="nota_resolucion" id="nota_resolucion" rows="3" maxlength="1000"
              class="w-full border border-slate-300 rounded-xl px-3 py-2 mb-4"
              placeholder="Ej.: pagó en efectivo al recoger, trae 3 sillas nuevas el viernes..."></textarea>

    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-6 py-2.5 rounded-xl transition">
        Cerrar incidencia
    </button>
</form>

<?php else: ?>
<!-- Resolución registrada -->
<div class="bg-white rounded-2xl shadow p-6 mt-6">
    <h3 class="font-extrabold text-slate-800 mb-4">Resolución</h3>
    <dl class="text-sm space-y-3">
        <div><dt class="text-slate-400">Resultado</dt>
             <dd class="font-bold"><?= $textoEstado ?>
                 <?php if ($incidencia['estado'] === 'cobrado'): ?>
                     — Q<?= number_format((float) $incidencia['monto_cobrado'], 2) ?>
                 <?php endif; ?></dd></div>
        <div><dt class="text-slate-400">Resuelta</dt>
             <dd><?= fechaHora($incidencia['fecha_resolucion']) ?> por <?= htmlspecialchars($incidencia['resuelve_nombre'] ?? '—') ?></dd></div>
        <div><dt class="text-slate-400">Acuerdo</dt>
             <dd class="whitespace-pre-line"><?= htmlspecialchars($incidencia['nota_resolucion'] ?? '') ?: '—' ?></dd></div>
    </dl>
</div>
<?php endif; ?>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
