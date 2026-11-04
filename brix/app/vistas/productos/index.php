<?php include RAIZ . '/app/vistas/plantillas/header.php';
function num($n){ return rtrim(rtrim(number_format((float)$n,2),'0'),'.'); }
$soloBajo = !empty($_GET['bajo']);

// Datos para el PDF que se genera en el navegador (respeta el filtro actual)
$pdfData = array_map(function($p) {
    $stockTxt = $p['es_compuesto']
        ? ((int)$p['disponible'] . ' (hace)')
        : (rtrim(rtrim(number_format((float)$p['stock'],2),'0'),'.') . ' ' . ($p['unidad'] ?? ''));
    return [
        'nombre'   => $p['nombre'] . ($p['codigo'] ? ' (' . $p['codigo'] . ')' : ''),
        'categoria'=> $p['categoria'] ?? '—',
        'stock'    => trim($stockTxt),
        'minimo'   => rtrim(rtrim(number_format((float)$p['stock_minimo'],2),'0'),'.'),
        'costo'    => 'Q' . number_format($p['precio_compra'],2),
        'venta'    => 'Q' . number_format($p['precio_venta'],2),
        'vence'    => $p['fecha_vencimiento'] ? date('d/m/Y', strtotime($p['fecha_vencimiento'])) : '—',
    ];
}, $productos);
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">Productos e inventario</h2>
        <p class="text-sm text-slate-500">Valor total al costo: <span class="font-medium text-slate-700">Q<?= number_format($valorTotal, 2) ?></span></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if ($soloBajo): ?>
            <a href="<?= URL_BASE ?>/?url=productos/index" class="rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2">Ver todos</a>
        <?php else: ?>
            <a href="<?= URL_BASE ?>/?url=productos/index&bajo=1" class="rounded-md border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-700 text-sm font-medium px-4 py-2">Stock bajo</a>
        <?php endif; ?>
        <button type="button" onclick="descargarPDF()" class="rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2">Descargar PDF</button>
        <a href="<?= URL_BASE ?>/?url=productos/cuadre" class="rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2">Cuadre</a>
        <a href="<?= URL_BASE ?>/?url=productos/nuevo" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium px-4 py-2">Nuevo producto</a>
    </div>
</div>

