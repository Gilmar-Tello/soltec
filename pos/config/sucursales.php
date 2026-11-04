<?php
// ============================================================
//  SUCURSALES (funcion extra "sucursales"; Q100 al mes por sucursal).
//  Las crea SOLTEC desde el panel maestro. "Principal" = sucursal_id vacio (NULL): el negocio tal como era.
//  Cada usuario pertenece a una sucursal; el turno de caja (caja_sesiones.sucursal_id) la hereda al abrirse,
//  y asi cada venta queda ligada a una sucursal para los informes.
//  Productos y clientes pertenecen a UNA sola sucursal (columna sucursal_id; 0 = Principal), cada sucursal tiene su propio catalogo.
//  Convencion: productos y clientes usan 0 para la Principal; usuarios y caja_sesiones usan NULL.
// ============================================================

const NOMBRE_SUCURSAL_PRINCIPAL = 'Principal';

// Sucursales ACTIVAS: id => nombre. Vacio si la funcion esta apagada o la tabla aun no existe.
function sucursalesDelNegocio(): array {
    static $lista = null;
    if ($lista !== null) return $lista;
    $lista = [];
    if (!funcionActiva('sucursales')) return $lista;
    try {
        $st = obtenerConexion()->prepare("SELECT id, nombre FROM sucursales WHERE tenant_id = :t AND activo = 1 ORDER BY nombre");
        $st->execute([':t' => (int) ($_SESSION['tenant_id'] ?? 0)]);
        foreach ($st->fetchAll() as $f) $lista[(int) $f['id']] = $f['nombre'];
    } catch (Throwable $e) {
        error_log('[SOLTEC pos] sucursales no disponible: ' . $e->getMessage());
    }
    return $lista;
}

// ¿El negocio tiene la funcion encendida y al menos una sucursal activa?
function sucursalesHabilitadas(): bool { return sucursalesDelNegocio() !== []; }

// Nombres de TODAS las sucursales (tambien las desactivadas, para informes historicos). 0 = Principal.
function nombresSucursales(): array {
    static $nombres = null;
    if ($nombres !== null) return $nombres;
    $nombres = [0 => NOMBRE_SUCURSAL_PRINCIPAL];
    try {
        $st = obtenerConexion()->prepare("SELECT id, nombre, activo FROM sucursales WHERE tenant_id = :t ORDER BY nombre");
        $st->execute([':t' => (int) ($_SESSION['tenant_id'] ?? 0)]);
        foreach ($st->fetchAll() as $f) $nombres[(int) $f['id']] = $f['nombre'] . ($f['activo'] ? '' : ' (inactiva)');
    } catch (Throwable $e) { /* sin tabla: solo Principal */ }
    return $nombres;
}

