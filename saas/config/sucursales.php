<?php

// ============================================================
// Sucursales: de dónde sale el producto
//
//   operativa → tiene su propio inventario y despacha ella misma
//   vitrina   → solo atiende y reserva; el producto sale de su
//               bodega (bodega_id), que es una sucursal operativa
//
// Todo lo que toca stock (buscar productos, combos, guardar una
// reserva) pregunta aquí a qué sucursal ir, en vez de usar
// directamente la sucursal donde se hizo la reserva.
// ============================================================

// Devuelve el id de la sucursal cuyo inventario se usa.
// 0 = no existe, o es una vitrina sin bodega asignada (no hay stock).
function sucursalDeStock(int $sucursalId, int $tenantId): int {
    static $memoria = [];
    $clave = $tenantId . ':' . $sucursalId;
    if (isset($memoria[$clave])) return $memoria[$clave];

    $stmt = obtenerConexion()->prepare(
        "SELECT tipo, bodega_id FROM sucursales WHERE id = :id AND tenant_id = :tid"
    );
    $stmt->execute([':id' => $sucursalId, ':tid' => $tenantId]);
    $s = $stmt->fetch();

    if (!$s) return $memoria[$clave] = 0;
    if ($s['tipo'] === 'vitrina') return $memoria[$clave] = (int) ($s['bodega_id'] ?? 0);
    return $memoria[$clave] = $sucursalId;
}
