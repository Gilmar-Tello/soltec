<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<style>
@media print {
    body * { visibility: hidden; }
    #ticketImprimible, #ticketImprimible * { visibility: visible; }
    #ticketImprimible {
        position: fixed; top: 0; left: 0;
        width: 72mm; max-width: 72mm;
    }
    .no-print { display: none !important; }
}
</style>

<script src="<?= URL_BASE ?>/app/public/vendor/html5-qrcode-2.3.8.min.js"></script>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-4">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (esAdminConSucursales()): ?>
    <!-- Aviso fijo para el administrador: la venta es de la caja (y sucursal) que esta viendo -->
    <div class="bg-green-100 border border-green-300 text-green-800 font-bold rounded-xl px-4 py-2 mb-4">
        🏬 Vendiendo en: <?= htmlspecialchars(nombreSucursalTrabajo()) ?>
    </div>
<?php endif; ?>

<?php if (!empty($cotizacionOrigen)): ?>
    <div class="bg-blue-50 border border-blue-300 text-blue-800 rounded-xl p-4 mb-4">
        📝 Estás convirtiendo la <b>Cotización #<?= $cotizacionOrigen['id'] ?></b> en una venta.
        Revisá los precios antes de cobrar — algunos pueden haber cambiado desde que se cotizó.
    </div>
<?php endif; ?>

<div class="flex flex-col lg:flex-row gap-5">

    <!-- PANEL IZQUIERDO -->
    <div class="flex-1">

        <!-- Buscar cliente -->
        <div class="bg-white rounded-2xl shadow p-4 mb-4 <?= planPermiteCredito() ? '' : 'hidden' ?>">
            <label class="block text-sm font-700 text-gray-600 mb-2">Buscar cliente (opcional)</label>
            <div class="relative">
                <input type="text" id="buscadorCliente" autocomplete="off"
                       placeholder="🔍 Buscar cliente por nombre, NIT o teléfono..."
                       oninput="filtrarClientes(this.value)"
                       onkeydown="onBuscadorClienteKeydown(event)"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                <div id="resultadosClientes"
                     class="hidden absolute z-20 w-full bg-white border-2 border-gray-200 rounded-xl mt-1 shadow-lg max-h-56 overflow-y-auto"></div>
            </div>
            <div id="clienteSeleccionadoInfo" class="hidden mt-2 flex items-center justify-between bg-green-50 rounded-xl px-3 py-2">
                <span class="text-sm font-bold text-green-800" id="clienteSeleccionadoNombre"></span>
                <button type="button" onclick="quitarClienteSeleccionado()"
                        class="text-red-400 hover:text-red-600 font-bold text-xs">✕ Quitar</button>
            </div>
            <div id="infoSaldoCliente" class="hidden mt-2 p-2 bg-blue-50 rounded-xl text-xs">
                <div class="flex justify-between text-blue-700">
                    <span>Saldo disponible</span>
                    <span class="font-bold" id="saldoDisponibleCliente">Q0.00</span>
                </div>
                <div class="flex justify-between text-gray-500 mt-1">
                    <span>Saldo después de esta compra</span>
                    <span class="font-bold" id="saldoRestanteCliente">Q0.00</span>
                </div>
            </div>
            <p id="avisoClienteRequerido" class="hidden text-xs text-red-500 mt-2">
                ⚠️ Este tipo de pago requiere seleccionar un cliente. Presioná <kbd class="px-1 bg-gray-100 border border-gray-300 rounded font-mono text-xs">F2</kbd> para crear uno nuevo si no existe.
            </p>
        </div>

        <!-- Buscar producto -->
        <div class="bg-white rounded-2xl shadow p-4 mb-4">
            <label class="block text-sm font-700 text-gray-600 mb-2">Buscar producto</label>
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <input type="text" id="buscadorProducto" autocomplete="off"
                           placeholder="🔍 Buscar producto, escanear código, o cantidad*producto (ej: 5*aceite)..."
                           oninput="filtrarProductos(this.value)"
                           onkeydown="onBuscadorKeydown(event)"
                           enterkeyhint="done"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm"
                           autofocus>
                    <span id="indicadorEscaneo"
                          class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-xs bg-green-100 text-green-700 font-bold px-2 py-1 rounded-full">
                        📷 Escaneado
                    </span>
                    <div id="resultadosProductos"
                         class="hidden absolute z-20 w-full bg-white border-2 border-gray-200 rounded-xl mt-1 shadow-lg max-h-72 overflow-y-auto"></div>
                </div>
                <button onclick="abrirCamara()" aria-label="Escanear con cámara"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold px-3 md:px-4 py-3 rounded-xl text-sm transition whitespace-nowrap">
                    📷<span class="hidden sm:inline"> Cámara</span>
                </button>
            </div>
            <p class="md:hidden mt-2 text-xs text-gray-400">Tocá un producto de la lista para agregarlo.</p>
            <div class="hidden md:flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-xs text-gray-400">
                <span><kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded font-mono">↑↓</kbd> moverse</span>
                <span><kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded font-mono">Enter</kbd> agregar</span>
                <span><kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded font-mono">F2</kbd> cliente</span>
                <span><kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded font-mono">F8</kbd> vaciar</span>
                <span><kbd class="px-1.5 py-0.5 bg-gray-100 border border-gray-300 rounded font-mono">F9</kbd> cobrar</span>
                <button type="button" onclick="abrirModalAtajos()" class="text-blue-500 hover:text-blue-700 font-bold underline">❓ Ver todos los atajos</button>
            </div>
        </div>

        <!-- Tabla de productos agregados -->
        <div class="bg-white rounded-2xl shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="hidden sm:table-cell px-3 py-2 text-left text-gray-500">Código</th>
                            <th class="px-3 py-2 text-left text-gray-500">Producto</th>
                            <th class="px-3 py-2 text-left text-gray-500">Cant.</th>
                            <th class="px-3 py-2 text-left text-gray-500">Precio</th>
                            <th class="px-3 py-2 text-right text-gray-500">Subtotal</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="carritoItems"></tbody>
                </table>
            </div>
            <p class="text-gray-400 text-sm text-center py-6" id="carritoVacio">El carrito está vacío</p>
        </div>
    </div>

    <!-- PANEL DERECHO -->
    <div class="w-full lg:w-[28rem]">
        <div class="bg-white rounded-2xl shadow p-4 md:p-5 lg:sticky lg:top-20">
            <h3 class="font-extrabold text-gray-800 text-lg mb-4">🧾 Resumen</h3>

            <div class="flex items-center gap-3 mb-3">
                <label class="text-sm text-gray-600 font-600 whitespace-nowrap">Descuento (Q)</label>
                <input type="number" id="descuento" min="0" step="0.01" value="0"
                       oninput="recalcular()"
                       onchange="validarDescuento()"
                       class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
            </div>

            <div class="bg-green-50 rounded-xl p-3 mb-4 space-y-1">
                <div class="flex justify-between text-sm text-gray-600">
                    <span>Subtotal</span><span id="subtotal">Q0.00</span>
                </div>
                <div class="flex justify-between text-sm text-gray-600">
                    <span>Descuento</span><span id="descuentoMostrar">Q0.00</span>
                </div>
                <div class="flex justify-between font-extrabold text-green-800 text-lg border-t pt-1">
                    <span>TOTAL</span><span id="total">Q0.00</span>
                </div>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-700 text-gray-600 mb-2">Tipo de pago</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2" id="opcionesPago">
                    <?php foreach (['efectivo'=>'💵 Efectivo','tarjeta'=>'💳 Tarjeta','credito'=>'📒 Crédito','saldo_cliente'=>'🏦 Saldo'] as $val=>$lbl): ?>
                    <label class="<?= (!planPermiteCredito() && in_array($val, ['credito','saldo_cliente'], true)) ? 'hidden ' : '' ?>flex flex-col items-center p-2 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-green-400 transition has-[:checked]:border-green-500 has-[:checked]:bg-green-50"
                           id="opcionPago_<?= $val ?>">
                        <input type="radio" name="tipoPago" value="<?= $val ?>"
                               <?= $val==='efectivo'?'checked':'' ?>
                               onchange="cambiarTipoPago('<?= $val ?>')" class="hidden">
                        <span class="text-lg"><?= explode(' ',$lbl)[0] ?></span>
                        <span class="text-xs font-bold text-gray-600 mt-1"><?= explode(' ',$lbl)[1] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p id="avisoCreditoOffline" class="hidden text-xs text-red-500 mt-2">
                    ⚠️ Sin conexión: crédito no disponible
                </p>
                <p id="avisoSaldoOffline" class="hidden text-xs text-red-500 mt-2">
                    ⚠️ Sin conexión: pago con saldo no disponible
                </p>
            </div>

            <div id="seccionEfectivo" class="mb-3">
                <label class="block text-sm font-700 text-gray-600 mb-1">Monto recibido (Q)</label>
                <input type="number" id="montoRecibido" min="0" step="0.01" value="0"
                       oninput="calcularCambio()"
                       class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                <div class="flex justify-between mt-2 text-sm font-bold">
                    <span class="text-gray-600">Cambio</span>
                    <span class="text-green-600" id="cambio">Q0.00</span>
                </div>
            </div>
            <div class="mb-4">
                <textarea id="notasVenta" rows="1" placeholder="Notas (opcional)"
                          class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm resize-none"></textarea>
            </div>

            <button id="btnCobrar" onclick="iniciarCobro()"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-extrabold py-4 rounded-xl text-lg transition">
                ✅ Cobrar
            </button>
            <button id="btnGuardarCotizacion" onclick="guardarComoCotizacion()"
                    class="w-full bg-blue-100 hover:bg-blue-200 text-blue-700 font-bold py-3 rounded-xl text-sm transition mt-3">
                💾 Guardar como Cotización
            </button>
            <a href="<?= URL_BASE ?>/?url=pos/index"
               class="block text-center text-gray-400 hover:text-gray-600 text-sm mt-3">Cancelar</a>
        </div>
    </div>
