<?php

class ConfiguracionControlador {

    private function db() { return obtenerConexion(); }
    private function tid(): int { return tenantActual(); }

    public function index() {
        $stmt = $this->db()->prepare("SELECT * FROM negocios WHERE id = :id");
        $stmt->execute([':id' => $this->tid()]);
        $negocio = $stmt->fetch();
        if (!$negocio) {
            $_SESSION['error'] = 'No se encontró el negocio.';
            header('Location: ' . URL_BASE . '/?url=dashboard/index'); exit;
        }
        $tituloPagina = 'Datos del negocio';
        include RAIZ . '/app/vistas/configuracion/index.php';
    }

    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
        }

        $tid       = $this->tid();
        $nombre    = trim($_POST['nombre_negocio'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono  = trim($_POST['telefono'] ?? '');
        $nit       = trim($_POST['nit'] ?? '');

        if ($nombre === '') {
            $_SESSION['error'] = 'El nombre del negocio es obligatorio.';
            header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
        }

        // ── Logo (opcional) ────────────────────────────────────
        $logoPath = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK && $_FILES['logo']['size'] > 0) {
            if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $_SESSION['error'] = 'El logo no debe superar los 2 MB.';
                header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
            }
            $info = @getimagesize($_FILES['logo']['tmp_name']);
            if ($info === false) {
                $_SESSION['error'] = 'El archivo del logo no es una imagen válida.';
                header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
            }
            // El GIF se guarda como PNG (al redibujarlo se pierde la animacion, que en un logo no hace falta)
            $permitidos = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'png',
            ];
            $mime = $info['mime'] ?? '';
            if (!isset($permitidos[$mime])) {
                $_SESSION['error'] = 'Formato de logo no permitido. Usá JPG, PNG, WEBP o GIF.';
                header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
            }
            $ext = $permitidos[$mime];
            $dir = RAIZ . '/app/public/img/tenant_' . $tid;
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            if (!is_dir($dir) || !is_writable($dir)) {
                $_SESSION['error'] = 'No se pudo crear la carpeta del logo. Verificá permisos de app/public/img.';
                header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
            }
            $nombreArchivo = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destino = $dir . '/' . $nombreArchivo;
            $mimeSalida = ($mime === 'image/gif') ? 'image/png' : $mime;
            if (!guardarLogoSeguro($_FILES['logo']['tmp_name'], $destino, $mimeSalida)) {
                $_SESSION['error'] = 'No se pudo guardar el logo. Prueba con otra imagen (JPG, PNG o WEBP).';
                header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
            }
            $logoPath = 'app/public/img/tenant_' . $tid . '/' . $nombreArchivo;
        }

        // ── Actualizar negocio ─────────────────────────────────
        if ($logoPath !== null) {
            $this->db()->prepare(
                "UPDATE negocios SET nombre_negocio=:n, direccion=:d, telefono=:t, nit=:nit, logo=:logo WHERE id=:id"
            )->execute([
                ':n'=>$nombre, ':d'=>($direccion!==''?$direccion:null), ':t'=>($telefono!==''?$telefono:null),
                ':nit'=>($nit!==''?$nit:null), ':logo'=>$logoPath, ':id'=>$tid,
            ]);
        } else {
            $this->db()->prepare(
                "UPDATE negocios SET nombre_negocio=:n, direccion=:d, telefono=:t, nit=:nit WHERE id=:id"
            )->execute([
                ':n'=>$nombre, ':d'=>($direccion!==''?$direccion:null), ':t'=>($telefono!==''?$telefono:null),
                ':nit'=>($nit!==''?$nit:null), ':id'=>$tid,
            ]);
        }

        // Refleja el nombre en la sesión (se muestra en la barra lateral)
        $_SESSION['nombre_negocio'] = $nombre;
        if ($logoPath !== null) $_SESSION['negocio_logo'] = $logoPath;

        $_SESSION['exito'] = 'Datos del negocio actualizados.';
        header('Location: ' . URL_BASE . '/?url=configuracion/index'); exit;
    }
}
