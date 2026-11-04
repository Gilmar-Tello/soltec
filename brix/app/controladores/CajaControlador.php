<?php

// ============================================================
//  LIBRO DE CAJA — todos los ingresos y egresos de un periodo, con saldo corrido.
//  Ingresos: ventas, ingresos manuales y (si el negocio lo activa) cierres de caja del POS.
//  Egresos: gastos manuales y compras del POS. Lo anulado no cuenta en ningun total.
// ============================================================
class CajaControlador {

    private function db() { return obtenerConexion(); }
    private function tid(): int { return tenantActual(); }

    private function negocio(): array {
        $st = $this->db()->prepare("SELECT id, nombre_negocio, saldo_inicial, saldo_inicial_fecha FROM negocios WHERE id = :id");
        $st->execute([':id' => $this->tid()]);
        return $st->fetch() ?: ['id' => $this->tid(), 'nombre_negocio' => '', 'saldo_inicial' => 0, 'saldo_inicial_fecha' => null];
    }

    // Saldo con el que arranca el periodo: saldo inicial del libro + todo lo que pasó antes de $desde
    private function saldoAnterior(int $tid, array $neg, string $desde): float {
        $ancla = $neg['saldo_inicial_fecha'] ?: null;
        $suma = function (string $tabla) use ($tid, $desde, $ancla): float {
            $sql = "SELECT COALESCE(SUM(monto),0) FROM `$tabla`
                    WHERE tenant_id = :t AND anulado_en IS NULL AND DATE(creado_en) < :d";
            $p = [':t' => $tid, ':d' => $desde];
            if ($ancla) { $sql .= " AND DATE(creado_en) >= :a"; $p[':a'] = $ancla; }
            $st = $this->db()->prepare($sql);
            $st->execute($p);
            return (float) $st->fetchColumn();
        };
        return round((float) $neg['saldo_inicial'] + $suma('ingresos') - $suma('egresos'), 2);
    }

