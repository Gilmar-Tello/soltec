<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-xl mx-auto">
    <h2 class="text-2xl font-extrabold text-green-800 mb-6">⚙️ Configuración del Negocio</h2>

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

    <?php $esAdmin = $_SESSION['usuario_rol_id'] == 1; ?>
    <?php if (!$esAdmin): ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 rounded-xl p-4 mb-5 text-sm">
            👁️ Solo puedes ver esta información. Únicamente el administrador puede modificarla.
        </div>
    <?php endif; ?>

    <?php if (!empty($configSucursal)):
        // Se está viendo una SUCURSAL: dirección, teléfono, NIT y logo propios (vacío = usa los del negocio)
        $propio      = $configSucursal;
        $logoMostrar = !empty($propio['logo']) ? $propio['logo'] : ($config['logo'] ?? '');
        $valor = function ($campo) use ($esAdmin, $propio, $config) {
            return $esAdmin ? ($propio[$campo] ?? '') : (($propio[$campo] ?? '') !== '' && $propio[$campo] !== null ? $propio[$campo] : ($config[$campo] ?? ''));
        };
    ?>
    <div class="bg-white rounded-2xl shadow p-6">
        <div class="bg-green-100 text-green-800 font-bold rounded-xl px-4 py-2 mb-2 text-sm">🏬 Datos de la sucursal: <?= htmlspecialchars($propio['nombre']) ?></div>
        <p class="text-sm text-gray-500 mb-5">Lo que dejes vacío usa los datos del negocio (<strong><?= htmlspecialchars($config['nombre_negocio']) ?></strong>). El nombre del negocio se cambia desde la Principal.</p>
        <form method="POST" action="<?= URL_BASE ?>/?url=configuracion/guardar" enctype="multipart/form-data">
            <input type="hidden" name="sucursal_destino" value="<?= (int) $propio['id'] ?>">

            <!-- Logo: el propio de la sucursal o, si no tiene, el del negocio -->
            <div class="flex flex-col items-center mb-6">
                <div class="w-24 h-24 bg-green-50 rounded-2xl flex items-center justify-center overflow-hidden border-2 border-green-200 mb-3">
                    <?php if ($logoMostrar): ?>
                        <img src="<?= URL_BASE ?>/<?= htmlspecialchars($logoMostrar) ?>" alt="Logo" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="text-4xl">🏪</span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-400 mb-2"><?= !empty($propio['logo']) ? 'Logo propio de esta sucursal' : 'Usa el logo del negocio' ?></p>
                <?php if ($esAdmin): ?>
                <label class="cursor-pointer bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-4 py-2 rounded-xl text-xs transition">
                    📷 Cambiar logo de la sucursal
                    <input type="file" name="logo" accept="image/*" class="hidden">
                </label>
                <p class="text-xs text-gray-400 mt-1">JPG, PNG o WEBP — máx. 2MB</p>
                <?php if (!empty($propio['logo'])): ?>
                <label class="text-xs text-gray-600 mt-2 flex items-center gap-2">
                    <input type="checkbox" name="quitar_logo" value="1"> Quitar el logo propio (usar el del negocio)
                </label>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Dirección</label>
                    <input type="text" name="direccion" maxlength="255" <?= !$esAdmin ? 'disabled' : '' ?>
                           value="<?= htmlspecialchars($valor('direccion')) ?>"
                           placeholder="<?= htmlspecialchars(($config['direccion'] ?? '') !== '' ? 'Negocio: ' . $config['direccion'] : 'Zona, municipio, departamento') ?>"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm disabled:bg-gray-50 disabled:text-gray-400">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-700 text-gray-600 mb-1">Teléfono</label>
                        <input type="text" name="telefono" maxlength="50" <?= !$esAdmin ? 'disabled' : '' ?>
                               value="<?= htmlspecialchars($valor('telefono')) ?>"
                               placeholder="<?= htmlspecialchars(($config['telefono'] ?? '') !== '' ? 'Negocio: ' . $config['telefono'] : '00000000') ?>"
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm disabled:bg-gray-50 disabled:text-gray-400">
                    </div>
                    <div>
                        <label class="block text-sm font-700 text-gray-600 mb-1">NIT</label>
                        <input type="text" name="nit" maxlength="50" <?= !$esAdmin ? 'disabled' : '' ?>
                               value="<?= htmlspecialchars($valor('nit')) ?>"
                               placeholder="<?= htmlspecialchars(($config['nit'] ?? '') !== '' ? 'Negocio: ' . $config['nit'] : 'CF o número de NIT') ?>"
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm disabled:bg-gray-50 disabled:text-gray-400">
                    </div>
                </div>
            </div>

            <?php if ($esAdmin): ?>
            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl transition mt-6">
                💾 Guardar datos de la sucursal
            </button>
            <?php endif; ?>
        </form>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl shadow p-6">
        <form method="POST" action="<?= URL_BASE ?>/?url=configuracion/guardar" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $config['id'] ?>">

            <!-- Logo -->
            <div class="flex flex-col items-center mb-6">
                <div class="w-24 h-24 bg-green-50 rounded-2xl flex items-center justify-center overflow-hidden border-2 border-green-200 mb-3">
                    <?php if ($config['logo']): ?>
                        <img src="<?= URL_BASE ?>/<?= htmlspecialchars($config['logo']) ?>" alt="Logo" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="text-4xl">🏪</span>
                    <?php endif; ?>
                </div>
                <?php if ($esAdmin): ?>
                <label class="cursor-pointer bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-4 py-2 rounded-xl text-xs transition">
                    📷 Cambiar logo
                    <input type="file" name="logo" accept="image/*" class="hidden">
                </label>
                <p class="text-xs text-gray-400 mt-1">JPG, PNG o WEBP — máx. 2MB</p>
                <?php endif; ?>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Nombre del negocio <span class="text-red-500">*</span></label>
                    <input type="text" name="nombre_negocio" required <?= !$esAdmin?'disabled':'' ?>
                           value="<?= htmlspecialchars($config['nombre_negocio']) ?>"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm disabled:bg-gray-50 disabled:text-gray-400">
                </div>
                <div>
                    <label class="block text-sm font-700 text-gray-600 mb-1">Dirección</label>
                    <input type="text" name="direccion" <?= !$esAdmin?'disabled':'' ?>
                           value="<?= htmlspecialchars($config['direccion'] ?? '') ?>"
                           placeholder="Zona, municipio, departamento"
                           class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm disabled:bg-gray-50 disabled:text-gray-400">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-700 text-gray-600 mb-1">Teléfono</label>
                        <input type="text" name="telefono" <?= !$esAdmin?'disabled':'' ?>
                               value="<?= htmlspecialchars($config['telefono'] ?? '') ?>"
                               placeholder="00000000"
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm disabled:bg-gray-50 disabled:text-gray-400">
                    </div>
                    <div>
                        <label class="block text-sm font-700 text-gray-600 mb-1">NIT</label>
                        <input type="text" name="nit" <?= !$esAdmin?'disabled':'' ?>
                               value="<?= htmlspecialchars($config['nit'] ?? '') ?>"
                               placeholder="CF o número de NIT"
                               class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-sm disabled:bg-gray-50 disabled:text-gray-400">
                    </div>
                </div>
            </div>

            <?php if ($esAdmin): ?>
            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl transition mt-6">
                💾 Guardar Cambios
            </button>
            <?php endif; ?>
        </form>
    </div>

    <?php endif; ?>

    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 mt-5 text-sm text-blue-700">
        💡 Este nombre, dirección, teléfono y logo aparecerán en los tickets de venta y reportes PDF.
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>