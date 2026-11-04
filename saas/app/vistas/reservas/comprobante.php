<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante #<?= $reserva['id'] ?> — <?= htmlspecialchars($reserva['nombre_negocio']) ?></title>
    <script src="<?= URL_BASE ?>/app/public/vendor/tailwind-3.4.17.js"></script>
    <style>
        /* ====================================================
           COMPROBANTE TERMICO 80mm (AON PR-250 y similares)

           La impresora termica no dibuja letra fina ni color:
           quema puntos. Por eso el papel lleva Arial/Helvetica
           en negrita y negro puro, no Courier ni gris. El ancho
           util de un rollo de 80mm son 72mm reales.
           ==================================================== */

        body { font-family: Arial, Helvetica, sans-serif; }

        /* Vista en pantalla: se ve igual de angosto que el papel */
        .comprobante {
            width: 302px;               /* ~80mm en pantalla */
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
        }
        .tk-linea      { border-top: 1px dashed #94a3b8; margin: 6px 0; }
        .tk-titulo     { font-size: 15px; font-weight: 800; letter-spacing: .5px; }
        .tk-sub        { font-size: 11px; }
        .tk-etiqueta   { font-size: 11px; font-weight: 700; letter-spacing: .6px; }
        .tk-texto      { font-size: 12px; font-weight: 700; }
        .tk-mini       { font-size: 11px; }
        .tk-total      { font-size: 16px; font-weight: 800; }
        .tk-fila       { display: flex; justify-content: space-between; gap: 8px; }

        @media print {
            @page { size: 80mm auto; margin: 0; }

            html, body {
                width: 80mm;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }

            body * { visibility: hidden; }
            .comprobante, .comprobante * { visibility: visible; }
            .no-print { display: none !important; }

            .comprobante {
                position: absolute;
                left: 0; top: 0;
                width: 72mm !important;
                max-width: 72mm !important;
                margin: 0 !important;
                padding: 2mm 3mm !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: #fff !important;
                color: #000 !important;
                font-family: Arial, Helvetica, sans-serif !important;
                font-weight: 700 !important;
                line-height: 1.35 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Tamanos en puntos: lo que la termica imprime nitido */
            .comprobante .tk-titulo   { font-size: 13pt !important; }
            .comprobante .tk-total    { font-size: 13pt !important; }
            .comprobante .tk-texto    { font-size: 10pt !important; }
            .comprobante .tk-etiqueta { font-size: 9pt  !important; }
            .comprobante .tk-sub,
            .comprobante .tk-mini     { font-size: 9pt  !important; }

            .comprobante .tk-linea { border-top: 1px dashed #000 !important; margin: 2mm 0 !important; }
            .comprobante .tk-suave { color: #000 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen py-8 px-4">

<!-- Acciones (no se imprimen) -->
<div class="no-print max-w-sm mx-auto mb-5 flex gap-3 flex-wrap">
    <button onclick="window.print()"
            class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 py-2.5 rounded-xl text-sm transition">
        🖨️ Imprimir
    </button>

    <?php $urlWhatsapp = urlWhatsappReserva($reserva, $detalle); ?>
    <a href="<?= $urlWhatsapp ?>" target="_blank"
       class="bg-green-500 hover:bg-green-600 text-white font-bold px-5 py-2.5 rounded-xl text-sm transition">
        💬 Enviar por WhatsApp
    </a>

    <a href="<?= URL_BASE ?>/?url=reservas/detalle/<?= $reserva['id'] ?>"
       class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-5 py-2.5 rounded-xl text-sm transition">
        ← Volver
    </a>
</div>

<!-- ── Ticket ───────────────────────────────────────────── -->
<div class="comprobante mx-auto bg-white rounded-xl shadow-lg p-5">

    <!-- Encabezado -->
    <div style="text-align:center;">
        <div class="tk-titulo"><?= htmlspecialchars(mb_strtoupper($reserva['nombre_negocio'])) ?></div>
        <?php if ($reserva['neg_direccion']): ?>
            <div class="tk-sub tk-suave"><?= htmlspecialchars($reserva['neg_direccion']) ?></div>
        <?php endif; ?>
        <?php if ($reserva['neg_telefono']): ?>
            <div class="tk-sub tk-suave">Tel: <?= htmlspecialchars($reserva['neg_telefono']) ?></div>
        <?php endif; ?>
        <?php if ($reserva['neg_nit']): ?>
            <div class="tk-sub tk-suave">NIT: <?= htmlspecialchars($reserva['neg_nit']) ?></div>
        <?php endif; ?>
        <div class="tk-sub tk-suave" style="margin-top:4px;">
            Sucursal: <?= htmlspecialchars($reserva['sucursal_nombre']) ?>
        </div>
    </div>

    <div class="tk-linea"></div>

    <div class="tk-fila tk-mini tk-suave">
        <span>Comprobante</span><span class="tk-texto">No. <?= $reserva['id'] ?></span>
    </div>
    <div class="tk-fila tk-mini tk-suave">
        <span>Emitido</span><span><?= fechaHora($reserva['fecha_reservacion']) ?></span>
    </div>
    <div class="tk-fila tk-mini tk-suave">
        <span>Atendio</span><span><?= htmlspecialchars($reserva['usuario_nombre']) ?></span>
    </div>

    <div class="tk-linea"></div>

    <!-- Cliente -->
    <div class="tk-etiqueta">CLIENTE</div>
    <div class="tk-texto"><?= htmlspecialchars($reserva['nombre_completo']) ?></div>
    <div class="tk-mini tk-suave">Tel: <?= htmlspecialchars($reserva['telefono']) ?></div>

    <div class="tk-linea"></div>

    <!-- Fechas -->
    <div class="tk-etiqueta">FECHAS</div>
    <?php
    // Cada fecha lleva su hora; las reservas viejas no la tienen y
    // se muestran solo con el dia, como siempre.
    $tkFecha = function ($fecha, $hora) {
        $t = fechaCorta($fecha);
        if (!empty($hora)) $t .= ' ' . horaCorta($hora);
        return $t;
    };
    $tkEntrega = $reserva['fecha_entrega'] ?: $reserva['fecha_uso'];
    ?>
    <div class="tk-fila tk-texto">
        <span>Entrega</span><span><?= $tkFecha($tkEntrega, $reserva['hora_entrega'] ?? null) ?></span>
    </div>
    <div class="tk-fila tk-texto">
        <span>Uso</span><span><?= $tkFecha($reserva['fecha_uso'], $reserva['hora_uso'] ?? null) ?></span>
    </div>
    <?php if ($reserva['fecha_recoleccion_programada']): ?>
    <div class="tk-fila tk-texto">
        <span>Recoleccion</span><span><?= $tkFecha($reserva['fecha_recoleccion_programada'], $reserva['hora_recoleccion'] ?? null) ?></span>
    </div>
    <?php endif; ?>

    <div class="tk-linea"></div>

    <!-- A donde se entrega: es lo que lee el que va en el camion -->
    <div class="tk-etiqueta">ENTREGAR EN</div>
    <div class="tk-texto"><?= htmlspecialchars(direccionEntrega($reserva, 'Por coordinar')) ?></div>

    <div class="tk-linea"></div>

    <!-- Productos -->
    <div class="tk-etiqueta">PRODUCTOS</div>
    <?php foreach ($detalle as $d): ?>
        <div style="margin-top:4px;">
            <div class="tk-texto"><?= htmlspecialchars($d['producto_nombre']) ?></div>
            <div class="tk-fila tk-mini">
                <span class="tk-suave">
                    <?= $d['cantidad'] ?> x Q<?= number_format($d['precio_unitario'], 2) ?><?php
                        if (($d['tipo_precio'] ?? 'evento') === 'dia') echo ' x ' . $d['dias'] . 'd';
                    ?>
                </span>
                <span class="tk-texto">Q<?= number_format($d['subtotal'], 2) ?></span>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="tk-linea"></div>

    <!-- Totales -->
    <?php
    // El total ya trae el costo inicial sumado: el subtotal de los
    // productos es total + descuento - costo inicial.
    $tkCosto = (float)($reserva['costo_inicial_monto'] ?? 0);
    $tkDesc  = (float)($reserva['descuento'] ?? 0);
    ?>
    <?php if ($tkDesc > 0 || $tkCosto > 0): ?>
        <div class="tk-fila tk-mini tk-suave">
            <span>Subtotal</span><span>Q<?= number_format($reserva['total'] + $tkDesc - $tkCosto, 2) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($tkDesc > 0): ?>
        <div class="tk-fila tk-mini tk-suave">
            <span>Descuento</span><span>-Q<?= number_format($tkDesc, 2) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($tkCosto > 0): ?>
        <div class="tk-fila tk-mini tk-suave">
            <span><?= htmlspecialchars($reserva['costo_inicial_desc'] ?: 'Costo inicial') ?></span><span>+Q<?= number_format($tkCosto, 2) ?></span>
        </div>
    <?php endif; ?>
    <div class="tk-fila tk-total" style="margin-top:4px;">
        <span>TOTAL</span><span>Q<?= number_format($reserva['total'], 2) ?></span>
    </div>

    <div class="tk-linea"></div>

    <!-- Estado: en texto plano, la termica no imprime emojis -->
    <?php
    $estadoTexto = [
        'reservado' => 'RESERVADO',
        'entregado' => 'ENTREGADO',
        'devuelto'  => 'DEVUELTO',
        'cancelado' => 'CANCELADO',
    ];
    ?>
    <div class="tk-texto" style="text-align:center; letter-spacing:1px;">
        ** <?= $estadoTexto[$reserva['estado']] ?? mb_strtoupper($reserva['estado']) ?> **
    </div>

    <!-- Firmas -->
    <div style="margin-top:34px; display:flex; gap:10px;">
        <div style="flex:1; text-align:center;">
            <div style="border-top:1px solid #000; padding-top:3px;" class="tk-mini">Firma cliente</div>
        </div>
        <div style="flex:1; text-align:center;">
            <div style="border-top:1px solid #000; padding-top:3px;" class="tk-mini">Firma responsable</div>
        </div>
    </div>

    <div class="tk-mini" style="text-align:center; margin-top:10px;">
        Gracias por su preferencia
    </div>
    <div class="tk-mini" style="text-align:center;">.</div>
</div>

</body>
</html>
