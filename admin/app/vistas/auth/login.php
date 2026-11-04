<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1b2a">
    <title>Acceso — SOLTEC</title>
    <link rel="icon" href="https://soltec.click/img/logo.png" type="image/png">
    <?php include RAIZ . '/app/vistas/plantillas/tema.php'; ?>
</head>
<body class="min-h-screen fondo-marca flex items-center justify-center p-4">

    <div class="w-full max-w-md">

        <div class="text-center mb-8 text-white">
            <img src="https://soltec.click/img/logo.png" alt="Logo de SOLTEC" width="110" height="110"
                 class="w-28 h-auto mx-auto mb-3 drop-shadow-2xl">
            <h1 class="text-4xl font-black tracking-widest">SOLTEC</h1>
            <p class="text-sol-hielo mt-1">Panel maestro</p>
        </div>

        <div class="bg-white rounded-xl shadow-sol-alta p-8">

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-5 text-sm">
                    ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="<?= URL_BASE ?>/?url=auth/entrar" method="POST" class="space-y-4">
                <div>
                    <label class="etiqueta-sol">👤 Usuario</label>
                    <input type="text" name="usuario" required autocomplete="username"
                           class="campo-sol" style="padding:.75rem 1rem; font-size:1rem;">
                </div>
                <div>
                    <label class="etiqueta-sol" for="campo-contrasena">🔒 Contraseña</label>
                    <div class="relative">
                        <input type="password" name="contrasena" id="campo-contrasena" required autocomplete="current-password"
                               class="campo-sol" style="padding:.75rem 3rem .75rem 1rem; font-size:1rem;">
                        <button type="button" id="ver-contrasena"
                                class="absolute inset-y-0 right-0 px-3 flex items-center text-sol-claro hover:text-sol-acento"
                                aria-label="Mostrar contraseña" aria-pressed="false" title="Mostrar contraseña">
                            <!-- Ojo abierto: la contraseña está oculta, al tocar se muestra -->
                            <svg id="icono-ojo" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <!-- Ojo tachado: la contraseña está visible, al tocar se oculta -->
                            <svg id="icono-ojo-tachado" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="hidden">
                                <path d="M17.94 17.94A10.9 10.9 0 0 1 12 19c-6.5 0-10-7-10-7a18.4 18.4 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19"/>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                                <line x1="2" y1="2" x2="22" y2="22"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-sol w-full py-3 text-base">
                    Entrar
                </button>
            </form>
        </div>

        <p class="text-center text-sol-hielo text-sm mt-6 opacity-80">
            SOLTEC &copy; <?= date('Y') ?>
        </p>
    </div>

    <script>
        // Ojo para ver/ocultar la contraseña antes de entrar
        (function () {
            var campo  = document.getElementById('campo-contrasena');
            var boton  = document.getElementById('ver-contrasena');
            var abierto  = document.getElementById('icono-ojo');
            var tachado  = document.getElementById('icono-ojo-tachado');

            boton.addEventListener('click', function () {
                var mostrar = campo.type === 'password';
                campo.type = mostrar ? 'text' : 'password';
                abierto.classList.toggle('hidden', mostrar);
                tachado.classList.toggle('hidden', !mostrar);
                boton.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
                boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
                boton.title = mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña';
                campo.focus();
            });
        })();
    </script>
</body>
</html>
