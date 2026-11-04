<?php
// ============================================================
//  Tema visual del Panel Maestro — mismo lenguaje de diseño que la
//  página principal de soltec.click (estilos.css): azul marino,
//  acento azul, celeste, Nunito, bordes de 12px y sombras suaves.
//  Lo comparten header.php (panel) y login.php.
// ============================================================
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<script src="<?= URL_BASE ?>/app/public/vendor/tailwind-3.4.17.js"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    sol: {
                        oscuro:      '#0d1b2a',
                        medio:       '#1b263b',
                        claro:       '#415a77',
                        acento:      '#2563eb',
                        'acento-osc':'#1b4bc0',
                        suave:       '#eaf4ff',
                        celeste:     '#4db8ff',
                        hielo:       '#cfe0f5',
                        fondo:       '#f4f7fb',
                        borde:       '#dbe3ee',
                        texto:       '#1f2937',
                        gris:        '#5b6779'
                    }
                },
                fontFamily: {
                    sans: ['Nunito', 'Segoe UI', 'system-ui', 'sans-serif']
                },
                boxShadow: {
                    sol:        '0 4px 12px rgba(13, 27, 42, 0.08)',
                    'sol-alta': '0 12px 28px rgba(13, 27, 42, 0.16)'
                }
            }
        }
    }
</script>
<style>
    body {
        font-family: 'Nunito', 'Segoe UI', system-ui, sans-serif;
        background: #f4f7fb;
        color: #1f2937;
    }

    /* Degradado del encabezado de la página principal */
    .fondo-marca {
        background: linear-gradient(135deg, #0d1b2a 0%, #1b263b 60%, #415a77 100%);
    }

    /* Tarjeta blanca con borde y sombra suave */
    .tarjeta-sol {
        background: #ffffff;
        border: 1px solid #dbe3ee;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(13, 27, 42, 0.08);
    }

    /* Tarjeta oscura de cifras (como las estadísticas de la landing) */
    .cifra-sol {
        background: #0d1b2a;
        color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(13, 27, 42, 0.08);
    }

    /* Formularios */
    .etiqueta-sol {
        display: block;
        font-size: .875rem;
        font-weight: 700;
        color: #415a77;
        margin-bottom: .35rem;
    }
    .campo-sol {
        width: 100%;
        padding: .55rem 1rem;
        border: 2px solid #dbe3ee;
        border-radius: 10px;
        font-size: .875rem;
        background: #ffffff;
        color: #1f2937;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .campo-sol:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px #eaf4ff;
    }

    /* Botones */
    .btn-sol {
        display: inline-block;
        background: #2563eb;
        color: #ffffff;
        font-weight: 800;
        border-radius: 8px;
        transition: background .15s ease, transform .15s ease;
        cursor: pointer;
        text-align: center;
    }
    .btn-sol:hover, .btn-sol:focus-visible {
        background: #1b4bc0;
        transform: translateY(-1px);
    }
    .btn-sol-sec {
        display: inline-block;
        background: #eaf4ff;
        color: #1b263b;
        font-weight: 800;
        border-radius: 8px;
        transition: background .15s ease;
        text-align: center;
    }
    .btn-sol-sec:hover, .btn-sol-sec:focus-visible {
        background: #dbe3ee;
    }

    a:focus-visible, button:focus-visible {
        outline: 3px solid #4db8ff;
        outline-offset: 2px;
    }

    /* Telefono: sin zoom automatico del iPhone y botones comodos para el dedo */
    @media (max-width: 768px) {
        .campo-sol, select.campo-sol { font-size: 16px !important; min-height: 44px; }
        .btn-sol, .btn-sol-sec { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; }
    }

    @media (prefers-reduced-motion: reduce) {
        * { transition: none !important; }
    }
</style>
