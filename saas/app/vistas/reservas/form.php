<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>
<?php
// ============================================================
// NUEVA RESERVA — un solo recorrido de arriba hacia abajo:
//   1 Sucursal → 2 Cliente → 3 Fechas → 4 Productos → 5 Combo →
//   6 Descuento → 7 Dirección de entrega → Crear
//
// Las fechas van ANTES que los productos: la disponibilidad se
// calcula por fechas ("¿cuántas sillas libres del 10 al 12?").
//
// Pensado primero para celular:
//  - una sola columna (en PC se centra, no se estira)
//  - inputs de 16px en el celular: con menos, el iPhone hace
//    zoom solo al tocar el campo
//  - Enter en los buscadores NO envía el formulario
// ============================================================
$sucursalEsFija = isset($sucursalFija) && $sucursalFija;

// Clases que se repiten
$claseTarjeta = 'bg-white rounded-2xl shadow p-4 sm:p-5';
$claseInput   = 'w-full px-4 py-2.5 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-base sm:text-sm disabled:bg-slate-50 disabled:text-slate-400';

if (!function_exists('pasoTitulo')) {
function pasoTitulo(int $n, string $titulo, string $extra = ''): string {
    return '<div class="flex items-center gap-3 mb-3">'
         . '<span class="w-7 h-7 shrink-0 rounded-full bg-teal-600 text-white text-sm font-extrabold flex items-center justify-center">' . $n . '</span>'
         . '<h3 class="font-bold text-slate-700">' . $titulo . '</h3>' . $extra
         . '</div>';
}
}
?>

<div class="max-w-3xl mx-auto">

<div class="flex items-center gap-3 mb-5">
    <a href="<?= URL_BASE ?>/?url=reservas/index" class="text-teal-700 font-bold text-xl p-1">←</a>
    <h2 class="text-xl sm:text-2xl font-extrabold text-teal-800">📅 Nueva Reserva</h2>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div id="avisoFormulario" class="hidden bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5"></div>