</div>

<!-- Modal monto recibido (F9, pago en efectivo) -->
<div id="modalMontoRecibido" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xs p-6">
        <h3 class="font-extrabold text-gray-800 text-lg mb-1">💵 Monto recibido</h3>
        <p class="text-sm text-gray-500 mb-4">Total a cobrar: <span class="font-bold text-green-700" id="totalModalMonto">Q0.00</span></p>
        <input type="number" id="montoRecibidoModal" min="0" step="0.01"
               oninput="actualizarCambioModal()"
               onkeydown="if(event.key==='Enter'){ event.preventDefault(); confirmarMontoRecibido(); }"
               class="w-full px-4 py-3 text-2xl text-center border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none font-bold mb-2">
        <div class="flex justify-between text-sm font-bold mb-1">
            <span class="text-gray-600">Cambio</span>
            <span class="text-green-600" id="cambioModal">Q0.00</span>
        </div>
        <p id="avisoMontoInsuficienteModal" class="hidden text-xs text-red-500 mb-3">⚠️ El monto es menor al total</p>
        <div class="flex gap-3 mt-3">
            <button type="button" onclick="confirmarMontoRecibido()"
                    class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-xl text-sm transition">
                ✅ Confirmar y Cobrar
            </button>
            <button type="button" onclick="cerrarModalMontoRecibido()"
                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 rounded-xl text-sm transition">
                Cancelar
            </button>
        </div>
    </div>
</div>

<!-- Modal atajos de teclado -->
<div id="modalAtajos" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-extrabold text-gray-800 text-lg">⌨️ Atajos de teclado</h3>
            <button type="button" onclick="cerrarModalAtajos()" class="text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
        </div>
        <div class="space-y-3 text-sm">
            <div class="flex items-center justify-between">
                <span class="text-gray-600">Moverse entre resultados (cliente o producto)</span>
                <kbd class="px-2 py-1 bg-gray-100 border border-gray-300 rounded font-mono text-xs whitespace-nowrap">↑ ↓</kbd>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-600">Seleccionar / agregar el resaltado</span>
                <kbd class="px-2 py-1 bg-gray-100 border border-gray-300 rounded font-mono text-xs">Enter</kbd>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-600">Cerrar ventana emergente / limpiar búsqueda</span>
                <kbd class="px-2 py-1 bg-gray-100 border border-gray-300 rounded font-mono text-xs">Esc</kbd>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-600">Agregar un cliente nuevo sin salir de la venta</span>
                <kbd class="px-2 py-1 bg-gray-100 border border-gray-300 rounded font-mono text-xs">F2</kbd>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-600">Vaciar la venta actual (pide confirmación)</span>
                <kbd class="px-2 py-1 bg-gray-100 border border-gray-300 rounded font-mono text-xs">F8</kbd>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-600">Cobrar / finalizar la venta (si es efectivo, primero pide el monto recibido)</span>
                <kbd class="px-2 py-1 bg-gray-100 border border-gray-300 rounded font-mono text-xs">F9</kbd>
            </div>
            <div class="border-t border-gray-100 pt-3">
                <div class="font-bold text-gray-700 mb-1">🔢 Agregar una cantidad exacta de una vez</div>
                <p class="text-gray-500">
                    Escribí <code class="bg-gray-100 px-1 rounded">cantidad*producto</code>, por ejemplo
                    <code class="bg-gray-100 px-1 rounded">5*aceite</code>, y presioná Enter.
                    Si hay más de un producto que coincide (por ejemplo dos tipos de cemento),
                    va a aparecer la lista completa — elegí el correcto con <kbd class="px-1 bg-gray-100 border border-gray-300 rounded font-mono text-xs">↑↓</kbd> antes de presionar Enter.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Modal nuevo cliente rápido (F2) -->
<div id="modalNuevoCliente" class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-extrabold text-gray-800 text-lg">👤 Nuevo Cliente</h3>
            <button type="button" onclick="cerrarModalNuevoCliente()" class="text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
        </div>
        <form id="formNuevoClienteRapido" onsubmit="return guardarClienteRapido(event)">
            <label class="block text-sm font-700 text-gray-600 mb-1">Nombre <span class="text-red-500">*</span></label>
            <input type="text" id="nuevoClienteNombre" required
                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm mb-3">
            <label class="block text-sm font-700 text-gray-600 mb-1">Teléfono</label>
            <input type="text" id="nuevoClienteTelefono"
                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm mb-3">
            <label class="block text-sm font-700 text-gray-600 mb-1">NIT</label>
            <input type="text" id="nuevoClienteNit" placeholder="CF"
                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm mb-4">
            <div class="flex gap-3">
                <button type="submit"
                        class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-2 rounded-xl text-sm transition">
                    ✅ Guardar
                </button>
                <button type="button" onclick="cerrarModalNuevoCliente()"
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 rounded-xl text-sm transition">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal cámara -->
<div id="modalCamara" class="fixed inset-0 bg-black bg-opacity-80 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <span class="font-bold text-gray-700">📷 Escanear código de barras</span>
            <button onclick="cerrarCamara()" class="text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
        </div>
        <div id="lectorCamara" class="w-full"></div>
        <div class="p-3 text-center text-xs text-gray-400">
            Apuntá la cámara directamente al código de barras del producto
        </div>
    </div>
