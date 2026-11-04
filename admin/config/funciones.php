<?php
// ============================================================
// Catálogo de FUNCIONES OPCIONALES (mejoras) que se encienden por negocio
// desde el panel maestro. Todo está APAGADO por defecto.
//
//   'clave' => [
//       'nombre', 'icono', 'descripcion',
//       'costo_extra' => true|false,   // solo informativo: se muestra el aviso "Costo extra"
//       'disponible'  => true|false,   // false = aún no está programada: aparece como "Próximamente"
//   ]
//
// Para agregar una función nueva: se añade aquí, y en el sistema se pregunta con
// funcionActiva('clave') (POS: config/seguridad.php).
// ============================================================

function catalogoFunciones(string $sistema): array {
    static $catalogo = [
        'pos' => [
            'fotos_productos' => [
                'nombre'      => 'Fotos de productos',
                'icono'       => '📷',
                'descripcion' => 'Subir o tomar una foto por producto; se ve en el inventario y en la pantalla de venta.',
                'costo_extra' => true,
                'precio'      => 'Q75 al mes',
                'disponible'  => true,    // lista: requiere correr migracion_pos_mejoras antes de encenderla
            ],
            'sync_brix' => [
                'nombre'      => 'Sincronizar con Brix',
                'icono'       => '🔄',
                'descripcion' => 'Cada cierre de caja y cada compra recibida se registran solos en el libro de caja de Brix.',
                'costo_extra' => true,
                'disponible'  => true,    // requiere: migracion_brix_libro_caja + migracion_pos_mejoras + tarea programada (cron)
                'parametro'   => 'negocio_brix',   // hay que elegir a qué negocio de Brix se envía
                'precio'      => 'Q100 al mes',
            ],
            'sucursales' => [
                'nombre'      => 'Sucursales',
                'icono'       => '🏬',
                'descripcion' => 'Varias sucursales con el mismo sistema: cada usuario trabaja en la suya y en Informes se ve cuál vende más y el detalle de cada una. Las sucursales las creas tú desde aquí (botón "Administrar sucursales").',
                'costo_extra' => true,
                'precio'      => 'Q100 por sucursal al mes',
                'disponible'  => true,    // requiere: migracion_pos_sucursales_planes
                'enlace_admin' => 'sucursales',
            ],
        ],
    ];
    return $catalogo[$sistema] ?? [];
}

// Lo que se cobra por cada sucursal activa (solo informativo en el panel)
const SUCURSAL_PRECIO_MES = 100;

// El valor guardado 'estandar' se muestra como "Medio"
function etiquetaPlan($plan): string {
    return ['basico' => 'Básico', 'estandar' => 'Medio', 'premium' => 'Premium'][$plan] ?? (string) $plan;
}
