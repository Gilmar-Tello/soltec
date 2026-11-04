<?php
// Direccion, telefono, NIT y logo: los de la sucursal donde se hizo la cotizacion (o los del negocio)
$config = configEfectiva((int) ($cotizacion['sucursal_id'] ?? 0));
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
        <a href="<?= URL_BASE ?>/?url=cotizaciones/ver/<?= $cotizacion['id'] ?>"
           class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-5 py-2 rounded-xl text-sm transition">
            ← Volver
        </a>
        <button onclick="window.print()"
                class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl text-sm transition">
            🖨️ Imprimir
        </button>
        <a href="<?= URL_BASE ?>/?url=pos/venta"
           class="bg-blue-100 hover:bg-blue-200 text-blue-700 font-bold px-5 py-2 rounded-xl text-sm transition">
            ➕ Nueva Venta
        </a>
    </div>
    <!-- Ticket -->
    <div class="ticket bg-white rounded-2xl shadow p-6 font-sans text-sm">
        <div class="text-center border-2 border-blue-400 text-blue-700 font-extrabold py-2 mb-3 rounded-lg">
            📝 COTIZACIÓN — No es un comprobante de pago
        </div>

        <!-- Encabezado -->
        <div class="text-center border-b-2 border-dashed border-gray-300 pb-4 mb-4">
            <?php if ($config['logo']): ?>
                <img src="<?= URL_BASE ?>/<?= htmlspecialchars($config['logo']) ?>" alt="Logo"
                     class="w-14 h-14 object-cover rounded-xl mx-auto mb-2">
            <?php else: ?>
                <div class="text-2xl mb-1">🏪</div>
            <?php endif; ?>
            <?php // Cotizacion de una sucursal: solo el nombre de la sucursal. De la Principal: el nombre del negocio. ?>
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
            <div class="text-xs text-gray-400 mt-1"><?= date('d/m/Y H:i', strtotime($cotizacion['creado_en'])) ?></div>
            <div class="text-xs text-gray-400">Cotización #<?= $cotizacion['id'] ?> · Atendió: <?= htmlspecialchars($cotizacion['usuario_nombre']) ?></div>
        </div>
        <!-- Cliente -->
        <?php if ($cotizacion['cliente_nombre']): ?>
        <div class="mb-3 text-xs">
            <div><span class="text-gray-500">Cliente:</span> <?= htmlspecialchars($cotizacion['cliente_nombre']) ?></div>
            <?php if (!empty($cotizacion['cliente_telefono'])): ?>
            <div class="text-gray-400">Tel: <?= htmlspecialchars($cotizacion['cliente_telefono']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Productos -->
        <div class="border-b border-dashed border-gray-300 pb-3 mb-3">
            <?php foreach ($detalle as $d): ?>
            <div class="flex justify-between mb-1">
                <div class="flex-1">
                    <div class="text-xs font-bold"><?= htmlspecialchars($d['producto_nombre']) ?></div>
                    <div class="text-xs text-gray-500"><?= number_format($d['cantidad'],2) ?> <?= htmlspecialchars($d['unidad']) ?> x Q<?= number_format($d['precio_unitario'],2) ?></div>
                </div>
                <div class="font-bold text-xs">Q<?= number_format($d['subtotal'],2) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- Totales -->
        <div class="space-y-1 text-xs border-b border-dashed border-gray-300 pb-3 mb-3">
            <div class="flex justify-between text-gray-500">
                <span>Subtotal</span><span>Q<?= number_format($cotizacion['subtotal'],2) ?></span>
            </div>
            <?php if ($cotizacion['descuento'] > 0): ?>
            <div class="flex justify-between text-red-500">
                <span>Descuento</span><span>-Q<?= number_format($cotizacion['descuento'],2) ?></span>
            </div>
            <?php endif; ?>
            <div class="flex justify-between font-extrabold text-base">
                <span>TOTAL</span><span>Q<?= number_format($cotizacion['total'],2) ?></span>
            </div>
        </div>

        <?php if (!empty($cotizacion['notas'])): ?>
        <div class="text-xs text-gray-500 mb-3">
            <b>Nota:</b> <?= htmlspecialchars($cotizacion['notas']) ?>
        </div>
        <?php endif; ?>

        <div class="text-xs text-gray-400 text-center border-t border-dashed border-gray-300 pt-3">
            Los precios de esta cotización pueden variar sin previo aviso.<br>
            No representa una venta ni un comprobante de pago.
        </div>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
