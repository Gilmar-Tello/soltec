<?php

class SucursalControlador {

    private const TIPOS = ['operativa', 'vitrina'];

    private function db() { return obtenerConexion(); }

    private function tenantId(): int {
        return (int) $_SESSION['tenant_id'];
    }

    private function volver(string $ruta): void {
        header('Location: ' . URL_BASE . '/?url=' . $ruta); exit;
    }

    // ── Listado ────────────────────────────────────────────────
    public function index() {
        $stmt = $this->db()->prepare(
            "SELECT s.*, b.nombre AS bodega_nombre
             FROM sucursales s
             LEFT JOIN sucursales b ON b.id = s.bodega_id AND b.tenant_id = s.tenant_id
             WHERE s.tenant_id = :tid
             ORDER BY s.creado_en DESC"
        );
        $stmt->execute([':tid' => $this->tenantId()]);
        $sucursales = $stmt->fetchAll();

        $tituloPagina = 'Sucursales';
        include RAIZ . '/app/vistas/sucursales/index.php';
    }

    // ── Formulario nueva sucursal ──────────────────────────────
    public function nueva() {
        $bodegas = $this->bodegasDisponibles(0);
        $tituloPagina = 'Nueva Sucursal';
        include RAIZ . '/app/vistas/sucursales/form.php';
    }

    // ── Guardar nueva sucursal ─────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->volver('sucursales/index');

        $datos = $this->leerFormulario(0, 'sucursales/nueva');

        $this->db()->prepare(
            "INSERT INTO sucursales (tenant_id, nombre, direccion, telefono, tipo, bodega_id, activa)
             VALUES (:tid, :nombre, :dir, :tel, :tipo, :bodega, 1)"
        )->execute([
            ':tid'    => $this->tenantId(),
            ':nombre' => $datos['nombre'],
            ':dir'    => $datos['direccion'],
            ':tel'    => $datos['telefono'],
            ':tipo'   => $datos['tipo'],
            ':bodega' => $datos['bodega_id'],
        ]);

