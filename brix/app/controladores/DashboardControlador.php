<?php

class DashboardControlador {

    private function db() { return obtenerConexion(); }

    public function index() {
        $db  = $this->db();
        $tid = tenantActual();

        // ── Ingresos / egresos de hoy y del mes ────────────────
        $ingHoy = $db->prepare("SELECT COALESCE(SUM(monto),0) FROM ingresos WHERE tenant_id=:t AND anulado_en IS NULL AND DATE(creado_en)=CURDATE()");
        $ingHoy->execute([':t'=>$tid]);
        $ingresosHoy = (float) $ingHoy->fetchColumn();

        $egrHoy = $db->prepare("SELECT COALESCE(SUM(monto),0) FROM egresos WHERE tenant_id=:t AND anulado_en IS NULL AND DATE(creado_en)=CURDATE()");
        $egrHoy->execute([':t'=>$tid]);
        $egresosHoy = (float) $egrHoy->fetchColumn();

        $ingMes = $db->prepare("SELECT COALESCE(SUM(monto),0) FROM ingresos WHERE tenant_id=:t AND anulado_en IS NULL AND YEAR(creado_en)=YEAR(CURDATE()) AND MONTH(creado_en)=MONTH(CURDATE())");
        $ingMes->execute([':t'=>$tid]);
        $ingresosMes = (float) $ingMes->fetchColumn();

        $egrMes = $db->prepare("SELECT COALESCE(SUM(monto),0) FROM egresos WHERE tenant_id=:t AND anulado_en IS NULL AND YEAR(creado_en)=YEAR(CURDATE()) AND MONTH(creado_en)=MONTH(CURDATE())");
        $egrMes->execute([':t'=>$tid]);
        $egresosMes = (float) $egrMes->fetchColumn();

        // ── Inventario ─────────────────────────────────────────
        $inv = $db->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(COALESCE(s.cantidad,0) * p.precio_compra),0) AS valor
             FROM productos p
             LEFT JOIN stock s ON s.producto_id = p.id AND s.tenant_id = :t1
             WHERE p.tenant_id = :t2 AND p.activo = 1"
        );
        $inv->execute([':t1'=>$tid, ':t2'=>$tid]);
        $invRow = $inv->fetch();
        $totalProductos  = (int) $invRow['total'];
        $valorInventario = (float) $invRow['valor'];

        $bajo = $db->prepare(
            "SELECT COUNT(*) FROM productos p
             LEFT JOIN stock s ON s.producto_id = p.id AND s.tenant_id = :t1
             WHERE p.tenant_id = :t2 AND p.activo = 1 AND p.es_compuesto = 0 AND COALESCE(s.cantidad,0) <= p.stock_minimo"
        );
        $bajo->execute([':t1'=>$tid, ':t2'=>$tid]);
        $stockBajo = (int) $bajo->fetchColumn();

        $venc = $db->prepare(
            "SELECT COUNT(*) FROM productos
             WHERE tenant_id = :t AND activo = 1 AND fecha_vencimiento IS NOT NULL
               AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
        );
        $venc->execute([':t'=>$tid]);
        $porVencer = (int) $venc->fetchColumn();

        // ── Último cuadre ──────────────────────────────────────
        $cua = $db->prepare("SELECT porcentaje_perdida, creado_en FROM cuadres WHERE tenant_id=:t ORDER BY id DESC LIMIT 1");
        $cua->execute([':t'=>$tid]);
        $ultimoCuadre = $cua->fetch();

        // ── Productos próximos a vencer (lista) ────────────────
        $listaVenc = $db->prepare(
            "SELECT nombre, fecha_vencimiento
             FROM productos
             WHERE tenant_id = :t AND activo = 1 AND fecha_vencimiento IS NOT NULL
               AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY fecha_vencimiento ASC LIMIT 8"
        );
        $listaVenc->execute([':t'=>$tid]);
        $proximosVencer = $listaVenc->fetchAll();

        $balanceHoy = $ingresosHoy - $egresosHoy;
        $balanceMes = $ingresosMes - $egresosMes;

        $tituloPagina = 'Dashboard';
        include RAIZ . '/app/vistas/dashboard/index.php';
    }
}
