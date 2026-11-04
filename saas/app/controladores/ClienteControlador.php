<?php

class ClienteControlador {

    private function db() { return obtenerConexion(); }

    private function tenantId(): int {
        return (int) $_SESSION['tenant_id'];
    }

    // ── Listado ────────────────────────────────────────────────
    public function index() {
        $busqueda = trim($_GET['busqueda'] ?? '');

        // Trabajador con sucursal asignada: solo ve clientes que tienen
        // al menos una reserva en su sucursal, o que él mismo registró.
        if (esTrabajador() && !empty($_SESSION['sucursal_id'])) {
            $sid = (int) $_SESSION['sucursal_id'];
            $sql = "SELECT DISTINCT c.*
                    FROM clientes c
                    WHERE c.tenant_id = :tid
                    AND (
                        EXISTS (
                            SELECT 1 FROM reservas r
                            WHERE r.cliente_id = c.id
                            AND r.tenant_id = c.tenant_id
                            AND r.sucursal_id = :sid
                        )
                        OR NOT EXISTS (
                            SELECT 1 FROM reservas r2
                            WHERE r2.cliente_id = c.id
                            AND r2.tenant_id = c.tenant_id
                        )
                    )";
            $params = [':tid' => $this->tenantId(), ':sid' => $sid];
        } else {
            $sql = "SELECT * FROM clientes c WHERE c.tenant_id = :tid";
            $params = [':tid' => $this->tenantId()];
        }

        if ($busqueda) {
            $sql .= " AND (c.nombre_completo LIKE :b1 OR c.telefono LIKE :b2)";
            $params[':b1'] = "%$busqueda%";
            $params[':b2'] = "%$busqueda%";
        }

        $sql .= " ORDER BY c.nombre_completo ASC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $clientes = $stmt->fetchAll();

        $tituloPagina = 'Clientes';
        include RAIZ . '/app/vistas/clientes/index.php';
    }

    // ── Formulario nuevo ────────────────────────────────────────
    public function nuevo() {
        $volverAReserva = ($_GET['volver'] ?? '') === 'reserva';
        $tituloPagina = 'Nuevo Cliente';
        include RAIZ . '/app/vistas/clientes/form.php';
    }

