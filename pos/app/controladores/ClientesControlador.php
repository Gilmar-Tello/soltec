<?php

class ClientesControlador {

    private function db() { return obtenerConexion(); }

    // ── Listado ───────────────────────────────────────────────
    public function index() {
        $tid = tenantActual();
        $busqueda = trim($_GET['busqueda'] ?? '');
        $sql = "SELECT c.*,
                    COALESCE(SUM(CASE WHEN v.estado_pago='pendiente' AND v.anulada=0 THEN v.saldo_pendiente ELSE 0 END), 0) AS deuda_total
                FROM clientes c
                LEFT JOIN ventas v ON v.cliente_id = c.id AND v.tenant_id = :tid1
                WHERE c.activo = 1 AND c.tenant_id = :tid2" . sqlSucursal('c');   // los clientes de la sucursal que se esta viendo
        $params = [':tid1' => $tid, ':tid2' => $tid];
        if ($busqueda) {
            $sql .= " AND (c.nombre LIKE :b OR c.telefono LIKE :b2 OR c.nit LIKE :b3)";
            $params[':b']  = "%$busqueda%";
            $params[':b2'] = "%$busqueda%";
            $params[':b3'] = "%$busqueda%";
        }
        $sql .= " GROUP BY c.id ORDER BY c.nombre ASC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $clientes     = $stmt->fetchAll();
        $tituloPagina = 'Clientes';
        include RAIZ . '/app/vistas/clientes/lista.php';
    }

    // ── Nuevo cliente ─────────────────────────────────────────
    public function nuevo() {
        $cliente      = null;
        $tituloPagina = 'Nuevo Cliente';
        include RAIZ . '/app/vistas/clientes/formulario.php';
    }

