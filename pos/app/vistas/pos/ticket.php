<?php
// Direccion, telefono, NIT y logo: los de la sucursal donde se hizo la venta (o los del negocio)
$config = configEfectiva((int) ($sucursalTicket ?? sucursalTrabajo()));
include RAIZ . '/app/vistas/plantillas/header.php';
?>

<style>
@page {
    size: 80mm auto;
    margin: 0;
}
@media print {
    * { visibility: hidden; margin: 0; padding: 0; }
    .ticket, .ticket * { visibility: visible; }
    .ticket {
        position: fixed !important;
        top: 0; left: 0;
        width: 72mm !important;
        max-width: 72mm !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 4mm !important;
        font-size: 11pt !important;
        font-family: Arial, Helvetica, sans-serif !important;
        font-weight: 700 !important;
        line-height: 1.35 !important;
        color: #000 !important;
        background: #fff !important;
    }
    .ticket * {
        font-family: Arial, Helvetica, sans-serif !important;
        font-weight: 700 !important;
        color: #000 !important;
    }
    .ticket .text-xl  { font-size: 15pt !important; }
    .ticket .text-base { font-size: 11.5pt !important; }
    .ticket .text-xs  { font-size: 9.5pt !important; }
    .ticket .text-lg  { font-size: 13pt !important; }
}
</style>

