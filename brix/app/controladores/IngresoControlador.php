<?php

class IngresoControlador {

    private function db() { return obtenerConexion(); }
    private function tid(): int { return tenantActual(); }

    // ── Listado ────────────────────────────────────────────────
    public function index() {
        $db  = $this->db();
        $tid = $this->tid();

        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        $tipo  = $_GET['tipo']  ?? '';

        $sql = "SELECT i.*, u.nombre AS usuario_nombre
                FROM ingresos i
                LEFT JOIN usuarios u ON u.id = i.usuario_id
                WHERE i.tenant_id = :t AND i.anulado_en IS NULL AND DATE(i.creado_en) BETWEEN :d AND :h";
        $params = [':t'=>$tid, ':d'=>$desde, ':h'=>$hasta];
        if ($tipo === 'venta' || $tipo === 'libre') {
            $sql .= " AND i.tipo = :tp";
            $params[':tp'] = $tipo;
        }
        $sql .= " ORDER BY i.creado_en DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $ingresos = $stmt->fetchAll();

        $total = 0;
        foreach ($ingresos as $i) { $total += (float)$i['monto']; }

        $tituloPagina = 'Ingresos';
        include RAIZ . '/app/vistas/ingresos/index.php';
    }

    // ── Comprobante imprimible (venta o servicio) ──────────────
    public function comprobante($id) {
        $db  = $this->db();
        $tid = $this->tid();
        $id  = intval($id);

        $stmt = $db->prepare(
            "SELECT i.*, u.nombre AS usuario_nombre
             FROM ingresos i
             LEFT JOIN usuarios u ON u.id = i.usuario_id
             WHERE i.id = :id AND i.tenant_id = :t"
        );
        $stmt->execute([':id'=>$id, ':t'=>$tid]);
        $ingreso = $stmt->fetch();
        if (!$ingreso) {
            $_SESSION['error'] = 'Comprobante no encontrado.';
            header('Location: ' . URL_BASE . '/?url=ingresos/index'); exit;
        }

        $neg = $db->prepare("SELECT * FROM negocios WHERE id = :id");
        $neg->execute([':id'=>$tid]);
        $negocio = $neg->fetch() ?: [];

        if ($ingreso['tipo'] === 'venta') {
            $d = $db->prepare(
                "SELECT d.cantidad, d.precio_unitario, d.subtotal, p.nombre AS concepto
                 FROM ingreso_detalle d
                 LEFT JOIN productos p ON p.id = d.producto_id
                 WHERE d.ingreso_id = :i AND d.tenant_id = :t"
            );
        } else {
            $d = $db->prepare(
                "SELECT descripcion AS concepto, cantidad, precio_unitario, subtotal
                 FROM ingreso_detalle_libre
                 WHERE ingreso_id = :i AND tenant_id = :t"
            );
        }
        $d->execute([':i'=>$id, ':t'=>$tid]);
        $lineas = $d->fetchAll();

        // Compatibilidad con ingresos antiguos sin detalle
        if (empty($lineas)) {
            $lineas = [[
                'concepto'        => $ingreso['concepto'] ?: ($ingreso['tipo']==='venta' ? 'Venta' : 'Servicio'),
                'cantidad'        => 1,
                'precio_unitario' => $ingreso['monto'],
                'subtotal'        => $ingreso['monto'],
            ]];
        }

        $tituloPagina = 'Comprobante #' . $id;
        if (ob_get_level()) ob_end_clean();
        include RAIZ . '/app/vistas/ingresos/comprobante.php';
        exit;
    }

    // ── Formulario nuevo ───────────────────────────────────────
    public function nuevo() {
        $db  = $this->db();
        $tid = $this->tid();

        $stmt = $db->prepare(
            "SELECT p.id, p.nombre, p.precio_venta, p.es_compuesto, COALESCE(s.cantidad,0) AS stock
             FROM productos p
             LEFT JOIN stock s ON s.producto_id = p.id AND s.tenant_id = :t1
             WHERE p.tenant_id = :t2 AND p.activo = 1
             ORDER BY p.nombre"
        );
        $stmt->execute([':t1'=>$tid, ':t2'=>$tid]);
        $productos = $stmt->fetchAll();

        // Para compuestos, el stock vendible es cuántos se pueden hacer con los componentes
        foreach ($productos as &$p) {
            if ($p['es_compuesto']) $p['stock'] = $this->disponibilidadCompuesto((int)$p['id']);
        }
        unset($p);

        $tituloPagina = 'Nuevo ingreso';
        include RAIZ . '/app/vistas/ingresos/nuevo.php';
    }