<form method="GET" class="bg-white rounded-lg border border-slate-200 p-4 mb-4 flex flex-wrap items-end gap-3">
    <input type="hidden" name="url" value="productos/index">
    <div class="flex-1 min-w-[200px]">
        <label class="block text-xs text-slate-500 mb-1">Buscar</label>
        <input type="text" name="busqueda" value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>" placeholder="Nombre o código" class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm">
    </div>
    <div>
        <label class="block text-xs text-slate-500 mb-1">Categoría</label>
        <select name="categoria" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
            <option value="">Todas</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (intval($_GET['categoria']??0)===$c['id'])?'selected':'' ?>><?= htmlspecialchars($c['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-700 pb-1.5">
        <input type="checkbox" name="bajo" value="1" <?= $soloBajo ? 'checked' : '' ?> class="rounded border-slate-300">
        Solo stock bajo
    </label>
    <button class="rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-1.5">Filtrar</button>
</form>

<?php if ($soloBajo): ?>
<div class="mb-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-4 py-2">
    Mostrando solo productos simples con stock igual o menor a su mínimo.
</div>
<?php endif; ?>

<div class="bg-white rounded-lg border border-slate-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
            <tr>
                <th class="text-left px-4 py-3">Producto</th>
                <th class="text-left px-4 py-3">Categoría</th>
                <th class="text-right px-4 py-3">Stock</th>
                <th class="text-right px-4 py-3">Costo</th>
                <th class="text-right px-4 py-3">Venta</th>
                <th class="text-left px-4 py-3">Vence</th>
                <th class="text-right px-4 py-3">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($productos)): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">
                    <?= $soloBajo ? 'Ningún producto con stock bajo.' : 'Sin productos.' ?>
                    <?php if (!$soloBajo): ?><a href="<?= URL_BASE ?>/?url=productos/nuevo" class="text-indigo-600 hover:underline">Crear el primero</a>.<?php endif; ?>
                </td></tr>
            <?php else: foreach ($productos as $p):
                $bajo = !$p['es_compuesto'] && (float)$p['stock'] <= (float)$p['stock_minimo'];
                $vencido = $p['fecha_vencimiento'] && strtotime($p['fecha_vencimiento']) < strtotime(date('Y-m-d'));
            ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-800"><?= htmlspecialchars($p['nombre']) ?></div>
                        <div class="text-xs text-slate-400">
                            <?= htmlspecialchars($p['codigo'] ?? '') ?>
                            <?php if ($p['es_compuesto']): ?><span class="ml-1 rounded bg-indigo-50 text-indigo-600 px-1.5 py-0.5">compuesto</span><?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($p['categoria'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-right">
                        <?php if ($p['es_compuesto']): ?>
                            <span class="text-slate-700"><?= (int)$p['disponible'] ?></span>
                            <span class="text-xs text-slate-400">puede hacer</span>
                        <?php else: ?>
                            <span class="<?= $bajo ? 'text-amber-600 font-semibold' : 'text-slate-700' ?>"><?= num($p['stock']) ?></span>
                            <span class="text-xs text-slate-400"><?= htmlspecialchars($p['unidad'] ?? '') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right text-slate-500">Q<?= number_format($p['precio_compra'], 2) ?></td>
                    <td class="px-4 py-3 text-right text-slate-700">Q<?= number_format($p['precio_venta'], 2) ?></td>
                    <td class="px-4 py-3 text-sm <?= $vencido ? 'text-red-600' : 'text-slate-500' ?>">
                        <?= $p['fecha_vencimiento'] ? date('d/m/Y', strtotime($p['fecha_vencimiento'])) . ($vencido ? ' (vencido)' : '') : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <?php if ($p['es_compuesto']): ?>
                            <a href="<?= URL_BASE ?>/?url=productos/componentes/<?= $p['id'] ?>" class="text-slate-500 hover:underline text-xs mr-3">Receta</a>
                        <?php else: ?>
                            <a href="<?= URL_BASE ?>/?url=productos/entrada/<?= $p['id'] ?>" class="text-emerald-600 hover:underline text-xs mr-3">Entrada</a>
                        <?php endif; ?>
                        <a href="<?= URL_BASE ?>/?url=productos/editar/<?= $p['id'] ?>" class="text-indigo-600 hover:underline text-xs mr-3">Editar</a>
                        <a href="<?= URL_BASE ?>/?url=productos/toggle/<?= $p['id'] ?>" class="text-red-500 hover:underline text-xs" onclick="return confirm('¿Desactivar este producto?')">Baja</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Generación de PDF en el navegador (sin abrir pestañas ni diálogo de impresión) -->
<script src="<?= URL_BASE ?>/app/public/vendor/jspdf-2.5.1.umd.min.js"></script>
<script src="<?= URL_BASE ?>/app/public/vendor/jspdf-autotable-3.8.2.min.js"></script>
<script>
const PDF_DATA   = <?= jsonJs($pdfData) ?>;
const PDF_BAJO   = <?= $soloBajo ? 'true' : 'false' ?>;
const PDF_NEG    = <?= jsonJs($_SESSION['nombre_negocio'] ?? '') ?>;

function descargarPDF() {
    if (!window.jspdf || !window.jspdf.jsPDF) { alert('No se pudo cargar el generador de PDF. Revisá tu conexión e intentá de nuevo.'); return; }
    if (PDF_DATA.length === 0) { alert('No hay productos para exportar.'); return; }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'pt', format: 'letter' });
    const titulo = PDF_BAJO ? 'Productos con stock bajo' : 'Listado de productos';
    const ahora  = new Date();
    const fecha  = ahora.toLocaleDateString('es-GT') + ' ' + ahora.toLocaleTimeString('es-GT', {hour:'2-digit', minute:'2-digit'});

    // Encabezado
    doc.setFontSize(16); doc.setTextColor(15,23,42); doc.text('Brix', 40, 42);
    doc.setFontSize(10); doc.setTextColor(100,116,139); doc.text(PDF_NEG || '', 40, 58);
    doc.setFontSize(12); doc.setTextColor(30,41,59); doc.text(titulo, 572, 42, { align: 'right' });
    doc.setFontSize(9);  doc.setTextColor(100,116,139); doc.text(fecha, 572, 58, { align: 'right' });

    // Tabla
    const head = PDF_BAJO
        ? [['Producto','Categoría','Stock','Mínimo','Costo','Venta','Vence']]
        : [['Producto','Categoría','Stock','Costo','Venta','Vence']];
    const body = PDF_DATA.map(p => PDF_BAJO
        ? [p.nombre, p.categoria, p.stock, p.minimo, p.costo, p.venta, p.vence]
        : [p.nombre, p.categoria, p.stock, p.costo, p.venta, p.vence]);

    doc.autoTable({
        head: head,
        body: body,
        startY: 78,
        styles: { fontSize: 9, cellPadding: 4, textColor: [51,65,85] },
        headStyles: { fillColor: [241,245,249], textColor: [71,85,105], fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [250,250,252] },
        margin: { left: 40, right: 40 },
    });

    let y = doc.lastAutoTable.finalY + 18;
    doc.setFontSize(9); doc.setTextColor(100,116,139);
    doc.text('Total de productos: ' + PDF_DATA.length, 40, y);

    const nombreArchivo = (PDF_BAJO ? 'stock-bajo' : 'productos') + '-' +
        ahora.getFullYear() + String(ahora.getMonth()+1).padStart(2,'0') + String(ahora.getDate()).padStart(2,'0') + '.pdf';
    doc.save(nombreArchivo);
}
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
