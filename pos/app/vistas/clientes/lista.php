<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-green-800">👥 Clientes</h2>
        <p class="text-gray-500 text-sm mt-1"><?= count($clientes) ?> clientes registrados</p>
    </div>
    <div class="flex gap-3">
        <button onclick="abrirPdf('<?= URL_BASE ?>/?url=clientes/pdfDeudores')"
                class="bg-red-500 hover:bg-red-600 text-white font-bold px-4 py-3 rounded-xl transition text-sm">
            📄 Reporte Deudores
        </button>
        <a href="<?= URL_BASE ?>/?url=clientes/nuevo"
           class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-3 rounded-xl transition text-sm text-center">
            ➕ Nuevo Cliente
        </a>
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

<!-- Alertas -->
<?php if (isset($_SESSION['exito'])): ?>
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-xl p-4 mb-5">
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

<!-- Buscador -->
<form method="GET" class="bg-white rounded-2xl shadow p-4 mb-6 flex gap-3">
    <input type="hidden" name="url" value="clientes/index">
    <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>"
           placeholder="🔍 Buscar por nombre, teléfono o NIT..."
           class="flex-1 px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
    <button type="submit"
            class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl text-sm transition">
        Buscar
    </button>
    <?php if ($busqueda): ?>
        <a href="<?= URL_BASE ?>/?url=clientes/index"
           class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-5 py-2 rounded-xl text-sm transition">
            Limpiar
        </a>
    <?php endif; ?>
</form>

<!-- Tabla -->
<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">Cliente</th>
                    <th class="px-4 py-3 text-left">Teléfono</th>
                    <th class="px-4 py-3 text-left">NIT</th>
                    <th class="px-4 py-3 text-right">Límite Crédito</th>
                    <th class="px-4 py-3 text-right">Deuda Actual</th>
                    <th class="px-4 py-3 text-right">Saldo a favor</th>
                    <th class="px-4 py-3 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($clientes)): ?>
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay clientes registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($clientes as $c):
                        $tieneDeuda   = $c['deuda_total'] > 0;
                        $excedeLimit  = $c['limite_credito'] > 0 && $c['deuda_total'] > $c['limite_credito'];
                    ?>
                    <tr class="hover:bg-green-50 transition <?= $excedeLimit ? 'bg-red-50' : '' ?>">
                        <td class="px-4 py-3">
                            <div class="font-bold text-gray-800"><?= htmlspecialchars($c['nombre']) ?></div>
                            <?php if ($c['direccion']): ?>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($c['direccion']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs"><?= htmlspecialchars($c['nit'] ?? 'CF') ?></td>
                        <td class="px-4 py-3 text-right text-gray-700">
                            <?= $c['limite_credito'] > 0 ? 'Q' . number_format($c['limite_credito'], 2) : '<span class="text-gray-300 text-xs">Sin crédito</span>' ?>
                        </td>
                        <td class="px-4 py-3 text-right font-bold <?= $tieneDeuda ? ($excedeLimit ? 'text-red-600' : 'text-orange-500') : 'text-gray-400' ?>">
                            <?= $tieneDeuda ? 'Q' . number_format($c['deuda_total'], 2) : '—' ?>
                            <?php if ($excedeLimit): ?>
                                <div class="text-xs text-red-500">⚠️ Excede límite</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right font-bold <?= $c['saldo_favor'] > 0 ? 'text-blue-600' : 'text-gray-300' ?>">
                            <?= $c['saldo_favor'] > 0 ? 'Q' . number_format($c['saldo_favor'], 2) : '—' ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-center gap-2 flex-wrap">
                                <a href="<?= URL_BASE ?>/?url=clientes/cuenta/<?= $c['id'] ?>"
                                   class="bg-green-100 hover:bg-green-200 text-green-700 text-xs font-bold px-3 py-1 rounded-lg transition">
                                    📋 Cuenta
                                </a>
                                <a href="<?= URL_BASE ?>/?url=clientes/editar/<?= $c['id'] ?>"
                                   class="bg-blue-100 hover:bg-blue-200 text-blue-700 text-xs font-bold px-3 py-1 rounded-lg transition">
                                    ✏️ Editar
                                </a>
                                <a href="<?= URL_BASE ?>/?url=clientes/desactivar/<?= $c['id'] ?>"
                                   onclick="return confirm('¿Desactivar este cliente?')"
                                   class="bg-red-100 hover:bg-red-200 text-red-700 text-xs font-bold px-3 py-1 rounded-lg transition">
                                    🚫 Desactivar
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