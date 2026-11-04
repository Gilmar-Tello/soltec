<?php

class ReservaControlador {

    private function db() { return obtenerConexion(); }

    private function tenantId(): int {
        return (int) $_SESSION['tenant_id'];
    }

    // ── Aislamiento por sucursal ───────────────────────────────
    // El listado ya filtraba por sucursal, pero las acciones por ID
    // (detalle, entregar, cancelar...) solo filtraban por tenant: un
    // trabajador podia operar reservas de otra sucursal escribiendo
    // la URL a mano. Esto lo corta.
    private function exigirAccesoReserva($id): void {
        if (!esTrabajador() || empty($_SESSION['sucursal_id'])) return;

        // Puede operarla la sucursal que la vendio (p. ej. la vitrina)
        // y la que la despacha (la bodega, donde estan los repartidores).
        $stmt = $this->db()->prepare(
            "SELECT 1 FROM reservas
             WHERE id = :id AND tenant_id = :tid
               AND (sucursal_id = :sid1 OR sucursal_despacho_id = :sid2)"
        );
        $stmt->execute([
            ':id'   => $id,
            ':tid'  => $this->tenantId(),
            ':sid1' => (int) $_SESSION['sucursal_id'],
            ':sid2' => (int) $_SESSION['sucursal_id'],
        ]);

        if (!$stmt->fetch()) {
            $_SESSION['error'] = 'Esa reserva pertenece a otra sucursal.';
            header('Location: ' . URL_BASE . '/?url=reservas/index');
            exit;
        }
    }

    // ── Listado ────────────────────────────────────────────────
    public function index() {
        $filtro = $_GET['estado'] ?? '';
        $sql = "SELECT r.*, c.nombre_completo, c.telefono, s.nombre AS sucursal_nombre
                FROM reservas r
                INNER JOIN clientes c ON c.id = r.cliente_id
                INNER JOIN sucursales s ON s.id = r.sucursal_id
                WHERE r.tenant_id = :tid";
        $params = [':tid' => $this->tenantId()];

        // Trabajador con sucursal asignada: ve las que vendio su sucursal
        // y las que le toca despachar (reservas hechas en una vitrina)
        if (esTrabajador() && !empty($_SESSION['sucursal_id'])) {
            $sql .= " AND (r.sucursal_id = :sid1 OR r.sucursal_despacho_id = :sid2)";
            $params[':sid1'] = (int) $_SESSION['sucursal_id'];
            $params[':sid2'] = (int) $_SESSION['sucursal_id'];
        }

        if ($filtro) {
            $sql .= " AND r.estado = :estado";
            $params[':estado'] = $filtro;
        }

        $sql .= " ORDER BY r.fecha_reservacion DESC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $reservas = $stmt->fetchAll();

        $tituloPagina = 'Reservas';
        include RAIZ . '/app/vistas/reservas/index.php';
    }

    // ── Formulario nueva reserva ───────────────────────────────
    public function nueva() {
        $sucursales = $this->obtenerSucursales();

        if (empty($sucursales)) {
            $_SESSION['error'] = 'Primero creá al menos una sucursal.';
            header('Location: ' . URL_BASE . '/?url=sucursales/index'); exit;
        }

        // Trabajador con sucursal asignada: solo ve y opera la suya
        $sucursalFija = null;
        if (esTrabajador() && !empty($_SESSION['sucursal_id'])) {
            $sucursalFija = (int) $_SESSION['sucursal_id'];
            // Filtra la lista para mostrar solo la suya
            $sucursales = array_filter($sucursales, fn($s) => $s['id'] === $sucursalFija);
            $sucursales = array_values($sucursales);

            if (empty($sucursales)) {
                $_SESSION['error'] = 'Tu sucursal asignada no está activa. Contactá al administrador.';
                header('Location: ' . URL_BASE . '/?url=dashboard/index'); exit;
            }
        }

        $tituloPagina = 'Nueva Reserva';
        include RAIZ . '/app/vistas/reservas/form.php';
    }

