<?php
// ============================================================
//  Tema visual de Alquileres — paleta de SOLTEC (la misma de
//  soltec.click, Brix, el panel maestro y el POS)
//
//  Las vistas usan "teal" (marca) y "slate" (grises) de Tailwind. En vez
//  de editarlas una por una, aqui se REDEFINEN esas dos familias con los
//  colores de SOLTEC.
//
//  NO se tocan los colores de estado ni los que tienen significado propio:
//  emerald (ok/entregado), orange (pendiente/alertas), red, amber, sky
//  (en gestion), violet (vitrina) y el verde de WhatsApp.
//
//  Lo incluyen header.php y login.php. El comprobante termico (80mm) no:
//  sale en blanco y negro por la impresora.
// ============================================================
?>
<meta name="theme-color" content="#0d1b2a">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="<?= URL_BASE ?>/app/public/vendor/tailwind-3.4.17.js"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    // Marca: teal -> azul de SOLTEC
                    teal: {
                        50:  '#eaf4ff',
                        100: '#dbe9fb',
                        200: '#bfd6f2',
                        300: '#a3c0e3',
                        400: '#4db8ff',   // celeste
                        500: '#2563eb',   // borde de campo enfocado
                        600: '#2563eb',   // botones
                        700: '#1b4bc0',   // hover de botones, importes, iconos
                        800: '#1b263b',   // titulos
                        900: '#0d1b2a'    // azul marino principal
                    },
                    // Grises -> azul marino de SOLTEC (igual que Brix)
                    slate: {
                        50:  '#f7f9fc',
                        100: '#f4f7fb',   // fondo de pagina (igual que la landing)
                        200: '#dbe3ee',   // bordes suaves
                        300: '#c3cfde',   // bordes de campos
                        400: '#8b9bb0',
                        500: '#5b6779',   // texto suave de la landing
                        600: '#415a77',
                        700: '#2c3e55',
                        800: '#1b263b',
                        900: '#0d1b2a'
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

    /* Menu lateral: mismo degradado que Brix, el POS y la landing */
    aside.sidebar { background: linear-gradient(180deg, #0d1b2a 0%, #1b263b 100%) !important; }
    .sidebar .border-teal-600 { border-color: rgba(255, 255, 255, 0.12) !important; }
    .sidebar .bg-teal-800 { background-color: rgba(0, 0, 0, 0.22) !important; }

    a:focus-visible, button:focus-visible {
        outline: 3px solid #4db8ff;
        outline-offset: 2px;
    }

    @media (prefers-reduced-motion: reduce) {
        * { transition: none !important; }
    }
</style>
