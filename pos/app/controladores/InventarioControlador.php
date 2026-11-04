<?php

class InventarioControlador {

    private function db() { return obtenerConexion(); }

    // ── Listado principal ─────────────────────────────────────
    public function index() {
        $tid = tenantActual();
        $busqueda    = trim($_GET['busqueda'] ?? '');
        $filtroCateg = intval($_GET['categoria'] ?? 0);

        $sql = "SELECT p.*, c.nombre AS categoria, u.abreviatura AS unidad,
                       COALESCE(s.cantidad, 0) AS stock_actual
                FROM productos p
                JOIN categorias c      ON p.categoria_id = c.id
                JOIN unidades_medida u ON p.unidad_id    = u.id
                LEFT JOIN stock s      ON s.producto_id  = p.id AND s.tenant_id = :tid1
                WHERE p.activo = 1 AND p.tenant_id = :tid2";
        $params = [':tid1' => $tid, ':tid2' => $tid];
        // El filtrado (texto y categoria) lo hace el navegador al instante, sin recargar.
        // $busqueda y $filtroCateg solo se usan para dejar los filtros puestos al recargar.
        $sql .= sqlSucursal('p');   // el catalogo es el de la sucursal que se esta viendo
        $sql .= " ORDER BY p.nombre ASC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $productos  = $stmt->fetchAll();

        $stmt2 = $this->db()->prepare("SELECT * FROM categorias WHERE activo=1 AND tenant_id=:tid ORDER BY nombre");
        $stmt2->execute([':tid' => $tid]);
        $categorias = $stmt2->fetchAll();

        $mostrarFotos = funcionActiva('fotos_productos');   // la enciende el panel maestro
        $tidVista     = $tid;
        $tituloPagina = 'Inventario';
        include RAIZ . '/app/vistas/inventario/lista.php';
    }

    // ── PDF: todos los productos ──────────────────────────────
    public function pdfProductos() {
        $tid = tenantActual();
        $stmt = $this->db()->prepare(
            "SELECT p.*, c.nombre AS categoria, u.abreviatura AS unidad,
                    COALESCE(s.cantidad,0) AS stock_actual
             FROM productos p
             JOIN categorias c ON p.categoria_id=c.id
             JOIN unidades_medida u ON p.unidad_id=u.id
             LEFT JOIN stock s ON s.producto_id=p.id AND s.tenant_id=:tid1
             WHERE p.activo=1 AND p.tenant_id=:tid2" . sqlSucursal('p') . " ORDER BY p.nombre"
        );
        $stmt->execute([':tid1'=>$tid, ':tid2'=>$tid]);
        $productos = $stmt->fetchAll();
        $tituloPagina = 'Reporte de Productos';
        $tipoPdf = 'productos';
        ob_end_clean();
        include RAIZ . '/app/vistas/inventario/pdf_vista.php';
        exit;
    }

    // ── PDF: stock bajo ───────────────────────────────────────
    public function pdfStockBajo() {
        $tid = tenantActual();
        $stmt = $this->db()->prepare(
            "SELECT p.*, c.nombre AS categoria, u.abreviatura AS unidad,
                    COALESCE(s.cantidad,0) AS stock_actual
             FROM productos p
             JOIN categorias c ON p.categoria_id=c.id
             JOIN unidades_medida u ON p.unidad_id=u.id
             LEFT JOIN stock s ON s.producto_id=p.id AND s.tenant_id=:tid1
             WHERE p.activo=1 AND p.tenant_id=:tid2" . sqlSucursal('p') . " AND COALESCE(s.cantidad,0) <= p.stock_minimo
             ORDER BY stock_actual ASC"
        );
        $stmt->execute([':tid1'=>$tid, ':tid2'=>$tid]);
        $productos = $stmt->fetchAll();
        $tituloPagina = 'Productos con Stock Bajo';
        $tipoPdf = 'stock_bajo';
        ob_end_clean();
        include RAIZ . '/app/vistas/inventario/pdf_vista.php';
        exit;
    }

