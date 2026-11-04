<?php

class DashboardControlador {

    private function db() { return obtenerConexion(); }

    public function index() {
        $tenantId   = $_SESSION['tenant_id'];
        $db         = $this->db();

        // Filtro por sucursal si es trabajador con sucursal asignada
        $filtroSucursal = (esTrabajador() && !empty($_SESSION['sucursal_id']))
            ? (int) $_SESSION['sucursal_id']
            : null;

        // ── Resumen de inventario ──────────────────────────────
        $sqlInv = "SELECT
                COUNT(*) AS total_productos,
                COALESCE(SUM(cantidad_total), 0) AS unidades_totales,
                COALESCE(SUM(cantidad_disponible), 0) AS unidades_disponibles,
                COALESCE(SUM(cantidad_total - cantidad_disponible), 0) AS unidades_en_uso
             FROM inventario WHERE tenant_id = :tid";
        $paramsInv = [':tid' => $tenantId];

        if ($filtroSucursal) {
            $sqlInv .= " AND sucursal_id = :sid";
            $paramsInv[':sid'] = $filtroSucursal;
        }

        $stmt = $db->prepare($sqlInv);
        $stmt->execute($paramsInv);
        $resumenInventario = $stmt->fetch();

        // ── Próximas recolecciones ─────────────────────────────
        $sqlRec = "SELECT r.*, c.nombre_completo, c.telefono, s.nombre AS sucursal_nombre
             FROM reservas r
             INNER JOIN clientes c ON c.id = r.cliente_id
             INNER JOIN sucursales s ON s.id = r.sucursal_id
             WHERE r.tenant_id = :tid AND r.estado = 'entregado'";
        $paramsRec = [':tid' => $tenantId];

        if ($filtroSucursal) {
            // Tambien las que despacha (reservas vendidas en una vitrina)
            $sqlRec .= " AND (r.sucursal_id = :sid1 OR r.sucursal_despacho_id = :sid2)";
            $paramsRec[':sid1'] = $filtroSucursal;
            $paramsRec[':sid2'] = $filtroSucursal;
        }

        $sqlRec .= " ORDER BY r.fecha_recoleccion_programada ASC LIMIT 5";
        $stmt = $db->prepare($sqlRec);
        $stmt->execute($paramsRec);
        $proximasRecolecciones = $stmt->fetchAll();

        // ── Sucursales activas (solo admin) ────────────────────
        $totalSucursales = null;
        if (esAdmin()) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS total FROM sucursales WHERE tenant_id = :tid AND activa = 1"
            );
            $stmt->execute([':tid' => $tenantId]);
            $totalSucursales = $stmt->fetch()['total'];
        }

        // ── Reservas activas de hoy (trabajador: solo su sucursal) ──
        // "Hoy" lo define PHP con el reloj de Guatemala y se manda
        // como parametro: asi no depende de la zona horaria que
        // tenga configurada MySQL.
        $sqlHoy = "SELECT COUNT(*) AS total FROM reservas
                   WHERE tenant_id = :tid
                   AND estado IN ('reservado','entregado')
                   AND fecha_uso = :hoy";
        $paramsHoy = [':tid' => $tenantId, ':hoy' => hoyGt()];

        if ($filtroSucursal) {
            $sqlHoy .= " AND (sucursal_id = :sid1 OR sucursal_despacho_id = :sid2)";
            $paramsHoy[':sid1'] = $filtroSucursal;
            $paramsHoy[':sid2'] = $filtroSucursal;
        }

        $stmt = $db->prepare($sqlHoy);
        $stmt->execute($paramsHoy);
        $reservasHoy = $stmt->fetch()['total'];

        $tituloPagina = 'Dashboard';
        include RAIZ . '/app/vistas/dashboard/index.php';
    }
}
