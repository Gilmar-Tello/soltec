<?php

// ============================================================
// INCIDENCIAS — daños y faltantes detectados al recoger
//
// El recolector NO entra aquí: sus incidencias nacen solas al
// confirmar la devolución (ReservaControlador::confirmardevolucion).
// Este módulo es del administrador: ve lo pendiente, contacta al
// cliente y deja escrito cómo terminó.
//
// Inventario: las unidades dañadas o perdidas YA se descontaron
// de cantidad_total al confirmar la devolución. Por eso cobrar o
// perdonar no toca el inventario; solo 'repuesto' (el cliente
// trae unidades nuevas) las vuelve a sumar.
// ============================================================

class IncidenciaControlador {

    private const RESOLUCIONES = ['cobrado', 'perdonado', 'repuesto'];

    private function db() { return obtenerConexion(); }

    private function tenantId(): int {
        return (int) $_SESSION['tenant_id'];
    }

    private function volver(string $ruta): void {
        header('Location: ' . URL_BASE . '/?url=' . $ruta);
        exit;
    }

    // ── Listado ────────────────────────────────────────────────
    public function index() {
        $filtro = $_GET['estado'] ?? 'abiertas';

        $sql = "SELECT inc.*, i.nombre AS producto_nombre,
                       c.nombre_completo, c.telefono,
                       s.nombre AS sucursal_nombre,
                       ur.nombre AS reporta_nombre
                FROM incidencias inc
                INNER JOIN reservas   r  ON r.id  = inc.reserva_id
                INNER JOIN clientes   c  ON c.id  = r.cliente_id
                INNER JOIN sucursales s  ON s.id  = r.sucursal_id
                INNER JOIN inventario i  ON i.id  = inc.inventario_id
                INNER JOIN usuarios   ur ON ur.id = inc.usuario_reporta_id
                WHERE inc.tenant_id = :tid";

        // Filtro por lista blanca: nunca se pega el texto del GET al SQL
        if ($filtro === 'abiertas') {
            $sql .= " AND inc.estado IN ('pendiente', 'en_gestion')";
        } elseif ($filtro === 'resueltas') {
            $sql .= " AND inc.estado IN ('cobrado', 'perdonado', 'repuesto')";
        } else {
            $filtro = 'todas';
        }

        // Primero lo pendiente, luego lo que está en gestión, luego lo cerrado
        $sql .= " ORDER BY CASE inc.estado
                             WHEN 'pendiente'  THEN 0
                             WHEN 'en_gestion' THEN 1
                             ELSE 2
                           END,
                           inc.fecha_registro DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':tid' => $this->tenantId()]);
        $incidencias = $stmt->fetchAll();

        $tituloPagina = 'Incidencias';
        include RAIZ . '/app/vistas/incidencias/index.php';
    }

    // ── Detalle ────────────────────────────────────────────────
    public function detalle($id) {
        $incidencia = $this->buscar((int) $id);

        if (!$incidencia) {
            $_SESSION['error'] = 'Incidencia no encontrada.';
            $this->volver('incidencias/index');
        }

        $urlWhatsapp = 'https://wa.me/' . telefonoWhatsapp($incidencia['telefono'])
                     . '?text=' . rawurlencode(mensajeIncidenciaWhatsapp($incidencia));

        $tituloPagina = 'Incidencia #' . $incidencia['id'];
        include RAIZ . '/app/vistas/incidencias/detalle.php';
    }

    // ── Marcar "en gestión" (ya se contactó al cliente) ────────
    public function gestionar($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->volver('incidencias/index');

        $id   = (int) $id;
        $stmt = $this->db()->prepare(
            "UPDATE incidencias SET estado = 'en_gestion'
             WHERE id = :id AND tenant_id = :tid AND estado = 'pendiente'"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['exito'] = 'Incidencia marcada en gestión.';
        } else {
            $_SESSION['error'] = 'La incidencia ya no está pendiente.';
        }

        $this->volver('incidencias/detalle/' . $id);
    }

    // ── Resolver (cobrado / perdonado / repuesto) ──────────────
    public function resolver($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->volver('incidencias/index');

        $id         = (int) $id;
        $resolucion = $_POST['resolucion'] ?? '';
        $montoTexto = trim($_POST['monto'] ?? '');
        $nota       = trim($_POST['nota_resolucion'] ?? '');

        if (!in_array($resolucion, self::RESOLUCIONES, true)) {
            $_SESSION['error'] = 'Elegí cómo se resolvió la incidencia.';
            $this->volver('incidencias/detalle/' . $id);
        }

        // El monto solo existe si se cobró (la base también lo exige con un CHECK)
        $monto = null;
        if ($resolucion === 'cobrado') {
            if (!is_numeric($montoTexto) || (float) $montoTexto <= 0 || (float) $montoTexto > 99999999) {
                $_SESSION['error'] = 'Si se cobró, el monto debe ser mayor a cero.';
                $this->volver('incidencias/detalle/' . $id);
            }
            $monto = round((float) $montoTexto, 2);
        }

        $db  = $this->db();
        $tid = $this->tenantId();

        $db->beginTransaction();
        try {
            // FOR UPDATE: si dos personas resuelven a la vez, la segunda
            // espera y luego ve que ya no está abierta. Así 'repuesto'
            // nunca suma inventario dos veces.
            $stmt = $db->prepare(
                "SELECT id, inventario_id, cantidad FROM incidencias
                 WHERE id = :id AND tenant_id = :tid
                   AND estado IN ('pendiente', 'en_gestion')
                 FOR UPDATE"
            );
            $stmt->execute([':id' => $id, ':tid' => $tid]);
            $incidencia = $stmt->fetch();

            if (!$incidencia) {
                $db->rollBack();
                $_SESSION['error'] = 'La incidencia no existe o ya fue resuelta.';
                $this->volver('incidencias/detalle/' . $id);
            }

            $db->prepare(
                "UPDATE incidencias
                 SET estado = :estado, monto_cobrado = :monto, nota_resolucion = :nota,
                     usuario_resuelve_id = :usr, fecha_resolucion = :fecha
                 WHERE id = :id AND tenant_id = :tid"
            )->execute([
                ':estado' => $resolucion,
                ':monto'  => $monto,
                ':nota'   => $nota !== '' ? $nota : null,
                ':usr'    => (int) $_SESSION['usuario_id'],
                ':fecha'  => ahoraGt(),
                ':id'     => $id,
                ':tid'    => $tid,
            ]);

            // El cliente trajo unidades nuevas: vuelven al inventario
            if ($resolucion === 'repuesto') {
                $db->prepare(
                    "UPDATE inventario
                     SET cantidad_total      = cantidad_total      + :cant1,
                         cantidad_disponible = cantidad_disponible + :cant2
                     WHERE id = :iid AND tenant_id = :tid"
                )->execute([
                    ':cant1' => (int) $incidencia['cantidad'],
                    ':cant2' => (int) $incidencia['cantidad'],
                    ':iid'   => (int) $incidencia['inventario_id'],
                    ':tid'   => $tid,
                ]);
            }

            $db->commit();
            $_SESSION['exito'] = 'Incidencia resuelta.'
                . ($resolucion === 'repuesto' ? ' Las unidades repuestas volvieron al inventario.' : '');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $_SESSION['error'] = mensajeError($e, 'No se pudo resolver la incidencia.');
        }

        $this->volver('incidencias/detalle/' . $id);
    }

    // ── Helper: una incidencia con todo lo necesario para mostrarla ──
    private function buscar(int $id): ?array {
        $stmt = $this->db()->prepare(
            "SELECT inc.*, i.nombre AS producto_nombre,
                    r.fecha_uso, r.fecha_recoleccion_programada,
                    c.nombre_completo, c.telefono,
                    s.nombre AS sucursal_nombre,
                    ur.nombre AS reporta_nombre,
                    us.nombre AS resuelve_nombre
             FROM incidencias inc
             INNER JOIN reservas   r  ON r.id  = inc.reserva_id
             INNER JOIN clientes   c  ON c.id  = r.cliente_id
             INNER JOIN sucursales s  ON s.id  = r.sucursal_id
             INNER JOIN inventario i  ON i.id  = inc.inventario_id
             INNER JOIN usuarios   ur ON ur.id = inc.usuario_reporta_id
             LEFT  JOIN usuarios   us ON us.id = inc.usuario_resuelve_id
             WHERE inc.id = :id AND inc.tenant_id = :tid"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }
}
