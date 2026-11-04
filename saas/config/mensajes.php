<?php
// ============================================================
// MENSAJES — Alquileres SOLTEC
//
// El texto de WhatsApp se arma en un solo lugar: antes estaba
// duplicado en el detalle y en el comprobante, y cualquier
// cambio habia que hacerlo dos veces.
//
// Sin emojis: en telefonos y computadoras viejas salen como
// cuadritos, y un mensaje de negocio se lee mas serio sin ellos.
// Los asteriscos si se quedan: WhatsApp los usa para negrita.
// ============================================================

// Deja el telefono listo para wa.me: solo digitos y con el
// codigo de pais de Guatemala si vino de 8 digitos.
function telefonoWhatsapp(?string $telefono): string {
    $limpio = preg_replace('/\D/', '', (string) $telefono);
    if (strlen($limpio) === 8) $limpio = '502' . $limpio;
    return $limpio;
}

// A donde se entrega ESTA reserva: la direccion que se escribio al
// crearla y, si quedo vacia, la del cliente. El cliente puede vivir
// en un lado y hacer el evento en otro.
function direccionEntrega(array $reserva, string $vacio = ''): string {
    $dir = trim((string) ($reserva['direccion_entrega'] ?? ''));
    if ($dir === '') $dir = trim((string) ($reserva['direccion'] ?? ''));
    return $dir !== '' ? $dir : $vacio;
}

function mensajeReservaWhatsapp(array $reserva, array $detalle): string {
    $productos = implode(', ', array_map(
        fn($d) => $d['cantidad'] . ' ' . $d['producto_nombre'],
        $detalle
    ));

    $lineas = [];
    $lineas[] = "Estimado/a *{$reserva['nombre_completo']}*, *{$reserva['nombre_negocio']}*"
              . " - Sucursal *{$reserva['sucursal_nombre']}* le confirma su reserva:";
    $lineas[] = '';
    $lineas[] = "*No. de reserva:* {$reserva['id']}";
    $lineas[] = "*Productos:* {$productos}";
    // Cada fecha con su hora; las reservas viejas no la tienen y
    // salen solo con el dia, como antes.
    $conHora = function (?string $fecha, ?string $hora): string {
        $t = fechaCorta($fecha);
        if (!empty($hora)) $t .= ' a las ' . horaCorta($hora);
        return $t;
    };
    $fechaEntrega = !empty($reserva['fecha_entrega']) ? $reserva['fecha_entrega'] : $reserva['fecha_uso'];

    $lineas[] = '*Entrega:* ' . $conHora($fechaEntrega, $reserva['hora_entrega'] ?? null);
    $lineas[] = '*Fecha de uso:* ' . $conHora($reserva['fecha_uso'], $reserva['hora_uso'] ?? null);

    if (!empty($reserva['fecha_recoleccion_programada'])) {
        $lineas[] = '*Recoleccion:* ' . $conHora($reserva['fecha_recoleccion_programada'], $reserva['hora_recoleccion'] ?? null);
    }

    $lineas[] = '*Direccion de entrega:* ' . direccionEntrega($reserva, 'Por coordinar');

    $costo = (float) ($reserva['costo_inicial_monto'] ?? 0);
    if ($costo > 0) {
        $etiqueta = trim((string) ($reserva['costo_inicial_desc'] ?? '')) ?: 'Costo inicial';
        $lineas[] = "*{$etiqueta}:* Q" . number_format($costo, 2);
    }

    $lineas[] = '*Total:* Q' . number_format($reserva['total'], 2);
    $lineas[] = '';
    $lineas[] = 'Cualquier duda estamos a la orden. Gracias por preferirnos.';

    return implode("\n", $lineas);
}

function urlWhatsappReserva(array $reserva, array $detalle): string {
    return 'https://wa.me/' . telefonoWhatsapp($reserva['telefono'])
         . '?text=' . rawurlencode(mensajeReservaWhatsapp($reserva, $detalle));
}

// ── Ubicacion en bodega ────────────────────────────────────
// Se lee como una celda de Excel: "Estanteria A · C3 / F2"
function ubicacionTexto(array $producto, string $vacio = '—'): string {
    $estante = trim((string) ($producto['estanteria'] ?? ''));
    $columna = trim((string) ($producto['columna']    ?? ''));
    $fila    = trim((string) ($producto['fila']       ?? ''));

    if ($estante === '' && $columna === '' && $fila === '') return $vacio;

    $celda = [];
    if ($columna !== '') $celda[] = 'Col ' . $columna;
    if ($fila    !== '') $celda[] = 'Fila ' . $fila;

    $partes = [];
    if ($estante !== '') $partes[] = $estante;
    if ($celda)          $partes[] = implode(' / ', $celda);

    return implode(' · ', $partes);
}

// ── Incidencias ────────────────────────────────────────────
// Primer mensaje al cliente cuando al recoger faltó algo o
// regresó dañado. Es para abrir la conversación, no para cobrar:
// el monto se acuerda después, por mensaje o llamada.
function mensajeIncidenciaWhatsapp(array $incidencia): string {
    $que = $incidencia['tipo'] === 'perdido' ? 'no se encontraron al momento de recoger' : 'regresaron con daños';
    $negocio = $_SESSION['nombre_negocio'] ?? '';

    $lineas = [];
    $lineas[] = "Estimado/a *{$incidencia['nombre_completo']}*, le saluda *{$negocio}*.";
    $lineas[] = '';
    $lineas[] = "Al recoger su reserva *No. {$incidencia['reserva_id']}* notamos que"
              . " *{$incidencia['cantidad']} {$incidencia['producto_nombre']}* {$que}.";
    $lineas[] = '';
    $lineas[] = 'Queremos coordinar con usted la mejor forma de resolverlo. Quedamos atentos a su respuesta.';

    return implode("\n", $lineas);
}
