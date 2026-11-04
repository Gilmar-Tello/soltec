<?php

class ComboControlador {

    private function db() { return obtenerConexion(); }

    private function tenantId(): int {
        return (int) $_SESSION['tenant_id'];
    }

    // ── Listado ────────────────────────────────────────────────
    public function index() {
        $stmt = $this->db()->prepare(
            "SELECT c.*,
                    COUNT(cp.id) AS total_productos
             FROM combos c
             LEFT JOIN combo_productos cp ON cp.combo_id = c.id
             WHERE c.tenant_id = :tid
             GROUP BY c.id
             ORDER BY c.nombre ASC"
        );
        $stmt->execute([':tid' => $this->tenantId()]);
        $combos = $stmt->fetchAll();

        $tituloPagina = 'Combos';
        include RAIZ . '/app/vistas/combos/index.php';
    }

    // ── Formulario nuevo ───────────────────────────────────────
    public function nuevo() {
        $sucursales = $this->obtenerSucursales();
        $tituloPagina = 'Nuevo Combo';
        include RAIZ . '/app/vistas/combos/form.php';
    }

    // ── Guardar ────────────────────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=combos/index'); exit;
        }

        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $productos   = $_POST['productos'] ?? [];

        if (!$nombre) {
            $_SESSION['error'] = 'El nombre del combo es obligatorio.';
            header('Location: ' . URL_BASE . '/?url=combos/nuevo'); exit;
        }

        // Filtra líneas vacías
        $lineas = [];
        foreach ($productos as $l) {
            $invId = intval($l['inventario_id'] ?? 0);
            $cant  = intval($l['cantidad'] ?? 0);
            if ($invId && $cant > 0) {
                $lineas[] = ['inventario_id' => $invId, 'cantidad' => $cant];
            }
        }

        if (empty($lineas)) {
            $_SESSION['error'] = 'Agregá al menos un producto al combo.';
            header('Location: ' . URL_BASE . '/?url=combos/nuevo'); exit;
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            $db->prepare(
                "INSERT INTO combos (tenant_id, nombre, descripcion, activo)
                 VALUES (:tid, :nombre, :desc, 1)"
            )->execute([
                ':tid'    => $this->tenantId(),
                ':nombre' => $nombre,
                ':desc'   => $descripcion,
            ]);
            $comboId = $db->lastInsertId();

            foreach ($lineas as $l) {
                $db->prepare(
                    "INSERT INTO combo_productos (combo_id, tenant_id, inventario_id, cantidad)
                     VALUES (:cid, :tid, :iid, :cant)"
                )->execute([
                    ':cid'  => $comboId,
                    ':tid'  => $this->tenantId(),
                    ':iid'  => $l['inventario_id'],
                    ':cant' => $l['cantidad'],
                ]);
            }

            $db->commit();
            $_SESSION['exito'] = "Combo \"$nombre\" creado correctamente.";
            header('Location: ' . URL_BASE . '/?url=combos/index'); exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al guardar el combo. Intenta de nuevo.');
            header('Location: ' . URL_BASE . '/?url=combos/nuevo'); exit;
        }
    }

    // ── Editar ─────────────────────────────────────────────────
    public function editar($id) {
        $stmt = $this->db()->prepare(
            "SELECT * FROM combos WHERE id = :id AND tenant_id = :tid"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $combo = $stmt->fetch();

        if (!$combo) {
            $_SESSION['error'] = 'Combo no encontrado.';
            header('Location: ' . URL_BASE . '/?url=combos/index'); exit;
        }

        $stmt = $this->db()->prepare(
            "SELECT cp.*, i.nombre AS producto_nombre, i.precio_alquiler
             FROM combo_productos cp
             INNER JOIN inventario i ON i.id = cp.inventario_id
             WHERE cp.combo_id = :cid AND cp.tenant_id = :tid"
        );
        $stmt->execute([':cid' => $id, ':tid' => $this->tenantId()]);
        $productosCombo = $stmt->fetchAll();

        $sucursales = $this->obtenerSucursales();
        $tituloPagina = 'Editar — ' . $combo['nombre'];
        include RAIZ . '/app/vistas/combos/form.php';
    }

    // ── Actualizar ─────────────────────────────────────────────
    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=combos/index'); exit;
        }

        $id          = intval($_POST['id']);
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $productos   = $_POST['productos'] ?? [];

        if (!$nombre) {
            $_SESSION['error'] = 'El nombre es obligatorio.';
            header('Location: ' . URL_BASE . '/?url=combos/editar/' . $id); exit;
        }

        $lineas = [];
        foreach ($productos as $l) {
            $invId = intval($l['inventario_id'] ?? 0);
            $cant  = intval($l['cantidad'] ?? 0);
            if ($invId && $cant > 0) {
                $lineas[] = ['inventario_id' => $invId, 'cantidad' => $cant];
            }
        }

        if (empty($lineas)) {
            $_SESSION['error'] = 'El combo debe tener al menos un producto.';
            header('Location: ' . URL_BASE . '/?url=combos/editar/' . $id); exit;
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            $db->prepare(
                "UPDATE combos SET nombre=:nombre, descripcion=:desc WHERE id=:id AND tenant_id=:tid"
            )->execute([':nombre' => $nombre, ':desc' => $descripcion, ':id' => $id, ':tid' => $this->tenantId()]);

            // Reemplaza los productos del combo
            $db->prepare("DELETE FROM combo_productos WHERE combo_id = :cid AND tenant_id = :tid")
               ->execute([':cid' => $id, ':tid' => $this->tenantId()]);

            foreach ($lineas as $l) {
                $db->prepare(
                    "INSERT INTO combo_productos (combo_id, tenant_id, inventario_id, cantidad)
                     VALUES (:cid, :tid, :iid, :cant)"
                )->execute([
                    ':cid'  => $id,
                    ':tid'  => $this->tenantId(),
                    ':iid'  => $l['inventario_id'],
                    ':cant' => $l['cantidad'],
                ]);
            }

            $db->commit();
            $_SESSION['exito'] = 'Combo actualizado correctamente.';
            header('Location: ' . URL_BASE . '/?url=combos/index'); exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al actualizar. Intenta de nuevo.');
            header('Location: ' . URL_BASE . '/?url=combos/editar/' . $id); exit;
        }
    }

    // ── Toggle activo ──────────────────────────────────────────
    public function toggleActivo($id) {
        $stmt = $this->db()->prepare(
            "SELECT activo, nombre FROM combos WHERE id = :id AND tenant_id = :tid"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $combo = $stmt->fetch();

        if (!$combo) {
            $_SESSION['error'] = 'Combo no encontrado.';
            header('Location: ' . URL_BASE . '/?url=combos/index'); exit;
        }

        $nuevo = $combo['activo'] ? 0 : 1;
        $this->db()->prepare(
            "UPDATE combos SET activo = :a WHERE id = :id AND tenant_id = :tid"
        )->execute([':a' => $nuevo, ':id' => $id, ':tid' => $this->tenantId()]);

        $_SESSION['exito'] = $nuevo ? "Combo \"{$combo['nombre']}\" activado." : "Combo \"{$combo['nombre']}\" desactivado.";
        header('Location: ' . URL_BASE . '/?url=combos/index'); exit;
    }

    // ── Combos disponibles vía AJAX (para nueva reserva) ───────
    public function ajax() {
        header('Content-Type: application/json');
        $sucursalId = intval($_GET['sucursal_id'] ?? 0);

        // Vitrina: los combos se arman con el stock de su bodega
        $sucursalId = $sucursalId ? sucursalDeStock($sucursalId, $this->tenantId()) : 0;
        if (!$sucursalId) { echo json_encode([]); exit; }

        // Con las fechas de la reserva, el máximo de combos se calcula
        // con lo libre en esos días, no con lo que hay en bodega hoy
        // Acepta día suelto (día completo) o día con hora (AAAA-MM-DDTHH:MM)
        $desde = fechaOMomentoISO($_GET['desde'] ?? '');
        $hasta = fechaOMomentoISO($_GET['hasta'] ?? '');
        $porFechas = rangoFechasValido($desde, $hasta);

        // Trae todos los combos activos del tenant
        $stmt = $this->db()->prepare(
            "SELECT c.id, c.nombre, c.descripcion FROM combos c
             WHERE c.tenant_id = :tid AND c.activo = 1
             ORDER BY c.nombre ASC"
        );
        $stmt->execute([':tid' => $this->tenantId()]);
        $combos = $stmt->fetchAll();

        $resultado = [];

        foreach ($combos as $combo) {
            // Trae productos del combo con su disponibilidad en la sucursal indicada
            $stmt2 = $this->db()->prepare(
                "SELECT cp.cantidad AS cant_combo, cp.inventario_id,
                        i.nombre AS producto_nombre, i.precio_alquiler,
                        COALESCE(i.cantidad_disponible, 0) AS cantidad_disponible
                 FROM combo_productos cp
                 LEFT JOIN inventario i ON i.id = cp.inventario_id
                    AND i.sucursal_id = :sid AND i.tenant_id = :tid
                 WHERE cp.combo_id = :cid AND cp.tenant_id = :tid2"
            );
            $stmt2->execute([
                ':tid'  => $this->tenantId(),
                ':tid2' => $this->tenantId(),
                ':sid'  => $sucursalId,
                ':cid'  => $combo['id'],
            ]);
            $productos = $stmt2->fetchAll();

            if (empty($productos)) continue;

            if ($porFechas) {
                $libres = unidadesLibres(array_column($productos, 'inventario_id'), $desde, $hasta, $this->tenantId());
                foreach ($productos as &$p) {
                    $p['cantidad_disponible'] = $libres[(int) $p['inventario_id']] ?? 0;
                }
                unset($p);
            }

            // Calcula cuántos combos completos se pueden armar con el stock actual
            $maxCombos = PHP_INT_MAX;
            foreach ($productos as $p) {
                if ($p['cant_combo'] <= 0) continue;
                $posibles = floor($p['cantidad_disponible'] / $p['cant_combo']);
                $maxCombos = min($maxCombos, $posibles);
            }

            // Solo muestra combos con al menos 1 disponible
            if ($maxCombos < 1) continue;

            $combo['productos']  = $productos;
            $combo['max_combos'] = $maxCombos === PHP_INT_MAX ? 0 : (int) $maxCombos;

            // Precio base de 1 combo (suma de precio × cantidad por producto)
            $combo['precio_combo'] = array_reduce($productos, fn($carry, $p) =>
                $carry + ($p['precio_alquiler'] * $p['cant_combo']), 0
            );

            $resultado[] = $combo;
        }

        echo json_encode($resultado);
        exit;
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
