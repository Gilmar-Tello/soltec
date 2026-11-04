<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="flex items-center gap-3 mb-6">
    <a href="<?= URL_BASE ?>/?url=reservas/detalle/<?= $reserva['id'] ?>" class="text-teal-700 font-bold text-xl">←</a>
    <div>
        <h2 class="text-2xl font-extrabold text-teal-800">✏️ Modificar reserva #<?= $reserva['id'] ?></h2>
        <p class="text-sm text-slate-500">
            <?= htmlspecialchars($reserva['nombre_completo']) ?> ·
            <?= htmlspecialchars($reserva['sucursal_nombre']) ?> ·
            uso <?= fechaCorta($reserva['fecha_uso']) ?>
        </p>
    </div>
</div>

<?php
// Valores guardados de la reserva. Una reserva anterior a las horas no
// las tiene: sus campos de hora quedan vacíos y no son obligatorios.
$eFechaEntrega = !empty($reserva['fecha_entrega']) ? substr($reserva['fecha_entrega'], 0, 10) : substr($reserva['fecha_uso'], 0, 10);
$eFechaUso     = substr($reserva['fecha_uso'], 0, 10);
$eFechaRec     = !empty($reserva['fecha_recoleccion_programada'])
    ? substr($reserva['fecha_recoleccion_programada'], 0, 10)
    : (new DateTimeImmutable($eFechaUso))->modify('+1 day')->format('Y-m-d');
$eHoraEntrega  = substr((string) ($reserva['hora_entrega'] ?? ''), 0, 5);
$eHoraUso      = substr((string) ($reserva['hora_uso'] ?? ''), 0, 5);
$eHoraRec      = substr((string) ($reserva['hora_recoleccion'] ?? ''), 0, 5);
$eSinHoras     = ($eHoraEntrega === '' && $eHoraUso === '' && $eHoraRec === '');
$reqHora       = $eSinHoras ? '' : 'required';
?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-sm text-amber-800">
    Podés agregar productos, cambiar cantidades, quitar lo que el cliente ya no quiere y ajustar las fechas, las horas
    y el costo inicial. Las cantidades se revisan contra lo libre entre la entrega y la recolección de esta reserva.
    El cliente no se cambia acá: si es otro, cancelá la reserva y hacé una nueva.
</div>

