<?php
// ============================================================
//  Tema visual de Brix — paleta de SOLTEC (la misma de soltec.click)
//
//  Las vistas de Brix usan las clases "slate" (grises) e "indigo"
//  (acento). En vez de editarlas una por una, aqui se REDEFINEN esas
//  dos familias con los colores de SOLTEC. Verde/rojo/ambar (estados)
//  no se tocan.
//
//  Tambien trae los ajustes para telefono.
// ============================================================
?>
<meta name="theme-color" content="#0d1b2a">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<script src="<?= URL_BASE ?>/app/public/vendor/tailwind-3.4.17.js"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    // Grises -> azul marino de SOLTEC
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
                        900: '#0d1b2a'    // azul marino principal
                    },
                    // Acento -> azul de SOLTEC
                    indigo: {
                        50:  '#eaf4ff',
                        400: '#4db8ff',   // celeste
                        500: '#2563eb',
                        600: '#2563eb',
                        700: '#1b4bc0'
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

    /* Degradado del encabezado de la landing */
    .fondo-marca { background: linear-gradient(135deg, #0d1b2a 0%, #1b263b 60%, #415a77 100%); }
    aside.sidebar { background: linear-gradient(180deg, #0d1b2a 0%, #1b263b 100%) !important; }

    a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible {
        outline: 3px solid #4db8ff;
        outline-offset: 2px;
    }

    /* ── Telefono ─────────────────────────────────────────── */
    @media (max-width: 768px) {
        /* El iPhone hace zoom al tocar un campo con letra menor a 16px */
        input:not([type="checkbox"]):not([type="radio"]):not([type="file"]),
        select, textarea { font-size: 16px !important; }

        /* Campos y botones comodos para el dedo (minimo ~44px) */
        input:not([type="checkbox"]):not([type="radio"]):not([type="file"]),
        select { min-height: 44px; }
        main button, main a.rounded-md { min-height: 44px; }

        /* Botones chicos dentro de tablas ("Quitar", "Editar"...) */
        td button, td a { display: inline-block; padding: .55rem .45rem; }

        main { padding: 1rem !important; }
    }

    @media (prefers-reduced-motion: reduce) {
        * { transition: none !important; }
    }
</style>