    // ── Guardar cliente ───────────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=clientes/index'); exit;
        }
        $tid = tenantActual();
        $nombre  = trim($_POST['nombre'] ?? '');
        $tel     = trim($_POST['telefono'] ?? '');
        $dir     = trim($_POST['direccion'] ?? '');
        $nit     = trim($_POST['nit'] ?? 'CF');
        $limite  = floatval($_POST['limite_credito'] ?? 0);

        if (!$nombre) {
            $_SESSION['error'] = 'El nombre es obligatorio.';
            header('Location: ' . URL_BASE . '/?url=clientes/nuevo'); exit;
        }
        $this->db()->prepare("INSERT INTO clientes (tenant_id,nombre,telefono,direccion,nit,limite_credito" . (catalogoPorSucursal() ? ',sucursal_id' : '') . ")
            VALUES(:tid,:nombre,:telefono,:direccion,:nit,:limite" . (catalogoPorSucursal() ? ',' . sucursalTrabajo() : '') . ")")
            ->execute([':tid'=>$tid,':nombre'=>$nombre,':telefono'=>$tel,':direccion'=>$dir,':nit'=>$nit,':limite'=>$limite]);
        $_SESSION['exito'] = "Cliente '$nombre' registrado"
            . (catalogoPorSucursal() && sucursalesHabilitadas() ? ' en ' . nombreSucursalTrabajo() : '') . '.';
        header('Location: ' . URL_BASE . '/?url=clientes/index'); exit;
    }

    // ── Crear cliente rápido desde el POS (AJAX, responde JSON) ──
    public function crearRapido() {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido']); exit;
        }
        $tid    = tenantActual();
        $nombre = trim($_POST['nombre'] ?? '');
        $tel    = trim($_POST['telefono'] ?? '');
        $nit    = trim($_POST['nit'] ?? '') ?: 'CF';

        if (!$nombre) {
            echo json_encode(['exito' => false, 'mensaje' => 'El nombre es obligatorio.']); exit;
        }

        $stmt = $this->db()->prepare(
            "INSERT INTO clientes (tenant_id, nombre, telefono, nit" . (catalogoPorSucursal() ? ', sucursal_id' : '') . ") VALUES (:tid, :nombre, :telefono, :nit" . (catalogoPorSucursal() ? ', ' . sucursalTrabajo() : '') . ")");
        $stmt->execute([':tid' => $tid, ':nombre' => $nombre, ':telefono' => $tel, ':nit' => $nit]);
        $id = $this->db()->lastInsertId();

        echo json_encode(['exito' => true, 'id' => $id, 'nombre' => $nombre]);
        exit;
    }

    // ── Editar cliente ────────────────────────────────────────
    public function editar($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT * FROM clientes WHERE id=:id AND tenant_id=:tid" . sqlSucursal());
        $stmt->execute([':id'=>$id, ':tid'=>$tid]);
        $cliente = $stmt->fetch();
        if (!$cliente) { header('Location: ' . URL_BASE . '/?url=clientes/index'); exit; }
        $tituloPagina = 'Editar Cliente';
        include RAIZ . '/app/vistas/clientes/formulario.php';
    }

    // ── Actualizar cliente ────────────────────────────────────
    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=clientes/index'); exit;
        }
        $tid    = tenantActual();
        $id     = intval($_POST['id']);
        $nombre = trim($_POST['nombre'] ?? '');
        $tel    = trim($_POST['telefono'] ?? '');
        $dir    = trim($_POST['direccion'] ?? '');
        $nit    = trim($_POST['nit'] ?? 'CF');
        $limite = floatval($_POST['limite_credito'] ?? 0);

        $this->db()->prepare("UPDATE clientes SET nombre=:n,telefono=:t,direccion=:d,nit=:nit,limite_credito=:l WHERE id=:id AND tenant_id=:tid" . sqlSucursal())
            ->execute([':n'=>$nombre,':t'=>$tel,':d'=>$dir,':nit'=>$nit,':l'=>$limite,':id'=>$id,':tid'=>$tid]);
        $_SESSION['exito'] = 'Cliente actualizado.';
        header('Location: ' . URL_BASE . '/?url=clientes/index'); exit;
    }

    // ── Ver cuenta del cliente ────────────────────────────────
    public function cuenta($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT * FROM clientes WHERE id=:id AND tenant_id=:tid" . sqlSucursal());
        $stmt->execute([':id'=>$id, ':tid'=>$tid]);
        $cliente = $stmt->fetch();
        if (!$cliente) { header('Location: ' . URL_BASE . '/?url=clientes/index'); exit; }

        $stmt2 = $this->db()->prepare(
            "SELECT v.*,
                    COALESCE(SUM(p.monto), 0) AS pagado
             FROM ventas v
             LEFT JOIN pagos_credito p ON p.venta_id = v.id AND p.tenant_id = :tid1
             WHERE v.cliente_id = :id AND v.tipo_pago = 'credito' AND v.anulada = 0 AND v.tenant_id = :tid2
             GROUP BY v.id
             ORDER BY v.creado_en DESC");
        $stmt2->execute([':tid1'=>$tid, ':id' => $id, ':tid2'=>$tid]);
        $ventas = $stmt2->fetchAll();

        $deudaTotal = array_sum(array_map(
            fn($v) => $v['estado_pago'] === 'pendiente' ? $v['saldo_pendiente'] : 0,
            $ventas
        ));

        $detallesPorVenta = [];
        $ventaIds = array_column($ventas, 'id');
        if (!empty($ventaIds)) {
            $placeholders = implode(',', array_fill(0, count($ventaIds), '?'));
            $stmtD = $this->db()->prepare(
                "SELECT d.venta_id, d.cantidad, d.precio_unitario, d.subtotal,
                        p.nombre AS producto_nombre, u.abreviatura AS unidad
                 FROM detalle_venta d
                 JOIN productos p ON p.id = d.producto_id
                 JOIN unidades_medida u ON u.id = p.unidad_id
                 WHERE d.venta_id IN ($placeholders) AND d.tenant_id = ?");
            $stmtD->execute([...$ventaIds, $tid]);
            foreach ($stmtD->fetchAll() as $d) {
                $detallesPorVenta[$d['venta_id']][] = $d;
            }
        }

        $stmt3 = $this->db()->prepare(
            "SELECT m.*, u.nombre AS usuario_nombre
             FROM movimientos_saldo_cliente m
             JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.cliente_id=:id AND m.tenant_id=:tid
             ORDER BY m.creado_en DESC");
        $stmt3->execute([':id'=>$id, ':tid'=>$tid]);
        $movimientosSaldo = $stmt3->fetchAll();

        $tituloPagina = 'Cuenta — ' . $cliente['nombre'];
        include RAIZ . '/app/vistas/clientes/cuenta.php';
    }

    // ── Registrar depósito de saldo a favor ───────────────────
    public function depositarSaldo($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=clientes/cuenta/' . $id); exit;
        }
        $tid   = tenantActual();
        $monto = floatval($_POST['monto'] ?? 0);
        $notas = trim($_POST['notas'] ?? '');

        if (!is_finite($monto) || $monto <= 0) {
            $_SESSION['error'] = 'El monto debe ser mayor a cero.';
            header('Location: ' . URL_BASE . '/?url=clientes/cuenta/' . $id); exit;
        }
        $monto = round($monto, 2);

        $db = $this->db();
        $db->beginTransaction();
        try {
            // FOR UPDATE: un depósito y una venta con saldo al mismo tiempo no se pisan
            $stmt = $db->prepare("SELECT saldo_favor FROM clientes WHERE id=:id AND tenant_id=:tid" . sqlSucursal() . " FOR UPDATE");
            $stmt->execute([':id'=>$id, ':tid'=>$tid]);
            $saldoActual = $stmt->fetchColumn();
            if ($saldoActual === false) {
                $db->rollBack();
                $_SESSION['error'] = 'Cliente no encontrado.';
                header('Location: ' . URL_BASE . '/?url=clientes/index'); exit;
            }
            $saldoActual = floatval($saldoActual);
            $saldoNuevo  = round($saldoActual + $monto, 2);

            $db->prepare("UPDATE clientes SET saldo_favor=:s WHERE id=:id AND tenant_id=:tid")
               ->execute([':s'=>$saldoNuevo, ':id'=>$id, ':tid'=>$tid]);

            $db->prepare("INSERT INTO movimientos_saldo_cliente
                (tenant_id,cliente_id,usuario_id,tipo,monto,saldo_anterior,saldo_nuevo,notas)
                VALUES(:tid,:cid,:uid,'deposito',:monto,:ant,:nvo,:notas)")
               ->execute([
                   ':tid'=>$tid, ':cid'=>$id, ':uid'=>$_SESSION['usuario_id'],
                   ':monto'=>$monto, ':ant'=>$saldoActual, ':nvo'=>$saldoNuevo, ':notas'=>$notas,
               ]);
            $db->commit();
            $_SESSION['exito'] = "Depósito de Q" . number_format($monto, 2) . " registrado. Nuevo saldo: Q" . number_format($saldoNuevo, 2) . ".";
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'No se pudo registrar el depósito. Intentá de nuevo.');
        }
        header('Location: ' . URL_BASE . '/?url=clientes/cuenta/' . $id); exit;
    }

    // ── PDF: todos los deudores ───────────────────────────────
    public function pdfDeudores() {
        $tid = tenantActual();
        $stmt = $this->db()->prepare(
            "SELECT c.*,
                    COALESCE(SUM(CASE WHEN v.estado_pago='pendiente' AND v.anulada=0 THEN v.saldo_pendiente ELSE 0 END), 0) AS deuda_total,
                    COUNT(CASE WHEN v.estado_pago='pendiente' AND v.anulada=0 THEN 1 END) AS ventas_pendientes
             FROM clientes c
             JOIN ventas v ON v.cliente_id = c.id AND v.tenant_id = :tid1
             WHERE c.activo = 1 AND c.tenant_id = :tid2" . sqlSucursal('c') . "
             GROUP BY c.id
             HAVING deuda_total > 0
             ORDER BY deuda_total DESC"
        );
        $stmt->execute([':tid1'=>$tid, ':tid2'=>$tid]);
        $clientes = $stmt->fetchAll();
        $totalDeuda   = array_sum(array_column($clientes, 'deuda_total'));
        $tituloPagina = 'Clientes Deudores';
        ob_end_clean();
        include RAIZ . '/app/vistas/clientes/pdf_deudores.php';
        exit;
    }

    // ── PDF: deuda individual ─────────────────────────────────
    public function pdfCuenta($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT * FROM clientes WHERE id=:id AND tenant_id=:tid" . sqlSucursal());
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $cliente = $stmt->fetch();
        if (!$cliente) { header('Location: ' . URL_BASE . '/?url=clientes/index'); exit; }

        $stmt2 = $this->db()->prepare(
            "SELECT v.*, COALESCE(SUM(p.monto), 0) AS pagado
             FROM ventas v
             LEFT JOIN pagos_credito p ON p.venta_id = v.id AND p.tenant_id = :tid1
             WHERE v.cliente_id = :id AND v.tipo_pago = 'credito' AND v.anulada = 0 AND v.tenant_id = :tid2
             GROUP BY v.id ORDER BY v.creado_en DESC");
        $stmt2->execute([':tid1'=>$tid, ':id' => $id, ':tid2'=>$tid]);
        $ventas = $stmt2->fetchAll();

        $deudaTotal   = array_sum(array_map(fn($v) => $v['estado_pago'] === 'pendiente' ? $v['saldo_pendiente'] : 0, $ventas));
        $tituloPagina = 'Estado de Cuenta — ' . $cliente['nombre'];
        ob_end_clean();
        include RAIZ . '/app/vistas/clientes/pdf_cuenta.php';
        exit;
    }

    // ── Desactivar cliente ────────────────────────────────────
    public function desactivar($id) {
        $tid = tenantActual();
        $this->db()->prepare("UPDATE clientes SET activo=0 WHERE id=:id AND tenant_id=:tid" . sqlSucursal())
             ->execute([':id'=>$id, ':tid'=>$tid]);
        $_SESSION['exito'] = 'Cliente desactivado.';
        header('Location: ' . URL_BASE . '/?url=clientes/index'); exit;
    }
}