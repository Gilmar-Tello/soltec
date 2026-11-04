<?php

class InformesControlador {

    private function db() { return obtenerConexion(); }

    // ── Sucursal elegida en el filtro: null = todas, '0' = Principal, 'N' = id de sucursal ──
    // Solo existe si el negocio tiene la funcion de sucursales; cualquier otro valor se ignora.
    private function sucursalElegida(): ?string {
        if (!sucursalesHabilitadas()) return null;
        // Sin filtro explicito (primera vez que entra) se muestra la sucursal que se esta administrando; para ver
        // el negocio completo se elige "Todas" en el filtro, que envia sucursal= vacio.
        if (!isset($_GET['sucursal'])) return (string) sucursalTrabajo();
        $s = $_GET['sucursal'];
        if (!is_string($s) || $s === '') return null;
        if ($s === '0') return '0';
        $id = (int) $s;
        return isset(sucursalesDelNegocio()[$id]) ? (string) $id : null;
    }

    // Trozo de SQL para limitar las ventas a las cajas de una sucursal ('' si no hay filtro)
    private function condicionSucursal(?string $sel, string $columna, int $tid, array &$params): string {
        if ($sel === null) return '';
        $params[':suc_t'] = $tid;
        if ($sel === '0') {
            return " AND $columna IN (SELECT id FROM caja_sesiones WHERE tenant_id=:suc_t AND sucursal_id IS NULL)";
        }
        $params[':suc_id'] = (int) $sel;
        return " AND $columna IN (SELECT id FROM caja_sesiones WHERE tenant_id=:suc_t AND sucursal_id=:suc_id)";
    }