    // ── PDF: próximos a vencer ────────────────────────────────
    public function pdfVencimientos() {
        $tid = tenantActual();
        $stmt = $this->db()->prepare(
            "SELECT p.*, c.nombre AS categoria, u.abreviatura AS unidad,
                    COALESCE(s.cantidad,0) AS stock_actual,
                    DATEDIFF(p.fecha_vencimiento, CURDATE()) AS dias_restantes
             FROM productos p
             JOIN categorias c ON p.categoria_id=c.id
             JOIN unidades_medida u ON p.unidad_id=u.id
             LEFT JOIN stock s ON s.producto_id=p.id AND s.tenant_id=:tid1
             WHERE p.activo=1 AND p.tenant_id=:tid2
               " . sqlSucursal('p') . " AND p.fecha_vencimiento IS NOT NULL
               AND p.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY p.fecha_vencimiento ASC"
        );
        $stmt->execute([':tid1'=>$tid, ':tid2'=>$tid]);
        $productos = $stmt->fetchAll();
        $tituloPagina = 'Productos Próximos a Vencer';
        $tipoPdf = 'vencimientos';
        ob_end_clean();
        include RAIZ . '/app/vistas/inventario/pdf_vista.php';
        exit;
    }

    // ── Nuevo producto ────────────────────────────────────────
    public function nuevo() {
        $tid = tenantActual();
        $stmt1 = $this->db()->prepare("SELECT * FROM categorias WHERE activo=1 AND tenant_id=:tid ORDER BY nombre");
        $stmt1->execute([':tid'=>$tid]);
        $categorias = $stmt1->fetchAll();

        $stmt2 = $this->db()->prepare("SELECT * FROM unidades_medida WHERE activo=1 AND tenant_id=:tid ORDER BY nombre");
        $stmt2->execute([':tid'=>$tid]);
        $unidades = $stmt2->fetchAll();

        $stmt3 = $this->db()->prepare("SELECT * FROM proveedores WHERE activo=1 AND tenant_id=:tid ORDER BY nombre");
        $stmt3->execute([':tid'=>$tid]);
        $proveedores = $stmt3->fetchAll();

        $tituloPagina = 'Nuevo Producto';
        $producto     = null;
        include RAIZ . '/app/vistas/inventario/formulario.php';
    }

    // ── Guardar producto ──────────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=inventario/index'); exit; }
        $tid = tenantActual();
        $campos = $this->extraerCampos();
        $si     = floatval($_POST['stock_inicial'] ?? 0);
        // Sucursal del producto nuevo: el administrador la elige en el formulario; los demas usuarios, la suya.
        $colSuc = ''; $valSuc = '';
        if (catalogoPorSucursal()) {
            $colSuc = ',sucursal_id'; $valSuc = ',:sucursal_id';
            $campos[':sucursal_id'] = $this->sucursalParaProductoNuevo();
        }
        $error  = $this->validarProducto($campos, $tid, 0);
        if (!$error && (!is_finite($si) || $si < 0)) {
            $error = 'El stock inicial no puede ser negativo.';
        }
        if ($error) {
            $_SESSION['error'] = $error;
            header('Location: ' . URL_BASE . '/?url=inventario/nuevo'); exit;
        }