<form method="POST" action="<?= URL_BASE ?>/?url=reservas/actualizar" id="formEditar">
<input type="hidden" name="reserva_id" value="<?= $reserva['id'] ?>">

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- COLUMNA IZQUIERDA -->
    <div class="space-y-5">

        <!-- Dirección de entrega -->
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-700 mb-2">📍 Dirección de entrega</h3>
            <textarea name="direccion_entrega" rows="2"
                      placeholder="Dónde se entregan los bienes"
                      class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm"><?= htmlspecialchars(direccionEntrega($reserva)) ?></textarea>
            <p class="text-[11px] text-slate-400 mt-1">
                Dirección del cliente: <?= htmlspecialchars($reserva['direccion'] ?: '—') ?>
            </p>
        </div>

        <!-- Fechas y horas -->
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-700 mb-3">📅 Fechas y horas</h3>
            <div class="space-y-3">
                <?php
                $filasFecha = [
                    ['Entrega',     'fechaEntrega',     'fecha_entrega',     $eFechaEntrega, 'horaEntrega',     'hora_entrega',     $eHoraEntrega],
                    ['Uso',         'fechaUso',         'fecha_uso',         $eFechaUso,     'horaUso',         'hora_uso',         $eHoraUso],
                    ['Recolección', 'fechaRecoleccion', 'fecha_recoleccion', $eFechaRec,     'horaRecoleccion', 'hora_recoleccion', $eHoraRec],
                ];
                foreach ($filasFecha as [$rotulo, $idF, $nomF, $valF, $idH, $nomH, $valH]): ?>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1"><?= $rotulo ?></label>
                    <div class="grid grid-cols-5 gap-2">
                        <input type="date" id="<?= $idF ?>" name="<?= $nomF ?>" value="<?= $valF ?>" required
                               class="col-span-3 px-3 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
                        <input type="time" id="<?= $idH ?>" name="<?= $nomH ?>" value="<?= $valH ?>" <?= $reqHora ?>
                               class="col-span-2 px-3 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="avisoFechas" class="hidden mt-3 text-xs font-bold text-orange-600 bg-orange-50 border border-orange-200 rounded-lg px-3 py-2"></div>
            <p class="text-[11px] text-slate-400 mt-2">
                Un producto queda libre otra vez desde la hora de recolección.
                <?php if ($eSinHoras): ?>Esta reserva es anterior a las horas: podés dejarlas vacías o completarlas.<?php endif; ?>
            </p>
        </div>

        <!-- Costo inicial -->
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-700 mb-1">➕ Costo inicial <span class="text-xs font-normal text-slate-400">(opcional)</span></h3>
            <p class="text-[11px] text-slate-400 mb-3">Un cargo extra por ubicación, flete u otra razón. Se suma al total.</p>
            <input type="text" id="costoDesc" name="costo_inicial_desc" maxlength="150"
                   value="<?= htmlspecialchars((string) ($reserva['costo_inicial_desc'] ?? '')) ?>"
                   placeholder="Descripción (ej. flete a aldea)"
                   class="w-full px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm mb-2">
            <div class="flex items-center gap-3">
                <span class="text-slate-500 font-bold text-sm">Q</span>
                <input type="number" id="costoMonto" name="costo_inicial_monto" min="0" step="0.01"
                       value="<?= number_format((float) ($reserva['costo_inicial_monto'] ?? 0), 2, '.', '') ?>"
                       class="flex-1 px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm"
                       oninput="calcularTotal()">
            </div>
        </div>

        <!-- Descuento -->
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-700 mb-3">🏷️ Descuento</h3>
            <div class="flex items-center gap-3">
                <span class="text-slate-500 font-bold text-sm">Q</span>
                <input type="number" name="descuento" id="descuentoInput"
                       min="0" step="0.01" value="<?= number_format((float) $reserva['descuento'], 2, '.', '') ?>"
                       class="flex-1 px-4 py-2 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm"
                       oninput="calcularTotal()">
            </div>
        </div>

        <!-- Resumen -->
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-700 mb-3">🧾 Resumen</h3>
            <div class="space-y-1">
                <div class="flex justify-between text-sm text-slate-500">
                    <span>Subtotal</span><span>Q<span id="subtotalReserva">0.00</span></span>
                </div>
                <div id="filaDescuento" class="hidden flex justify-between text-sm text-red-500">
                    <span>Descuento</span><span>-Q<span id="descuentoMostrar">0.00</span></span>
                </div>
                <div id="filaCosto" class="hidden flex justify-between text-sm text-slate-600">
                    <span id="costoEtiqueta">Costo inicial</span><span>+Q<span id="costoMostrar">0.00</span></span>
                </div>
                <div class="flex justify-between font-extrabold text-teal-700 text-lg border-t border-slate-100 pt-2">
                    <span>TOTAL</span><span>Q<span id="totalReserva">0.00</span></span>
                </div>
                <div class="text-xs text-slate-400 pt-1">
                    Total anterior: Q<?= number_format($reserva['total'], 2) ?>
                </div>
            </div>

            <button type="submit"
                    class="w-full mt-4 bg-teal-600 hover:bg-teal-700 text-white font-extrabold py-3.5 rounded-2xl transition shadow">
                💾 Guardar cambios
            </button>
            <a href="<?= URL_BASE ?>/?url=reservas/detalle/<?= $reserva['id'] ?>"
               class="block text-center mt-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-2.5 rounded-2xl transition text-sm">
                Cancelar
            </a>
        </div>
    </div>

    <!-- COLUMNA DERECHA: productos -->
    <div>
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-700 mb-3">📦 Productos de la reserva</h3>

            <div class="relative mb-4">
                <input type="text" id="buscarProducto" placeholder="Buscar producto para agregar..."
                       autocomplete="off"
                       class="w-full px-4 py-2.5 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-sm">
                <div id="resultadosProducto"
                     class="hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden z-20 max-h-56 overflow-y-auto"></div>
            </div>

            <div id="lineasProductos" class="space-y-3"></div>
            <div id="sinProductos" class="hidden text-xs text-orange-500 text-center py-3 font-bold">
                La reserva quedaría vacía. Agregá al menos un producto.
            </div>
        </div>
    </div>
