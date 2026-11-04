// ============================================================
// MÓDULO OFFLINE — POS GT
// Maneja: registro de Service Worker, IndexedDB, detección de
// conexión, y sincronización de ventas pendientes.
// ============================================================

const DB_NAME = 'pos_gt_offline';
const DB_VERSION = 1;
const STORE_VENTAS = 'ventas_pendientes';

let dbOffline = null;

// ── Verificación REAL de conexión (más confiable que navigator.onLine) ──
// navigator.onLine puede reportar "true" incorrectamente en Windows/Firefox
// aunque no haya internet real. Hacemos un ping corto al propio servidor.
async function hayConexionReal() {
    if (!navigator.onLine) return false; // si el navegador ya sabe que no hay red, confiamos en eso
    try {
        const controlador = new AbortController();
        const timeoutId = setTimeout(() => controlador.abort(), 2500);
        const respuesta = await fetch(window.URL_BASE_JS + '/?url=pos/ping', {
            method: 'GET',
            cache: 'no-store',
            signal: controlador.signal
        });
        clearTimeout(timeoutId);
        return respuesta.ok;
    } catch (err) {
        return false;
    }
}

// ── Registrar Service Worker ─────────────────────────────────
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(window.URL_BASE_JS + '/app/public/js/sw.js')
            .then(() => console.log('✅ Service Worker registrado'))
            .catch(err => console.warn('⚠️ Error registrando Service Worker:', err));
    });
}

// ── Abrir/crear IndexedDB ────────────────────────────────────
function abrirDB() {
    return new Promise((resolve, reject) => {
        if (dbOffline) { resolve(dbOffline); return; }
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_VENTAS)) {
                const store = db.createObjectStore(STORE_VENTAS, { keyPath: 'idLocal', autoIncrement: true });
                store.createIndex('estado', 'estado', { unique: false });
            }
        };
        request.onsuccess = (event) => { dbOffline = event.target.result; resolve(dbOffline); };
        request.onerror = (event) => reject(event.target.error);
    });
}

// ── Identificador único por venta offline ────────────────────
function generarUuidVenta() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'v-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
}

// ── Guardar una venta pendiente ──────────────────────────────
async function guardarVentaOffline(datosVenta) {
    const db = await abrirDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_VENTAS, 'readwrite');
        const store = tx.objectStore(STORE_VENTAS);
        const registro = {
            ...datosVenta,
            uuid: generarUuidVenta(),   // identificador único: el servidor ignora duplicados
            estado: 'pendiente',
            fechaCreacion: new Date().toISOString(),
        };
        const req = store.add(registro);
        req.onsuccess = () => resolve(req.result); // devuelve idLocal
        req.onerror = () => reject(req.error);
    });
}

// ── Obtener todas las ventas pendientes ──────────────────────
async function obtenerVentasPendientes() {
    const db = await abrirDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_VENTAS, 'readonly');
        const store = tx.objectStore(STORE_VENTAS);
        const req = store.getAll();
        req.onsuccess = () => resolve(req.result.filter(v => v.estado === 'pendiente'));
        req.onerror = () => reject(req.error);
    });
}

// ── Marcar una venta como sincronizada (la elimina de la cola) ──
async function eliminarVentaOffline(idLocal) {
    const db = await abrirDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_VENTAS, 'readwrite');
        tx.objectStore(STORE_VENTAS).delete(idLocal);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

// ── Marcar venta con error de sincronización ──────────────────
async function marcarVentaConError(idLocal, mensaje) {
    const db = await abrirDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_VENTAS, 'readwrite');
        const store = tx.objectStore(STORE_VENTAS);
        const req = store.get(idLocal);
        req.onsuccess = () => {
            const venta = req.result;
            venta.estado = 'error';
            venta.errorMensaje = mensaje;
            store.put(venta);
            resolve();
        };
        req.onerror = () => reject(req.error);
    });
}

// ── Sincronizar ventas pendientes con el servidor ────────────
let sincronizandoAhora = false;
async function sincronizarVentasPendientes() {
    // Evita dos sincronizaciones en paralelo en la misma pestaña
    if (sincronizandoAhora) return;
    sincronizandoAhora = true;
    try {
        await sincronizarVentasPendientesInterno();
    } finally {
        sincronizandoAhora = false;
    }
}