        // Foto (solo si el negocio tiene la funcion encendida)
        $fotoNueva = null;
        if (funcionActiva('fotos_productos')) {
            [$fotoNueva, $errorFoto] = procesarFotoProducto($tid);
            if ($errorFoto) {
                $_SESSION['error'] = $errorFoto;
                header('Location: ' . URL_BASE . '/?url=inventario/nuevo'); exit;
            }
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO productos
                (tenant_id,codigo,nombre,descripcion,categoria_id,unidad_id,proveedor_id,precio_compra,precio_minimo,precio_venta_real,stock_minimo,fecha_vencimiento" . $colSuc . ")
                VALUES(:tid,:codigo,:nombre,:descripcion,:categoria_id,:unidad_id,:proveedor_id,:precio_compra,:precio_minimo,:precio_venta_real,:stock_minimo,:fecha_vencimiento" . $valSuc . ")");
            $campos[':tid'] = $tid;
            $stmt->execute($campos);
            $pid = $db->lastInsertId();
            if ($fotoNueva !== null) {
                $db->prepare("UPDATE productos SET foto=:f WHERE id=:id AND tenant_id=:tid")
                   ->execute([':f'=>$fotoNueva, ':id'=>$pid, ':tid'=>$tid]);
            }
            $db->prepare("INSERT INTO stock (tenant_id,producto_id,cantidad) VALUES(:tid,:pid,:c)")
               ->execute([':tid'=>$tid, ':pid'=>$pid, ':c'=>$si]);
            if ($si > 0) {
                $db->prepare("INSERT INTO movimientos_stock (tenant_id,producto_id,usuario_id,tipo,origen,cantidad,stock_anterior,stock_nuevo,motivo)
                    VALUES(:tid,:pid,:uid,'entrada','ajuste_manual',:c,0,:sn,'Stock inicial al crear producto')")
                   ->execute([':tid'=>$tid,':pid'=>$pid,':uid'=>$_SESSION['usuario_id'],':c'=>$si,':sn'=>$si]);
            }
            $db->commit();
            $_SESSION['exito'] = "Producto '{$campos[':nombre']}' guardado"
                . (catalogoPorSucursal() && sucursalesHabilitadas() ? ' en ' . (nombresSucursales()[(int) ($campos[':sucursal_id'] ?? 0)] ?? NOMBRE_SUCURSAL_PRINCIPAL) : '') . '.';
            header('Location: ' . URL_BASE . '/?url=inventario/index');
        } catch (Throwable $e) {
            $db->rollBack();
            borrarFotoProducto($fotoNueva, $tid);   // no dejar la foto huerfana
            $_SESSION['error'] = mensajeError($e, 'No se pudo guardar el producto. Intentá de nuevo.');
            header('Location: ' . URL_BASE . '/?url=inventario/nuevo');
        }
        exit;
    }

    // ── Editar producto ───────────────────────────────────────
    public function editar($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT * FROM productos WHERE id=:id AND tenant_id=:tid" . sqlSucursal());
        $stmt->execute([':id'=>$id, ':tid'=>$tid]);
        $producto = $stmt->fetch();
        if (!$producto) { header('Location: ' . URL_BASE . '/?url=inventario/index'); exit; }

        $stmt1 = $this->db()->prepare("SELECT * FROM categorias WHERE activo=1 AND tenant_id=:tid ORDER BY nombre");
        $stmt1->execute([':tid'=>$tid]);
        $categorias = $stmt1->fetchAll();

        $stmt2 = $this->db()->prepare("SELECT * FROM unidades_medida WHERE activo=1 AND tenant_id=:tid ORDER BY nombre");
        $stmt2->execute([':tid'=>$tid]);
        $unidades = $stmt2->fetchAll();

        $stmt3 = $this->db()->prepare("SELECT * FROM proveedores WHERE activo=1 AND tenant_id=:tid ORDER BY nombre");
        $stmt3->execute([':tid'=>$tid]);
        $proveedores = $stmt3->fetchAll();

        $tituloPagina = 'Editar Producto';
        include RAIZ . '/app/vistas/inventario/formulario.php';
    }

    // ── Actualizar producto ───────────────────────────────────
    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=inventario/index'); exit; }
        $tid = tenantActual();
        $campos = $this->extraerCampos();
        $idProducto = intval($_POST['id'] ?? 0);
        if (!$this->productoEnVista($idProducto, $tid)) {   // solo se edita lo de la sucursal que se esta viendo
            $_SESSION['error'] = 'Producto no encontrado.';
            header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
        }
        $error = $this->validarProducto($campos, $tid, $idProducto);
        if ($error) {
            $_SESSION['error'] = $error;
            header('Location: ' . URL_BASE . '/?url=inventario/editar/' . $idProducto); exit;
        }
        // Foto: subir una nueva o quitar la actual (solo si el negocio tiene la funcion encendida)
        $conFotos = funcionActiva('fotos_productos');
        $fotoNueva = null; $quitarFoto = false; $fotoAnterior = null;
        if ($conFotos) {
            [$fotoNueva, $errorFoto] = procesarFotoProducto($tid);
            if ($errorFoto) {
                $_SESSION['error'] = $errorFoto;
                header('Location: ' . URL_BASE . '/?url=inventario/editar/' . $idProducto); exit;
            }
            $quitarFoto = !empty($_POST['quitar_foto']);
            $qf = $this->db()->prepare("SELECT foto FROM productos WHERE id=:id AND tenant_id=:tid");
            $qf->execute([':id'=>$idProducto, ':tid'=>$tid]);
            $fotoAnterior = $qf->fetchColumn() ?: null;
        }

