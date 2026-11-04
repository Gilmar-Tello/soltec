<?php
include RAIZ . '/app/vistas/plantillas/header.php';
function q($n) { return 'Q' . number_format((float) $n, 2); }
$mesAnterior = date('Y-m-01', strtotime('first day of last month'));
$finMesAnt   = date('Y-m-t',  strtotime('first day of last month'));
$campo = 'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none';
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">Libro de caja</h2>
        <p class="text-sm text-slate-500">Todo lo que entra y sale, con el saldo al día</p>
    </div>
    <button type="button" id="btnInformePdf"
            class="rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium px-4 py-2">
        📄 Descargar informe PDF
    </button>
</div>

<!-- Periodo -->
<form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
    <input type="hidden" name="url" value="caja/index">
    <div>
        <label for="desde" class="block text-sm text-slate-600 mb-1">Desde</label>
        <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
    </div>
    <div>
        <label for="hasta" class="block text-sm text-slate-600 mb-1">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
    </div>
    <button class="rounded-md border border-indigo-400 text-indigo-600 hover:bg-indigo-50 text-sm font-medium px-4 py-2">Ver período</button>
    <div class="text-xs text-slate-500 pb-2 flex gap-3">
        <a class="hover:underline" href="<?= URL_BASE ?>/?url=caja/index&desde=<?= date('Y-m-01') ?>&hasta=<?= date('Y-m-d') ?>">Este mes</a>
        <a class="hover:underline" href="<?= URL_BASE ?>/?url=caja/index&desde=<?= $mesAnterior ?>&hasta=<?= $finMesAnt ?>">Mes anterior</a>
    </div>
</form>

<?php if ($aviso): ?>
    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800"><?= htmlspecialchars($aviso) ?></div>
<?php endif; ?>

<!-- Resumen -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
        <div class="text-sm text-slate-500">Saldo inicial</div>
        <div class="text-lg font-bold text-slate-900"><?= q($saldoInicial) ?></div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
        <div class="text-sm text-slate-500">Entradas</div>
        <div class="text-lg font-bold text-emerald-600">+ <?= q($entradas) ?></div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
        <div class="text-sm text-slate-500">Salidas</div>
        <div class="text-lg font-bold text-red-600">− <?= q($salidas) ?></div>
    </div>
    <div class="rounded-lg border border-indigo-400 bg-white px-4 py-3">
        <div class="text-sm text-slate-500">Saldo final</div>
        <div class="text-lg font-bold <?= $saldoFinal < 0 ? 'text-red-600' : 'text-slate-900' ?>"><?= q($saldoFinal) ?></div>
    </div>
</div>