async function sincronizarVentasPendientesInterno() {
    const online = await hayConexionReal();
    if (!online) return;

    const pendientes = await obtenerVentasPendientes();
    if (pendientes.length === 0) return;

    actualizarIndicadorSync(pendientes.length, true);

    for (const venta of pendientes) {
        try {
            const formData = new FormData();
            formData.append('items', JSON.stringify(venta.items));
            formData.append('cliente_id', venta.cliente_id || '');
            formData.append('tipo_pago', venta.tipo_pago);
            formData.append('monto_recibido', venta.monto_recibido);
            formData.append('descuento', venta.descuento || 0);
            formData.append('notas', venta.notas || '');
            // Sucursal de la caja con la que se hizo la venta ('' en las ventas guardadas antes de esta version)
            formData.append('sucursal_id', (venta.sucursal_id === undefined || venta.sucursal_id === null) ? '' : venta.sucursal_id);
            formData.append('offline_id', venta.idLocal);
            // Ventas guardadas antes de esta versión no tienen uuid: se arma uno estable
            formData.append('offline_uuid', venta.uuid || ('legacy-' + venta.idLocal + '-' + (venta.fechaCreacion || '')));

            const respuesta = await fetch(window.URL_BASE_JS + '/?url=pos/procesarVentaOffline', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });

            const resultado = await respuesta.json();

            if (resultado.exito) {
                await eliminarVentaOffline(venta.idLocal);
            } else {
                await marcarVentaConError(venta.idLocal, resultado.mensaje || 'Error desconocido');
            }
        } catch (err) {
            console.warn('Error sincronizando venta offline:', err);
            // No eliminamos, se reintentará en el próximo ciclo
        }
    }

    const restantes = await obtenerVentasPendientes();
    actualizarIndicadorSync(restantes.length, false);

    if (restantes.length === 0 && pendientes.length > 0) {
        mostrarNotificacionSync('✅ Todas las ventas se sincronizaron correctamente.');
    }
}

// ── Indicador visual de estado de conexión ───────────────────
function crearIndicadorConexion() {
    if (document.getElementById('indicadorConexion')) return;
    const div = document.createElement('div');
    div.id = 'indicadorConexion';
    div.className = 'fixed bottom-4 right-4 z-50 hidden';
    div.innerHTML = `
        <div id="badgeConexion" class="flex items-center gap-2 px-4 py-2 rounded-full shadow-lg text-sm font-bold transition-all">
            <span id="puntoConexion" class="w-2 h-2 rounded-full"></span>
            <span id="textoConexion"></span>
        </div>
    `;
    document.body.appendChild(div);
}

function actualizarIndicadorConexion() {
    crearIndicadorConexion();
    const cont = document.getElementById('indicadorConexion');
    const badge = document.getElementById('badgeConexion');
    const punto = document.getElementById('puntoConexion');
    const texto = document.getElementById('textoConexion');

    if (navigator.onLine) {
        cont.classList.add('hidden');
    } else {
        cont.classList.remove('hidden');
        badge.className = 'flex items-center gap-2 px-4 py-2 rounded-full shadow-lg text-sm font-bold bg-red-100 text-red-700';
        punto.className = 'w-2 h-2 rounded-full bg-red-500 animate-pulse';
        texto.textContent = '🔴 Sin conexión';
    }
}

async function actualizarIndicadorSync(cantidad, sincronizando) {
    crearIndicadorConexion();
    const cont = document.getElementById('indicadorConexion');
    const badge = document.getElementById('badgeConexion');
    const punto = document.getElementById('puntoConexion');
    const texto = document.getElementById('textoConexion');

    if (cantidad > 0) {
        cont.classList.remove('hidden');
        if (sincronizando) {
            badge.className = 'flex items-center gap-2 px-4 py-2 rounded-full shadow-lg text-sm font-bold bg-blue-100 text-blue-700';
            punto.className = 'w-2 h-2 rounded-full bg-blue-500 animate-pulse';
            texto.textContent = `🔄 Sincronizando ${cantidad} venta(s)...`;
        } else {
            badge.className = 'flex items-center gap-2 px-4 py-2 rounded-full shadow-lg text-sm font-bold bg-yellow-100 text-yellow-700';
            punto.className = 'w-2 h-2 rounded-full bg-yellow-500';
            texto.textContent = `⏳ ${cantidad} venta(s) pendiente(s) de sincronizar`;
        }
    } else if (navigator.onLine) {
        cont.classList.add('hidden');
    }
}

function mostrarNotificacionSync(mensaje) {
    const div = document.createElement('div');
    div.className = 'fixed top-4 right-4 z-50 bg-green-600 text-white px-5 py-3 rounded-xl shadow-lg font-bold text-sm';
    div.textContent = mensaje;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 4000);
}

// ── Listeners de conexión ────────────────────────────────────
window.addEventListener('online', () => {
    actualizarIndicadorConexion();
    sincronizarVentasPendientes();
});
window.addEventListener('offline', () => {
    actualizarIndicadorConexion();
});

// ── Inicialización al cargar cualquier página ────────────────
document.addEventListener('DOMContentLoaded', () => {
    actualizarIndicadorConexion();
    obtenerVentasPendientes().then(pendientes => {
        if (pendientes.length > 0) actualizarIndicadorSync(pendientes.length, false);
    });
    if (navigator.onLine) sincronizarVentasPendientes();
});