<form method="POST" action="<?= URL_BASE ?>/?url=reservas/guardar" id="formReserva" class="space-y-4 sm:space-y-5" novalidate>

    <!-- 1. SUCURSAL -->
    <section id="pasoSucursal" class="<?= $claseTarjeta ?>">
        <?= pasoTitulo(1, '🏬 Sucursal') ?>
        <?php if ($sucursalEsFija): ?>
            <input type="hidden" name="sucursal_id" id="sucursalSelect" value="<?= (int) $sucursalFija ?>">
            <div class="px-4 py-2.5 border-2 border-slate-100 bg-slate-50 rounded-xl text-sm text-slate-600 font-bold">
                <?= htmlspecialchars($sucursales[0]['nombre'] ?? '') ?>
                <span class="text-xs text-slate-400 font-normal ml-1">(tu sucursal asignada)</span>
            </div>
        <?php else: ?>
            <select name="sucursal_id" id="sucursalSelect" class="<?= $claseInput ?>">
                <option value="">Seleccioná una sucursal</option>
                <?php foreach ($sucursales as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-400 mt-2">Los productos y combos se buscan en el inventario de esta sucursal.</p>
        <?php endif; ?>
    </section>

    <!-- 2. CLIENTE -->
    <section id="pasoCliente" class="<?= $claseTarjeta ?>">
        <?= pasoTitulo(2, '👤 Cliente') ?>
        <input type="hidden" name="cliente_id" id="cliente_id">
        <div id="busquedaCliente">
            <div class="relative">
                <input type="search" id="buscarCliente" placeholder="Buscar por nombre o teléfono..."
                       autocomplete="off" enterkeyhint="search" class="<?= $claseInput ?>">
                <div id="resultadosCliente"
                     class="hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-30 max-h-64 overflow-y-auto"></div>
            </div>
            <div class="mt-2 flex items-center gap-2">
                <span class="text-xs text-slate-400">¿No existe?</span>
                <a href="<?= URL_BASE ?>/?url=clientes/nuevo&volver=reserva"
                   class="text-teal-600 text-xs font-bold hover:underline py-1">+ Crear nuevo cliente</a>
            </div>
        </div>
        <div id="clienteSeleccionado" class="hidden bg-teal-50 border border-teal-200 rounded-xl p-3">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-bold text-teal-800 text-sm truncate" id="clienteNombre"></div>
                    <div class="text-teal-600 text-xs" id="clienteTel"></div>
                </div>
                <button type="button" id="btnCambiarCliente"
                        class="shrink-0 text-red-500 hover:text-red-700 text-xs font-bold px-2 py-2">✕ cambiar</button>
            </div>
        </div>
    </section>

    <!-- 3. FECHAS Y HORAS: entrega → uso → recolección -->
    <section id="pasoFechas" class="<?= $claseTarjeta ?>">
        <?= pasoTitulo(3, '📆 Fechas y horas') ?>
        <?php
        // Cada momento lleva fecha y hora. Pueden caer el mismo día.
        $momentos = [
            ['📦 Entrega',     'fecha_entrega',     'fechaEntrega',     'hora_entrega',     'horaEntrega'],
            ['🎉 Uso',         'fecha_uso',         'fechaUso',         'hora_uso',         'horaUso'],
            ['🔄 Recolección', 'fecha_recoleccion', 'fechaRecoleccion', 'hora_recoleccion', 'horaRecoleccion'],
        ];
        ?>
        <div class="space-y-4">
            <?php foreach ($momentos as [$etiqueta, $nombreFecha, $idFecha, $nombreHora, $idHora]): ?>
            <div>
                <div class="text-sm font-bold text-slate-600 mb-1"><?= $etiqueta ?> <span class="text-red-500">*</span></div>
                <div class="grid grid-cols-5 gap-3">
                    <div class="col-span-3">
                        <label for="<?= $idFecha ?>" class="text-xs text-slate-400 block mb-0.5">Fecha</label>
                        <input type="date" name="<?= $nombreFecha ?>" id="<?= $idFecha ?>" min="<?= hoyGt() ?>" class="<?= $claseInput ?>">
                    </div>
                    <div class="col-span-2">
                        <label for="<?= $idHora ?>" class="text-xs text-slate-400 block mb-0.5">Hora</label>
                        <input type="time" name="<?= $nombreHora ?>" id="<?= $idHora ?>" class="<?= $claseInput ?>">
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="avisoFechas" class="hidden mt-3 bg-orange-50 border border-orange-200 text-orange-700 text-xs rounded-lg px-3 py-2"></div>
        <p class="text-xs text-slate-400 mt-3">
            Los productos quedan ocupados desde la entrega hasta la recolección. Si se recogen a las 2:00 PM, otra reserva puede entregarlos ese mismo día desde esa hora.
            Entrega, uso y recolección pueden ser el mismo día: la hora decide el orden.
        </p>
    </section>

    <!-- 4. PRODUCTOS -->
    <section id="pasoProductos" class="<?= $claseTarjeta ?>">
        <?= pasoTitulo(4, '📦 Productos') ?>
        <div class="relative">
            <input type="search" id="buscarProducto" placeholder="Primero elegí sucursal y fechas"
                   autocomplete="off" enterkeyhint="done" disabled class="<?= $claseInput ?>">
            <div id="resultadosProducto"
                 class="hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-30 max-h-72 overflow-y-auto"></div>
        </div>
        <p class="text-xs text-slate-400 mt-2">
            💡 Escribí <strong class="text-slate-500">50*silla</strong> para agregar 50 sillas de una vez.
        </p>
        <div id="avisoProducto" class="hidden mt-2 bg-amber-50 border border-amber-200 text-amber-700 text-xs rounded-lg px-3 py-2"></div>

        <div id="lineasProductos" class="space-y-3 mt-4"></div>
        <div id="sinProductos" class="text-xs text-slate-400 text-center py-3">Aún no agregaste productos.</div>
    </section>

    <!-- 5. COMBO -->
    <section id="pasoCombo" class="<?= $claseTarjeta ?>">
        <?= pasoTitulo(5, '🎁 Combo', '<span class="text-xs text-slate-400">(opcional)</span>') ?>
        <select id="comboSelect" disabled class="<?= $claseInput ?>">
            <option value="">Primero elegí sucursal y fechas</option>
        </select>

        <div id="cantidadComboWrap" class="hidden mt-3 flex items-end gap-3">
            <div class="flex-1">
                <label for="cantidadCombos" class="text-xs text-slate-500 block mb-1">Cantidad de combos</label>
                <input type="number" id="cantidadCombos" min="1" value="1" inputmode="numeric"
                       class="<?= $claseInput ?> text-center">
            </div>
            <button type="button" id="btnAgregarCombo"
                    class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-5 py-3 rounded-xl text-sm transition">
                ➕ Agregar
            </button>
        </div>

        <div id="infoCombo" class="hidden bg-teal-50 border border-teal-200 rounded-xl p-3 text-xs text-teal-700 mt-3"></div>
    </section>

    <!-- 6. DESCUENTO -->
    <section id="pasoDescuento" class="<?= $claseTarjeta ?>">
        <?= pasoTitulo(6, '🏷️ Descuento', '<span class="text-xs text-slate-400">(opcional)</span>') ?>
        <div class="flex items-center gap-3 mb-2">
            <span class="text-slate-500 font-bold text-sm">Q</span>
            <input type="number" name="descuento" id="descuentoInput" min="0" step="0.01" value="0"
                   inputmode="decimal" class="<?= $claseInput ?>">
        </div>
        <button type="button" id="btnDescuento10"
                class="w-full bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-700 font-bold py-2.5 rounded-xl text-xs transition">
            💡 Sugerir 10% de descuento (Q<span id="descuento10Preview">0.00</span>)
        </button>
    </section>

    <!-- 7. COSTO INICIAL -->
    <section id="pasoCosto" class="<?= $claseTarjeta ?>">
        <?= pasoTitulo(7, '➕ Costo inicial', '<span class="text-xs text-slate-400">(opcional)</span>') ?>
        <div class="space-y-3">
            <div>
                <label for="costoDesc" class="block text-sm font-bold text-slate-600 mb-1">Descripción</label>
                <input type="text" name="costo_inicial_desc" id="costoDesc" maxlength="150" autocomplete="off"
                       placeholder="Ej: Flete a aldea lejana, montaje en segundo piso..."
                       class="<?= $claseInput ?>">
            </div>
            <div>
                <label for="costoMonto" class="block text-sm font-bold text-slate-600 mb-1">Monto</label>
                <div class="flex items-center gap-3">
                    <span class="text-slate-500 font-bold text-sm">Q</span>
                    <input type="number" name="costo_inicial_monto" id="costoMonto" min="0" step="0.01" value="0"
                           inputmode="decimal" class="<?= $claseInput ?>">
                </div>
            </div>
        </div>
        <p class="text-[11px] text-slate-400 mt-2">Un cargo extra de esta reserva (por ubicación u otra razón). Se suma al total y no lleva descuento.</p>
    </section>

    <!-- 8. DIRECCIÓN DE ENTREGA -->
    <section id="pasoDatos" class="<?= $claseTarjeta ?>">
        <?= pasoTitulo(8, '📍 Dirección de entrega') ?>
        <div>
            <label for="direccionEntrega" class="block text-sm font-bold text-slate-600 mb-1">📍 Dirección de entrega</label>
            <textarea name="direccion_entrega" id="direccionEntrega" rows="2" maxlength="255"
                      placeholder="Dónde se entregan los bienes (salón, casa, dirección del evento...)"
                      class="<?= $claseInput ?>"></textarea>
            <p class="text-[11px] text-slate-400 mt-1">Se llena sola con la dirección del cliente. Cambiala si el evento es en otro lado.</p>
        </div>
    </section>

    <!-- RESUMEN + CREAR -->
    <section class="<?= $claseTarjeta ?>">
        <div class="space-y-1">
            <div class="flex justify-between text-sm text-slate-500">
                <span>Subtotal</span>
                <span>Q<span id="subtotalReserva">0.00</span></span>
            </div>
            <div id="filaDescuento" class="hidden flex justify-between text-sm text-red-500">
                <span>Descuento</span>
                <span>-Q<span id="descuentoMostrar">0.00</span></span>
            </div>
            <div id="filaCosto" class="hidden flex justify-between text-sm text-slate-600 gap-3">
                <span id="costoEtiqueta" class="truncate">Costo inicial</span>
                <span class="shrink-0">+Q<span id="costoMostrar">0.00</span></span>
            </div>
            <div class="flex justify-between font-extrabold text-teal-700 text-xl border-t border-slate-100 pt-2">
                <span>TOTAL</span>
                <span>Q<span id="totalReserva">0.00</span></span>
            </div>
        </div>

        <button type="submit" id="btnCrear"
                class="w-full mt-4 bg-teal-600 hover:bg-teal-700 text-white font-extrabold py-4 rounded-2xl transition text-base shadow disabled:opacity-60">
            💾 Crear Reserva
        </button>
    </section>
</form>
</div>

<script>
const URL_BASE = '<?= URL_BASE ?>';

// Todo texto que viene del servidor se escapa antes de meterlo al HTML
function esc(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function dinero(n) { return (parseFloat(n) || 0).toFixed(2); }

// Enter dentro de un buscador no debe enviar el formulario
function bloquearEnter(input, alPresionar) {
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); alPresionar && alPresionar(); }
    });
}

