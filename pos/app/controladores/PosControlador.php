<?php

class PosControlador {

    // Sucursal cuyo catalogo (productos y clientes) usan los validadores de esta peticion: la de la CAJA en las
    // ventas (null = la sucursal de trabajo). La venta obedece siempre a su caja, nunca al selector del administrador.
    private ?int $sucursalCatalogo = null;

    private function db() { return obtenerConexion(); }

    // ── Dashboard / verificar caja ────────────────────────────
    public function index() {
        $caja = $this->cajaActiva();
        $tituloPagina = 'Punto de Venta';
        if (!$caja) {
            include RAIZ . '/app/vistas/pos/abrir_caja.php';
        } else {
            include RAIZ . '/app/vistas/pos/index.php';
        }
    }

    // ── Abrir caja ────────────────────────────────────────────
    public function abrirCaja() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=pos/index'); exit;
        }
        $tid = tenantActual();
        $monto = floatval($_POST['monto_inicial'] ?? 0);
        if (!is_finite($monto) || $monto < 0) {
            $_SESSION['error'] = 'El monto inicial no puede ser negativo.';
            header('Location: ' . URL_BASE . '/?url=pos/index'); exit;
        }
        // Evita abrir dos cajas a la vez (doble clic en "Abrir caja")
        if ($this->cajaActiva()) {
            header('Location: ' . URL_BASE . '/?url=pos/index'); exit;
        }
        // La caja se abre en la sucursal de trabajo: la del cajero, o la que el administrador eligio en el selector.
        // En caja_sesiones la Principal se guarda como NULL (el INSERT de siempre).
        $sucursalId = sucursalTrabajo() ?: null;
        if ($sucursalId !== null) {
            $this->db()->prepare("INSERT INTO caja_sesiones (tenant_id,usuario_id, monto_inicial, sucursal_id) VALUES(:tid,:uid, :monto, :suc)")
                 ->execute([':tid'=>$tid, ':uid' => $_SESSION['usuario_id'], ':monto' => $monto, ':suc' => $sucursalId]);
        } else {
            $this->db()->prepare("INSERT INTO caja_sesiones (tenant_id,usuario_id, monto_inicial) VALUES(:tid,:uid, :monto)")
                 ->execute([':tid'=>$tid, ':uid' => $_SESSION['usuario_id'], ':monto' => $monto]);
        }
        $_SESSION['exito'] = 'Caja abierta correctamente.';
        header('Location: ' . URL_BASE . '/?url=pos/index'); exit;
    }

    // ── Cerrar caja ───────────────────────────────────────────
    public function cerrarCaja() {
        $tid = tenantActual();
        $caja = $this->cajaActiva();
        if (!$caja) { header('Location: ' . URL_BASE . '/?url=pos/index'); exit; }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $montoFinal = floatval($_POST['monto_final'] ?? 0);
            if (!is_finite($montoFinal) || $montoFinal < 0) {
                $_SESSION['error'] = 'El monto final no puede ser negativo.';
                header('Location: ' . URL_BASE . '/?url=pos/cerrarCaja'); exit;
            }
            $this->db()->prepare("UPDATE caja_sesiones SET estado='cerrada', monto_final=:mf, cerrada_en=NOW() WHERE id=:id AND tenant_id=:tid")
                 ->execute([':mf' => $montoFinal, ':id' => $caja['id'], ':tid' => $tid]);
            $this->anotarCierreParaBrix($tid, (int) $caja['id']);   // solo si el negocio tiene la funcion encendida
            $_SESSION['exito'] = 'Caja cerrada correctamente.';
            header('Location: ' . URL_BASE . '/?url=pos/index'); exit;
        }

        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) AS total_ventas, COALESCE(SUM(total),0) AS total_monto
             FROM ventas WHERE caja_sesion_id=:id AND tenant_id=:tid AND anulada=0");
        $stmt->execute([':id' => $caja['id'], ':tid' => $tid]);
        $resumen      = $stmt->fetch();
        $tituloPagina = 'Cerrar Caja';
        include RAIZ . '/app/vistas/pos/cerrar_caja.php';
    }

    // ── Cierre de caja -> ingreso en Brix (cola) ──────────────
    // Ingreso = lo COBRADO en este turno: ventas pagadas (efectivo, tarjeta, saldo del cliente) que no se
    // anularon, mas los abonos a credito que cobro quien cerro la caja durante el turno. Las ventas a credito
    // sin pagar no cuentan todavia; el monto inicial de la caja tampoco (no es una ganancia).
    private function anotarCierreParaBrix(int $tid, int $cajaId): void {
        try {
            if (brixVinculado($tid) === null) return;
            $db = $this->db();

            // Con sucursales, el cierre se etiqueta con su sucursal y su usuario, y solo cuenta los abonos de ventas
            // de ESA sucursal: un usuario puede tener dos cajas abiertas a la vez (una por sucursal) y, sin este
            // filtro, el mismo abono entraria en los dos cierres y Brix recibiria el ingreso duplicado.
            $conSucursales = funcionActiva('sucursales') && $this->cajaTieneSucursal();
            $st = $db->prepare("SELECT usuario_id, abierta_en, cerrada_en" . ($conSucursales ? ", sucursal_id" : "") . " FROM caja_sesiones WHERE id=:id AND tenant_id=:t");
            $st->execute([':id' => $cajaId, ':t' => $tid]);
            $caja = $st->fetch();
            if (!$caja || !$caja['cerrada_en']) return;

            $v = $db->prepare(
                "SELECT tipo_pago, COALESCE(SUM(total),0) AS suma
                 FROM ventas
                 WHERE tenant_id=:t AND caja_sesion_id=:c AND anulada=0 AND estado_pago='pagado'
                   AND tipo_pago IN ('efectivo','tarjeta','saldo_cliente')
                 GROUP BY tipo_pago");
            $v->execute([':t' => $tid, ':c' => $cajaId]);
            $porTipo = [];
            foreach ($v->fetchAll() as $f) $porTipo[$f['tipo_pago']] = (float) $f['suma'];

            $etiquetaCaja = '';
            if ($conSucursales) {
                // Abonos: solo los de ventas hechas en la caja de esta misma sucursal (pagos_credito -> ventas -> caja_sesiones)
                $a = $db->prepare(
                    "SELECT COALESCE(SUM(pc.monto),0) FROM pagos_credito pc
                     JOIN ventas v ON v.id = pc.venta_id AND v.tenant_id = pc.tenant_id
                     JOIN caja_sesiones cv ON cv.id = v.caja_sesion_id
                     WHERE pc.tenant_id=:t AND pc.usuario_id=:u AND pc.creado_en >= :ini AND pc.creado_en <= :fin
                       AND COALESCE(cv.sucursal_id,0) = :suc");
                $a->execute([':t' => $tid, ':u' => $caja['usuario_id'], ':ini' => $caja['abierta_en'], ':fin' => $caja['cerrada_en'],
                             ':suc' => (int) ($caja['sucursal_id'] ?? 0)]);

                // Etiqueta del movimiento en Brix: caja, sucursal y usuario
                $nu = $db->prepare("SELECT nombre FROM usuarios WHERE id=:u AND tenant_id=:t");
                $nu->execute([':u' => $caja['usuario_id'], ':t' => $tid]);
                $etiquetaCaja = ' · Sucursal ' . (nombresSucursales()[(int) ($caja['sucursal_id'] ?? 0)] ?? NOMBRE_SUCURSAL_PRINCIPAL)
                              . ' · ' . ($nu->fetchColumn() ?: 'usuario #' . $caja['usuario_id']);
            } else {
                $a = $db->prepare(
                    "SELECT COALESCE(SUM(monto),0) FROM pagos_credito
                     WHERE tenant_id=:t AND usuario_id=:u AND creado_en >= :ini AND creado_en <= :fin");
                $a->execute([':t' => $tid, ':u' => $caja['usuario_id'], ':ini' => $caja['abierta_en'], ':fin' => $caja['cerrada_en']]);
            }
            $abonos = (float) $a->fetchColumn();

            $partes = [];
            foreach (['efectivo' => 'efectivo', 'tarjeta' => 'tarjeta', 'saldo_cliente' => 'saldo de clientes'] as $k => $rot) {
                if (!empty($porTipo[$k])) $partes[] = $rot . ' Q' . number_format($porTipo[$k], 2);
            }
            if ($abonos > 0) $partes[] = 'abonos Q' . number_format($abonos, 2);
            $total = array_sum($porTipo) + $abonos;

            encolarMovimientoBrix(
                $tid, 'ingreso', 'pos:caja:' . $cajaId, (string) $caja['cerrada_en'],
                'Cierre de caja POS #' . $cajaId . $etiquetaCaja . ($partes ? ' · ' . implode(', ', $partes) : ''),
                $total
            );
        } catch (Throwable $e) {
            error_log('[SOLTEC pos] No se pudo anotar el cierre de caja para Brix: ' . $e->getMessage());
        }
    }

    // ── Pantalla de venta ─────────────────────────────────────
    public function venta() {
        $tid = tenantActual();
        $caja = $this->cajaActiva();
        if (!$caja) {
            $_SESSION['error'] = 'Debés abrir la caja antes de realizar ventas.';
            header('Location: ' . URL_BASE . '/?url=pos/index'); exit;
        }
        // La foto solo se pide si el negocio tiene encendida la funcion (si no, ni se toca la columna)
        $sucCaja = $this->sucursalDeCaja($caja);   // la pantalla de venta muestra el catalogo de la sucursal de la CAJA
        $colFoto = funcionActiva('fotos_productos') ? ', p.foto' : '';
        $stmt = $this->db()->prepare(
            "SELECT p.id, p.nombre, p.codigo, p.precio_venta_real, p.precio_minimo,
                    u.abreviatura AS unidad{$colFoto}, COALESCE(s.cantidad,0) AS stock,
                    COALESCE(vd.total_vendido, 0) AS total_vendido
             FROM productos p
             JOIN unidades_medida u ON u.id = p.unidad_id
             LEFT JOIN stock s ON s.producto_id = p.id AND s.tenant_id = :tid1
             LEFT JOIN (
                 SELECT d.producto_id, SUM(d.cantidad) AS total_vendido
                 FROM detalle_venta d
                 JOIN ventas v ON v.id = d.venta_id
                 WHERE v.tenant_id = :tid3
                 GROUP BY d.producto_id
             ) vd ON vd.producto_id = p.id
             WHERE p.activo=1 AND p.tenant_id = :tid2" . sqlSucursalId($sucCaja, 'p') . " AND COALESCE(s.cantidad,0) > 0
             ORDER BY total_vendido DESC, p.nombre ASC");
        $stmt->execute([':tid1'=>$tid, ':tid2'=>$tid, ':tid3'=>$tid]);
        $productos = $stmt->fetchAll();
        foreach ($productos as &$prodFoto) { $prodFoto['foto'] = urlFotoProducto($prodFoto['foto'] ?? null, $tid); }
        unset($prodFoto);

        $stmt2 = $this->db()->prepare(
            "SELECT id, nombre, nit, telefono, limite_credito, saldo_favor FROM clientes WHERE activo=1 AND tenant_id=:tid" . sqlSucursalId($sucCaja) . " ORDER BY nombre");
        $stmt2->execute([':tid'=>$tid]);
        $clientes = planPermiteCredito() ? $stmt2->fetchAll() : [];   // sin el modulo de Clientes no se ofrecen

        // Si venimos de "Convertir en Venta" desde una cotización, precargamos sus datos
        $cotizacionOrigen = null;
        $cotizacionId = intval($_GET['cotizacion_id'] ?? 0);
        if ($cotizacionId) {
            $stmtC = $this->db()->prepare(
                "SELECT * FROM cotizaciones WHERE id=:id AND tenant_id=:tid AND estado='abierta'" . sqlSucursalId($sucCaja));
            $stmtC->execute([':id'=>$cotizacionId, ':tid'=>$tid]);
            $cotizacionOrigen = $stmtC->fetch();
            if ($cotizacionOrigen) {
                $stmtCD = $this->db()->prepare(
                    "SELECT dc.producto_id, dc.cantidad, dc.precio_unitario, p.nombre, p.codigo,
                            p.precio_minimo, COALESCE(s.cantidad,0) AS stock, u.abreviatura AS unidad
                     FROM detalle_cotizacion dc
                     JOIN productos p ON p.id = dc.producto_id
                     JOIN unidades_medida u ON u.id = p.unidad_id
                     LEFT JOIN stock s ON s.producto_id = p.id AND s.tenant_id = :tid1
                     WHERE dc.cotizacion_id=:id AND dc.tenant_id=:tid2");
                $stmtCD->execute([':tid1'=>$tid, ':id'=>$cotizacionId, ':tid2'=>$tid]);
                $cotizacionOrigen['items'] = $stmtCD->fetchAll();
            } else {
                $_SESSION['error'] = 'Esa cotización no existe o ya no está abierta.';
            }
        }

        $tituloPagina = 'Nueva Venta';
        include RAIZ . '/app/vistas/pos/venta.php';
    }

    // ── Procesar venta ────────────────────────────────────────
    public function procesarVenta() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=pos/venta'); exit;
        }
        $tid = tenantActual();

        $caja       = $this->cajaActiva();
        if (!$caja) {
            $_SESSION['error'] = 'No hay caja abierta.';
            header('Location: ' . URL_BASE . '/?url=pos/index'); exit;
        }

        $clienteId  = intval($_POST['cliente_id'] ?? 0) ?: null;
        $tipoPago   = $_POST['tipo_pago'] ?? 'efectivo';
        $recibido   = floatval($_POST['monto_recibido'] ?? 0);
        $descuento  = floatval($_POST['descuento'] ?? 0);
        $notas      = trim($_POST['notas'] ?? '');
        $items      = json_decode($_POST['items'] ?? '[]', true);
        $cotizacionId = intval($_POST['cotizacion_id'] ?? 0) ?: null;
        $this->sucursalCatalogo = $this->sucursalDeCaja($caja);   // productos y clientes de la venta: los de su caja

        $volverConError = function (string $mensaje) {
            $_SESSION['error'] = $mensaje;
            header('Location: ' . URL_BASE . '/?url=pos/venta'); exit;
        };

        // ── 1. Validaciones que no necesitan bloquear nada ──
        try {
            if (!in_array($tipoPago, ['efectivo', 'tarjeta', 'credito', 'saldo_cliente'], true)) {
                throw new ErrorValidacion('Tipo de pago no válido.');
            }
            if (!is_finite($recibido) || $recibido < 0) {
                throw new ErrorValidacion('El monto recibido no es válido.');
            }
            $carrito = $this->validarCarrito($items, $tid, $descuento);
            $this->validarCliente($clienteId, $tid, $tipoPago);

            // Si viene de una cotización, verificar que siga abierta y sea de este tenant
            if ($cotizacionId) {
                $stmtCV = $this->db()->prepare("SELECT id FROM cotizaciones WHERE id=:id AND tenant_id=:tid AND estado='abierta'" . sqlSucursalId($this->sucursalDeCaja($caja)));
                $stmtCV->execute([':id'=>$cotizacionId, ':tid'=>$tid]);
                if (!$stmtCV->fetch()) {
                    throw new ErrorValidacion('Esa cotización ya no está disponible para convertir (puede que ya se haya usado).');
                }
            }
        } catch (ErrorValidacion $e) {
            $volverConError($e->getMessage());
        }

        $subtotal   = $carrito['subtotal'];
        $descuento  = $carrito['descuento'];
        $total      = $carrito['total'];
        $cambio     = $tipoPago === 'efectivo' ? max(0, round($recibido - $total, 2)) : 0;
        $saldo      = $tipoPago === 'credito'  ? $total : 0;
        $estadoPago = $tipoPago === 'credito' ? 'pendiente' : 'pagado';
        if ($tipoPago === 'saldo_cliente') {
            $recibido = $total;
            $cambio   = 0;
        }

        // ── 2. Transacción: todo lo que toca saldos y stock se bloquea (FOR UPDATE) ──
        $db = $this->db();
        $db->beginTransaction();
        try {
            // Pago con saldo a favor: se bloquea la fila del cliente y se vuelve a revisar
            // (evita que dos cajas gasten el mismo saldo al mismo tiempo)
            $cliente = null;
            if ($tipoPago === 'saldo_cliente') {
                $stmtCli = $db->prepare("SELECT nombre, saldo_favor FROM clientes WHERE id=:id AND tenant_id=:tid FOR UPDATE");
                $stmtCli->execute([':id'=>$clienteId, ':tid'=>$tid]);
                $cliente = $stmtCli->fetch();
                if (!$cliente) throw new ErrorValidacion('Cliente no encontrado.');
                if (floatval($cliente['saldo_favor']) < $total - 0.01) {
                    throw new ErrorValidacion("'{$cliente['nombre']}' no tiene saldo suficiente. Saldo disponible: Q" .
                        number_format($cliente['saldo_favor'], 2) . ", total de la venta: Q" . number_format($total, 2) . ".");
                }
            }

            $db->prepare("INSERT INTO ventas
                (tenant_id,caja_sesion_id,cliente_id,cotizacion_id,usuario_id,subtotal,descuento,total,tipo_pago,monto_recibido,cambio,saldo_pendiente,estado_pago,notas)
                VALUES(:tid,:cid,:cliid,:cotid,:uid,:sub,:desc,:total,:tpago,:recibido,:cambio,:saldo,:estado,:notas)")
               ->execute([
                   ':tid'      => $tid,
                   ':cid'      => $caja['id'],
                   ':cliid'    => $clienteId,
                   ':cotid'    => $cotizacionId,
                   ':uid'      => $_SESSION['usuario_id'],
                   ':sub'      => $subtotal,
                   ':desc'     => $descuento,
                   ':total'    => $total,
                   ':tpago'    => $tipoPago,
                   ':recibido' => $recibido,
                   ':cambio'   => $cambio,
                   ':saldo'    => $saldo,
                   ':estado'   => $estadoPago,
                   ':notas'    => $notas,
               ]);
            $ventaId = (int) $db->lastInsertId();

            if ($cotizacionId) {
                $upd = $db->prepare("UPDATE cotizaciones SET estado='convertida' WHERE id=:id AND tenant_id=:tid AND estado='abierta'");
                $upd->execute([':id'=>$cotizacionId, ':tid'=>$tid]);
                if ($upd->rowCount() !== 1) {
                    // Otra caja la convirtió un instante antes
                    throw new ErrorValidacion('Esa cotización acaba de ser convertida en otra venta.');
                }
            }

            if ($tipoPago === 'saldo_cliente') {
                $saldoAnterior = floatval($cliente['saldo_favor']);
                $saldoNuevo    = round($saldoAnterior - $total, 2);

                $db->prepare("UPDATE clientes SET saldo_favor=:s WHERE id=:id AND tenant_id=:tid")
                   ->execute([':s'=>$saldoNuevo, ':id'=>$clienteId, ':tid'=>$tid]);

                $db->prepare("INSERT INTO movimientos_saldo_cliente
                    (tenant_id,cliente_id,usuario_id,tipo,monto,saldo_anterior,saldo_nuevo,venta_id,notas)
                    VALUES(:tid,:cid,:uid,'consumo',:monto,:ant,:nvo,:vid,:notas)")
                   ->execute([
                       ':tid'=>$tid, ':cid'=>$clienteId, ':uid'=>$_SESSION['usuario_id'],
                       ':monto'=>$total, ':ant'=>$saldoAnterior, ':nvo'=>$saldoNuevo,
                       ':vid'=>$ventaId, ':notas'=>'Consumo por venta #'.$ventaId,
                   ]);
            }

            $this->registrarDetalleYDescontarStock($db, $tid, $ventaId, $carrito['items'], 'Venta #' . $ventaId);

            $db->commit();
            header('Location: ' . URL_BASE . '/?url=pos/ticket/' . $ventaId);
        } catch (ErrorValidacion $e) {
            $db->rollBack();
            $_SESSION['error'] = $e->getMessage();
            header('Location: ' . URL_BASE . '/?url=pos/venta');
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'No se pudo procesar la venta. Intentá de nuevo.');
            header('Location: ' . URL_BASE . '/?url=pos/venta');
        }
        exit;
    }

    // ── Guardar carrito actual como cotización (no toca stock) ──
    public function guardarCotizacion() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=pos/venta'); exit;
        }
        $tid = tenantActual();
        $clienteId = intval($_POST['cliente_id'] ?? 0) ?: null;
        $descuento = floatval($_POST['descuento'] ?? 0);
        $notas     = trim($_POST['notas'] ?? '');
        $items     = json_decode($_POST['items'] ?? '[]', true);

        // Misma validación que una venta real (cantidades, precios, mínimos, descuento), sin tocar stock
        try {
            $carrito = $this->validarCarrito($items, $tid, $descuento);
            $this->validarCliente($clienteId, $tid, 'efectivo');
        } catch (ErrorValidacion $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: ' . URL_BASE . '/?url=pos/venta'); exit;
        }
        $items     = $carrito['items'];
        $subtotal  = $carrito['subtotal'];
        $descuento = $carrito['descuento'];
        $total     = $carrito['total'];

        $db = $this->db();
        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO cotizaciones (tenant_id,cliente_id,usuario_id,estado,subtotal,descuento,total,notas" . (catalogoPorSucursal() ? ',sucursal_id' : '') . ")
                VALUES(:tid,:cid,:uid,'abierta',:sub,:desc,:total,:notas" . (catalogoPorSucursal() ? ',' . sucursalTrabajo() : '') . ")")
               ->execute([
                   ':tid'=>$tid, ':cid'=>$clienteId, ':uid'=>$_SESSION['usuario_id'],
                   ':sub'=>$subtotal, ':desc'=>$descuento, ':total'=>$total, ':notas'=>$notas,
               ]);
            $cotId = $db->lastInsertId();

            foreach ($items as $item) {
                $pid    = intval($item['id']);
                $cant   = floatval($item['cantidad']);
                $precio = floatval($item['precio']);
                $db->prepare("INSERT INTO detalle_cotizacion (tenant_id,cotizacion_id,producto_id,cantidad,precio_unitario,subtotal)
                    VALUES(:tid,:cid,:pid,:cant,:precio,:sub)")
                   ->execute([':tid'=>$tid, ':cid'=>$cotId, ':pid'=>$pid, ':cant'=>$cant, ':precio'=>$precio, ':sub'=>$cant*$precio]);
            }

            $db->commit();
            $_SESSION['exito'] = 'Cotización #' . $cotId . ' guardada correctamente. El stock no se vio afectado.';
            header('Location: ' . URL_BASE . '/?url=cotizaciones/ver/' . $cotId);
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'No se pudo guardar la cotización. Intentá de nuevo.');
            header('Location: ' . URL_BASE . '/?url=pos/venta');
        }
        exit;
    }

    // ── Ticket ────────────────────────────────────────────────
    public function ticket($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare(
            "SELECT v.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono, u.nombre AS cajero, ua.nombre AS anulada_por_nombre
             FROM ventas v
             LEFT JOIN clientes c ON c.id = v.cliente_id
             JOIN usuarios u ON u.id = v.usuario_id
             LEFT JOIN usuarios ua ON ua.id = v.anulada_por
             WHERE v.id=:id AND v.tenant_id=:tid");
        $stmt->execute([':id'=>$id, ':tid'=>$tid]);
        $venta = $stmt->fetch();
        if (!$venta || !$this->ventaEnVista((int) $venta['id'], $tid)) { header('Location: ' . URL_BASE . '/?url=pos/venta'); exit; }

        $stmt2 = $this->db()->prepare(
            "SELECT d.*, p.nombre AS producto_nombre, u.abreviatura AS unidad
             FROM detalle_venta d
             JOIN productos p ON p.id = d.producto_id
             JOIN unidades_medida u ON u.id = p.unidad_id
             WHERE d.venta_id=:id AND d.tenant_id=:tid");
        $stmt2->execute([':id'=>$id, ':tid'=>$tid]);
        $detalle      = $stmt2->fetchAll();

        $stmt3 = $this->db()->prepare(
            "SELECT p.*, u.nombre AS usuario_nombre
             FROM pagos_credito p
             JOIN usuarios u ON u.id = p.usuario_id
             WHERE p.venta_id=:id AND p.tenant_id=:tid
             ORDER BY p.creado_en ASC");
        $stmt3->execute([':id'=>$id, ':tid'=>$tid]);
        $abonos = $stmt3->fetchAll();

        $saldoClienteRestante = null;
        if ($venta['tipo_pago'] === 'saldo_cliente' && $venta['cliente_id']) {
            $stmt4 = $this->db()->prepare("SELECT saldo_favor FROM clientes WHERE id=:id AND tenant_id=:tid");
            $stmt4->execute([':id'=>$venta['cliente_id'], ':tid'=>$tid]);
            $saldoClienteRestante = $stmt4->fetchColumn();
        }

        // Direccion, telefono, NIT y logo del comprobante: los de la sucursal donde se hizo la venta (la de su caja)
        $sucursalTicket = 0;
        if (sucursalesTienenDatos() && !empty($venta['caja_sesion_id'])) {
            $sc = $this->db()->prepare("SELECT COALESCE(sucursal_id,0) FROM caja_sesiones WHERE id=:id AND tenant_id=:tid");
            $sc->execute([':id' => $venta['caja_sesion_id'], ':tid' => $tid]);
            $sucursalTicket = (int) $sc->fetchColumn();
        }

        $tituloPagina = 'Ticket #' . $id;
        include RAIZ . '/app/vistas/pos/ticket.php';
    }

    // ── PDF del historial de ventas ───────────────────────────
    public function pdfHistorial() {
        $tid = tenantActual();
        $desde = fechaGet('desde', date('Y-m-d'));
        $hasta = fechaGet('hasta', date('Y-m-d'));
        $esAdmin = $_SESSION['usuario_rol_id'] == 1;

        $sql = "SELECT v.*, c.nombre AS cliente_nombre, u.nombre AS cajero
             FROM ventas v
             LEFT JOIN clientes c ON c.id = v.cliente_id
             JOIN usuarios u ON u.id = v.usuario_id
             WHERE v.tenant_id=:tid AND v.anulada=0 AND DATE(v.creado_en) BETWEEN :desde AND :hasta";
        $params = [':tid'=>$tid, ':desde'=>$desde, ':hasta'=>$hasta];
        if (!$esAdmin) {
            $sql .= " AND v.usuario_id = :uid";
            $params[':uid'] = $_SESSION['usuario_id'];
        }
        if (esAdminConSucursales()) {   // el administrador ve las ventas de la sucursal que esta administrando (la de la caja)
            $sql .= " AND v.caja_sesion_id IN (SELECT id FROM caja_sesiones WHERE tenant_id=:tid_suc AND COALESCE(sucursal_id,0)=:suc)";
            $params[':tid_suc'] = $tid;
            $params[':suc']     = sucursalTrabajo();
        }
        $sql .= " ORDER BY v.creado_en DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $ventas = $stmt->fetchAll();

        $totalVentas = array_sum(array_column($ventas, 'total'));
        $totalPorTipo = ['efectivo'=>0,'tarjeta'=>0,'credito'=>0,'saldo_cliente'=>0];
        foreach ($ventas as $v) { $totalPorTipo[$v['tipo_pago']] += $v['total']; }

        $tituloPagina = $esAdmin ? 'Historial de Ventas' : 'Mi Historial de Ventas';
        ob_end_clean();
        include RAIZ . '/app/vistas/pos/pdf_historial.php';
        exit;
    }

    // ── Historial de ventas ───────────────────────────────────
    public function historial() {
        $tid = tenantActual();
        $desde = fechaGet('desde', date('Y-m-d'));
        $hasta = fechaGet('hasta', date('Y-m-d'));
        $esAdmin = $_SESSION['usuario_rol_id'] == 1;
        $verAnuladas = $esAdmin && ($_GET['anuladas'] ?? '') === '1';

        $sql = "SELECT v.*, c.nombre AS cliente_nombre, u.nombre AS cajero, ua.nombre AS anulada_por_nombre
             FROM ventas v
             LEFT JOIN clientes c ON c.id = v.cliente_id
             JOIN usuarios u ON u.id = v.usuario_id
             LEFT JOIN usuarios ua ON ua.id = v.anulada_por
             WHERE v.tenant_id=:tid AND v.anulada=" . ($verAnuladas ? '1' : '0') . " AND DATE(v.creado_en) BETWEEN :desde AND :hasta";
        $params = [':tid'=>$tid, ':desde'=>$desde, ':hasta'=>$hasta];
        if (!$esAdmin) {
            $sql .= " AND v.usuario_id = :uid";
            $params[':uid'] = $_SESSION['usuario_id'];
        }
        if (esAdminConSucursales()) {   // el administrador ve las ventas de la sucursal que esta administrando (la de la caja)
            $sql .= " AND v.caja_sesion_id IN (SELECT id FROM caja_sesiones WHERE tenant_id=:tid_suc AND COALESCE(sucursal_id,0)=:suc)";
            $params[':tid_suc'] = $tid;
            $params[':suc']     = sucursalTrabajo();
        }
        $sql .= " ORDER BY v.creado_en DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $ventas = $stmt->fetchAll();

        $totalVentas = array_sum(array_column($ventas, 'total'));
        $tituloPagina = $esAdmin ? 'Historial de Ventas' : 'Mi Historial de Ventas';
        include RAIZ . '/app/vistas/pos/historial.php';
    }

    // ── Abonar a crédito ──────────────────────────────────────
    public function abonar($id) {
        $tid = tenantActual();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $monto = floatval($_POST['monto'] ?? 0);
            $notas = trim($_POST['notas'] ?? '');
            if ($monto <= 0) {
                $_SESSION['error'] = 'El monto debe ser mayor a cero.';
                header('Location: ' . URL_BASE . '/?url=pos/ticket/' . $id); exit;
            }
            if (!is_finite($monto)) {
                $_SESSION['error'] = 'El monto no es válido.';
                header('Location: ' . URL_BASE . '/?url=pos/ticket/' . $id); exit;
            }
            $monto = round($monto, 2);
            $db = $this->db();

            // Transacción + bloqueo de la venta: dos abonos simultáneos no pueden pisarse
            $db->beginTransaction();
            try {
                $check = $db->prepare("SELECT saldo_pendiente, anulada FROM ventas WHERE id=:id AND tenant_id=:tid FOR UPDATE");
                $check->execute([':id'=>$id, ':tid'=>$tid]);
                $ventaCheck = $check->fetch();
                if (!$ventaCheck || !$this->ventaEnVista((int) $id, $tid)) {
                    throw new ErrorValidacion('Venta no encontrada.');
                }
                if ($ventaCheck['anulada']) {
                    throw new ErrorValidacion('No se puede abonar a una venta anulada.');
                }
                $saldoActual = floatval($ventaCheck['saldo_pendiente']);
                if ($saldoActual <= 0) {
                    throw new ErrorValidacion('Esta venta no tiene saldo pendiente.');
                }
                if ($monto > $saldoActual + 0.009) {
                    throw new ErrorValidacion('El abono (Q' . number_format($monto, 2) .
                        ') es mayor que el saldo pendiente (Q' . number_format($saldoActual, 2) . ').');
                }

                $db->prepare("INSERT INTO pagos_credito (tenant_id,venta_id,usuario_id,monto,notas) VALUES(:tid,:vid,:uid,:monto,:notas)")
                   ->execute([':tid'=>$tid,':vid'=>$id,':uid'=>$_SESSION['usuario_id'],':monto'=>$monto,':notas'=>$notas]);

                $nuevoSaldo = max(0, round($saldoActual - $monto, 2));
                $estado     = $nuevoSaldo <= 0 ? 'pagado' : 'pendiente';

                $db->prepare("UPDATE ventas SET saldo_pendiente=:saldo, estado_pago=:estado WHERE id=:id AND tenant_id=:tid")
                   ->execute([':saldo'=>$nuevoSaldo,':estado'=>$estado,':id'=>$id,':tid'=>$tid]);
                $db->commit();
            } catch (ErrorValidacion $e) {
                $db->rollBack();
                $_SESSION['error'] = $e->getMessage();
                header('Location: ' . URL_BASE . '/?url=pos/ticket/' . $id); exit;
            } catch (Throwable $e) {
                $db->rollBack();
                $_SESSION['error'] = mensajeError($e, 'No se pudo registrar el abono. Intentá de nuevo.');
                header('Location: ' . URL_BASE . '/?url=pos/ticket/' . $id); exit;
            }

            $_SESSION['exito'] = 'Abono registrado correctamente.';
            header('Location: ' . URL_BASE . '/?url=pos/ticket/' . $id); exit;
        }
        header('Location: ' . URL_BASE . '/?url=pos/ticket/' . $id); exit;
    }

    // ── Anular venta (solo admin) — devuelve stock, revierte saldo, deja auditoría ──
    public function anularVenta($id) {
        if ($_SESSION['usuario_rol_id'] != 1) {
            $_SESSION['error'] = 'Solo el administrador puede anular ventas.';
            header('Location: ' . URL_BASE . '/?url=pos/ticket/' . $id); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=pos/ticket/' . $id); exit;
        }

        $tid    = tenantActual();
        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') {
            $_SESSION['error'] = 'Tenés que indicar el motivo de la anulación.';
            header('Location: ' . URL_BASE . '/?url=pos/ticket/' . $id); exit;
        }

        $db = $this->db();
        $stmt = $db->prepare("SELECT * FROM ventas WHERE id=:id AND tenant_id=:tid");
        $stmt->execute([':id'=>$id, ':tid'=>$tid]);
        $venta = $stmt->fetch();

        if (!$venta) {
            $_SESSION['error'] = 'Venta no encontrada.';
            header('Location: ' . URL_BASE . '/?url=pos/historial'); exit;
        }
        if (!$this->ventaEnVista((int) $id, $tid)) {   // el administrador solo anula ventas de la sucursal que esta viendo
            $_SESSION['error'] = 'Venta no encontrada.';
            header('Location: ' . URL_BASE . '/?url=pos/historial'); exit;
        }
        if ($venta['anulada']) {
            $_SESSION['error'] = 'Esta venta ya estaba anulada.';
            header('Location: ' . URL_BASE . '/?url=pos/ticket/' . $id); exit;
        }

        $db->beginTransaction();
        try {
            // 0. Bloquear la venta y revisar de nuevo: evita anularla dos veces
            //    (doble clic o dos administradores al mismo tiempo) y devolver el stock doble
            $lock = $db->prepare("SELECT anulada FROM ventas WHERE id=:id AND tenant_id=:tid FOR UPDATE");
            $lock->execute([':id'=>$id, ':tid'=>$tid]);
            if ($lock->fetchColumn()) {
                throw new ErrorValidacion('Esta venta ya estaba anulada.');
            }

            // 1. Marcar la venta como anulada (soft delete — nunca se borra el registro)
            $db->prepare("UPDATE ventas SET
                anulada=1, anulada_por=:uid, anulada_motivo=:motivo, anulada_en=NOW()
                WHERE id=:id AND tenant_id=:tid")
               ->execute([':uid'=>$_SESSION['usuario_id'], ':motivo'=>$motivo, ':id'=>$id, ':tid'=>$tid]);

            // 2. Devolver cada producto al stock
            $detalle = $db->prepare("SELECT * FROM detalle_venta WHERE venta_id=:id AND tenant_id=:tid ORDER BY producto_id");
            $detalle->execute([':id'=>$id, ':tid'=>$tid]);
            foreach ($detalle->fetchAll() as $d) {
                $s = $db->prepare("SELECT cantidad FROM stock WHERE producto_id=:pid AND tenant_id=:tid FOR UPDATE");
                $s->execute([':pid'=>$d['producto_id'], ':tid'=>$tid]);
                $actual = floatval($s->fetchColumn());
                $nuevo  = $actual + floatval($d['cantidad']);

                $db->prepare("UPDATE stock SET cantidad=:n WHERE producto_id=:pid AND tenant_id=:tid")
                   ->execute([':n'=>$nuevo, ':pid'=>$d['producto_id'], ':tid'=>$tid]);

                $db->prepare("INSERT INTO movimientos_stock
                    (tenant_id,producto_id,usuario_id,tipo,origen,cantidad,stock_anterior,stock_nuevo,motivo,referencia_id)
                    VALUES(:tid,:pid,:uid,'entrada','devolucion',:cant,:ant,:nvo,:mot,:ref)")
                   ->execute([
                       ':tid'=>$tid, ':pid'=>$d['producto_id'], ':uid'=>$_SESSION['usuario_id'],
                       ':cant'=>$d['cantidad'], ':ant'=>$actual, ':nvo'=>$nuevo,
                       ':mot'=>"Anulación de venta #$id: $motivo", ':ref'=>$id
                   ]);
            }

            // 3. Si se pagó con saldo a favor del cliente, revertir el consumo
            if ($venta['tipo_pago'] === 'saldo_cliente' && $venta['cliente_id']) {
                $c = $db->prepare("SELECT saldo_favor FROM clientes WHERE id=:id AND tenant_id=:tid FOR UPDATE");
                $c->execute([':id'=>$venta['cliente_id'], ':tid'=>$tid]);
                $saldoAnterior = floatval($c->fetchColumn());
                $saldoNuevo    = $saldoAnterior + floatval($venta['total']);

                $db->prepare("UPDATE clientes SET saldo_favor=:s WHERE id=:id AND tenant_id=:tid")
                   ->execute([':s'=>$saldoNuevo, ':id'=>$venta['cliente_id'], ':tid'=>$tid]);

                $db->prepare("INSERT INTO movimientos_saldo_cliente
                    (tenant_id,cliente_id,usuario_id,tipo,monto,saldo_anterior,saldo_nuevo,venta_id,notas)
                    VALUES(:tid,:cid,:uid,'ajuste',:monto,:ant,:nvo,:vid,:notas)")
                   ->execute([
                       ':tid'=>$tid, ':cid'=>$venta['cliente_id'], ':uid'=>$_SESSION['usuario_id'],
                       ':monto'=>$venta['total'], ':ant'=>$saldoAnterior, ':nvo'=>$saldoNuevo,
                       ':vid'=>$id, ':notas'=>"Reintegro por anulación de venta #$id: $motivo",
                   ]);
            }

            $db->commit();
            $_SESSION['exito'] = 'Venta anulada correctamente. El stock fue devuelto al inventario.';
        } catch (ErrorValidacion $e) {
            $db->rollBack();
            $_SESSION['error'] = $e->getMessage();
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'No se pudo anular la venta. Intentá de nuevo.');
        }
        header('Location: ' . URL_BASE . '/?url=pos/ticket/' . $id); exit;
    }

    // ── Historial de sesiones de caja ─────────────────────────
    public function historialCaja() {
        $tid = tenantActual();
        $esAdmin = $_SESSION['usuario_rol_id'] == 1;
        $desde = fechaGet('desde', date('Y-m-01'));
        $hasta = fechaGet('hasta', date('Y-m-d'));

        $sql = "SELECT cs.*, u.nombre AS usuario_nombre,
                COALESCE((SELECT SUM(v.total) FROM ventas v WHERE v.caja_sesion_id=cs.id AND v.tipo_pago='efectivo' AND v.anulada=0), 0) AS total_efectivo,
                COALESCE((SELECT SUM(v.total) FROM ventas v WHERE v.caja_sesion_id=cs.id AND v.tipo_pago='tarjeta' AND v.anulada=0), 0) AS total_tarjeta,
                COALESCE((SELECT SUM(v.total) FROM ventas v WHERE v.caja_sesion_id=cs.id AND v.tipo_pago='credito' AND v.anulada=0), 0) AS total_credito,
                COALESCE((SELECT COUNT(*) FROM ventas v WHERE v.caja_sesion_id=cs.id AND v.anulada=0), 0) AS total_ventas
             FROM caja_sesiones cs
             JOIN usuarios u ON u.id = cs.usuario_id
             WHERE cs.tenant_id=:tid AND DATE(cs.abierta_en) BETWEEN :desde AND :hasta";
        $params = [':tid'=>$tid, ':desde'=>$desde, ':hasta'=>$hasta];
        if (!$esAdmin) {
            $sql .= " AND cs.usuario_id = :uid";
            $params[':uid'] = $_SESSION['usuario_id'];
        }
        if (esAdminConSucursales()) {   // el administrador ve las cajas de la sucursal que esta administrando
            $sql .= " AND COALESCE(cs.sucursal_id,0) = :suc";
            $params[':suc'] = sucursalTrabajo();
        }
        $sql .= " ORDER BY cs.abierta_en DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $sesiones = $stmt->fetchAll();

        $tituloPagina = $esAdmin ? 'Historial de Caja' : 'Mi Historial de Caja';
        include RAIZ . '/app/vistas/pos/historial_caja.php';
    }

    // ── PDF historial de caja ─────────────────────────────────
    public function pdfHistorialCaja() {
        $tid = tenantActual();
        $esAdmin = $_SESSION['usuario_rol_id'] == 1;
        $desde = fechaGet('desde', date('Y-m-01'));
        $hasta = fechaGet('hasta', date('Y-m-d'));

        $sql = "SELECT cs.*, u.nombre AS usuario_nombre,
                COALESCE((SELECT SUM(v.total) FROM ventas v WHERE v.caja_sesion_id=cs.id AND v.tipo_pago='efectivo' AND v.anulada=0), 0) AS total_efectivo,
                COALESCE((SELECT SUM(v.total) FROM ventas v WHERE v.caja_sesion_id=cs.id AND v.tipo_pago='tarjeta' AND v.anulada=0), 0) AS total_tarjeta,
                COALESCE((SELECT SUM(v.total) FROM ventas v WHERE v.caja_sesion_id=cs.id AND v.tipo_pago='credito' AND v.anulada=0), 0) AS total_credito,
                COALESCE((SELECT COUNT(*) FROM ventas v WHERE v.caja_sesion_id=cs.id AND v.anulada=0), 0) AS total_ventas
             FROM caja_sesiones cs
             JOIN usuarios u ON u.id = cs.usuario_id
             WHERE cs.tenant_id=:tid AND DATE(cs.abierta_en) BETWEEN :desde AND :hasta";
        $params = [':tid'=>$tid, ':desde'=>$desde, ':hasta'=>$hasta];
        if (!$esAdmin) {
            $sql .= " AND cs.usuario_id = :uid";
            $params[':uid'] = $_SESSION['usuario_id'];
        }
        if (esAdminConSucursales()) {   // el administrador ve las cajas de la sucursal que esta administrando
            $sql .= " AND COALESCE(cs.sucursal_id,0) = :suc";
            $params[':suc'] = sucursalTrabajo();
        }
        $sql .= " ORDER BY cs.abierta_en DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $sesiones = $stmt->fetchAll();

        $tituloPagina = $esAdmin ? 'Historial de Caja' : 'Mi Historial de Caja';
        ob_end_clean();
        include RAIZ . '/app/vistas/pos/pdf_historial_caja.php';
        exit;
    }

    // ── Ping para verificar conexión real ─────────────────────
    public function ping() {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'hora' => date('H:i:s')]);
        exit;
    }

    // ── Procesar venta sincronizada desde offline ─────────────
    public function procesarVentaOffline() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['exito'=>false,'mensaje'=>'Método no permitido']); exit;
        }

        $tid = tenantActual();
        // La venta offline se armo con el catalogo de UNA sucursal: se registra en la caja abierta de ESA sucursal,
        // aunque el administrador este viendo otra al sincronizar. Las ventas guardadas antes de esta version no
        // traen sucursal ('') y usan la caja activa de siempre.
        $sucOffline = (catalogoPorSucursal() && isset($_POST['sucursal_id']) && $_POST['sucursal_id'] !== '')
            ? intval($_POST['sucursal_id']) : null;
        $caja = $this->cajaActiva($sucOffline);
        if (!$caja) {
            echo json_encode(['exito'=>false,'mensaje'=>'No hay caja abierta en el servidor. Abrí caja antes de sincronizar.']);
            exit;
        }

        $clienteId  = intval($_POST['cliente_id'] ?? 0) ?: null;
        $tipoPago   = $_POST['tipo_pago'] ?? 'efectivo';
        $recibido   = floatval($_POST['monto_recibido'] ?? 0);
        $descuento  = floatval($_POST['descuento'] ?? 0);
        $notas      = trim($_POST['notas'] ?? '');
        $items      = json_decode($_POST['items'] ?? '[]', true);
        $offlineUuid = trim($_POST['offline_uuid'] ?? '');
        $this->sucursalCatalogo = $this->sucursalDeCaja($caja);   // productos y clientes de la venta: los de su caja

        $db = $this->db();

        // ── Evitar ventas duplicadas ──
        // Si la respuesta se perdió o dos pestañas sincronizaron a la vez, la misma venta
        // podría llegar dos veces. Con el identificador único la segunda se ignora.
        $usaUuid = $this->ventasTieneOfflineUuid();
        if ($usaUuid) {
            if (!preg_match('/^[A-Za-z0-9:._-]{8,64}$/', $offlineUuid)) {
                echo json_encode(['exito'=>false,'mensaje'=>'Venta offline sin identificador válido. Recargá la página e intentá de nuevo.']); exit;
            }
            $dup = $db->prepare("SELECT id FROM ventas WHERE tenant_id=:tid AND offline_uuid=:u LIMIT 1");
            $dup->execute([':tid'=>$tid, ':u'=>$offlineUuid]);
            if ($idExistente = $dup->fetchColumn()) {
                echo json_encode(['exito'=>true, 'venta_id'=>(int)$idExistente, 'duplicada'=>true]); exit;
            }
        }

        try {
            if (!in_array($tipoPago, ['efectivo', 'tarjeta'], true)) {
                throw new ErrorValidacion('Solo las ventas en efectivo o tarjeta se pueden procesar offline.');
            }
            if (!is_finite($recibido) || $recibido < 0) {
                throw new ErrorValidacion('El monto recibido no es válido.');
            }
            $carrito = $this->validarCarrito($items, $tid, $descuento);
            $this->validarCliente($clienteId, $tid, $tipoPago);
        } catch (ErrorValidacion $e) {
            echo json_encode(['exito'=>false, 'mensaje'=>$e->getMessage()]); exit;
        }

        $subtotal = $carrito['subtotal'];
        $descuento = $carrito['descuento'];
        $total    = $carrito['total'];
        $cambio   = $tipoPago === 'efectivo' ? max(0, round($recibido - $total, 2)) : 0;

        $db->beginTransaction();
        try {
            $columnas = 'tenant_id,caja_sesion_id,cliente_id,usuario_id,subtotal,descuento,total,tipo_pago,monto_recibido,cambio,saldo_pendiente,estado_pago,notas,origen_registro';
            $valores  = ":tid,:cid,:cliid,:uid,:sub,:desc,:total,:tpago,:recibido,:cambio,0,'pagado',:notas,'offline_sincronizada'";
            $params   = [
                ':tid'=>$tid, ':cid'=>$caja['id'], ':cliid'=>$clienteId, ':uid'=>$_SESSION['usuario_id'],
                ':sub'=>$subtotal, ':desc'=>$descuento, ':total'=>$total,
                ':tpago'=>$tipoPago, ':recibido'=>$recibido, ':cambio'=>$cambio, ':notas'=>$notas,
            ];
            if ($usaUuid) {
                $columnas .= ',offline_uuid';
                $valores  .= ',:uuid';
                $params[':uuid'] = $offlineUuid;
            }
            $db->prepare("INSERT INTO ventas ($columnas) VALUES($valores)")->execute($params);
            $ventaId = (int) $db->lastInsertId();

            $this->registrarDetalleYDescontarStock($db, $tid, $ventaId, $carrito['items'],
                'Venta #' . $ventaId . ' (sincronizada offline)', 'Esta venta requiere revisión manual.');

            $db->commit();
            echo json_encode(['exito'=>true, 'venta_id'=>$ventaId]);
        } catch (ErrorValidacion $e) {
            $db->rollBack();
            echo json_encode(['exito'=>false, 'mensaje'=>$e->getMessage()]);
        } catch (PDOException $e) {
            $db->rollBack();
            if ($usaUuid && $e->getCode() === '23000' && str_contains($e->getMessage(), 'offline_uuid')) {
                // Otra pestaña la guardó en el mismo instante: ya está registrada
                echo json_encode(['exito'=>true, 'duplicada'=>true]);
            } else {
                echo json_encode(['exito'=>false, 'mensaje'=>mensajeError($e, 'No se pudo sincronizar la venta. Se reintentará.')]);
            }
        } catch (Throwable $e) {
            $db->rollBack();
            echo json_encode(['exito'=>false, 'mensaje'=>mensajeError($e, 'No se pudo sincronizar la venta. Se reintentará.')]);
        }
        exit;
    }

    // ══════════════════ HELPERS DE VALIDACIÓN (servidor) ══════════════════
    // El navegador puede equivocarse o ser manipulado; el servidor es quien decide.

    /**
     * Revisa el carrito completo y devuelve los totales calculados en el servidor.
     * Lanza ErrorValidacion con un mensaje apto para el usuario.
     */
    private function validarCarrito($items, int $tid, float $descuento): array {
        if (!is_array($items) || count($items) === 0) {
            throw new ErrorValidacion('El carrito está vacío.');
        }
        if (count($items) > 500) {
            throw new ErrorValidacion('El carrito tiene demasiados productos.');
        }
        if (!is_finite($descuento) || $descuento < 0) {
            throw new ErrorValidacion('El descuento no puede ser negativo.');
        }

        $stmtP = $this->db()->prepare(
            "SELECT nombre, precio_minimo FROM productos WHERE id=:pid AND tenant_id=:tid AND activo=1"
            . sqlSucursalId($this->sucursalCatalogo ?? sucursalTrabajo()));

        $limpios     = [];
        $subtotal    = 0.0;
        $minimoTotal = 0.0;
        foreach ($items as $item) {
            $pid    = intval($item['id'] ?? 0);
            $cant   = floatval($item['cantidad'] ?? 0);
            $precio = floatval($item['precio'] ?? 0);

            if ($pid <= 0) {
                throw new ErrorValidacion('Uno de los productos del carrito no es válido.');
            }
            $stmtP->execute([':pid' => $pid, ':tid' => $tid]);
            $prod = $stmtP->fetch();
            if (!$prod) {
                throw new ErrorValidacion('Uno de los productos del carrito ya no existe.');
            }
            if (!is_finite($cant) || $cant <= 0 || $cant > 1000000) {
                throw new ErrorValidacion("La cantidad de '{$prod['nombre']}' debe ser mayor a cero.");
            }
            if (!is_finite($precio) || $precio <= 0) {
                throw new ErrorValidacion("El precio de '{$prod['nombre']}' no es válido.");
            }
            if ($precio < floatval($prod['precio_minimo']) - 0.001) {
                throw new ErrorValidacion("El precio de '{$prod['nombre']}' (Q" . number_format($precio, 2) .
                    ") está por debajo del mínimo autorizado (Q" . number_format($prod['precio_minimo'], 2) . ").");
            }

            $limpios[]    = ['id' => $pid, 'cantidad' => $cant, 'precio' => $precio, 'nombre' => $prod['nombre']];
            $subtotal    += $cant * $precio;
            $minimoTotal += floatval($prod['precio_minimo']) * $cant;
        }

        $subtotal = round($subtotal, 2);
        if ($descuento > $subtotal) {
            throw new ErrorValidacion('El descuento no puede ser mayor que el subtotal.');
        }
        $total = round($subtotal - $descuento, 2);

        // El descuento global no puede bajar el total por debajo de la suma de los mínimos autorizados
        if ($total < $minimoTotal - 0.01) {
            throw new ErrorValidacion("El descuento aplicado deja el total en Q" . number_format($total, 2) .
                ", por debajo del mínimo permitido para este carrito (Q" . number_format($minimoTotal, 2) . ").");
        }

        return ['items' => $limpios, 'subtotal' => $subtotal, 'descuento' => round($descuento, 2), 'total' => $total];
    }

    /** El cliente (si viene) debe ser de este negocio; crédito y saldo lo exigen. */
    private function validarCliente(?int $clienteId, int $tid, string $tipoPago): void {
        if (!planPermiteCredito() && in_array($tipoPago, ['credito', 'saldo_cliente'], true)) {
            throw new ErrorValidacion('Tu plan no incluye ventas a crédito ni con saldo de clientes.');
        }
        if (in_array($tipoPago, ['credito', 'saldo_cliente'], true) && !$clienteId) {
            throw new ErrorValidacion($tipoPago === 'credito'
                ? 'Seleccioná un cliente para vender al crédito.'
                : 'Seleccioná un cliente para pagar con saldo a favor.');
        }
        if (!$clienteId) return;
        $stmt = $this->db()->prepare("SELECT id FROM clientes WHERE id=:id AND tenant_id=:tid"
            . sqlSucursalId($this->sucursalCatalogo ?? sucursalTrabajo()));
        $stmt->execute([':id' => $clienteId, ':tid' => $tid]);
        if (!$stmt->fetch()) {
            throw new ErrorValidacion('Cliente no encontrado.');
        }
    }

    /**
     * Inserta el detalle y descuenta el stock bloqueando cada fila (SELECT ... FOR UPDATE).
     * Si dos cajas venden el último producto al mismo tiempo, la segunda espera a que
     * termine la primera y luego ve el stock real, así nunca queda en negativo.
     * Debe llamarse DENTRO de una transacción.
     */
    private function registrarDetalleYDescontarStock(PDO $db, int $tid, int $ventaId, array $items, string $motivo, string $sufijoError = ''): void {
        // Cada linea guarda el costo que tenia el producto en este momento (detalle_venta.costo_unitario), para que
        // la ganancia de los informes pasados no cambie cuando el costo del producto suba o baje.
        $conCosto   = ventasGuardanCosto();
        $insDetalle = $db->prepare("INSERT INTO detalle_venta (tenant_id,venta_id,producto_id,cantidad,precio_unitario,subtotal" . ($conCosto ? ',costo_unitario' : '') . ")
            VALUES(:tid,:vid,:pid,:cant,:precio,:sub" . ($conCosto ? ',(SELECT precio_compra FROM productos WHERE id=:pid2 AND tenant_id=:tid2)' : '') . ")");
        foreach ($items as $item) {
            $params = [
                ':tid'=>$tid, ':vid'=>$ventaId, ':pid'=>$item['id'], ':cant'=>$item['cantidad'],
                ':precio'=>$item['precio'], ':sub'=>round($item['cantidad'] * $item['precio'], 2),
            ];
            if ($conCosto) { $params[':pid2'] = $item['id']; $params[':tid2'] = $tid; }
            $insDetalle->execute($params);
        }

        // Bloquear siempre en el mismo orden (por ID) evita bloqueos cruzados entre dos ventas
        usort($items, fn($a, $b) => $a['id'] <=> $b['id']);

        $selStock = $db->prepare("SELECT cantidad FROM stock WHERE producto_id=:pid AND tenant_id=:tid FOR UPDATE");
        $updStock = $db->prepare("UPDATE stock SET cantidad=:n WHERE producto_id=:pid AND tenant_id=:tid");
        $insMov   = $db->prepare("INSERT INTO movimientos_stock
            (tenant_id,producto_id,usuario_id,tipo,origen,cantidad,stock_anterior,stock_nuevo,motivo,referencia_id)
            VALUES(:tid,:pid,:uid,'salida','venta',:cant,:ant,:nvo,:mot,:ref)");

        foreach ($items as $item) {
            $selStock->execute([':pid'=>$item['id'], ':tid'=>$tid]);
            $actual = $selStock->fetchColumn();
            $actual = $actual === false ? 0.0 : floatval($actual);
            // Si el mismo producto viene en dos líneas, la segunda ya ve el stock rebajado
            if ($item['cantidad'] > $actual + 0.0001) {
                throw new ErrorValidacion("Stock insuficiente para '{$item['nombre']}'. Disponible: " .
                    rtrim(rtrim(number_format($actual, 2, '.', ''), '0'), '.') . ", solicitado: " .
                    rtrim(rtrim(number_format($item['cantidad'], 2, '.', ''), '0'), '.') . '.' .
                    ($sufijoError ? ' ' . $sufijoError : ''));
            }
            $nuevo = round($actual - $item['cantidad'], 4);

            $updStock->execute([':n'=>$nuevo, ':pid'=>$item['id'], ':tid'=>$tid]);
            $insMov->execute([
                ':tid'=>$tid, ':pid'=>$item['id'], ':uid'=>$_SESSION['usuario_id'],
                ':cant'=>$item['cantidad'], ':ant'=>$actual, ':nvo'=>$nuevo,
                ':mot'=>$motivo, ':ref'=>$ventaId,
            ]);
        }
    }

    /** ¿Ya se ejecutó la migración que agrega ventas.offline_uuid? */
    private function ventasTieneOfflineUuid(): bool {
        static $tiene = null;
        if ($tiene === null) {
            $tiene = (bool) $this->db()->query("SHOW COLUMNS FROM ventas LIKE 'offline_uuid'")->fetch();
        }
        return $tiene;
    }

    // ── Helper: caja activa ───────────────────────────────────
    // Cajero/bodeguero: su unica caja abierta, como siempre.
    // Administrador de un negocio con sucursales: una caja abierta POR SUCURSAL (puede dejar abierta la de la
    // Principal, ir a la otra sucursal y abrir una segunda); cuenta la de la sucursal que esta administrando.
    // En caja_sesiones la Principal se guarda como NULL, de ahi el COALESCE.
    // Con $sucursal (ventas sincronizadas desde offline) se busca la caja abierta de ESA sucursal, aunque el
    // administrador este viendo otra en ese momento. 0 = Principal.
    private function cajaActiva(?int $sucursal = null) {
        $tid    = tenantActual();
        $sql    = "SELECT * FROM caja_sesiones WHERE usuario_id=:uid AND tenant_id=:tid AND estado='abierta'";
        $params = [':uid' => $_SESSION['usuario_id'], ':tid' => $tid];
        if ($sucursal !== null || esAdminConSucursales()) {
            $sql .= " AND COALESCE(sucursal_id,0) = :suc";
            $params[':suc'] = $sucursal ?? sucursalTrabajo();
        }
        $stmt = $this->db()->prepare($sql . " ORDER BY id DESC LIMIT 1");
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /** La tabla caja_sesiones ya tiene la columna sucursal_id? (migracion de sucursales del 28/10) */
    private function cajaTieneSucursal(): bool {
        static $tiene = null;
        if ($tiene === null) {
            $tiene = (bool) $this->db()->query("SHOW COLUMNS FROM caja_sesiones LIKE 'sucursal_id'")->fetch();
        }
        return $tiene;
    }

    // Sucursal de una caja (0 = Principal; en caja_sesiones la Principal se guarda como NULL).
    private function sucursalDeCaja($caja): int {
        return (int) ($caja['sucursal_id'] ?? 0);
    }

    // El administrador de un negocio con sucursales solo abre, abona o anula las ventas de la sucursal que esta
    // viendo (la sucursal de una venta es la de su caja). Los demas usuarios: como siempre.
    private function ventaEnVista(int $ventaId, int $tid): bool {
        if (!esAdminConSucursales()) return true;
        $st = $this->db()->prepare(
            "SELECT 1 FROM ventas v JOIN caja_sesiones cs ON cs.id = v.caja_sesion_id
             WHERE v.id=:id AND v.tenant_id=:t AND COALESCE(cs.sucursal_id,0) = :suc");
        $st->execute([':id' => $ventaId, ':t' => $tid, ':suc' => sucursalTrabajo()]);
        return (bool) $st->fetchColumn();
    }
}