</div>
</form>

<script>
const URL_BASE    = '<?= URL_BASE ?>';
const SUCURSAL_ID = <?= (int) $reserva['sucursal_id'] ?>;
const RESERVA_ID  = <?= (int) $reserva['id'] ?>;

// Fechas y horas de esta reserva (los campos del formulario): el buscador
// pregunta lo libre entre la entrega y la recolección, sin contar esta
// misma reserva (excluir). Texto 'AAAA-MM-DD' tal cual, sin Date.
const fEntrega = document.getElementById('fechaEntrega');
const fUso     = document.getElementById('fechaUso');
const fRecoge  = document.getElementById('fechaRecoleccion');
const hEntrega = document.getElementById('horaEntrega');
const hUso     = document.getElementById('horaUso');
const hRecoge  = document.getElementById('horaRecoleccion');
const avisoFechas = document.getElementById('avisoFechas');

function comparar(f1, h1, f2, h2) {
    if (f1 !== f2) return f1 < f2 ? -1 : 1;
    if (!h1 || !h2) return 0;
    return h1 < h2 ? -1 : (h1 > h2 ? 1 : 0);
}
function mensajeOrden() {
    const e = fEntrega.value, u = fUso.value, r = fRecoge.value;
    if (e && u && comparar(e, hEntrega.value, u, hUso.value) > 0)  return 'El uso no puede ser antes de la entrega.';
    if (u && r && comparar(u, hUso.value, r, hRecoge.value) > 0)    return 'La recolección no puede ser antes del uso.';
    if (e && r && (comparar(e, hEntrega.value, r, hRecoge.value) > 0 ||
        (e === r && hEntrega.value && hRecoge.value && hRecoge.value <= hEntrega.value))) {
        return 'La recolección tiene que ser después de la entrega.';
    }
    return '';
}
function paramFechas() {
    if (!fEntrega.value || !fRecoge.value || mensajeOrden()) return '';
    const desde = `${fEntrega.value}T${hEntrega.value || '00:00'}`;
    const hasta = `${fRecoge.value}T${hRecoge.value || '23:59'}`;
    return `&desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}&excluir=${RESERVA_ID}`;
}

// Lo que la reserva tiene ahorita. 'tope' es lo maximo que se puede
// poner: lo libre en las fechas de esta reserva (sin contarse a si misma).
const lineas = {};

<?php foreach ($detalle as $d): ?>
lineas[<?= (int) $d['inventario_id'] ?>] = {
    id:      <?= (int) $d['inventario_id'] ?>,
    nombre:  <?= jsonJs($d['producto_nombre']) ?>,
    precio:  <?= (float) $d['precio_unitario'] ?>,
    cantidad:<?= (int) $d['cantidad'] ?>,
    dias:    <?= max(1, (int) $d['dias']) ?>,
    tipo:    <?= jsonJs($d['tipo_precio'] ?? 'evento') ?>,
    tope:    <?= (int) $d['cantidad'] + (int) $d['cantidad_disponible'] ?>,
    foto:    <?= jsonJs((!empty($d['foto_path']) && existeImagen($d['foto_path'])) ? urlImagen($d['foto_path']) : '') ?>,
    ubicacion: <?= jsonJs(ubicacionTexto($d, '')) ?>,
    original: true
};
<?php endforeach; ?>

