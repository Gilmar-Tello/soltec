<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1b2a">
    <title>Verificación — SOLTEC</title>
    <link rel="icon" href="https://soltec.click/img/logo.png" type="image/png">
    <?php include RAIZ . '/app/vistas/plantillas/tema.php'; ?>
</head>
<body class="min-h-screen fondo-marca flex items-center justify-center p-4">

    <div class="w-full max-w-md">

        <div class="text-center mb-8 text-white">
            <img src="https://soltec.click/img/logo.png" alt="Logo de SOLTEC" width="110" height="110"
                 class="w-28 h-auto mx-auto mb-3 drop-shadow-2xl">
            <h1 class="text-4xl font-black tracking-widest">SOLTEC</h1>
            <p class="text-sol-hielo mt-1">Verificación en dos pasos</p>
        </div>

        <div class="bg-white rounded-xl shadow-sol-alta p-8">

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-5 text-sm">
                    ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <p class="text-sm text-sol-gris mb-4">
                Abre tu app de autenticación y escribe el código de 6 dígitos de <strong>SOLTEC Panel</strong>.
                Si no tienes el teléfono, usa un código de recuperación.
            </p>

            <form action="<?= URL_BASE ?>/?url=auth/confirmar" method="POST" class="space-y-4">
                <div>
                    <label class="etiqueta-sol" for="codigo">🔐 Código</label>
                    <input type="text" name="codigo" id="codigo" required autofocus
                           autocomplete="one-time-code" inputmode="text" maxlength="14"
                           class="campo-sol text-center font-mono tracking-widest"
                           style="padding:.75rem 1rem; font-size:1.4rem;" placeholder="123456">
                </div>
                <button type="submit" class="btn-sol w-full py-3 text-base">Verificar</button>
            </form>

            <div class="text-center mt-5">
                <a href="<?= URL_BASE ?>/?url=auth/salir" class="text-sm text-sol-acento font-bold">← Cancelar</a>
            </div>
        </div>

        <p class="text-center text-sol-hielo text-sm mt-6 opacity-80">
            SOLTEC &copy; <?= date('Y') ?>
        </p>
    </div>
</body>
</html>
