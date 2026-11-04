<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<?php $esEdicion = isset($producto); ?>

<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=inventario/index" class="text-teal-700 font-bold text-xl">←</a>
        <h2 class="text-2xl font-extrabold text-teal-800">
            <?= $esEdicion ? '✏️ Editar Producto' : '➕ Nuevo Producto' ?>
        </h2>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST"
          action="<?= URL_BASE ?>/?url=inventario/<?= $esEdicion ? 'actualizar' : 'guardar' ?>"
          enctype="multipart/form-data" data-comprimir-imagenes
          class="bg-white rounded-2xl shadow p-6 space-y-4">

        <?php if ($esEdicion): ?>
            <input type="hidden" name="id" value="<?= $producto['id'] ?>">
        <?php endif; ?>

        <div>
            <label class="block text-sm font-bold text-slate-600 mb-1">Nombre del producto <span class="text-red-500">*</span></label>
            <input type="text" name="nombre" required
                   value="<?= htmlspecialchars($producto['nombre'] ?? '') ?>"
                   placeholder="Ej: Silla plástica blanca"
                   class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
        </div>

        <!-- ── Foto del producto ───────────────────────────── -->
        <div class="border-2 border-dashed border-slate-200 rounded-xl p-4">
            <label class="block text-sm font-bold text-slate-600 mb-2">📷 Foto del producto</label>
            <div class="flex items-start gap-4">
                <?php if ($esEdicion && !empty($producto['foto_path']) && existeImagen($producto['foto_path'])): ?>
                    <div class="text-center">
                        <img src="<?= urlImagen($producto['foto_path']) ?>"
                             alt="<?= htmlspecialchars($producto['nombre']) ?>"
                             onclick="soltecAbrirFoto(this.src, '<?= htmlspecialchars($producto['nombre'], ENT_QUOTES) ?>')"
                             class="w-24 h-24 object-cover rounded-xl border-2 border-slate-200 cursor-zoom-in">
                        <label class="flex items-center justify-center gap-1 text-[11px] text-red-500 mt-1 cursor-pointer">
                            <input type="checkbox" name="quitar_foto" value="1" class="accent-red-500"> quitar
                        </label>
                    </div>
                <?php endif; ?>
                <div class="flex-1">
                    <input type="file" name="foto" accept="image/*"
                           onchange="soltecVistaPrevia(this, 'previewProducto')"
                           class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl
                                  file:border-0 file:text-xs file:font-bold file:bg-teal-50 file:text-teal-700
                                  hover:file:bg-teal-100 cursor-pointer">
                    <p class="text-[11px] text-slate-400 mt-1.5">
                        Se reduce sola antes de subirse (máx. 1280px), para no cargar el hosting.
                    </p>
                    <div id="previewProducto" class="flex gap-2 mt-2"></div>
                </div>
            </div>
        </div>

        <!-- ── Ubicación en bodega ─────────────────────────── -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
            <label class="block text-sm font-bold text-slate-600 mb-1">📍 Ubicación en bodega</label>
            <p class="text-[11px] text-slate-400 mb-3">
                Como una celda de Excel: la estantería es la hoja, y columna + fila dicen el lugar exacto.
            </p>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Estantería</label>
                    <input type="text" name="estanteria" maxlength="50"
                           value="<?= htmlspecialchars($producto['estanteria'] ?? '') ?>"
                           placeholder="Ej: Estantería A"
                           class="w-full px-3 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Columna</label>
                    <input type="text" name="columna" maxlength="10"
                           value="<?= htmlspecialchars($producto['columna'] ?? '') ?>"
                           placeholder="Ej: C3"
                           class="w-full px-3 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm text-center">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Fila</label>
                    <input type="text" name="fila" maxlength="10"
                           value="<?= htmlspecialchars($producto['fila'] ?? '') ?>"
                           placeholder="Ej: 2"
                           class="w-full px-3 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm text-center">
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-slate-600 mb-1">Sucursal <span class="text-red-500">*</span></label>
                <select name="sucursal_id" required
                        class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
                    <option value="">Seleccioná una sucursal</option>
                    <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (($producto['sucursal_id'] ?? null) == $s['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-600 mb-1">Categoría</label>
                <select name="categoria_id"
                        class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
                    <option value="">Sin categoría</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (($producto['categoria_id'] ?? null) == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-600 mb-1">Descripción</label>
            <textarea name="descripcion" rows="2"
                      placeholder="Detalles adicionales del producto"
                      class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm"><?= htmlspecialchars($producto['descripcion'] ?? '') ?></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-slate-600 mb-1">Precio de alquiler (Q) <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="precio_alquiler" required
                       value="<?= htmlspecialchars($producto['precio_alquiler'] ?? '') ?>"
                       placeholder="0.00"
                       class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-600 mb-1">Cantidad total <span class="text-red-500">*</span></label>
                <input type="number" min="0" name="cantidad_total" required
                       value="<?= htmlspecialchars($producto['cantidad_total'] ?? '0') ?>"
                       class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
                <?php if ($esEdicion): ?>
                    <p class="text-xs text-slate-400 mt-1">Disponibles actualmente: <?= $producto['cantidad_disponible'] ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-6 py-3 rounded-xl transition">
                💾 <?= $esEdicion ? 'Guardar cambios' : 'Agregar producto' ?>
            </button>
            <a href="<?= URL_BASE ?>/?url=inventario/index"
               class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-6 py-3 rounded-xl transition">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