</div>

<!-- Modal ticket offline -->
<div id="modalTicketOffline" class="fixed inset-0 bg-black bg-opacity-80 z-50 hidden flex items-center justify-center p-4 no-print-bg">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden" id="contenidoTicketOffline">
        <!-- Se llena dinámicamente con JS -->
    </div>
</div>

<form method="POST" action="<?= URL_BASE ?>/?url=pos/procesarVenta" id="formVenta">
    <input type="hidden" name="items"          id="inputItems">
    <input type="hidden" name="cliente_id"     id="inputCliente">
    <input type="hidden" name="tipo_pago"      id="inputTipoPago" value="efectivo">
    <input type="hidden" name="monto_recibido" id="inputRecibido" value="0">
    <input type="hidden" name="descuento"      id="inputDescuento" value="0">
    <input type="hidden" name="notas"          id="inputNotas">
    <input type="hidden" name="cotizacion_id"  id="inputCotizacionId" value="<?= !empty($cotizacionOrigen) ? $cotizacionOrigen['id'] : '' ?>">
</form>

<form method="POST" action="<?= URL_BASE ?>/?url=pos/guardarCotizacion" id="formCotizacion">
    <input type="hidden" name="items"      id="inputItemsCotizacion">
    <input type="hidden" name="cliente_id" id="inputClienteCotizacion">
    <input type="hidden" name="descuento"  id="inputDescuentoCotizacion" value="0">
    <input type="hidden" name="notas"      id="inputNotasCotizacion">
</form>