// Sucursal del usuario conectado (null = Principal). Solo cuenta si esa sucursal sigue activa.
function sucursalDeUsuario(): ?int {
    if (!sucursalesHabilitadas()) return null;
    try {
        $st = obtenerConexion()->prepare("SELECT sucursal_id FROM usuarios WHERE id = :u AND tenant_id = :t");
        $st->execute([':u' => (int) ($_SESSION['usuario_id'] ?? 0), ':t' => (int) ($_SESSION['tenant_id'] ?? 0)]);
        $s = (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return null;
    }
    return isset(sucursalesDelNegocio()[$s]) ? $s : null;
}

// ── Sucursal de TRABAJO: la que se esta viendo y administrando ──────────────────────────────
//   - Cajero/bodeguero: siempre la suya.
//   - Administrador: la que elija en el selector del menu (queda en la sesion); si no ha elegido, la suya.
// Siempre devuelve un entero (0 = Principal), a diferencia de sucursalDeUsuario() que devuelve null.
// Todo lo que muestre o guarde productos y clientes debe preguntar aqui, nunca leer $_SESSION directo.

// ¿Es administrador y el negocio tiene sucursales activas? (los demas nunca ven el selector)
function esAdminConSucursales(): bool {
    return (int) ($_SESSION['usuario_rol_id'] ?? 0) === 1 && sucursalesHabilitadas();
}

function sucursalTrabajo(): int {
    if (!sucursalesHabilitadas()) return 0;
    if (esAdminConSucursales() && isset($_SESSION['sucursal_trabajo'])) {
        $elegida = (int) $_SESSION['sucursal_trabajo'];
        if ($elegida === 0 || isset(sucursalesDelNegocio()[$elegida])) return $elegida;
        unset($_SESSION['sucursal_trabajo']);   // la elegida se desactivo: vuelve a la suya
    }
    return sucursalDeUsuario() ?? 0;
}

function nombreSucursalTrabajo(): string {
    return nombresSucursales()[sucursalTrabajo()] ?? NOMBRE_SUCURSAL_PRINCIPAL;
}

// Cambia la sucursal que el administrador esta viendo. false si no puede o si la sucursal no es valida.
function cambiarSucursalTrabajo(int $id): bool {
    if (!esAdminConSucursales()) return false;
    if ($id !== 0 && !isset(sucursalesDelNegocio()[$id])) return false;
    $_SESSION['sucursal_trabajo'] = $id;
    return true;
}

// ¿El catalogo (productos y clientes) de este negocio esta repartido por sucursal?
// Si: la columna sucursal_id existe (migracion de catalogo por sucursal) y el negocio tiene al menos una sucursal
// registrada, activa o no. Asi los datos de una sucursal desactivada, o con la funcion apagada, NUNCA se mezclan
// con los de la Principal. Si no, todo funciona como siempre y ni se toca la columna.
function catalogoPorSucursal(): bool {
    static $r = null;
    if ($r !== null) return $r;
    $r = false;
    if (count(nombresSucursales()) > 1) {
        try {
            $r = (bool) obtenerConexion()->query("SHOW COLUMNS FROM productos LIKE 'sucursal_id'")->fetch();
        } catch (Throwable $e) {
            $r = false;
        }
    }
    return $r;
}

// ¿La tabla sucursales ya tiene las 4 columnas del comprobante (direccion, telefono, nit y logo) y el telefono
// admite 50 caracteres? (migracion del 02/11). Ojo: direccion y telefono ya existian desde el 28/10, pero nit y logo
// no: por eso se revisan las cuatro. Si falta algo, todas las sucursales usan los datos del negocio, como antes.
function sucursalesTienenDatos(): bool {
    static $r = null;
    if ($r !== null) return $r;
    try {
        $n = (int) obtenerConexion()->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'sucursales'
                AND column_name IN ('direccion', 'telefono', 'nit', 'logo')
                AND NOT (column_name = 'telefono' AND CHARACTER_MAXIMUM_LENGTH < 50)")->fetchColumn();
        $r = ($n === 4);
    } catch (Throwable $e) {
        $r = false;
    }
    return $r;
}

// Datos del negocio para el comprobante, los PDF y el encabezado, segun la sucursal: la fila de
// configuracion_negocio, con la direccion, el telefono, el NIT y el logo PROPIOS de la sucursal cuando los tiene
// (lo que la sucursal deje vacio, usa lo del negocio). 0 = Principal = los datos del negocio.
function configEfectiva(int $sucursalId) {
    static $cache = [];
    $tid   = tenantActual();
    $clave = $tid . ':' . $sucursalId;
    if (array_key_exists($clave, $cache)) return $cache[$clave];
    $st = obtenerConexion()->prepare("SELECT * FROM configuracion_negocio WHERE tenant_id = :tid LIMIT 1");
    $st->execute([':tid' => $tid]);
    $c = $st->fetch();
    if ($c && $sucursalId > 0) {
        // 'nombre_sucursal' = nombre de la sucursal (null en la Principal): el comprobante lo muestra bajo el nombre del negocio
        $conDatos = sucursalesTienenDatos();
        $s = obtenerConexion()->prepare("SELECT nombre" . ($conDatos ? ", direccion, telefono, nit, logo" : "") . " FROM sucursales WHERE id = :id AND tenant_id = :tid");
        $s->execute([':id' => $sucursalId, ':tid' => $tid]);
        $propios = $s->fetch();
        if ($propios) {
            $c['nombre_sucursal'] = $propios['nombre'];
            if ($conDatos) {
                foreach (['direccion', 'telefono', 'nit', 'logo'] as $campo) {
                    if (trim((string) ($propios[$campo] ?? '')) !== '') $c[$campo] = $propios[$campo];
                }
            }
        }
    }
    return $cache[$clave] = $c;
}

// Fragmento SQL para dejar solo lo de la sucursal de trabajo: " AND p.sucursal_id = 3" (alias opcional).
// El numero sale de sucursalTrabajo() (siempre un entero), por eso va directo en el SQL, sin placeholder.
// Vacio si el catalogo no esta repartido por sucursal.
function sqlSucursal(string $alias = ''): string {
    return sqlSucursalId(sucursalTrabajo(), $alias);
}

// Lo mismo para una sucursal concreta (la venta usa la de su CAJA, no la que el administrador esta viendo).
// $sucursalId debe ser un entero: 0 = Principal.
function sqlSucursalId(int $sucursalId, string $alias = ''): string {
    if (!catalogoPorSucursal()) return '';
    return ' AND ' . ($alias !== '' ? $alias . '.' : '') . 'sucursal_id = ' . $sucursalId;
}
