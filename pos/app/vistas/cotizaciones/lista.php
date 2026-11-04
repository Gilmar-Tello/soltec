<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<?php if (isset($_SESSION['exito'])): ?>
    <div class="bg-green-50 border border-green-300 text-green-800 rounded-xl p-4 mb-5">
        ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
    </div>
    <?php unset($_SESSION['exito']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h2 class="text-2xl font-extrabold text-green-800">📝 <?= htmlspecialchars($tituloPagina) ?></h2>
    <a href="<?= URL_BASE ?>/?url=pos/venta"
       class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl text-sm transition text-center">
        ➕ Nueva Venta
    </a>
</div>

<!-- Filtros -->
<form method="GET" class="bg-white rounded-2xl shadow p-4 mb-6 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="url" value="cotizaciones/index">
    <div>
        <label class="block text-xs text-gray-500 mb-1">Desde</label>
        <input type="date" name="desde" value="<?= $desde ?>"
               class="px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Hasta</label>
        <input type="date" name="hasta" value="<?= $hasta ?>"
               class="px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">Estado</label>
        <select name="estado" class="px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
            <option value="">Todas</option>
            <option value="abierta" <?= $estado==='abierta'?'selected':'' ?>>📝 Abiertas</option>
            <option value="convertida" <?= $estado==='convertida'?'selected':'' ?>>✅ Convertidas</option>
            <option value="anulada" <?= $estado==='anulada'?'selected':'' ?>>🗑️ Eliminadas</option>
        </select>
    </div>
    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-xl text-sm transition">
        Filtrar
    </button>
</form>

<!-- Tabla -->
<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-green-700 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Fecha</th>
                    <th class="px-4 py-3 text-left">Cliente</th>
                    <th class="px-4 py-3 text-left">Usuario</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-center">Ver</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($cotizaciones)): ?>
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay cotizaciones en este rango.</td></tr>
                <?php else: ?>
                <?php foreach ($cotizaciones as $c): ?>
                <tr class="hover:bg-green-50 transition">
                    <td class="px-4 py-3 text-gray-400 font-mono text-xs">#<?= $c['id'] ?></td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= date('d/m/Y H:i', strtotime($c['creado_en'])) ?></td>
                    <td class="px-4 py-3 font-bold text-gray-700"><?= htmlspecialchars($c['cliente_nombre'] ?? 'Sin cliente') ?></td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($c['usuario_nombre']) ?></td>
                    <td class="px-4 py-3 text-right font-bold text-green-700">Q<?= number_format($c['total'], 2) ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($c['estado'] === 'abierta'): ?>
                            <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-1 rounded-full">📝 Abierta</span>
                        <?php elseif ($c['estado'] === 'convertida'): ?>
                            <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ Convertida</span>
                        <?php else: ?>
                            <span class="bg-gray-100 text-gray-500 text-xs font-bold px-2 py-1 rounded-full">🗑️ Eliminada</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <a href="<?= URL_BASE ?>/?url=cotizaciones/ver/<?= $c['id'] ?>"
                           class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-1 rounded-lg transition">👁️ Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
