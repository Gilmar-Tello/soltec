<?php

// ============================================================
//  Brix — "Mi contraseña": cada usuario cambia la suya.
//  Pide la contraseña actual (aunque alguien deje la sesion
//  abierta, no puede cambiarla sin conocerla) y limita los
//  intentos fallidos, igual que el login.
// ============================================================
class PerfilControlador {

    private function db() { return obtenerConexion(); }

    public function index() {
        $tituloPagina = 'Mi contraseña';
        include RAIZ . '/app/vistas/perfil/index.php';
    }

    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=perfil/index'); exit;
        }

        $tid    = tenantActual();
        $uid    = (int) ($_SESSION['usuario_id'] ?? 0);
        $volver = URL_BASE . '/?url=perfil/index';

        // Las contrasenas no se recortan: el login tampoco lo hace
        $actual  = (string) ($_POST['contrasena_actual'] ?? '');
        $nueva   = (string) ($_POST['contrasena_nueva'] ?? '');
        $repetir = (string) ($_POST['repetir'] ?? '');

        // ── Freno a quien adivina la contrasena actual ──
        $llave   = 'cambiar-clave:' . $uid;
        $minutos = loginBloqueado($llave);
        if ($minutos > 0) {
            $_SESSION['error'] = "Demasiados intentos fallidos. Prueba de nuevo en $minutos minuto(s).";
            header('Location: ' . $volver); exit;
        }

        // ── Reglas de la contrasena nueva ──
        if ($actual === '' || $nueva === '' || $repetir === '') {
            $_SESSION['error'] = 'Completa los tres campos.';
            header('Location: ' . $volver); exit;
        }
        if ($nueva !== $repetir) {
            $_SESSION['error'] = 'La contraseña nueva y su repetición no coinciden.';
            header('Location: ' . $volver); exit;
        }
        // Politica comun: 8 a 72 caracteres (bcrypt ignora lo que pase de 72 bytes), sin claves comunes
        $errorClave = validarContrasenaNueva($nueva, [$_SESSION['nombre_negocio'] ?? '']);
        if ($errorClave !== '') {
            $_SESSION['error'] = $errorClave;
            header('Location: ' . $volver); exit;
        }
        if ($nueva === $actual) {
            $_SESSION['error'] = 'La contraseña nueva debe ser distinta de la actual.';
            header('Location: ' . $volver); exit;
        }

        // ── Verificar la actual (siempre dentro de MI negocio) ──
        $stmt = $this->db()->prepare(
            "SELECT contrasena, usuario FROM usuarios WHERE id = :id AND tenant_id = :tid AND activo = 1");
        $stmt->execute([':id' => $uid, ':tid' => $tid]);
        $filaUsuario = $stmt->fetch();
        $hash = $filaUsuario['contrasena'] ?? false;

        if (!$hash || !password_verify($actual, $hash)) {
            registrarIntentoFallido($llave);
            $_SESSION['error'] = 'La contraseña actual es incorrecta.';
            header('Location: ' . $volver); exit;
        }
        limpiarIntentos($llave);

        // La clave tampoco puede contener el nombre de usuario con el que se entra
        $errorClave = validarContrasenaNueva($nueva, [$filaUsuario['usuario'] ?? '', $_SESSION['nombre_negocio'] ?? '']);
        if ($errorClave !== '') {
            $_SESSION['error'] = $errorClave;
            header('Location: ' . $volver); exit;
        }

        // ── Guardar ──
        try {
            $this->db()->prepare("UPDATE usuarios SET contrasena = :c WHERE id = :id AND tenant_id = :tid")
                 ->execute([':c' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => $uid, ':tid' => $tid]);
        } catch (Throwable $e) {
            $_SESSION['error'] = mensajeError($e, 'No se pudo cambiar la contraseña. Intenta de nuevo.');
            header('Location: ' . $volver); exit;
        }

        // ID de sesion nuevo despues de un cambio sensible
        regenerarSesion();

        $_SESSION['exito'] = 'Contraseña actualizada correctamente.';
        header('Location: ' . $volver); exit;
    }
}
