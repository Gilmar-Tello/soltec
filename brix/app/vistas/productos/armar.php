<?php include RAIZ . '/app/vistas/plantillas/header.php';
function num($n){ return rtrim(rtrim(number_format((float)$n,2),'0'),'.'); }
?>

<div class="mb-4">
    <a href="<?= URL_BASE ?>/?url=productos/index" class="text-sm text-slate-500 hover:underline">← Volver a productos</a>
    <h2 class="text-lg font-semibold text-slate-900 mt-1">Armar "<?= htmlspecialchars($compuesto['nombre']) ?>"</h2>
    <p class="text-sm text-slate-500">Stock actual del compuesto: <span class="font-medium text-slate-700"><?= num($compuesto['stock']) ?></span></p>
</div>

<?php if (empty($componentes)): ?>
    <div class="bg-white rounded-lg border border-slate-200 p-6 max-w-2xl">
        <p class="text-sm text-slate-600">Este compuesto todavía no tiene receta.</p>
        <a href="<?= URL_BASE ?>/?url=productos/componentes/<?= $compuesto['id'] ?>" class="text-sm text-indigo-600 hover:underline">Definir componentes →</a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 max-w-4xl">
        <div class="bg-white rounded-lg border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Componentes (por unidad armada)</h3>
            <table class="w-full text-sm">
                <thead class="text-slate-500 text-xs uppercase tracking-wide border-b border-slate-200">
                    <tr><th class="text-left py-2">Producto</th><th class="text-right py-2">Requiere</th><th class="text-right py-2">Stock</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($componentes as $c): ?>
                    <tr>
                        <td class="py-2 text-slate-700"><?= htmlspecialchars($c['nombre']) ?></td>
                        <td class="py-2 text-right"><?= num($c['cantidad']) ?></td>
                        <td class="py-2 text-right <?= (float)$c['stock'] < (float)$c['cantidad'] ? 'text-red-600' : 'text-slate-500' ?>"><?= num($c['stock']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Armar</h3>
            <p class="text-sm text-slate-500 mb-3">Máximo posible con el stock actual: <span class="font-semibold text-slate-800"><?= (int)$maxArmable ?></span></p>
            <?php if ((int)$maxArmable > 0): ?>
            <form method="POST" action="<?= URL_BASE ?>/?url=productos/armar/<?= $compuesto['id'] ?>" class="space-y-3">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Cantidad a armar</label>
                    <input type="number" min="1" max="<?= (int)$maxArmable ?>" step="1" name="cantidad" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2.5">Armar y sumar al stock</button>
            </form>
            <?php else: ?>
                <p class="text-sm text-amber-600">No hay stock suficiente de los componentes para armar unidades.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