<script>
<?php $cfgOffline = configEfectiva((int) ($sucCaja ?? 0)); ?>
// Datos del comprobante que se arma sin internet (en el navegador): nombre del negocio y, si la caja es de una
// sucursal, el nombre de la sucursal.
window.NOMBRE_NEGOCIO_JS  = <?= json_encode((string) ($cfgOffline['nombre_negocio'] ?? 'POS GT'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.NOMBRE_SUCURSAL_JS = <?= json_encode((string) ($cfgOffline['nombre_sucursal'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
let carrito = [];
let tipoPagoActual = 'efectivo';
let clienteSeleccionadoId = '';

const clientesData = <?= jsonJs(array_map(fn($c) => [
    'id' => $c['id'],
    'nombre' => $c['nombre'],
    'nit' => $c['nit'] ?? '',
    'telefono' => $c['telefono'] ?? '',
    'saldo_favor' => floatval($c['saldo_favor']),
], $clientes)) ?>;

// Catálogo completo de productos (para búsqueda y escaneo)
const productosData = <?= jsonJs(array_map(fn($p) => [
    'id' => $p['id'],
    'nombre' => $p['nombre'],
    'codigo' => $p['codigo'] ?? '',
    'precio' => floatval($p['precio_venta_real']),
    'precioMinimo' => floatval($p['precio_minimo']),
    'stock' => floatval($p['stock']),
    'unidad' => $p['unidad'],
    'foto' => $p['foto'] ?? null,
], $productos)) ?>;

// Si venimos de convertir una cotización, estos son sus datos para precargar el carrito
const cotizacionOrigenId = <?= !empty($cotizacionOrigen) ? $cotizacionOrigen['id'] : 'null' ?>;
const cotizacionOrigenClienteId = <?= !empty($cotizacionOrigen) ? intval($cotizacionOrigen['cliente_id']) : 'null' ?>;
const cotizacionOrigenItems = <?= !empty($cotizacionOrigen) ? jsonJs(array_map(fn($it) => [
    'id' => $it['producto_id'],
    'nombre' => $it['nombre'],
    'codigo' => $it['codigo'] ?? '',
    'precioMinimo' => floatval($it['precio_minimo']),
    'precio' => floatval($it['precio_unitario']),
    'stock' => floatval($it['stock']),
    'unidad' => $it['unidad'],
    'cantidad' => floatval($it['cantidad']),
], $cotizacionOrigen['items'])) : '[]' ?>;

const LIMITE_RESULTADOS = 100;

function escaparHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ══════════════════════ BÚSQUEDA DE CLIENTE ══════════════════════
let resultadosClientesActuales = [];
let indiceClienteSeleccionado = -1;

function filaClienteHtml(c, idx) {
    const activa = idx === indiceClienteSeleccionado;
    const sub = [c.nit, c.telefono].filter(Boolean).join(' · ');
    return `
        <div class="fila-resultado px-3 py-3 md:py-2 cursor-pointer select-none ${activa ? 'bg-green-50' : 'hover:bg-gray-50'}"
             data-idx="${idx}">
            <div class="text-sm font-bold text-gray-800">${escaparHtml(c.nombre)}</div>
            ${sub ? `<div class="text-xs text-gray-400">${escaparHtml(sub)}</div>` : ''}
        </div>`;
}

function pintarListaClientes() {
    const cont = document.getElementById('resultadosClientes');
    if (resultadosClientesActuales.length === 0) { cont.classList.add('hidden'); return; }
    cont.innerHTML = resultadosClientesActuales.map((c, idx) => filaClienteHtml(c, idx)).join('');
    cont.classList.remove('hidden');
    const filaActiva = cont.querySelector('.bg-green-50');
    if (filaActiva) filaActiva.scrollIntoView({ block: 'nearest' });
}

function filtrarClientes(query) {
    query = query.trim().toLowerCase();
    if (!query) {
        resultadosClientesActuales = [];
        indiceClienteSeleccionado = -1;
        pintarListaClientes();
        return;
    }
    resultadosClientesActuales = clientesData.filter(c =>
        c.nombre.toLowerCase().includes(query) ||
        (c.nit || '').toLowerCase().includes(query) ||
        (c.telefono || '').includes(query)
    ).slice(0, LIMITE_RESULTADOS);
    indiceClienteSeleccionado = resultadosClientesActuales.length ? 0 : -1;
    pintarListaClientes();
}

function seleccionarCliente(id) {
    const cliente = clientesData.find(c => String(c.id) === String(id));
    if (!cliente) return;
    clienteSeleccionadoId = String(id);
    document.getElementById('clienteSeleccionadoNombre').textContent = cliente.nombre;
    document.getElementById('clienteSeleccionadoInfo').classList.remove('hidden');
    document.getElementById('buscadorCliente').value = '';
    resultadosClientesActuales = [];
    pintarListaClientes();
    document.getElementById('avisoClienteRequerido').classList.add('hidden');
    actualizarInfoCliente();
    document.getElementById('buscadorProducto').focus();
}

function quitarClienteSeleccionado() {
    clienteSeleccionadoId = '';
    document.getElementById('clienteSeleccionadoInfo').classList.add('hidden');
    actualizarInfoCliente();
}

function onBuscadorClienteKeydown(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (resultadosClientesActuales.length === 0) return;
        indiceClienteSeleccionado = Math.min(indiceClienteSeleccionado + 1, resultadosClientesActuales.length - 1);
        pintarListaClientes();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (resultadosClientesActuales.length === 0) return;
        indiceClienteSeleccionado = Math.max(indiceClienteSeleccionado - 1, 0);
        pintarListaClientes();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (indiceClienteSeleccionado >= 0 && resultadosClientesActuales[indiceClienteSeleccionado]) {
            seleccionarCliente(resultadosClientesActuales[indiceClienteSeleccionado].id);
        }
    }
}

// ══════════════════════ BÚSQUEDA DE PRODUCTO ══════════════════════
let resultadosProductosActuales = [];
let indiceProductoSeleccionado = -1;

function filaProductoHtml(p, idx) {
    const activa = idx === indiceProductoSeleccionado;
    return `
        <div class="fila-resultado px-3 py-3 md:py-2 cursor-pointer select-none flex items-center justify-between gap-3 ${activa ? 'bg-green-50' : 'hover:bg-gray-50'}"
             data-idx="${idx}">
            ${p.foto ? `<img src="${escaparHtml(p.foto)}" alt="" loading="lazy" data-zoom="${escaparHtml(p.foto)}" data-titulo="${escaparHtml(p.nombre)}" onerror="this.style.display='none'" class="w-10 h-10 rounded-lg object-cover bg-gray-100 flex-shrink-0">` : ''}
            <div class="min-w-0 flex-1">
                <div class="font-bold text-gray-800 text-sm leading-tight truncate">${escaparHtml(p.nombre)}</div>
                <div class="text-xs text-gray-400 mt-0.5">
                    Stock: ${p.stock.toFixed(2)} ${escaparHtml(p.unidad)}
                    &nbsp;·&nbsp;<span class="text-orange-500 font-600">Mín: Q${p.precioMinimo.toFixed(2)}</span>
                    &nbsp;·&nbsp;<span class="text-green-600 font-bold">Venta: Q${p.precio.toFixed(2)}</span>
                </div>
            </div>
            ${activa ? '<span class="hidden md:inline text-green-500 text-xs font-bold whitespace-nowrap">↵ Enter</span>' : ''}
            <span class="md:hidden text-green-600 text-lg font-bold">＋</span>
        </div>`;
}

function pintarListaProductos() {
    const cont = document.getElementById('resultadosProductos');
    if (resultadosProductosActuales.length === 0) { cont.classList.add('hidden'); return; }
    cont.innerHTML = resultadosProductosActuales.map((p, idx) => filaProductoHtml(p, idx)).join('');
    cont.classList.remove('hidden');
    const filaActiva = cont.querySelector('.bg-green-50');
    if (filaActiva) filaActiva.scrollIntoView({ block: 'nearest' });
}

function filtrarProductos(query) {
    query = query.trim().toLowerCase();

    // Soporta "cantidad*texto" (ej: 5*aceite) — filtramos solo por el texto de búsqueda
    const match = query.match(/^(\d+(?:\.\d+)?)\s*\*\s*(.*)$/);
    const textoBusqueda = match ? match[2].trim() : query;

    if (!textoBusqueda) {
        resultadosProductosActuales = [];
        indiceProductoSeleccionado = -1;
        pintarListaProductos();
        return;
    }
    resultadosProductosActuales = productosData.filter(p =>
        p.nombre.toLowerCase().includes(textoBusqueda) || p.codigo.toLowerCase().includes(textoBusqueda)
    ).slice(0, LIMITE_RESULTADOS);
    indiceProductoSeleccionado = resultadosProductosActuales.length ? 0 : -1;
    pintarListaProductos();
}

function limpiarBusquedaTrasAgregar() {
    const input = document.getElementById('buscadorProducto');
    input.value = '';
    filtrarProductos('');
    input.focus();
}

function buscarPorCodigo(valor) {
    // Usado por el lector de código de barras / cámara: siempre busca coincidencia EXACTA
    // de código y da feedback visual (verde/rojo) sin importar el resultado.
    const codigo = valor.trim().toLowerCase();
    if (!codigo) return;

    const producto = productosData.find(p => p.codigo.toLowerCase() === codigo);

    const input = document.getElementById('buscadorProducto');
    const indicador = document.getElementById('indicadorEscaneo');
    const camaraAbierta = !document.getElementById('modalCamara').classList.contains('hidden');

    if (producto) {
        agregarAlCarrito(producto.id);
        indicador.classList.remove('hidden');
        input.classList.remove('border-gray-200');
        input.classList.add('border-green-500');
        setTimeout(() => {
            indicador.classList.add('hidden');
            input.classList.remove('border-green-500');
            input.classList.add('border-gray-200');
        }, 800);
        if (camaraAbierta) setTimeout(() => cerrarCamara(), 400);
    } else {
        input.classList.remove('border-gray-200');
        input.classList.add('border-red-500');
        setTimeout(() => {
            input.classList.remove('border-red-500');
            input.classList.add('border-gray-200');
        }, 800);
    }

    input.value = '';
    filtrarProductos('');
    if (!camaraAbierta) input.focus();
}

function manejarEnterBusqueda(valorCrudo) {
    const valor = valorCrudo.trim();
    if (!valor) return;

    // Patrón "cantidad*texto", ej: "5*aceite" o "2.5*arena"
    const match = valor.match(/^(\d+(?:\.\d+)?)\s*\*\s*(.*)$/);
    if (match) {
        const cantidad = parseFloat(match[1]);
        // El texto ya se usó en filtrarProductos() para armar resultadosProductosActuales.
        // Si hay más de una coincidencia (ej: dos tipos de cemento), se agrega el que esté
        // resaltado — por defecto el primero, o el que el usuario haya elegido con ↑↓.
        if (cantidad > 0 && indiceProductoSeleccionado >= 0 && resultadosProductosActuales[indiceProductoSeleccionado]) {
            agregarAlCarrito(resultadosProductosActuales[indiceProductoSeleccionado].id, cantidad);
            limpiarBusquedaTrasAgregar();
        } else {
            alert('No se encontró ningún producto que coincida con "' + (match[2].trim() || valor) + '".');
        }
        return;
    }

    // Coincidencia exacta de código (lector de barras físico escribiendo + Enter)
    const porCodigo = productosData.find(p => p.codigo.toLowerCase() === valor.toLowerCase());
    if (porCodigo) {
        agregarAlCarrito(porCodigo.id);
        limpiarBusquedaTrasAgregar();
        return;
    }

    // Si no, agregamos el producto resaltado en la lista de resultados
    if (indiceProductoSeleccionado >= 0 && resultadosProductosActuales[indiceProductoSeleccionado]) {
        agregarAlCarrito(resultadosProductosActuales[indiceProductoSeleccionado].id);
        limpiarBusquedaTrasAgregar();
    }
}

function onBuscadorKeydown(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (resultadosProductosActuales.length === 0) return;
        indiceProductoSeleccionado = Math.min(indiceProductoSeleccionado + 1, resultadosProductosActuales.length - 1);
        pintarListaProductos();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (resultadosProductosActuales.length === 0) return;
        indiceProductoSeleccionado = Math.max(indiceProductoSeleccionado - 1, 0);
        pintarListaProductos();
    } else if (e.key === 'Enter' || e.keyCode === 13) {
        // keyCode 13 como respaldo: algunos teclados Android no envían e.key = 'Enter'
        e.preventDefault();
        manejarEnterBusqueda(e.target.value);
    }
    // Escape se maneja globalmente (cierra modales o limpia la búsqueda)
}

// ══════════════ SELECCIÓN CON TOQUE / MOUSE EN LAS LISTAS ══════════════
// Antes cada fila tenía onmouseenter que volvía a dibujar la lista (innerHTML).
// En celulares el "hover" se simula justo antes del click; al redibujar, la fila
// tocada desaparece y el click nunca llega (iOS además cancela el click si el hover
// cambia el contenido). Ahora se usa un solo listener por lista (delegación).
function conectarListaResultados(idLista, idInput, alSeleccionar, alResaltar) {
    const lista = document.getElementById(idLista);

    // Evita que el input pierda el foco (y que el teclado del celular se cierre) al tocar
    lista.addEventListener('pointerdown', e => { if (e.target.closest('.fila-resultado')) e.preventDefault(); });

    lista.addEventListener('click', e => {
        const fila = e.target.closest('.fila-resultado');
        if (!fila) return;
        alSeleccionar(parseInt(fila.dataset.idx, 10));
    });

    // Resaltado con mouse: solo en equipos con puntero real y sin redibujar toda la lista
    lista.addEventListener('mouseover', e => {
        if (!window.matchMedia('(hover: hover)').matches) return;
        const fila = e.target.closest('.fila-resultado');
        if (!fila) return;
        lista.querySelectorAll('.fila-resultado.bg-green-50').forEach(f => f.classList.remove('bg-green-50'));
        fila.classList.add('bg-green-50');
        alResaltar(parseInt(fila.dataset.idx, 10));
    });
}

document.addEventListener('DOMContentLoaded', () => {
    conectarListaResultados('resultadosProductos', 'buscadorProducto',
        idx => {
            const p = resultadosProductosActuales[idx];
            if (!p) return;
            // Respeta el atajo cantidad*producto también al tocar
            const m = document.getElementById('buscadorProducto').value.trim().match(/^(\d+(?:\.\d+)?)\s*\*/);
            const cantidad = m ? parseFloat(m[1]) : 1;
            agregarAlCarrito(p.id, cantidad > 0 ? cantidad : 1);
            limpiarBusquedaTrasAgregar();
        },
        idx => { indiceProductoSeleccionado = idx; }
    );
    conectarListaResultados('resultadosClientes', 'buscadorCliente',
        idx => { const c = resultadosClientesActuales[idx]; if (c) seleccionarCliente(c.id); },
        idx => { indiceClienteSeleccionado = idx; }
    );

    // Cerrar las listas al tocar fuera de ellas
    document.addEventListener('pointerdown', e => {
        if (!e.target.closest('#resultadosClientes, #buscadorCliente')) {
            document.getElementById('resultadosClientes').classList.add('hidden');
        }
        if (!e.target.closest('#resultadosProductos, #buscadorProducto')) {
            document.getElementById('resultadosProductos').classList.add('hidden');
        }
    });
    // Al volver a enfocar el buscador, mostrar de nuevo los resultados que había
    document.getElementById('buscadorProducto').addEventListener('focus', () => {
        if (resultadosProductosActuales.length) pintarListaProductos();
    });
    document.getElementById('buscadorCliente').addEventListener('focus', () => {
        if (resultadosClientesActuales.length) pintarListaClientes();
    });
});

let html5QrCode = null;

function abrirCamara() {
    document.getElementById('modalCamara').classList.remove('hidden');
    html5QrCode = new Html5Qrcode("lectorCamara");
    const config = { fps: 10, qrbox: { width: 250, height: 150 } };

    html5QrCode.start(
        { facingMode: "environment" },
        config,
        (decodedText) => {
            buscarPorCodigo(decodedText);
            if (navigator.vibrate) navigator.vibrate(100);
        },
        (errorMessage) => {}
    ).catch(err => {
        alert('No se pudo acceder a la cámara: ' + err);
        cerrarCamara();
    });
}

function cerrarCamara() {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            html5QrCode = null;
        }).catch(() => {});
    }
    document.getElementById('modalCamara').classList.add('hidden');
    document.getElementById('buscadorProducto').focus();
}

