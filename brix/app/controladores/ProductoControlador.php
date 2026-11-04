<?php

class ProductoControlador {

    private function db() { return obtenerConexion(); }
    private function tid(): int { return tenantActual(); }

    // ── Inventario (listado) ───────────────────────────────────
    public function index() {
        $db  = $this->db();
        $tid = $this->tid();

        $busqueda = trim($_GET['busqueda'] ?? '');
        $cat      = intval($_GET['categoria'] ?? 0);
        $soloBajo = !empty($_GET['bajo']);

        $sql = "SELECT p.*, c.nombre AS categoria, um.abreviatura AS unidad,
                       COALESCE(s.cantidad,0) AS stock,
                       COALESCE(s.cantidad,0) * p.precio_compra AS valor_costo
                FROM productos p
                LEFT JOIN categorias c       ON c.id = p.categoria_id
                LEFT JOIN unidades_medida um ON um.id = p.unidad_id
                LEFT JOIN stock s            ON s.producto_id = p.id AND s.tenant_id = :t1
                WHERE p.tenant_id = :t2 AND p.activo = 1";
        $params = [':t1'=>$tid, ':t2'=>$tid];
        if ($busqueda !== '') {
            $sql .= " AND (p.nombre LIKE :b OR p.codigo LIKE :b2)";
            $params[':b'] = "%$busqueda%"; $params[':b2'] = "%$busqueda%";
        }
        if ($cat > 0) { $sql .= " AND p.categoria_id = :c"; $params[':c'] = $cat; }
        // Solo stock bajo: aplica a productos simples (los compuestos no guardan stock propio)
        if ($soloBajo) { $sql .= " AND p.es_compuesto = 0 AND COALESCE(s.cantidad,0) <= p.stock_minimo"; }
        $sql .= " ORDER BY p.nombre";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $productos = $stmt->fetchAll();

        // Para los compuestos, el "stock" es cuántos se pueden hacer con los componentes
        foreach ($productos as &$p) {
            $p['disponible'] = $p['es_compuesto'] ? $this->disponibilidadCompuesto((int)$p['id']) : (float)$p['stock'];
        }
        unset($p);

        $catStmt = $db->prepare("SELECT id, nombre FROM categorias WHERE tenant_id = :t AND activo = 1 ORDER BY nombre");
        $catStmt->execute([':t'=>$tid]);
        $categorias = $catStmt->fetchAll();

        $valorTotal = 0;
        foreach ($productos as $p) { $valorTotal += (float)$p['valor_costo']; }

        $tituloPagina = 'Productos';
        include RAIZ . '/app/vistas/productos/index.php';
    }

    // ── Cuántas unidades de un compuesto se pueden hacer hoy ────
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

    // ── PDF / vista imprimible del listado (respeta filtros) ────
    public function pdf() {
        $db  = $this->db();
        $tid = $this->tid();

        $busqueda = trim($_GET['busqueda'] ?? '');
        $cat      = intval($_GET['categoria'] ?? 0);
        $soloBajo = !empty($_GET['bajo']);

        $sql = "SELECT p.*, c.nombre AS categoria, um.abreviatura AS unidad,
                       COALESCE(s.cantidad,0) AS stock
                FROM productos p
                LEFT JOIN categorias c       ON c.id = p.categoria_id
                LEFT JOIN unidades_medida um ON um.id = p.unidad_id
                LEFT JOIN stock s            ON s.producto_id = p.id AND s.tenant_id = :t1
                WHERE p.tenant_id = :t2 AND p.activo = 1";
        $params = [':t1'=>$tid, ':t2'=>$tid];
        if ($busqueda !== '') {
            $sql .= " AND (p.nombre LIKE :b OR p.codigo LIKE :b2)";
            $params[':b'] = "%$busqueda%"; $params[':b2'] = "%$busqueda%";
        }
        if ($cat > 0) { $sql .= " AND p.categoria_id = :c"; $params[':c'] = $cat; }
        if ($soloBajo) { $sql .= " AND p.es_compuesto = 0 AND COALESCE(s.cantidad,0) <= p.stock_minimo"; }
        $sql .= " ORDER BY p.nombre";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $productos = $stmt->fetchAll();

        foreach ($productos as &$p) {
            $p['disponible'] = $p['es_compuesto'] ? $this->disponibilidadCompuesto((int)$p['id']) : (float)$p['stock'];
        }
        unset($p);

        $tituloPagina = $soloBajo ? 'Productos con stock bajo' : 'Listado de productos';
        if (ob_get_level()) ob_end_clean();
        include RAIZ . '/app/vistas/productos/pdf.php';
        exit;
    }