        $_SESSION['exito'] = "Sucursal '{$datos['nombre']}' creada correctamente.";
        $this->volver('sucursales/index');
    }

    // ── Formulario editar ──────────────────────────────────────
    public function editar($id) {
        $sucursal = $this->buscar((int) $id);

        if (!$sucursal) {
            $_SESSION['error'] = 'Sucursal no encontrada.';
            $this->volver('sucursales/index');
        }

        $bodegas = $this->bodegasDisponibles((int) $sucursal['id']);
        $tituloPagina = 'Editar — ' . $sucursal['nombre'];
        include RAIZ . '/app/vistas/sucursales/form.php';
    }

    // ── Actualizar ──────────────────────────────────────────────
    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->volver('sucursales/index');

        $id     = intval($_POST['id'] ?? 0);
        $actual = $this->buscar($id);
        if (!$actual) {
            $_SESSION['error'] = 'Sucursal no encontrada.';
            $this->volver('sucursales/index');
        }

        $datos = $this->leerFormulario($id, 'sucursales/editar/' . $id);

        // Una sucursal que es bodega de alguna vitrina no puede
        // volverse vitrina: esas vitrinas se quedarían sin stock.
        if ($datos['tipo'] === 'vitrina' && $actual['tipo'] !== 'vitrina') {
            $dependientes = $this->vitrinasQueDependenDe($id);
            if ($dependientes) {
                $_SESSION['error'] = "No se puede volver vitrina: es la bodega de "
                    . implode(', ', $dependientes) . ". Primero asigná otra bodega a esas vitrinas.";
                $this->volver('sucursales/editar/' . $id);
            }
        }

        $this->db()->prepare(
            "UPDATE sucursales
             SET nombre = :nombre, direccion = :dir, telefono = :tel, tipo = :tipo, bodega_id = :bodega
             WHERE id = :id AND tenant_id = :tid"
        )->execute([
            ':nombre' => $datos['nombre'],
            ':dir'    => $datos['direccion'],
            ':tel'    => $datos['telefono'],
            ':tipo'   => $datos['tipo'],
            ':bodega' => $datos['bodega_id'],
            ':id'     => $id,
            ':tid'    => $this->tenantId(),
        ]);

        $_SESSION['exito'] = 'Sucursal actualizada correctamente.'
            . ($datos['tipo'] === 'vitrina' && $actual['tipo'] !== 'vitrina'
                ? ' Desde ahora sus reservas se despachan desde la bodega; el inventario propio que tenga ya no se ofrece al reservar.'
                : '');
        $this->volver('sucursales/index');
    }

    // ── Activar / Desactivar ──────────────────────────────────
    public function toggleActiva($id) {
        $sucursal = $this->buscar((int) $id);

        if (!$sucursal) {
            $_SESSION['error'] = 'Sucursal no encontrada.';
            $this->volver('sucursales/index');
        }

        $nuevo = $sucursal['activa'] ? 0 : 1;

        // Apagar una bodega deja sin producto a sus vitrinas
        if ($nuevo === 0) {
            $dependientes = $this->vitrinasQueDependenDe((int) $sucursal['id']);
            if ($dependientes) {
                $_SESSION['error'] = "No se puede desactivar: es la bodega de "
                    . implode(', ', $dependientes) . ".";
                $this->volver('sucursales/index');
            }
        }

        $this->db()->prepare(
            "UPDATE sucursales SET activa = :a WHERE id = :id AND tenant_id = :tid"
        )->execute([':a' => $nuevo, ':id' => (int) $id, ':tid' => $this->tenantId()]);

        $_SESSION['exito'] = $nuevo
            ? "'{$sucursal['nombre']}' fue reactivada."
            : "'{$sucursal['nombre']}' fue desactivada.";
        $this->volver('sucursales/index');
    }

    // ── Helpers ────────────────────────────────────────────────

    // Lee y valida el formulario. Si algo falla, regresa al formulario.
    private function leerFormulario(int $idActual, string $rutaError): array {
        $nombre   = mb_substr(trim($_POST['nombre'] ?? ''), 0, 100);
        $tipo     = $_POST['tipo'] ?? 'operativa';
        $bodegaId = intval($_POST['bodega_id'] ?? 0);

        $error = null;
        if ($nombre === '') {
            $error = 'El nombre de la sucursal es obligatorio.';
        } elseif (!in_array($tipo, self::TIPOS, true)) {
            $error = 'Tipo de sucursal no válido.';
        } elseif ($tipo === 'vitrina') {
            // La bodega tiene que ser una sucursal operativa y activa
            // de este negocio, y no la misma sucursal.
            $validas = array_column($this->bodegasDisponibles($idActual), 'id');
            if (!$bodegaId || !in_array($bodegaId, array_map('intval', $validas), true)) {
                $error = 'Una vitrina necesita una bodega: elegí la sucursal operativa de donde sale el producto.';
            }
        }

        if ($error) {
            $_SESSION['error'] = $error;
            $this->volver($rutaError);
        }

        return [
            'nombre'    => $nombre,
            'direccion' => mb_substr(trim($_POST['direccion'] ?? ''), 0, 255),
            'telefono'  => mb_substr(trim($_POST['telefono'] ?? ''), 0, 20),
            'tipo'      => $tipo,
            'bodega_id' => $tipo === 'vitrina' ? $bodegaId : null,
        ];
    }

    private function buscar(int $id): ?array {
        $stmt = $this->db()->prepare(
            "SELECT * FROM sucursales WHERE id = :id AND tenant_id = :tid"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        return $stmt->fetch() ?: null;
    }

    // Sucursales que pueden ser bodega: operativas, activas y distintas a la actual
    private function bodegasDisponibles(int $excluirId): array {
        $stmt = $this->db()->prepare(
            "SELECT id, nombre FROM sucursales
             WHERE tenant_id = :tid AND tipo = 'operativa' AND activa = 1 AND id <> :excluir
             ORDER BY nombre"
        );
        $stmt->execute([':tid' => $this->tenantId(), ':excluir' => $excluirId]);
        return $stmt->fetchAll();
    }

    // Nombres de las vitrinas activas que usan esta sucursal como bodega
    private function vitrinasQueDependenDe(int $id): array {
        $stmt = $this->db()->prepare(
            "SELECT nombre FROM sucursales
             WHERE tenant_id = :tid AND tipo = 'vitrina' AND bodega_id = :id AND activa = 1"
        );
        $stmt->execute([':tid' => $this->tenantId(), ':id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