// ══════════════════════ CARRITO ══════════════════════
function agregarAlCarrito(id, cantidadAgregar = 1) {
    id = String(id);
    const producto = productosData.find(p => String(p.id) === id);
    if (!producto) return;

    const { nombre, codigo, precio, precioMinimo, stock, unidad } = producto;
    const existe = carrito.find(i => i.id === id);
    const cantidadActual = existe ? existe.cantidad : 0;

    if (cantidadActual + cantidadAgregar > stock) {
        alert('No hay suficiente stock de ' + nombre + '. Disponible: ' + stock);
        return;
    }

    if (existe) {
        existe.cantidad += cantidadAgregar;
    } else {
        carrito.push({ id, nombre, codigo, precio, precioMinimo, stock, unidad, cantidad: cantidadAgregar });
    }
    renderCarrito();
}

function cambiarCantidadManual(id, valor) {
    const item = carrito.find(i => i.id === id);
    if (!item) return;
    let nueva = parseFloat(valor);
    if (isNaN(nueva) || nueva <= 0) {
        carrito = carrito.filter(i => i.id !== id);
        renderCarrito();
        return;
    }
    if (nueva > item.stock) {
        alert('No hay suficiente stock de ' + item.nombre + '. Disponible: ' + item.stock);
        nueva = item.stock;
    }
    item.cantidad = nueva;
    renderCarrito();
}

function cambiarPrecioManual(id, valor) {
    const item = carrito.find(i => i.id === id);
    if (!item) return;
    let nuevo = parseFloat(valor);
    if (isNaN(nuevo) || nuevo <= 0) {
        renderCarrito();
        return;
    }
    if (nuevo < item.precioMinimo) {
        alert('El precio de "' + item.nombre + '" no puede ser menor a Q' + item.precioMinimo.toFixed(2) + ' (precio mínimo autorizado).');
        nuevo = item.precioMinimo;
    }
    item.precio = nuevo;
    renderCarrito();
}

function eliminarItem(id) {
    carrito = carrito.filter(i => i.id !== id);
    renderCarrito();
}

function filaCarritoHtml(i) {
    return `
        <tr class="border-b border-gray-100">
            <td class="hidden sm:table-cell px-3 py-2 text-gray-400 text-xs font-mono">${escaparHtml(i.codigo || '—')}</td>
            <td class="px-2 sm:px-3 py-2 font-bold text-gray-800 min-w-[7rem]">${escaparHtml(i.nombre)}</td>
            <td class="px-3 py-2">
                <input type="number" min="0.01" step="0.01" value="${i.cantidad}"
                       onchange="cambiarCantidadManual('${i.id}', this.value)"
                       onclick="this.select()"
                       class="w-16 text-center text-sm font-bold border-2 border-gray-200 rounded-lg py-1 focus:border-green-500 focus:outline-none">
            </td>
            <td class="px-3 py-2">
                <div class="flex items-center gap-1">
                    <span class="text-xs text-gray-400">Q</span>
                    <input type="number" min="${i.precioMinimo}" step="0.01" value="${i.precio.toFixed(2)}"
                           onchange="cambiarPrecioManual('${i.id}', this.value)"
                           onclick="this.select()"
                           title="Mínimo autorizado: Q${i.precioMinimo.toFixed(2)}"
                           class="w-20 text-sm font-bold border-2 border-gray-200 rounded-lg px-1 py-1 focus:border-green-500 focus:outline-none">
                </div>
            </td>
            <td class="px-3 py-2 text-right font-bold text-green-700">Q${(i.precio * i.cantidad).toFixed(2)}</td>
            <td class="px-3 py-2 text-center">
                <button onclick="eliminarItem('${i.id}')"
                        class="w-7 h-7 bg-red-100 hover:bg-red-200 text-red-600 rounded-lg font-bold text-sm">✕</button>
            </td>
        </tr>`;
}

