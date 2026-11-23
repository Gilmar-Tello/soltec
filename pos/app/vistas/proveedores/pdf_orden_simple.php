<?php
if (!isset($_SESSION['usuario_id'])) { echo '<p style="padding:20px;color:red;">Sesión expirada.</p>'; exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tituloPagina ?></title>
    <?php include RAIZ . '/app/vistas/plantillas/tema.php'; ?>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gray-100 p-6">

    <!-- Barra superior (no se imprime) -->
    <div class="no-print flex items-center justify-between bg-white rounded-xl shadow px-6 py-4 mb-6 max-w-2xl mx-auto">
        <span class="text-gray-400 text-sm">📄 Se abrió el diálogo de impresión — elegí "Guardar como PDF" para descargarlo</span>
        <button onclick="window.print()"
                class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl transition whitespace-nowrap ml-4">
            🖨️ Abrir diálogo de nuevo
        </button>
    </div>
    <script>
        // Se abre automáticamente el diálogo de impresión/guardar apenas carga la página,
        // así con un solo clic desde la orden ya queda lista la descarga del PDF.
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 300);
        });
    </script>

    <!-- Documento -->
    <div class="bg-white max-w-2xl mx-auto rounded-xl shadow p-8">
        <h1 class="text-xl font-bold text-gray-800 mb-1">Lista de Compra — Orden #<?= $orden['id'] ?></h1>
        <p class="text-gray-600">Proveedor: <b><?= htmlspecialchars($orden['proveedor_nombre']) ?></b></p>
        <p class="text-gray-400 text-sm mb-6">Fecha: <?= date('d/m/Y', strtotime($orden['creado_en'])) ?></p>

        <table class="w-full text-sm border border-gray-300" style="border-collapse: collapse;">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-gray-300 px-3 py-2 text-left">Código</th>
                    <th class="border border-gray-300 px-3 py-2 text-left">Producto</th>
                    <th class="border border-gray-300 px-3 py-2 text-right">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalle as $d): ?>
                <tr>
                    <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($d['producto_codigo'] ?: '—') ?></td>
                    <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($d['producto_nombre']) ?></td>
                    <td class="border border-gray-300 px-3 py-2 text-right">
                        <?= number_format($d['cantidad'], 2) ?> <?= htmlspecialchars($d['unidad']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="text-gray-400 text-xs mt-6">
            Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
        </p>
    </div>

</body>
</html>
