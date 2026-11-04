<?php
// ============================================================
//  POS -> Brix: el POS NO se conecta a la base de Brix. Solo anota cada movimiento en su propia
//  cola (sync_brix_cola) y un proceso aparte del panel maestro la envia a Brix (con reintentos y
//  sin duplicar). Asi el POS no guarda credenciales de Brix.
//  Solo se anota si el negocio tiene encendida la funcion "sync_brix" y la vinculo a un negocio de Brix.
// ============================================================

// Id del negocio de Brix al que esta vinculado este negocio, o null si la sincronizacion esta apagada
function brixVinculado(int $tid): ?int {
    static $cache = [];
    if (array_key_exists($tid, $cache)) return $cache[$tid];
    $cache[$tid] = null;
    try {
        $st = obtenerConexion()->prepare(
            "SELECT valor FROM negocio_funciones WHERE tenant_id = :t AND funcion = 'sync_brix' AND activo = 1");
        $st->execute([':t' => $tid]);
        $v = (int) $st->fetchColumn();
        $cache[$tid] = $v > 0 ? $v : null;
    } catch (Throwable $e) {
        error_log('[SOLTEC pos] brixVinculado: ' . $e->getMessage());
    }
    return $cache[$tid];
}

// Anota un movimiento para Brix. Nunca interrumpe la operacion del POS: ante cualquier error, solo lo registra.
// $referencia identifica el hecho de forma unica (ej. pos:caja:15): anotarlo dos veces no lo duplica.
function encolarMovimientoBrix(int $tid, string $tipo, string $referencia, string $fecha, string $concepto, float $monto): void {
    $monto = round($monto, 2);
    if ($monto <= 0 || !in_array($tipo, ['ingreso', 'egreso'], true)) return;
    if (brixVinculado($tid) === null) return;
    try {
        obtenerConexion()->prepare(
            "INSERT IGNORE INTO sync_brix_cola (tenant_id, tipo, referencia, fecha, concepto, monto)
             VALUES (:t, :tp, :r, :f, :c, :m)"
        )->execute([':t' => $tid, ':tp' => $tipo, ':r' => $referencia, ':f' => $fecha,
                    ':c' => mb_substr($concepto, 0, 255), ':m' => $monto]);
    } catch (Throwable $e) {
        error_log('[SOLTEC pos] No se pudo anotar el movimiento para Brix (' . $referencia . '): ' . $e->getMessage());
    }
}
