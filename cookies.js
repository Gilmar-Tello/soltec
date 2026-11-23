/* ============================================================
   SOLTEC — Aviso de cookies de la landing (soltec.click)
   - Guarda la decision del visitante en una cookie propia (1 año).
   - Solo si acepta, se activa el contador de visitas (contador.php).
   - Si rechaza, no se envia nada al servidor ni se guarda otra cookie.
   ============================================================ */
(function () {
    'use strict';

    var NOMBRE = 'soltec_cookies';
    var banner = document.getElementById('aviso-cookies');
    if (!banner) { return; }

    function leer() {
        var m = document.cookie.match(new RegExp('(?:^|; )' + NOMBRE + '=([^;]*)'));
        return m ? decodeURIComponent(m[1]) : null;
    }

    function guardar(valor) {
        var seguro = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = NOMBRE + '=' + encodeURIComponent(valor) +
            '; Max-Age=31536000; Path=/; SameSite=Lax' + seguro;
    }

    function borrarCookieContador() {
        // Si antes aceptó y ahora rechaza, se quita la cookie del contador.
        document.cookie = 'soltec_visita=; Max-Age=0; Path=/; SameSite=Lax';
    }

    function contarVisita() {
        fetch('contador.php', { method: 'POST', credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (d && Number.isInteger(d.visitas)) {
                    document.getElementById('visitas-numero').textContent = d.visitas.toLocaleString('es-GT');
                    document.getElementById('visitas-caja').hidden = false;
                }
            })
            .catch(function () {});
    }

    function mostrar() {
        banner.hidden = false;
        var aceptar = document.getElementById('cookies-aceptar');
        if (aceptar) { aceptar.focus({ preventScroll: true }); }
    }

    function decidir(valor) {
        guardar(valor);
        banner.hidden = true;
        if (valor === 'aceptadas') {
            contarVisita();
        } else {
            borrarCookieContador();
            document.getElementById('visitas-caja').hidden = true;
        }
    }

    document.getElementById('cookies-aceptar').addEventListener('click', function () { decidir('aceptadas'); });
    document.getElementById('cookies-rechazar').addEventListener('click', function () { decidir('rechazadas'); });

    // Enlace del pie para cambiar la decision
    var cambiar = document.getElementById('cookies-cambiar');
    if (cambiar) {
        cambiar.addEventListener('click', function (e) { e.preventDefault(); mostrar(); });
    }

    var previa = leer();
    if (previa === 'aceptadas') {
        contarVisita();
    } else if (previa !== 'rechazadas') {
        mostrar();
    }
})();