        $campos[':id']  = $idProducto;
        $campos[':tid'] = $tid;
        $this->db()->prepare("UPDATE productos SET
            codigo=:codigo,nombre=:nombre,descripcion=:descripcion,
            categoria_id=:categoria_id,unidad_id=:unidad_id,proveedor_id=:proveedor_id,
            precio_compra=:precio_compra,precio_minimo=:precio_minimo,precio_venta_real=:precio_venta_real,
            stock_minimo=:stock_minimo,fecha_vencimiento=:fecha_vencimiento
            WHERE id=:id AND tenant_id=:tid" . sqlSucursal())->execute($campos);
        if ($conFotos && ($fotoNueva !== null || $quitarFoto)) {
            $this->db()->prepare("UPDATE productos SET foto=:f WHERE id=:id AND tenant_id=:tid")
                 ->execute([':f'=>$fotoNueva, ':id'=>$idProducto, ':tid'=>$tid]);   // null = sin foto
            borrarFotoProducto($fotoAnterior, $tid);
        }
        $_SESSION['exito'] = 'Producto actualizado.';
        header('Location: ' . URL_BASE . '/?url=inventario/index');
        exit;
    }

    // ── Ajustar stock ─────────────────────────────────────────
    public function ajustarStock($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare(
            "SELECT p.*,COALESCE(s.cantidad,0) AS stock_actual FROM productos p
             LEFT JOIN stock s ON s.producto_id=p.id AND s.tenant_id=:tid1
             WHERE p.id=:id AND p.tenant_id=:tid2" . sqlSucursal('p'));
        $stmt->execute([':tid1'=>$tid, ':id'=>$id, ':tid2'=>$tid]);
        $producto = $stmt->fetch();
        if (!$producto) { header('Location: ' . URL_BASE . '/?url=inventario/index'); exit; }
        $tituloPagina = 'Ajustar Stock — ' . $producto['nombre'];
        include RAIZ . '/app/vistas/inventario/ajustar_stock.php';
    }

    // ── Guardar ajuste ────────────────────────────────────────
    public function guardarAjuste() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=inventario/index'); exit; }
        $tid      = tenantActual();
        $pid      = intval($_POST['producto_id']);
        $tipo     = $_POST['tipo'] ?? '';
        $cantidad = floatval($_POST['cantidad'] ?? 0);
        $motivo   = trim($_POST['motivo'] ?? '');
        if (!in_array($tipo, ['entrada', 'salida'], true)) {
            $_SESSION['error'] = 'Tipo de ajuste no válido.';
            header('Location: ' . URL_BASE . '/?url=inventario/ajustarStock/' . $pid); exit;
        }
        if (!is_finite($cantidad) || $cantidad <= 0 || !$motivo) {
            $_SESSION['error'] = 'Ingresá cantidad y motivo.';
            header('Location: ' . URL_BASE . '/?url=inventario/ajustarStock/' . $pid); exit;
        }
        $db = $this->db();

        // Verificar que el producto pertenezca a este tenant
        $check = $db->prepare("SELECT id FROM productos WHERE id=:pid AND tenant_id=:tid" . sqlSucursal());
        $check->execute([':pid'=>$pid, ':tid'=>$tid]);
        if (!$check->fetch()) {
            $_SESSION['error'] = 'Producto no encontrado.';
            header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
        }

        $db->beginTransaction();
        try {
            // FOR UPDATE: el stock no puede cambiar (por una venta) mientras se ajusta
            $s = $db->prepare("SELECT cantidad FROM stock WHERE producto_id=:pid AND tenant_id=:tid FOR UPDATE");
            $s->execute([':pid'=>$pid, ':tid'=>$tid]);
            $fila = $s->fetchColumn();
            if ($fila === false) {
                // Producto sin fila de stock: se crea en cero
                $db->prepare("INSERT INTO stock (tenant_id,producto_id,cantidad) VALUES(:tid,:pid,0)")
                   ->execute([':tid'=>$tid, ':pid'=>$pid]);
            }
            $actual = floatval($fila);

            if ($tipo === 'salida' && $cantidad > $actual + 0.0001) {
                $db->rollBack();
                $_SESSION['error'] = 'Stock insuficiente.';
                header('Location: ' . URL_BASE . '/?url=inventario/ajustarStock/' . $pid); exit;
            }
            $nuevo = $tipo === 'entrada' ? $actual + $cantidad : $actual - $cantidad;
            $db->prepare("UPDATE stock SET cantidad=:n WHERE producto_id=:pid AND tenant_id=:tid")
               ->execute([':n'=>$nuevo,':pid'=>$pid,':tid'=>$tid]);
            $db->prepare("INSERT INTO movimientos_stock (tenant_id,producto_id,usuario_id,tipo,origen,cantidad,stock_anterior,stock_nuevo,motivo)
                VALUES(:tid,:pid,:uid,:tipo,'ajuste_manual',:c,:ant,:nvo,:mot)")
               ->execute([':tid'=>$tid,':pid'=>$pid,':uid'=>$_SESSION['usuario_id'],':tipo'=>$tipo,':c'=>$cantidad,':ant'=>$actual,':nvo'=>$nuevo,':mot'=>$motivo]);
            $db->commit();
            $_SESSION['exito'] = 'Stock ajustado correctamente.';
            header('Location: ' . URL_BASE . '/?url=inventario/index');
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'No se pudo ajustar el stock. Intentá de nuevo.');
            header('Location: ' . URL_BASE . '/?url=inventario/ajustarStock/' . $pid);
        }
        exit;
    }