function renderCarrito() {
    const cuerpo = document.getElementById('carritoItems');
    const vacio = document.getElementById('carritoVacio');
    if (carrito.length === 0) {
        cuerpo.innerHTML = '';
        vacio.classList.remove('hidden');
        recalcular();
        return;
    }
    vacio.classList.add('hidden');
    cuerpo.innerHTML = carrito.map(filaCarritoHtml).join('');
    recalcular();
}

function recalcular() {
    const sub  = carrito.reduce((a, i) => a + i.precio * i.cantidad, 0);
    const desc = parseFloat(document.getElementById('descuento').value) || 0;
    const tot  = Math.max(0, sub - desc);
    document.getElementById('subtotal').textContent        = 'Q' + sub.toFixed(2);
    document.getElementById('descuentoMostrar').textContent = 'Q' + desc.toFixed(2);
    document.getElementById('total').textContent           = 'Q' + tot.toFixed(2);
    calcularCambio();
    actualizarInfoCliente();
}

function validarDescuento() {
    const sub = carrito.reduce((a, i) => a + i.precio * i.cantidad, 0);
    const minimoTotal = carrito.reduce((a, i) => a + i.cantidad * i.precioMinimo, 0);
    const maxDescuento = Math.max(0, sub - minimoTotal);
    const descInput = document.getElementById('descuento');
    const desc = parseFloat(descInput.value) || 0;
    if (desc > maxDescuento + 0.001) {
        alert('El descuento máximo permitido para este carrito es Q' + maxDescuento.toFixed(2) + '. No podés vender por debajo del mínimo autorizado de cada producto (Q' + minimoTotal.toFixed(2) + ' en total).');
        descInput.value = maxDescuento.toFixed(2);
    }
    recalcular();
}

function calcularCambio() {
    const tot     = parseFloat(document.getElementById('total').textContent.replace('Q','')) || 0;
    const recibido = parseFloat(document.getElementById('montoRecibido').value) || 0;
    const cambio  = Math.max(0, recibido - tot);
    document.getElementById('cambio').textContent = 'Q' + cambio.toFixed(2);
}

function cambiarTipoPago(tipo) {
    tipoPagoActual = tipo;
    document.getElementById('seccionEfectivo').style.display = tipo === 'efectivo' ? '' : 'none';
    document.getElementById('inputTipoPago').value = tipo;
    const requiereCliente = (tipo === 'credito' || tipo === 'saldo_cliente');
    document.getElementById('avisoClienteRequerido').classList.toggle('hidden', !requiereCliente || !!clienteSeleccionadoId);
    actualizarInfoCliente();
}

function actualizarInfoCliente() {
    const info = document.getElementById('infoSaldoCliente');
    if (tipoPagoActual !== 'saldo_cliente' || !clienteSeleccionadoId) { info.classList.add('hidden'); return; }

    const cliente = clientesData.find(c => String(c.id) === String(clienteSeleccionadoId));
    if (!cliente) { info.classList.add('hidden'); return; }

    const tot = parseFloat(document.getElementById('total').textContent.replace('Q','')) || 0;
    const restante = cliente.saldo_favor - tot;

    document.getElementById('saldoDisponibleCliente').textContent = 'Q' + cliente.saldo_favor.toFixed(2);
    const spanRestante = document.getElementById('saldoRestanteCliente');
    spanRestante.textContent = 'Q' + restante.toFixed(2);
    spanRestante.className = 'font-bold ' + (restante < 0 ? 'text-red-600' : 'text-gray-600');
    info.classList.remove('hidden');
}

async function actualizarDisponibilidadCredito() {
    const opcionCredito = document.getElementById('opcionPago_credito');
    const avisoCredito = document.getElementById('avisoCreditoOffline');
    const radioCredito = opcionCredito.querySelector('input');

    const opcionSaldo = document.getElementById('opcionPago_saldo_cliente');
    const avisoSaldo = document.getElementById('avisoSaldoOffline');
    const radioSaldo = opcionSaldo.querySelector('input');

    const online = await hayConexionReal();

    if (!online) {
        opcionCredito.classList.add('opacity-40', 'cursor-not-allowed');
        radioCredito.disabled = true;
        avisoCredito.classList.remove('hidden');

        opcionSaldo.classList.add('opacity-40', 'cursor-not-allowed');
        radioSaldo.disabled = true;
        avisoSaldo.classList.remove('hidden');

        if (radioCredito.checked || radioSaldo.checked) {
            document.querySelector('input[name="tipoPago"][value="efectivo"]').checked = true;
            cambiarTipoPago('efectivo');
        }
    } else {
        opcionCredito.classList.remove('opacity-40', 'cursor-not-allowed');
        radioCredito.disabled = false;
        avisoCredito.classList.add('hidden');

        opcionSaldo.classList.remove('opacity-40', 'cursor-not-allowed');
        radioSaldo.disabled = false;
        avisoSaldo.classList.add('hidden');
    }
}

window.addEventListener('online', actualizarDisponibilidadCredito);
window.addEventListener('offline', actualizarDisponibilidadCredito);
document.addEventListener('DOMContentLoaded', actualizarDisponibilidadCredito);
document.addEventListener('DOMContentLoaded', () => document.getElementById('buscadorProducto').focus());
document.addEventListener('DOMContentLoaded', precargarDesdeCotizacion);

// ── Precargar el carrito si venimos de "Convertir en Venta" ──
function precargarDesdeCotizacion() {
    if (!cotizacionOrigenId || cotizacionOrigenItems.length === 0) return;

    carrito = cotizacionOrigenItems.map(it => ({
        id: String(it.id),
        nombre: it.nombre,
        codigo: it.codigo,
        precio: it.precio,
        precioMinimo: it.precioMinimo,
        stock: it.stock,
        unidad: it.unidad,
        cantidad: it.cantidad,
    }));
    renderCarrito();

    if (cotizacionOrigenClienteId) {
        const cliente = clientesData.find(c => String(c.id) === String(cotizacionOrigenClienteId));
        if (cliente) seleccionarCliente(cliente.id);
    }

    // Ya no tiene sentido volver a guardarla como cotización mientras se está convirtiendo
    document.getElementById('btnGuardarCotizacion').style.display = 'none';
}

// ── Vaciar venta actual (F8) ──────────────────────────────
function vaciarVenta() {
    if (carrito.length === 0) return;
    if (!confirm('¿Vaciar la venta actual? Se perderá todo lo agregado al carrito.')) return;
    carrito = [];
    renderCarrito();
    document.getElementById('descuento').value = 0;
    document.getElementById('montoRecibido').value = 0;
    document.getElementById('notasVenta').value = '';
    quitarClienteSeleccionado();
    document.querySelector('input[name="tipoPago"][value="efectivo"]').checked = true;
    cambiarTipoPago('efectivo');
    recalcular();
    document.getElementById('buscadorProducto').focus();
}