// ── Fechas y horas: sin ellas no se puede saber qué está libre ─
const inputEntrega = document.getElementById('fechaEntrega');
const inputUso     = document.getElementById('fechaUso');
const inputRecoge  = document.getElementById('fechaRecoleccion');
const horaEntrega  = document.getElementById('horaEntrega');
const horaUso      = document.getElementById('horaUso');
const horaRecoge   = document.getElementById('horaRecoleccion');
const avisoFechas  = document.getElementById('avisoFechas');

// Compara dos momentos (fecha + hora). Todo es texto 'AAAA-MM-DD' y
// 'HH:MM': convertirlo a Date es justo lo que corre las fechas un día.
// Si es el mismo día y a alguno le falta la hora, no se puede decir
// cuál va primero: devuelve 0 y no se acusa a nadie.
function comparar(f1, h1, f2, h2) {
    if (f1 !== f2) return f1 < f2 ? -1 : 1;
    if (!h1 || !h2) return 0;
    return h1 < h2 ? -1 : (h1 > h2 ? 1 : 0);
}

// Mensaje si el orden entrega → uso → recolección no se cumple
function mensajeOrden() {
    const e = inputEntrega.value, u = inputUso.value, r = inputRecoge.value;
    if (e && u && comparar(e, horaEntrega.value, u, horaUso.value) > 0) {
        return 'El uso no puede ser antes de la entrega.';
    }
    if (u && r && comparar(u, horaUso.value, r, horaRecoge.value) > 0) {
        return 'La recolección no puede ser antes del uso.';
    }
    if (e && r) {
        if (comparar(e, horaEntrega.value, r, horaRecoge.value) > 0 ||
            (e === r && horaEntrega.value && horaRecoge.value && horaRecoge.value <= horaEntrega.value)) {
            return 'La recolección tiene que ser después de la entrega.';
        }
    }
    return '';
}

// Rango que ocupa la reserva: de la entrega a la recolección. Si aún
// falta alguna hora se usa el día completo (lo más prudente).
function rangoFechas() {
    if (!inputEntrega.value || !inputRecoge.value || mensajeOrden()) return null;
    return {
        desde: `${inputEntrega.value}T${horaEntrega.value || '00:00'}`,
        hasta: `${inputRecoge.value}T${horaRecoge.value || '23:59'}`
    };
}
function paramFechas() {
    const r = rangoFechas();
    return r ? `&desde=${encodeURIComponent(r.desde)}&hasta=${encodeURIComponent(r.hasta)}` : '';
}

