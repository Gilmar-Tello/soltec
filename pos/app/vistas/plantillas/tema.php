<?php
// ============================================================
//  Tema visual del POS — paleta de SOLTEC (la misma de soltec.click,
//  Brix y el panel maestro)
//
//  Las vistas del POS usan la familia "green" de Tailwind como color de
//  marca (menu, botones, titulos, bordes de foco). En vez de editarlas una
//  por una, aqui se REDEFINE "green" con el azul marino / azul de SOLTEC.
//
//  Los ESTADOS de exito (✅ Pagado, ✅ Activo, vuelto, ganancia, alertas de
//  "guardado") usan "emerald" (verde real de Tailwind), que no se toca, para
//  que sigan viendose verdes. Rojo/naranja/amarillo tampoco cambian.
//
//  Este archivo carga tambien Tailwind y la fuente: lo incluyen header.php,
//  login.php y las vistas de impresion (pdf_*.php).
// ============================================================
?>
<meta name="theme-color" content="#0d1b2a">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="<?= URL_BASE ?>/app/public/vendor/tailwind-3.4.17.js"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    // Marca: verde -> azul de SOLTEC
                    green: {
                        50:  '#eaf4ff',   // fondos suaves
                        100: '#dbe9fb',
                        200: '#bfd6f2',
                        300: '#a3c0e3',   // bordes de tarjetas / texto tenue sobre el menu
                        400: '#4db8ff',   // celeste
                        500: '#2563eb',   // borde de campo enfocado
                        600: '#2563eb',   // botones
                        700: '#1b4bc0',   // hover de botones, encabezados de tabla, importes
                        800: '#1b263b',   // titulos
                        900: '#0d1b2a'    // azul marino principal
                    },
                    // Colores sueltos de la landing
                    sol: {
                        fondo: '#f4f7fb',
                        borde: '#dbe3ee'
                    }
                },
                fontFamily: {
                    sans: ['Nunito', 'Segoe UI', 'system-ui', 'sans-serif']
                }
            }
        }
    }
</script>
<style>
    body { font-family: 'Nunito', 'Segoe UI', system-ui, sans-serif; }

    /* Menu lateral: mismo degradado que Brix y la landing */
    aside.sidebar { background: linear-gradient(180deg, #0d1b2a 0%, #1b263b 100%) !important; }
    .sidebar .border-green-700 { border-color: rgba(255, 255, 255, 0.12) !important; }
    .sidebar .bg-green-900 { background-color: rgba(0, 0, 0, 0.22) !important; }
    .sidebar .hover\:bg-green-700:hover { background-color: #2c3e55; }

    a:focus-visible, button:focus-visible {
        outline: 3px solid #4db8ff;
        outline-offset: 2px;
    }

    @media (prefers-reduced-motion: reduce) {
        * { transition: none !important; }
    }
</style>