<div class="max-w-sm mx-auto">

    <!-- Acciones (no se imprimen) -->
    <div class="no-print flex gap-3 mb-5 flex-wrap">
        <a href="<?= URL_BASE ?>/?url=pos/venta"
           class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl text-sm transition">
            ➕ Nueva Venta
        </a>
        <button onclick="window.print()"
                class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-5 py-2 rounded-xl text-sm transition">
            🖨️ Imprimir
        </button>
        <a href="<?= URL_BASE ?>/?url=pos/historial"
           class="bg-blue-100 hover:bg-blue-200 text-blue-700 font-bold px-5 py-2 rounded-xl text-sm transition">
            📋 Historial
        </a>
        <?php if ($_SESSION['usuario_rol_id'] == 1 && !$venta['anulada']): ?>
        <button onclick="document.getElementById('modalAnular').classList.remove('hidden')"
                class="bg-red-100 hover:bg-red-200 text-red-700 font-bold px-5 py-2 rounded-xl text-sm transition ml-auto">
            🗑️ Anular Venta
        </button>
        <?php endif; ?>
    </div>

    <?php if ($venta['anulada']): ?>
        <div class="bg-red-50 border-2 border-red-300 text-red-800 rounded-xl p-4 mb-4 no-print">
            <div class="font-extrabold">❌ Esta venta fue anulada</div>
            <div class="text-sm mt-1">Motivo: <?= htmlspecialchars($venta['anulada_motivo']) ?></div>
            <div class="text-xs text-red-600 mt-1">
                Por <?= htmlspecialchars($venta['anulada_por_nombre'] ?? '—') ?> el <?= date('d/m/Y H:i', strtotime($venta['anulada_en'])) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal anular venta -->
    <div id="modalAnular" class="no-print fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
            <h3 class="font-extrabold text-gray-800 text-lg mb-2">🗑️ Anular Venta #<?= $venta['id'] ?></h3>
            <p class="text-sm text-gray-500 mb-4">
                Los productos vendidos volverán al stock<?= $venta['tipo_pago']==='saldo_cliente' ? ' y el monto se reintegrará al saldo del cliente' : '' ?>.
                Esta acción queda registrada con tu usuario y no se puede deshacer.
            </p>
            <form method="POST" action="<?= URL_BASE ?>/?url=pos/anularVenta/<?= $venta['id'] ?>">
                <label class="block text-sm font-700 text-gray-600 mb-1">Motivo de la anulación <span class="text-red-500">*</span></label>
                <textarea name="motivo" rows="3" required
                          placeholder="Ej: Cliente devolvió el producto por defecto de fábrica"
                          class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none text-sm resize-none mb-4"></textarea>
                <div class="flex gap-3">
                    <button type="submit"
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded-xl text-sm transition">
                        ✅ Confirmar Anulación
                    </button>
                    <button type="button" onclick="document.getElementById('modalAnular').classList.add('hidden')"
                            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 rounded-xl text-sm transition">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['exito'])): ?>
        <div class="bg-green-50 border border-green-300 text-green-800 rounded-xl p-4 mb-4 no-print">
            ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
        </div>
        <?php unset($_SESSION['exito']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-4 no-print">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Ticket -->
    <div class="ticket bg-white rounded-2xl shadow p-6 font-sans text-sm">
        <?php if ($venta['anulada']): ?>
        <div class="text-center border-2 border-red-500 text-red-600 font-extrabold py-2 mb-3 rounded-lg">
            ❌ VENTA ANULADA
        </div>
        <?php endif; ?>
        <!-- Encabezado -->
        <div class="text-center border-b-2 border-dashed border-gray-300 pb-4 mb-4">
            <?php if ($config['logo']): ?>
                <img src="<?= URL_BASE ?>/<?= htmlspecialchars($config['logo']) ?>" alt="Logo"
                     class="w-14 h-14 object-cover rounded-xl mx-auto mb-2">
            <?php else: ?>
                <div class="text-2xl mb-1">🏪</div>
            <?php endif; ?>
            <?php // Venta de una sucursal: solo el nombre de la sucursal. Venta de la Principal: el nombre del negocio. ?>
            <div class="text-xl font-extrabold"><?= htmlspecialchars(!empty($config['nombre_sucursal']) ? $config['nombre_sucursal'] : $config['nombre_negocio']) ?></div>
            <?php if ($config['direccion']): ?>
                <div class="text-xs text-gray-500"><?= htmlspecialchars($config['direccion']) ?></div>
            <?php endif; ?>
            <?php if ($config['telefono']): ?>
                <div class="text-xs text-gray-500">Tel: <?= htmlspecialchars($config['telefono']) ?></div>
            <?php endif; ?>
            <?php if ($config['nit']): ?>
                <div class="text-xs text-gray-500">NIT: <?= htmlspecialchars($config['nit']) ?></div>
            <?php endif; ?>
            <div class="text-xs text-gray-400 mt-1"><?= date('d/m/Y H:i', strtotime($venta['creado_en'])) ?></div>
            <div class="text-xs text-gray-400">Le atendió: <?= htmlspecialchars($venta['cajero']) ?></div>
        </div>

        <!-- Cliente -->
        <?php if ($venta['cliente_nombre']): ?>
        <div class="mb-3 text-xs">
            <div><span class="text-gray-500">Cliente:</span> <?= htmlspecialchars($venta['cliente_nombre']) ?></div>
            <?php if (!empty($venta['cliente_telefono'])): ?>
            <div class="text-gray-400">Tel: <?= htmlspecialchars($venta['cliente_telefono']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Productos -->
        <div class="border-b border-dashed border-gray-300 pb-3 mb-3">
            <?php foreach ($detalle as $d): ?>
            <div class="flex justify-between mb-1">
                <div class="flex-1">
                    <div class="text-xs font-bold"><?= htmlspecialchars($d['producto_nombre']) ?></div>
                    <div class="text-xs text-gray-500"><?= number_format($d['cantidad'],2) ?> x Q<?= number_format($d['precio_unitario'],2) ?></div>
                </div>
                <div class="font-bold text-xs">Q<?= number_format($d['subtotal'],2) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Totales -->
        <div class="space-y-1 text-xs border-b border-dashed border-gray-300 pb-3 mb-3">
            <div class="flex justify-between">
                <span class="text-gray-500">Subtotal</span>
                <span>Q<?= number_format($venta['subtotal'],2) ?></span>
            </div>
            <?php if ($venta['descuento'] > 0): ?>
            <div class="flex justify-between text-red-500">
                <span>Descuento</span>
                <span>-Q<?= number_format($venta['descuento'],2) ?></span>
            </div>
            <?php endif; ?>
            <div class="flex justify-between font-extrabold text-base">
                <span>TOTAL</span>
                <span>Q<?= number_format($venta['total'],2) ?></span>
            </div>
            <?php if ($venta['tipo_pago'] !== 'credito'): ?>
            <div class="flex justify-between text-gray-500">
                <span>Pago (<?= ['efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','saldo_cliente'=>'Saldo cliente'][$venta['tipo_pago']] ?? ucfirst($venta['tipo_pago']) ?>)</span>
                <span>Q<?= number_format($venta['monto_recibido'],2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($venta['cambio'] > 0): ?>
            <div class="flex justify-between text-green-600 font-bold">
                <span>Cambio</span>
                <span>Q<?= number_format($venta['cambio'],2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($venta['tipo_pago'] === 'credito'): ?>
            <div class="flex justify-between text-red-600 font-bold">
                <span>Saldo pendiente</span>
                <span>Q<?= number_format($venta['saldo_pendiente'],2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($venta['tipo_pago'] === 'saldo_cliente' && $saldoClienteRestante !== null): ?>
            <div class="flex justify-between text-blue-600 font-bold">
                <span>Saldo restante del cliente</span>
                <span>Q<?= number_format($saldoClienteRestante, 2) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Abonos registrados -->
        <?php if ($venta['tipo_pago'] === 'credito' && !empty($abonos)): ?>
        <?php $totalAbonado = array_sum(array_column($abonos, 'monto')); ?>
        <div class="border-b border-dashed border-gray-300 pb-3 mb-3">
            <div class="text-xs font-bold text-gray-600 mb-1">Abonos registrados:</div>
            <?php foreach ($abonos as $a): ?>
            <div class="flex justify-between text-xs mb-0.5">
                <span class="text-gray-500"><?= date('d/m/Y H:i', strtotime($a['creado_en'])) ?></span>
                <span class="font-bold text-green-600">Q<?= number_format($a['monto'], 2) ?></span>
            </div>
            <?php endforeach; ?>
            <div class="flex justify-between text-xs font-extrabold border-t border-gray-200 pt-1 mt-1">
                <span>Total abonado</span>
                <span class="text-green-700">Q<?= number_format($totalAbonado, 2) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Estado -->
        <div class="text-center mb-3">
            <?php if ($venta['estado_pago'] === 'pagado'): ?>
                <span class="bg-green-100 text-green-700 font-bold px-3 py-1 rounded-full text-xs">✅ PAGADO</span>
            <?php else: ?>
                <span class="bg-red-100 text-red-700 font-bold px-3 py-1 rounded-full text-xs">⏳ CRÉDITO PENDIENTE</span>
            <?php endif; ?>
        </div>

        <!-- Nota -->
        <?php if (!empty($venta['notas']) && empty($abonos)): ?>
        <div class="border-t border-dashed border-gray-300 pt-3 mb-3 text-xs text-gray-600">
            <b>Nota:</b> <?= htmlspecialchars($venta['notas']) ?>
        </div>
        <?php endif; ?>

        <div class="text-center text-xs text-gray-400">
            ¡Gracias por su compra!
        </div>
    </div>

    <!-- Abonar a crédito -->
    <?php if ($venta['tipo_pago'] === 'credito' && $venta['estado_pago'] === 'pendiente' && !$venta['anulada']): ?>
    <div class="no-print bg-white rounded-2xl shadow p-5 mt-5">
        <h3 class="font-bold text-gray-700 mb-3">💳 Registrar Abono</h3>
        <form method="POST" action="<?= URL_BASE ?>/?url=pos/abonar/<?= $venta['id'] ?>" class="flex gap-3">
            <input type="number" name="monto" step="0.01" min="0.01"
                   placeholder="Monto (Q)"
                   class="flex-1 px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
            <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition">
                ✅ Abonar
            </button>
        </form>
    </div>
    <?php endif; ?>

</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>