    // Cuántas unidades de un compuesto se pueden hacer con el stock de componentes
    private function disponibilidadCompuesto(int $compuestoId): int {
        $stmt = $this->db()->prepare(
            "SELECT COALESCE(MIN(FLOOR(COALESCE(s.cantidad,0) / pc.cantidad)), 0) AS disp
             FROM producto_componentes pc
             LEFT JOIN stock s ON s.producto_id = pc.componente_id AND s.tenant_id = :t1
             WHERE pc.compuesto_id = :cid AND pc.tenant_id = :t2 AND pc.cantidad > 0"
        );
        $stmt->execute([':t1'=>$this->tid(), ':cid'=>$compuestoId, ':t2'=>$this->tid()]);
        return (int) $stmt->fetchColumn();
    }

    // ── Guardar ────────────────────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=ingresos/index'); exit;
        }

        $tipo = ($_POST['tipo'] ?? 'libre') === 'venta' ? 'venta' : 'libre';

        if ($tipo === 'libre') {
            $this->guardarLibre();
        } else {
            $this->guardarVenta();
        }
    }

    private function guardarLibre() {
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
            header('Location: ' . URL_BASE . '/?url=ingresos/nuevo'); exit;
        }

        $total = 0;
        foreach ($lineas as $l) { $total += $l['sub']; }

        $db->beginTransaction();
        try {
            $db->prepare(
                "INSERT INTO ingresos (tenant_id, usuario_id, tipo, concepto, monto)
                 VALUES (:t, :u, 'libre', :c, :m)"
            )->execute([
                ':t'=>$tid, ':u'=>$_SESSION['usuario_id'],
                ':c'=>($concepto !== '' ? $concepto : 'Ingreso libre'), ':m'=>$total,
            ]);
            $ingresoId = $db->lastInsertId();

            $ins = $db->prepare(
                "INSERT INTO ingreso_detalle_libre (tenant_id, ingreso_id, descripcion, cantidad, precio_unitario, subtotal)
                 VALUES (:t, :i, :d, :cant, :precio, :sub)"
            );
            foreach ($lineas as $l) {
                $ins->execute([
                    ':t'=>$tid, ':i'=>$ingresoId, ':d'=>$l['desc'],
                    ':cant'=>$l['cant'], ':precio'=>$l['precio'], ':sub'=>$l['sub'],
                ]);
            }

            $db->commit();
            $_SESSION['exito'] = 'Ingreso registrado. Total: Q' . number_format($total, 2);
            header('Location: ' . URL_BASE . '/?url=ingresos/index'); exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al registrar el ingreso. Intenta de nuevo.');
            header('Location: ' . URL_BASE . '/?url=ingresos/nuevo'); exit;
        }
    }

    private function guardarVenta() {
        $db  = $this->db();
        $tid = $this->tid();

        $concepto = trim($_POST['concepto'] ?? '');
        $items    = $_POST['items'] ?? [];

        // Normaliza y valida líneas
        $lineas = [];
        foreach ($items as $it) {
            $pid    = intval($it['producto_id'] ?? 0);
            $cant   = round((float) ($it['cantidad'] ?? 0), 2);
            $precio = round((float) ($it['precio'] ?? 0), 2);
            if ($pid <= 0 || $cant <= 0) continue;

            $stmt = $db->prepare(
                "SELECT p.id, p.nombre, p.precio_venta, p.es_compuesto, COALESCE(s.cantidad,0) AS stock
                 FROM productos p
                 LEFT JOIN stock s ON s.producto_id = p.id AND s.tenant_id = :t1
                 WHERE p.id = :id AND p.tenant_id = :t2 AND p.activo = 1"
            );
            $stmt->execute([':t1'=>$tid, ':id'=>$pid, ':t2'=>$tid]);
            $prod = $stmt->fetch();
            if (!$prod) continue;

            if ($precio <= 0) $precio = (float) $prod['precio_venta'];

            if ($prod['es_compuesto']) {
                // Compuesto: se consume de los componentes al vender
                $disp = $this->disponibilidadCompuesto($pid);
                if ($cant > $disp) {
                    $_SESSION['error'] = "No hay componentes suficientes para {$cant} de \"{$prod['nombre']}\". Podés hacer: {$disp}.";
                    header('Location: ' . URL_BASE . '/?url=ingresos/nuevo'); exit;
                }
                $cstmt = $db->prepare(
                    "SELECT pc.componente_id, pc.cantidad, COALESCE(s.cantidad,0) AS stock
                     FROM producto_componentes pc
                     LEFT JOIN stock s ON s.producto_id = pc.componente_id AND s.tenant_id = :t1
                     WHERE pc.compuesto_id = :cid AND pc.tenant_id = :t2 AND pc.cantidad > 0"
                );
                $cstmt->execute([':t1'=>$tid, ':cid'=>$pid, ':t2'=>$tid]);
                $lineas[] = [
                    'id'=>$pid, 'nombre'=>$prod['nombre'], 'cant'=>$cant, 'precio'=>$precio,
                    'sub'=>round($cant * $precio, 2), 'es_compuesto'=>1,
                    'componentes'=>$cstmt->fetchAll(),
                ];
            } else {
                if ($cant > (float) $prod['stock']) {
                    $_SESSION['error'] = "Stock insuficiente de \"{$prod['nombre']}\". Disponible: " . rtrim(rtrim(number_format($prod['stock'],2),'0'),'.') . ".";
                    header('Location: ' . URL_BASE . '/?url=ingresos/nuevo'); exit;
                }
                $lineas[] = [
                    'id'=>$pid, 'nombre'=>$prod['nombre'], 'cant'=>$cant, 'precio'=>$precio,
                    'sub'=>round($cant * $precio, 2), 'es_compuesto'=>0, 'stock'=>(float)$prod['stock'],
                ];
            }
        }

        if (empty($lineas)) {
            $_SESSION['error'] = 'Agregá al menos un producto con cantidad mayor a cero.';
            header('Location: ' . URL_BASE . '/?url=ingresos/nuevo'); exit;
        }

        $total = 0;
        foreach ($lineas as $l) { $total += $l['sub']; }

        $db->beginTransaction();
        try {
            $db->prepare(
                "INSERT INTO ingresos (tenant_id, usuario_id, tipo, concepto, monto)
                 VALUES (:t, :u, 'venta', :c, :m)"
            )->execute([
                ':t'=>$tid, ':u'=>$_SESSION['usuario_id'],
                ':c'=>($concepto !== '' ? $concepto : 'Venta de productos'), ':m'=>$total,
            ]);
            $ingresoId = $db->lastInsertId();

            foreach ($lineas as $l) {
                $db->prepare(
                    "INSERT INTO ingreso_detalle (tenant_id, ingreso_id, producto_id, cantidad, precio_unitario, subtotal)
                     VALUES (:t, :i, :p, :cant, :precio, :sub)"
                )->execute([
                    ':t'=>$tid, ':i'=>$ingresoId, ':p'=>$l['id'],
                    ':cant'=>$l['cant'], ':precio'=>$l['precio'], ':sub'=>$l['sub'],
                ]);

                if (!empty($l['es_compuesto'])) {
                    // Compuesto: descuenta cada componente (cantidad receta × cantidad vendida)
                    foreach ($l['componentes'] as $c) {
                        $usar = (float)$c['cantidad'] * $l['cant'];
                        $ant  = (float)$c['stock'];
                        $nvo  = $ant - $usar;
                        $db->prepare("UPDATE stock SET cantidad = cantidad - :u WHERE producto_id = :p AND tenant_id = :t")
                           ->execute([':u'=>$usar, ':p'=>$c['componente_id'], ':t'=>$tid]);
                        $db->prepare(
                            "INSERT INTO movimientos_stock
                                (tenant_id, producto_id, usuario_id, tipo, origen, cantidad, stock_anterior, stock_nuevo, motivo, referencia_id)
                             VALUES (:t, :p, :u, 'salida', 'venta', :cant, :ant, :nvo, :mot, :ref)"
                        )->execute([
                            ':t'=>$tid, ':p'=>$c['componente_id'], ':u'=>$_SESSION['usuario_id'],
                            ':cant'=>$usar, ':ant'=>$ant, ':nvo'=>$nvo,
                            ':mot'=>'Venta de '.$l['nombre'].' (ingreso #'.$ingresoId.')', ':ref'=>$ingresoId,
                        ]);
                    }
                } else {
                    // Simple: descuento atómico de su propio stock
                    $db->prepare("UPDATE stock SET cantidad = cantidad - :c WHERE producto_id = :p AND tenant_id = :t")
                       ->execute([':c'=>$l['cant'], ':p'=>$l['id'], ':t'=>$tid]);
                    $nuevo = $l['stock'] - $l['cant'];
                    $db->prepare(
                        "INSERT INTO movimientos_stock
                            (tenant_id, producto_id, usuario_id, tipo, origen, cantidad, stock_anterior, stock_nuevo, motivo, referencia_id)
                         VALUES (:t, :p, :u, 'salida', 'venta', :cant, :ant, :nvo, :mot, :ref)"
                    )->execute([
                        ':t'=>$tid, ':p'=>$l['id'], ':u'=>$_SESSION['usuario_id'],
                        ':cant'=>$l['cant'], ':ant'=>$l['stock'], ':nvo'=>$nuevo,
                        ':mot'=>'Venta (ingreso #'.$ingresoId.')', ':ref'=>$ingresoId,
                    ]);
                }
            }

            $db->commit();
            $_SESSION['exito'] = 'Venta registrada. Total: Q' . number_format($total, 2);
            header('Location: ' . URL_BASE . '/?url=ingresos/index'); exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al registrar la venta. Intenta de nuevo.');
            header('Location: ' . URL_BASE . '/?url=ingresos/nuevo'); exit;
        }
    }
}
