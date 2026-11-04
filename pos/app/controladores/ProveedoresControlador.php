<?php

class ProveedoresControlador {

    private function db() { return obtenerConexion(); }

    // ── Listado ───────────────────────────────────────────────
    public function index() {
        $tid = tenantActual();
        $busqueda = trim($_GET['busqueda'] ?? '');
        $sql = "SELECT p.*,
                    COUNT(o.id) AS total_ordenes,
                    COALESCE(SUM(CASE WHEN o.estado='pendiente' THEN 1 ELSE 0 END), 0) AS ordenes_pendientes
                FROM proveedores p
                LEFT JOIN ordenes_compra o ON o.proveedor_id = p.id AND o.tenant_id = :tid1" . sqlSucursal('o') . "
                WHERE p.activo = 1 AND p.tenant_id = :tid2";
        $params = [':tid1' => $tid, ':tid2' => $tid];
        if ($busqueda) {
            $sql .= " AND (p.nombre LIKE :b OR p.contacto LIKE :b2 OR p.telefono LIKE :b3)";
            $params[':b']  = "%$busqueda%";
            $params[':b2'] = "%$busqueda%";
            $params[':b3'] = "%$busqueda%";
        }
        $sql .= " GROUP BY p.id ORDER BY p.nombre ASC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $proveedores  = $stmt->fetchAll();
        $tituloPagina = 'Proveedores';
        include RAIZ . '/app/vistas/proveedores/lista.php';
    }

    // ── Nuevo proveedor ───────────────────────────────────────
    public function nuevo() {
        $proveedor    = null;
        $tituloPagina = 'Nuevo Proveedor';
        include RAIZ . '/app/vistas/proveedores/formulario.php';
    }

