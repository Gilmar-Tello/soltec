<?php

// ============================================================
// DISPONIBILIDAD POR FECHAS Y HORAS
//
// Como un hotel: no importa cuántas sillas hay en bodega HOY,
// importa cuántas están comprometidas en el momento que pide
// el cliente.
//
//   cantidad_total       → todas las unidades que tiene el negocio
//   cantidad_disponible  → las que están FÍSICAMENTE en bodega ahora
//                          (baja al entregar, sube al devolver)
//   libres(X..Y)         → cantidad_total − lo comprometido en X..Y
//
// Una reserva ocupa su producto desde su ENTREGA (fecha_entrega +
// hora_entrega) hasta su RECOLECCIÓN (fecha_recoleccion_programada
// + hora_recoleccion):
//   - Si la recolección es a las 14:00, otra reserva puede
//     entregarlo ese mismo día a las 14:00 o después (liberación
//     por hora). Los extremos se tocan sin chocar.
//   - Reservas SIN hora (las anteriores a las horas, o las que no
//     la tengan) se tratan como día completo, igual que antes:
//     entran a las 00:00 y salen a las 23:59:59.
//   - Regla 3: una reserva entregada cuya recolección ya pasó y
//     no ha vuelto sigue ocupando, hasta que se registre la
//     devolución.
//   - Una reserva entregada ANTES de su fecha de entrega ocupa
//     desde que salió de bodega, no desde la fecha programada.
// ============================================================

// Lleva un día ('2026-11-10') o un momento ('2026-11-10 14:00:00')
// a un momento completo. Un día suelto significa el día entero:
// 00:00:00 si es el inicio del rango, 23:59:59 si es el final.
// Devuelve '' si no es válido.
function momentoRango(string $valor, bool $esFin): string {
    $v = fechaOMomentoISO($valor);
    if ($v === '') return '';
    if (strlen($v) === 10) return $v . ($esFin ? ' 23:59:59' : ' 00:00:00');
    return $v;
}

// Momento en que la reserva empieza a ocupar sus productos
function momentoInicioReserva(array $reserva): string {
    $fecha = trim((string) ($reserva['fecha_entrega'] ?? '')) ?: (string) ($reserva['fecha_uso'] ?? '');
    return momentoISO($fecha, $reserva['hora_entrega'] ?? '', '00:00:00');
}

// Momento en que la reserva deja libres sus productos (reservas
// viejas sin recolección: el día siguiente al uso)
function momentoFinReserva(array $reserva): string {
    $rec = substr(trim((string) ($reserva['fecha_recoleccion_programada'] ?? '')), 0, 10);
    if ($rec !== '' && esFechaValida($rec)) {
        return momentoISO($rec, $reserva['hora_recoleccion'] ?? '', '23:59:59');
    }
    $uso = substr(trim((string) ($reserva['fecha_uso'] ?? '')), 0, 10);
    $dia = (new DateTimeImmutable($uso))->modify('+1 day')->format('Y-m-d');
    return $dia . ' 23:59:59';
}

// Unidades libres de cada producto entre $desde y $hasta. Cada uno
// puede ser un día (AAAA-MM-DD) o un momento (AAAA-MM-DD HH:MM:SS).
// $excluirReservaId: al editar una reserva, no contarla contra sí misma.
// Devuelve [inventario_id => unidades libres] (nunca negativo).
function unidadesLibres(array $inventarioIds, string $desde, string $hasta, int $tenantId, int $excluirReservaId = 0): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $inventarioIds))));
    if (!$ids) return [];

    $desdeM = momentoRango($desde, false);
    $hastaM = momentoRango($hasta, true);
    if ($desdeM === '' || $hastaM === '') return [];

    $db = obtenerConexion();

    // Placeholders con nombre único por id: con emulación apagada
    // no se puede repetir un mismo nombre en la consulta.
    $marcas = [];
    $params = [':tid' => $tenantId];
    foreach ($ids as $i => $id) {
        $marcas[] = ':i' . $i;
        $params[':i' . $i] = $id;
    }
    $lista = implode(', ', $marcas);

    $stmt = $db->prepare(
        "SELECT id, cantidad_total FROM inventario WHERE tenant_id = :tid AND id IN ($lista)"
    );
    $stmt->execute($params);
    $totales = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $params2 = $params + [
        ':tid2'    => $tenantId,
        ':excluir' => $excluirReservaId,
        ':hasta'   => $hastaM,
        ':desde'   => $desdeM,
        ':ahora'   => ahoraGt(),
    ];
    $stmt = $db->prepare(
        "SELECT dr.inventario_id, SUM(dr.cantidad) AS comprometidas
         FROM detalle_reserva dr
         INNER JOIN reservas r ON r.id = dr.reserva_id AND r.tenant_id = :tid2
         WHERE dr.tenant_id = :tid
           AND dr.inventario_id IN ($lista)
           AND r.estado IN ('reservado', 'entregado')
           AND r.id <> :excluir
           -- empieza a ocupar en su entrega; si ya se entregó
           -- (aunque haya sido antes), ya está afuera
           AND (TIMESTAMP(COALESCE(r.fecha_entrega, r.fecha_uso), COALESCE(r.hora_entrega, '00:00:00')) < :hasta
                OR r.estado = 'entregado')
           AND (
                -- se cruza con el rango pedido
                TIMESTAMP(COALESCE(r.fecha_recoleccion_programada, DATE_ADD(r.fecha_uso, INTERVAL 1 DAY)),
                          COALESCE(r.hora_recoleccion, '23:59:59')) > :desde
                -- o ya debía volver y sigue afuera (regla 3)
             OR (r.estado = 'entregado'
                 AND TIMESTAMP(COALESCE(r.fecha_recoleccion_programada, DATE_ADD(r.fecha_uso, INTERVAL 1 DAY)),
                               COALESCE(r.hora_recoleccion, '23:59:59')) < :ahora)
           )
         GROUP BY dr.inventario_id"
    );
    $stmt->execute($params2);
    $comprometidas = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $libres = [];
    foreach ($ids as $id) {
        if (!isset($totales[$id])) continue;
        $libres[$id] = max(0, (int) $totales[$id] - (int) ($comprometidas[$id] ?? 0));
    }
    return $libres;
}