const contenedor = document.getElementById('lineasProductos');

function escapar(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function pintarLineas() {
    contenedor.innerHTML = '';
    const ids = Object.keys(lineas);

    document.getElementById('sinProductos').classList.toggle('hidden', ids.length > 0);

    ids.forEach(id => {
        const l = lineas[id];
        const div = document.createElement('div');
        div.className = 'bg-slate-50 border border-slate-200 rounded-xl p-3 space-y-2';
        div.innerHTML = `
            <input type="hidden" name="productos[${id}][inventario_id]" value="${id}">
            <div class="flex items-center gap-2">
                ${l.foto ? `<img src="${l.foto}" alt="" loading="lazy"
                      onclick="soltecAbrirFoto('${escapar(l.foto)}', ${escapar(JSON.stringify(l.nombre))})"
                      class="w-9 h-9 object-cover rounded-lg border border-slate-200 cursor-zoom-in shrink-0">` : ''}
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-bold text-slate-700">${escapar(l.nombre)}</div>
                    ${l.ubicacion ? `<div class="text-[10px] text-slate-400">📍 ${escapar(l.ubicacion)}</div>` : ''}
                </div>
                <span class="text-teal-600 text-xs font-bold">Q${l.precio.toFixed(2)}/u</span>
                <button type="button" onclick="quitar(${id})"
                        class="text-red-400 hover:text-red-600 font-bold">✕</button>
            </div>
            <div class="grid grid-cols-3 gap-2 items-end">
                <div>
                    <label class="text-xs text-slate-500 block mb-0.5">Cantidad</label>
                    <input type="number" name="productos[${id}][cantidad]" min="1" max="${l.tope}"
                           value="${l.cantidad}"
                           class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-sm text-center focus:border-teal-500 focus:outline-none"
                           oninput="cambiarCantidad(${id}, this)">
                    <div class="text-[10px] text-slate-400 text-center mt-0.5">máx. ${l.tope}</div>
                </div>
                <div>
                    <label class="text-xs text-slate-500 block mb-0.5">Modalidad</label>
                    <select name="productos[${id}][tipo]"
                            class="w-full px-2 py-1.5 border border-slate-300 rounded-lg text-xs focus:border-teal-500 focus:outline-none"
                            onchange="cambiarTipo(${id}, this.value)">
                        <option value="evento" ${l.tipo !== 'dia' ? 'selected' : ''}>Por evento</option>
                        <option value="dia"    ${l.tipo === 'dia' ? 'selected' : ''}>Por día</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500 block mb-0.5">Días</label>
                    <input type="number" name="productos[${id}][dias]" min="1" value="${l.dias}"
                           ${l.tipo === 'dia' ? '' : 'disabled'}
                           class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-sm text-center focus:border-teal-500 focus:outline-none ${l.tipo === 'dia' ? '' : 'bg-slate-100'}"
                           oninput="cambiarDias(${id}, this.value)">
                </div>
            </div>
            <div class="text-right text-xs text-slate-400">
                Subtotal: <span class="font-bold text-slate-600">Q${(l.precio * l.cantidad * (l.tipo === 'dia' ? l.dias : 1)).toFixed(2)}</span>
                ${l.original ? '' : '<span class="ml-2 text-teal-600 font-bold">nuevo</span>'}
            </div>
        `;
        contenedor.appendChild(div);
    });

    calcularTotal();
}

function cambiarCantidad(id, input) {
    let valor = parseInt(input.value) || 0;
    if (valor > lineas[id].tope) { valor = lineas[id].tope; input.value = valor; }
    if (valor < 1) valor = 1;
    lineas[id].cantidad = valor;
    pintarLineas();
}

function cambiarTipo(id, tipo) { lineas[id].tipo = tipo; pintarLineas(); }
function cambiarDias(id, dias) { lineas[id].dias = Math.max(1, parseInt(dias) || 1); pintarLineas(); }

function quitar(id) {
    if (!confirm('¿Quitar este producto de la reserva?')) return;
    delete lineas[id];
    pintarLineas();
}

function calcularTotal() {
    let subtotal = 0;
    Object.values(lineas).forEach(l => {
        subtotal += l.precio * l.cantidad * (l.tipo === 'dia' ? l.dias : 1);
    });
    const desc  = Math.min(parseFloat(document.getElementById('descuentoInput').value) || 0, subtotal);
    // El costo inicial va aparte: el descuento solo rebaja los productos
    const costo = Math.max(parseFloat(document.getElementById('costoMonto').value) || 0, 0);
    document.getElementById('subtotalReserva').textContent  = subtotal.toFixed(2);
    document.getElementById('descuentoMostrar').textContent = desc.toFixed(2);
    document.getElementById('totalReserva').textContent     = (subtotal - desc + costo).toFixed(2);
    document.getElementById('filaDescuento').classList.toggle('hidden', desc <= 0);
    document.getElementById('costoMostrar').textContent     = costo.toFixed(2);
    document.getElementById('costoEtiqueta').textContent    = document.getElementById('costoDesc').value.trim() || 'Costo inicial';
    document.getElementById('filaCosto').classList.toggle('hidden', costo <= 0);
}

// ── Buscar productos para agregar ──────────────────────────────
const inputProducto      = document.getElementById('buscarProducto');
const resultadosProducto = document.getElementById('resultadosProducto');
let timeoutProducto;

inputProducto.addEventListener('input', () => {
    clearTimeout(timeoutProducto);
    const q = inputProducto.value.trim();
    if (q.length < 1) { resultadosProducto.classList.add('hidden'); return; }
    timeoutProducto = setTimeout(() => {
        fetch(`${URL_BASE}/?url=reservas/inventarioajax&sucursal_id=${SUCURSAL_ID}${paramFechas()}&q=${encodeURIComponent(q)}`,
              { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.length) {
                    resultadosProducto.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400">Sin resultados.</div>';
                    resultadosProducto.classList.remove('hidden'); return;
                }
                resultadosProducto.innerHTML = data.map(p => {
                    const yaEsta = !!lineas[p.id];
                    const mini = p.foto_url
                        ? `<img src="${p.foto_url}" alt="" loading="lazy" class="w-10 h-10 object-cover rounded-lg border border-slate-200 shrink-0">`
                        : `<div class="w-10 h-10 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-300 text-sm shrink-0">📷</div>`;
                    return `<div onclick="agregar(${p.id}, ${escapar(JSON.stringify(p.nombre))}, ${p.precio_alquiler}, ${p.cantidad_disponible}, '${p.foto_url}', ${escapar(JSON.stringify(p.ubicacion || ''))})"
                          class="flex items-center gap-3 px-3 py-2.5 cursor-pointer hover:bg-teal-50 text-sm border-b border-slate-100 last:border-0">
                        ${mini}
                        <div class="min-w-0">
                            <div><span class="font-bold">${escapar(p.nombre)}</span>
                                 <span class="text-teal-600 ml-2 text-xs">Q${parseFloat(p.precio_alquiler).toFixed(2)}</span>
                                 <span class="text-slate-400 ml-1 text-xs">libres: ${p.cantidad_disponible}</span></div>
                            ${yaEsta ? '<div class="text-[10px] text-amber-600 font-bold">ya está en la reserva</div>' : ''}
                        </div>
                    </div>`;
                }).join('');
                resultadosProducto.classList.remove('hidden');
            }).catch(() => {});
    }, 300);
});