    // ── Formulario nuevo / editar ──────────────────────────────
    public function nuevo() {
        [$categorias, $unidades] = $this->catalogos();
        $producto = null;
        $tituloPagina = 'Nuevo producto';
        include RAIZ . '/app/vistas/productos/form.php';
    }

    public function editar($id) {
        $db  = $this->db();
        $tid = $this->tid();
        $stmt = $db->prepare(
            "SELECT p.*, COALESCE(s.cantidad,0) AS stock
             FROM productos p
             LEFT JOIN stock s ON s.producto_id = p.id AND s.tenant_id = :t1
             WHERE p.id = :id AND p.tenant_id = :t2"
        );
        $stmt->execute([':t1'=>$tid, ':id'=>intval($id), ':t2'=>$tid]);
        $producto = $stmt->fetch();
        if (!$producto) {
            $_SESSION['error'] = 'Producto no encontrado.';
            header('Location: ' . URL_BASE . '/?url=productos/index'); exit;
        }
        [$categorias, $unidades] = $this->catalogos();
        $tituloPagina = 'Editar producto';
        include RAIZ . '/app/vistas/productos/form.php';
    }

    private function catalogos(): array {
        $tid = $this->tid();
        $c = $this->db()->prepare("SELECT id, nombre FROM categorias WHERE tenant_id = :t AND activo = 1 ORDER BY nombre");
        $c->execute([':t'=>$tid]);
        $u = $this->db()->prepare("SELECT id, nombre, abreviatura FROM unidades_medida WHERE tenant_id = :t AND activo = 1 ORDER BY nombre");
        $u->execute([':t'=>$tid]);
        return [$c->fetchAll(), $u->fetchAll()];
    }

