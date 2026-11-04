<?php
// ============================================================
// Costo de lo vendido
//
// detalle_venta.costo_unitario guarda lo que COSTABA el producto en el momento de venderlo.
// Asi la ganancia de los informes pasados no cambia cuando el costo del producto sube o baja
// (por ejemplo, al recibir una compra mas cara y recalcularse el costo promedio).
//
// Todo depende de la migracion PASO2_migracion_2026-11-02.sql: mientras la columna no exista,
// el sistema se comporta como antes (costo = precio de compra actual del producto) y NO recalcula el costo
// al recibir compras.
// ============================================================

// ¿Ya existe la columna detalle_venta.costo_unitario?
function ventasGuardanCosto(): bool {
    static $r = null;
    if ($r !== null) return $r;
    try {
        $r = (bool) obtenerConexion()->query("SHOW COLUMNS FROM detalle_venta LIKE 'costo_unitario'")->fetch();
    } catch (Throwable $e) {
        $r = false;
    }
    return $r;
}

// Expresion SQL del costo unitario de una linea de venta: el costo guardado al vender o, si esa linea no lo
// tiene, el costo actual del producto. $d = alias de detalle_venta, $p = alias de productos.
function sqlCostoLinea(string $d = 'd', string $p = 'p'): string {
    return ventasGuardanCosto() ? "COALESCE($d.costo_unitario, $p.precio_compra)" : "$p.precio_compra";
}
