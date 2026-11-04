<?php
function num($n){ return rtrim(rtrim(number_format((float)$n,2),'0'),'.'); }
$soloBajo = !empty($_GET['bajo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina) ?> — Brix</title>
    <script src="<?= URL_BASE ?>/app/public/vendor/tailwind-3.4.17.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .doc { box-shadow: none !important; border: none !important; }
            @page { margin: 14mm; }
        }
    </style>
</head>
<body class="bg-slate-100 p-6">

    <div class="no-print flex items-center justify-between bg-white rounded-lg border border-slate-200 px-6 py-3 mb-6 max-w-4xl mx-auto">
        <a href="<?= URL_BASE ?>/?url=productos/index<?= $soloBajo ? '&bajo=1' : '' ?>" class="text-sm text-slate-500 hover:underline">← Volver</a>
        <span class="text-sm text-slate-400">Vista previa</span>
        <button onclick="window.print()" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold px-5 py-2">Descargar / Imprimir PDF</button>
    </div>

    <div class="doc bg-white rounded-lg border border-slate-200 p-8 max-w-4xl mx-auto">
        <div class="flex items-start justify-between border-b border-slate-200 pb-4 mb-4">
            <div>
                <div class="text-xl font-bold text-slate-900">Brix</div>
                <div class="text-sm text-slate-500"><?= htmlspecialchars($_SESSION['nombre_negocio'] ?? '') ?></div>
            </div>
            <div class="text-right">
                <div class="text-base font-semibold text-slate-800"><?= htmlspecialchars($tituloPagina) ?></div>
                <div class="text-xs text-slate-500"><?= date('d/m/Y H:i') ?></div>
            </div>
        </div>

        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-300">
                    <th class="py-2 pr-2">Producto</th>
                    <th class="py-2 px-2">Categoría</th>
                    <th class="py-2 px-2 text-right">Stock</th>
                    <?php if ($soloBajo): ?><th class="py-2 px-2 text-right">Mínimo</th><?php endif; ?>
                    <th class="py-2 px-2 text-right">Costo</th>
                    <th class="py-2 px-2 text-right">Venta</th>
                    <th class="py-2 pl-2">Vence</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($productos)): ?>
                    <tr><td colspan="7" class="py-6 text-center text-slate-400">Sin productos para mostrar.</td></tr>
                <?php else: foreach ($productos as $p):
                    $vencido = $p['fecha_vencimiento'] && strtotime($p['fecha_vencimiento']) < strtotime(date('Y-m-d'));
                ?>
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-2">
                            <div class="font-medium text-slate-800"><?= htmlspecialchars($p['nombre']) ?></div>
                            <?php if (!empty($p['codigo'])): ?><div class="text-xs text-slate-400"><?= htmlspecialchars($p['codigo']) ?></div><?php endif; ?>
                        </td>
                        <td class="py-2 px-2 text-slate-600"><?= htmlspecialchars($p['categoria'] ?? '—') ?></td>
                        <td class="py-2 px-2 text-right">
                            <?php if ($p['es_compuesto']): ?>
                                <?= (int)$p['disponible'] ?> <span class="text-xs text-slate-400">(hace)</span>
                            <?php else: ?>
                                <?= num($p['stock']) ?> <span class="text-xs text-slate-400"><?= htmlspecialchars($p['unidad'] ?? '') ?></span>
                            <?php endif; ?>
                        </td>
                        <?php if ($soloBajo): ?><td class="py-2 px-2 text-right text-slate-500"><?= num($p['stock_minimo']) ?></td><?php endif; ?>
                        <td class="py-2 px-2 text-right text-slate-600">Q<?= number_format($p['precio_compra'], 2) ?></td>
                        <td class="py-2 px-2 text-right text-slate-800">Q<?= number_format($p['precio_venta'], 2) ?></td>
                        <td class="py-2 pl-2 <?= $vencido ? 'text-red-600' : 'text-slate-500' ?>">
                            <?= $p['fecha_vencimiento'] ? date('d/m/Y', strtotime($p['fecha_vencimiento'])) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div class="mt-4 text-sm text-slate-500">Total de productos: <span class="font-semibold text-slate-700"><?= count($productos) ?></span></div>
    </div>

    <script>window.addEventListener('load', function(){ /* listo para imprimir */ });</script>
</body>
</html>
