# SOLTEC — Soluciones Tecnológicas

Plataforma SaaS multi-tenant en PHP 8 y MySQL, con sistemas listos para vender a negocios por mensualidad.

## Sistemas

| Carpeta | Sistema | Descripción |
|---|---|---|
| `pos/` | POS GT | Punto de venta, inventario, cotizaciones, proveedores e informes |
| `saas/` | Alquileres | Reservas, inventario por sucursal, entregas y devoluciones con fotos |
| `brix/` | Brix | Control de ingresos, egresos e inventario para pequeños negocios |
| `admin/` | Panel maestro | Administración de clientes (tenants), planes y accesos |
| `/` | Landing | Sitio público de SOLTEC |

## Características

- Multi-tenant: cada negocio ve solo sus datos.
- Arquitectura MVC propia (rutas, controladores, vistas).
- Autenticación con verificación en dos pasos (TOTP) en el panel maestro.
- Consultas con PDO y sentencias preparadas.
- Modo offline para el POS (service worker).

## Tecnologías

PHP 8 · MySQL · PDO · Tailwind CSS · JavaScript · Apache (.htaccess)

## Seguridad

Las credenciales, las copias de bases de datos y los archivos que suben los clientes
(logos, fotos, documentos) **no forman parte de este repositorio**.

## Autor

Gilmar Tello Vásquez — [@Gilmar-Tello](https://github.com/Gilmar-Tello)
