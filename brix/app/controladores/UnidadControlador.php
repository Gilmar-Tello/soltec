<?php

class UnidadControlador {

    private function db() { return obtenerConexion(); }
    private function tid(): int { return tenantActual(); }

    public function index() {
        $stmt = $this->db()->prepare(
            "SELECT um.*, (SELECT COUNT(*) FROM productos p WHERE p.unidad_id = um.id AND p.tenant_id = um.tenant_id) AS n_productos
             FROM unidades_medida um WHERE um.tenant_id = :t ORDER BY um.nombre"
        );
        $stmt->execute([':t'=>$this->tid()]);
        $unidades = $stmt->fetchAll();

        $editar = null;
        if (!empty($_GET['editar'])) {
            $e = $this->db()->prepare("SELECT * FROM unidades_medida WHERE id = :id AND tenant_id = :t");
            $e->execute([':id'=>intval($_GET['editar']), ':t'=>$this->tid()]);
            $editar = $e->fetch() ?: null;
        }

        $tituloPagina = 'Unidades de medida';
        include RAIZ . '/app/vistas/unidades/index.php';
    }

    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=unidades/index'); exit; }
        $nombre = trim($_POST['nombre'] ?? '');
        $abrev  = trim($_POST['abreviatura'] ?? '');
        if ($nombre === '' || $abrev === '') {
            $_SESSION['error'] = 'Nombre y abreviatura son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=unidades/index'); exit;
        }
        $this->db()->prepare(
            "INSERT INTO unidades_medida (tenant_id, nombre, abreviatura, activo) VALUES (:t, :n, :a, 1)"
        )->execute([':t'=>$this->tid(), ':n'=>$nombre, ':a'=>$abrev]);
        $_SESSION['exito'] = 'Unidad creada.';
        header('Location: ' . URL_BASE . '/?url=unidades/index'); exit;
    }

    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=unidades/index'); exit; }
        $id     = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $abrev  = trim($_POST['abreviatura'] ?? '');
        if ($nombre === '' || $abrev === '') {
            $_SESSION['error'] = 'Nombre y abreviatura son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=unidades/index'); exit;
        }
        $this->db()->prepare(
            "UPDATE unidades_medida SET nombre = :n, abreviatura = :a WHERE id = :id AND tenant_id = :t"
        )->execute([':n'=>$nombre, ':a'=>$abrev, ':id'=>$id, ':t'=>$this->tid()]);
        $_SESSION['exito'] = 'Unidad actualizada.';
        header('Location: ' . URL_BASE . '/?url=unidades/index'); exit;
    }

    public function toggle($id) {
        $id = intval($id);
        $stmt = $this->db()->prepare("SELECT activo FROM unidades_medida WHERE id = :id AND tenant_id = :t");
        $stmt->execute([':id'=>$id, ':t'=>$this->tid()]);
        $u = $stmt->fetch();
        if ($u) {
            $nuevo = $u['activo'] ? 0 : 1;
            $this->db()->prepare("UPDATE unidades_medida SET activo = :a WHERE id = :id AND tenant_id = :t")
                 ->execute([':a'=>$nuevo, ':id'=>$id, ':t'=>$this->tid()]);
            $_SESSION['exito'] = $nuevo ? 'Unidad activada.' : 'Unidad desactivada.';
        }
        header('Location: ' . URL_BASE . '/?url=unidades/index'); exit;
    }
}
