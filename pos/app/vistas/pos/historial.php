<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h2 class="text-2xl font-extrabold text-green-800">📋 <?= htmlspecialchars($tituloPagina) ?><?= $verAnuladas ? ' — Anuladas' : '' ?></h2>
    <div class="flex gap-2 flex-wrap">
        <?php if ($_SESSION['usuario_rol_id'] == 1): ?>
        <a href="<?= URL_BASE ?>/?url=pos/historial&desde=<?= $desde ?>&hasta=<?= $hasta ?><?= $verAnuladas ? '' : '&anuladas=1' ?>"
           class="<?= $verAnuladas ? 'bg-gray-600 hover:bg-gray-700' : 'bg-red-100 hover:bg-red-200 text-red-700' ?> font-bold px-4 py-2 rounded-xl text-sm transition <?= $verAnuladas ? 'text-white' : '' ?>">
            <?= $verAnuladas ? '↩️ Ver ventas activas' : '🗑️ Ver ventas anuladas' ?>
        </a>
        <?php endif; ?>
        <button onclick="abrirPdf('<?= URL_BASE ?>/?url=pos/pdfHistorial&desde=<?= $desde ?>&hasta=<?= $hasta ?>')"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition">
            📄 Generar PDF
        </button>
    </div>
</div>

<!-- Modal PDF -->
<div id="modalPdf" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
            <span class="font-bold text-gray-700">Vista previa del reporte</span>
            <button onclick="cerrarPdf()" class="text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
        </div>
        <iframe id="iframePdf" src="" class="flex-1 w-full rounded-b-2xl border-0"></iframe>
    </div>
</div>
<script>
function abrirPdf(url) {
    document.getElementById('iframePdf').src = url;
    document.getElementById('modalPdf').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function cerrarPdf() {
    document.getElementById('modalPdf').classList.add('hidden');
    document.getElementById('iframePdf').src = '';
    document.body.style.overflow = '';
}
document.getElementById('modalPdf').addEventListener('click', function(e) {
    if (e.target === this) cerrarPdf();
});
</script>

<!-- Filtro de fechas -->
<form method="GET" class="bg-white rounded-2xl shadow p-4 mb-6 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="url" value="pos/historial">
    <div>
        <label class="block text-xs text-gray-500 mb-1">Desde</label>
        <input type="date" name="desde" value="<?= $desde ?>"
               class="px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Hasta</label>
        <input type="date" name="hasta" value="<?= $hasta ?>"
               class="px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
    </div>
    <button type="submit"
            class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl text-sm transition">
        Filtrar
    </button>
    <div class="ml-auto text-right">
        <div class="text-xs text-gray-400">Total del período</div>
        <div class="text-xl font-extrabold text-green-700">Q<?= number_format($totalVentas, 2) ?></div>
    </div>
</form>

<!-- Tabla -->
<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Fecha</th>
                    <th class="px-4 py-3 text-left">Cliente</th>
                    <th class="px-4 py-3 text-left">Cajero</th>
                    <th class="px-4 py-3 text-center">Pago</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <?php if ($verAnuladas): ?>
                        <th class="px-4 py-3 text-left">Motivo de anulación</th>
                    <?php else: ?>
                        <th class="px-4 py-3 text-center">Estado</th>
                    <?php endif; ?>
                    <th class="px-4 py-3 text-center">Ver</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($ventas)): ?>
                    <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No hay ventas en este período.</td></tr>
                <?php else: ?>
                    <?php foreach ($ventas as $v): ?>
                    <tr class="hover:bg-green-50 transition">
                        <td class="px-4 py-3 text-gray-400 font-mono">#<?= $v['id'] ?></td>
                        <td class="px-4 py-3 text-gray-500 text-xs"><?= date('d/m/Y H:i', strtotime($v['creado_en'])) ?></td>
                        <td class="px-4 py-3 text-gray-700">
                            <?= htmlspecialchars($v['cliente_nombre'] ?? 'Consumidor Final') ?>
                            <?php if ($v['origen_registro'] === 'offline_sincronizada'): ?>
                                <span class="ml-1 text-xs" title="Sincronizada desde modo offline">📡</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($v['cajero']) ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php
                            $pagoColor = match($v['tipo_pago']) {
                                'efectivo' => 'bg-emerald-100 text-emerald-700',
                                'tarjeta'  => 'bg-blue-100 text-blue-700',
                                'credito'  => 'bg-orange-100 text-orange-700',
                                default    => ''
                            };
                            $pagoIcon = match($v['tipo_pago']) {
                                'efectivo'=>'💵','tarjeta'=>'💳','credito'=>'📒', default=>''
                            };
                            ?>
                            <span class="<?= $pagoColor ?> text-xs font-bold px-2 py-1 rounded-full">
                                <?= $pagoIcon ?> <?= ucfirst($v['tipo_pago']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-green-700">Q<?= number_format($v['total'], 2) ?></td>
                        <?php if ($verAnuladas): ?>
                        <td class="px-4 py-3 text-gray-600 text-xs">
                            <?= htmlspecialchars($v['anulada_motivo'] ?? '—') ?>
                            <div class="text-gray-400 mt-0.5">
                                Por <?= htmlspecialchars($v['anulada_por_nombre'] ?? '—') ?> · <?= $v['anulada_en'] ? date('d/m/Y H:i', strtotime($v['anulada_en'])) : '' ?>
                            </div>
                        </td>
                        <?php else: ?>
                        <td class="px-4 py-3 text-center">
                            <?php if ($v['estado_pago'] === 'pagado'): ?>
                                <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-1 rounded-full">✅ Pagado</span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full">⏳ Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td class="px-4 py-3 text-center">
                            <a href="<?= URL_BASE ?>/?url=pos/ticket/<?= $v['id'] ?>"
                               class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-1 rounded-lg transition">
                                👁️ Ver
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>