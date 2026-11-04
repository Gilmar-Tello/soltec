<?php
// ============================================================
//  TAREA PROGRAMADA (cron) — envia a Brix los movimientos que anoto el POS.
//  Solo se ejecuta desde la linea de comandos; por la web no hace nada.
//
//  En hPanel > Avanzado > Cron Jobs: tipo PHP, cada minuto, comando:
//     /home/u923763167/domains/soltec.click/public_html/admin/cron/sync_pos_brix.php
//  (o con el boton "Sincronizar ahora" en Panel > Clientes > Funciones)
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

date_default_timezone_set('America/Guatemala');
define('RAIZ', dirname(__DIR__));
define('PRIVADO', dirname(RAIZ, 2) . '/privado');
define('URL_BASE', 'https://admin.soltec.click');

require_once RAIZ . '/config/seguridad.php';
require_once PRIVADO . '/admin_base_datos.php';
require_once RAIZ . '/config/sync_brix.php';

try {
    $r = sincronizarPosBrix();
    echo date('Y-m-d H:i:s') . " sync_pos_brix: enviados={$r['enviados']} errores={$r['errores']} en_espera={$r['en_espera']}\n";
} catch (Throwable $e) {
    error_log('[SOLTEC sync_brix cron] ' . $e->getMessage());
    echo date('Y-m-d H:i:s') . " sync_pos_brix: FALLO — " . $e->getMessage() . "\n";
    exit(1);
}
