<?php include RAIZ . '/app/vistas/plantillas/header.php';
function num($n){ return rtrim(rtrim(number_format((float)$n,2),'0'),'.'); }
?>

<div class="mb-4">
    <a href="<?= URL_BASE ?>/?url=productos/index" class="text-sm text-slate-500 hover:underline">← Volver a productos</a>
    <h2 class="text-lg font-semibold text-slate-900 mt-1">Entrada de stock — <?= htmlspecialchars($producto['nombre']) ?></h2>
    <p class="text-sm text-slate-500">
        Stock actual: <span class="font-medium text-slate-700"><?= num($producto['stock']) ?> <?= htmlspecialchars($producto['unidad'] ?? '') ?></span>
        · Último costo: <span class="font-medium text-slate-700">Q<?= number_format($producto['precio_compra'], 2) ?></span>
    </p>
</div>

<form method="POST" action="<?= URL_BASE ?>/?url=productos/entrada/<?= $producto['id'] ?>" class="bg-white rounded-lg border border-slate-200 p-6 max-w-xl">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Cantidad que entra *</label>
            <input type="number" step="0.01" min="0" name="cantidad" id="in-cant" required autofocus oninput="calc()" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Precio de compra (unitario)</label>
            <input type="number" step="0.01" min="0" name="precio_compra" id="in-precio" value="<?= htmlspecialchars($producto['precio_compra']) ?>" oninput="calc()" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <p class="text-xs text-slate-400 mt-1">Actualiza el costo del producto al último precio pagado.</p>
        </div>
    </div>

    <div class="mt-4 rounded-md bg-slate-50 border border-slate-200 px-4 py-3 flex items-center justify-between">
        <span class="text-sm text-slate-600">Costo total de la compra</span>
        <span class="text-lg font-bold text-slate-900" id="costo-total">Q0.00</span>
    </div>

    <label class="flex items-center gap-2 text-sm text-slate-700 mt-4">
        <input type="checkbox" name="registrar_egreso" value="1" checked class="rounded border-slate-300">
        Registrar automáticamente el egreso por esta compra
    </label>

    <div class="mt-5">
        <button type="submit" class="rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2.5">Registrar entrada</button>
        <a href="<?= URL_BASE ?>/?url=productos/index" class="ml-2 rounded-md border border-slate-300 text-slate-600 text-sm px-5 py-2.5">Cancelar</a>
    </div>
</form>

<script>
function calc() {
    const c = parseFloat(document.getElementById('in-cant').value || '0');
    const p = parseFloat(document.getElementById('in-precio').value || '0');
    const total = (c > 0 && p > 0) ? c * p : 0;
    document.getElementById('costo-total').textContent = 'Q' + total.toFixed(2);
}
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
