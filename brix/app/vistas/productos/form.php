<?php include RAIZ . '/app/vistas/plantillas/header.php';
$esEdicion = !empty($producto);
$accion = $esEdicion ? 'actualizar' : 'guardar';
?>

<div class="mb-4">
    <a href="<?= URL_BASE ?>/?url=productos/index" class="text-sm text-slate-500 hover:underline">← Volver a productos</a>
    <h2 class="text-lg font-semibold text-slate-900 mt-1"><?= $esEdicion ? 'Editar producto' : 'Nuevo producto' ?></h2>
</div>

<form method="POST" action="<?= URL_BASE ?>/?url=productos/<?= $accion ?>" class="bg-white rounded-lg border border-slate-200 p-6 max-w-3xl">
    <?php if ($esEdicion): ?><input type="hidden" name="id" value="<?= $producto['id'] ?>"><?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Nombre *</label>
            <input type="text" name="nombre" required value="<?= htmlspecialchars($producto['nombre'] ?? '') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Código (opcional)</label>
            <input type="text" name="codigo" value="<?= htmlspecialchars($producto['codigo'] ?? '') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Fecha de vencimiento (opcional)</label>
            <input type="date" name="fecha_vencimiento" value="<?= htmlspecialchars($producto['fecha_vencimiento'] ?? '') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Categoría *</label>
            <select name="categoria_id" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Seleccioná…</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (($producto['categoria_id'] ?? 0)==$c['id'])?'selected':'' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <a href="<?= URL_BASE ?>/?url=categorias/index" class="text-xs text-indigo-600 hover:underline">+ Nueva categoría</a>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Unidad de medida *</label>
            <select name="unidad_id" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Seleccioná…</option>
                <?php foreach ($unidades as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (($producto['unidad_id'] ?? 0)==$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['nombre']) ?> (<?= htmlspecialchars($u['abreviatura']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <a href="<?= URL_BASE ?>/?url=unidades/index" class="text-xs text-indigo-600 hover:underline">+ Nueva unidad</a>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Precio de compra (costo)</label>
            <input type="number" step="0.01" min="0" name="precio_compra" value="<?= htmlspecialchars($producto['precio_compra'] ?? '0') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Precio de venta</label>
            <input type="number" step="0.01" min="0" name="precio_venta" value="<?= htmlspecialchars($producto['precio_venta'] ?? '0') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Stock mínimo (alerta)</label>
            <input type="number" step="0.01" min="0" name="stock_minimo" value="<?= htmlspecialchars($producto['stock_minimo'] ?? '0') ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>

        <?php if (!$esEdicion): ?>
        <div id="wrap-stock-inicial">
            <label class="block text-xs text-slate-500 mb-1">Stock inicial</label>
            <input type="number" step="0.01" min="0" name="stock_inicial" value="0" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <?php else: ?>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Stock actual</label>
            <input type="text" disabled value="<?= htmlspecialchars(rtrim(rtrim(number_format((float)($producto['stock']??0),2),'0'),'.')) ?>" class="w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
            <p class="text-xs text-slate-400 mt-1">El stock se ajusta con ventas, armados y cuadres.</p>
        </div>
        <?php endif; ?>

        <div class="sm:col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Descripción (opcional)</label>
            <textarea name="descripcion" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars($producto['descripcion'] ?? '') ?></textarea>
        </div>

        <?php if (!$esEdicion): ?>
        <div class="sm:col-span-2">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="es_compuesto" value="1" id="chk-compuesto" onchange="document.getElementById('wrap-stock-inicial').style.display = this.checked ? 'none' : 'block';" class="rounded border-slate-300">
                Es un producto compuesto (se arma a partir de otros productos)
            </label>
            <p class="text-xs text-slate-400 mt-1 ml-6">Al guardarlo definirás su receta. No lleva stock propio: al venderlo se descuentan sus componentes del inventario.</p>
        </div>
        <?php else: ?>
            <input type="hidden" name="es_compuesto" value="<?= $producto['es_compuesto'] ? 1 : 0 ?>">
        <?php endif; ?>
    </div>

    <div class="mt-6 flex gap-2">
        <button type="submit" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold px-5 py-2.5"><?= $esEdicion ? 'Guardar cambios' : 'Crear producto' ?></button>
        <a href="<?= URL_BASE ?>/?url=productos/index" class="rounded-md border border-slate-300 text-slate-600 text-sm px-5 py-2.5">Cancelar</a>
    </div>
</form>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
