<?php

class CotizacionesControlador {

    private function db() { return obtenerConexion(); }

    // ── Historial de cotizaciones ──────────────────────────────
    public function index() {
        requerirPermiso('pos');
        $tid    = tenantActual();
        $desde  = fechaGet('desde', date('Y-m-01'));
        $hasta  = fechaGet('hasta', date('Y-m-d'));
        $estado = $_GET['estado'] ?? '';
        $esAdmin = $_SESSION['usuario_rol_id'] == 1;

        $sql = "SELECT c.*, cl.nombre AS cliente_nombre, u.nombre AS usuario_nombre
                FROM cotizaciones c
                LEFT JOIN clientes cl ON cl.id = c.cliente_id
                JOIN usuarios u ON u.id = c.usuario_id
                WHERE c.tenant_id=:tid AND DATE(c.creado_en) BETWEEN :desde AND :hasta";
        $params = [':tid'=>$tid, ':desde'=>$desde, ':hasta'=>$hasta];

        if (!$esAdmin) {
            $sql .= " AND c.usuario_id = :uid";
            $params[':uid'] = $_SESSION['usuario_id'];
        }
        if ($estado && in_array($estado, ['abierta','convertida','anulada'])) {
            $sql .= " AND c.estado = :estado";
            $params[':estado'] = $estado;
        }
        $sql .= sqlSucursal('c');   // las cotizaciones de la sucursal que se esta viendo
        $sql .= " ORDER BY c.creado_en DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $cotizaciones = $stmt->fetchAll();

        $tituloPagina = $esAdmin ? 'Cotizaciones' : 'Mis Cotizaciones';
        include RAIZ . '/app/vistas/cotizaciones/lista.php';
    }

    // ── Ver detalle de una cotización ──────────────────────────
    public function ver($id) {
        requerirPermiso('pos');
        $tid = tenantActual();
        $stmt = $this->db()->prepare(
            "SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono,
                    u.nombre AS usuario_nombre
             FROM cotizaciones c
             LEFT JOIN clientes cl ON cl.id = c.cliente_id
             JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.id=:id AND c.tenant_id=:tid");
        $stmt->execute([':id'=>$id, ':tid'=>$tid]);
        $cotizacion = $stmt->fetch();
        if (!$cotizacion || !$this->cotizacionEnVista($cotizacion)) { header('Location: ' . URL_BASE . '/?url=cotizaciones/index'); exit; }

        $esAdmin = $_SESSION['usuario_rol_id'] == 1;
        if (!$esAdmin && $cotizacion['usuario_id'] != $_SESSION['usuario_id']) {
            $_SESSION['error'] = 'No tenés permiso para ver esta cotización.';
            header('Location: ' . URL_BASE . '/?url=cotizaciones/index'); exit;
        }

        // El precio actual se consulta en vivo — nunca se guarda, para que siempre esté al día
        $stmt2 = $this->db()->prepare(
            "SELECT dc.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                    p.precio_venta_real AS precio_actual, u.abreviatura AS unidad
             FROM detalle_cotizacion dc
             JOIN productos p ON p.id = dc.producto_id
             JOIN unidades_medida u ON u.id = p.unidad_id
             WHERE dc.cotizacion_id=:id AND dc.tenant_id=:tid");
        $stmt2->execute([':id'=>$id, ':tid'=>$tid]);
        $detalle = $stmt2->fetchAll();

        // Si ya se convirtió, buscamos la venta resultante para enlazarla
        $ventaResultante = null;
        if ($cotizacion['estado'] === 'convertida') {
            $stmt3 = $this->db()->prepare("SELECT id FROM ventas WHERE cotizacion_id=:id AND tenant_id=:tid LIMIT 1");
            $stmt3->execute([':id'=>$id, ':tid'=>$tid]);
            $ventaResultante = $stmt3->fetchColumn() ?: null;
        }

        $tituloPagina = 'Cotización #' . $id;
        include RAIZ . '/app/vistas/cotizaciones/ver.php';
    }

    // ── Vista imprimible de la cotización (formato ticket 80mm) ──
    public function imprimir($id) {
        requerirPermiso('pos');
        $tid = tenantActual();
        $stmt = $this->db()->prepare(
            "SELECT c.*, cl.nombre AS cliente_nombre, cl.telefono AS cliente_telefono,
                    u.nombre AS usuario_nombre
             FROM cotizaciones c
             LEFT JOIN clientes cl ON cl.id = c.cliente_id
             JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.id=:id AND c.tenant_id=:tid");
        $stmt->execute([':id'=>$id, ':tid'=>$tid]);
        $cotizacion = $stmt->fetch();
        if (!$cotizacion || !$this->cotizacionEnVista($cotizacion)) { header('Location: ' . URL_BASE . '/?url=cotizaciones/index'); exit; }

        $esAdmin = $_SESSION['usuario_rol_id'] == 1;
        if (!$esAdmin && $cotizacion['usuario_id'] != $_SESSION['usuario_id']) {
            $_SESSION['error'] = 'No tenés permiso para ver esta cotización.';
            header('Location: ' . URL_BASE . '/?url=cotizaciones/index'); exit;
        }

        $stmt2 = $this->db()->prepare(
            "SELECT dc.*, p.nombre AS producto_nombre, u.abreviatura AS unidad
             FROM detalle_cotizacion dc
             JOIN productos p ON p.id = dc.producto_id
             JOIN unidades_medida u ON u.id = p.unidad_id
             WHERE dc.cotizacion_id=:id AND dc.tenant_id=:tid");
        $stmt2->execute([':id'=>$id, ':tid'=>$tid]);
        $detalle = $stmt2->fetchAll();

        $tituloPagina = 'Cotización #' . $id;
        include RAIZ . '/app/vistas/cotizaciones/imprimir.php';
    }

    // ── Eliminar (anular) una cotización abierta ───────────────
    public function eliminar($id) {
        requerirPermiso('pos');
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT estado, usuario_id FROM cotizaciones WHERE id=:id AND tenant_id=:tid" . sqlSucursal());
        $stmt->execute([':id'=>$id, ':tid'=>$tid]);
        $cot = $stmt->fetch();
        if (!$cot) {
            $_SESSION['error'] = 'Cotización no encontrada.';
            header('Location: ' . URL_BASE . '/?url=cotizaciones/index'); exit;
        }
        $esAdmin = $_SESSION['usuario_rol_id'] == 1;
        if (!$esAdmin && $cot['usuario_id'] != $_SESSION['usuario_id']) {
            $_SESSION['error'] = 'No tenés permiso para eliminar esta cotización.';
            header('Location: ' . URL_BASE . '/?url=cotizaciones/index'); exit;
        }
        if ($cot['estado'] !== 'abierta') {
            $_SESSION['error'] = 'Solo se pueden eliminar cotizaciones abiertas.';
            header('Location: ' . URL_BASE . '/?url=cotizaciones/ver/' . $id); exit;
        }

        $this->db()->prepare("UPDATE cotizaciones SET estado='anulada' WHERE id=:id AND tenant_id=:tid")
             ->execute([':id'=>$id, ':tid'=>$tid]);

        $_SESSION['exito'] = 'Cotización eliminada.';
        header('Location: ' . URL_BASE . '/?url=cotizaciones/index'); exit;
    }

    // Una cotizacion pertenece a la sucursal donde se hizo; solo se ve desde esa sucursal.
    private function cotizacionEnVista(array $cot): bool {
        return !catalogoPorSucursal() || (int) ($cot['sucursal_id'] ?? 0) === sucursalTrabajo();
    }
}
