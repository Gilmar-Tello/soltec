<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
        <a href="<?= URL_BASE ?>/?url=clientes/index" class="text-green-700 font-bold text-xl">←</a>
        <div>
            <h2 class="text-2xl font-extrabold text-green-800">📋 Estado de Cuenta</h2>
            <p class="text-gray-500 text-sm"><?= htmlspecialchars($cliente['nombre']) ?></p>
        </div>
    </div>
    <button onclick="abrirPdf('<?= URL_BASE ?>/?url=clientes/pdfCuenta/<?= $cliente['id'] ?>')"
            class="bg-red-500 hover:bg-red-600 text-white font-bold px-4 py-2 rounded-xl text-sm transition">
        🖨️ Imprimir Cuenta
    </button>
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

<!-- Resumen -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-4 text-center">
        <div class="text-xs text-gray-400 mb-1">Teléfono</div>
        <div class="font-bold text-gray-700"><?= htmlspecialchars($cliente['telefono'] ?? '—') ?></div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center">
        <div class="text-xs text-gray-400 mb-1">NIT</div>
        <div class="font-bold text-gray-700 font-mono text-sm"><?= htmlspecialchars($cliente['nit'] ?? 'CF') ?></div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center">
        <div class="text-xs text-gray-400 mb-1">Límite de crédito</div>
        <div class="font-bold text-green-700">
            <?= $cliente['limite_credito'] > 0 ? 'Q' . number_format($cliente['limite_credito'], 2) : 'Sin crédito' ?>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4 text-center">
        <div class="text-xs text-gray-400 mb-1">Deuda total</div>
        <div class="font-bold text-2xl <?= $deudaTotal > 0 ? 'text-red-600' : 'text-green-600' ?>">
            Q<?= number_format($deudaTotal, 2) ?>
        </div>
    </div>
</div>

<!-- Saldo a favor -->
<div class="bg-white rounded-2xl shadow overflow-hidden mb-5">
    <div class="px-5 py-4 bg-blue-50 border-b border-blue-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="font-bold text-blue-800">💰 Saldo a favor</div>
            <div class="text-xs text-gray-500">Monto que el cliente dejó pagado por adelantado para ir consumiendo</div>
        </div>
        <div class="text-3xl font-extrabold text-blue-700">Q<?= number_format($cliente['saldo_favor'], 2) ?></div>
    </div>
    <div class="p-4">
        <form method="POST" action="<?= URL_BASE ?>/?url=clientes/depositarSaldo/<?= $cliente['id'] ?>" class="flex flex-wrap gap-2 items-center">
            <input type="number" name="monto" step="0.01" min="0.01" placeholder="Monto a depositar (Q)" required
                   class="w-44 px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm">
            <input type="text" name="notas" placeholder="Notas (opcional)"
                   class="flex-1 min-w-[160px] px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm">
            <button type="submit" onclick="return confirm('¿Confirmás el depósito de saldo?')"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition whitespace-nowrap">
                💵 Registrar depósito
            </button>
        </form>

        <?php if (!empty($movimientosSaldo)): ?>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-gray-500">Fecha</th>
                        <th class="px-3 py-2 text-left text-gray-500">Tipo</th>
                        <th class="px-3 py-2 text-right text-gray-500">Monto</th>
                        <th class="px-3 py-2 text-right text-gray-500">Saldo resultante</th>
                        <th class="px-3 py-2 text-left text-gray-500">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($movimientosSaldo as $m): ?>
                    <tr>
                        <td class="px-3 py-2 text-gray-500 text-xs"><?= date('d/m/Y H:i', strtotime($m['creado_en'])) ?></td>
                        <td class="px-3 py-2">
                            <?php if ($m['tipo'] === 'deposito'): ?>
                                <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">💵 Depósito</span>
                            <?php elseif ($m['tipo'] === 'consumo'): ?>
                                <span class="bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded-full">🛒 Consumo</span>
                            <?php else: ?>
                                <span class="bg-gray-100 text-gray-700 text-xs font-bold px-2 py-1 rounded-full">⚙️ Ajuste</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-right font-bold <?= $m['tipo'] === 'deposito' ? 'text-green-600' : 'text-orange-600' ?>">
                            <?= $m['tipo'] === 'deposito' ? '+' : '-' ?>Q<?= number_format($m['monto'], 2) ?>
                        </td>
                        <td class="px-3 py-2 text-right text-gray-600">Q<?= number_format($m['saldo_nuevo'], 2) ?></td>
                        <td class="px-3 py-2 text-gray-500 text-xs">
                            <?php if ($m['venta_id']): ?>
                                <a href="<?= URL_BASE ?>/?url=pos/ticket/<?= $m['venta_id'] ?>" class="text-blue-600 hover:underline">Venta #<?= $m['venta_id'] ?></a>
                            <?php endif; ?>
                            <?= htmlspecialchars($m['notas'] ?? '') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Ventas pendientes con botón de abono directo -->
<?php
$ventasPendientes = array_filter($ventas, fn($v) => $v['estado_pago'] === 'pendiente');
$ventasPagadas    = array_filter($ventas, fn($v) => $v['estado_pago'] === 'pagado');
?>