// ── 1. Sucursal ────────────────────────────────────────────────
const sucursalSelect = document.getElementById('sucursalSelect');
let sucursalActual = sucursalSelect.value || null;
let combosData = [];

// Productos y combos se habilitan solo con sucursal Y fechas
function activarSucursal(sid) {
    sucursalActual = sid || null;
    const listo = !!(sucursalActual && rangoFechas());
    const input = document.getElementById('buscarProducto');
    input.disabled = !listo;
    input.placeholder = listo ? 'Buscar producto... (ej: 50*silla)' : 'Primero elegí sucursal y fechas';
    if (listo) {
        cargarCombos(sucursalActual);
    } else {
        const sel = document.getElementById('comboSelect');
        sel.innerHTML = '<option value="">Primero elegí sucursal y fechas</option>';
        sel.disabled = true;
        ocultarCombo();
    }
}

if (sucursalSelect.tagName === 'SELECT') {
    let sucursalPrevia = sucursalSelect.value;
    sucursalSelect.addEventListener('change', function () {
        // Los productos son de una sucursal: si cambia, la lista ya no sirve
        if (Object.keys(productosAgregados).length &&
            !confirm('Cambiar de sucursal quita los productos que ya agregaste. ¿Continuar?')) {
            this.value = sucursalPrevia;
            return;
        }
        vaciarProductos();
        sucursalPrevia = this.value;
        activarSucursal(this.value);
    });
}

// ── 2. Cliente ─────────────────────────────────────────────────
const inputCliente      = document.getElementById('buscarCliente');
const resultadosCliente = document.getElementById('resultadosCliente');
let timeoutCliente;
let clientesEncontrados = [];

