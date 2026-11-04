<?php
// ============================================================
//  SOLTEC — contador de visitas de la landing (soltec.click)
//
//  - Sin base de datos: el numero vive en un archivo JSON dentro de
//    /home/u923763167/privado (fuera de public_html, no se descarga).
//  - Cuenta UNA visita por navegador (cookie de 1 año), no por recarga.
//  - Solo cuenta peticiones POST del propio sitio (las lanza index.html),
//    asi que robots, previsualizaciones y enlaces directos no suman.
//  - Si algo falla, responde sin numero y la pagina simplemente oculta
//    el contador: nunca rompe la landing.
// ============================================================
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex');

function responder(?int $visitas, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode(['visitas' => $visitas]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(null, 405);
}

// ── Donde se guarda el numero ──────────────────────────────
$dirPrivado = dirname(__DIR__) . '/privado';
if (!is_dir($dirPrivado)) {
    @mkdir($dirPrivado, 0700, true);
}
$archivo = $dirPrivado . '/contador_landing.json';

// ── ¿Esta visita se debe sumar? ────────────────────────────
$nombreCookie = 'soltec_visita';
$yaContado    = isset($_COOKIE[$nombreCookie]);

$agente = $_SERVER['HTTP_USER_AGENT'] ?? '';
$esRobot = $agente === ''
    || preg_match('/bot|crawl|spider|slurp|curl|wget|python|httpclient|headless|preview|facebookexternalhit|monitor|uptime|lighthouse/i', $agente) === 1;

// Los navegadores modernos avisan desde donde sale la peticion
$sitio    = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
$esPropio = $sitio === '' || $sitio === 'same-origin';

$sumar = !$yaContado && !$esRobot && $esPropio;

// ── Leer (y sumar) con bloqueo: dos visitas a la vez no se pisan ──
$gestor = @fopen($archivo, 'c+');
if ($gestor === false) {
    error_log('[SOLTEC landing] No se pudo abrir ' . $archivo);
    responder(null, 503);
}

flock($gestor, $sumar ? LOCK_EX : LOCK_SH);

$datos = json_decode((string) stream_get_contents($gestor), true);
$total = is_array($datos) ? max(0, (int) ($datos['total'] ?? 0)) : 0;

if ($sumar) {
    $total++;
    $nuevo = json_encode([
        'total' => $total,
        'desde' => is_array($datos) && isset($datos['desde']) ? $datos['desde'] : date('Y-m-d'),
    ]);
    ftruncate($gestor, 0);
    rewind($gestor);
    fwrite($gestor, (string) $nuevo);
    fflush($gestor);
}

flock($gestor, LOCK_UN);
fclose($gestor);

// ── Marcar este navegador como ya contado ──────────────────
if ($sumar) {
    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie($nombreCookie, '1', [
        'expires'  => time() + 31536000,
        'path'     => '/',
        'secure'   => $esHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

responder($total);
