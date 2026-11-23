// ============================================================
// SERVICE WORKER — POS GT (modo offline)
//
// Qué guarda en este equipo y qué NO:
//   · Estáticos (CSS/JS/imágenes/CDN): sí, para que el POS cargue sin internet.
//   · Páginas con sesión: SOLO la pantalla de venta y el inicio del POS (lo único que
//     hace falta para seguir vendiendo sin internet). Informes, tickets, cuentas de
//     clientes, usuarios, configuración, etc. NUNCA se guardan.
//   · Las páginas guardadas caducan a las 12 h y se borran al cerrar sesión o al
//     llegar al login (también desde login.php).
//   · Nunca se guarda una página a la que el servidor redirigió (p. ej. el login
//     cuando la sesión venció): antes quedaba el login guardado como si fuera la venta.
//   · pos/ping y la sincronización de ventas NO pasan por la caché: si no hay red de
//     verdad, deben fallar de verdad (antes un ping guardado hacía creer que había red
//     y la venta pendiente se marcaba con error).
// ============================================================
const CACHE_NAME = 'pos-gt-v6'; // v6: paleta de SOLTEC (las paginas guardadas se renuevan). v5: las ventas offline viajan con su sucursal (offline.js nuevo). v4: solo se guardan pos/index y pos/venta; caducidad de 12 h
const EDAD_MAX_PAGINA_MS = 12 * 60 * 60 * 1000;

// Rutas (?url=...) con sesión que SÍ se guardan para trabajar sin internet
const PAGINAS_OFFLINE = ['pos/index', 'pos/venta'];

// Las librerias JS ya se sirven desde /app/public/vendor/ (mismo sitio); solo quedan las fuentes de Google
const RECURSOS_EXTERNOS_CDN = [
    'https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap',
];

function rutaDe(urlTexto) {
    try {
        const u = new URL(urlTexto);
        if (u.origin !== self.location.origin) return null;
        return (u.searchParams.get('url') || '').replace(/\/+$/, '');
    } catch (e) { return null; }
}

function limpiarCaches() {
    return caches.keys().then((nombres) => Promise.all(nombres.map((n) => caches.delete(n))));
}

function paginaFresca(respuesta) {
    const fecha = respuesta.headers.get('date');
    if (!fecha) return true;
    const t = new Date(fecha).getTime();
    return isNaN(t) || (Date.now() - t) < EDAD_MAX_PAGINA_MS;
}

const PAGINA_SIN_CONEXION = () => new Response(
    `<!DOCTYPE html><html><head><meta charset="UTF-8">
    <title>Sin conexión</title>
    <style>body{font-family:sans-serif;text-align:center;padding:60px 20px;background:#f4f7fb;}
    h1{color:#0d1b2a;}p{color:#6b7280;}</style></head>
    <body><h1>📡 Sin conexión</h1>
    <p>Esta página no está disponible sin internet.</p>
    <p>Sin conexión solo funciona la pantalla de <b>Venta</b> (ábrela una vez con internet al empezar el turno).</p>
    </body></html>`,
    { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
);

// ── Instalación ────────────────────────────────────────────
self.addEventListener('install', () => {
    self.skipWaiting();
});

// ── Activación: borrar caches de versiones anteriores (incluye páginas viejas con sesión) ──
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((nombres) =>
            Promise.all(nombres.filter((n) => n !== CACHE_NAME).map((n) => caches.delete(n)))
        )
    );
    self.clients.claim();
});

// Orden desde la página (por si se quiere vaciar a mano)
self.addEventListener('message', (event) => {
    if (event.data === 'limpiar-cache') event.waitUntil(limpiarCaches());
});

// ── Estrategia de fetch ──────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const peticion = event.request;
    const url = peticion.url;
    const ruta = rutaDe(url);

    // Cerrar sesión / llegar al login: se vacía todo lo guardado en este equipo
    if (ruta === 'auth/salir' || ruta === 'auth/login') {
        event.waitUntil(limpiarCaches());
        return; // la petición sigue su camino normal
    }

    // Ping y sincronización de ventas offline: SIEMPRE a la red real, sin caché ni respuestas inventadas
    if (ruta === 'pos/ping' || ruta === 'pos/procesarVentaOffline') {
        return;
    }

    // Otros POST (login, ventas normales...): nunca se guardan
    if (peticion.method === 'POST') {
        event.respondWith(
            fetch(peticion).catch(() => new Response(JSON.stringify({ offline: true }), {
                headers: { 'Content-Type': 'application/json' }
            }))
        );
        return;
    }

    if (peticion.method !== 'GET') return;

    // Recursos estáticos (CSS, JS, imágenes, fuentes, CDN) → Cache First
    const esEstatico = /\.(css|js|png|jpg|jpeg|webp|svg|woff2?)$/.test(url)
        || RECURSOS_EXTERNOS_CDN.some((cdn) => url.startsWith(cdn));

    if (esEstatico) {
        event.respondWith(
            caches.match(peticion).then((guardado) => {
                if (guardado) return guardado;
                return fetch(peticion).then((respuesta) => {
                    if (respuesta && (respuesta.ok || respuesta.type === 'opaque')) {
                        const copia = respuesta.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(peticion, copia));
                    }
                    return respuesta;
                }).catch(() => guardado);
            })
        );
        return;
    }

    // Páginas con sesión que se permiten offline (venta e inicio) → Network First
    if (ruta !== null && PAGINAS_OFFLINE.includes(ruta)) {
        event.respondWith(
            fetch(peticion)
                .then((respuesta) => {
                    // Solo páginas completas y normales: nada redirigido (login) ni con error
                    if (respuesta && respuesta.status === 200 && !respuesta.redirected) {
                        const copia = respuesta.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(peticion, copia));
                    }
                    return respuesta;
                })
                .catch(() =>
                    caches.match(peticion).then((guardado) => {
                        if (guardado && paginaFresca(guardado)) return guardado;
                        if (guardado) caches.open(CACHE_NAME).then((cache) => cache.delete(peticion));
                        return PAGINA_SIN_CONEXION();
                    })
                )
        );
        return;
    }

    // Cualquier otra página (informes, tickets, clientes, usuarios...): solo red, sin guardar nada
    if (peticion.mode === 'navigate') {
        event.respondWith(fetch(peticion).catch(() => PAGINA_SIN_CONEXION()));
    }
    // Otros GET (AJAX, etc.): la red decide; no se guardan
});