inputCliente.addEventListener('input', () => {
    clearTimeout(timeoutCliente);
    const q = inputCliente.value.trim();
    if (q.length < 2) { resultadosCliente.classList.add('hidden'); return; }
    timeoutCliente = setTimeout(() => {
        fetch(`${URL_BASE}/?url=clientes/buscarajax&q=${encodeURIComponent(q)}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (inputCliente.value.trim() !== q) return; // llegó tarde: ya escribieron otra cosa
                clientesEncontrados = data;
                resultadosCliente.innerHTML = data.length
                    ? data.map((c, i) =>
                        `<button type="button" data-i="${i}"
                                 class="w-full text-left px-4 py-3 hover:bg-teal-50 text-sm border-b border-slate-100 last:border-0">
                            <span class="font-bold">${esc(c.nombre_completo)}</span>
                            <span class="text-slate-400 ml-2 text-xs">${esc(c.telefono)}</span>
                         </button>`).join('')
                    : '<div class="px-4 py-3 text-sm text-slate-400">No se encontraron clientes.</div>';
                resultadosCliente.classList.remove('hidden');
            }).catch(() => {});
    }, 300);
});

resultadosCliente.addEventListener('click', e => {
    const b = e.target.closest('[data-i]');
    if (b) seleccionarCliente(clientesEncontrados[b.dataset.i]);
});
bloquearEnter(inputCliente, () => {
    if (clientesEncontrados.length === 1) seleccionarCliente(clientesEncontrados[0]);
});

function seleccionarCliente(c) {
    if (!c) return;
    document.getElementById('cliente_id').value = c.id;
    document.getElementById('clienteNombre').textContent = c.nombre_completo;
    document.getElementById('clienteTel').textContent = c.telefono || '';
    document.getElementById('clienteSeleccionado').classList.remove('hidden');
    document.getElementById('busquedaCliente').classList.add('hidden');
    resultadosCliente.classList.add('hidden');

    // Prellena la dirección de entrega sin pisar lo que ya hayan escrito
    const dir = document.getElementById('direccionEntrega');
    if (!dir.value.trim() && c.direccion) dir.value = c.direccion;
}
document.getElementById('btnCambiarCliente').addEventListener('click', () => {
    document.getElementById('cliente_id').value = '';
    document.getElementById('clienteSeleccionado').classList.add('hidden');
    document.getElementById('busquedaCliente').classList.remove('hidden');
    inputCliente.value = '';
    inputCliente.focus();
});

// ── 3. Productos ───────────────────────────────────────────────
const inputProducto      = document.getElementById('buscarProducto');
const resultadosProducto = document.getElementById('resultadosProducto');
const avisoProducto      = document.getElementById('avisoProducto');
let timeoutProducto;
let productosEncontrados = [];
let indiceResaltado = -1;
let lineaIndex = 0;
const productosAgregados = {};   // id -> { precio, max }

// "50*silla" -> { cantidad: 50, texto: 'silla' }   |   "silla" -> { cantidad: 1, texto: 'silla' }
// Mismo patrón que el POS, pero solo enteros: no se alquila media silla.
function leerBusqueda(valor) {
    const m = valor.trim().match(/^(\d+)\s*\*\s*(.*)$/);
    return m ? { cantidad: parseInt(m[1], 10), texto: m[2].trim(), conCantidad: true }
             : { cantidad: 1, texto: valor.trim(), conCantidad: false };
}

inputProducto.addEventListener('input', () => {
    clearTimeout(timeoutProducto);
    const { texto } = leerBusqueda(inputProducto.value);
    if (!sucursalActual || !rangoFechas() || texto.length < 1) {
        productosEncontrados = []; indiceResaltado = -1;
        resultadosProducto.classList.add('hidden');
        return;
    }
    timeoutProducto = setTimeout(() => buscarProductos(texto), 300);
});

function buscarProductos(texto) {
    fetch(`${URL_BASE}/?url=reservas/inventarioajax&sucursal_id=${sucursalActual}${paramFechas()}&q=${encodeURIComponent(texto)}`,
          { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (leerBusqueda(inputProducto.value).texto !== texto) return; // respuesta vieja
            productosEncontrados = data;
            indiceResaltado = data.length ? 0 : -1;
            pintarResultadosProducto();
        }).catch(() => {});
}

function pintarResultadosProducto() {
    const { cantidad, conCantidad } = leerBusqueda(inputProducto.value);
    if (!productosEncontrados.length) {
        resultadosProducto.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400">Sin resultados.</div>';
        resultadosProducto.classList.remove('hidden');
        return;
    }
    const encabezado = conCantidad
        ? `<div class="px-3 py-2 bg-teal-50 text-teal-700 text-xs font-bold border-b border-teal-100">Se agregarán ${cantidad} unidades</div>`
        : '';
    resultadosProducto.innerHTML = encabezado + productosEncontrados.map((p, i) => {
        const miniatura = p.foto_url
            ? `<img src="${esc(p.foto_url)}" alt="" loading="lazy" data-foto="${i}"
                    class="w-11 h-11 object-cover rounded-lg border border-slate-200 shrink-0 cursor-zoom-in">`
            : `<div class="w-11 h-11 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-300 text-sm shrink-0">📷</div>`;
        const sinStock = parseInt(p.cantidad_disponible) <= 0;
        return `<button type="button" data-i="${i}" ${sinStock ? 'disabled' : ''}
                    class="w-full text-left flex items-center gap-3 px-3 py-2.5 text-sm border-b border-slate-100 last:border-0
                           ${i === indiceResaltado ? 'bg-teal-50' : 'hover:bg-teal-50'} disabled:opacity-50 disabled:cursor-not-allowed">
                    ${miniatura}
                    <div class="min-w-0 flex-1">
                        <div class="font-bold truncate">${esc(p.nombre)}</div>
                        <div class="text-xs">
                            <span class="text-teal-600">Q${dinero(p.precio_alquiler)}</span>
                            <span class="text-slate-400 ml-1">libres: ${parseInt(p.cantidad_disponible)}</span>
                        </div>
                        ${p.ubicacion ? `<div class="text-[10px] text-slate-400 truncate">📍 ${esc(p.ubicacion)}</div>` : ''}
                    </div>
                </button>`;
    }).join('');
    resultadosProducto.classList.remove('hidden');
}

resultadosProducto.addEventListener('click', e => {
    const foto = e.target.closest('[data-foto]');
    if (foto && typeof soltecAbrirFoto === 'function') {
        e.stopPropagation(); e.preventDefault();
        const p = productosEncontrados[foto.dataset.foto];
        soltecAbrirFoto(p.foto_url, p.nombre);
        return;
    }
    const b = e.target.closest('[data-i]');
    if (b && !b.disabled) agregarDesdeBusqueda(productosEncontrados[b.dataset.i]);
});

inputProducto.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        if (!productosEncontrados.length) return;
        e.preventDefault();
        const paso = e.key === 'ArrowDown' ? 1 : -1;
        indiceResaltado = Math.max(0, Math.min(productosEncontrados.length - 1, indiceResaltado + paso));
        pintarResultadosProducto();
    }
});
bloquearEnter(inputProducto, () => {
    // Enter agrega el resaltado (el primero, o el que eligieron con ↑↓)
    if (indiceResaltado >= 0 && productosEncontrados[indiceResaltado]) {
        agregarDesdeBusqueda(productosEncontrados[indiceResaltado]);
    }
});

function agregarDesdeBusqueda(p) {
    if (!p) return;
    const { cantidad } = leerBusqueda(inputProducto.value);
    agregarProducto(p.id, p.nombre, p.precio_alquiler, p.cantidad_disponible, cantidad || 1, p.foto_url);
    inputProducto.value = '';
    productosEncontrados = []; indiceResaltado = -1;
    resultadosProducto.classList.add('hidden');
    inputProducto.focus();
}

function mostrarAviso(texto) {
    avisoProducto.textContent = texto;
    avisoProducto.classList.toggle('hidden', !texto);
}

function agregarProducto(id, nombre, precio, maxDisp, cantInicial, fotoUrl) {
    id = parseInt(id); maxDisp = parseInt(maxDisp) || 0;
    mostrarAviso('');

    // Ya estaba en la lista: se suma a la línea existente
    if (productosAgregados[id]) {
        const input = document.querySelector(`[data-pid="${id}"] input[name*="[cantidad]"]`);
        if (input) {
            const pedido = (parseInt(input.value) || 0) + cantInicial;
            input.value = Math.min(pedido, maxDisp);
            if (pedido > maxDisp) mostrarAviso(`Solo hay ${maxDisp} de "${nombre}" disponibles.`);
            calcularTotal();
        }
        return;
    }

    if (cantInicial > maxDisp) {
        mostrarAviso(`Pediste ${cantInicial} de "${nombre}" pero solo hay ${maxDisp} libres en esas fechas. Se agregaron ${maxDisp}.`);
        cantInicial = maxDisp;
    }
    if (cantInicial <= 0) return;

    productosAgregados[id] = { precio: parseFloat(precio) || 0, max: maxDisp };
    document.getElementById('sinProductos').classList.add('hidden');

    const idx = lineaIndex++;
    const div = document.createElement('div');
    div.className = 'bg-slate-50 border border-slate-200 rounded-xl p-3 space-y-2';
    div.dataset.pid = id;
    div.innerHTML = `
        <input type="hidden" name="productos[${idx}][inventario_id]" value="${id}">
        <div class="flex items-center gap-2">
            ${fotoUrl ? `<img src="${esc(fotoUrl)}" alt="" loading="lazy" data-accion="foto"
                 class="w-10 h-10 object-cover rounded-lg border border-slate-200 cursor-zoom-in shrink-0">` : ''}
            <div class="flex-1 min-w-0">
                <div class="text-sm font-bold text-slate-700 truncate">${esc(nombre)}</div>
                <div class="text-teal-600 text-xs font-bold">Q${dinero(precio)}/u · <span data-libres>libres: ${maxDisp}</span></div>
            </div>
            <button type="button" data-accion="quitar" aria-label="Quitar"
                    class="shrink-0 w-9 h-9 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 font-bold">✕</button>
        </div>
        <div class="grid grid-cols-3 gap-2 items-end">
            <div>
                <label class="text-xs text-slate-500 block mb-0.5">Cantidad</label>
                <input type="number" name="productos[${idx}][cantidad]" inputmode="numeric"
                       min="1" max="${maxDisp}" value="${cantInicial}" data-accion="calcular"
                       class="w-full px-2 py-2 border border-slate-300 rounded-lg text-base sm:text-sm text-center focus:border-teal-500 focus:outline-none">
            </div>
            <div>
                <label class="text-xs text-slate-500 block mb-0.5">Modalidad</label>
                <select name="productos[${idx}][tipo]" data-accion="modalidad"
                        class="w-full px-1 py-2 border border-slate-300 rounded-lg text-sm focus:border-teal-500 focus:outline-none">
                    <option value="evento">Evento</option>
                    <option value="dia">Por día</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500 block mb-0.5">Días</label>
                <input type="number" name="productos[${idx}][dias]" inputmode="numeric"
                       min="1" value="1" disabled data-accion="calcular" data-dias
                       class="w-full px-2 py-2 border border-slate-200 bg-slate-100 rounded-lg text-base sm:text-sm text-center focus:border-teal-500 focus:outline-none">
            </div>
        </div>
        <div class="text-right text-xs text-slate-400">
            Subtotal: <span class="font-bold text-slate-600" data-subtotal>Q0.00</span>
        </div>`;
    document.getElementById('lineasProductos').appendChild(div);
    calcularTotal();
}

// Un solo escuchador para todas las líneas (quitar, modalidad, cantidades, foto)
const lineasProductos = document.getElementById('lineasProductos');
lineasProductos.addEventListener('click', e => {
    const linea = e.target.closest('[data-pid]');
    if (!linea) return;
    const accion = e.target.dataset.accion;
    if (accion === 'quitar') {
        delete productosAgregados[linea.dataset.pid];
        linea.remove();
        mostrarAviso('');
        calcularTotal();
        if (!Object.keys(productosAgregados).length) document.getElementById('sinProductos').classList.remove('hidden');
    } else if (accion === 'foto' && typeof soltecAbrirFoto === 'function') {
        soltecAbrirFoto(e.target.src, linea.querySelector('.truncate').textContent);
    }
});
lineasProductos.addEventListener('input', e => {
    if (e.target.dataset.accion === 'calcular') calcularTotal();
});
lineasProductos.addEventListener('change', e => {
    // Al salir del campo, la cantidad se acomoda entre 1 y lo disponible
    if (e.target.name && e.target.name.endsWith('[cantidad]')) {
        const max = productosAgregados[e.target.closest('[data-pid]').dataset.pid]?.max || 1;
        const v = parseInt(e.target.value) || 1;
        if (v > max) mostrarAviso(`Solo hay ${max} disponibles de ese producto.`);
        e.target.value = Math.max(1, Math.min(v, max));
        calcularTotal();
        return;
    }
    if (e.target.dataset.accion !== 'modalidad') return;
    const dias = e.target.closest('[data-pid]').querySelector('[data-dias]');
    const porDia = e.target.value === 'dia';
    dias.disabled = !porDia;
    dias.classList.toggle('bg-slate-100', !porDia);
    calcularTotal();
});

function vaciarProductos() {
    lineasProductos.innerHTML = '';
    Object.keys(productosAgregados).forEach(k => delete productosAgregados[k]);
    document.getElementById('sinProductos').classList.remove('hidden');
    mostrarAviso('');
    calcularTotal();
}

// ── 4. Combo ───────────────────────────────────────────────────
const comboSelect = document.getElementById('comboSelect');

function cargarCombos(sid) {
    comboSelect.disabled = true;
    comboSelect.innerHTML = '<option value="">Cargando combos...</option>';
    fetch(`${URL_BASE}/?url=combos/ajax&sucursal_id=${sid}${paramFechas()}`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (String(sucursalActual) !== String(sid)) return; // cambiaron de sucursal mientras cargaba
            combosData = data;
            comboSelect.innerHTML = '';
            if (!data.length) {
                comboSelect.innerHTML = '<option value="">No hay combos con stock libre en esas fechas</option>';
                ocultarCombo();
                return;
            }
            comboSelect.appendChild(new Option('-- Seleccioná un combo --', ''));
            data.forEach((c, i) => {
                let texto = `${c.nombre} (máx. ${c.max_combos})`;
                if (c.descripcion) texto += ` — ${c.descripcion}`;
                comboSelect.appendChild(new Option(texto, i));   // Option escapa el texto solo
            });
            comboSelect.disabled = false;
        }).catch(() => {
            comboSelect.innerHTML = '<option value="">No se pudieron cargar los combos</option>';
        });
}

function ocultarCombo() {
    document.getElementById('cantidadComboWrap').classList.add('hidden');
    document.getElementById('infoCombo').classList.add('hidden');
}

comboSelect.addEventListener('change', function () {
    if (this.value === '') { ocultarCombo(); return; }
    const cant = document.getElementById('cantidadCombos');
    cant.value = 1;
    cant.max = combosData[this.value]?.max_combos || 1;
    document.getElementById('cantidadComboWrap').classList.remove('hidden');
    actualizarInfoCombo();
});
document.getElementById('cantidadCombos').addEventListener('input', actualizarInfoCombo);

function actualizarInfoCombo() {
    const combo = combosData[comboSelect.value];
    if (!combo) return;
    const cant = parseInt(document.getElementById('cantidadCombos').value) || 1;
    const max  = combo.max_combos;
    const info = document.getElementById('infoCombo');
    info.innerHTML =
        `<strong>${esc(combo.nombre)} × ${cant}</strong><br>` +
        combo.productos.map(p => `${p.cant_combo * cant} × ${esc(p.producto_nombre)}`).join(', ') +
        `<br><span class="font-bold">Subtotal combo: Q${dinero(combo.precio_combo * cant)}</span>` +
        (cant > max ? `<br><span class="text-red-600 font-bold">⚠️ Solo hay stock para ${max} combo${max !== 1 ? 's' : ''}</span>` : '');
    info.classList.remove('hidden');
}

document.getElementById('btnAgregarCombo').addEventListener('click', () => {
    const combo = combosData[comboSelect.value];
    if (!combo) return;
    const cant = parseInt(document.getElementById('cantidadCombos').value) || 1;
    if (cant > combo.max_combos) {
        alert(`Solo hay stock para ${combo.max_combos} combo${combo.max_combos !== 1 ? 's' : ''}.`);
        return;
    }
    combo.productos.forEach(p => {
        agregarProducto(p.inventario_id, p.producto_nombre, p.precio_alquiler, p.cantidad_disponible, p.cant_combo * cant, p.foto_url);
    });
    comboSelect.value = '';
    ocultarCombo();
    document.getElementById('pasoProductos').scrollIntoView({ behavior: 'smooth', block: 'start' });
});

// ── 5. Descuento y totales ─────────────────────────────────────
const descuentoInput = document.getElementById('descuentoInput');
descuentoInput.addEventListener('input', calcularTotal);
const costoMonto = document.getElementById('costoMonto');
const costoDesc  = document.getElementById('costoDesc');
costoMonto.addEventListener('input', calcularTotal);
costoDesc.addEventListener('input', calcularTotal);

function calcularTotal() {
    let subtotal = 0;
    lineasProductos.querySelectorAll('[data-pid]').forEach(linea => {
        const precio = productosAgregados[linea.dataset.pid]?.precio || 0;
        const cant   = parseInt(linea.querySelector('input[name*="[cantidad]"]').value) || 0;
        const tipo   = linea.querySelector('select').value;
        const diasIn = linea.querySelector('[data-dias]');
        const dias   = (tipo === 'dia' && !diasIn.disabled) ? (parseInt(diasIn.value) || 1) : 1;
        const sub    = precio * cant * dias;
        subtotal += sub;
        linea.querySelector('[data-subtotal]').textContent = 'Q' + dinero(sub);
    });
    const desc  = Math.min(Math.max(parseFloat(descuentoInput.value) || 0, 0), subtotal);
    // El costo inicial se suma aparte: el descuento solo rebaja los productos
    const costo = Math.max(parseFloat(costoMonto.value) || 0, 0);
    document.getElementById('subtotalReserva').textContent   = dinero(subtotal);
    document.getElementById('totalReserva').textContent      = dinero(subtotal - desc + costo);
    document.getElementById('costoMostrar').textContent      = dinero(costo);
    document.getElementById('costoEtiqueta').textContent     = costoDesc.value.trim() || 'Costo inicial';
    document.getElementById('filaCosto').classList.toggle('hidden', costo <= 0);
    document.getElementById('descuentoMostrar').textContent  = dinero(desc);
    document.getElementById('filaDescuento').classList.toggle('hidden', desc <= 0);
    document.getElementById('descuento10Preview').textContent = dinero(subtotal * 0.10);
}

document.getElementById('btnDescuento10').addEventListener('click', () => {
    const subtotal = parseFloat(document.getElementById('subtotalReserva').textContent) || 0;
    if (subtotal <= 0) { alert('Primero agregá productos para calcular el descuento.'); return; }
    descuentoInput.value = dinero(subtotal * 0.10);
    calcularTotal();
});

// ── 3. Fechas: al cambiarlas se revisa de nuevo lo agregado ────
function alCambiarFechas() {
    // Cada fecha arranca donde terminó la anterior: uso desde la
    // entrega, recolección desde el uso.
    if (inputEntrega.value) {
        inputUso.min = inputEntrega.value;
        if (!inputUso.value || inputUso.value < inputEntrega.value) inputUso.value = inputEntrega.value;
    }
    if (inputUso.value) {
        inputRecoge.min = inputUso.value;
        if (!inputRecoge.value || inputRecoge.value < inputUso.value) inputRecoge.value = inputUso.value;
    }
    const orden = mensajeOrden();
    avisoFechas.textContent = orden ? '⚠️ ' + orden : '';
    avisoFechas.classList.toggle('hidden', !orden);

    activarSucursal(sucursalActual);
    revisarProductosAgregados();
}
[inputEntrega, inputUso, inputRecoge, horaEntrega, horaUso, horaRecoge]
    .forEach(el => el.addEventListener('change', alCambiarFechas));

// Los productos ya agregados se vuelven a consultar con las fechas
// nuevas: si ahora hay menos libres, se baja la cantidad y se avisa.
function revisarProductosAgregados() {
    const ids = Object.keys(productosAgregados);
    if (!ids.length || !sucursalActual || !rangoFechas()) return;
    fetch(`${URL_BASE}/?url=reservas/inventarioajax&sucursal_id=${sucursalActual}${paramFechas()}&ids=${ids.join(',')}`,
          { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            const avisos = [];
            data.forEach(p => {
                const info  = productosAgregados[p.id];
                const linea = lineasProductos.querySelector(`[data-pid="${p.id}"]`);
                if (!info || !linea) return;
                info.max = parseInt(p.cantidad_disponible) || 0;
                const cant = linea.querySelector('input[name$="[cantidad]"]');
                cant.max = info.max;
                linea.querySelector('[data-libres]').textContent = `libres: ${info.max}`;
                if ((parseInt(cant.value) || 0) > info.max) {
                    avisos.push(`${p.nombre}: quedan ${info.max}`);
                    cant.value = info.max;
                }
            });
            mostrarAviso(avisos.length ? 'Con las fechas nuevas se ajustaron cantidades → ' + avisos.join(' · ') : '');
            calcularTotal();
        }).catch(() => {});
}

// ── Crear ──────────────────────────────────────────────────────
// Revisa lo básico antes de enviar y lleva al paso que falta.
// El servidor vuelve a validar todo igual.
const aviso = document.getElementById('avisoFormulario');
document.getElementById('formReserva').addEventListener('submit', e => {
    const uso    = document.getElementById('fechaUso').value;
    const recoge = document.getElementById('fechaRecoleccion').value;
    const problemas = [
        [!sucursalActual,                                   'Elegí la sucursal.',                        'pasoSucursal'],
        [!document.getElementById('cliente_id').value,      'Elegí el cliente.',                         'pasoCliente'],
        [!Object.keys(productosAgregados).length,           'Agregá al menos un producto.',              'pasoProductos'],
        [!uso,                                              'Indicá la fecha de uso.',                   'pasoFechas'],
        [!recoge,                                           'Indicá la fecha de recolección.',           'pasoFechas'],
        [recoge && uso && recoge < uso,                     'La recolección no puede ser antes del uso.', 'pasoFechas'],
        [Object.keys(productosAgregados).some(id => {
            const l = lineasProductos.querySelector(`[data-pid="${id}"] input[name$="[cantidad]"]`);
            return l && (parseInt(l.value) || 0) < 1;
         }),                                                'Hay un producto sin unidades libres en esas fechas: quitalo o cambiá las fechas.', 'pasoProductos'],
    ];
    // Lo nuevo: fecha de entrega, las tres horas, el orden y el costo inicial
    const problemasExtra = [
        [!inputEntrega.value, 'Indicá la fecha de entrega.', 'pasoFechas'],
        [!horaEntrega.value || !horaUso.value || !horaRecoge.value,
                              'Indicá la hora de entrega, de uso y de recolección.', 'pasoFechas'],
        [!!mensajeOrden(),    mensajeOrden(), 'pasoFechas'],
        [(parseFloat(costoMonto.value) || 0) > 0 && !costoDesc.value.trim(),
                              'Escribí la descripción del costo inicial.', 'pasoCosto'],
    ];
    const falla = problemas.find(p => p[0]) || problemasExtra.find(p => p[0]);
    if (falla) {
        e.preventDefault();
        aviso.textContent = '⚠️ ' + falla[1];
        aviso.classList.remove('hidden');
        document.getElementById(falla[2]).scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    aviso.classList.add('hidden');
    // Evita reservas duplicadas por doble toque en el celular
    const btn = document.getElementById('btnCrear');
    btn.disabled = true;
    btn.textContent = 'Guardando...';
});

// Cerrar listas al tocar fuera
document.addEventListener('click', e => {
    if (!resultadosCliente.contains(e.target) && e.target !== inputCliente) resultadosCliente.classList.add('hidden');
    if (!resultadosProducto.contains(e.target) && e.target !== inputProducto) resultadosProducto.classList.add('hidden');
});

// Sucursal fija (trabajador) o ya elegida al volver: activar de una vez
if (sucursalActual) activarSucursal(sucursalActual);
calcularTotal();
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
