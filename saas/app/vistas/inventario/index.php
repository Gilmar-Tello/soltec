<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center justify-between mb-4">
    <h2 class="text-2xl font-extrabold text-teal-800">📦 Inventario</h2>
    <a href="<?= URL_BASE ?>/?url=inventario/nuevo"
       class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 py-2.5 rounded-xl transition text-sm">
        ➕ Nuevo Producto
    </a>
</div>

<?php if (isset($_SESSION['exito'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl p-4 mb-4">
        ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
    </div>
    <?php unset($_SESSION['exito']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-4">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- Tabs de sucursal -->
<div class="flex gap-2 mb-4 flex-wrap">
    <a href="<?= URL_BASE ?>/?url=inventario/index<?= $busqueda ? '&busqueda=' . urlencode($busqueda) : '' ?>"
       class="px-4 py-2 rounded-xl text-sm font-bold transition
              <?= !$sucursalFiltro ? 'bg-teal-600 text-white shadow' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        🏬 Todas
    </a>
    <?php foreach ($sucursales as $s): ?>
        <a href="<?= URL_BASE ?>/?url=inventario/index&sucursal=<?= $s['id'] ?><?= $busqueda ? '&busqueda=' . urlencode($busqueda) : '' ?>"
           class="px-4 py-2 rounded-xl text-sm font-bold transition
                  <?= $sucursalFiltro == $s['id'] ? 'bg-teal-600 text-white shadow' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
            <?= htmlspecialchars($s['nombre']) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Buscador de producto -->
<form method="GET" action="<?= URL_BASE ?>/" class="mb-5 flex gap-2">
    <input type="hidden" name="url" value="inventario/index">
    <?php if ($sucursalFiltro): ?>
        <input type="hidden" name="sucursal" value="<?= $sucursalFiltro ?>">
    <?php endif; ?>
    <input type="text" name="busqueda"
           value="<?= htmlspecialchars($busqueda) ?>"
           placeholder="🔍 Buscar producto por nombre en <?= $sucursalFiltro ? htmlspecialchars(array_column($sucursales, 'nombre', 'id')[$sucursalFiltro] ?? 'esta sucursal') : 'todas las sucursales' ?>..."
           class="flex-1 px-4 py-2.5 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
    <button type="submit"
            class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 rounded-xl text-sm transition">
        Buscar
    </button>
    <?php if ($busqueda): ?>
        <a href="<?= URL_BASE ?>/?url=inventario/index<?= $sucursalFiltro ? '&sucursal=' . $sucursalFiltro : '' ?>"
           class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-4 rounded-xl text-sm transition flex items-center">
            ✕ Limpiar
        </a>
    <?php endif; ?>
</form>

<?php if ($busqueda): ?>
    <p class="text-sm text-slate-500 mb-3">
        <?= count($inventario) ?> resultado<?= count($inventario) != 1 ? 's' : '' ?> para
        <span class="font-bold text-teal-700">"<?= htmlspecialchars($busqueda) ?>"</span>
        <?= $sucursalFiltro ? 'en ' . htmlspecialchars(array_column($sucursales, 'nombre', 'id')[$sucursalFiltro] ?? '') : 'en todas las sucursales' ?>
    </p>
<?php endif; ?>

<!-- En celular la tabla no cabe: se desliza de lado (overflow-x-auto)
     y min-w evita que las columnas se aplasten -->
<p class="md:hidden text-xs text-slate-400 mb-2">↔️ Deslizá la tabla hacia los lados para ver toda la información.</p>
<div class="bg-white rounded-2xl shadow overflow-x-auto">
    <table class="w-full min-w-[860px] text-sm">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-3 py-3 text-left text-slate-500">Foto</th>
                <th class="px-4 py-3 text-left text-slate-500">Producto</th>
                <th class="px-4 py-3 text-left text-slate-500">Ubicación</th>
                <th class="px-4 py-3 text-left text-slate-500">Categoría</th>
                <th class="px-4 py-3 text-left text-slate-500">Sucursal</th>
                <th class="px-4 py-3 text-right text-slate-500">Precio</th>
                <th class="px-4 py-3 text-center text-slate-500">Disponibilidad</th>
                <th class="px-4 py-3 text-right text-slate-500">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($inventario)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">Todavía no hay productos en el inventario.</td></tr>
            <?php else: ?>
                <?php foreach ($inventario as $p):
                    $total      = (int) $p['cantidad_total'];
                    $disponible = (int) $p['cantidad_disponible'];
                    $ocupadas   = $total - $disponible;
                    $porcentaje = $total > 0 ? round(($disponible / $total) * 100) : 0;

                    if ($total === 0) {
                        $colorBarra = 'bg-slate-300'; $colorTexto = 'text-slate-500';
                    } elseif ($porcentaje === 0) {
                        $colorBarra = 'bg-orange-400'; $colorTexto = 'text-orange-600';
                    } elseif ($porcentaje < 40) {
                        $colorBarra = 'bg-amber-400'; $colorTexto = 'text-amber-600';
                    } else {
                        $colorBarra = 'bg-emerald-400'; $colorTexto = 'text-emerald-600';
                    }
                ?>
                <tr class="hover:bg-teal-50">
                    <td class="px-3 py-3">
                        <?php if (!empty($p['foto_path']) && existeImagen($p['foto_path'])): ?>
                            <img src="<?= urlImagen($p['foto_path']) ?>" loading="lazy"
                                 alt="<?= htmlspecialchars($p['nombre']) ?>"
                                 onclick="soltecAbrirFoto(this.src, '<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>')"
                                 class="w-12 h-12 object-cover rounded-lg border border-slate-200 cursor-zoom-in hover:ring-2 hover:ring-teal-400 transition">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-300 text-lg">📷</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 font-bold text-slate-800 min-w-[140px]"><?= htmlspecialchars($p['nombre']) ?></td>
                    <td class="px-4 py-3">
                        <?php $ubic = ubicacionTexto($p, ''); ?>
                        <?php if ($ubic !== ''): ?>
                            <span class="inline-block bg-slate-100 text-slate-600 text-[11px] font-bold px-2 py-1 rounded-lg whitespace-nowrap">
                                <?= htmlspecialchars($ubic) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-slate-300 text-xs">sin ubicar</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($p['categoria_nombre'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-slate-600 whitespace-nowrap"><?= htmlspecialchars($p['sucursal_nombre']) ?></td>
                    <td class="px-4 py-3 text-right text-slate-700 whitespace-nowrap">Q<?= number_format($p['precio_alquiler'], 2) ?></td>
                    <td class="px-4 py-3 min-w-[120px]">
                        <div class="flex items-center justify-center gap-2">
                            <span class="text-xs font-bold <?= $colorTexto ?>"><?= $disponible ?> / <?= $total ?></span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-1.5 mt-1 max-w-[120px] mx-auto">
                            <div class="<?= $colorBarra ?> h-1.5 rounded-full" style="width: <?= $porcentaje ?>%"></div>
                        </div>
                        <?php if ($ocupadas > 0): ?>
                            <div class="text-[10px] text-slate-400 text-center mt-0.5"><?= $ocupadas ?> en uso</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="<?= URL_BASE ?>/?url=inventario/editar/<?= $p['id'] ?>"
                           class="text-teal-600 hover:underline text-xs font-bold mr-3">✏️ Editar</a>
                        <a href="<?= URL_BASE ?>/?url=inventario/eliminar/<?= $p['id'] ?>"
                           onclick="return confirm('¿Eliminar este producto del inventario?')"
                           class="text-red-500 hover:underline text-xs font-bold">🗑️ Eliminar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