    // ── Guardar nuevo ──────────────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=productos/index'); exit; }
        $d = $this->leerDatos();
        if ($d['error']) {
            $_SESSION['error'] = $d['error'];
            header('Location: ' . URL_BASE . '/?url=productos/nuevo'); exit;
        }
        $db = $this->db(); $tid = $this->tid();
        $db->beginTransaction();
        try {
            $db->prepare(
                "INSERT INTO productos
                    (tenant_id, codigo, nombre, descripcion, categoria_id, unidad_id,
                     precio_compra, precio_venta, stock_minimo, fecha_vencimiento, es_compuesto, activo)
                 VALUES (:t, :cod, :nom, :desc, :cat, :uni, :pc, :pv, :sm, :fv, :comp, 1)"
            )->execute([
                ':t'=>$tid, ':cod'=>$d['codigo'], ':nom'=>$d['nombre'], ':desc'=>$d['descripcion'],
                ':cat'=>$d['categoria_id'], ':uni'=>$d['unidad_id'], ':pc'=>$d['precio_compra'],
                ':pv'=>$d['precio_venta'], ':sm'=>$d['stock_minimo'], ':fv'=>$d['fecha_vencimiento'],
                ':comp'=>$d['es_compuesto'],
            ]);
            $pid = $db->lastInsertId();

            // Los compuestos arrancan en 0 (se llenan al "armar"). Los simples pueden llevar stock inicial.
            $stockInicial = $d['es_compuesto'] ? 0 : $d['stock_inicial'];
            $db->prepare("INSERT INTO stock (tenant_id, producto_id, cantidad) VALUES (:t, :p, :c)")
               ->execute([':t'=>$tid, ':p'=>$pid, ':c'=>$stockInicial]);

            if ($stockInicial > 0) {
                $db->prepare(
                    "INSERT INTO movimientos_stock
                        (tenant_id, producto_id, usuario_id, tipo, origen, cantidad, stock_anterior, stock_nuevo, motivo)
                     VALUES (:t, :p, :u, 'entrada', 'inicial', :cant, 0, :nvo, 'Stock inicial')"
                )->execute([':t'=>$tid, ':p'=>$pid, ':u'=>$_SESSION['usuario_id'], ':cant'=>$stockInicial, ':nvo'=>$stockInicial]);
            }

            $db->commit();
            if ($d['es_compuesto']) {
                $_SESSION['exito'] = 'Producto compuesto creado. Ahora definí sus componentes.';
                header('Location: ' . URL_BASE . '/?url=productos/componentes/' . $pid); exit;
            }
            $_SESSION['exito'] = 'Producto creado.';
            header('Location: ' . URL_BASE . '/?url=productos/index'); exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al crear el producto. Intenta de nuevo.');
            header('Location: ' . URL_BASE . '/?url=productos/nuevo'); exit;
        }
    }

    // ── Actualizar ─────────────────────────────────────────────
    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=productos/index'); exit; }
        $id = intval($_POST['id'] ?? 0);
        $d = $this->leerDatos();
        if ($d['error']) {
            $_SESSION['error'] = $d['error'];
            header('Location: ' . URL_BASE . '/?url=productos/editar/' . $id); exit;
        }
        $db = $this->db(); $tid = $this->tid();

        // No se permite cambiar el tipo (compuesto/simple) al editar, para no romper el stock.
        $db->prepare(
            "UPDATE productos SET codigo=:cod, nombre=:nom, descripcion=:desc, categoria_id=:cat,
                    unidad_id=:uni, precio_compra=:pc, precio_venta=:pv, stock_minimo=:sm, fecha_vencimiento=:fv
             WHERE id=:id AND tenant_id=:t"
        )->execute([
            ':cod'=>$d['codigo'], ':nom'=>$d['nombre'], ':desc'=>$d['descripcion'], ':cat'=>$d['categoria_id'],
            ':uni'=>$d['unidad_id'], ':pc'=>$d['precio_compra'], ':pv'=>$d['precio_venta'], ':sm'=>$d['stock_minimo'],
            ':fv'=>$d['fecha_vencimiento'], ':id'=>$id, ':t'=>$tid,
        ]);

        $_SESSION['exito'] = 'Producto actualizado.';
        header('Location: ' . URL_BASE . '/?url=productos/index'); exit;
    }

    private function leerDatos(): array {
        $nombre       = trim($_POST['nombre'] ?? '');
        $categoria_id = intval($_POST['categoria_id'] ?? 0);
        $unidad_id    = intval($_POST['unidad_id'] ?? 0);
        $error = '';
        if ($nombre === '')                 $error = 'El nombre es obligatorio.';
        elseif ($categoria_id <= 0)         $error = 'Seleccioná una categoría.';
        elseif ($unidad_id <= 0)            $error = 'Seleccioná una unidad de medida.';

        // Validar que categoría y unidad pertenezcan al tenant
        if (!$error) {
            $tid = $this->tid();
            $c = $this->db()->prepare("SELECT id FROM categorias WHERE id=:id AND tenant_id=:t");
            $c->execute([':id'=>$categoria_id, ':t'=>$tid]);
            if (!$c->fetch()) $error = 'Categoría inválida.';
            else {
                $u = $this->db()->prepare("SELECT id FROM unidades_medida WHERE id=:id AND tenant_id=:t");
                $u->execute([':id'=>$unidad_id, ':t'=>$tid]);
                if (!$u->fetch()) $error = 'Unidad inválida.';
            }
        }

        $fv = trim($_POST['fecha_vencimiento'] ?? '');
        return [
            'error'             => $error,
            'codigo'            => (($cod = trim($_POST['codigo'] ?? '')) !== '' ? $cod : null),
            'nombre'            => $nombre,
            'descripcion'       => (($ds = trim($_POST['descripcion'] ?? '')) !== '' ? $ds : null),
            'categoria_id'      => $categoria_id,
            'unidad_id'         => $unidad_id,
            'precio_compra'     => round((float)($_POST['precio_compra'] ?? 0), 2),
            'precio_venta'      => round((float)($_POST['precio_venta'] ?? 0), 2),
            'stock_minimo'      => round((float)($_POST['stock_minimo'] ?? 0), 2),
            'fecha_vencimiento' => ($fv !== '' ? $fv : null),
            'es_compuesto'      => !empty($_POST['es_compuesto']) ? 1 : 0,
            'stock_inicial'     => round((float)($_POST['stock_inicial'] ?? 0), 2),
        ];
    }

    // ── Activar / desactivar ───────────────────────────────────
    public function toggle($id) {
        $id = intval($id);
        $stmt = $this->db()->prepare("SELECT activo FROM productos WHERE id=:id AND tenant_id=:t");
        $stmt->execute([':id'=>$id, ':t'=>$this->tid()]);
        $p = $stmt->fetch();
        if ($p) {
            $nuevo = $p['activo'] ? 0 : 1;
            $this->db()->prepare("UPDATE productos SET activo=:a WHERE id=:id AND tenant_id=:t")
                 ->execute([':a'=>$nuevo, ':id'=>$id, ':t'=>$this->tid()]);
            $_SESSION['exito'] = $nuevo ? 'Producto reactivado.' : 'Producto desactivado.';
        }
        header('Location: ' . URL_BASE . '/?url=productos/index'); exit;
    }

    // ── Componentes de un compuesto (receta) ───────────────────
    public function componentes($id) {
        $db  = $this->db(); $tid = $this->tid();
        $id  = intval($id);

        $stmt = $db->prepare("SELECT * FROM productos WHERE id=:id AND tenant_id=:t AND es_compuesto=1");
        $stmt->execute([':id'=>$id, ':t'=>$tid]);
        $compuesto = $stmt->fetch();
        if (!$compuesto) {
            $_SESSION['error'] = 'Producto compuesto no encontrado.';
            header('Location: ' . URL_BASE . '/?url=productos/index'); exit;
        }

        // Componentes actuales (con su stock disponible)
        $comp = $db->prepare(
            "SELECT pc.*, p.nombre, um.abreviatura AS unidad, COALESCE(s.cantidad,0) AS stock
             FROM producto_componentes pc
             INNER JOIN productos p ON p.id = pc.componente_id
             LEFT JOIN unidades_medida um ON um.id = p.unidad_id
             LEFT JOIN stock s ON s.producto_id = p.id AND s.tenant_id = :t1
             WHERE pc.compuesto_id = :cid AND pc.tenant_id = :t2
             ORDER BY p.nombre"
        );
        $comp->execute([':t1'=>$tid, ':cid'=>$id, ':t2'=>$tid]);
        $componentes = $comp->fetchAll();

        // Cuántas unidades del compuesto se pueden hacer hoy
        $disponible = $this->disponibilidadCompuesto($id);

        // Productos simples disponibles como componentes
        $disp = $db->prepare(
            "SELECT id, nombre FROM productos
             WHERE tenant_id = :t AND activo = 1 AND es_compuesto = 0 AND id <> :cid
             ORDER BY nombre"
        );
        $disp->execute([':t'=>$tid, ':cid'=>$id]);
        $disponibles = $disp->fetchAll();

        $tituloPagina = 'Componentes — ' . $compuesto['nombre'];
        include RAIZ . '/app/vistas/productos/componentes.php';
    }

    // Reemplaza toda la receta del compuesto
    public function guardarcomponentes() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=productos/index'); exit; }
        $db  = $this->db(); $tid = $this->tid();
        $compuestoId = intval($_POST['compuesto_id'] ?? 0);

        $chk = $db->prepare("SELECT id FROM productos WHERE id=:id AND tenant_id=:t AND es_compuesto=1");
        $chk->execute([':id'=>$compuestoId, ':t'=>$tid]);
        if (!$chk->fetch()) {
            $_SESSION['error'] = 'Compuesto inválido.';
            header('Location: ' . URL_BASE . '/?url=productos/index'); exit;
        }

        $comps = $_POST['componentes'] ?? [];
        $limpios = [];
        foreach ($comps as $c) {
            $cid  = intval($c['componente_id'] ?? 0);
            $cant = round((float)($c['cantidad'] ?? 0), 2);
            if ($cid <= 0 || $cant <= 0 || $cid === $compuestoId) continue;
            // Verifica que el componente exista, sea simple y del tenant
            $v = $db->prepare("SELECT id FROM productos WHERE id=:id AND tenant_id=:t AND es_compuesto=0");
            $v->execute([':id'=>$cid, ':t'=>$tid]);
            if ($v->fetch()) $limpios[$cid] = $cant; // dedup por componente
        }

        if (count($limpios) < 1) {
            $_SESSION['error'] = 'Agregá al menos un componente válido.';
            header('Location: ' . URL_BASE . '/?url=productos/componentes/' . $compuestoId); exit;
        }

        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM producto_componentes WHERE compuesto_id=:c AND tenant_id=:t")
               ->execute([':c'=>$compuestoId, ':t'=>$tid]);
            $ins = $db->prepare(
                "INSERT INTO producto_componentes (tenant_id, compuesto_id, componente_id, cantidad)
                 VALUES (:t, :comp, :cid, :cant)"
            );
            foreach ($limpios as $cid => $cant) {
                $ins->execute([':t'=>$tid, ':comp'=>$compuestoId, ':cid'=>$cid, ':cant'=>$cant]);
            }
            $db->commit();
            $_SESSION['exito'] = 'Receta guardada (' . count($limpios) . ' componentes).';
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al guardar la receta. Intenta de nuevo.');
        }
        header('Location: ' . URL_BASE . '/?url=productos/componentes/' . $compuestoId); exit;
    }

    // ── Entrada de stock (compra) — solo productos simples ─────
    public function entrada($id) {
        $db  = $this->db(); $tid = $this->tid();
        $id  = intval($id);

        $stmt = $db->prepare(
            "SELECT p.*, um.abreviatura AS unidad, COALESCE(s.cantidad,0) AS stock
             FROM productos p
             LEFT JOIN unidades_medida um ON um.id = p.unidad_id
             LEFT JOIN stock s ON s.producto_id = p.id AND s.tenant_id = :t1
             WHERE p.id = :id AND p.tenant_id = :t2 AND p.es_compuesto = 0"
        );
        $stmt->execute([':t1'=>$tid, ':id'=>$id, ':t2'=>$tid]);
        $producto = $stmt->fetch();
        if (!$producto) {
            $_SESSION['error'] = 'Producto no encontrado (o es compuesto: la entrada de stock es solo para productos simples).';
            header('Location: ' . URL_BASE . '/?url=productos/index'); exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $cantidad = round((float)($_POST['cantidad'] ?? 0), 2);
            $precio   = round((float)($_POST['precio_compra'] ?? 0), 2);
            $registrarEgreso = !empty($_POST['registrar_egreso']);

            if ($cantidad <= 0) {
                $_SESSION['error'] = 'Ingresá una cantidad mayor a cero.';
                header('Location: ' . URL_BASE . '/?url=productos/entrada/' . $id); exit;
            }
            if ($precio < 0) $precio = 0;

            $ant   = (float)$producto['stock'];
            $nuevo = $ant + $cantidad;
            $costoTotal = round($cantidad * $precio, 2);

            $db->beginTransaction();
            try {
                // Suma al stock (asegura la fila)
                $db->prepare(
                    "INSERT INTO stock (tenant_id, producto_id, cantidad) VALUES (:t, :p, :c)
                     ON DUPLICATE KEY UPDATE cantidad = cantidad + :c2"
                )->execute([':t'=>$tid, ':p'=>$id, ':c'=>$cantidad, ':c2'=>$cantidad]);

                // Actualiza el precio de compra al último precio pagado
                if ($precio > 0) {
                    $db->prepare("UPDATE productos SET precio_compra = :pc WHERE id = :id AND tenant_id = :t")
                       ->execute([':pc'=>$precio, ':id'=>$id, ':t'=>$tid]);
                }

                // Registra el movimiento de entrada
                $db->prepare(
                    "INSERT INTO movimientos_stock
                        (tenant_id, producto_id, usuario_id, tipo, origen, cantidad, stock_anterior, stock_nuevo, motivo)
                     VALUES (:t, :p, :u, 'entrada', 'compra', :cant, :ant, :nvo, 'Entrada de stock')"
                )->execute([
                    ':t'=>$tid, ':p'=>$id, ':u'=>$_SESSION['usuario_id'],
                    ':cant'=>$cantidad, ':ant'=>$ant, ':nvo'=>$nuevo,
                ]);

                // Egreso automático por la compra
                if ($registrarEgreso && $costoTotal > 0) {
                    $db->prepare(
                        "INSERT INTO egresos (tenant_id, usuario_id, concepto, descripcion, monto)
                         VALUES (:t, :u, :c, NULL, :m)"
                    )->execute([
                        ':t'=>$tid, ':u'=>$_SESSION['usuario_id'],
                        ':c'=>'Compra de stock: ' . $producto['nombre'], ':m'=>$costoTotal,
                    ]);
                    $egresoId = $db->lastInsertId();
                    // Detalle (si existe la tabla). Se ignora si aún no corriste la migración.
                    try {
                        $db->prepare(
                            "INSERT INTO egreso_detalle (tenant_id, egreso_id, descripcion, cantidad, precio_unitario, subtotal)
                             VALUES (:t, :e, :d, :cant, :precio, :sub)"
                        )->execute([
                            ':t'=>$tid, ':e'=>$egresoId, ':d'=>'Compra: ' . $producto['nombre'],
                            ':cant'=>$cantidad, ':precio'=>$precio, ':sub'=>$costoTotal,
                        ]);
                    } catch (Exception $ig) { /* tabla de detalle no disponible: el egreso ya quedó */ }
                }

                $db->commit();
                $msg = "Entrada registrada: +{$cantidad} de \"{$producto['nombre']}\".";
                if ($registrarEgreso && $costoTotal > 0) $msg .= ' Egreso por Q' . number_format($costoTotal, 2) . '.';
                $_SESSION['exito'] = $msg;
                header('Location: ' . URL_BASE . '/?url=productos/index'); exit;
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error'] = mensajeError($e, 'Error al registrar la entrada. Intenta de nuevo.');
                header('Location: ' . URL_BASE . '/?url=productos/entrada/' . $id); exit;
            }
        }

        $tituloPagina = 'Entrada de stock — ' . $producto['nombre'];
        include RAIZ . '/app/vistas/productos/entrada.php';
    }

    // ── Cuadre de inventario ───────────────────────────────────
    public function cuadre() {
        $db  = $this->db(); $tid = $this->tid();
        $stmt = $db->prepare(
            "SELECT p.id, p.nombre, p.precio_compra, um.abreviatura AS unidad, COALESCE(s.cantidad,0) AS stock
             FROM productos p
             LEFT JOIN unidades_medida um ON um.id = p.unidad_id
             LEFT JOIN stock s ON s.producto_id = p.id AND s.tenant_id = :t1
             WHERE p.tenant_id = :t2 AND p.activo = 1 AND p.es_compuesto = 0
             ORDER BY p.nombre"
        );
        $stmt->execute([':t1'=>$tid, ':t2'=>$tid]);
        $productos = $stmt->fetchAll();

        // Historial de cuadres
        $hist = $db->prepare("SELECT c.*, u.nombre AS usuario_nombre FROM cuadres c LEFT JOIN usuarios u ON u.id=c.usuario_id WHERE c.tenant_id=:t ORDER BY c.id DESC LIMIT 10");
        $hist->execute([':t'=>$tid]);
        $historial = $hist->fetchAll();

        $tituloPagina = 'Cuadre de inventario';
        include RAIZ . '/app/vistas/productos/cuadre.php';
    }

    public function guardarcuadre() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=productos/cuadre'); exit; }
        $db  = $this->db(); $tid = $this->tid();
        $conteos = $_POST['fisico'] ?? [];   // [producto_id => cantidad_fisica]
        $notas   = trim($_POST['notas'] ?? '');
        $ajustar = !empty($_POST['ajustar']);

        // Trae productos actuales
        $stmt = $db->prepare(
            "SELECT p.id, p.precio_compra, COALESCE(s.cantidad,0) AS stock
             FROM productos p LEFT JOIN stock s ON s.producto_id = p.id AND s.tenant_id = :t1
             WHERE p.tenant_id = :t2 AND p.activo = 1 AND p.es_compuesto = 0"
        );
        $stmt->execute([':t1'=>$tid, ':t2'=>$tid]);
        $productos = $stmt->fetchAll();

        $lineas = [];
        $valorEsperado = 0; $valorFisico = 0; $valorFaltante = 0;
        foreach ($productos as $p) {
            if (!array_key_exists($p['id'], $conteos)) continue;         // producto no contado → se omite
            if ($conteos[$p['id']] === '') continue;
            $sistema = (float)$p['stock'];
            $fisico  = round((float)$conteos[$p['id']], 2);
            $costo   = (float)$p['precio_compra'];
            $dif     = $fisico - $sistema;
            $valDif  = $dif * $costo;

            $valorEsperado += $sistema * $costo;
            $valorFisico   += $fisico  * $costo;
            if ($dif < 0) $valorFaltante += abs($valDif);

            $lineas[] = [
                'producto_id' => $p['id'], 'sistema' => $sistema, 'fisico' => $fisico,
                'dif' => $dif, 'costo' => $costo, 'valDif' => $valDif,
            ];
        }

        if (empty($lineas)) {
            $_SESSION['error'] = 'No ingresaste ningún conteo físico.';
            header('Location: ' . URL_BASE . '/?url=productos/cuadre'); exit;
        }

        $porcentaje = $valorEsperado > 0 ? round(($valorFaltante / $valorEsperado) * 100, 2) : 0;

        $db->beginTransaction();
        try {
            $db->prepare(
                "INSERT INTO cuadres (tenant_id, usuario_id, valor_esperado, valor_fisico, valor_perdida, porcentaje_perdida, notas)
                 VALUES (:t, :u, :ve, :vf, :vp, :pp, :n)"
            )->execute([
                ':t'=>$tid, ':u'=>$_SESSION['usuario_id'], ':ve'=>round($valorEsperado,2),
                ':vf'=>round($valorFisico,2), ':vp'=>round($valorFaltante,2), ':pp'=>$porcentaje,
                ':n'=>($notas !== '' ? $notas : null),
            ]);
            $cuadreId = $db->lastInsertId();

            $insDet = $db->prepare(
                "INSERT INTO cuadre_detalle (tenant_id, cuadre_id, producto_id, stock_sistema, stock_fisico, diferencia, costo_unitario, valor_diferencia)
                 VALUES (:t, :c, :p, :ss, :sf, :d, :cu, :vd)"
            );
            foreach ($lineas as $l) {
                $insDet->execute([
                    ':t'=>$tid, ':c'=>$cuadreId, ':p'=>$l['producto_id'], ':ss'=>$l['sistema'],
                    ':sf'=>$l['fisico'], ':d'=>$l['dif'], ':cu'=>$l['costo'], ':vd'=>$l['valDif'],
                ]);

                // Ajusta el stock del sistema al conteo físico (opcional)
                if ($ajustar && abs($l['dif']) > 0.0001) {
                    $db->prepare(
                        "INSERT INTO stock (tenant_id, producto_id, cantidad) VALUES (:t, :p, :c)
                         ON DUPLICATE KEY UPDATE cantidad = :c2"
                    )->execute([':t'=>$tid, ':p'=>$l['producto_id'], ':c'=>$l['fisico'], ':c2'=>$l['fisico']]);
                    $origen = $l['dif'] < 0 ? 'merma' : 'ajuste';
                    $tipo   = $l['dif'] < 0 ? 'salida' : 'entrada';
                    $db->prepare(
                        "INSERT INTO movimientos_stock
                            (tenant_id, producto_id, usuario_id, tipo, origen, cantidad, stock_anterior, stock_nuevo, motivo, referencia_id)
                         VALUES (:t, :p, :u, :tipo, :origen, :cant, :ant, :nvo, 'Ajuste por cuadre', :ref)"
                    )->execute([
                        ':t'=>$tid, ':p'=>$l['producto_id'], ':u'=>$_SESSION['usuario_id'],
                        ':tipo'=>$tipo, ':origen'=>$origen, ':cant'=>abs($l['dif']),
                        ':ant'=>$l['sistema'], ':nvo'=>$l['fisico'], ':ref'=>$cuadreId,
                    ]);
                }
            }

            $db->commit();
            $_SESSION['exito'] = 'Cuadre registrado. Pérdida sobre el costo: ' . number_format($porcentaje, 2) . '%.';
            header('Location: ' . URL_BASE . '/?url=productos/cuadre'); exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'Error al guardar el cuadre. Intenta de nuevo.');
            header('Location: ' . URL_BASE . '/?url=productos/cuadre'); exit;
        }
    }
}
