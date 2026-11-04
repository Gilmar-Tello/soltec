<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar — Brix</title>
    <?php include RAIZ . '/app/vistas/plantillas/tema.php'; ?>
</head>
<body class="fondo-marca min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <div class="text-4xl font-black tracking-tight text-white">Brix</div>
            <div class="text-sm text-[#cfe0f5] mt-1">Control de ingresos, egresos e inventario</div>
        </div>

        <div class="bg-white rounded-xl shadow-2xl p-6">
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form method="POST" action="<?= URL_BASE ?>/?url=auth/entrar" class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1" for="usuario">Usuario</label>
                    <input type="text" name="usuario" id="usuario" autofocus required
                           autocomplete="username" autocapitalize="none" autocorrect="off"
                           class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1" for="campo-contrasena">Contraseña</label>
                    <div class="relative">
                        <input type="password" name="contrasena" id="campo-contrasena" required
                               autocomplete="current-password"
                               class="w-full rounded-md border border-slate-300 pl-3 pr-12 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
                        <button type="button" id="ver-contrasena"
                                class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-600 hover:text-indigo-600"
                                aria-label="Mostrar contraseña" aria-pressed="false" title="Mostrar contraseña">
                            <svg id="icono-ojo" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
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
                <button type="submit"
                        class="w-full rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-2.5 transition">
                    Ingresar
                </button>
            </form>
        </div>

        <div class="text-center text-xs text-[#cfe0f5] opacity-80 mt-6">SOLTEC · <?= date('Y') ?></div>
    </div>

    <script>
        (function () {
            var campo   = document.getElementById('campo-contrasena');
            var boton   = document.getElementById('ver-contrasena');
            var abierto = document.getElementById('icono-ojo');
            var tachado = document.getElementById('icono-ojo-tachado');
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
