<?php
if (!isset($_SESSION['usuario_id'])) { echo '<p style="padding:20px;color:red;">Sesión expirada.</p>'; exit; }
$config = obtenerConexion()->prepare("SELECT * FROM configuracion_negocio WHERE tenant_id = :tid LIMIT 1");
$config->execute([':tid' => tenantActual()]);
$config = $config->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tituloPagina ?> — POS GT</title>
    <?php include RAIZ . '/app/vistas/plantillas/tema.php'; ?>
    <style>
        body { font-family: 'Nunito', sans-serif; }
        .ticket-print { display: none; }
        @page {
            size: 80mm auto;
            margin: 0;
        }
        @media print {
            .no-print { display: none !important; }
            .documento-pantalla { display: none !important; }
            body { background: white !important; }

            .ticket-print {
                display: block !important;
                position: fixed !important;
                top: 0; left: 0;
                width: 72mm !important;
                max-width: 72mm !important;
                padding: 4mm !important;
                font-size: 11pt !important;
                font-family: Arial, Helvetica, sans-serif !important;
                font-weight: 700 !important;
                line-height: 1.35 !important;
                color: #000 !important;
                background: #fff !important;
            }
            .ticket-print * {
                font-family: Arial, Helvetica, sans-serif !important;
                font-weight: 700 !important;
                color: #000 !important;
            }
            .ticket-print .titulo   { font-size: 15pt !important; }
            .ticket-print .chico    { font-size: 9.5pt !important; }
            .ticket-print .grande   { font-size: 13pt !important; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-6">

    <!-- Barra superior -->
    <div class="no-print flex items-center justify-between bg-white rounded-2xl shadow px-6 py-4 mb-6 max-w-3xl mx-auto">
        <span class="text-gray-400 text-sm">📄 Vista previa</span>
        <h1 class="font-extrabold text-green-800"><?= $tituloPagina ?></h1>
        <button onclick="window.print()" id="btnImprimirInterno"
                class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl transition">
            🖨️ Descargar / Imprimir PDF
        </button>
    </div>
    <script>
        // Si esta página está cargada dentro de un iframe (modal), el modal ya trae su propio
        // botón de impresión externo — ocultamos este para evitar imprimir desde adentro del iframe.
        if (window.self !== window.top) {
            document.getElementById('btnImprimirInterno').style.display = 'none';
        }
    </script>

    <!-- Documento (vista de pantalla / hoja normal) -->
    <div class="documento-pantalla bg-white max-w-3xl mx-auto rounded-2xl shadow p-8">

        <!-- Encabezado -->
        <div class="flex items-center justify-between border-b-2 border-green-600 pb-4 mb-6">
            <div class="flex items-center gap-3">
                <?php if ($config['logo']): ?>
                    <img src="<?= URL_BASE ?>/<?= htmlspecialchars($config['logo']) ?>" alt="Logo" class="w-12 h-12 object-cover rounded-xl">
                <?php endif; ?>
                <div>
                    <div class="text-2xl font-extrabold text-green-800"><?= htmlspecialchars($config['nombre_negocio']) ?></div>
                    <div class="text-gray-500 text-sm"><?= htmlspecialchars($config['direccion'] ?? '') ?></div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xl font-bold text-gray-800">Estado de Cuenta</div>
                <div class="text-gray-400 text-sm">Generado: <?= date('d/m/Y H:i') ?></div>
            </div>
        </div>

        <!-- Datos del cliente -->
        <div class="grid grid-cols-2 gap-4 mb-6 bg-gray-50 rounded-xl p-4">
            <div>
                <div class="text-xs text-gray-400">Cliente</div>
                <div class="font-extrabold text-gray-800 text-lg"><?= htmlspecialchars($cliente['nombre']) ?></div>
            </div>
            <div>
                <div class="text-xs text-gray-400">NIT</div>
                <div class="font-bold text-gray-700 font-mono"><?= htmlspecialchars($cliente['nit'] ?? 'CF') ?></div>
            </div>
            <div>
                <div class="text-xs text-gray-400">Teléfono</div>
                <div class="font-bold text-gray-700"><?= htmlspecialchars($cliente['telefono'] ?? '—') ?></div>
            </div>
            <div>
                <div class="text-xs text-gray-400">Límite de crédito</div>
                <div class="font-bold text-green-700">
                    <?= $cliente['limite_credito'] > 0 ? 'Q'.number_format($cliente['limite_credito'],2) : 'Sin límite' ?>
                </div>
            </div>
        </div>

        <!-- Resumen deuda -->
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 flex items-center justify-between">
            <div class="text-gray-600 font-bold">Deuda total pendiente</div>
            <div class="text-3xl font-extrabold text-red-600">Q<?= number_format($deudaTotal, 2) ?></div>
        </div>

        <!-- Detalle de ventas -->
        <?php if (empty($ventas)): ?>
            <div class="text-center py-8 text-gray-400">✅ Este cliente no tiene deudas pendientes.</div>
        <?php else: ?>
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-green-700 text-white">
                    <th class="px-3 py-2 text-left">#</th>
                    <th class="px-3 py-2 text-left">Fecha</th>
                    <th class="px-3 py-2 text-right">Total</th>
                    <th class="px-3 py-2 text-right">Abonado</th>
                    <th class="px-3 py-2 text-right">Saldo</th>
                    <th class="px-3 py-2 text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ventas as $i => $v): ?>
                <tr class="<?= $i%2===0?'bg-white':'bg-gray-50' ?> border-b border-gray-100
                           <?= $v['estado_pago']==='pendiente'?'bg-red-50':'' ?>">
                    <td class="px-3 py-2 font-mono text-xs text-gray-500">#<?= $v['id'] ?></td>
                    <td class="px-3 py-2 text-gray-600 text-xs"><?= date('d/m/Y H:i', strtotime($v['creado_en'])) ?></td>
                    <td class="px-3 py-2 text-right font-bold text-gray-800">Q<?= number_format($v['total'],2) ?></td>
                    <td class="px-3 py-2 text-right text-emerald-600">Q<?= number_format($v['pagado'],2) ?></td>
                    <td class="px-3 py-2 text-right font-extrabold <?= $v['saldo_pendiente']>0?'text-red-600':'text-gray-400' ?>">
                        Q<?= number_format($v['saldo_pendiente'],2) ?>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <?php if ($v['estado_pago']==='pagado'): ?>
                            <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-1 rounded-full">✅ Pagado</span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full">⏳ Pendiente</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="bg-red-50">
                    <td colspan="4" class="px-3 py-2 text-right font-extrabold text-gray-700">SALDO TOTAL PENDIENTE</td>
                    <td class="px-3 py-2 text-right font-extrabold text-red-600 text-base">Q<?= number_format($deudaTotal,2) ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="border-t border-gray-200 mt-6 pt-4 text-xs text-gray-400 text-center">
            POS GT — SISTEMAS GT &copy; <?= date('Y') ?> · Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
        </div>
    </div>

    <!-- Documento angosto (solo se muestra al imprimir, formato térmica 80mm) -->
    <div class="ticket-print">
        <div style="text-align:center; margin-bottom:8px;">
            <?php if ($config['logo']): ?>
                <img src="<?= URL_BASE ?>/<?= htmlspecialchars($config['logo']) ?>" alt="Logo" style="width:50px;height:50px;object-fit:cover;border-radius:8px;margin:0 auto 4px;">
            <?php endif; ?>
            <div class="titulo"><?= htmlspecialchars($config['nombre_negocio']) ?></div>
            <div class="chico"><?= htmlspecialchars($config['direccion'] ?? '') ?></div>
            <?php if (!empty($config['telefono'])): ?>
                <div class="chico">Tel: <?= htmlspecialchars($config['telefono']) ?></div>
            <?php endif; ?>
        </div>

        <div style="border-top:1px dashed #000; border-bottom:1px dashed #000; padding:6px 0; margin-bottom:8px; text-align:center;">
            ESTADO DE CUENTA
            <div class="chico"><?= date('d/m/Y H:i') ?></div>
        </div>

        <div style="margin-bottom:8px;">
            <div>Cliente: <?= htmlspecialchars($cliente['nombre']) ?></div>
            <div class="chico">NIT: <?= htmlspecialchars($cliente['nit'] ?? 'CF') ?></div>
            <?php if (!empty($cliente['telefono'])): ?>
                <div class="chico">Tel: <?= htmlspecialchars($cliente['telefono']) ?></div>
            <?php endif; ?>
            <div class="chico">
                Límite: <?= $cliente['limite_credito'] > 0 ? 'Q'.number_format($cliente['limite_credito'],2) : 'Sin límite' ?>
            </div>
        </div>

        <?php if (empty($ventas)): ?>
            <div style="text-align:center; padding:10px 0;">✅ Sin deudas pendientes.</div>
        <?php else: ?>
        <div style="border-top:1px dashed #000; padding-top:6px; margin-bottom:6px;">
            <?php foreach ($ventas as $v): ?>
            <div style="border-bottom:1px dashed #000; padding-bottom:6px; margin-bottom:6px;">
                <div style="display:flex; justify-content:space-between;">
                    <span>Venta #<?= $v['id'] ?></span>
                    <span class="chico"><?= date('d/m/Y H:i', strtotime($v['creado_en'])) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Total</span><span>Q<?= number_format($v['total'],2) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Abonado</span><span>Q<?= number_format($v['pagado'],2) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Saldo</span><span>Q<?= number_format($v['saldo_pendiente'],2) ?></span>
                </div>
                <div>
                    <?= $v['estado_pago']==='pagado' ? '✅ Pagado' : '⏳ Pendiente' ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; margin-top:6px;" class="grande">
            <span>TOTAL PENDIENTE</span>
            <span>Q<?= number_format($deudaTotal,2) ?></span>
        </div>

        <div class="chico" style="text-align:center; margin-top:10px;">
            SISTEMAS GT &copy; <?= date('Y') ?><br>
            Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
        </div>
    </div>
</body>
</html>