    // ── Guardar nueva reserva ──────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=reservas/index'); exit;
        }

        $clienteId    = intval($_POST['cliente_id'] ?? 0);
        $sucursalId   = intval($_POST['sucursal_id'] ?? 0);
        $fechaEntrega = fechaISO($_POST['fecha_entrega'] ?? '');
        $horaEntrega  = horaISO($_POST['hora_entrega'] ?? '');
        $fechaUso     = trim($_POST['fecha_uso'] ?? '');
        $horaUso      = horaISO($_POST['hora_uso'] ?? '');
        $fechaRecolec = trim($_POST['fecha_recoleccion'] ?? '') ?: null;
        $horaRecolec  = horaISO($_POST['hora_recoleccion'] ?? '');
        $productos    = $_POST['productos'] ?? [];
        $descuento    = max(0, floatval($_POST['descuento'] ?? 0));
        $direccion    = mb_substr(trim($_POST['direccion_entrega'] ?? ''), 0, 255);

        // Costo inicial: un cargo extra de esta reserva (ubicación, flete...).
        // Sin monto no hay cargo, y con monto hace falta decir de qué es.
        $costoDesc  = mb_substr(trim($_POST['costo_inicial_desc'] ?? ''), 0, 150);
        $costoMonto = min(99999999.99, round(max(0, floatval($_POST['costo_inicial_monto'] ?? 0)), 2));
        if ($costoMonto <= 0) $costoDesc = '';

        // Trabajador solo puede operar su sucursal asignada
        if (esTrabajador() && !empty($_SESSION['sucursal_id'])) {
            if ($sucursalId !== (int) $_SESSION['sucursal_id']) {
                $_SESSION['error'] = 'No podés crear reservas de otras sucursales.';
                header('Location: ' . URL_BASE . '/?url=reservas/nueva'); exit;
            }
        }

        if (!$clienteId || !$sucursalId || !$fechaEntrega || !$fechaUso || !$fechaRecolec || empty($productos)) {
            $_SESSION['error'] = 'Cliente, sucursal, fechas de entrega, uso y recolección, y al menos un producto son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=reservas/nueva'); exit;
        }

        // ── Fechas ─────────────────────────────────────────────
        // Se guardan tal cual vienen del calendario (AAAA-MM-DD),
        // sin pasarlas por strtotime ni por ninguna conversion de
        // zona horaria: asi la fecha que eligio el usuario es
        // exactamente la que queda en la base y la que se imprime.
        $fechaUso     = fechaISO($fechaUso);
        $fechaRecolec = fechaISO($fechaRecolec);

        if (!esFechaValida($fechaUso)) {
            $_SESSION['error'] = 'La fecha de uso no es válida.';
            header('Location: ' . URL_BASE . '/?url=reservas/nueva'); exit;
        }

        if (diasEntreFechas(hoyGt(), $fechaUso) < 0) {
            $_SESSION['error'] = 'La fecha de uso no puede ser anterior a hoy (' . fechaCorta(hoyGt()) . ').';
            header('Location: ' . URL_BASE . '/?url=reservas/nueva'); exit;
        }

        // La recolección es obligatoria: sin ella no se sabe hasta
        // cuándo queda ocupado el producto.
        if (!esFechaValida($fechaRecolec)) {
            $_SESSION['error'] = 'La fecha de recolección no es válida.';
            header('Location: ' . URL_BASE . '/?url=reservas/nueva'); exit;
        }
        if (diasEntreFechas($fechaUso, $fechaRecolec) < 0) {
            $_SESSION['error'] = 'La recolección (' . fechaCorta($fechaRecolec) .
                                 ') no puede ser antes de la fecha de uso (' . fechaCorta($fechaUso) . ').';
            header('Location: ' . URL_BASE . '/?url=reservas/nueva'); exit;
        }

        // ── Fechas y horas: entrega → uso → recolección ────────
        // Las tres llevan hora. Pueden caer el mismo día (entrega por la
        // mañana, uso por la tarde, recolección por la noche): por eso la
        // hora decide el orden, no solo la fecha.
        $errorCrono = $this->validarCronograma(
            $fechaEntrega, $horaEntrega, $fechaUso, $horaUso, $fechaRecolec, $horaRecolec, true, true
        );
        if ($errorCrono) {
            $_SESSION['error'] = $errorCrono;
            header('Location: ' . URL_BASE . '/?url=reservas/nueva'); exit;
        }
        if ($costoMonto > 0 && $costoDesc === '') {
            $_SESSION['error'] = 'Escribí la descripción del costo inicial (por ejemplo: flete a aldea).';
            header('Location: ' . URL_BASE . '/?url=reservas/nueva'); exit;
        }
        $momentoEntrega = momentoISO($fechaEntrega, $horaEntrega, '00:00:00');
        $momentoRecolec = momentoISO($fechaRecolec, $horaRecolec, '23:59:59');

        // De donde sale el producto: la misma sucursal, o su bodega si es vitrina
        $sucursalStock = sucursalDeStock($sucursalId, $this->tenantId());
        if (!$sucursalStock) {
            $_SESSION['error'] = 'Esta sucursal es una vitrina sin bodega asignada. Configurala en Sucursales.';
            header('Location: ' . URL_BASE . '/?url=reservas/nueva'); exit;
        }

        $db     = $this->db();
        $lineas = [];
        $subtotal = 0;

        foreach ($productos as $linea) {
            $invId    = intval($linea['inventario_id'] ?? 0);
            $cantidad = intval($linea['cantidad'] ?? 0);
            $dias     = max(1, intval($linea['dias'] ?? 1));
            $tipo     = in_array($linea['tipo'] ?? '', ['dia', 'evento']) ? $linea['tipo'] : 'evento';
            if (!$invId || $cantidad <= 0) continue;

            // El producto TIENE que ser de la sucursal que despacha.
            // Sin el filtro de sucursal se podia reservar en una sucursal
            // stock que fisicamente estaba en otra.
            $stmt = $db->prepare(
                "SELECT id, nombre, precio_alquiler, cantidad_disponible
                 FROM inventario
                 WHERE id = :id AND tenant_id = :tid AND sucursal_id = :sid"
            );
            $stmt->execute([
                ':id'  => $invId,
                ':tid' => $this->tenantId(),
                ':sid' => $sucursalStock,
            ]);
            $prod = $stmt->fetch();

            if (!$prod) continue;

            // La disponibilidad se revisa por fechas más abajo, dentro de
            // la transacción (si el producto viene repetido, se suma).

            // Subtotal: si es por día multiplica los días
            $lineaSubtotal = $cantidad * $prod['precio_alquiler'] * ($tipo === 'dia' ? $dias : 1);
            $subtotal += $lineaSubtotal;
            $lineas[] = [
                'inventario_id'  => $invId,
                'nombre'         => $prod['nombre'],
                'cantidad'       => $cantidad,
                'dias'           => $dias,
                'tipo_precio'    => $tipo,
                'precio_unitario'=> $prod['precio_alquiler'],
                'subtotal'       => $lineaSubtotal,
            ];
        }

        if (empty($lineas)) {
            $_SESSION['error'] = 'Agregá al menos un producto con cantidad mayor a cero.';
            header('Location: ' . URL_BASE . '/?url=reservas/nueva'); exit;
        }

        // Descuento no puede superar el subtotal
        $descuento = min($descuento, $subtotal);
        // El costo inicial va aparte del descuento (el descuento solo
        // rebaja los productos) y queda DENTRO del total, para que el
        // listado, los reportes y el comprobante lo incluyan solos.
        $total = $subtotal - $descuento + $costoMonto;

        // Direccion de entrega: si no escribieron una, se usa la del
        // cliente. Queda guardada en la reserva, asi que si despues
        // el cliente cambia de casa, los comprobantes viejos siguen
        // diciendo a donde se entrego de verdad.
        if ($direccion === '') {
            $stmt = $db->prepare("SELECT direccion FROM clientes WHERE id = :id AND tenant_id = :tid");
            $stmt->execute([':id' => $clienteId, ':tid' => $this->tenantId()]);
            $direccion = (string) ($stmt->fetchColumn() ?: '');
        }

        $db->beginTransaction();
        try {
            // ¿Alcanza para esas fechas? Se revisa con los productos
            // bloqueados (FOR UPDATE): si dos personas reservan las
            // mismas sillas al mismo tiempo, la segunda espera y ve lo
            // que apartó la primera. Así no se reserva de más.
            $this->exigirUnidadesLibres($lineas, $momentoEntrega, $momentoRecolec, 0);

            $stmt = $db->prepare(
                "INSERT INTO reservas (tenant_id, sucursal_id, sucursal_despacho_id, cliente_id, usuario_id,
                    fecha_reservacion, fecha_entrega, hora_entrega, fecha_uso, hora_uso,
                    fecha_recoleccion_programada, hora_recoleccion,
                    direccion_entrega, estado, total, descuento, costo_inicial_desc, costo_inicial_monto)
                 VALUES (:tid, :suc, :desp, :cli, :usr, NOW(), :ent, :hent, :uso, :huso,
                    :rec, :hrec, :dir, 'reservado', :total, :desc, :cdesc, :cmonto)"
            );
            $stmt->execute([
                ':tid'   => $this->tenantId(),
                ':suc'   => $sucursalId,
                ':desp'  => $sucursalStock,
                ':cli'   => $clienteId,
                ':usr'   => $_SESSION['usuario_id'],
                ':uso'   => $fechaUso,
                ':rec'   => $fechaRecolec,
                ':ent'   => $fechaEntrega,
                ':hent'  => $horaEntrega ?: null,
                ':huso'  => $horaUso ?: null,
                ':hrec'  => $horaRecolec ?: null,
                ':cdesc' => $costoDesc !== '' ? $costoDesc : null,
                ':cmonto'=> $costoMonto,
                ':dir'   => $direccion ?: null,
                ':total' => $total,
                ':desc'  => $descuento,
            ]);
            $reservaId = $db->lastInsertId();

            foreach ($lineas as $l) {
                $db->prepare(
                    "INSERT INTO detalle_reserva
                        (tenant_id, reserva_id, inventario_id, cantidad, dias, tipo_precio, precio_unitario, subtotal)
                     VALUES (:tid, :rid, :iid, :cant, :dias, :tipo, :precio, :sub)"
                )->execute([
                    ':tid'   => $this->tenantId(),
                    ':rid'   => $reservaId,
                    ':iid'   => $l['inventario_id'],
                    ':cant'  => $l['cantidad'],
                    ':dias'  => $l['dias'],
                    ':tipo'  => $l['tipo_precio'],
                    ':precio'=> $l['precio_unitario'],
                    ':sub'   => $l['subtotal'],
                ]);
                // Reservar ya NO descuenta del inventario: el producto
                // sigue en bodega hasta que se entrega.
            }

            $db->commit();
            $_SESSION['exito'] = 'Reserva creada. Total: Q' . number_format($total, 2);
            header('Location: ' . URL_BASE . '/?url=reservas/detalle/' . $reservaId); exit;

        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $_SESSION['error'] = $e instanceof SinUnidadesLibres
                ? $e->getMessage()
                : mensajeError($e, 'Error al guardar la reserva.');
            header('Location: ' . URL_BASE . '/?url=reservas/nueva'); exit;
        }
    }

    // ── Formulario para modificar una reserva ──────────────────
    // Solo mientras esté en estado 'reservado': una vez entregada,
    // los bienes ya salieron y cambiarla seria mentir sobre lo que
    // el cliente tiene en la mano.
    public function editar($id) {
        $this->exigirAccesoReserva($id);

        $db   = $this->db();
        $stmt = $db->prepare(
            "SELECT r.*, c.nombre_completo, c.telefono, c.direccion,
                    s.nombre AS sucursal_nombre
             FROM reservas r
             INNER JOIN clientes c   ON c.id = r.cliente_id
             INNER JOIN sucursales s ON s.id = r.sucursal_id
             WHERE r.id = :id AND r.tenant_id = :tid AND r.estado = 'reservado'"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            $_SESSION['error'] = 'Solo se pueden modificar las reservas que todavía no se han entregado.';
            header('Location: ' . URL_BASE . '/?url=reservas/detalle/' . $id); exit;
        }

        // cantidad_disponible es lo que queda SIN contar esta reserva,
        // asi que el tope de cada linea es disponible + lo ya apartado.
        $stmt = $db->prepare(
            "SELECT dr.*, i.nombre AS producto_nombre, i.cantidad_disponible, i.foto_path,
                    i.estanteria, i.columna, i.fila
             FROM detalle_reserva dr
             INNER JOIN inventario i ON i.id = dr.inventario_id
             WHERE dr.reserva_id = :rid AND dr.tenant_id = :tid
             ORDER BY i.nombre ASC"
        );
        $stmt->execute([':rid' => $id, ':tid' => $this->tenantId()]);
        $detalle = $stmt->fetchAll();

        // Tope de cada linea = lo libre en las fechas de ESTA reserva,
        // sin contarla contra si misma. La vista suma cantidad +
        // cantidad_disponible, por eso aqui se guarda la diferencia.
        $hasta  = momentoFinReserva($reserva);
        $libres = unidadesLibres(array_column($detalle, 'inventario_id'), momentoInicioReserva($reserva), $hasta,
                                 $this->tenantId(), (int) $id);
        foreach ($detalle as &$d) {
            $d['cantidad_disponible'] = max(0, ($libres[(int) $d['inventario_id']] ?? 0) - (int) $d['cantidad']);
        }
        unset($d);

        $tituloPagina = 'Modificar reserva #' . $id;
        include RAIZ . '/app/vistas/reservas/editar.php';
    }

    // ── Guardar los cambios de la reserva ──────────────────────
    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=reservas/index'); exit;
        }

        $id = intval($_POST['reserva_id'] ?? 0);
        $this->exigirAccesoReserva($id);

        $db  = $this->db();
        $tid = $this->tenantId();

        $stmt = $db->prepare(
            "SELECT * FROM reservas WHERE id = :id AND tenant_id = :tid AND estado = 'reservado'"
        );
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            $_SESSION['error'] = 'Solo se pueden modificar las reservas que todavía no se han entregado.';
            header('Location: ' . URL_BASE . '/?url=reservas/detalle/' . $id); exit;
        }

        $productos = $_POST['productos'] ?? [];
        $descuento = max(0, floatval($_POST['descuento'] ?? 0));
        $direccion = mb_substr(trim($_POST['direccion_entrega'] ?? ''), 0, 255);
        $volver    = URL_BASE . '/?url=reservas/editar/' . $id;

        // ── Fechas, horas y costo inicial ──────────────────────
        $fechaEntrega = fechaISO($_POST['fecha_entrega'] ?? '');
        $horaEntrega  = horaISO($_POST['hora_entrega'] ?? '');
        $fechaUso     = fechaISO($_POST['fecha_uso'] ?? '');
        $horaUso      = horaISO($_POST['hora_uso'] ?? '');
        $fechaRecolec = fechaISO($_POST['fecha_recoleccion'] ?? '');
        $horaRecolec  = horaISO($_POST['hora_recoleccion'] ?? '');

        $costoDesc  = mb_substr(trim($_POST['costo_inicial_desc'] ?? ''), 0, 150);
        $costoMonto = min(99999999.99, round(max(0, floatval($_POST['costo_inicial_monto'] ?? 0)), 2));
        if ($costoMonto <= 0) $costoDesc = '';

        // Una reserva anterior a las horas puede seguir sin ellas, y una
        // entrega que ya quedó atrasada no debe impedir tocar solo los
        // productos: la fecha se compara con hoy solo si la cambiaron.
        $sinHoras = empty($reserva['hora_entrega']) && empty($reserva['hora_uso']) && empty($reserva['hora_recoleccion']);
        $entregaGuardada = fechaISO($reserva['fecha_entrega'] ?? '') ?: fechaISO($reserva['fecha_uso']);

        $errorCrono = $this->validarCronograma(
            $fechaEntrega, $horaEntrega, $fechaUso, $horaUso, $fechaRecolec, $horaRecolec,
            !$sinHoras, $fechaEntrega !== $entregaGuardada
        );
        if (!$errorCrono && $costoMonto > 0 && $costoDesc === '') {
            $errorCrono = 'Escribí la descripción del costo inicial (por ejemplo: flete a aldea).';
        }
        if ($errorCrono) {
            $_SESSION['error'] = $errorCrono;
            header('Location: ' . $volver); exit;
        }

        $momentoEntrega = momentoISO($fechaEntrega, $horaEntrega, '00:00:00');
        $momentoRecolec = momentoISO($fechaRecolec, $horaRecolec, '23:59:59');

        // ── Lo que la reserva tiene hoy ────────────────────────
        $stmt = $db->prepare(
            "SELECT * FROM detalle_reserva WHERE reserva_id = :rid AND tenant_id = :tid"
        );
        $stmt->execute([':rid' => $id, ':tid' => $tid]);

        $viejas = [];
        foreach ($stmt->fetchAll() as $l) {
            $viejas[(int) $l['inventario_id']] = $l;
        }

        // ── Lo que quedaria despues del cambio ─────────────────
        $nuevas   = [];
        $subtotal = 0;

        foreach ($productos as $linea) {
            $invId    = intval($linea['inventario_id'] ?? 0);
            $cantidad = intval($linea['cantidad'] ?? 0);
            $dias     = max(1, intval($linea['dias'] ?? 1));
            $tipo     = in_array($linea['tipo'] ?? '', ['dia', 'evento'], true) ? $linea['tipo'] : 'evento';

            // cantidad 0 = el usuario quito ese producto de la reserva
            if (!$invId || $cantidad <= 0) continue;

            $stmt = $db->prepare(
                "SELECT id, nombre, precio_alquiler, cantidad_disponible
                 FROM inventario
                 WHERE id = :id AND tenant_id = :tid AND sucursal_id = :sid"
            );
            // Los productos se buscan donde se despacha (la bodega si se vendio en vitrina).
            // Respaldo a sucursal_id por si la reserva es anterior a la columna.
            $stmt->execute([
                ':id'  => $invId,
                ':tid' => $tid,
                ':sid' => (int) ($reserva['sucursal_despacho_id'] ?: $reserva['sucursal_id']),
            ]);
            $prod = $stmt->fetch();

            if (!$prod) continue;

            // Los productos que ya estaban conservan el precio con el
            // que se cotizaron; los nuevos entran al precio de hoy.
            $precio = isset($viejas[$invId])
                    ? (float) $viejas[$invId]['precio_unitario']
                    : (float) $prod['precio_alquiler'];

            $lineaSubtotal = $cantidad * $precio * ($tipo === 'dia' ? $dias : 1);
            $subtotal += $lineaSubtotal;

            $nuevas[$invId] = [
                'inventario_id'   => $invId,
                'nombre'          => $prod['nombre'],
                'cantidad'        => $cantidad,
                'dias'            => $dias,
                'tipo_precio'     => $tipo,
                'precio_unitario' => $precio,
                'subtotal'        => $lineaSubtotal,
            ];
        }

        if (!$nuevas) {
            $_SESSION['error'] = 'La reserva tiene que quedar con al menos un producto. Si el cliente ya no quiere nada, cancelala.';
            header('Location: ' . $volver); exit;
        }

        $descuento = min($descuento, $subtotal);
        $total     = $subtotal - $descuento + $costoMonto;

        // ── Aplicar ────────────────────────────────────────────
        $db->beginTransaction();
        try {
            // Mientras la reserva no se entrega, el inventario no se
            // mueve: solo se revisa que alcance en SUS fechas, sin
            // contarse a si misma.
            $this->exigirUnidadesLibres(array_values($nuevas), $momentoEntrega, $momentoRecolec, $id);

            $db->prepare("DELETE FROM detalle_reserva WHERE reserva_id = :rid AND tenant_id = :tid")
               ->execute([':rid' => $id, ':tid' => $tid]);

            $insert = $db->prepare(
                "INSERT INTO detalle_reserva
                    (tenant_id, reserva_id, inventario_id, cantidad, dias, tipo_precio, precio_unitario, subtotal)
                 VALUES (:tid, :rid, :iid, :cant, :dias, :tipo, :precio, :sub)"
            );

            foreach ($nuevas as $invId => $l) {
                $insert->execute([
                    ':tid'    => $tid,
                    ':rid'    => $id,
                    ':iid'    => $invId,
                    ':cant'   => $l['cantidad'],
                    ':dias'   => $l['dias'],
                    ':tipo'   => $l['tipo_precio'],
                    ':precio' => $l['precio_unitario'],
                    ':sub'    => $l['subtotal'],
                ]);
            }

            $db->prepare(
                "UPDATE reservas
                 SET fecha_entrega = :fent, hora_entrega = :hent,
                     fecha_uso = :fuso, hora_uso = :huso,
                     fecha_recoleccion_programada = :frec, hora_recoleccion = :hrec,
                     costo_inicial_desc = :cdesc, costo_inicial_monto = :cmonto,
                     total = :total, descuento = :desc, direccion_entrega = :dir
                 WHERE id = :id AND tenant_id = :tid"
            )->execute([
                ':fent'   => $fechaEntrega,
                ':hent'   => $horaEntrega ?: null,
                ':fuso'   => $fechaUso,
                ':huso'   => $horaUso ?: null,
                ':frec'   => $fechaRecolec,
                ':hrec'   => $horaRecolec ?: null,
                ':cdesc'  => $costoDesc !== '' ? $costoDesc : null,
                ':cmonto' => $costoMonto,
                ':total'  => $total,
                ':desc'   => $descuento,
                ':dir'    => $direccion ?: null,
                ':id'     => $id,
                ':tid'    => $tid,
            ]);

            $db->commit();
            $_SESSION['exito'] = 'Reserva actualizada. Nuevo total: Q' . number_format($total, 2);
            header('Location: ' . URL_BASE . '/?url=reservas/detalle/' . $id); exit;

        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[SOLTEC alquileres] editar reserva: ' . $e->getMessage());
            $_SESSION['error'] = $e instanceof SinUnidadesLibres
                ? $e->getMessage()
                : mensajeError($e, 'No se pudo actualizar la reserva.');
            header('Location: ' . $volver); exit;
        }
    }

    // ── Detalle de reserva ─────────────────────────────────────
    public function detalle($id) {
        $this->exigirAccesoReserva($id);

        $stmt = $this->db()->prepare(
            "SELECT r.*, c.nombre_completo, c.telefono, c.direccion,
                    s.nombre AS sucursal_nombre, u.nombre AS usuario_nombre,
                    n.nombre_negocio
             FROM reservas r
             INNER JOIN clientes c ON c.id = r.cliente_id
             INNER JOIN sucursales s ON s.id = r.sucursal_id
             INNER JOIN usuarios u ON u.id = r.usuario_id
             INNER JOIN negocios n ON n.id = r.tenant_id
             WHERE r.id = :id AND r.tenant_id = :tid"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            $_SESSION['error'] = 'Reserva no encontrada.';
            header('Location: ' . URL_BASE . '/?url=reservas/index'); exit;
        }

        $stmt = $this->db()->prepare(
            "SELECT dr.*, i.nombre AS producto_nombre, i.foto_path,
                    i.estanteria, i.columna, i.fila
             FROM detalle_reserva dr
             INNER JOIN inventario i ON i.id = dr.inventario_id
             WHERE dr.reserva_id = :rid AND dr.tenant_id = :tid"
        );
        $stmt->execute([':rid' => $id, ':tid' => $this->tenantId()]);
        $detalle = $stmt->fetchAll();

        // Fotos de evidencia (entrega y devolución)
        $fotos = $this->obtenerFotosReserva((int) $id);

        $tituloPagina = 'Reserva #' . $id;
        include RAIZ . '/app/vistas/reservas/detalle.php';
    }

    // ── Entregar ───────────────────────────────────────────────
    public function entregar($id) {
        $this->exigirAccesoReserva($id);

        $db = $this->db();
        $stmt = $db->prepare(
            "SELECT * FROM reservas WHERE id = :id AND tenant_id = :tid AND estado = 'reservado'"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            $_SESSION['error'] = 'La reserva no existe o ya fue procesada.';
            header('Location: ' . URL_BASE . '/?url=reservas/index'); exit;
        }

        $db->beginTransaction();
        try {
            // Al entregar SÍ sale de bodega. Si las unidades todavía no
            // regresaron de otra reserva, no se puede entregar: el
            // sistema no deja registrar algo que no está físicamente.
            $lineas = $db->prepare(
                "SELECT dr.inventario_id, SUM(dr.cantidad) AS cantidad, i.nombre, i.cantidad_disponible
                 FROM detalle_reserva dr
                 INNER JOIN inventario i ON i.id = dr.inventario_id AND i.tenant_id = dr.tenant_id
                 WHERE dr.reserva_id = :rid AND dr.tenant_id = :tid
                 GROUP BY dr.inventario_id, i.nombre, i.cantidad_disponible"
            );
            $lineas->execute([':rid' => $id, ':tid' => $this->tenantId()]);
            $lineas = $lineas->fetchAll();

            $bloqueo = $db->prepare(
                "SELECT cantidad_disponible FROM inventario WHERE id = :id AND tenant_id = :tid FOR UPDATE"
            );
            $faltan = [];
            foreach ($lineas as $l) {
                $bloqueo->execute([':id' => $l['inventario_id'], ':tid' => $this->tenantId()]);
                $enBodega = (int) $bloqueo->fetchColumn();
                if ($enBodega < (int) $l['cantidad']) {
                    $faltan[] = ((int) $l['cantidad'] - $enBodega) . ' ' . $l['nombre'];
                }
            }
            if ($faltan) {
                throw new SinUnidadesLibres('No se puede entregar todavía: faltan en bodega '
                    . implode(', ', $faltan) . '. Probablemente no han regresado de otra reserva.');
            }

            $descontar = $db->prepare(
                "UPDATE inventario SET cantidad_disponible = cantidad_disponible - :cant
                 WHERE id = :id AND tenant_id = :tid"
            );
            foreach ($lineas as $l) {
                $descontar->execute([':cant' => (int) $l['cantidad'], ':id' => $l['inventario_id'], ':tid' => $this->tenantId()]);
            }

            $db->prepare("UPDATE reservas SET estado = 'entregado' WHERE id = :id AND tenant_id = :tid")
               ->execute([':id' => $id, ':tid' => $this->tenantId()]);

            $db->prepare(
                "INSERT INTO entregas (tenant_id, reserva_id, usuario_id, fecha_hora_entrega)
                 VALUES (:tid, :rid, :usr, NOW())"
            )->execute([
                ':tid' => $this->tenantId(),
                ':rid' => $id,
                ':usr' => $_SESSION['usuario_id'],
            ]);

            $db->commit();

            // Fotos del producto al momento de salir con el cliente.
            // Se guardan despues del commit: si falla una foto, la
            // entrega ya quedo registrada igual.
            $fotos = $this->guardarFotosReserva((int) $id, 'entrega');

            $_SESSION['exito'] = 'Entrega registrada correctamente.'
                . ($fotos['guardadas'] ? ' Se adjuntaron ' . $fotos['guardadas'] . ' foto(s) de evidencia.' : '')
                . ($fotos['errores'] ? ' Aviso: ' . implode(' ', $fotos['errores']) : '');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $_SESSION['error'] = $e instanceof SinUnidadesLibres
                ? $e->getMessage()
                : mensajeError($e, 'Error al registrar la entrega.');
        }

        header('Location: ' . URL_BASE . '/?url=reservas/detalle/' . $id); exit;
    }

    // ── Devolver → redirige a inspección ──────────────────────
    public function devolver($id) {
        $this->exigirAccesoReserva($id);

        $stmt = $this->db()->prepare(
            "SELECT * FROM reservas WHERE id = :id AND tenant_id = :tid AND estado = 'entregado'"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            $_SESSION['error'] = 'La reserva no existe o no está en estado Entregado.';
            header('Location: ' . URL_BASE . '/?url=reservas/index'); exit;
        }

        // En vez de procesar directo, va al formulario de inspección
        header('Location: ' . URL_BASE . '/?url=reservas/inspeccion/' . $id); exit;
    }

    // ── Formulario de inspección de bienes ─────────────────────
    public function inspeccion($id) {
        $this->exigirAccesoReserva($id);

        $db = $this->db();

        $stmt = $db->prepare(
            "SELECT r.*, c.nombre_completo, s.nombre AS sucursal_nombre
             FROM reservas r
             INNER JOIN clientes c ON c.id = r.cliente_id
             INNER JOIN sucursales s ON s.id = r.sucursal_id
             WHERE r.id = :id AND r.tenant_id = :tid AND r.estado = 'entregado'"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            $_SESSION['error'] = 'Reserva no encontrada o no está en estado Entregado.';
            header('Location: ' . URL_BASE . '/?url=reservas/index'); exit;
        }

        // Agrupado por producto: una reserva puede traer el mismo
        // producto en dos lineas (suelto y dentro de un combo), y el
        // formulario de inspeccion usa inventario_id como llave.
        $stmt = $db->prepare(
            "SELECT dr.inventario_id, SUM(dr.cantidad) AS cantidad, i.nombre AS producto_nombre
             FROM detalle_reserva dr
             INNER JOIN inventario i ON i.id = dr.inventario_id
             WHERE dr.reserva_id = :rid AND dr.tenant_id = :tid
             GROUP BY dr.inventario_id, i.nombre"
        );
        $stmt->execute([':rid' => $id, ':tid' => $this->tenantId()]);
        $detalle = $stmt->fetchAll();

        // Fotos tomadas al momento de la entrega: sirven de
        // referencia para comparar como salio y como regreso.
        $fotos = $this->obtenerFotosReserva((int) $id);

        $tituloPagina = 'Inspección de bienes — Reserva #' . $id;
        include RAIZ . '/app/vistas/reservas/inspeccion.php';
    }

    // ── Confirmar devolución con inspección ─────────────────────
    public function confirmardevolucion() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=reservas/index'); exit;
        }

        $id        = intval($_POST['reserva_id']);
        $this->exigirAccesoReserva($id);

        $db        = $this->db();
        $tid       = $this->tenantId();
        $usuarioId = $_SESSION['usuario_id'];

        // Verifica estado
        $stmt = $db->prepare(
            "SELECT * FROM reservas WHERE id = :id AND tenant_id = :tid AND estado = 'entregado'"
        );
        $stmt->execute([':id' => $id, ':tid' => $tid]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            $_SESSION['error'] = 'Reserva no válida para devolución.';
            header('Location: ' . URL_BASE . '/?url=reservas/index'); exit;
        }

        $inspecciones = $_POST['inspeccion'] ?? [];

        // Una fila por producto (mismo agrupado que el formulario de
        // inspeccion). Sin esto, un producto repetido en dos lineas se
        // procesaba dos veces y sumaba el doble al inventario.
        $stmt = $db->prepare(
            "SELECT dr.inventario_id, SUM(dr.cantidad) AS cantidad, i.nombre AS producto_nombre
             FROM detalle_reserva dr
             INNER JOIN inventario i ON i.id = dr.inventario_id
             WHERE dr.reserva_id = :rid AND dr.tenant_id = :tid
             GROUP BY dr.inventario_id, i.nombre"
        );
        $stmt->execute([':rid' => $id, ':tid' => $tid]);
        $lineas = $stmt->fetchAll();

        // Cada unidad que salio tiene que quedar justificada: buena,
        // dañada o perdida. Antes solo se revisaba que la suma no se
        // pasara, y las unidades que faltaban desaparecian del
        // inventario sin dejar rastro ni abrir incidencia.
        foreach ($lineas as $linea) {
            $invId     = $linea['inventario_id'];
            $cantTotal = (int) $linea['cantidad'];
            // OJO: el formulario manda el campo SIN enye ([danada]).
            $buena     = intval($inspecciones[$invId]['buena']   ?? 0);
            $dañada    = intval($inspecciones[$invId]['danada']  ?? 0);
            $perdida   = intval($inspecciones[$invId]['perdida'] ?? 0);
            $suma      = $buena + $dañada + $perdida;

            $error = null;
            if ($buena < 0 || $dañada < 0 || $perdida < 0) {
                $error = "Las cantidades de \"{$linea['producto_nombre']}\" no pueden ser negativas.";
            } elseif ($suma > $cantTotal) {
                $error = "La suma para \"{$linea['producto_nombre']}\" ({$suma}) supera lo que salió ({$cantTotal}).";
            } elseif ($suma < $cantTotal) {
                $faltan = $cantTotal - $suma;
                $error  = "Faltan {$faltan} unidad(es) de \"{$linea['producto_nombre']}\" por justificar: "
                        . "cada unidad debe quedar como buena, dañada o perdida.";
            }

            if ($error) {
                $_SESSION['error'] = $error;
                header('Location: ' . URL_BASE . '/?url=reservas/inspeccion/' . $id); exit;
            }
        }

        $db->beginTransaction();
        try {
            // 1. Cambiar estado de la reserva
            $db->prepare("UPDATE reservas SET estado = 'devuelto' WHERE id = :id AND tenant_id = :tid")
               ->execute([':id' => $id, ':tid' => $tid]);

            // 2. Registrar devolución
            $db->prepare(
                "INSERT INTO devoluciones (tenant_id, reserva_id, usuario_id, fecha_hora_devolucion)
                 VALUES (:tid, :rid, :usr, NOW())"
            )->execute([':tid' => $tid, ':rid' => $id, ':usr' => $usuarioId]);
            $devolucionId = $db->lastInsertId();
            $incidenciasCreadas = 0;

            // 3. Por cada producto: registrar inspección y actualizar inventario
            foreach ($lineas as $linea) {
                $invId   = $linea['inventario_id'];
                $buena   = intval($inspecciones[$invId]['buena']   ?? 0);
                $dañada  = intval($inspecciones[$invId]['danada']  ?? 0);
                $perdida = intval($inspecciones[$invId]['perdida'] ?? 0);
                $motivoDano    = trim($inspecciones[$invId]['motivo_dano']    ?? '');
                $responsable   = trim($inspecciones[$invId]['responsable']    ?? '');
                $observaciones = trim($inspecciones[$invId]['observaciones']  ?? '');

                // Registrar en revisiones_bienes
                $db->prepare(
                    "INSERT INTO revisiones_bienes
                        (tenant_id, devolucion_id, inventario_id, cantidad_buena,
                         cantidad_dañada, cantidad_perdida, motivo_dano, responsable_perdida, observaciones)
                     VALUES (:tid, :did, :iid, :buena, :danada, :perdida, :motivo, :resp, :obs)"
                )->execute([
                    ':tid'    => $tid,
                    ':did'    => $devolucionId,
                    ':iid'    => $invId,
                    ':buena'  => $buena,
                    ':danada' => $dañada,
                    ':perdida'=> $perdida,
                    ':motivo' => $motivoDano,
                    ':resp'   => $responsable,
                    ':obs'    => $observaciones,
                ]);
                $revisionId = (int) $db->lastInsertId();

                // Daños y faltantes abren una incidencia para el administrador.
                // Va dentro de la misma transacción: o queda todo, o nada.
                $detalleIncidencia = implode("\n", array_filter([
                    $motivoDano  !== '' ? 'Motivo: ' . $motivoDano       : '',
                    $responsable !== '' ? 'Responsable: ' . $responsable : '',
                    $observaciones,
                ], fn($t) => $t !== ''));

                foreach (['danado' => $dañada, 'perdido' => $perdida] as $tipoIncidencia => $cantIncidencia) {
                    if ($cantIncidencia <= 0) continue;
                    $db->prepare(
                        "INSERT INTO incidencias
                            (tenant_id, reserva_id, revision_id, inventario_id, tipo, cantidad,
                             observaciones, usuario_reporta_id, estado, fecha_registro)
                         VALUES (:tid, :rid, :rev, :iid, :tipo, :cant, :obs, :usr, 'pendiente', :fecha)"
                    )->execute([
                        ':tid'   => $tid,
                        ':rid'   => $id,
                        ':rev'   => $revisionId,
                        ':iid'   => $invId,
                        ':tipo'  => $tipoIncidencia,
                        ':cant'  => $cantIncidencia,
                        ':obs'   => $detalleIncidencia !== '' ? $detalleIncidencia : null,
                        ':usr'   => $usuarioId,
                        ':fecha' => ahoraGt(),
                    ]);
                    $incidenciasCreadas++;
                }

                // Solo las unidades BUENAS regresan al inventario disponible
                if ($buena > 0) {
                    $db->prepare(
                        "UPDATE inventario SET cantidad_disponible = cantidad_disponible + :buena
                         WHERE id = :id AND tenant_id = :tid"
                    )->execute([':buena' => $buena, ':id' => $invId, ':tid' => $tid]);
                }

                // Las unidades DAÑADAS o PERDIDAS reducen el total del inventario
                $bajas = $dañada + $perdida;
                if ($bajas > 0) {
                    $db->prepare(
                        "UPDATE inventario SET cantidad_total = cantidad_total - :bajas
                         WHERE id = :id AND tenant_id = :tid"
                    )->execute([':bajas' => $bajas, ':id' => $invId, ':tid' => $tid]);
                }
            }

            $db->commit();

            // Fotos de como regreso el producto
            $fotos = $this->guardarFotosReserva((int) $id, 'devolucion');

            $_SESSION['exito'] = 'Devolución e inspección registradas correctamente.'
                . ($incidenciasCreadas ? " Se abrieron {$incidenciasCreadas} incidencia(s) para el administrador." : '')
                . ($fotos['guardadas'] ? ' Se adjuntaron ' . $fotos['guardadas'] . ' foto(s) de evidencia.' : '')
                . ($fotos['errores'] ? ' Aviso: ' . implode(' ', $fotos['errores']) : '');
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al registrar la devolución. Intenta de nuevo.');
            header('Location: ' . URL_BASE . '/?url=reservas/inspeccion/' . $id); exit;
        }

        header('Location: ' . URL_BASE . '/?url=reservas/detalle/' . $id); exit;
    }

    // ── Cancelar ───────────────────────────────────────────────
    public function cancelar($id) {
        $this->exigirAccesoReserva($id);

        $db = $this->db();
        $stmt = $db->prepare(
            "SELECT * FROM reservas WHERE id = :id AND tenant_id = :tid AND estado = 'reservado'"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            $_SESSION['error'] = 'Solo se pueden cancelar reservas en estado Reservado.';
            header('Location: ' . URL_BASE . '/?url=reservas/index'); exit;
        }

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE reservas SET estado = 'cancelado' WHERE id = :id AND tenant_id = :tid")
               ->execute([':id' => $id, ':tid' => $this->tenantId()]);

            // No se toca el inventario: una reserva sin entregar nunca
            // saco nada de bodega. Al cancelarla, sus fechas quedan
            // libres solas porque ya no cuenta como 'reservado'.

            $db->commit();
            $_SESSION['exito'] = 'Reserva cancelada. Esas fechas quedaron libres para otras reservas.';
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al cancelar. Intenta de nuevo.');
        }

        header('Location: ' . URL_BASE . '/?url=reservas/detalle/' . $id); exit;
    }

    // ── Comprobante imprimible ─────────────────────────────────
    public function comprobante($id) {
        $this->exigirAccesoReserva($id);

        $db = $this->db();

        $stmt = $db->prepare(
            "SELECT r.*, c.nombre_completo, c.telefono, c.direccion,
                    s.nombre AS sucursal_nombre, u.nombre AS usuario_nombre,
                    n.nombre_negocio, n.direccion AS neg_direccion,
                    n.telefono AS neg_telefono, n.nit AS neg_nit
             FROM reservas r
             INNER JOIN clientes c ON c.id = r.cliente_id
             INNER JOIN sucursales s ON s.id = r.sucursal_id
             INNER JOIN usuarios u ON u.id = r.usuario_id
             INNER JOIN negocios n ON n.id = r.tenant_id
             WHERE r.id = :id AND r.tenant_id = :tid"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            $_SESSION['error'] = 'Reserva no encontrada.';
            header('Location: ' . URL_BASE . '/?url=reservas/index'); exit;
        }

        $stmt = $db->prepare(
            "SELECT dr.*, i.nombre AS producto_nombre
             FROM detalle_reserva dr
             INNER JOIN inventario i ON i.id = dr.inventario_id
             WHERE dr.reserva_id = :rid AND dr.tenant_id = :tid"
        );
        $stmt->execute([':rid' => $id, ':tid' => $this->tenantId()]);
        $detalle = $stmt->fetchAll();

        $tituloPagina = 'Comprobante #' . $id;
        include RAIZ . '/app/vistas/reservas/comprobante.php';
    }

    // ── Inventario disponible vía AJAX ─────────────────────────
    public function inventarioajax() {
        header('Content-Type: application/json');
        $sucursalId = intval($_GET['sucursal_id'] ?? 0);
        $q = trim($_GET['q'] ?? '');

        // Si piden productos de una vitrina, se muestran los de su bodega
        $sucursalId = $sucursalId ? sucursalDeStock($sucursalId, $this->tenantId()) : 0;
        if (!$sucursalId) { echo json_encode([]); exit; }

        // Fechas de la reserva: con ellas se responde "¿cuántas libres
        // en esos días?". Sin fechas, se usa lo que hay en bodega hoy.
        // Acepta día suelto (día completo) o día con hora (AAAA-MM-DDTHH:MM)
        $desde   = fechaOMomentoISO($_GET['desde'] ?? '');
        $hasta   = fechaOMomentoISO($_GET['hasta'] ?? '');
        $excluir = intval($_GET['excluir'] ?? 0);
        $porFechas = rangoFechasValido($desde, $hasta);

        // ids=1,2,3: volver a consultar productos ya agregados (al
        // cambiar las fechas en el formulario)
        $ids = array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))));

        $sql = "SELECT id, nombre, precio_alquiler, cantidad_disponible,
                       foto_path, estanteria, columna, fila
                FROM inventario
                WHERE tenant_id = :tid AND sucursal_id = :sid";
        $params = [':tid' => $this->tenantId(), ':sid' => $sucursalId];

        if ($ids) {
            $marcas = [];
            foreach (array_values($ids) as $i => $idProd) {
                $marcas[] = ':p' . $i;
                $params[':p' . $i] = $idProd;
            }
            $sql .= ' AND id IN (' . implode(', ', $marcas) . ')';
        } elseif ($q) {
            $sql .= " AND nombre LIKE :q";
            $params[':q'] = "%$q%";
        }
        if (!$porFechas) $sql .= " AND cantidad_disponible > 0";

        $sql .= " ORDER BY nombre ASC LIMIT 15";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll();

        // Con fechas, 'cantidad_disponible' pasa a ser lo libre en esos
        // días (el formulario la usa como tope sin saber de dónde sale)
        if ($porFechas && $filas) {
            $libres = unidadesLibres(array_column($filas, 'id'), $desde, $hasta, $this->tenantId(), $excluir);
            foreach ($filas as &$f) {
                $f['en_bodega'] = (int) $f['cantidad_disponible'];
                $f['cantidad_disponible'] = $libres[(int) $f['id']] ?? 0;
            }
            unset($f);
        }

        // Se manda la URL ya armada y la ubicacion ya escrita: el
        // JavaScript solo pinta, no tiene que saber como se guardan.
        $productos = array_map(function ($p) {
            $p['foto_url']  = (!empty($p['foto_path']) && existeImagen($p['foto_path']))
                            ? urlImagen($p['foto_path']) : '';
            $p['ubicacion'] = ubicacionTexto($p, '');
            unset($p['foto_path']);
            return $p;
        }, $filas);

        echo json_encode($productos);
        exit;
    }

    // ── Disponibilidad por fechas ──────────────────────────────
    // Bloquea los productos (FOR UPDATE) y revisa que las cantidades
    // pedidas quepan en las fechas. Si no alcanza, lanza
    // SinUnidadesLibres con un mensaje listo para el usuario.
    // $lineas: [['inventario_id' => .., 'nombre' => .., 'cantidad' => ..], ...]
    private function exigirUnidadesLibres(array $lineas, string $desde, string $hasta, int $excluirReservaId): void {
        $pedido  = [];
        $nombres = [];
        foreach ($lineas as $l) {
            $idProd = (int) $l['inventario_id'];
            $pedido[$idProd]  = ($pedido[$idProd] ?? 0) + (int) $l['cantidad'];
            $nombres[$idProd] = $l['nombre'] ?? ('producto #' . $idProd);
        }

        $bloqueo = $this->db()->prepare(
            "SELECT id FROM inventario WHERE id = :id AND tenant_id = :tid FOR UPDATE"
        );
        foreach (array_keys($pedido) as $idProd) {
            $bloqueo->execute([':id' => $idProd, ':tid' => $this->tenantId()]);
        }

        $libres = unidadesLibres(array_keys($pedido), $desde, $hasta, $this->tenantId(), $excluirReservaId);

        foreach ($pedido as $idProd => $cant) {
            $hay = $libres[$idProd] ?? 0;
            if ($cant > $hay) {
                throw new SinUnidadesLibres(
                    "No alcanza \"{$nombres[$idProd]}\" del " . momentoCorto($desde) . ' al ' . momentoCorto($hasta)
                    . ": pediste {$cant} y hay {$hay} libres en esas fechas."
                );
            }
        }
    }

    // ── Fotos de evidencia de una reserva ──────────────────────
    // Se usan al entregar (como sale el producto) y al recibir
    // (como regreso). Es la prueba del estado del bien.
    private function guardarFotosReserva(int $reservaId, string $tipo): array {
        $guardadas = 0;
        $errores   = [];
        $archivos  = archivosNormalizados($_FILES['fotos'] ?? []);

        if (!$archivos) return ['guardadas' => 0, 'errores' => []];

        // Todo va dentro de un try propio: la entrega o la devolucion
        // ya quedaron registradas. Si algo falla con las fotos (por
        // ejemplo, que todavia no se haya creado la tabla), se avisa
        // pero NO se tumba la operacion principal.
        try {
            $stmt = $this->db()->prepare(
                "INSERT INTO reserva_fotos (tenant_id, reserva_id, tipo, ruta, usuario_id, creado_en)
                 VALUES (:tid, :rid, :tipo, :ruta, :usr, NOW())"
            );

            foreach (array_slice($archivos, 0, 6) as $archivo) {
                $error = null;
                $ruta  = guardarImagenSubida($archivo, 'reservas', 'res' . $reservaId . '_' . $tipo, $error);

                if (!$ruta) {
                    if ($error) $errores[] = $error;
                    continue;
                }

                $stmt->execute([
                    ':tid'  => $this->tenantId(),
                    ':rid'  => $reservaId,
                    ':tipo' => $tipo,
                    ':ruta' => $ruta,
                    ':usr'  => $_SESSION['usuario_id'],
                ]);
                $guardadas++;
            }
        } catch (Throwable $e) {
            error_log('[SOLTEC alquileres] fotos reserva: ' . $e->getMessage());
            $errores[] = 'Las fotos no se pudieron guardar.';
        }

        return ['guardadas' => $guardadas, 'errores' => $errores];
    }

    private function obtenerFotosReserva(int $reservaId): array {
        // Si la tabla aun no existe (migracion pendiente), la pantalla
        // se muestra igual, solo sin la seccion de fotos.
        try {
            $stmt = $this->db()->prepare(
                "SELECT f.*, u.nombre AS usuario_nombre
                 FROM reserva_fotos f
                 LEFT JOIN usuarios u ON u.id = f.usuario_id
                 WHERE f.reserva_id = :rid AND f.tenant_id = :tid
                 ORDER BY f.creado_en ASC, f.id ASC"
            );
            $stmt->execute([':rid' => $reservaId, ':tid' => $this->tenantId()]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('[SOLTEC alquileres] reserva_fotos: ' . $e->getMessage());
            return [];
        }
    }

    // ── Helpers ────────────────────────────────────────────────
    // Revisa fechas y horas de entrega, uso y recolección.
    // Devuelve el mensaje de error, o null si todo está bien.
    //   $exigirHoras : las tres horas son obligatorias
    //   $noAntesDeHoy: la entrega no puede ser un día que ya pasó
    private function validarCronograma(string $fEntrega, string $hEntrega, string $fUso, string $hUso,
                                       string $fRecolec, string $hRecolec,
                                       bool $exigirHoras, bool $noAntesDeHoy): ?string {
        $partes = [
            'entrega'     => [$fEntrega, $hEntrega],
            'uso'         => [$fUso, $hUso],
            'recolección' => [$fRecolec, $hRecolec],
        ];
        foreach ($partes as $nombre => [$f, $h]) {
            if (!esFechaValida($f)) return "La fecha de {$nombre} no es válida.";
            if ($exigirHoras && $h === '') return "Indicá la hora de {$nombre}.";
        }

        if ($noAntesDeHoy && diasEntreFechas(hoyGt(), $fEntrega) < 0) {
            return 'La fecha de entrega no puede ser anterior a hoy (' . fechaCorta(hoyGt()) . ').';
        }

        // Sin hora (reservas viejas) la entrega y el uso cuentan desde la
        // medianoche y la recolección hasta el final del día.
        $entrega = momentoISO($fEntrega, $hEntrega, '00:00:00');
        $uso     = momentoISO($fUso, $hUso, '00:00:00');
        $recolec = momentoISO($fRecolec, $hRecolec, '23:59:59');

        if ($uso < $entrega) {
            return 'El uso (' . momentoCorto($uso) . ') no puede ser antes de la entrega (' . momentoCorto($entrega) . ').';
        }
        if ($recolec < $uso) {
            return 'La recolección (' . momentoCorto($recolec) . ') no puede ser antes del uso (' . momentoCorto($uso) . ').';
        }
        if ($recolec <= $entrega) {
            return 'La recolección tiene que ser después de la entrega.';
        }
        return null;
    }

    private function obtenerSucursales(): array {
        $stmt = $this->db()->prepare(
            "SELECT id, nombre FROM sucursales WHERE tenant_id = :tid AND activa = 1 ORDER BY nombre"
        );
        $stmt->execute([':tid' => $this->tenantId()]);
        return $stmt->fetchAll();
    }
}

// Falta de unidades: su mensaje se le muestra al usuario tal cual
// (a diferencia de los errores de base de datos, que van al log).
class SinUnidadesLibres extends RuntimeException {}