    // ── Guardar ─────────────────────────────────────────────────
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=clientes/index'); exit;
        }

        $nombre    = trim($_POST['nombre_completo'] ?? '');
        $telefono  = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (!$nombre || !$telefono) {
            $_SESSION['error'] = 'Nombre completo y teléfono son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=clientes/nuevo'); exit;
        }

        // ── Cliente repetido ───────────────────────────────────
        // Mismo nombre Y mismo telefono = es la misma persona.
        // Si solo coincide el telefono puede ser un familiar, asi
        // que eso no se bloquea.
        $repetido = $this->buscarDuplicado($nombre, $telefono);
        if ($repetido) {
            $_SESSION['error'] = "Ese cliente ya está registrado: \"{$repetido['nombre_completo']}\" "
                               . "(tel. {$repetido['telefono']}). Buscalo por su nombre en la reserva "
                               . "en vez de crearlo otra vez.";
            $destino = !empty($_POST['volver_a_reserva']) ? 'reservas/nueva' : 'clientes/nuevo';
            header('Location: ' . URL_BASE . '/?url=' . $destino); exit;
        }

        $stmt = $this->db()->prepare(
            "INSERT INTO clientes (tenant_id, nombre_completo, telefono, direccion)
             VALUES (:tid, :nombre, :tel, :dir)"
        );
        $stmt->execute([
            ':tid' => $this->tenantId(), ':nombre' => $nombre,
            ':tel' => $telefono, ':dir' => $direccion,
        ]);

        $_SESSION['exito'] = "Cliente '$nombre' agregado. Ya podés buscarlo en el formulario de reserva.";

        // Si viene del flujo de reserva, regresa directo ahí
        if (!empty($_POST['volver_a_reserva'])) {
            header('Location: ' . URL_BASE . '/?url=reservas/nueva'); exit;
        }

        header('Location: ' . URL_BASE . '/?url=clientes/index'); exit;
    }

    // ── Formulario editar ──────────────────────────────────────
    public function editar($id) {
        $stmt = $this->db()->prepare("SELECT * FROM clientes WHERE id = :id AND tenant_id = :tid");
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            $_SESSION['error'] = 'Cliente no encontrado.';
            header('Location: ' . URL_BASE . '/?url=clientes/index'); exit;
        }

        $tituloPagina = 'Editar — ' . $cliente['nombre_completo'];
        include RAIZ . '/app/vistas/clientes/form.php';
    }

    // ── Actualizar ──────────────────────────────────────────────
    public function actualizar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . URL_BASE . '/?url=clientes/index'); exit;
        }

        $id        = intval($_POST['id']);
        $nombre    = trim($_POST['nombre_completo'] ?? '');
        $telefono  = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (!$nombre || !$telefono) {
            $_SESSION['error'] = 'Nombre completo y teléfono son obligatorios.';
            header('Location: ' . URL_BASE . '/?url=clientes/editar/' . $id); exit;
        }

        // El mismo control que al crear, pero sin contarse a si mismo
        $repetido = $this->buscarDuplicado($nombre, $telefono, $id);
        if ($repetido) {
            $_SESSION['error'] = "Ya existe otro cliente con ese nombre y teléfono: "
                               . "\"{$repetido['nombre_completo']}\" (tel. {$repetido['telefono']}).";
            header('Location: ' . URL_BASE . '/?url=clientes/editar/' . $id); exit;
        }

        $stmt = $this->db()->prepare(
            "UPDATE clientes SET nombre_completo=:nombre, telefono=:tel, direccion=:dir
             WHERE id=:id AND tenant_id=:tid"
        );
        $stmt->execute([
            ':nombre' => $nombre, ':tel' => $telefono, ':dir' => $direccion,
            ':id' => $id, ':tid' => $this->tenantId(),
        ]);

        $_SESSION['exito'] = 'Cliente actualizado correctamente.';
        header('Location: ' . URL_BASE . '/?url=clientes/index'); exit;
    }

    // ── Historial de alquileres de un cliente ───────────────────
    public function historial($id) {
        $stmt = $this->db()->prepare("SELECT * FROM clientes WHERE id = :id AND tenant_id = :tid");
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId()]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            $_SESSION['error'] = 'Cliente no encontrado.';
            header('Location: ' . URL_BASE . '/?url=clientes/index'); exit;
        }

        $stmt = $this->db()->prepare(
            "SELECT r.*, s.nombre AS sucursal_nombre
             FROM reservas r
             INNER JOIN sucursales s ON s.id = r.sucursal_id
             WHERE r.cliente_id = :cid AND r.tenant_id = :tid
             ORDER BY r.fecha_reservacion DESC"
        );
        $stmt->execute([':cid' => $id, ':tid' => $this->tenantId()]);
        $reservas = $stmt->fetchAll();

        $tituloPagina = 'Historial — ' . $cliente['nombre_completo'];
        include RAIZ . '/app/vistas/clientes/historial.php';
    }

    // ── Deteccion de clientes repetidos ─────────────────────────
    // Compara nombre y telefono ya normalizados: "Maria  Lopez" y
    // "maria lopez" son el mismo nombre, y "5555-1234", "55551234"
    // y "+502 5555 1234" son el mismo telefono.
    private function buscarDuplicado(string $nombre, string $telefono, int $excluirId = 0): ?array {
        $nombreNorm = $this->normalizarNombre($nombre);
        $telNorm    = $this->normalizarTelefono($telefono);

        if ($nombreNorm === '' || $telNorm === '') return null;

        // Se traen solo los del mismo nombre (son poquitos) y el
        // telefono se compara en PHP, que es donde se puede limpiar
        // bien sin depender de funciones de MySQL.
        $stmt = $this->db()->prepare(
            "SELECT id, nombre_completo, telefono
             FROM clientes
             WHERE tenant_id = :tid AND id <> :excluir
               AND REPLACE(REPLACE(LOWER(TRIM(nombre_completo)), '  ', ' '), '  ', ' ') = :nombre"
        );
        $stmt->execute([
            ':tid'     => $this->tenantId(),
            ':excluir' => $excluirId,
            ':nombre'  => $nombreNorm,
        ]);

        foreach ($stmt->fetchAll() as $c) {
            if ($this->normalizarNombre($c['nombre_completo']) !== $nombreNorm) continue;
            if ($this->normalizarTelefono($c['telefono']) === $telNorm) return $c;
        }

        return null;
    }

    private function normalizarNombre(string $nombre): string {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $nombre)));
    }

    // Deja solo digitos y quita el 502 del inicio, para que el mismo
    // numero escrito de tres formas distintas cuente como uno solo.
    private function normalizarTelefono(string $telefono): string {
        $digitos = preg_replace('/\D/', '', $telefono);
        if (strlen($digitos) === 11 && str_starts_with($digitos, '502')) {
            $digitos = substr($digitos, 3);
        }
        return $digitos;
    }

    // ── Buscar cliente vía AJAX (autocompletado en reservas) ────
    // Busca en TODOS los clientes del tenant sin importar sucursal,
    // porque al crear una reserva puede ser un cliente nuevo o de otra sucursal.
    public function buscarajax() {
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            echo json_encode([]); exit;
        }

        $stmt = $this->db()->prepare(
            "SELECT id, nombre_completo, telefono, direccion
             FROM clientes
             WHERE tenant_id = :tid
               AND (nombre_completo LIKE :q1 OR telefono LIKE :q2)
             ORDER BY nombre_completo ASC LIMIT 8"
        );
        $stmt->execute([
            ':tid' => $this->tenantId(),
            ':q1'  => "%$q%",
            ':q2'  => "%$q%",
        ]);
        echo json_encode($stmt->fetchAll());
        exit;
    }
}
