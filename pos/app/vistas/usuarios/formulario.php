<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=usuarios/index" class="text-green-700 font-bold text-xl">←</a>
        <h2 class="text-2xl font-extrabold text-green-800">
            <?= $usuario ? '✏️ Editar Usuario' : '➕ Nuevo Usuario' ?>
        </h2>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow p-6">
        <form method="POST"
              action="<?= URL_BASE ?>/?url=<?= $usuario ? 'usuarios/actualizar' : 'usuarios/guardar' ?>">
            <?php if ($usuario): ?>
                <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-700 text-gray-600 mb-1">Nombre completo <span class="text-red-500">*</span></label>
                    <input type="text" name="nombre" required
                           value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>"
                           placeholder="Nombre del usuario"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Usuario <span class="text-red-500">*</span></label>
                    <input type="text" name="usuario" required
                           value="<?= htmlspecialchars($usuario['usuario'] ?? '') ?>"
                           placeholder="Nombre de acceso"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">
                        Contraseña <?= $usuario ? '(dejar vacío para no cambiar)' : '<span class="text-red-500">*</span>' ?>
                    </label>
                    <input type="password" name="contrasena"
                           <?= !$usuario ? 'required' : '' ?>
                           placeholder="<?= $usuario ? 'Nueva contraseña (opcional)' : 'Mínimo 8 caracteres' ?>"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-700 text-gray-600 mb-2">Rol <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <?php
                        $rolesInfo = [
                            1 => ['icon'=>'👑','color'=>'purple','desc'=>'Acceso total al sistema'],
                            2 => ['icon'=>'🛍️','color'=>'blue',  'desc'=>'Solo POS y clientes'],
                            3 => ['icon'=>'📦','color'=>'yellow','desc'=>'Inventario y proveedores'],
                        ];
                        foreach ($roles as $r):
                            $info    = $rolesInfo[$r['id']] ?? ['icon'=>'👤','color'=>'gray','desc'=>''];
                            $checked = ($usuario['rol_id'] ?? '') == $r['id'] ? 'checked' : '';
                        ?>
                        <label class="flex flex-col items-center gap-2 p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-green-400 transition has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                            <input type="radio" name="rol_id" value="<?= $r['id'] ?>" <?= $checked ?> required class="hidden">
                            <span class="text-3xl"><?= $info['icon'] ?></span>
                            <span class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($r['nombre']) ?></span>
                            <span class="text-xs text-gray-400 text-center"><?= $info['desc'] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (sucursalesHabilitadas()): ?>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-700 text-gray-600 mb-1" for="campoSucursal">🏬 Sucursal donde trabaja</label>
                    <select name="sucursal_id" id="campoSucursal"
                            class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm">
                        <option value="0"><?= htmlspecialchars(NOMBRE_SUCURSAL_PRINCIPAL) ?></option>
                        <?php foreach (sucursalesDelNegocio() as $sid => $snombre): ?>
                            <option value="<?= (int) $sid ?>" <?= ((int) ($usuario['sucursal_id'] ?? 0)) === $sid ? 'selected' : '' ?>><?= htmlspecialchars($snombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Sus ventas y su caja cuentan para esa sucursal en los informes.</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 rounded-xl transition">
                    💾 <?= $usuario ? 'Actualizar' : 'Crear Usuario' ?>
                </button>
                <a href="<?= URL_BASE ?>/?url=usuarios/index"
                   class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-6 py-3 rounded-xl transition">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>