    // ── Historial movimientos ─────────────────────────────────
    public function movimientos($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT * FROM productos WHERE id=:id AND tenant_id=:tid" . sqlSucursal());
        $stmt->execute([':id'=>$id, ':tid'=>$tid]);
        $producto = $stmt->fetch();
        if (!$producto) { header('Location: ' . URL_BASE . '/?url=inventario/index'); exit; }

        $stmt2 = $this->db()->prepare(
            "SELECT m.*,u.nombre AS usuario_nombre FROM movimientos_stock m
             JOIN usuarios u ON m.usuario_id=u.id
             WHERE m.producto_id=:pid AND m.tenant_id=:tid ORDER BY m.creado_en DESC");
        $stmt2->execute([':pid'=>$id, ':tid'=>$tid]);
        $movimientos  = $stmt2->fetchAll();
        $tituloPagina = 'Historial — ' . $producto['nombre'];
        include RAIZ . '/app/vistas/inventario/movimientos.php';
    }

    // ── Categorías ────────────────────────────────────────────
    public function categorias() {
        $tid = tenantActual();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre'] ?? '');
            $desc   = trim($_POST['descripcion'] ?? '');
            if ($nombre) {
                $this->db()->prepare("INSERT INTO categorias (tenant_id,nombre,descripcion) VALUES(:tid,:n,:d)")
                     ->execute([':tid'=>$tid, ':n'=>$nombre,':d'=>$desc]);
                $_SESSION['exito'] = "Categoría '$nombre' creada.";
            }
            header('Location: ' . URL_BASE . '/?url=inventario/categorias'); exit;
        }
        $stmt = $this->db()->prepare("SELECT * FROM categorias WHERE tenant_id=:tid ORDER BY nombre");
        $stmt->execute([':tid'=>$tid]);
        $categorias   = $stmt->fetchAll();
        $tituloPagina = 'Categorías';
        include RAIZ . '/app/vistas/inventario/categorias.php';
    }