<?php if (!empty($ventasPendientes)): ?>
<div class="bg-white rounded-2xl shadow overflow-hidden mb-5">
    <div class="px-5 py-4 bg-red-50 border-b border-red-100 flex items-center justify-between">
        <div class="font-bold text-red-700">⏳ Ventas pendientes de pago</div>
        <div class="text-red-600 font-extrabold">Q<?= number_format($deudaTotal, 2) ?></div>
    </div>
    <div class="divide-y divide-gray-100">
        <?php foreach ($ventasPendientes as $v): ?>
        <div class="p-4 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <span class="text-gray-400 font-mono text-xs">#<?= $v['id'] ?></span>
                    <span class="text-gray-500 text-xs"><?= date('d/m/Y H:i', strtotime($v['creado_en'])) ?></span>
                </div>
                <div class="flex gap-4 mt-1">
                    <div class="text-sm text-gray-600">Total: <b>Q<?= number_format($v['total'], 2) ?></b></div>
                    <div class="text-sm text-green-600">Abonado: <b>Q<?= number_format($v['pagado'], 2) ?></b></div>
                    <div class="text-sm text-red-600 font-extrabold">Saldo: Q<?= number_format($v['saldo_pendiente'], 2) ?></div>
                </div>
                <?php if (!empty($detallesPorVenta[$v['id']])): ?>
                <details class="mt-2">
                    <summary class="text-xs text-blue-600 cursor-pointer font-bold hover:underline">
                        🛒 Ver productos (<?= count($detallesPorVenta[$v['id']]) ?>)
                    </summary>
                    <div class="mt-2 bg-gray-50 rounded-xl p-2 space-y-1">
                        <?php foreach ($detallesPorVenta[$v['id']] as $d): ?>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-600"><?= htmlspecialchars($d['producto_nombre']) ?> — <?= number_format($d['cantidad'], 2) ?> <?= $d['unidad'] ?> x Q<?= number_format($d['precio_unitario'], 2) ?></span>
                            <span class="font-bold text-gray-700">Q<?= number_format($d['subtotal'], 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php endif; ?>
            </div>
            <!-- Formulario de abono directo -->
            <form method="POST" action="<?= URL_BASE ?>/?url=pos/abonar/<?= $v['id'] ?>"
                  class="flex gap-2 items-center">
                <input type="hidden" name="notas" value="Abono desde cuenta del cliente">
                <input type="number" name="monto" step="0.01" min="0.01"
                       max="<?= $v['saldo_pendiente'] ?>"
                       placeholder="Monto (Q)"
                       value="<?= $v['saldo_pendiente'] ?>"
                       class="w-32 px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                <button type="submit"
                        onclick="return confirm('¿Registrar abono?')"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition whitespace-nowrap">
                    ✅ Abonar
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="bg-green-50 border border-green-200 rounded-2xl p-5 mb-5 text-center text-green-700 font-bold">
    ✅ Este cliente no tiene deudas pendientes.
</div>
<?php endif; ?>

<!-- Historial de ventas pagadas -->
<?php if (!empty($ventasPagadas)): ?>
<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 font-bold text-gray-600">
        ✅ Historial de ventas pagadas
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-gray-500">#</th>
                    <th class="px-4 py-2 text-left text-gray-500">Fecha</th>
                    <th class="px-4 py-2 text-right text-gray-500">Total</th>
                    <th class="px-4 py-2 text-center text-gray-500">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($ventasPagadas as $v): ?>
                <tr class="hover:bg-green-50">
                    <td class="px-4 py-3 text-gray-400 font-mono text-xs">#<?= $v['id'] ?></td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= date('d/m/Y H:i', strtotime($v['creado_en'])) ?></td>
                    <td class="px-4 py-3 text-right font-bold text-gray-700">Q<?= number_format($v['total'], 2) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ Pagado</span>
                    </td>
                </tr>
                <?php if (!empty($detallesPorVenta[$v['id']])): ?>
                <tr>
                    <td colspan="4" class="px-4 pb-3 bg-white">
                        <details>
                            <summary class="text-xs text-blue-600 cursor-pointer font-bold hover:underline">
                                🛒 Ver productos (<?= count($detallesPorVenta[$v['id']]) ?>)
                            </summary>
                            <div class="mt-2 bg-gray-50 rounded-xl p-2 space-y-1">
                                <?php foreach ($detallesPorVenta[$v['id']] as $d): ?>
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-600"><?= htmlspecialchars($d['producto_nombre']) ?> — <?= number_format($d['cantidad'], 2) ?> <?= $d['unidad'] ?> x Q<?= number_format($d['precio_unitario'], 2) ?></span>
                                    <span class="font-bold text-gray-700">Q<?= number_format($d['subtotal'], 2) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>

<!-- Modal PDF -->
<div id="modalPdf" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
            <span class="font-bold text-gray-700">Estado de cuenta — <?= htmlspecialchars($cliente['nombre']) ?></span>
            <div class="flex items-center gap-3">
                <button onclick="imprimirIframe()"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition">
                    🖨️ Imprimir
                </button>
                <button onclick="cerrarPdf()" class="text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
            </div>
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
function imprimirIframe() {
    const frame = document.getElementById('iframePdf');
    frame.contentWindow.focus();
    frame.contentWindow.print();
}
document.getElementById('modalPdf').addEventListener('click', function(e) {
    if (e.target === this) cerrarPdf();
});
</script>