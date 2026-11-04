<?php

/**
 * Servicios a la medida con mensualidad (ej. NutriFluidos).
 *
 * El panel solo hace dos cosas con ellos: habilitar / suspender el acceso y fijar la
 * fecha de pago. El estado vive en la tabla `servicio_estado` (UNA fila) de la base del
 * cliente; el propio sistema del cliente la lee en cada petición (php/auth.php).
 * Las conexiones se definen en privado/admin_base_datos.php (SERVICIOS_MENSUALES).
 */
class ServiciosControlador {

    // Debe coincidir con NF_AVISO_DIAS_ANTES del sistema del cliente (php/auth.php).
    private const AVISO_DIAS_ANTES = 3;

    private function claveServicio(): string {
        $clave = (string) ($_POST['servicio'] ?? $_GET['servicio'] ?? 'nutrifluidos');
        if (!isset($GLOBALS['SERVICIOS_MENSUALES'][$clave])) {
            http_response_code(404);
            die('<div style="font-family:sans-serif;padding:20px;"><h2>404 — Servicio no encontrado</h2></div>');
        }
        return $clave;
    }

    private function urlVer(string $clave): string {
        return URL_BASE . '/?url=servicios/ver&servicio=' . urlencode($clave);
    }

    private function soloPost(string $clave): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $this->urlVer($clave));
            exit;
        }
    }

    /** Lee la fila única del estado. Lanza excepción si la tabla o la fila no existen. */
    private function leerEstado(PDO $db): array {
        $fila = $db->query(
            "SELECT estado, fecha_pago, suspendido_desde, motivo, actualizado_en,
                    DATEDIFF(fecha_pago, CURDATE()) AS dias
               FROM servicio_estado WHERE id = 1"
        )->fetch();
        if (!$fila) {
            throw new RuntimeException('La tabla servicio_estado no tiene su fila única (id = 1).');
        }
        return $fila;
    }

    // ── Pantalla ───────────────────────────────────────────────
    public function ver() {
        $clave    = $this->claveServicio();
        $servicio = $GLOBALS['SERVICIOS_MENSUALES'][$clave];
        $estado   = null;
        $problema = '';

        try {
            $estado = $this->leerEstado(conexionServicio($clave));
        } catch (Throwable $e) {
            $problema = mensajeError($e, 'No se pudo leer el estado de ' . $servicio['nombre']
                . '. ¿Ya corriste el SQL servicio_estado en su base y pegaste el usuario de esa base en privado/admin_base_datos.php?');
        }

        // OJO: header.php usa un foreach con $clave (selector de sistemas) y la pisa.
        // Por eso la vista usa $servicioClave, que ese menú no toca.
        $servicioClave = $clave;
        $avisoDias     = self::AVISO_DIAS_ANTES;
        $tituloPagina  = $servicio['nombre'] . ' — Servicio mensual';
        include RAIZ . '/app/vistas/panel/servicio_mensual.php';
    }

    // ── Habilitar / suspender ──────────────────────────────────
    public function cambiarEstado() {
        $clave = $this->claveServicio();
        $this->soloPost($clave);
        $servicio = $GLOBALS['SERVICIOS_MENSUALES'][$clave];

        // Solo el valor exacto '0' suspende; cualquier otra cosa habilita.
        $suspender = (($_POST['estado'] ?? '') === '0');
        $motivo    = trim((string) ($_POST['motivo'] ?? ''));
        if (mb_strlen($motivo) > 255) {
            $_SESSION['error'] = 'El motivo es demasiado largo (máximo 255 caracteres).';
            header('Location: ' . $this->urlVer($clave)); exit;
        }

        try {
            $db = conexionServicio($clave);
            $this->leerEstado($db);   // confirma que la tabla y la fila existen

            if ($suspender) {
                $db->prepare(
                    "UPDATE servicio_estado
                        SET estado = 0, suspendido_desde = COALESCE(suspendido_desde, NOW()), motivo = :m
                      WHERE id = 1"
                )->execute([':m' => ($motivo !== '' ? $motivo : 'Pago pendiente')]);
            } else {
                $db->prepare(
                    "UPDATE servicio_estado SET estado = 1, suspendido_desde = NULL, motivo = NULL WHERE id = 1"
                )->execute();
            }
            $despues = $this->leerEstado($db);
        } catch (Throwable $e) {
            $_SESSION['error'] = mensajeError($e, 'No se pudo cambiar el estado de ' . $servicio['nombre'] . '.');
            header('Location: ' . $this->urlVer($clave)); exit;
        }

        registrarEventoPanel(
            $suspender ? 'servicio_suspendido' : 'servicio_habilitado',
            $clave . ($suspender && $motivo !== '' ? ' motivo=' . $motivo : '')
        );

        if ($suspender) {
            $_SESSION['exito'] = $servicio['nombre'] . ' quedó SUSPENDIDO: nadie puede entrar ni seguir trabajando.';
        } else {
            $_SESSION['exito'] = $servicio['nombre'] . ' quedó HABILITADO.';
            if ($despues['dias'] === null || (int) $despues['dias'] < 0) {
                $_SESSION['exito'] .= ' Recuerda fijar su próxima fecha de pago.';
            }
        }
        header('Location: ' . $this->urlVer($clave)); exit;
    }

    // ── Fecha de pago ──────────────────────────────────────────
    public function guardarFecha() {
        $clave = $this->claveServicio();
        $this->soloPost($clave);
        $servicio = $GLOBALS['SERVICIOS_MENSUALES'][$clave];

        $texto = trim((string) ($_POST['fecha_pago'] ?? ''));
        $valor = null;   // vacío = sin fecha (los usuarios no ven ningún aviso)
        if ($texto !== '') {
            $d = DateTime::createFromFormat('!Y-m-d', $texto);
            $anio = $d ? (int) $d->format('Y') : 0;
            if (!$d || $d->format('Y-m-d') !== $texto || $anio < 2024 || $anio > 2100) {
                $_SESSION['error'] = 'La fecha de pago no es válida.';
                header('Location: ' . $this->urlVer($clave)); exit;
            }
            $valor = $texto;
        }

        try {
            $db = conexionServicio($clave);
            $this->leerEstado($db);
            $db->prepare("UPDATE servicio_estado SET fecha_pago = :f WHERE id = 1")->execute([':f' => $valor]);
        } catch (Throwable $e) {
            $_SESSION['error'] = mensajeError($e, 'No se pudo guardar la fecha de pago de ' . $servicio['nombre'] . '.');
            header('Location: ' . $this->urlVer($clave)); exit;
        }

        registrarEventoPanel('servicio_fecha_pago', $clave . ' fecha=' . ($valor ?? 'sin fecha'));
        $_SESSION['exito'] = $valor
            ? 'Fecha de pago guardada: ' . date('d/m/Y', strtotime($valor)) . '.'
            : 'Fecha de pago quitada: no se mostrará ningún aviso.';
        header('Location: ' . $this->urlVer($clave)); exit;
    }
}