<!-- Libro -->
<h3 class="text-base font-semibold text-slate-900 mb-2">Libro de caja</h3>
<div class="bg-white rounded-lg border border-slate-200 overflow-hidden mb-8">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-700 text-sm">
            <tr>
                <th class="text-left px-4 py-3">Fecha</th>
                <th class="text-left px-4 py-3">Origen</th>
                <th class="text-left px-4 py-3">Detalle</th>
                <th class="text-right px-4 py-3">Entrada</th>
                <th class="text-right px-4 py-3">Salida</th>
                <th class="text-right px-4 py-3">Saldo</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <tr class="bg-slate-50/60">
                <td class="px-4 py-2.5 text-slate-700" colspan="5">Saldo al iniciar el período</td>
                <td class="px-4 py-2.5 text-right text-slate-700"><?= q($saldoInicial) ?></td>
            </tr>
            <?php if (empty($movimientos)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin movimientos en este período.</td></tr>
            <?php else: foreach ($movimientos as $m): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2.5 whitespace-nowrap text-slate-700"><?= date('d/m/Y', strtotime($m['creado_en'])) ?></td>
                    <td class="px-4 py-2.5"><span class="inline-block rounded px-2 py-0.5 text-xs font-semibold text-white <?= htmlspecialchars($m['color']) ?>"><?= htmlspecialchars($m['etiqueta']) ?></span></td>
                    <td class="px-4 py-2.5 text-slate-700"><?= htmlspecialchars($m['detalle']) ?></td>
                    <td class="px-4 py-2.5 text-right text-slate-800"><?= $m['entrada'] !== null ? q($m['entrada']) : '' ?></td>
                    <td class="px-4 py-2.5 text-right text-slate-800"><?= $m['salida']  !== null ? q($m['salida'])  : '' ?></td>
                    <td class="px-4 py-2.5 text-right font-medium <?= $m['saldo'] < 0 ? 'text-red-600' : 'text-slate-900' ?>"><?= q($m['saldo']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Gastos -->
<h3 class="text-base font-semibold text-slate-900 mb-2">Gastos</h3>
<form method="POST" action="<?= URL_BASE ?>/?url=caja/registrarGasto" class="bg-white rounded-lg border border-slate-200 p-4 mb-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
    <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
    <input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
    <div class="md:col-span-3">
        <label for="g_concepto" class="block text-xs text-slate-500 mb-1">Concepto</label>
        <input type="text" id="g_concepto" name="concepto" list="listaConceptos" required maxlength="255" placeholder="Concepto…" autocomplete="off" class="<?= $campo ?>">
        <datalist id="listaConceptos">
            <?php foreach ($conceptosGasto as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?>
        </datalist>
    </div>
    <div class="md:col-span-4">
        <label for="g_desc" class="block text-xs text-slate-500 mb-1">Descripción (opcional)</label>
        <input type="text" id="g_desc" name="descripcion" maxlength="500" placeholder="ej. FACTURA SEPTIEMBRE" class="<?= $campo ?>">
    </div>
    <div class="md:col-span-2">
        <label for="g_monto" class="block text-xs text-slate-500 mb-1">Monto Q</label>
        <input type="number" id="g_monto" name="monto" required min="0.01" step="0.01" placeholder="Monto Q" class="<?= $campo ?>">
    </div>
    <div class="md:col-span-2">
        <label for="g_fecha" class="block text-xs text-slate-500 mb-1">Fecha</label>
        <input type="date" id="g_fecha" name="fecha" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" class="<?= $campo ?>">
    </div>
    <div class="md:col-span-1">
        <button class="w-full rounded-md bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-3 py-2">Registrar</button>
    </div>
</form>

<?php if ($gastos): ?>
<div class="bg-white rounded-lg border border-slate-200 overflow-hidden mb-8">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
            <tr><th class="text-left px-4 py-2.5">Fecha</th><th class="text-left px-4 py-2.5">Detalle</th><th class="text-right px-4 py-2.5">Monto</th><th class="px-4 py-2.5"></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($gastos as $g): ?>
            <tr>
                <td class="px-4 py-2.5 whitespace-nowrap text-slate-700"><?= date('d/m/Y', strtotime($g['creado_en'])) ?></td>
                <td class="px-4 py-2.5 text-slate-700"><?= htmlspecialchars($g['detalle']) ?></td>
                <td class="px-4 py-2.5 text-right text-slate-800"><?= q($g['monto']) ?></td>
                <td class="px-4 py-2.5 text-right">
                    <form method="POST" action="<?= URL_BASE ?>/?url=caja/anular" class="inline" data-confirmar="¿Anular este gasto? Ya no contará en el libro.">
                        <input type="hidden" name="clase" value="egreso"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                        <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>"><input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
                        <button class="text-red-600 hover:underline text-xs">▸ Anular</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="mb-8"></div>
<?php endif; ?>

<!-- Otros ingresos -->
<h3 class="text-base font-semibold text-slate-900 mb-2">Otros ingresos</h3>
<form method="POST" action="<?= URL_BASE ?>/?url=caja/registrarIngreso" class="bg-white rounded-lg border border-slate-200 p-4 mb-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
    <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
    <input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
    <div class="md:col-span-7">
        <label for="i_concepto" class="block text-xs text-slate-500 mb-1">Concepto</label>
        <input type="text" id="i_concepto" name="concepto" required maxlength="255" placeholder="ej. Donación, préstamo, venta de equipo…" autocomplete="off" class="<?= $campo ?>">
    </div>
    <div class="md:col-span-2">
        <label for="i_monto" class="block text-xs text-slate-500 mb-1">Monto Q</label>
        <input type="number" id="i_monto" name="monto" required min="0.01" step="0.01" placeholder="Monto Q" class="<?= $campo ?>">
    </div>
    <div class="md:col-span-2">
        <label for="i_fecha" class="block text-xs text-slate-500 mb-1">Fecha</label>
        <input type="date" id="i_fecha" name="fecha" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" class="<?= $campo ?>">
    </div>
    <div class="md:col-span-1">
        <button class="w-full rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-3 py-2">Registrar</button>
    </div>
</form>

<?php if ($otrosIngresos): ?>
<div class="bg-white rounded-lg border border-slate-200 overflow-hidden mb-8">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
            <tr><th class="text-left px-4 py-2.5">Fecha</th><th class="text-left px-4 py-2.5">Detalle</th><th class="text-right px-4 py-2.5">Monto</th><th class="px-4 py-2.5"></th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($otrosIngresos as $g): ?>
            <tr>
                <td class="px-4 py-2.5 whitespace-nowrap text-slate-700"><?= date('d/m/Y', strtotime($g['creado_en'])) ?></td>
                <td class="px-4 py-2.5 text-slate-700"><?= htmlspecialchars($g['detalle']) ?></td>
                <td class="px-4 py-2.5 text-right text-slate-800"><?= q($g['monto']) ?></td>
                <td class="px-4 py-2.5 text-right">
                    <form method="POST" action="<?= URL_BASE ?>/?url=caja/anular" class="inline" data-confirmar="¿Anular este ingreso? Ya no contará en el libro.">
                        <input type="hidden" name="clase" value="ingreso"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                        <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>"><input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
                        <button class="text-red-600 hover:underline text-xs">▸ Anular</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="mb-8"></div>
<?php endif; ?>

<!-- Saldo inicial del libro -->
<details class="bg-white rounded-lg border border-slate-200 p-4 mb-6">
    <summary class="cursor-pointer text-sm font-medium text-slate-700">⚙️ Saldo inicial del libro</summary>
    <form method="POST" action="<?= URL_BASE ?>/?url=caja/saldoInicial" class="mt-3 flex flex-wrap items-end gap-3">
        <div>
            <label for="si_monto" class="block text-xs text-slate-500 mb-1">Saldo con el que arranca (Q)</label>
            <input type="number" id="si_monto" name="saldo_inicial" step="0.01" value="<?= htmlspecialchars(number_format((float) $neg['saldo_inicial'], 2, '.', '')) ?>" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="si_fecha" class="block text-xs text-slate-500 mb-1">Cuenta desde (opcional)</label>
            <input type="date" id="si_fecha" name="saldo_inicial_fecha" value="<?= htmlspecialchars((string) ($neg['saldo_inicial_fecha'] ?? '')) ?>" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-md border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2">Guardar</button>
        <p class="text-xs text-slate-500 basis-full">Si pones una fecha, el libro no cuenta nada anterior a ella: es el saldo con el que empiezas ese día.</p>
    </form>
</details>

<!-- Informe PDF (se genera en el navegador, como los demás PDF de Brix) -->
<script src="<?= URL_BASE ?>/app/public/vendor/jspdf-2.5.1.umd.min.js"></script>
<script src="<?= URL_BASE ?>/app/public/vendor/jspdf-autotable-3.8.2.min.js"></script>
<script>
const CAJA = <?= jsonJs($datosPdf) ?>;

// Confirmación al anular
document.querySelectorAll('form[data-confirmar]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.getAttribute('data-confirmar'))) e.preventDefault(); });
});

function quetzales(n) {
    return 'Q' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function descargarInforme() {
    if (!window.jspdf || !window.jspdf.jsPDF) { alert('No se pudo cargar el generador de PDF. Revisa tu conexión e intenta de nuevo.'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'pt', format: 'letter' });
    const periodo = CAJA.esMes ? 'del mes' : 'del período';

    // Encabezado
    doc.setFontSize(15); doc.setTextColor(15, 23, 42); doc.text('Informe de caja', 40, 44);
    doc.setFontSize(10); doc.setTextColor(100, 116, 139);
    doc.text(CAJA.negocio || '', 40, 60);
    doc.text('Del ' + CAJA.desde + ' al ' + CAJA.hasta, 572, 44, { align: 'right' });
    const ahora = new Date();
    doc.text('Generado: ' + ahora.toLocaleDateString('es-GT') + ' ' + ahora.toLocaleTimeString('es-GT', { hour: '2-digit', minute: '2-digit' }), 572, 60, { align: 'right' });

    // Filas del informe (misma disposición de la hoja de cálculo)
    const filas = [];
    const der = { halign: 'right' };
    filas.push({ t: 'saldo', c: [{ content: 'Saldo anterior', colSpan: 4 }, { content: quetzales(CAJA.saldoAnterior), styles: der }] });

    filas.push({ t: 'seccion', c: [{ content: 'Ingresos', colSpan: 5 }] });
    filas.push({ t: 'cabecera', c: ['No.', 'Fecha', 'Descripción', { content: 'Subtotal', styles: der }, { content: 'Total', styles: der }] });
    if (CAJA.ingresos.length === 0) filas.push({ t: 'dato', c: [{ content: 'Sin ingresos en el período', colSpan: 5, styles: { fontStyle: 'italic', textColor: [148, 163, 184] } }] });
    CAJA.ingresos.forEach(function (r, i) {
        filas.push({ t: 'dato', c: [String(i + 1), r.fecha, r.detalle, { content: quetzales(r.monto), styles: der }, ''] });
    });
    filas.push({ t: 'total', c: [{ content: 'Total', colSpan: 4 }, { content: quetzales(CAJA.totalIngresos), styles: der }] });

    filas.push({ t: 'seccion', c: [{ content: 'Egresos', colSpan: 5 }] });
    filas.push({ t: 'cabecera', c: ['No.', 'Fecha', 'Descripción', { content: 'Subtotal', styles: der }, { content: 'Total', styles: der }] });
    if (CAJA.egresos.length === 0) filas.push({ t: 'dato', c: [{ content: 'Sin egresos en el período', colSpan: 5, styles: { fontStyle: 'italic', textColor: [148, 163, 184] } }] });
    CAJA.egresos.forEach(function (r, i) {
        filas.push({ t: 'dato', c: [String(i + 1), r.fecha, r.detalle, { content: quetzales(r.monto), styles: der }, ''] });
    });
    filas.push({ t: 'total', c: [{ content: 'Total', colSpan: 4 }, { content: quetzales(CAJA.totalEgresos), styles: der }] });

    filas.push({ t: 'seccion', c: [{ content: 'Resumen', colSpan: 5 }] });
    filas.push({ t: 'resumen', c: [{ content: 'Ingresos ' + periodo + ' más saldo anterior', colSpan: 3 }, { content: quetzales(CAJA.saldoAnterior + CAJA.totalIngresos), styles: der }, ''] });
    filas.push({ t: 'resumen', c: [{ content: 'Egresos ' + periodo, colSpan: 3 }, { content: quetzales(CAJA.totalEgresos), styles: der }, ''] });
    filas.push({ t: 'final',   c: [{ content: 'Saldo para el próximo ' + (CAJA.esMes ? 'mes' : 'período'), colSpan: 3 }, { content: quetzales(CAJA.saldoFinal), styles: der }, ''] });

    doc.autoTable({
        body: filas.map(function (f) { return f.c; }),
        startY: 78,
        theme: 'grid',
        styles: { fontSize: 9, cellPadding: 4, textColor: [51, 65, 85], lineColor: [203, 213, 225], lineWidth: 0.5 },
        columnStyles: { 0: { cellWidth: 36 }, 1: { cellWidth: 66 }, 3: { cellWidth: 86 }, 4: { cellWidth: 86 } },
        margin: { left: 40, right: 40 },
        didParseCell: function (d) {
            const tipo = filas[d.row.index] && filas[d.row.index].t;
            if (tipo === 'seccion')  { d.cell.styles.fontStyle = 'bold'; d.cell.styles.fillColor = [241, 245, 249]; d.cell.styles.textColor = [15, 23, 42]; }
            if (tipo === 'cabecera') { d.cell.styles.fontStyle = 'bold'; d.cell.styles.textColor = [71, 85, 105]; }
            if (tipo === 'saldo' || tipo === 'total') { d.cell.styles.fontStyle = 'bold'; }
            if (tipo === 'final')    { d.cell.styles.fontStyle = 'bold'; d.cell.styles.fillColor = [238, 242, 255]; d.cell.styles.textColor = [15, 23, 42]; }
        },
    });

    doc.save('libro-de-caja-' + CAJA.desdeIso + '_' + CAJA.hastaIso + '.pdf');
}
document.getElementById('btnInformePdf').addEventListener('click', descargarInforme);
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
