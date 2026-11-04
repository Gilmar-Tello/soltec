<?php
function q($n){ return 'Q' . number_format((float)$n, 2); }
function nc($n){ return rtrim(rtrim(number_format((float)$n,2),'0'),'.'); }
$esVenta = $ingreso['tipo'] === 'venta';
$total = (float) $ingreso['monto'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante #<?= (int)$ingreso['id'] ?> — Brix</title>
    <style>
        * { box-sizing: border-box; }
        body { background:#e2e8f0; margin:0; padding:20px 10px;
               font-family: Arial, Helvetica, sans-serif; color:#000; }

        /* Barra de acciones (no se imprime) */
        .barra { width:80mm; margin:0 auto 14px; display:flex; justify-content:space-between; align-items:center;
                 background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:8px 12px; }
        .barra a { color:#64748b; text-decoration:none; font-size:13px; }
        .barra a:hover { text-decoration:underline; }
        .btn { background:#0f172a; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:13px; font-weight:700; cursor:pointer; }
        .btn:hover { background:#1e293b; }

        /* Ticket 80mm — todo Arial y en negrita para que imprima nítido en térmica */
        .ticket { width:80mm; margin:0 auto; background:#fff; padding:4mm 3mm;
                  font-family: Arial, Helvetica, sans-serif; font-weight:700; color:#000; line-height:1.4; }
        .ticket * { font-weight:700 !important; }
        .center { text-align:center; }
        .logo { max-width:52mm; max-height:22mm; object-fit:contain; margin-bottom:4px; }
        .neg-nombre { font-size:19px; }
        .small { font-size:13px; line-height:1.4; }
        .sep { border:none; border-top:2px dashed #000; margin:7px 0; }
        .fila { display:flex; justify-content:space-between; gap:8px; font-size:14px; line-height:1.55; }
        .item-nombre { font-size:15px; margin-top:4px; word-break:break-word; }
        .total { display:flex; justify-content:space-between; font-size:19px; margin-top:4px; }
        .pie { text-align:center; font-size:13px; margin-top:10px; }

        @media print {
            .no-print { display:none !important; }
            html, body { background:#fff; margin:0; padding:0; width:80mm; }
            .ticket { width:80mm; padding:2mm; margin:0; }
            @page { size: 80mm auto; margin: 0; }
        }
    </style>
</head>
<body>

    <div class="barra no-print">
        <a href="<?= URL_BASE ?>/?url=ingresos/index">← Volver</a>
        <button class="btn" onclick="window.print()">Imprimir</button>
    </div>

    <div class="ticket">
        <div class="center">
            <?php if (!empty($negocio['logo'])): ?>
                <img class="logo" src="<?= URL_BASE ?>/<?= htmlspecialchars($negocio['logo']) ?>" alt="Logo"><br>
            <?php endif; ?>
            <div class="neg-nombre"><?= htmlspecialchars($negocio['nombre_negocio'] ?? 'Brix') ?></div>
            <div class="small">
                <?php if (!empty($negocio['direccion'])): ?><?= htmlspecialchars($negocio['direccion']) ?><br><?php endif; ?>
                <?php if (!empty($negocio['telefono'])): ?>Tel: <?= htmlspecialchars($negocio['telefono']) ?><br><?php endif; ?>
                <?php if (!empty($negocio['nit'])): ?>NIT: <?= htmlspecialchars($negocio['nit']) ?><?php endif; ?>
            </div>
        </div>

        <hr class="sep">

        <div class="fila"><span>Comprobante</span><span>#<?= (int)$ingreso['id'] ?></span></div>
        <div class="fila"><span>Fecha</span><span><?= date('d/m/Y H:i', strtotime($ingreso['creado_en'])) ?></span></div>
        <div class="fila"><span>Tipo</span><span><?= $esVenta ? 'Venta' : 'Servicio' ?></span></div>
        <?php if (!empty($ingreso['usuario_nombre'])): ?>
            <div class="fila"><span>Atendió</span><span><?= htmlspecialchars($ingreso['usuario_nombre']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($ingreso['concepto'])): ?>
            <div class="small" style="margin-top:4px;"><?= htmlspecialchars($ingreso['concepto']) ?></div>
        <?php endif; ?>

        <hr class="sep">

        <?php foreach ($lineas as $l): ?>
            <div class="item-nombre"><?= htmlspecialchars($l['concepto'] ?? '') ?></div>
            <div class="fila">
                <span><?= nc($l['cantidad']) ?> x <?= q($l['precio_unitario']) ?></span>
                <span><?= q($l['subtotal']) ?></span>
            </div>
        <?php endforeach; ?>

        <hr class="sep">

        <div class="total"><span>TOTAL</span><span><?= q($total) ?></span></div>

        <hr class="sep">

        <div class="pie">¡Gracias por su compra!<br>Brix</div>
    </div>

    <script>
        // Descomentá para imprimir automáticamente al abrir el comprobante:
        // window.addEventListener('load', function(){ window.print(); });
    </script>
</body>
</html>
