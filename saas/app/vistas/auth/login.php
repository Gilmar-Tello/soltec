<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — Alquileres SOLTEC</title>
    <?php include RAIZ . '/app/vistas/plantillas/tema.php'; ?>
    <style>
        body { font-family: 'Nunito', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center px-4">

<div class="w-full max-w-sm">
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-teal-600 text-white text-2xl mb-3">🪑</div>
        <h1 class="text-xl font-extrabold text-slate-800">Alquileres SOLTEC</h1>
        <p class="text-sm text-slate-500">Iniciá sesión para continuar</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-orange-50 border border-orange-200 text-orange-700 text-sm rounded-xl p-3 mb-4">
                ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form method="POST" action="<?= URL_BASE ?>/?url=auth/entrar" class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Correo electrónico</label>
                <input type="email" name="email" required autofocus
                       class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Contraseña</label>
                <input type="password" name="contrasena" required
                       class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
            </div>
            <button type="submit"
                    class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 rounded-xl transition">
                Iniciar sesión
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-slate-400 mt-6">SOLTEC © 2026</p>
</div>

</body>
</html>
