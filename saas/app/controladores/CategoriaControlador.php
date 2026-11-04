<?php

class CategoriaControlador {

    private function db() { return obtenerConexion(); }

    private function tenantId(): int {
        return (int) $_SESSION['tenant_id'];
    }

    // ── Listado ────────────────────────────────────────────────
    public function index() {
        $stmt = $this->db()->prepare(
            "SELECT c.*, COUNT(i.id) AS total_productos
             FROM categorias_inventario c
             LEFT JOIN inventario i ON i.categoria_id = c.id AND i.tenant_id = c.tenant_id
             WHERE c.tenant_id = :tid
             GROUP BY c.id
             ORDER BY c.nombre ASC"
        );
        $stmt->execute([':tid' => $this->tenantId()]);
        $categorias = $stmt->fetchAll();

        $tituloPagina = 'Categorías';
        include RAIZ . '/app/vistas/categorias/index.php';
    }

    // ── Guardar nueva (desde modal inline) ────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
        }

        $nombre = trim($_POST['nombre'] ?? '');

        if (!$nombre) {
            $_SESSION['error'] = 'El nombre de la categoría es obligatorio.';
            header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
        }

        // Verifica que no exista ya con el mismo nombre en este tenant
        $stmt = $this->db()->prepare(
            "SELECT id FROM categorias_inventario WHERE tenant_id = :tid AND nombre = :nombre"
        );
        $stmt->execute([':tid' => $this->tenantId(), ':nombre' => $nombre]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Ya existe una categoría llamada \"$nombre\".";
            header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
        }

        $this->db()->prepare(
            "INSERT INTO categorias_inventario (tenant_id, nombre) VALUES (:tid, :nombre)"
        )->execute([':tid' => $this->tenantId(), ':nombre' => $nombre]);

        $_SESSION['exito'] = "Categoría \"$nombre\" creada correctamente.";
        header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
    }

    // ── Editar nombre ──────────────────────────────────────────
    public function editar($id) {
        $stmt = $this->db()->prepare(
            "SELECT * FROM categorias_inventario WHERE id = :id AND tenant_id = :tid"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $categoria = $stmt->fetch();

        if (!$categoria) {
            $_SESSION['error'] = 'Categoría no encontrada.';
            header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
        }

        $tituloPagina = 'Editar Categoría';
        include RAIZ . '/app/vistas/categorias/editar.php';
    }

    // ── Actualizar ─────────────────────────────────────────────
    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
        }

        $id     = intval($_POST['id']);
        $nombre = trim($_POST['nombre'] ?? '');

        if (!$nombre) {
            $_SESSION['error'] = 'El nombre es obligatorio.';
            header('Location: ' . URL_BASE . '/?url=categorias/editar/' . $id); exit;
        }

        $this->db()->prepare(
            "UPDATE categorias_inventario SET nombre = :nombre WHERE id = :id AND tenant_id = :tid"
        )->execute([':nombre' => $nombre, ':id' => $id, ':tid' => $this->tenantId()]);

        $_SESSION['exito'] = 'Categoría actualizada correctamente.';
        header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
    }

    // ── Eliminar (solo si no tiene productos) ──────────────────
    public function eliminar($id) {
        $stmt = $this->db()->prepare(
            "SELECT nombre FROM categorias_inventario WHERE id = :id AND tenant_id = :tid"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $cat = $stmt->fetch();

        if (!$cat) {
            $_SESSION['error'] = 'Categoría no encontrada.';
            header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
        }

        // No eliminar si tiene productos asignados
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) AS total FROM inventario WHERE categoria_id = :id AND tenant_id = :tid"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        if ($stmt->fetch()['total'] > 0) {
            $_SESSION['error'] = "No se puede eliminar \"{$cat['nombre']}\" porque tiene productos asignados.";
            header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
        }

        $this->db()->prepare(
            "DELETE FROM categorias_inventario WHERE id = :id AND tenant_id = :tid"
        )->execute([':id' => $id, ':tid' => $this->tenantId()]);

        $_SESSION['exito'] = "Categoría \"{$cat['nombre']}\" eliminada.";
        header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
    }
}
