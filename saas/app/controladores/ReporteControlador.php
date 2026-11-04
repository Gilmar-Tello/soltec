<?php

class ReporteControlador {

    private function db() { return obtenerConexion(); }

    private function tenantId(): int {
        return (int) $_SESSION['tenant_id'];
    }

    // ── Dashboard de reportes ──────────────────────────────────
    public function index() {
        $db  = $this->db();
        $tid = $this->tenantId();

        // Fechas de filtro (por defecto: mes actual)
        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-t');

        // ── 1. Reservas por período ────────────────────────────
        $stmt = $db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN estado='reservado'  THEN 1 ELSE 0 END) AS reservadas,
                SUM(CASE WHEN estado='entregado'  THEN 1 ELSE 0 END) AS entregadas,
                SUM(CASE WHEN estado='devuelto'   THEN 1 ELSE 0 END) AS devueltas,
                SUM(CASE WHEN estado='cancelado'  THEN 1 ELSE 0 END) AS canceladas,
                COALESCE(SUM(CASE WHEN estado != 'cancelado' THEN total ELSE 0 END), 0) AS ingresos_total
             FROM reservas
             WHERE tenant_id = :tid
               AND DATE(fecha_reservacion) BETWEEN :desde AND :hasta"
        );
        $stmt->execute([':tid' => $tid, ':desde' => $desde, ':hasta' => $hasta]);
        $resumenReservas = $stmt->fetch();

        // ── 2. Productos más alquilados ────────────────────────
        $stmt = $db->prepare(
            "SELECT i.nombre, SUM(dr.cantidad) AS veces_alquilado,
                    SUM(dr.subtotal) AS ingresos_generados
             FROM detalle_reserva dr
             INNER JOIN inventario i ON i.id = dr.inventario_id
             INNER JOIN reservas r ON r.id = dr.reserva_id
             WHERE dr.tenant_id = :tid
               AND r.estado != 'cancelado'
               AND DATE(r.fecha_reservacion) BETWEEN :desde AND :hasta
             GROUP BY dr.inventario_id, i.nombre
             ORDER BY veces_alquilado DESC
             LIMIT 10"
        );
        $stmt->execute([':tid' => $tid, ':desde' => $desde, ':hasta' => $hasta]);
        $productosTop = $stmt->fetchAll();

        // ── 3. Ingresos por sucursal ───────────────────────────
        $stmt = $db->prepare(
            "SELECT s.nombre AS sucursal,
                    COUNT(r.id) AS total_reservas,
                    COALESCE(SUM(CASE WHEN r.estado != 'cancelado' THEN r.total ELSE 0 END), 0) AS ingresos
             FROM reservas r
             INNER JOIN sucursales s ON s.id = r.sucursal_id
             WHERE r.tenant_id = :tid
               AND DATE(r.fecha_reservacion) BETWEEN :desde AND :hasta
             GROUP BY r.sucursal_id, s.nombre
             ORDER BY ingresos DESC"
        );
        $stmt->execute([':tid' => $tid, ':desde' => $desde, ':hasta' => $hasta]);
        $ingresosSucursal = $stmt->fetchAll();

        // ── 4. Clientes frecuentes ─────────────────────────────
        $stmt = $db->prepare(
            "SELECT c.nombre_completo, c.telefono,
                    COUNT(r.id) AS total_reservas,
                    COALESCE(SUM(CASE WHEN r.estado != 'cancelado' THEN r.total ELSE 0 END), 0) AS total_gastado
             FROM reservas r
             INNER JOIN clientes c ON c.id = r.cliente_id
             WHERE r.tenant_id = :tid
               AND DATE(r.fecha_reservacion) BETWEEN :desde AND :hasta
             GROUP BY r.cliente_id, c.nombre_completo, c.telefono
             ORDER BY total_reservas DESC
             LIMIT 10"
        );
        $stmt->execute([':tid' => $tid, ':desde' => $desde, ':hasta' => $hasta]);
        $clientesFrecuentes = $stmt->fetchAll();

        // ── 5. Inventario por sucursal ─────────────────────────
        $stmt = $db->prepare(
            "SELECT s.nombre AS sucursal,
                    COUNT(i.id) AS total_productos,
                    SUM(i.cantidad_total) AS unidades_totales,
                    SUM(i.cantidad_disponible) AS unidades_disponibles,
                    SUM(i.cantidad_total - i.cantidad_disponible) AS unidades_en_uso
             FROM inventario i
             INNER JOIN sucursales s ON s.id = i.sucursal_id
             WHERE i.tenant_id = :tid
             GROUP BY i.sucursal_id, s.nombre
             ORDER BY s.nombre ASC"
        );
        $stmt->execute([':tid' => $tid]);
        $inventarioSucursal = $stmt->fetchAll();

        // ── 6. Historial reciente de alquileres ────────────────
        $stmt = $db->prepare(
            "SELECT r.id, r.fecha_reservacion, r.fecha_uso, r.estado, r.total,
                    c.nombre_completo, s.nombre AS sucursal_nombre
             FROM reservas r
             INNER JOIN clientes c ON c.id = r.cliente_id
             INNER JOIN sucursales s ON s.id = r.sucursal_id
             WHERE r.tenant_id = :tid
               AND DATE(r.fecha_reservacion) BETWEEN :desde AND :hasta
             ORDER BY r.fecha_reservacion DESC
             LIMIT 20"
        );
        $stmt->execute([':tid' => $tid, ':desde' => $desde, ':hasta' => $hasta]);
        $historial = $stmt->fetchAll();

        $tituloPagina = 'Reportes';
        include RAIZ . '/app/vistas/reportes/index.php';
    }
}
