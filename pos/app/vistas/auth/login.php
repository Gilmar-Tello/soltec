<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — <?= htmlspecialchars($config['nombre_negocio'] ?? 'POS GT') ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏪</text></svg>">
    <?php include RAIZ . '/app/vistas/plantillas/tema.php'; ?>
    <style>
        body { font-family: 'Nunito', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-sol-fondo flex items-center justify-center p-4">

    <div class="w-full max-w-md">

        <!-- Logo / Título -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-600 rounded-2xl shadow-lg mb-4 overflow-hidden">
                <?php if (!empty($config['logo'])): ?>
                    <img src="<?= URL_BASE ?>/<?= htmlspecialchars($config['logo']) ?>" alt="Logo" class="w-full h-full object-cover">
                <?php else: ?>
                    <span class="text-4xl">🏪</span>
                <?php endif; ?>
            </div>
            <h1 class="text-3xl font-extrabold text-green-800"><?= htmlspecialchars($config['nombre_negocio'] ?? 'POS GT') ?></h1>
            <p class="text-green-600 mt-1">Sistema de Punto de Venta</p>
        </div>

        <!-- Tarjeta de login -->
        <div class="bg-white rounded-2xl shadow-lg p-8">

            <h2 class="text-xl font-bold text-gray-700 mb-6">Iniciar sesión</h2>

            <!-- Mensaje de error -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-5 flex items-center gap-3">
                    <span class="text-xl">⚠️</span>
                    <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Formulario -->
            <form action="<?= URL_BASE ?>/?url=auth/entrar" method="POST" class="space-y-5">

                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-2">
                        👤 Usuario
                    </label>
                    <input
                        type="text"
                        name="usuario"
                        placeholder="Ingresá tu usuario"
                        autocomplete="username"
                        required
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-green-500 focus:outline-none text-gray-700 text-base transition"
                    >
                </div>

                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-2">
                        🔒 Contraseña
                    </label>
                    <input
                        type="password"
                        name="contrasena"
                        placeholder="Ingresá tu contraseña"
                        autocomplete="current-password"
                        required
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-green-500 focus:outline-none text-gray-700 text-base transition"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-xl text-base transition shadow-md hover:shadow-lg mt-2"
                >
                    ✅ Entrar al sistema
                </button>

            </form>

        </div>

        <p class="text-center text-green-700 text-sm mt-6 opacity-70">
            <?= htmlspecialchars($config['nombre_negocio'] ?? 'POS GT') ?> &copy; <?= date('Y') ?>
        </p>

    </div>

    <script>
    // Al llegar al login (por ejemplo después de cerrar sesión) se borran las páginas
    // guardadas por el modo offline, para que nadie pueda verlas en ese equipo.
    // Las ventas offline pendientes NO se tocan: viven en IndexedDB, no aquí.
    if ('caches' in window) {
        caches.keys().then(nombres => nombres.forEach(n => caches.delete(n))).catch(() => {});
    }
    </script>

</body>
</html>