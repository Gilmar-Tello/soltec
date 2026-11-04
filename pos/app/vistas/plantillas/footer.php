</main>

    <footer class="text-center text-xs text-green-700 py-4 opacity-60">
        SISTEMAS GT &copy; <?= date('Y') ?> — POS GT
    </footer>

</div>

<!-- Overlay móvil -->
<div onclick="toggleMenu(false)"
     class="fixed inset-0 bg-black bg-opacity-40 z-40 hidden md:hidden" id="overlay"></div>

<script>
// Abre/cierra el menú lateral en móvil junto con el fondo oscuro
function toggleMenu(forzar) {
    const sidebar = document.getElementById('sidebar');
    const abrir = (typeof forzar === 'boolean') ? forzar : !sidebar.classList.contains('abierto');
    sidebar.classList.toggle('abierto', abrir);
    document.getElementById('overlay').classList.toggle('hidden', !abrir);
    document.body.classList.toggle('menu-abierto', abrir);
}
// Si se agranda la pantalla (girar el celular/tablet), cerrar el menú móvil
window.addEventListener('resize', () => { if (window.innerWidth >= 768) toggleMenu(false); });
</script>

<script>
// ── Zoom de fotos de producto ─────────────────────────────────────────────────────────────────────────
// Cualquier <img data-zoom="..."> se abre en grande al tocarla, sin recargar la pagina. Si data-zoom viene vacio se
// usa el src de la propia imagen. Se cierra con la X, tocando fuera de la foto o con Escape; tocar la foto la
// amplia al doble y se puede mover con el dedo. Un solo visor para todo el sistema (inventario, venta y formulario).
(function () {
    var visor = null, marco = null, foto = null, titulo = null, ampliada = false;

    function crear() {
        visor = document.createElement('div');
        visor.setAttribute('role', 'dialog');
        visor.setAttribute('aria-modal', 'true');
        visor.setAttribute('aria-label', 'Foto del producto');
        visor.style.cssText = 'position:fixed;top:0;right:0;bottom:0;left:0;z-index:70;display:none;flex-direction:column;align-items:center;justify-content:center;background:rgba(0,0,0,.85);padding:12px;';

        var cerrar = document.createElement('button');
        cerrar.type = 'button';
        cerrar.setAttribute('aria-label', 'Cerrar foto');
        cerrar.innerHTML = '&times;';
        cerrar.style.cssText = 'position:absolute;top:12px;right:12px;width:48px;height:48px;border-radius:9999px;background:#fff;color:#374151;font-size:32px;font-weight:700;line-height:1;box-shadow:0 2px 8px rgba(0,0,0,.4);';

        marco = document.createElement('div');
        marco.style.cssText = 'max-width:100%;max-height:80vh;overflow:auto;';
        foto = document.createElement('img');
        foto.alt = '';
        marco.appendChild(foto);

        titulo = document.createElement('div');
        titulo.style.cssText = 'margin-top:12px;color:#fff;font-weight:700;font-size:14px;text-align:center;padding:0 16px;pointer-events:none;';
        var ayuda = document.createElement('div');
        ayuda.textContent = 'Toca la foto para ampliar · toca fuera o la X para cerrar';
        ayuda.style.cssText = 'margin-top:4px;color:#fff;opacity:.7;font-size:12px;text-align:center;padding:0 16px;pointer-events:none;';

        visor.appendChild(cerrar);
        visor.appendChild(marco);
        visor.appendChild(titulo);
        visor.appendChild(ayuda);
        document.body.appendChild(visor);

        cerrar.addEventListener('click', cerrarVisor);
        visor.addEventListener('click', function (e) { if (e.target === visor) cerrarVisor(); });   // tocar fuera de la foto
        foto.addEventListener('click', function (e) { e.stopPropagation(); alternarZoom(); });
    }

    function reiniciar() {
        ampliada = false;
        foto.style.cssText = 'display:block;max-width:100%;max-height:80vh;margin:0 auto;border-radius:12px;background:#fff;cursor:zoom-in;';
    }

    function alternarZoom() {
        if (ampliada) { reiniciar(); return; }
        var anchoBase = foto.getBoundingClientRect().width;
        foto.style.maxWidth = 'none';
        foto.style.maxHeight = 'none';
        foto.style.width = (anchoBase * 2) + 'px';
        foto.style.height = 'auto';
        foto.style.cursor = 'zoom-out';
        ampliada = true;
    }

    function abrir(url, texto) {
        if (!visor) crear();
        reiniciar();
        foto.src = url;
        titulo.textContent = texto || '';
        visor.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function cerrarVisor() {
        if (!visor) return;
        visor.style.display = 'none';
        foto.removeAttribute('src');
        document.body.style.overflow = '';
    }

    // En fase de captura: se atiende antes que el clic de la fila (en Venta, tocar la foto NO agrega el producto)
    document.addEventListener('click', function (e) {
        var img = e.target && e.target.closest ? e.target.closest('img[data-zoom]') : null;
        if (!img) return;
        var url = img.getAttribute('data-zoom') || img.currentSrc || img.src;
        if (!url) return;
        e.preventDefault();
        e.stopPropagation();
        abrir(url, img.getAttribute('data-titulo') || '');
    }, true);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && visor && visor.style.display !== 'none') cerrarVisor();
    });
    var estilo = document.createElement('style');
    estilo.textContent = 'img[data-zoom]{cursor:zoom-in}';
    document.head.appendChild(estilo);
})();
</script>

<script src="<?= URL_BASE ?>/app/public/js/offline.js"></script>

<?php include __DIR__ . '/aviso_pago.php'; ?>
</body>
</html> 