// ── Modal nuevo cliente rápido (F2) ───────────────────────
function abrirModalNuevoCliente() {
    document.getElementById('modalNuevoCliente').classList.remove('hidden');
    document.getElementById('nuevoClienteNombre').focus();
}
function cerrarModalNuevoCliente() {
    document.getElementById('modalNuevoCliente').classList.add('hidden');
    document.getElementById('formNuevoClienteRapido').reset();
    document.getElementById('buscadorProducto').focus();
}
async function guardarClienteRapido(e) {
    e.preventDefault();
    const nombre = document.getElementById('nuevoClienteNombre').value.trim();
    if (!nombre) return false;
    const telefono = document.getElementById('nuevoClienteTelefono').value.trim();
    const nit = document.getElementById('nuevoClienteNit').value.trim();

    try {
        const resp = await fetch('<?= URL_BASE ?>/?url=clientes/crearRapido', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ nombre, telefono, nit })
        });
        const data = await resp.json();
        if (!data.exito) {
            alert(data.mensaje || 'No se pudo crear el cliente.');
            return false;
        }
        clientesData.push({ id: data.id, nombre: data.nombre, nit, telefono, saldo_favor: 0 });
        cerrarModalNuevoCliente();
        seleccionarCliente(data.id);
    } catch (err) {
        alert('Error de conexión al crear el cliente.');
    }
    return false;
}

// ── Modal de ayuda: atajos de teclado ───────────────────
function abrirModalAtajos() {
    document.getElementById('modalAtajos').classList.remove('hidden');
}
function cerrarModalAtajos() {
    document.getElementById('modalAtajos').classList.add('hidden');
    document.getElementById('buscadorProducto').focus();
}

// ── Atajos de teclado globales ─────────────────────────────
function hayModalAbierto() {
    return !document.getElementById('modalCamara').classList.contains('hidden')
        || !document.getElementById('modalNuevoCliente').classList.contains('hidden')
        || !document.getElementById('modalAtajos').classList.contains('hidden')
        || !document.getElementById('modalMontoRecibido').classList.contains('hidden')
        || !document.getElementById('modalTicketOffline').classList.contains('hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (!document.getElementById('modalNuevoCliente').classList.contains('hidden')) {
            cerrarModalNuevoCliente(); return;
        }
        if (!document.getElementById('modalAtajos').classList.contains('hidden')) {
            cerrarModalAtajos(); return;
        }
        if (!document.getElementById('modalMontoRecibido').classList.contains('hidden')) {
            cerrarModalMontoRecibido(); return;
        }
        if (!document.getElementById('modalCamara').classList.contains('hidden')) {
            cerrarCamara(); return;
        }
        if (!document.getElementById('resultadosClientes').classList.contains('hidden')) {
            resultadosClientesActuales = []; pintarListaClientes(); return;
        }
        if (!document.getElementById('resultadosProductos').classList.contains('hidden')) {
            document.getElementById('buscadorProducto').value = '';
            filtrarProductos('');
            return;
        }
        return;
    }
    if (hayModalAbierto()) return;
    if (e.key === 'F2') {
        e.preventDefault();
        abrirModalNuevoCliente();
    } else if (e.key === 'F8') {
        e.preventDefault();
        vaciarVenta();
    } else if (e.key === 'F9') {
        e.preventDefault();
        iniciarCobro();
    }
});

// ── Cobro rápido con monto recibido (F9) ──────────────────
function iniciarCobro() {
    if (carrito.length === 0) { alert('El carrito está vacío.'); return; }
    if (tipoPagoActual === 'efectivo') {
        abrirModalMontoRecibido();
    } else {
        procesarVenta();
    }
}

function abrirModalMontoRecibido() {
    const tot = parseFloat(document.getElementById('total').textContent.replace('Q','')) || 0;
    document.getElementById('totalModalMonto').textContent = 'Q' + tot.toFixed(2);
    const input = document.getElementById('montoRecibidoModal');
    input.value = tot.toFixed(2);
    document.getElementById('modalMontoRecibido').classList.remove('hidden');
    actualizarCambioModal();
    input.focus();
    input.select();
}

function cerrarModalMontoRecibido() {
    document.getElementById('modalMontoRecibido').classList.add('hidden');
    document.getElementById('buscadorProducto').focus();
}

function actualizarCambioModal() {
    const tot = parseFloat(document.getElementById('total').textContent.replace('Q','')) || 0;
    const recibido = parseFloat(document.getElementById('montoRecibidoModal').value) || 0;
    const cambio = Math.max(0, recibido - tot);
    document.getElementById('cambioModal').textContent = 'Q' + cambio.toFixed(2);
    document.getElementById('avisoMontoInsuficienteModal').classList.toggle('hidden', recibido >= tot);
}

function confirmarMontoRecibido() {
    const tot = parseFloat(document.getElementById('total').textContent.replace('Q','')) || 0;
    const recibido = parseFloat(document.getElementById('montoRecibidoModal').value) || 0;
    if (recibido < tot) {
        alert('El monto recibido es menor al total.');
        return;
    }
    document.getElementById('montoRecibido').value = recibido;
    calcularCambio();
    cerrarModalMontoRecibido();
    procesarVenta();
}

// ── Guardar el carrito actual como cotización (no toca stock) ──
function guardarComoCotizacion() {
    if (carrito.length === 0) { alert('El carrito está vacío.'); return; }

    const itemInvalido = carrito.find(i => i.precio < (i.precioMinimo || 0) - 0.001);
    if (itemInvalido) {
        alert('El precio de "' + itemInvalido.nombre + '" está por debajo del mínimo permitido (Q' + itemInvalido.precioMinimo.toFixed(2) + ').');
        return;
    }

    const desc = parseFloat(document.getElementById('descuento').value) || 0;
    document.getElementById('inputItemsCotizacion').value = JSON.stringify(carrito.map(i=>({id:i.id,cantidad:i.cantidad,precio:i.precio})));
    document.getElementById('inputClienteCotizacion').value = clienteSeleccionadoId;
    document.getElementById('inputDescuentoCotizacion').value = desc;
    document.getElementById('inputNotasCotizacion').value = document.getElementById('notasVenta').value;
    document.getElementById('formCotizacion').submit();
}

