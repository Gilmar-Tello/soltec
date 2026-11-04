<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h2 class="text-2xl font-extrabold text-green-800">🏧 <?= htmlspecialchars($tituloPagina) ?></h2>
    <button onclick="abrirPdf('<?= URL_BASE ?>/?url=pos/pdfHistorialCaja&desde=<?= $desde ?>&hasta=<?= $hasta ?>')"
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition">
        📄 Generar PDF
    </button>
</div>

<!-- Filtro de fechas -->
<form method="GET" class="bg-white rounded-2xl shadow p-4 mb-6 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="url" value="pos/historialCaja">
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
</form>

<!-- Tabla -->
<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">Usuario</th>
                    <th class="px-4 py-3 text-left">Apertura</th>
                    <th class="px-4 py-3 text-left">Cierre</th>
                    <th class="px-4 py-3 text-right">Monto inicial</th>
                    <th class="px-4 py-3 text-right">💵 Efectivo</th>
                    <th class="px-4 py-3 text-right">💳 Tarjeta</th>
                    <th class="px-4 py-3 text-right">📒 Crédito</th>
                    <th class="px-4 py-3 text-right">Total ventas</th>
                    <th class="px-4 py-3 text-right">Monto final</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($sesiones)): ?>
                    <tr><td colspan="10" class="px-4 py-10 text-center text-gray-400">No hay sesiones de caja en este período.</td></tr>
                <?php else: ?>
                    <?php foreach ($sesiones as $s):
                        $totalGeneral = $s['total_efectivo'] + $s['total_tarjeta'] + $s['total_credito'];
                    ?>
                    <tr class="hover:bg-green-50 transition">
                        <td class="px-4 py-3 font-bold text-gray-800"><?= htmlspecialchars($s['usuario_nombre']) ?></td>
                        <td class="px-4 py-3 text-gray-600 text-xs"><?= date('d/m/Y H:i', strtotime($s['abierta_en'])) ?></td>
                        <td class="px-4 py-3 text-gray-600 text-xs">
                            <?= $s['cerrada_en'] ? date('d/m/Y H:i', strtotime($s['cerrada_en'])) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-700">Q<?= number_format($s['monto_inicial'], 2) ?></td>
                        <td class="px-4 py-3 text-right text-green-600 font-bold">Q<?= number_format($s['total_efectivo'], 2) ?></td>
                        <td class="px-4 py-3 text-right text-blue-600 font-bold">Q<?= number_format($s['total_tarjeta'], 2) ?></td>
                        <td class="px-4 py-3 text-right text-orange-600 font-bold">Q<?= number_format($s['total_credito'], 2) ?></td>
                        <td class="px-4 py-3 text-right font-extrabold text-gray-800">Q<?= number_format($totalGeneral, 2) ?></td>
                        <td class="px-4 py-3 text-right text-gray-700">
                            <?= $s['monto_final'] !== null ? 'Q'.number_format($s['monto_final'], 2) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($s['estado'] === 'abierta'): ?>
                                <span class="bg-yellow-100 text-yellow-700 text-xs font-bold px-2 py-1 rounded-full">🟢 Abierta</span>
                            <?php else: ?>
                                <span class="bg-gray-100 text-gray-600 text-xs font-bold px-2 py-1 rounded-full">🔒 Cerrada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>