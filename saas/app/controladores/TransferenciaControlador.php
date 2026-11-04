<?php

class TransferenciaControlador {

    private function db() { return obtenerConexion(); }

    private function tenantId(): int {
        return (int) $_SESSION['tenant_id'];
    }

    // ── Listado ────────────────────────────────────────────────
    public function index() {
        $filtro = $_GET['estado'] ?? '';
        $sql = "SELECT t.*,
                       i.nombre AS producto_nombre,
                       so.nombre AS sucursal_origen,
                       sd.nombre AS sucursal_destino,
                       u.nombre AS usuario_nombre
                FROM transferencias_sucursales t
                INNER JOIN inventario i ON i.id = t.inventario_id
                INNER JOIN sucursales so ON so.id = t.sucursal_origen_id
                INNER JOIN sucursales sd ON sd.id = t.sucursal_destino_id
                INNER JOIN usuarios u ON u.id = t.usuario_id
                WHERE t.tenant_id = :tid";
        $params = [':tid' => $this->tenantId()];

        if ($filtro) {
            $sql .= " AND t.estado = :estado";
            $params[':estado'] = $filtro;
        }

        $sql .= " ORDER BY t.fecha DESC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $transferencias = $stmt->fetchAll();

        $tituloPagina = 'Transferencias entre sucursales';
        include RAIZ . '/app/vistas/transferencias/index.php';
    }

    // ── Formulario nueva transferencia ─────────────────────────
    public function nueva() {
        $sucursales = $this->obtenerSucursales();

        if (count($sucursales) < 2) {
            $_SESSION['error'] = 'Necesitás al menos 2 sucursales activas para realizar transferencias.';
            header('Location: ' . URL_BASE . '/?url=transferencias/index'); exit;
        }

        $tituloPagina = 'Nueva Transferencia';
        include RAIZ . '/app/vistas/transferencias/form.php';
    }

    // ── Inventario disponible por sucursal vía AJAX ────────────
    public function inventarioajax() {
        header('Content-Type: application/json');
        $sucursalId = intval($_GET['sucursal_id'] ?? 0);
        $q = trim($_GET['q'] ?? '');

        if (!$sucursalId) { echo json_encode([]); exit; }

        $sql = "SELECT id, nombre, cantidad_disponible
                FROM inventario
                WHERE tenant_id = :tid AND sucursal_id = :sid AND cantidad_disponible > 0";
        $params = [':tid' => $this->tenantId(), ':sid' => $sucursalId];

        if ($q) {
            $sql .= " AND nombre LIKE :q";
            $params[':q'] = "%$q%";
        }

        $sql .= " ORDER BY nombre ASC LIMIT 15";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        // 'retirables' = lo que de verdad se puede mover: en bodega y sin
        // comprometer para reservas futuras (la misma regla que al guardar)
        echo json_encode($this->conRetirables($stmt->fetchAll()));
        exit;
    }

    // Agrega a cada producto cuántas unidades se pueden sacar y, si son
    // las reservas las que limitan, desde qué fecha
    private function conRetirables(array $productos): array {
        foreach ($productos as &$p) {
            $r = unidadesRetirables((int) $p['id'], $this->tenantId());
            $p['retirables']   = $r['cantidad'];
            $p['limita_fecha'] = $r['fecha'] ? fechaCorta($r['fecha']) : '';
        }
        unset($p);
        return $productos;
    }