function agregar(id, nombre, precio, disponible, foto, ubicacion) {
    if (!lineas[id] && (parseInt(disponible) || 0) <= 0) {
        alert(`No hay "${nombre}" libre en las fechas de esta reserva.`);
        return;
    }
    resultadosProducto.classList.add('hidden');
    inputProducto.value = '';

    if (lineas[id]) {
        // Ya estaba: solo se le suma uno, sin pasarse del tope
        lineas[id].cantidad = Math.min(lineas[id].cantidad + 1, lineas[id].tope);
    } else {
        lineas[id] = {
            id: id, nombre: nombre, precio: parseFloat(precio),
            cantidad: 1, dias: 1, tipo: 'evento',
            tope: parseInt(disponible), foto: foto || '',
            ubicacion: ubicacion || '', original: false
        };
    }
    pintarLineas();
}

document.addEventListener('click', e => {
    if (!resultadosProducto.contains(e.target) && e.target !== inputProducto) {
        resultadosProducto.classList.add('hidden');
    }
});

// ── Al cambiar fechas u horas: se vuelve a consultar lo libre ──
// El tope de cada producto depende del rango; si ahora hay menos
// libres de lo que tiene la línea, se baja y se avisa. (Al guardar,
// el servidor vuelve a revisar todo.)
let timeoutFechas;
function alCambiarFechas() {
    if (fEntrega.value && (!fUso.value || fUso.value < fEntrega.value)) fUso.value = fEntrega.value;
    if (fUso.value && (!fRecoge.value || fRecoge.value < fUso.value)) fRecoge.value = fUso.value;
    const orden = mensajeOrden();
    avisoFechas.textContent = orden ? '⚠️ ' + orden : '';
    avisoFechas.classList.toggle('hidden', !orden);
    if (orden) return;

    clearTimeout(timeoutFechas);
    timeoutFechas = setTimeout(revisarTopes, 300);
}
[fEntrega, fUso, fRecoge, hEntrega, hUso, hRecoge].forEach(el => el.addEventListener('change', alCambiarFechas));