async function procesarVenta() {
    if (carrito.length === 0) { alert('El carrito está vacío.'); return; }

    const itemInvalido = carrito.find(i => i.precio < (i.precioMinimo || 0) - 0.001);
    if (itemInvalido) {
        alert('El precio de "' + itemInvalido.nombre + '" está por debajo del mínimo permitido (Q' + itemInvalido.precioMinimo.toFixed(2) + ').');
        return;
    }

    const tipoPago = document.querySelector('input[name="tipoPago"]:checked').value;

    const btnCobrar = document.getElementById('btnCobrar');
    const textoOriginal = btnCobrar.innerHTML;
    btnCobrar.disabled = true;
    btnCobrar.innerHTML = '⏳ Verificando conexión...';

    const online = await hayConexionReal();
    btnCobrar.disabled = false;
    btnCobrar.innerHTML = textoOriginal;

    if (tipoPago === 'credito' && !online) {
        alert('No podés realizar ventas a crédito sin conexión a internet. Elegí efectivo o tarjeta, o esperá a recuperar la conexión.');
        return;
    }
    if (tipoPago === 'saldo_cliente' && !online) {
        alert('No podés cobrar con saldo a favor sin conexión a internet. Elegí efectivo o tarjeta, o esperá a recuperar la conexión.');
        return;
    }
    if (tipoPago === 'credito' && !clienteSeleccionadoId) {
        alert('Seleccioná un cliente para venta a crédito.'); return;
    }
    if (tipoPago === 'saldo_cliente' && !clienteSeleccionadoId) {
        alert('Seleccioná un cliente para pagar con saldo a favor.'); return;
    }

    const desc = parseFloat(document.getElementById('descuento').value) || 0;
    const sub  = carrito.reduce((a,i) => a + i.precio * i.cantidad, 0);
    const tot  = Math.max(0, sub - desc);
    const minimoTotal = carrito.reduce((a,i) => a + i.cantidad * i.precioMinimo, 0);
    if (tot < minimoTotal - 0.01) {
        alert('El descuento aplicado deja el total en Q' + tot.toFixed(2) + ', por debajo del mínimo permitido para este carrito (Q' + minimoTotal.toFixed(2) + ').');
        return;
    }
    if (tipoPago === 'saldo_cliente') {
        const cliente = clientesData.find(c => String(c.id) === String(clienteSeleccionadoId));
        if (!cliente || cliente.saldo_favor < tot - 0.01) {
            alert('El cliente no tiene saldo suficiente. Saldo disponible: Q' + (cliente ? cliente.saldo_favor.toFixed(2) : '0.00') + ', total de la venta: Q' + tot.toFixed(2) + '.');
            return;
        }
    }
    const recibido = parseFloat(document.getElementById('montoRecibido').value) || 0;
    if (tipoPago === 'efectivo' && recibido < tot) {
        alert('El monto recibido es menor al total.'); return;
    }

    const clienteId = clienteSeleccionadoId;
    const notas = document.getElementById('notasVenta').value;

    if (!online) {
        const datosVenta = {
            items: carrito.map(i => ({ id: i.id, cantidad: i.cantidad, precio: i.precio, nombre: i.nombre, unidad: i.unidad })),
            // Sucursal de la CAJA con la que se armo este carrito: al sincronizar, la venta va a la caja abierta de ESA sucursal
            sucursal_id: <?= (int) ($sucCaja ?? 0) ?>,
            cliente_id: clienteId,
            tipo_pago: tipoPago,
            monto_recibido: recibido,
            descuento: desc,
            notas: notas,
            subtotal: sub,
            total: tot,
            cambio: Math.max(0, recibido - tot),
        };

        guardarVentaOffline(datosVenta).then((idLocal) => {
            mostrarTicketOffline({ ...datosVenta, idLocal });
        }).catch((err) => {
            alert('Error guardando la venta offline: ' + err);
        });
        return;
    }

    document.getElementById('inputItems').value    = JSON.stringify(carrito.map(i=>({id:i.id,cantidad:i.cantidad,precio:i.precio})));
    document.getElementById('inputCliente').value  = clienteId;
    document.getElementById('inputRecibido').value = recibido;
    document.getElementById('inputDescuento').value= desc;
    document.getElementById('inputNotas').value    = notas;
    document.getElementById('formVenta').submit();
}

function mostrarTicketOffline(venta) {
    const fecha = new Date().toLocaleString('es-GT');
    const itemsHtml = venta.items.map(i => `
        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <div>
                <div style="font-size:11px;font-weight:bold;">${escaparHtml(i.nombre)}</div>
                <div style="font-size:10px;color:#777;">${i.cantidad} x Q${i.precio.toFixed(2)}</div>
            </div>
            <div style="font-size:11px;font-weight:bold;">Q${(i.cantidad*i.precio).toFixed(2)}</div>
        </div>
    `).join('');

    const html = `
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 no-print">
            <span class="font-bold text-gray-700 text-sm">🧾 Ticket</span>
            <button onclick="cerrarTicketOffline()" class="text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
        </div>
        <div id="ticketImprimible" style="font-family:'Courier New',monospace;font-size:12px;padding:16px;">
            <div style="text-align:center;">
                <div style="font-size:16px;font-weight:bold;">🏪 ${escaparHtml(window.NOMBRE_SUCURSAL_JS || window.NOMBRE_NEGOCIO_JS || 'POS GT')}</div>
                <div style="font-size:10px;color:#777;">${fecha}</div>
            </div>
            <div style="border-top:1px dashed #999;margin:10px 0;"></div>
            ${itemsHtml}
            <div style="border-top:1px dashed #999;margin:10px 0;"></div>
            <div style="font-size:11px;display:flex;justify-content:space-between;"><span>Subtotal</span><span>Q${venta.subtotal.toFixed(2)}</span></div>
            ${venta.descuento > 0 ? `<div style="font-size:11px;color:#c00;display:flex;justify-content:space-between;"><span>Descuento</span><span>-Q${venta.descuento.toFixed(2)}</span></div>` : ''}
            <div style="font-size:14px;font-weight:bold;display:flex;justify-content:space-between;"><span>TOTAL</span><span>Q${venta.total.toFixed(2)}</span></div>
            <div style="font-size:11px;display:flex;justify-content:space-between;color:#777;"><span>Pago (${escaparHtml(venta.tipo_pago)})</span><span>Q${venta.monto_recibido.toFixed(2)}</span></div>
            ${venta.cambio > 0 ? `<div style="font-size:11px;font-weight:bold;color:green;display:flex;justify-content:space-between;"><span>Cambio</span><span>Q${venta.cambio.toFixed(2)}</span></div>` : ''}
            ${venta.notas ? `<div style="border-top:1px dashed #999;margin:10px 0;"></div><div style="font-size:11px;color:#444;"><b>Nota:</b> ${escaparHtml(venta.notas)}</div>` : ''}
            <div style="border-top:1px dashed #999;margin:10px 0;"></div>
            <div style="text-align:center;font-size:10px;color:#777;">¡Gracias por su compra!</div>
        </div>
        <div class="p-4 border-t border-gray-100 no-print">
            <button onclick="imprimirTicketOffline()"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl text-sm transition">
                🖨️ Imprimir
            </button>
        </div>
    `;

    document.getElementById('contenidoTicketOffline').innerHTML = html;
    document.getElementById('modalTicketOffline').classList.remove('hidden');

    carrito = [];
    renderCarrito();
    document.getElementById('descuento').value = 0;
    document.getElementById('montoRecibido').value = 0;
    document.getElementById('notasVenta').value = '';
    quitarClienteSeleccionado();
    document.querySelector('input[name="tipoPago"][value="efectivo"]').checked = true;
    cambiarTipoPago('efectivo');
    recalcular();
}

function imprimirTicketOffline() {
    window.print();
}

function cerrarTicketOffline() {
    document.getElementById('modalTicketOffline').classList.add('hidden');
    document.getElementById('contenidoTicketOffline').innerHTML = '';
    mostrarNotificacionVentaGuardada();
}

function mostrarNotificacionVentaGuardada() {
    const div = document.createElement('div');
    div.className = 'fixed top-4 right-4 z-50 bg-yellow-500 text-white px-5 py-3 rounded-xl shadow-lg font-bold text-sm';
    div.textContent = '⏳ Venta guardada offline. Se sincronizará automáticamente.';
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 4000);
}
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