    private function movimientos(int $tid, string $desde, string $hasta): array {
        $st = $this->db()->prepare(
            "(SELECT 'ingreso' AS clase, i.id AS id, i.creado_en AS creado_en, i.tipo AS tipo, i.origen AS origen,
                     COALESCE(NULLIF(i.concepto,''), IF(i.tipo = 'venta', 'Venta', 'Ingreso')) AS detalle, i.monto AS monto
              FROM ingresos i
              WHERE i.tenant_id = :t1 AND i.anulado_en IS NULL AND DATE(i.creado_en) BETWEEN :d1 AND :h1)
             UNION ALL
             (SELECT 'egreso', e.id, e.creado_en, 'gasto', e.origen,
                     CONCAT_WS(' · ', NULLIF(e.concepto,''), NULLIF(e.descripcion,'')), e.monto
              FROM egresos e
              WHERE e.tenant_id = :t2 AND e.anulado_en IS NULL AND DATE(e.creado_en) BETWEEN :d2 AND :h2)
             ORDER BY creado_en ASC, clase ASC, id ASC"
        );
        $st->execute([':t1' => $tid, ':d1' => $desde, ':h1' => $hasta, ':t2' => $tid, ':d2' => $desde, ':h2' => $hasta]);
        return $st->fetchAll();
    }

    // Etiqueta y color del origen de cada movimiento
    private function etiqueta(array $m): array {
        if ($m['clase'] === 'ingreso') {
            if ($m['origen'] === 'pos')   return ['Cierre POS',     'bg-sky-600'];
            if ($m['tipo'] === 'venta')   return ['Venta',          'bg-emerald-600'];
            return ['Ingreso', 'bg-emerald-600'];
        }
        if ($m['origen'] === 'pos')       return ['Compra POS',     'bg-amber-600'];
        return ['Gasto', 'bg-red-600'];
    }

    // ── Pantalla principal ─────────────────────────────────────
    public function index() {
        $tid = $this->tid();
        $neg = $this->negocio();

        $hoy   = date('Y-m-d');
        $desde = fechaGet('desde', date('Y-m-01'));
        $hasta = fechaGet('hasta', $hoy);
        if ($hasta < $desde) { [$desde, $hasta] = [$hasta, $desde]; }

        // El libro no cuenta nada anterior a su fecha de inicio
        $ancla = $neg['saldo_inicial_fecha'] ?: null;
        $aviso = '';
        if ($ancla && $desde < $ancla) {
            $desde = $ancla;
            if ($hasta < $desde) $hasta = $desde;
            $aviso = 'El libro de caja empieza el ' . date('d/m/Y', strtotime($ancla)) . '; lo anterior no se cuenta.';
        }

        $saldoInicial = $this->saldoAnterior($tid, $neg, $desde);
        $movimientos  = $this->movimientos($tid, $desde, $hasta);

        $saldo = $saldoInicial; $entradas = 0.0; $salidas = 0.0;
        $ingresosPdf = []; $egresosPdf = [];
        $gastos = []; $otrosIngresos = [];
        foreach ($movimientos as &$m) {
            $monto = (float) $m['monto'];
            [$m['etiqueta'], $m['color']] = $this->etiqueta($m);
            if ($m['clase'] === 'ingreso') {
                $m['entrada'] = $monto; $m['salida'] = null;
                $saldo += $monto; $entradas += $monto;
                $ingresosPdf[] = ['fecha' => date('d/m/Y', strtotime($m['creado_en'])), 'detalle' => $m['detalle'], 'monto' => $monto];
                if ($m['tipo'] === 'libre') $otrosIngresos[] = $m;           // los que se pueden anular
            } else {
                $m['entrada'] = null; $m['salida'] = $monto;
                $saldo -= $monto; $salidas += $monto;
                $egresosPdf[] = ['fecha' => date('d/m/Y', strtotime($m['creado_en'])), 'detalle' => $m['detalle'], 'monto' => $monto];
                $gastos[] = $m;
            }
            $m['saldo'] = round($saldo, 2);
        }
        unset($m);
        $saldoFinal = round($saldo, 2);

        // Sugerencias para el campo "Concepto"
        $sug = $this->db()->prepare(
            "SELECT concepto FROM egresos WHERE tenant_id = :t AND concepto <> '' GROUP BY concepto ORDER BY MAX(id) DESC LIMIT 30");
        $sug->execute([':t' => $tid]);
        $conceptosGasto = array_values(array_unique(array_merge(
            $sug->fetchAll(PDO::FETCH_COLUMN),
            ['Energía eléctrica', 'Agua', 'Internet', 'Alquiler', 'Salarios', 'Insumos', 'Transporte', 'Otros']
        )));

        // Datos para el informe PDF (formato mensual)
        $primero = date('Y-m-01', strtotime($desde));
        $ultimo  = date('Y-m-t',  strtotime($desde));
        $esMes   = ($desde === $primero && substr($hasta, 0, 7) === substr($desde, 0, 7) && $hasta <= $ultimo);
        $datosPdf = [
            'negocio'       => $neg['nombre_negocio'],
            'desde'         => date('d/m/Y', strtotime($desde)),
            'hasta'         => date('d/m/Y', strtotime($hasta)),
            'desdeIso'      => $desde,
            'hastaIso'      => $hasta,
            'esMes'         => $esMes,
            'saldoAnterior' => $saldoInicial,
            'ingresos'      => $ingresosPdf,
            'egresos'       => $egresosPdf,
            'totalIngresos' => round($entradas, 2),
            'totalEgresos'  => round($salidas, 2),
            'saldoFinal'    => $saldoFinal,
        ];

        $tituloPagina = 'Libro de caja';
        include RAIZ . '/app/vistas/caja/index.php';
    }

    // ── Volver al libro con el mismo periodo ───────────────────
    private function volver(): string {
        $d = fechaValida($_POST['desde'] ?? '');
        $h = fechaValida($_POST['hasta'] ?? '');
        $url = URL_BASE . '/?url=caja/index';
        if ($d) $url .= '&desde=' . $d;
        if ($h) $url .= '&hasta=' . $h;
        return $url;
    }

    private function montoValido($v): ?float {
        $m = round((float) str_replace(',', '', (string) $v), 2);
        return ($m > 0 && $m <= 99999999) ? $m : null;
    }

    // Fecha elegida + la hora de ahora (para que el orden dentro del dia sea el real)
    private function marcaDeTiempo(string $fecha): string {
        return $fecha === date('Y-m-d') ? date('Y-m-d H:i:s') : $fecha . ' ' . date('H:i:s');
    }

    private function validarMovimiento(string $concepto, $monto, $fecha, array $neg): ?string {
        if ($concepto === '' || mb_strlen($concepto) > 255)  return 'Escribe un concepto (máximo 255 caracteres).';
        if ($monto === null)                                 return 'El monto debe ser mayor que cero.';
        if (!$fecha)                                         return 'La fecha no es válida.';
        if ($fecha > date('Y-m-d'))                          return 'La fecha no puede ser futura.';
        if (!empty($neg['saldo_inicial_fecha']) && $fecha < $neg['saldo_inicial_fecha'])
            return 'La fecha es anterior al inicio del libro (' . date('d/m/Y', strtotime($neg['saldo_inicial_fecha'])) . ').';
        return null;
    }

    // ── Registrar un gasto ─────────────────────────────────────
    public function registrarGasto() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=caja/index'); exit; }
        $neg      = $this->negocio();
        $concepto = trim((string) ($_POST['concepto'] ?? ''));
        $desc     = trim((string) ($_POST['descripcion'] ?? ''));
        $monto    = $this->montoValido($_POST['monto'] ?? '');
        $fecha    = fechaValida($_POST['fecha'] ?? '');

        $error = $this->validarMovimiento($concepto, $monto, $fecha, $neg);
        if (!$error && mb_strlen($desc) > 500) $error = 'La descripción es demasiado larga (máximo 500 caracteres).';
        if ($error) { $_SESSION['error'] = $error; header('Location: ' . $this->volver()); exit; }

        $this->db()->prepare(
            "INSERT INTO egresos (tenant_id, usuario_id, concepto, descripcion, monto, creado_en, origen)
             VALUES (:t, :u, :c, :d, :m, :f, 'manual')"
        )->execute([
            ':t' => $this->tid(), ':u' => (int) $_SESSION['usuario_id'], ':c' => $concepto,
            ':d' => ($desc !== '' ? $desc : null), ':m' => $monto, ':f' => $this->marcaDeTiempo($fecha),
        ]);
        $_SESSION['exito'] = 'Gasto registrado: Q' . number_format($monto, 2);
        header('Location: ' . $this->volver()); exit;
    }

    // ── Registrar otro ingreso (manual) ────────────────────────
    public function registrarIngreso() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=caja/index'); exit; }
        $neg      = $this->negocio();
        $concepto = trim((string) ($_POST['concepto'] ?? ''));
        $monto    = $this->montoValido($_POST['monto'] ?? '');
        $fecha    = fechaValida($_POST['fecha'] ?? '');

        $error = $this->validarMovimiento($concepto, $monto, $fecha, $neg);
        if ($error) { $_SESSION['error'] = $error; header('Location: ' . $this->volver()); exit; }

        $this->db()->prepare(
            "INSERT INTO ingresos (tenant_id, usuario_id, tipo, concepto, monto, creado_en, origen)
             VALUES (:t, :u, 'libre', :c, :m, :f, 'manual')"
        )->execute([
            ':t' => $this->tid(), ':u' => (int) $_SESSION['usuario_id'], ':c' => $concepto,
            ':m' => $monto, ':f' => $this->marcaDeTiempo($fecha),
        ]);
        $_SESSION['exito'] = 'Ingreso registrado: Q' . number_format($monto, 2);
        header('Location: ' . $this->volver()); exit;
    }

    // ── Anular un movimiento (queda guardado, pero ya no cuenta) ──
    // Se pueden anular los gastos y los ingresos manuales o del POS. Las ventas con detalle de
    // producto no: mueven inventario y se corrigen desde su propio módulo.
    public function anular() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=caja/index'); exit; }
        $clase  = ($_POST['clase'] ?? '') === 'ingreso' ? 'ingreso' : 'egreso';
        $id     = (int) ($_POST['id'] ?? 0);
        $motivo = mb_substr(trim((string) ($_POST['motivo'] ?? '')), 0, 255);
        $tabla  = $clase === 'ingreso' ? 'ingresos' : 'egresos';

        $sql = "UPDATE `$tabla` SET anulado_en = NOW(), anulado_por = :u, anulado_motivo = :m
                WHERE id = :id AND tenant_id = :t AND anulado_en IS NULL";
        if ($clase === 'ingreso') $sql .= " AND tipo = 'libre'";
        $st = $this->db()->prepare($sql);
        $st->execute([':u' => (int) $_SESSION['usuario_id'], ':m' => ($motivo !== '' ? $motivo : null), ':id' => $id, ':t' => $this->tid()]);

        if ($st->rowCount() === 1) $_SESSION['exito'] = 'Movimiento anulado. Ya no cuenta en el libro.';
        else                       $_SESSION['error'] = 'No se pudo anular ese movimiento.';
        header('Location: ' . $this->volver()); exit;
    }

    // ── Saldo inicial del libro ────────────────────────────────
    public function saldoInicial() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . URL_BASE . '/?url=caja/index'); exit; }
        $monto = round((float) str_replace(',', '', (string) ($_POST['saldo_inicial'] ?? '0')), 2);
        $fecha = trim((string) ($_POST['saldo_inicial_fecha'] ?? ''));
        $fecha = ($fecha === '') ? null : fechaValida($fecha);
        if (abs($monto) > 99999999 || (($_POST['saldo_inicial_fecha'] ?? '') !== '' && $fecha === null)) {
            $_SESSION['error'] = 'Revisa el monto y la fecha del saldo inicial.';
            header('Location: ' . $this->volver()); exit;
        }
        $this->db()->prepare("UPDATE negocios SET saldo_inicial = :m, saldo_inicial_fecha = :f WHERE id = :id")
             ->execute([':m' => $monto, ':f' => $fecha, ':id' => $this->tid()]);
        $_SESSION['exito'] = 'Saldo inicial actualizado.';
        header('Location: ' . URL_BASE . '/?url=caja/index'); exit;
    }
}
