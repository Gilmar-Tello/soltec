<?php

class InventarioControlador {

    private function db() { return obtenerConexion(); }

    private function tenantId(): int {
        return (int) $_SESSION['tenant_id'];
    }

    // ── Listado ────────────────────────────────────────────────
    public function index() {
        $sucursalFiltro = intval($_GET['sucursal'] ?? 0);
        $busqueda       = trim($_GET['busqueda'] ?? '');
        $sucursales     = $this->obtenerSucursales();

        $sql = "SELECT i.*, c.nombre AS categoria_nombre, s.nombre AS sucursal_nombre
                FROM inventario i
                LEFT JOIN categorias_inventario c ON c.id = i.categoria_id
                INNER JOIN sucursales s ON s.id = i.sucursal_id
                WHERE i.tenant_id = :tid";
        $params = [':tid' => $this->tenantId()];

        if ($sucursalFiltro) {
            $sql .= " AND i.sucursal_id = :sid";
            $params[':sid'] = $sucursalFiltro;
        }

        if ($busqueda) {
            $sql .= " AND i.nombre LIKE :busqueda";
            $params[':busqueda'] = "%$busqueda%";
        }

        $sql .= " ORDER BY s.nombre ASC, i.nombre ASC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $inventario = $stmt->fetchAll();

        $tituloPagina = 'Inventario';
        include RAIZ . '/app/vistas/inventario/index.php';
    }

    // ── Formulario nuevo producto ───────────────────────────────
    public function nuevo() {
        $sucursales = $this->obtenerSucursales();
        $categorias = $this->obtenerCategorias();

        if (empty($sucursales)) {
            $_SESSION['error'] = 'Primero tenés que crear al menos una sucursal antes de registrar inventario.';
            header('Location: ' . URL_BASE . '/?url=sucursales/index'); exit;
        }

        $tituloPagina = 'Nuevo Producto';
        include RAIZ . '/app/vistas/inventario/form.php';
    }

