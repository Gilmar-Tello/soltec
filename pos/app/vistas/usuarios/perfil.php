<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-md mx-auto">
    <h2 class="text-2xl font-extrabold text-green-800 mb-6">👤 Mi Perfil</h2>

    <?php if (isset($_SESSION['exito'])): ?>
        <div class="bg-green-50 border border-green-300 text-green-800 rounded-xl p-4 mb-5">
            ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
        </div>
        <?php unset($_SESSION['exito']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Info del usuario -->
    <div class="bg-white rounded-2xl shadow p-6 mb-5">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center text-3xl">
                👤
            </div>
            <div>
                <div class="font-extrabold text-gray-800 text-lg"><?= htmlspecialchars($usuario['nombre']) ?></div>
                <div class="text-gray-500 font-mono text-sm">@<?= htmlspecialchars($usuario['usuario']) ?></div>
                <div class="mt-1">
                    <span class="bg-purple-100 text-purple-700 text-xs font-bold px-2 py-1 rounded-full">
                        <?= htmlspecialchars($usuario['rol_nombre']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Cambiar contraseña -->
    <div class="bg-white rounded-2xl shadow p-6">
        <h3 class="font-bold text-gray-700 mb-4">🔒 Cambiar Contraseña</h3>
        <form method="POST" action="<?= URL_BASE ?>/?url=usuarios/perfil" class="space-y-4">
            <div>
                <label class="block text-sm font-700 text-gray-600 mb-1">Contraseña actual</label>
                <input type="password" name="contrasena_actual" required
                       class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
            </div>
            <div>
                <label class="block text-sm font-700 text-gray-600 mb-1">Nueva contraseña</label>
                <input type="password" name="contrasena_nueva" required minlength="8" maxlength="72"
                       placeholder="Mínimo 8 caracteres"
                       class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
            </div>
            <div>
                <label class="block text-sm font-700 text-gray-600 mb-1">Repetir nueva contraseña</label>
                <input type="password" name="repetir" required minlength="8" maxlength="72"
                       class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
            </div>
            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl transition">
                💾 Actualizar Contraseña
            </button>
        </form>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>