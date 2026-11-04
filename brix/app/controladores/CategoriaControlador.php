<?php

class CategoriaControlador {

    private function db() { return obtenerConexion(); }
    private function tid(): int { return tenantActual(); }

    public function index() {
        $stmt = $this->db()->prepare(
            "SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id AND p.tenant_id = c.tenant_id) AS n_productos
             FROM categorias c WHERE c.tenant_id = :t ORDER BY c.nombre"
        );
        $stmt->execute([':t'=>$this->tid()]);
        $categorias = $stmt->fetchAll();

        $editar = null;
        if (!empty($_GET['editar'])) {
            $e = $this->db()->prepare("SELECT * FROM categorias WHERE id = :id AND tenant_id = :t");
            $e->execute([':id'=>intval($_GET['editar']), ':t'=>$this->tid()]);
            $editar = $e->fetch() ?: null;
        }

        $tituloPagina = 'Categorías';
        include RAIZ . '/app/vistas/categorias/index.php';
    }

    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=categorias/index'); exit; }
        $nombre = trim($_POST['nombre'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');
        if ($nombre === '') {
            $_SESSION['error'] = 'El nombre es obligatorio.';
            header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
        }
        $this->db()->prepare(
            "INSERT INTO categorias (tenant_id, nombre, descripcion, activo) VALUES (:t, :n, :d, 1)"
        )->execute([':t'=>$this->tid(), ':n'=>$nombre, ':d'=>($desc !== '' ? $desc : null)]);
        $_SESSION['exito'] = 'Categoría creada.';
        header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
    }

    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=categorias/index'); exit; }
        $id     = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');
        if ($nombre === '') {
            $_SESSION['error'] = 'El nombre es obligatorio.';
            header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
        }
        $this->db()->prepare(
            "UPDATE categorias SET nombre = :n, descripcion = :d WHERE id = :id AND tenant_id = :t"
        )->execute([':n'=>$nombre, ':d'=>($desc !== '' ? $desc : null), ':id'=>$id, ':t'=>$this->tid()]);
        $_SESSION['exito'] = 'Categoría actualizada.';
        header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
    }

    public function toggle($id) {
        $id = intval($id);
        $stmt = $this->db()->prepare("SELECT activo FROM categorias WHERE id = :id AND tenant_id = :t");
        $stmt->execute([':id'=>$id, ':t'=>$this->tid()]);
        $cat = $stmt->fetch();
        if ($cat) {
            $nuevo = $cat['activo'] ? 0 : 1;
            $this->db()->prepare("UPDATE categorias SET activo = :a WHERE id = :id AND tenant_id = :t")
                 ->execute([':a'=>$nuevo, ':id'=>$id, ':t'=>$this->tid()]);
            $_SESSION['exito'] = $nuevo ? 'Categoría activada.' : 'Categoría desactivada.';
        }
        header('Location: ' . URL_BASE . '/?url=categorias/index'); exit;
    }
}