    // ── Guardar nuevo producto ───────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
        }

        $datos = $this->leerDatosFormulario();

        if (!$datos['nombre'] || !$datos['sucursal_id'] || $datos['precio_alquiler'] === null) {
            $_SESSION['error'] = 'Nombre, sucursal y precio de alquiler son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=inventario/nuevo'); exit;
        }

        // Verifica duplicado case-insensitive: mismo nombre en la misma sucursal
        $stmt = $this->db()->prepare(
            "SELECT id FROM inventario
             WHERE tenant_id = :tid AND sucursal_id = :sid
             AND LOWER(nombre) = LOWER(:nombre)"
        );
        $stmt->execute([
            ':tid'    => $this->tenantId(),
            ':sid'    => $datos['sucursal_id'],
            ':nombre' => $datos['nombre'],
        ]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Ya existe un producto llamado \"{$datos['nombre']}\" en esa sucursal. Editalo para actualizar la cantidad.";
            header('Location: ' . URL_BASE . '/?url=inventario/nuevo'); exit;
        }

        // Foto del producto (opcional). Si falla, el producto se
        // guarda igual: no se pierde el trabajo por una foto.
        $errorFoto = null;
        $fotoPath  = guardarImagenSubida($_FILES['foto'] ?? [], 'productos', 'prod', $errorFoto);

        $stmt = $this->db()->prepare(
            "INSERT INTO inventario
                (tenant_id, sucursal_id, categoria_id, nombre, descripcion, foto_path,
                 estanteria, columna, fila, precio_alquiler, cantidad_total, cantidad_disponible)
             VALUES
                (:tid, :sucursal, :categoria, :nombre, :descripcion, :foto,
                 :estanteria, :columna, :fila, :precio, :total, :disponible)"
        );
        $stmt->execute([
            ':tid'         => $this->tenantId(),
            ':sucursal'    => $datos['sucursal_id'],
            ':categoria'   => $datos['categoria_id'],
            ':nombre'      => $datos['nombre'],
            ':descripcion' => $datos['descripcion'],
            ':foto'        => $fotoPath,
            ':estanteria'  => $datos['estanteria'],
            ':columna'     => $datos['columna'],
            ':fila'        => $datos['fila'],
            ':precio'      => $datos['precio_alquiler'],
            ':total'       => $datos['cantidad_total'],
            ':disponible'  => $datos['cantidad_total'],
        ]);

        $_SESSION['exito'] = "Producto '{$datos['nombre']}' agregado al inventario."
                           . ($errorFoto ? ' (La foto no se pudo guardar: ' . $errorFoto . ')' : '');
        header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
    }

    // ── Formulario editar ──────────────────────────────────────
    public function editar($id) {
        $stmt = $this->db()->prepare(
            "SELECT * FROM inventario WHERE id = :id AND tenant_id = :tid"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $producto = $stmt->fetch();

        if (!$producto) {
            $_SESSION['error'] = 'Producto no encontrado.';
            header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
        }

        $sucursales = $this->obtenerSucursales();
        $categorias = $this->obtenerCategorias();

        $tituloPagina = 'Editar — ' . $producto['nombre'];
        include RAIZ . '/app/vistas/inventario/form.php';
    }

    // ── Actualizar ──────────────────────────────────────────────
    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
        }

        $id = intval($_POST['id']);
        $datos = $this->leerDatosFormulario();

        if (!$datos['nombre'] || !$datos['sucursal_id'] || $datos['precio_alquiler'] === null) {
            $_SESSION['error'] = 'Nombre, sucursal y precio de alquiler son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=inventario/editar/' . $id); exit;
        }

        // Verifica duplicado case-insensitive excluyendo el producto actual
        $stmt = $this->db()->prepare(
            "SELECT id FROM inventario
             WHERE tenant_id = :tid AND sucursal_id = :sid
             AND LOWER(nombre) = LOWER(:nombre) AND id != :id"
        );
        $stmt->execute([
            ':tid'    => $this->tenantId(),
            ':sid'    => $datos['sucursal_id'],
            ':nombre' => $datos['nombre'],
            ':id'     => $id,
        ]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Ya existe otro producto llamado \"{$datos['nombre']}\" en esa sucursal.";
            header('Location: ' . URL_BASE . '/?url=inventario/editar/' . $id); exit;
        }

        $db = $this->db();

        // Trae cantidades actuales para calcular cuántas estaban "ocupadas"
        $stmt = $db->prepare("SELECT cantidad_total, cantidad_disponible, foto_path FROM inventario WHERE id = :id AND tenant_id = :tid");
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $actual = $stmt->fetch();

        if (!$actual) {
            $_SESSION['error'] = 'Producto no encontrado.';
            header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
        }

        $ocupadas = $actual['cantidad_total'] - $actual['cantidad_disponible'];
        $nuevaDisponible = max(0, $datos['cantidad_total'] - $ocupadas);

        // Foto: si suben una nueva reemplaza a la anterior; si marcan
        // "quitar foto" se borra; si no tocan nada, se queda la que hay.
        $errorFoto = null;
        $fotoNueva = guardarImagenSubida($_FILES['foto'] ?? [], 'productos', 'prod', $errorFoto);
        $fotoFinal = $actual['foto_path'];

        if ($fotoNueva) {
            borrarImagen($actual['foto_path']);
            $fotoFinal = $fotoNueva;
        } elseif (!empty($_POST['quitar_foto'])) {
            borrarImagen($actual['foto_path']);
            $fotoFinal = null;
        }

        $stmt = $db->prepare(
            "UPDATE inventario SET
                sucursal_id=:sucursal, categoria_id=:categoria, nombre=:nombre,
                descripcion=:descripcion, foto_path=:foto,
                estanteria=:estanteria, columna=:columna, fila=:fila,
                precio_alquiler=:precio,
                cantidad_total=:total, cantidad_disponible=:disponible
             WHERE id=:id AND tenant_id=:tid"
        );
        $stmt->execute([
            ':sucursal'    => $datos['sucursal_id'],
            ':categoria'   => $datos['categoria_id'],
            ':nombre'      => $datos['nombre'],
            ':descripcion' => $datos['descripcion'],
            ':foto'        => $fotoFinal,
            ':estanteria'  => $datos['estanteria'],
            ':columna'     => $datos['columna'],
            ':fila'        => $datos['fila'],
            ':precio'      => $datos['precio_alquiler'],
            ':total'       => $datos['cantidad_total'],
            ':disponible'  => $nuevaDisponible,
            ':id'          => $id,
            ':tid'         => $this->tenantId(),
        ]);

        $_SESSION['exito'] = 'Producto actualizado correctamente.'
                           . ($errorFoto ? ' (La foto no se pudo guardar: ' . $errorFoto . ')' : '');
        header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
    }

    // ── Eliminar (solo si no tiene historial relacionado) ───────
    public function eliminar($id) {
        $db = $this->db();

        $stmt = $db->prepare("SELECT nombre, foto_path FROM inventario WHERE id = :id AND tenant_id = :tid");
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $producto = $stmt->fetch();

        if (!$producto) {
            $_SESSION['error'] = 'Producto no encontrado.';
            header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
        }

        // Verifica que no tenga reservas asociadas — si las tiene, no se borra (se preserva el historial)
        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM detalle_reserva WHERE inventario_id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->fetch()['total'] > 0) {
            $_SESSION['error'] = "No se puede eliminar '{$producto['nombre']}' porque tiene reservas asociadas. Podés editarlo para poner la cantidad en 0.";
            header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
        }

        $stmt = $db->prepare("DELETE FROM inventario WHERE id = :id AND tenant_id = :tid");
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);

        borrarImagen($producto['foto_path'] ?? null);

        $_SESSION['exito'] = "'{$producto['nombre']}' fue eliminado del inventario.";
        header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
    }

    // ── Helpers privados ──────────────────────────────────────
    private function leerDatosFormulario(): array {
        return [
            'nombre'          => trim($_POST['nombre'] ?? ''),
            'descripcion'     => trim($_POST['descripcion'] ?? ''),
            'sucursal_id'     => intval($_POST['sucursal_id'] ?? 0) ?: null,
            'categoria_id'    => intval($_POST['categoria_id'] ?? 0) ?: null,
            'precio_alquiler' => isset($_POST['precio_alquiler']) && $_POST['precio_alquiler'] !== ''
                                    ? floatval($_POST['precio_alquiler']) : null,
            'cantidad_total'  => max(0, intval($_POST['cantidad_total'] ?? 0)),
            // Ubicacion en bodega, tipo celda de Excel: estanteria + columna + fila
            'estanteria'      => mb_substr(trim($_POST['estanteria'] ?? ''), 0, 50) ?: null,
            'columna'         => mb_substr(trim($_POST['columna']    ?? ''), 0, 10) ?: null,
            'fila'            => mb_substr(trim($_POST['fila']       ?? ''), 0, 10) ?: null,
        ];
    }

    private function obtenerSucursales(): array {
        $stmt = $this->db()->prepare("SELECT id, nombre FROM sucursales WHERE tenant_id = :tid AND activa = 1 ORDER BY nombre");
        $stmt->execute([':tid' => $this->tenantId()]);
        return $stmt->fetchAll();
    }

    private function obtenerCategorias(): array {
        $stmt = $this->db()->prepare("SELECT id, nombre FROM categorias_inventario WHERE tenant_id = :tid ORDER BY nombre");
        $stmt->execute([':tid' => $this->tenantId()]);
        return $stmt->fetchAll();
    }
}
