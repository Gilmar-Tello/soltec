<?php
// ============================================================
//  FUNCIONES AVANZADAS — lo que el cliente VE (solo informativo).
//  El cliente NO puede encenderlas: las habilita SOLTEC desde el panel maestro, y tienen costo extra.
//  Aqui solo se muestran, con su estado y precio, y un enlace para pedir informacion por WhatsApp.
//
//  Cada 'clave' debe coincidir con la del catalogo del panel (admin/config/funciones.php).
//  Los precios salen de config/planes.php.
//  Para agregar una funcion nueva: se programa, se agrega en ese catalogo del panel y aqui.
// ============================================================

const WHATSAPP_SOLTEC         = '50254925395';
const WHATSAPP_SOLTEC_VISIBLE = '+502 5492 5395';

function catalogoFuncionesCliente(): array {
    return [
        'fotos_productos' => [
            'nombre'      => 'Fotos de productos',
            'icono'       => '📷',
            'descripcion' => 'Agrega una foto a cada producto. Se ve en el inventario y al buscar en la pantalla de venta: vendes más rápido y sin confundir productos parecidos.',
            'precio'      => 'Q' . PRECIO_FOTOS_PRODUCTOS . ' al mes',
        ],
        'sync_brix' => [
            'nombre'      => 'Conexión con Brix',
            'icono'       => '🔄',
            'descripcion' => 'Si también usas Brix, cada cierre de caja y cada compra recibida pasan solos a su libro de caja. Sin escribir lo mismo dos veces.',
            'precio'      => 'Q' . PRECIO_SYNC_BRIX . ' al mes',
        ],
        'sucursales' => [
            'nombre'      => 'Sucursales',
            'icono'       => '🏬',
            'descripcion' => 'Maneja varias sucursales con el mismo sistema: cada usuario trabaja en la suya y en Informes ves cuál vende más y el detalle de cada una por separado.',
            'precio'      => 'Q' . PRECIO_POR_SUCURSAL . ' por sucursal al mes',
        ],
    ];
}

// Enlace de WhatsApp con el mensaje ya escrito (negocio + funcion que le interesa)
function enlaceWhatsappFuncion(string $negocio, string $funcion): string {
    $texto = 'Hola SOLTEC, soy de ' . $negocio . ' y quiero información de la función avanzada: ' . $funcion;
    return 'https://wa.me/' . WHATSAPP_SOLTEC . '?text=' . rawurlencode($texto);
}

function enlaceWhatsappPlan(string $negocio, string $plan): string {
    $texto = 'Hola SOLTEC, soy de ' . $negocio . ' y quiero información para cambiar al plan ' . $plan . ' del POS.';
    return 'https://wa.me/' . WHATSAPP_SOLTEC . '?text=' . rawurlencode($texto);
}