    // ── Unidades de medida ────────────────────────────────────
    public function unidades() {
        $tid = tenantActual();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre'] ?? '');
            $abrev  = trim($_POST['abreviatura'] ?? '');
            if ($nombre && $abrev) {
                $this->db()->prepare("INSERT INTO unidades_medida (tenant_id,nombre,abreviatura) VALUES(:tid,:n,:a)")
                     ->execute([':tid'=>$tid, ':n'=>$nombre,':a'=>$abrev]);
                $_SESSION['exito'] = "Unidad '$nombre' creada.";
            }
            header('Location: ' . URL_BASE . '/?url=inventario/unidades'); exit;
        }
        $stmt = $this->db()->prepare("SELECT * FROM unidades_medida WHERE tenant_id=:tid ORDER BY nombre");
        $stmt->execute([':tid'=>$tid]);
        $unidades     = $stmt->fetchAll();
        $tituloPagina = 'Unidades de Medida';
        include RAIZ . '/app/vistas/inventario/unidades.php';
    }

    // ── Eliminar categoría ────────────────────────────────────
    public function eliminarCategoria($id) {
        $tid = tenantActual();
        try {
            $this->db()->prepare("DELETE FROM categorias WHERE id=:id AND tenant_id=:tid")
                 ->execute([':id'=>$id, ':tid'=>$tid]);
            $_SESSION['exito'] = 'Categoría eliminada.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'No se puede eliminar: tiene productos asociados.';
        }
        header('Location: ' . URL_BASE . '/?url=inventario/categorias'); exit;
    }

    // ── Actualizar categoría ──────────────────────────────────
    public function actualizarCategoria() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=inventario/categorias'); exit; }
        $tid    = tenantActual();
        $id     = intval($_POST['id']);
        $nombre = trim($_POST['nombre'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');
        if ($nombre) {
            $this->db()->prepare("UPDATE categorias SET nombre=:n, descripcion=:d WHERE id=:id AND tenant_id=:tid")
                 ->execute([':n'=>$nombre, ':d'=>$desc, ':id'=>$id, ':tid'=>$tid]);
            $_SESSION['exito'] = 'Categoría actualizada.';
        }
        header('Location: ' . URL_BASE . '/?url=inventario/categorias'); exit;
    }

    // ── Eliminar unidad ───────────────────────────────────────
    public function eliminarUnidad($id) {
        $tid = tenantActual();
        try {
            $this->db()->prepare("DELETE FROM unidades_medida WHERE id=:id AND tenant_id=:tid")
                 ->execute([':id'=>$id, ':tid'=>$tid]);
            $_SESSION['exito'] = 'Unidad eliminada.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'No se puede eliminar: tiene productos asociados.';
        }
        header('Location: ' . URL_BASE . '/?url=inventario/unidades'); exit;
    }

    // ── Actualizar unidad ─────────────────────────────────────
    public function actualizarUnidad() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=inventario/unidades'); exit; }
        $tid    = tenantActual();
        $id     = intval($_POST['id']);
        $nombre = trim($_POST['nombre'] ?? '');
        $abrev  = trim($_POST['abreviatura'] ?? '');
        if ($nombre && $abrev) {
            $this->db()->prepare("UPDATE unidades_medida SET nombre=:n, abreviatura=:a WHERE id=:id AND tenant_id=:tid")
                 ->execute([':n'=>$nombre, ':a'=>$abrev, ':id'=>$id, ':tid'=>$tid]);
            $_SESSION['exito'] = 'Unidad actualizada.';
        }
        header('Location: ' . URL_BASE . '/?url=inventario/unidades'); exit;
    }

    // ── Eliminar producto (se desactiva, no se borra: conserva el historial) ──
    public function eliminar($id) {
        $tid = tenantActual();
        $stmt = $this->db()->prepare("SELECT nombre FROM productos WHERE id=:id AND tenant_id=:tid AND activo=1" . sqlSucursal());
        $stmt->execute([':id'=>$id, ':tid'=>$tid]);
        $producto = $stmt->fetch();
        if (!$producto) {
            $_SESSION['error'] = 'Producto no encontrado.';
            header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
        }

        $this->db()->prepare("UPDATE productos SET activo=0 WHERE id=:id AND tenant_id=:tid")
             ->execute([':id'=>$id, ':tid'=>$tid]);

        $_SESSION['exito'] = "'{$producto['nombre']}' se eliminó del inventario. Su historial de ventas y movimientos de stock se conserva.";
        header('Location: ' . URL_BASE . '/?url=inventario/index'); exit;
    }

    // ── Helper: validar datos del producto (devuelve mensaje o '' si está bien) ──
    private function validarProducto(array $c, int $tid, int $idExcluir): string {
        if (!$c[':nombre'] || !$c[':categoria_id'] || !$c[':unidad_id'] || !$c[':precio_venta_real']) {
            return 'Completá todos los campos obligatorios.';
        }
        foreach ([':precio_compra', ':precio_minimo', ':precio_venta_real', ':stock_minimo'] as $k) {
            if (!is_finite($c[$k]) || $c[$k] < 0) return 'Los precios y el stock mínimo no pueden ser negativos.';
        }
        if ($c[':precio_minimo'] > $c[':precio_venta_real']) {
            return 'El precio mínimo no puede ser mayor que el precio de venta.';
        }
        if ($c[':fecha_vencimiento'] !== null) {
            $f = DateTime::createFromFormat('Y-m-d', $c[':fecha_vencimiento']);
            if (!$f || $f->format('Y-m-d') !== $c[':fecha_vencimiento']) return 'La fecha de vencimiento no es válida.';
        }

        // Categoría, unidad y proveedor deben ser de este mismo negocio
        $db = $this->db();
        $chk = function (string $tabla, $id) use ($db, $tid): bool {
            $st = $db->prepare("SELECT 1 FROM $tabla WHERE id=:id AND tenant_id=:tid");
            $st->execute([':id' => $id, ':tid' => $tid]);
            return (bool) $st->fetchColumn();
        };
        if (!$chk('categorias', $c[':categoria_id']))      return 'La categoría seleccionada no existe.';
        if (!$chk('unidades_medida', $c[':unidad_id']))    return 'La unidad seleccionada no existe.';
        if ($c[':proveedor_id'] !== null && !$chk('proveedores', $c[':proveedor_id'])) return 'El proveedor seleccionado no existe.';

        // Código duplicado (solo dentro del mismo negocio, sin contar el propio producto)
        if ($c[':codigo']) {
            // El codigo solo se repite dentro de la MISMA sucursal (la libreria vende lo mismo en sus dos locales)
            $sqlCodigo = "SELECT id FROM productos WHERE codigo=:codigo AND tenant_id=:tid AND id<>:id";
            if (catalogoPorSucursal()) {
                $sqlCodigo .= ' AND sucursal_id = ' . (int) ($c[':sucursal_id'] ?? $this->sucursalDeProducto($idExcluir, $tid));
            }
            $st = $db->prepare($sqlCodigo);
            $st->execute([':codigo' => $c[':codigo'], ':tid' => $tid, ':id' => $idExcluir]);
            if ($st->fetch()) return "El código '{$c[':codigo']}' ya está en uso por otro producto.";
        }
        return '';
    }

    // ── Helper: extraer campos del POST ──────────────────────
    private function extraerCampos() {
        return [
            ':codigo'            => trim($_POST['codigo'] ?? '') ?: null,
            ':nombre'            => trim($_POST['nombre'] ?? ''),
            ':descripcion'       => trim($_POST['descripcion'] ?? ''),
            ':categoria_id'      => intval($_POST['categoria_id'] ?? 0),
            ':unidad_id'         => intval($_POST['unidad_id'] ?? 0),
            ':proveedor_id'      => !empty($_POST['proveedor_id']) ? intval($_POST['proveedor_id']) : null,
            ':precio_compra'     => floatval($_POST['precio_compra'] ?? 0),
            ':precio_minimo'     => floatval($_POST['precio_minimo'] ?? 0),
            ':precio_venta_real' => floatval($_POST['precio_venta_real'] ?? 0),
            ':stock_minimo'      => floatval($_POST['stock_minimo'] ?? 0),
            ':fecha_vencimiento' => !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
        ];
    }

    // Sucursal del producto que se esta creando: el administrador elige en el formulario (0 = Principal);
    // cualquier otro usuario, la suya. Un valor que no sea una sucursal activa del negocio se ignora.
    private function sucursalParaProductoNuevo(): int {
        if (esAdminConSucursales()) {
            $elegida = intval($_POST['sucursal_id'] ?? sucursalTrabajo());
            if ($elegida === 0 || isset(sucursalesDelNegocio()[$elegida])) return $elegida;
        }
        return sucursalTrabajo();
    }

    // Sucursal a la que pertenece un producto ya guardado (0 = Principal).
    private function sucursalDeProducto(int $id, int $tid): int {
        $st = $this->db()->prepare("SELECT sucursal_id FROM productos WHERE id=:id AND tenant_id=:tid");
        $st->execute([':id' => $id, ':tid' => $tid]);
        return (int) $st->fetchColumn();
    }

    // ¿El producto es de este negocio Y de la sucursal que se esta viendo?
    private function productoEnVista(int $id, int $tid): bool {
        $st = $this->db()->prepare("SELECT 1 FROM productos WHERE id=:id AND tenant_id=:tid" . sqlSucursal());
        $st->execute([':id' => $id, ':tid' => $tid]);
        return (bool) $st->fetchColumn();
    }
}