// Rango válido para consultar disponibilidad (los dos extremos
// existen y el fin no es antes del inicio). Acepta días o momentos.
function rangoFechasValido(string $desde, string $hasta): bool {
    $d = momentoRango($desde, false);
    $h = momentoRango($hasta, true);
    return $d !== '' && $h !== '' && $d <= $h;
}

// ── Sacar unidades del negocio (bajas y transferencias) ──────
// Cuántas unidades de un producto se pueden retirar sin dejar sin
// producto a ninguna reserva. Es el mínimo entre:
//   - lo que está en bodega (no se puede sacar lo que no está), y
//   - lo libre en el día más apretado de hoy en adelante.
// Lo comprometido solo SUBE el día que empieza una reserva (su
// entrega), así que el día más apretado siempre es hoy o una fecha
// de entrega futura: basta con revisar esos días, no todo el
// calendario. Se revisa el día completo (conservador).
// Devuelve ['cantidad' => N, 'en_bodega' => B, 'fecha' => día que limita o null].
function unidadesRetirables(int $inventarioId, int $tenantId): array {
    $db  = obtenerConexion();
    $hoy = hoyGt();

    $stmt = $db->prepare("SELECT cantidad_disponible FROM inventario WHERE id = :id AND tenant_id = :tid");
    $stmt->execute([':id' => $inventarioId, ':tid' => $tenantId]);
    $enBodega = (int) $stmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT DISTINCT COALESCE(r.fecha_entrega, r.fecha_uso)
         FROM detalle_reserva dr
         INNER JOIN reservas r ON r.id = dr.reserva_id AND r.tenant_id = :tid2
         WHERE dr.tenant_id = :tid AND dr.inventario_id = :id
           AND r.estado = 'reservado' AND COALESCE(r.fecha_entrega, r.fecha_uso) > :hoy"
    );
    $stmt->execute([':tid2' => $tenantId, ':tid' => $tenantId, ':id' => $inventarioId, ':hoy' => $hoy]);
    $dias = array_merge([$hoy], $stmt->fetchAll(PDO::FETCH_COLUMN));

    $minLibres = PHP_INT_MAX;
    $diaQueLimita = null;
    foreach ($dias as $dia) {
        $libres = unidadesLibres([$inventarioId], $dia, $dia, $tenantId)[$inventarioId] ?? 0;
        if ($libres < $minLibres) { $minLibres = $libres; $diaQueLimita = $dia; }
    }

    $cantidad = max(0, min($enBodega, $minLibres));
    return [
        'cantidad'  => $cantidad,
        'en_bodega' => $enBodega,
        // Solo se informa la fecha si son las reservas (y no la bodega) las que limitan
        'fecha'     => $minLibres < $enBodega ? $diaQueLimita : null,
    ];
}

// Mensaje para el usuario cuando quiere sacar más de lo permitido
function mensajeNoRetirable(string $nombre, int $pedido, array $r): string {
    $msg = "No se pueden sacar {$pedido} de \"{$nombre}\": como máximo {$r['cantidad']}";
    return $r['fecha']
        ? $msg . ', porque el resto está comprometido para reservas del ' . fechaCorta($r['fecha']) . '.'
        : $msg . " (es lo que hay en bodega).";
}
