<?php

class BajaControlador {

    private function db() { return obtenerConexion(); }

    private function tenantId(): int {
        return (int) $_SESSION['tenant_id'];
    }

    // ── Listado de bajas ───────────────────────────────────────
    public function index() {
        $stmt = $this->db()->prepare(
            "SELECT b.*, i.nombre AS producto_nombre, s.nombre AS sucursal_nombre,
                    u.nombre AS usuario_nombre
             FROM bajas_inventario b
             INNER JOIN inventario i ON i.id = b.inventario_id
             INNER JOIN sucursales s ON s.id = i.sucursal_id
             INNER JOIN usuarios u ON u.id = b.usuario_id
             WHERE b.tenant_id = :tid
             ORDER BY b.fecha_baja DESC"
        );
        $stmt->execute([':tid' => $this->tenantId()]);
        $bajas = $stmt->fetchAll();

        $tituloPagina = 'Gestión de Bajas';
        include RAIZ . '/app/vistas/bajas/index.php';
    }

    // ── Formulario nueva baja ──────────────────────────────────
    public function nueva() {
        $sucursales = $this->obtenerSucursales();

        if (empty($sucursales)) {
            $_SESSION['error'] = 'No hay sucursales registradas.';
            header('Location: ' . URL_BASE . '/?url=bajas/index'); exit;
        }

        $tituloPagina = 'Nueva Baja';
        include RAIZ . '/app/vistas/bajas/form.php';
    }

    // ── Inventario por sucursal vía AJAX ───────────────────────
    public function inventarioajax() {
        header('Content-Type: application/json');
        $sucursalId = intval($_GET['sucursal_id'] ?? 0);

        if (!$sucursalId) { echo json_encode([]); exit; }

        $stmt = $this->db()->prepare(
            "SELECT id, nombre, cantidad_total, cantidad_disponible
             FROM inventario
             WHERE tenant_id = :tid AND sucursal_id = :sid AND cantidad_total > 0
             ORDER BY nombre ASC"
        );
        $stmt->execute([':tid' => $this->tenantId(), ':sid' => $sucursalId]);

        // 'retirables' = lo que de verdad se puede dar de baja: en bodega
        // y sin comprometer para reservas futuras (misma regla que al guardar)
        $productos = $stmt->fetchAll();
        foreach ($productos as &$p) {
            $r = unidadesRetirables((int) $p['id'], $this->tenantId());
            $p['retirables']   = $r['cantidad'];
            $p['limita_fecha'] = $r['fecha'] ? fechaCorta($r['fecha']) : '';
        }
        unset($p);

        echo json_encode($productos);
        exit;
    }

    // ── Guardar baja ───────────────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=bajas/index'); exit;
        }

        $inventarioId = intval($_POST['inventario_id'] ?? 0);
        $cantidad     = intval($_POST['cantidad'] ?? 0);
        $motivo       = trim($_POST['motivo'] ?? '');
        $fechaBaja    = trim($_POST['fecha_baja'] ?? '') ?: hoyGt();

        if (!$inventarioId || $cantidad <= 0 || !$motivo) {
            $_SESSION['error'] = 'Producto, cantidad y motivo son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=bajas/nueva'); exit;
        }

        $db = $this->db();

        // Verifica que el producto exista y sea del tenant
        $stmt = $db->prepare(
            "SELECT id, nombre, cantidad_total, cantidad_disponible
             FROM inventario WHERE id = :id AND tenant_id = :tid"
        );
        $stmt->execute([':id' => $inventarioId, ':tid' => $this->tenantId()]);
        $producto = $stmt->fetch();

        if (!$producto) {
            $_SESSION['error'] = 'Producto no encontrado.';
            header('Location: ' . URL_BASE . '/?url=bajas/nueva'); exit;
        }

        $db->beginTransaction();
        try {
            // Con el producto bloqueado: solo se da de baja lo que esta
            // en bodega Y no esta comprometido para reservas futuras.
            // Si no, una silla rota hoy dejaria sin silla a un cliente
            // que ya reservo para el sabado.
            $db->prepare("SELECT id FROM inventario WHERE id = :id AND tenant_id = :tid FOR UPDATE")
               ->execute([':id' => $inventarioId, ':tid' => $this->tenantId()]);
            $retirables = unidadesRetirables($inventarioId, $this->tenantId());
            if ($cantidad > $retirables['cantidad']) {
                throw new RuntimeException(mensajeNoRetirable($producto['nombre'], $cantidad, $retirables));
            }

            // Registrar la baja
            $db->prepare(
                "INSERT INTO bajas_inventario (tenant_id, inventario_id, usuario_id, fecha_baja, motivo, cantidad)
                 VALUES (:tid, :iid, :uid, :fecha, :motivo, :cant)"
            )->execute([
                ':tid'    => $this->tenantId(),
                ':iid'    => $inventarioId,
                ':uid'    => $_SESSION['usuario_id'],
                ':fecha'  => $fechaBaja,
                ':motivo' => $motivo,
                ':cant'   => $cantidad,
            ]);

            // Reducir cantidad_total y cantidad_disponible
            $db->prepare(
                "UPDATE inventario
                 SET cantidad_total       = cantidad_total       - :cant,
                     cantidad_disponible  = cantidad_disponible  - :cant2
                 WHERE id = :id AND tenant_id = :tid"
            )->execute([
                ':cant'  => $cantidad,
                ':cant2' => $cantidad,
                ':id'    => $inventarioId,
                ':tid'   => $this->tenantId(),
            ]);

            $db->commit();
            $_SESSION['exito'] = "Baja registrada: {$cantidad} unidades de \"{$producto['nombre']}\".";
            header('Location: ' . URL_BASE . '/?url=bajas/index'); exit;

        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            // Solo nuestros RuntimeException se muestran tal cual; los de
            // base de datos (PDOException hereda de RuntimeException) y
            // cualquier otro error van al log
            $_SESSION['error'] = ($e instanceof PDOException || !($e instanceof RuntimeException))
                ? mensajeError($e, 'Error al registrar la baja.')
                : $e->getMessage();
            header('Location: ' . URL_BASE . '/?url=bajas/nueva'); exit;
        }
    }

    // ── Helper ─────────────────────────────────────────────────
    private function obtenerSucursales(): array {
        $stmt = $this->db()->prepare(
            "SELECT id, nombre FROM sucursales WHERE tenant_id = :tid AND activa = 1 ORDER BY nombre"
        );
        $stmt->execute([':tid' => $this->tenantId()]);
        return $stmt->fetchAll();
    }
}
