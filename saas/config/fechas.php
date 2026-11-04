<?php
// ============================================================
// FECHAS — Alquileres SOLTEC
//
// El problema: una fecha de calendario (19/09/2026) NO es un
// instante en el tiempo. Cuando se pasa por strtotime() o por
// new Date() el motor le inventa una hora (00:00) y una zona
// horaria; si el servidor esta en UTC y el negocio en Guatemala
// (UTC-6), ese 00:00 se corre 6 horas hacia atras y la fecha
// termina mostrandose un dia antes.
//
// La solucion: las fechas puras (fecha_uso, fecha_recoleccion)
// NUNCA se convierten. Se leen como texto 'AAAA-MM-DD' y se
// reacomodan a 'DD/MM/AAAA'. Sin zonas horarias de por medio no
// hay nada que se pueda correr.
// ============================================================

const ZONA_GT = 'America/Guatemala';

// 'AAAA-MM-DD' o 'AAAA-MM-DD HH:MM:SS'  ->  'DD/MM/AAAA'
function fechaCorta($valor, string $vacio = '—'): string {
    if ($valor === null || $valor === '' || $valor === '0000-00-00') return $vacio;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim((string) $valor), $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
    return $vacio;
}

// 'AAAA-MM-DD HH:MM:SS'  ->  'DD/MM/AAAA HH:MM'
function fechaHora($valor, string $vacio = '—'): string {
    if ($valor === null || $valor === '') return $vacio;
    $texto = trim((string) $valor);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $texto, $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1] . ' ' . $m[4] . ':' . $m[5];
    }
    return fechaCorta($texto, $vacio);
}

// Deja el valor listo para un <input type="date"> -> 'AAAA-MM-DD'
function fechaISO($valor): string {
    if ($valor === null || $valor === '') return '';
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', trim((string) $valor), $m)) return $m[1];
    return '';
}

// Hoy segun el reloj de Guatemala, pase lo que pase con el servidor.
function hoyGt(): string {
    return (new DateTimeImmutable('now', new DateTimeZone(ZONA_GT)))->format('Y-m-d');
}

function ahoraGt(): string {
    return (new DateTimeImmutable('now', new DateTimeZone(ZONA_GT)))->format('Y-m-d H:i:s');
}

// Valida que el texto sea una fecha real: '2026-02-31' no lo es.
function esFechaValida($valor): bool {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim((string) $valor), $m)) return false;
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

// Dias enteros entre dos fechas puras. Se comparan en UTC a
// proposito: al no haber horario de verano ni horas de por medio,
// la resta siempre da el numero de dias del calendario.
function diasEntreFechas($desde, $hasta): ?int {
    $d1 = DateTimeImmutable::createFromFormat('!Y-m-d', fechaISO($desde), new DateTimeZone('UTC'));
    $d2 = DateTimeImmutable::createFromFormat('!Y-m-d', fechaISO($hasta), new DateTimeZone('UTC'));
    if (!$d1 || !$d2) return null;
    return (int) $d1->diff($d2)->format('%r%a');
}

// Cuantos dias faltan (o pasaron) desde hoy hasta esa fecha.
function diasDesdeHoy($fecha): ?int {
    return diasEntreFechas(hoyGt(), $fecha);
}

// ============================================================
// HORAS
//
// Igual que con las fechas, la hora de una reserva NO es un
// instante con zona horaria: es "las 2 de la tarde" y punto.
// Se guarda como TIME ('HH:MM:SS') y se maneja como texto, sin
// pasarla nunca por strtotime ni new Date().
//
// Un "momento" es el texto 'AAAA-MM-DD HH:MM:SS'. Como tiene
// siempre el mismo formato, dos momentos se comparan como texto
// (el mas chico es el mas temprano).
// ============================================================

// 'HH:MM' o 'HH:MM:SS' -> 'HH:MM:SS'. Devuelve '' si no es una hora real.
function horaISO($valor): string {
    $v = trim((string) $valor);
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $v, $m)) return '';
    $h  = (int) $m[1];
    $mi = (int) $m[2];
    $s  = isset($m[3]) ? (int) $m[3] : 0;
    if ($h > 23 || $mi > 59 || $s > 59) return '';
    return sprintf('%02d:%02d:%02d', $h, $mi, $s);
}

// '14:30:00' -> '2:30 PM'
function horaCorta($valor, string $vacio = '—'): string {
    $h = horaISO($valor);
    if ($h === '') return $vacio;
    [$hh, $mm] = array_map('intval', explode(':', $h));
    $h12 = $hh % 12;
    if ($h12 === 0) $h12 = 12;
    return $h12 . ':' . sprintf('%02d', $mm) . ' ' . ($hh >= 12 ? 'PM' : 'AM');
}

// ('2026-11-10', '14:30:00') -> '10/11/2026 2:30 PM'. Sin hora, solo la fecha.
function fechaConHora($fecha, $hora, string $vacio = '—'): string {
    $f = fechaCorta($fecha, '');
    if ($f === '') return $vacio;
    $h = horaISO($hora);
    return $h === '' ? $f : $f . ' ' . horaCorta($h);
}

// ('2026-11-10', '14:30') -> '2026-11-10 14:30:00'.
// Si no hay hora usa $horaPorDefecto ('00:00:00' al inicio del dia,
// '23:59:59' al final). Devuelve '' si la fecha no es valida.
function momentoISO($fecha, $hora, string $horaPorDefecto = '00:00:00'): string {
    $f = fechaISO($fecha);
    if ($f === '' || !esFechaValida($f)) return '';
    $h = horaISO($hora);
    return $f . ' ' . ($h !== '' ? $h : $horaPorDefecto);
}

// Para lo que llega por la URL: acepta 'AAAA-MM-DD' o
// 'AAAA-MM-DD HH:MM[:SS]' (tambien con 'T' en medio) y lo deja en
// 'AAAA-MM-DD' o 'AAAA-MM-DD HH:MM:SS'. '' si no es valido.
function fechaOMomentoISO($valor): string {
    $v = trim((string) $valor);
    if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{1,2}:\d{2}(?::\d{2})?)/', $v, $m)) {
        $h = horaISO($m[2]);
        return (esFechaValida($m[1]) && $h !== '') ? $m[1] . ' ' . $h : '';
    }
    $f = fechaISO($v);
    return ($f !== '' && esFechaValida($f)) ? $f : '';
}

// 'AAAA-MM-DD HH:MM:SS' -> '10/11/2026 2:30 PM' (para mensajes)
function momentoCorto($momento, string $vacio = '—'): string {
    $v = fechaOMomentoISO($momento);
    if ($v === '') return $vacio;
    return strlen($v) > 10 ? fechaConHora(substr($v, 0, 10), substr($v, 11)) : fechaCorta($v);
}