function revisarTopes() {
    const params = paramFechas();
    if (!params) return;
    const ajustados = [];
    const consultas = Object.values(lineas).map(l =>
        fetch(`${URL_BASE}/?url=reservas/inventarioajax&sucursal_id=${SUCURSAL_ID}${params}&q=${encodeURIComponent(l.nombre)}`,
              { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                const p = data.find(x => parseInt(x.id) === l.id);
                // Lo libre ya no cuenta esta reserva, así que el tope es eso mismo
                const tope = p ? (parseInt(p.cantidad_disponible) || 0) : 0;
                l.tope = tope;
                if (l.cantidad > tope) { ajustados.push(`${l.nombre} (quedan ${tope})`); l.cantidad = Math.max(tope, 0); }
            })
            .catch(() => {})
    );
    Promise.all(consultas).then(() => {
        pintarLineas();
        if (ajustados.length) {
            avisoFechas.textContent = '⚠️ Con esas fechas hay menos libres: ' + ajustados.join(', ') + '. Ajustá o quitá el producto.';
            avisoFechas.classList.remove('hidden');
        }
    });
}

document.getElementById('costoDesc').addEventListener('input', calcularTotal);

document.getElementById('formEditar').addEventListener('submit', e => {
    const orden = mensajeOrden();
    if (orden) { e.preventDefault(); alert(orden); return; }
    if ((parseFloat(document.getElementById('costoMonto').value) || 0) > 0 && !document.getElementById('costoDesc').value.trim()) {
        e.preventDefault();
        alert('Escribí la descripción del costo inicial.');
        return;
    }
    if (Object.values(lineas).some(l => l.cantidad < 1)) {
        e.preventDefault();
        alert('Hay un producto sin unidades libres en esas fechas: quitalo o cambiá las fechas.');
        return;
    }
    if (Object.keys(lineas).length === 0) {
        e.preventDefault();
        alert('La reserva tiene que quedar con al menos un producto. Si el cliente ya no quiere nada, cancelá la reserva.');
    }
});

pintarLineas();
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
