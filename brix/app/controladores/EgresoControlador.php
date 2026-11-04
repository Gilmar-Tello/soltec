<?php

class EgresoControlador {

    private function db() { return obtenerConexion(); }
    private function tid(): int { return tenantActual(); }

    public function index() {
        $db  = $this->db();
        $tid = $this->tid();

        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');

        $stmt = $db->prepare(
            "SELECT e.*, u.nombre AS usuario_nombre
             FROM egresos e
             LEFT JOIN usuarios u ON u.id = e.usuario_id
             WHERE e.tenant_id = :t AND e.anulado_en IS NULL AND DATE(e.creado_en) BETWEEN :d AND :h
             ORDER BY e.creado_en DESC"
        );
        $stmt->execute([':t'=>$tid, ':d'=>$desde, ':h'=>$hasta]);
        $egresos = $stmt->fetchAll();

        $total = 0;
        foreach ($egresos as $e) { $total += (float)$e['monto']; }

        $tituloPagina = 'Egresos';
        include RAIZ . '/app/vistas/egresos/index.php';
    }

    public function nuevo() {
        $tituloPagina = 'Nuevo egreso';
        include RAIZ . '/app/vistas/egresos/nuevo.php';
    }

    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=egresos/index'); exit;
        }

        $db  = $this->db();
        $tid = $this->tid();

        $concepto = trim($_POST['concepto'] ?? '');
        $items    = $_POST['items'] ?? [];

        // Normaliza y valida las líneas (descripción, cantidad, precio unitario)
        $lineas = [];
        foreach ($items as $it) {
            $desc   = trim($it['descripcion'] ?? '');
            $cant   = round((float) ($it['cantidad'] ?? 0), 2);
            $precio = round((float) ($it['precio'] ?? 0), 2);
            if ($desc === '' || $cant <= 0) continue;
            if ($precio < 0) $precio = 0;
            $lineas[] = ['desc'=>$desc, 'cant'=>$cant, 'precio'=>$precio, 'sub'=>round($cant * $precio, 2)];
        }

        if (empty($lineas)) {
            $_SESSION['error'] = 'Agregá al menos una línea con descripción y cantidad.';
            header('Location: ' . URL_BASE . '/?url=egresos/nuevo'); exit;
        }

        $total = 0;
        foreach ($lineas as $l) { $total += $l['sub']; }

        $db->beginTransaction();
        try {
            $db->prepare(
                "INSERT INTO egresos (tenant_id, usuario_id, concepto, descripcion, monto)
                 VALUES (:t, :u, :c, :d, :m)"
            )->execute([
                ':t'=>$tid, ':u'=>$_SESSION['usuario_id'],
                ':c'=>($concepto !== '' ? $concepto : 'Egreso'), ':d'=>null, ':m'=>$total,
            ]);
            $egresoId = $db->lastInsertId();

            $ins = $db->prepare(
                "INSERT INTO egreso_detalle (tenant_id, egreso_id, descripcion, cantidad, precio_unitario, subtotal)
                 VALUES (:t, :e, :d, :cant, :precio, :sub)"
            );
            foreach ($lineas as $l) {
                $ins->execute([
                    ':t'=>$tid, ':e'=>$egresoId, ':d'=>$l['desc'],
                    ':cant'=>$l['cant'], ':precio'=>$l['precio'], ':sub'=>$l['sub'],
                ]);
            }

            $db->commit();
            $_SESSION['exito'] = 'Egreso registrado. Total: Q' . number_format($total, 2);
            header('Location: ' . URL_BASE . '/?url=egresos/index'); exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al registrar el egreso. Intenta de nuevo.');
            header('Location: ' . URL_BASE . '/?url=egresos/nuevo'); exit;
        }
    }
}