    // ── Guardar proveedor ─────────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit;
        }
        $tid = tenantActual();
        $campos = $this->extraerCampos();
        if (!$campos[':nombre']) {
            $_SESSION['error'] = 'El nombre es obligatorio.';
            header('Location: ' . URL_BASE . '/?url=proveedores/nuevo'); exit;
        }
        $campos[':tid'] = $tid;
        $this->db()->prepare("INSERT INTO proveedores (tenant_id,nombre,contacto,telefono,direccion,nit)
            VALUES(:tid,:nombre,:contacto,:telefono,:direccion,:nit)")
            ->execute($campos);
        $_SESSION['exito'] = "Proveedor '{$campos[':nombre']}' registrado.";
        header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit;
    }

    // ── Editar proveedor ──────────────────────────────────────
    public function editar($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT * FROM proveedores WHERE id=:id AND tenant_id=:tid");
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $proveedor = $stmt->fetch();
        if (!$proveedor) { header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit; }
        $tituloPagina = 'Editar Proveedor';
        include RAIZ . '/app/vistas/proveedores/formulario.php';
    }

    // ── Actualizar proveedor ──────────────────────────────────
    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit;
        }
        $tid           = tenantActual();
        $campos        = $this->extraerCampos();
        $campos[':id']  = intval($_POST['id']);
        $campos[':tid'] = $tid;
        $this->db()->prepare("UPDATE proveedores SET
            nombre=:nombre, contacto=:contacto, telefono=:telefono,
            direccion=:direccion, nit=:nit WHERE id=:id AND tenant_id=:tid")
            ->execute($campos);
        $_SESSION['exito'] = 'Proveedor actualizado.';
        header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit;
    }

    // ── Desactivar proveedor ──────────────────────────────────
    public function desactivar($id) {
        $tid = tenantActual();
        $this->db()->prepare("UPDATE proveedores SET activo=0 WHERE id=:id AND tenant_id=:tid")
             ->execute([':id' => $id, ':tid' => $tid]);
        $_SESSION['exito'] = 'Proveedor desactivado.';
        header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit;
    }

    // ── Nueva orden de compra ─────────────────────────────────
    public function nuevaOrden($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT * FROM proveedores WHERE id=:id AND tenant_id=:tid");
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $proveedor = $stmt->fetch();
        if (!$proveedor) { header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit; }

        $stmt2 = $this->db()->prepare(
            "SELECT p.*, u.abreviatura AS unidad, COALESCE(s.cantidad,0) AS stock_actual
            FROM productos p
            JOIN unidades_medida u ON u.id=p.unidad_id
            LEFT JOIN stock s ON s.producto_id=p.id AND s.tenant_id=:tid1
            WHERE p.activo=1 AND p.tenant_id=:tid2 AND p.proveedor_id=:pid" . sqlSucursal('p') . " ORDER BY p.nombre");
        $stmt2->execute([':tid1'=>$tid, ':tid2'=>$tid, ':pid'=>$id]);
        $productos = $stmt2->fetchAll();

        // Productos de este proveedor cuyo stock actual ya llegó (o está por debajo) del mínimo configurado
        $productosBajoStock = array_values(array_filter($productos, function ($p) {
            return floatval($p['stock_actual']) <= floatval($p['stock_minimo']);
        }));

        $tituloPagina = 'Nueva Orden de Compra';
        include RAIZ . '/app/vistas/proveedores/nueva_orden.php';
    }

    // ── Guardar orden de compra ───────────────────────────────
    public function guardarOrden() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit;
        }
        $tid = tenantActual();
        $proveedorId = intval($_POST['proveedor_id'] ?? 0);
        $notas       = trim($_POST['notas'] ?? '');
        $productos   = $_POST['productos'] ?? [];
        $cantidades  = $_POST['cantidades'] ?? [];
        $precios     = $_POST['precios'] ?? [];

        // Verificar que el proveedor pertenezca a este tenant
        $check = $this->db()->prepare("SELECT id FROM proveedores WHERE id=:id AND tenant_id=:tid");
        $check->execute([':id'=>$proveedorId, ':tid'=>$tid]);
        if (!$check->fetch()) {
            $_SESSION['error'] = 'Proveedor no encontrado.';
            header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit;
        }

        $items = [];
        foreach ($productos as $i => $pid) {
            $cant   = floatval($cantidades[$i] ?? 0);
            $precio = floatval($precios[$i] ?? 0);
            if ($pid && is_finite($cant) && $cant > 0 && is_finite($precio) && $precio > 0) {
                $items[] = ['pid' => intval($pid), 'cant' => $cant, 'precio' => $precio];
            }
        }

        if (empty($items)) {
            $_SESSION['error'] = 'Agregá al menos un producto con cantidad y precio.';
            header('Location: ' . URL_BASE . '/?url=proveedores/nuevaOrden/' . $proveedorId); exit;
        }

        // Validar que cada producto pertenezca realmente a este proveedor
        foreach ($items as $item) {
            $stmtVal = $this->db()->prepare("SELECT id, nombre FROM productos WHERE id=:pid AND tenant_id=:tid AND proveedor_id=:prov" . sqlSucursal());
            $stmtVal->execute([':pid'=>$item['pid'], ':tid'=>$tid, ':prov'=>$proveedorId]);
            if (!$stmtVal->fetch()) {
                $_SESSION['error'] = 'Uno de los productos seleccionados no pertenece a este proveedor.';
                header('Location: ' . URL_BASE . '/?url=proveedores/nuevaOrden/' . $proveedorId); exit;
            }
        }

        $total = array_sum(array_map(fn($i) => $i['cant'] * $i['precio'], $items));
        $db    = $this->db();
        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO ordenes_compra (tenant_id,proveedor_id,usuario_id,total,notas" . (catalogoPorSucursal() ? ',sucursal_id' : '') . ")
                VALUES(:tid,:pid,:uid,:total,:notas" . (catalogoPorSucursal() ? ',' . sucursalTrabajo() : '') . ")")
               ->execute([':tid'=>$tid,':pid'=>$proveedorId,':uid'=>$_SESSION['usuario_id'],':total'=>$total,':notas'=>$notas]);
            $ordenId = $db->lastInsertId();

            foreach ($items as $item) {
                $subtotal = $item['cant'] * $item['precio'];
                $db->prepare("INSERT INTO detalle_orden (tenant_id,orden_id,producto_id,cantidad,precio_unitario,subtotal)
                    VALUES(:tid,:oid,:pid,:cant,:precio,:sub)")
                   ->execute([':tid'=>$tid,':oid'=>$ordenId,':pid'=>$item['pid'],':cant'=>$item['cant'],
                              ':precio'=>$item['precio'],':sub'=>$subtotal]);
            }
            $db->commit();
            $_SESSION['exito'] = 'Orden de compra creada correctamente.';
            header('Location: ' . URL_BASE . '/?url=proveedores/orden/' . $ordenId);
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'No se pudo crear la orden. Intentá de nuevo.');
            header('Location: ' . URL_BASE . '/?url=proveedores/nuevaOrden/' . $proveedorId);
        }
        exit;
    }

    // ── PDF simple: lista de productos a comprar (código, producto, cantidad) ──
    public function pdfOrdenSimple($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare(
            "SELECT o.*, p.nombre AS proveedor_nombre
             FROM ordenes_compra o
             JOIN proveedores p ON p.id = o.proveedor_id
             WHERE o.id=:id AND o.tenant_id=:tid");
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $orden = $stmt->fetch();
        if (!$orden || !$this->ordenEnVista($orden)) { header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit; }

        $stmt2 = $this->db()->prepare(
            "SELECT d.cantidad, p.codigo AS producto_codigo, p.nombre AS producto_nombre, u.abreviatura AS unidad
             FROM detalle_orden d
             JOIN productos p ON p.id = d.producto_id
             JOIN unidades_medida u ON u.id = p.unidad_id
             WHERE d.orden_id=:id AND d.tenant_id=:tid
             ORDER BY p.nombre ASC");
        $stmt2->execute([':id' => $id, ':tid' => $tid]);
        $detalle = $stmt2->fetchAll();

        // FPDF usa Latin-1: hay que convertir todo texto que venga de la base (UTF-8)
        $co = fn($s) => mb_convert_encoding((string)$s, 'ISO-8859-1', 'UTF-8');

        ob_end_clean();
        require_once RAIZ . '/app/lib/fpdf19/fpdf.php';

        $pdf = new FPDF('P', 'mm', 'Letter');
        $pdf->SetTitle('Lista de Compra - Orden #' . $id);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->Cell(0, 8, $co('Lista de Compra, Orden #' . $id), 0, 1);

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(0, 6, $co('Proveedor: ' . $orden['proveedor_nombre']), 0, 1);
        $pdf->Cell(0, 6, $co('Fecha: ' . date('d/m/Y', strtotime($orden['creado_en']))), 0, 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(4);

        // Encabezado de tabla
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetFillColor(235, 235, 235);
        $pdf->Cell(30, 8, $co('Código'), 1, 0, 'L', true);
        $pdf->Cell(115, 8, $co('Producto'), 1, 0, 'L', true);
        $pdf->Cell(35, 8, $co('Cantidad'), 1, 1, 'R', true);

        // Filas
        $pdf->SetFont('Helvetica', '', 10);
        foreach ($detalle as $d) {
            $pdf->Cell(30, 8, $co($d['producto_codigo'] ?: '—'), 1, 0, 'L');
            $pdf->Cell(115, 8, $co($d['producto_nombre']), 1, 0, 'L');
            $pdf->Cell(35, 8, $co(number_format($d['cantidad'], 2) . ' ' . $d['unidad']), 1, 1, 'R');
        }

        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 5, $co('Generado el ' . date('d/m/Y \a \l\a\s H:i')), 0, 1);

        $nombreArchivo = 'orden_' . $id . '_lista_compra.pdf';
        $pdf->Output('D', $nombreArchivo); // 'D' = fuerza la descarga directa
        exit;
    }

    // ── Ver orden de compra ───────────────────────────────────
    public function orden($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare(
            "SELECT o.*, p.nombre AS proveedor_nombre, p.telefono AS proveedor_tel,
                    u.nombre AS usuario_nombre
             FROM ordenes_compra o
             JOIN proveedores p ON p.id = o.proveedor_id
             JOIN usuarios u    ON u.id = o.usuario_id
             WHERE o.id=:id AND o.tenant_id=:tid");
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $orden = $stmt->fetch();
        if (!$orden || !$this->ordenEnVista($orden)) { header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit; }

        $stmt2 = $this->db()->prepare(
            "SELECT d.*, p.nombre AS producto_nombre, u.abreviatura AS unidad
             FROM detalle_orden d
             JOIN productos p ON p.id = d.producto_id
             JOIN unidades_medida u ON u.id = p.unidad_id
             WHERE d.orden_id=:id AND d.tenant_id=:tid");
        $stmt2->execute([':id' => $id, ':tid' => $tid]);
        $detalle      = $stmt2->fetchAll();
        $tituloPagina = 'Orden #' . $id;
        include RAIZ . '/app/vistas/proveedores/orden.php';
    }

    // ── Recibir orden (actualiza stock) ───────────────────────
    public function recibirOrden($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT * FROM ordenes_compra WHERE id=:id AND tenant_id=:tid AND estado='pendiente'" . sqlSucursal());
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $orden = $stmt->fetch();
        if (!$orden) {
            $_SESSION['error'] = 'Orden no encontrada o ya fue procesada.';
            header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit;
        }

        $stmt2 = $this->db()->prepare("SELECT * FROM detalle_orden WHERE orden_id=:id AND tenant_id=:tid");
        $stmt2->execute([':id' => $id, ':tid' => $tid]);
        $detalle = $stmt2->fetchAll();

        $db = $this->db();
        $db->beginTransaction();
        try {
            // Marcar primero como recibida SOLO si sigue pendiente: si dos personas presionan
            // "Recibir" al mismo tiempo, la segunda no suma el stock otra vez.
            $marca = $db->prepare("UPDATE ordenes_compra SET estado='recibida' WHERE id=:id AND tenant_id=:tid AND estado='pendiente'");
            $marca->execute([':id' => $id, ':tid' => $tid]);
            if ($marca->rowCount() !== 1) {
                $db->rollBack();
                $_SESSION['error'] = 'Esta orden ya fue recibida.';
                header('Location: ' . URL_BASE . '/?url=proveedores/orden/' . $id); exit;
            }

            usort($detalle, fn($a, $b) => $a['producto_id'] <=> $b['producto_id']);
            $avisos = [];            // productos cuyo precio de venta quedo por debajo del nuevo costo
            $costosCambiados = 0;
            foreach ($detalle as $item) {
                $s = $db->prepare("SELECT cantidad FROM stock WHERE producto_id=:pid AND tenant_id=:tid FOR UPDATE");
                $s->execute([':pid' => $item['producto_id'], ':tid' => $tid]);
                $fila   = $s->fetchColumn();
                if ($fila === false) {
                    $db->prepare("INSERT INTO stock (tenant_id,producto_id,cantidad) VALUES(:tid,:pid,0)")
                       ->execute([':tid' => $tid, ':pid' => $item['producto_id']]);
                }
                $actual = floatval($fila);
                $nuevo  = $actual + $item['cantidad'];

                // Costo promedio ponderado: mezcla lo que ya hay en stock (a su costo actual) con lo que llega (al precio
                // de la orden). El precio de venta NO se toca. Solo corre si el negocio guarda el costo en cada venta
                // (migracion del 02/11), para que la ganancia de los informes pasados no cambie.
                $notaCosto = '';
                if (ventasGuardanCosto()) {
                    $pr = $db->prepare("SELECT nombre, precio_compra, precio_minimo, precio_venta_real FROM productos WHERE id=:pid AND tenant_id=:tid");
                    $pr->execute([':pid' => $item['producto_id'], ':tid' => $tid]);
                    $prod = $pr->fetch();
                    if ($prod) {
                        $costoViejo  = (float) $prod['precio_compra'];
                        $precioOrden = (float) $item['precio_unitario'];
                        $cantOrden   = (float) $item['cantidad'];
                        $base        = max($actual, 0.0);
                        $costoNuevo  = ($base <= 0 || $costoViejo <= 0)
                            ? $precioOrden
                            : ($base * $costoViejo + $cantOrden * $precioOrden) / ($base + $cantOrden);
                        $costoNuevo  = round($costoNuevo, 2);
                        if (abs($costoNuevo - $costoViejo) >= 0.005) {
                            $db->prepare("UPDATE productos SET precio_compra=:c WHERE id=:pid AND tenant_id=:tid")
                               ->execute([':c' => $costoNuevo, ':pid' => $item['producto_id'], ':tid' => $tid]);
                            $costosCambiados++;
                            $notaCosto = ' (costo: Q' . number_format($costoViejo, 2) . ' a Q' . number_format($costoNuevo, 2) . ')';
                            if ((float) $prod['precio_minimo'] < $costoNuevo || (float) $prod['precio_venta_real'] < $costoNuevo) {
                                $avisos[] = $prod['nombre'];
                            }
                        }
                    }
                }

                $db->prepare("UPDATE stock SET cantidad=:n WHERE producto_id=:pid AND tenant_id=:tid")
                   ->execute([':n'=>$nuevo, ':pid'=>$item['producto_id'], ':tid'=>$tid]);

                $db->prepare("INSERT INTO movimientos_stock
                    (tenant_id,producto_id,usuario_id,tipo,origen,cantidad,stock_anterior,stock_nuevo,motivo,referencia_id)
                    VALUES(:tid,:pid,:uid,'entrada','compra',:cant,:ant,:nvo,:mot,:ref)")
                   ->execute([
                       ':tid'  => $tid,
                       ':pid'  => $item['producto_id'],
                       ':uid'  => $_SESSION['usuario_id'],
                       ':cant' => $item['cantidad'],
                       ':ant'  => $actual,
                       ':nvo'  => $nuevo,
                       ':mot'  => 'Recepción de orden de compra #' . $id . $notaCosto,
                       ':ref'  => $id,
                   ]);
            }
            $db->commit();
            // Compra recibida = egreso en Brix (solo si el negocio tiene la sincronizacion encendida)
            try {
                $pv = $db->prepare("SELECT nombre FROM proveedores WHERE id=:id AND tenant_id=:t");
                $pv->execute([':id' => $orden['proveedor_id'], ':t' => $tid]);
                $nombreProveedor = (string) ($pv->fetchColumn() ?: 'proveedor');
                encolarMovimientoBrix($tid, 'egreso', 'pos:compra:' . (int) $id, date('Y-m-d H:i:s'),
                    'Compra a ' . $nombreProveedor . ' · Orden #' . (int) $id, (float) $orden['total']);
            } catch (Throwable $e) {
                error_log('[SOLTEC pos] No se pudo anotar la compra para Brix: ' . $e->getMessage());
            }
            $msg = 'Orden recibida. Stock actualizado correctamente.';
            if ($costosCambiados > 0) {
                $msg .= ' Costo promedio actualizado en ' . $costosCambiados . ' producto(s).';
            }
            if ($avisos) {
                $msg .= ' Revisa el precio de venta de: ' . implode(', ', array_slice($avisos, 0, 5))
                      . (count($avisos) > 5 ? ' y ' . (count($avisos) - 5) . ' más' : '') . ' (quedo por debajo del nuevo costo).';
            }
            $_SESSION['exito'] = $msg;
            header('Location: ' . URL_BASE . '/?url=proveedores/orden/' . $id);
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'No se pudo recibir la orden. Intentá de nuevo.');
            header('Location: ' . URL_BASE . '/?url=proveedores/orden/' . $id);
        }
        exit;
    }

    // ── Historial de órdenes por proveedor ────────────────────
    public function historial($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT * FROM proveedores WHERE id=:id AND tenant_id=:tid");
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $proveedor = $stmt->fetch();
        if (!$proveedor) { header('Location: ' . URL_BASE . '/?url=proveedores/index'); exit; }

        $stmt2 = $this->db()->prepare(
            "SELECT o.*, u.nombre AS usuario_nombre
             FROM ordenes_compra o
             JOIN usuarios u ON u.id = o.usuario_id
             WHERE o.proveedor_id=:id AND o.tenant_id=:tid" . sqlSucursal('o') . " ORDER BY o.creado_en DESC");
        $stmt2->execute([':id' => $id, ':tid' => $tid]);
        $ordenes      = $stmt2->fetchAll();
        $tituloPagina = 'Historial — ' . $proveedor['nombre'];
        include RAIZ . '/app/vistas/proveedores/historial.php';
    }

    // ── Helper ────────────────────────────────────────────────
    private function extraerCampos() {
        return [
            ':nombre'    => trim($_POST['nombre'] ?? ''),
            ':contacto'  => trim($_POST['contacto'] ?? ''),
            ':telefono'  => trim($_POST['telefono'] ?? ''),
            ':direccion' => trim($_POST['direccion'] ?? ''),
            ':nit'       => trim($_POST['nit'] ?? ''),
        ];
    }

    // Una orden de compra pertenece a la sucursal donde se hizo (sus productos son de esa sucursal).
    // Los proveedores, en cambio, son compartidos por todo el negocio.
    private function ordenEnVista(array $orden): bool {
        return !catalogoPorSucursal() || (int) ($orden['sucursal_id'] ?? 0) === sucursalTrabajo();
    }
}