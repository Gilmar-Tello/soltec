<?php
// ============================================================
//  Envia a Brix lo que el POS anoto en su cola (sync_brix_cola).
//  Lo ejecutan: (1) la tarea programada (cron) cada minuto, y (2) el boton "Sincronizar ahora" del panel.
//  Es seguro correrlo muchas veces: Brix rechaza el duplicado por la referencia unica del movimiento.
//
//  Requiere que ya existan las columnas nuevas de Brix (migracion_brix_libro_caja) y la cola del POS
//  (migracion_pos_mejoras). Si algo falla, el movimiento queda pendiente y se reintenta; tras 10
//  intentos pasa a "error" y aparece en la pantalla de Funciones del cliente.
// ============================================================

const SYNC_BRIX_MAX_INTENTOS = 10;

function sincronizarPosBrix(int $limite = 300): array {
    $res = ['enviados' => 0, 'errores' => 0, 'en_espera' => 0];
    $pos  = obtenerConexion('pos');
    $brix = obtenerConexion('brix');

    // Solo se mandan movimientos de negocios con la funcion ENCENDIDA y vinculada a un negocio de Brix.
    // Los demas se quedan pendientes (no se pierden) hasta que la funcion se encienda.
    $st = $pos->prepare(
        "SELECT c.*, f.valor AS brix_id
         FROM sync_brix_cola c
         LEFT JOIN negocio_funciones f
                ON f.tenant_id = c.tenant_id AND f.funcion = 'sync_brix' AND f.activo = 1
         WHERE c.estado = 'pendiente'
         ORDER BY c.id ASC
         LIMIT :lim");
    $st->bindValue(':lim', $limite, PDO::PARAM_INT);
    $st->execute();

    $negociosOk = [];     // id de Brix => id de un usuario del negocio (a quien se atribuye el movimiento)
    foreach ($st->fetchAll() as $fila) {
        $brixId = (int) $fila['brix_id'];
        if ($brixId <= 0) { $res['en_espera']++; continue; }

        try {
            if (!isset($negociosOk[$brixId])) {
                $n = $brix->prepare("SELECT id FROM negocios WHERE id = :id AND activo = 1");
                $n->execute([':id' => $brixId]);
                if (!$n->fetchColumn()) throw new RuntimeException('El negocio de Brix no existe o está suspendido.');
                $u = $brix->prepare("SELECT id FROM usuarios WHERE tenant_id = :t AND activo = 1 ORDER BY id ASC LIMIT 1");
                $u->execute([':t' => $brixId]);
                $uid = (int) $u->fetchColumn();
                if ($uid <= 0) throw new RuntimeException('El negocio de Brix no tiene usuarios activos.');
                $negociosOk[$brixId] = $uid;
            }
            $uid = $negociosOk[$brixId];

            if ($fila['tipo'] === 'ingreso') {
                $brix->prepare(
                    "INSERT INTO ingresos (tenant_id, usuario_id, tipo, concepto, monto, creado_en, origen, referencia_externa)
                     VALUES (:t, :u, 'libre', :c, :m, :f, 'pos', :r)
                     ON DUPLICATE KEY UPDATE id = id"
                )->execute([':t' => $brixId, ':u' => $uid, ':c' => $fila['concepto'], ':m' => $fila['monto'],
                            ':f' => $fila['fecha'], ':r' => $fila['referencia']]);
            } else {
                $brix->prepare(
                    "INSERT INTO egresos (tenant_id, usuario_id, concepto, descripcion, monto, creado_en, origen, referencia_externa)
                     VALUES (:t, :u, :c, NULL, :m, :f, 'pos', :r)
                     ON DUPLICATE KEY UPDATE id = id"
                )->execute([':t' => $brixId, ':u' => $uid, ':c' => $fila['concepto'], ':m' => $fila['monto'],
                            ':f' => $fila['fecha'], ':r' => $fila['referencia']]);
            }
            $pos->prepare("UPDATE sync_brix_cola SET estado='enviado', enviado_en=NOW(), intentos=intentos+1, ultimo_error=NULL WHERE id=:id")
                ->execute([':id' => $fila['id']]);
            $res['enviados']++;
        } catch (Throwable $e) {
            $intentos = (int) $fila['intentos'] + 1;
            $estado   = $intentos >= SYNC_BRIX_MAX_INTENTOS ? 'error' : 'pendiente';
            $pos->prepare("UPDATE sync_brix_cola SET estado=:e, intentos=:i, ultimo_error=:m WHERE id=:id")
                ->execute([':e' => $estado, ':i' => $intentos, ':m' => mb_substr($e->getMessage(), 0, 250), ':id' => $fila['id']]);
            error_log('[SOLTEC sync_brix] mov ' . $fila['id'] . ' (' . $fila['referencia'] . '): ' . $e->getMessage());
            $res['errores']++;
        }
    }
    return $res;
}