    // Todos los datos del informe (los usan la pantalla y el PDF)
    private function datosInforme(int $tid, string $desde, string $hasta, ?string $sel): array {
        $base = [':tid' => $tid, ':desde' => $desde, ':hasta' => $hasta];

        $p = $base; $c = $this->condicionSucursal($sel, 'caja_sesion_id', $tid, $p);
        $stmt = $this->db()->prepare(
            "SELECT DATE(creado_en) AS dia, COUNT(*) AS total_ventas, SUM(total) AS monto_total
             FROM ventas
             WHERE tenant_id=:tid AND anulada=0 AND DATE(creado_en) BETWEEN :desde AND :hasta $c
             GROUP BY DATE(creado_en)
             ORDER BY dia ASC");
        $stmt->execute($p);
        $ventasPorDia = $stmt->fetchAll();

        $p = $base; $c = $this->condicionSucursal($sel, 'v.caja_sesion_id', $tid, $p);
        $stmt = $this->db()->prepare(
            "SELECT p.nombre, SUM(d.cantidad) AS total_cantidad,
                    SUM(d.subtotal) AS total_monto,
                    SUM(d.cantidad * " . sqlCostoLinea() . ") AS total_costo,
                    SUM(d.subtotal) - SUM(d.cantidad * " . sqlCostoLinea() . ") AS ganancia
             FROM detalle_venta d
             JOIN ventas v ON v.id = d.venta_id
             JOIN productos p ON p.id = d.producto_id
             WHERE v.tenant_id=:tid AND v.anulada=0 AND DATE(v.creado_en) BETWEEN :desde AND :hasta $c
             GROUP BY d.producto_id
             ORDER BY total_cantidad DESC
             LIMIT 10");
        $stmt->execute($p);
        $topProductos = $stmt->fetchAll();

        $p = $base; $c = $this->condicionSucursal($sel, 'caja_sesion_id', $tid, $p);
        $stmt = $this->db()->prepare(
            "SELECT tipo_pago, COUNT(*) AS total_ventas, SUM(total) AS monto_total
             FROM ventas
             WHERE tenant_id=:tid AND anulada=0 AND DATE(creado_en) BETWEEN :desde AND :hasta $c
             GROUP BY tipo_pago");
        $stmt->execute($p);
        $porTipoPago = $stmt->fetchAll();

        $p = $base; $c = $this->condicionSucursal($sel, 'caja_sesion_id', $tid, $p);
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) AS total_ventas,
                    COALESCE(SUM(total), 0) AS monto_total,
                    COALESCE(AVG(total), 0) AS ticket_promedio,
                    COALESCE(SUM(CASE WHEN estado_pago='pendiente' THEN saldo_pendiente ELSE 0 END), 0) AS credito_pendiente
             FROM ventas
             WHERE tenant_id=:tid AND anulada=0 AND DATE(creado_en) BETWEEN :desde AND :hasta $c");
        $stmt->execute($p);
        $resumen = $stmt->fetch();

        $p = $base; $c = $this->condicionSucursal($sel, 'v.caja_sesion_id', $tid, $p);
        $stmt = $this->db()->prepare(
            "SELECT COALESCE(SUM(d.cantidad * " . sqlCostoLinea() . "),0) AS costo_total
             FROM detalle_venta d
             JOIN ventas v ON v.id = d.venta_id
             JOIN productos p ON p.id = d.producto_id
             WHERE v.tenant_id=:tid AND v.anulada=0 AND DATE(v.creado_en) BETWEEN :desde AND :hasta $c");
        $stmt->execute($p);
        $costoTotal = floatval($stmt->fetchColumn());

        $resumen['costo_total']      = $costoTotal;
        $resumen['ganancia_liquida'] = floatval($resumen['monto_total']) - $costoTotal;
        $resumen['margen_pct']       = $resumen['monto_total'] > 0
            ? ($resumen['ganancia_liquida'] / floatval($resumen['monto_total'])) * 100
            : 0;

        return compact('ventasPorDia', 'topProductos', 'porTipoPago', 'resumen');
    }

    // ── Ranking: que sucursal vende mas (todas, sin importar el filtro) ──
    private function rankingSucursales(int $tid, string $desde, string $hasta): array {
        $nombres = nombresSucursales();   // 0 = Principal

        $st = $this->db()->prepare(
            "SELECT COALESCE(cs.sucursal_id,0) AS sid, COUNT(v.id) AS total_ventas, COALESCE(SUM(v.total),0) AS monto_total
             FROM ventas v
             JOIN caja_sesiones cs ON cs.id = v.caja_sesion_id AND cs.tenant_id = :t1
             WHERE v.tenant_id=:t2 AND v.anulada=0 AND DATE(v.creado_en) BETWEEN :desde AND :hasta
             GROUP BY COALESCE(cs.sucursal_id,0)");
        $st->execute([':t1' => $tid, ':t2' => $tid, ':desde' => $desde, ':hasta' => $hasta]);
        $ventas = [];
        foreach ($st->fetchAll() as $f) $ventas[(int) $f['sid']] = $f;

        $st = $this->db()->prepare(
            "SELECT COALESCE(cs.sucursal_id,0) AS sid, COALESCE(SUM(d.cantidad * " . sqlCostoLinea() . "),0) AS costo
             FROM detalle_venta d
             JOIN ventas v ON v.id = d.venta_id
             JOIN caja_sesiones cs ON cs.id = v.caja_sesion_id AND cs.tenant_id = :t1
             JOIN productos p ON p.id = d.producto_id
             WHERE v.tenant_id=:t2 AND v.anulada=0 AND DATE(v.creado_en) BETWEEN :desde AND :hasta
             GROUP BY COALESCE(cs.sucursal_id,0)");
        $st->execute([':t1' => $tid, ':t2' => $tid, ':desde' => $desde, ':hasta' => $hasta]);
        $costos = [];
        foreach ($st->fetchAll() as $f) $costos[(int) $f['sid']] = (float) $f['costo'];

        // Incluye a las activas aunque no hayan vendido, y a cualquiera que tenga ventas en el periodo
        $ids = array_unique(array_merge([0], array_keys(sucursalesDelNegocio()), array_keys($ventas)));
        $totalGeneral = 0.0;
        foreach ($ids as $sid) $totalGeneral += (float) ($ventas[$sid]['monto_total'] ?? 0);

        $ranking = [];
        foreach ($ids as $sid) {
            $monto = (float) ($ventas[$sid]['monto_total'] ?? 0);
            $n     = (int) ($ventas[$sid]['total_ventas'] ?? 0);
            $ranking[] = [
                'id'       => $sid,
                'nombre'   => $nombres[$sid] ?? ('Sucursal #' . $sid),
                'ventas'   => $n,
                'monto'    => $monto,
                'ticket'   => $n > 0 ? $monto / $n : 0,
                'ganancia' => $monto - (float) ($costos[$sid] ?? 0),
                'pct'      => $totalGeneral > 0 ? ($monto / $totalGeneral) * 100 : 0,
            ];
        }
        usort($ranking, fn($a, $b) => $b['monto'] <=> $a['monto']);
        return $ranking;
    }

    // ── PDF de informes ───────────────────────────────────────
    public function pdf() {
        $tid = tenantActual();
        $desde = fechaGet('desde', date('Y-m-01'));
        $hasta = fechaGet('hasta', date('Y-m-d'));
        $sucursalSel    = $this->sucursalElegida();
        $sucursalNombre = $sucursalSel === null ? null : (nombresSucursales()[(int) $sucursalSel] ?? null);

        $d = $this->datosInforme($tid, $desde, $hasta, $sucursalSel);
        $ventasPorDia = $d['ventasPorDia']; $topProductos = $d['topProductos'];
        $porTipoPago  = $d['porTipoPago'];  $resumen      = $d['resumen'];

        $tituloPagina = 'Informe de Ventas';
        ob_end_clean();
        include RAIZ . '/app/vistas/informes/pdf.php';
        exit;
    }

    // ── Dashboard de informes ─────────────────────────────────
    public function index() {
        $tid = tenantActual();
        $desde = fechaGet('desde', date('Y-m-01'));
        $hasta = fechaGet('hasta', date('Y-m-d'));
        $sucursalSel    = $this->sucursalElegida();
        $sucursalNombre = $sucursalSel === null ? null : (nombresSucursales()[(int) $sucursalSel] ?? null);

        $d = $this->datosInforme($tid, $desde, $hasta, $sucursalSel);
        $ventasPorDia = $d['ventasPorDia']; $topProductos = $d['topProductos'];
        $porTipoPago  = $d['porTipoPago'];  $resumen      = $d['resumen'];

        $hayRanking = sucursalesHabilitadas();
        $ranking    = $hayRanking ? $this->rankingSucursales($tid, $desde, $hasta) : [];

        $tituloPagina = 'Informes';
        include RAIZ . '/app/vistas/informes/index.php';
    }
}