    // ── Guardar solicitud ──────────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=transferencias/index'); exit;
        }

        $inventarioId    = intval($_POST['inventario_id'] ?? 0);
        $sucursalOrigen  = intval($_POST['sucursal_origen_id'] ?? 0);
        $sucursalDestino = intval($_POST['sucursal_destino_id'] ?? 0);
        $cantidad        = intval($_POST['cantidad'] ?? 0);

        if (!$inventarioId || !$sucursalOrigen || !$sucursalDestino || $cantidad <= 0) {
            $_SESSION['error'] = 'Todos los campos son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=transferencias/nueva'); exit;
        }

        if ($sucursalOrigen === $sucursalDestino) {
            $_SESSION['error'] = 'La sucursal origen y destino no pueden ser la misma.';
            header('Location: ' . URL_BASE . '/?url=transferencias/nueva'); exit;
        }

        // Verifica disponibilidad real en la sucursal origen
        $stmt = $this->db()->prepare(
            "SELECT nombre, cantidad_disponible FROM inventario
             WHERE id = :id AND tenant_id = :tid AND sucursal_id = :sid"
        );
        $stmt->execute([
            ':id'  => $inventarioId,
            ':tid' => $this->tenantId(),
            ':sid' => $sucursalOrigen,
        ]);
        $producto = $stmt->fetch();

        if (!$producto) {
            $_SESSION['error'] = 'El producto no existe en la sucursal origen.';
            header('Location: ' . URL_BASE . '/?url=transferencias/nueva'); exit;
        }

        // No basta con que esten en bodega: tampoco pueden estar
        // comprometidas para reservas futuras de la sucursal origen.
        // (Se vuelve a revisar al completar: puede haber cambiado.)
        $retirables = unidadesRetirables($inventarioId, $this->tenantId());
        if ($cantidad > $retirables['cantidad']) {
            $_SESSION['error'] = mensajeNoRetirable($producto['nombre'], $cantidad, $retirables);
            header('Location: ' . URL_BASE . '/?url=transferencias/nueva'); exit;
        }

        $this->db()->prepare(
            "INSERT INTO transferencias_sucursales
                (tenant_id, inventario_id, sucursal_origen_id, sucursal_destino_id, cantidad, usuario_id, estado)
             VALUES (:tid, :iid, :origen, :destino, :cant, :uid, 'pendiente')"
        )->execute([
            ':tid'     => $this->tenantId(),
            ':iid'     => $inventarioId,
            ':origen'  => $sucursalOrigen,
            ':destino' => $sucursalDestino,
            ':cant'    => $cantidad,
            ':uid'     => $_SESSION['usuario_id'],
        ]);

        $_SESSION['exito'] = "Transferencia de {$cantidad} unidades de \"{$producto['nombre']}\" creada. Pendiente de aprobación.";
        header('Location: ' . URL_BASE . '/?url=transferencias/index'); exit;
    }

    // ── Completar transferencia ────────────────────────────────
    public function completar($id) {
        $db = $this->db();

        $stmt = $db->prepare(
            "SELECT * FROM transferencias_sucursales
             WHERE id = :id AND tenant_id = :tid AND estado = 'pendiente'"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $t = $stmt->fetch();

        if (!$t) {
            $_SESSION['error'] = 'Transferencia no encontrada o ya fue procesada.';
            header('Location: ' . URL_BASE . '/?url=transferencias/index'); exit;
        }

        // Reverifica disponibilidad al momento de aprobar (puede haber cambiado)
        $stmt = $db->prepare(
            "SELECT nombre, cantidad_disponible FROM inventario
             WHERE id = :id AND tenant_id = :tid AND sucursal_id = :sid"
        );
        $stmt->execute([
            ':id'  => $t['inventario_id'],
            ':tid' => $this->tenantId(),
            ':sid' => $t['sucursal_origen_id'],
        ]);
        $productoOrigen = $stmt->fetch();

        if (!$productoOrigen) {
            $_SESSION['error'] = 'El producto ya no existe en la sucursal origen.';
            header('Location: ' . URL_BASE . '/?url=transferencias/index'); exit;
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            // Con el producto bloqueado, se revisa que lo que sale no
            // este comprometido para reservas futuras (ni que alguien
            // lo haya entregado mientras la transferencia esperaba)
            $db->prepare("SELECT id FROM inventario WHERE id = :id AND tenant_id = :tid FOR UPDATE")
               ->execute([':id' => $t['inventario_id'], ':tid' => $this->tenantId()]);
            $retirables = unidadesRetirables((int) $t['inventario_id'], $this->tenantId());
            if ((int) $t['cantidad'] > $retirables['cantidad']) {
                throw new RuntimeException(mensajeNoRetirable($productoOrigen['nombre'], (int) $t['cantidad'], $retirables));
            }

            // Actualizar estado de la transferencia
            $db->prepare(
                "UPDATE transferencias_sucursales SET estado = 'completada' WHERE id = :id AND tenant_id = :tid"
            )->execute([':id' => $id, ':tid' => $this->tenantId()]);

            // Descontar del origen
            $db->prepare(
                "UPDATE inventario
                 SET cantidad_disponible = cantidad_disponible - :cant,
                     cantidad_total      = cantidad_total      - :cant2
                 WHERE id = :id AND tenant_id = :tid AND sucursal_id = :sid"
            )->execute([
                ':cant'  => $t['cantidad'],
                ':cant2' => $t['cantidad'],
                ':id'    => $t['inventario_id'],
                ':tid'   => $this->tenantId(),
                ':sid'   => $t['sucursal_origen_id'],
            ]);

            // Busca si ya existe el mismo producto (por nombre, case-insensitive)
            // en la sucursal destino para no duplicar
            $stmt = $db->prepare(
                "SELECT id FROM inventario
                 WHERE tenant_id = :tid
                   AND sucursal_id = :sid
                   AND LOWER(nombre) = LOWER(:nombre)"
            );
            $stmt->execute([
                ':tid'    => $this->tenantId(),
                ':sid'    => $t['sucursal_destino_id'],
                ':nombre' => $productoOrigen['nombre'],
            ]);
            $existeEnDestino = $stmt->fetch();

            if ($existeEnDestino) {
                // Ya existe — solo suma las cantidades al registro existente
                $db->prepare(
                    "UPDATE inventario
                     SET cantidad_disponible = cantidad_disponible + :cant,
                         cantidad_total      = cantidad_total      + :cant2
                     WHERE id = :id AND tenant_id = :tid"
                )->execute([
                    ':cant'  => $t['cantidad'],
                    ':cant2' => $t['cantidad'],
                    ':id'    => $existeEnDestino['id'],
                    ':tid'   => $this->tenantId(),
                ]);
            } else {
                // No existe — crea el registro copiando todos los datos del origen
                $db->prepare(
                    "INSERT INTO inventario
                        (tenant_id, sucursal_id, categoria_id, nombre, descripcion,
                         precio_alquiler, cantidad_total, cantidad_disponible)
                     SELECT tenant_id, :sid_destino, categoria_id, nombre, descripcion,
                            precio_alquiler, :cant, :cant2
                     FROM inventario
                     WHERE id = :id AND tenant_id = :tid"
                )->execute([
                    ':sid_destino' => $t['sucursal_destino_id'],
                    ':cant'        => $t['cantidad'],
                    ':cant2'       => $t['cantidad'],
                    ':id'          => $t['inventario_id'],
                    ':tid'         => $this->tenantId(),
                ]);
            }

            $db->commit();
            $_SESSION['exito'] = "Transferencia completada. {$t['cantidad']} unidades de \"{$productoOrigen['nombre']}\" movidas correctamente.";

        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            // Solo nuestros RuntimeException se muestran tal cual; los de
            // base de datos (PDOException hereda de RuntimeException) y
            // cualquier otro error van al log
            $_SESSION['error'] = ($e instanceof PDOException || !($e instanceof RuntimeException))
                ? mensajeError($e, 'Error al completar la transferencia.')
                : $e->getMessage();
        }

        header('Location: ' . URL_BASE . '/?url=transferencias/index'); exit;
    }

    // ── Rechazar transferencia ─────────────────────────────────
    public function rechazar($id) {
        $stmt = $this->db()->prepare(
            "SELECT * FROM transferencias_sucursales
             WHERE id = :id AND tenant_id = :tid AND estado = 'pendiente'"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);

        if (!$stmt->fetch()) {
            $_SESSION['error'] = 'Transferencia no encontrada o ya fue procesada.';
            header('Location: ' . URL_BASE . '/?url=transferencias/index'); exit;
        }

        $this->db()->prepare(
            "UPDATE transferencias_sucursales SET estado = 'rechazada'
             WHERE id = :id AND tenant_id = :tid"
        )->execute([':id' => $id, ':tid' => $this->tenantId()]);

        $_SESSION['exito'] = 'Transferencia rechazada.';
        header('Location: ' . URL_BASE . '/?url=transferencias/index'); exit;
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
