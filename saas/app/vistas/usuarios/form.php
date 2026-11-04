<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<?php
$esEdicion  = isset($usuario);
$claseInput = 'w-full px-4 py-2.5 border-2 border-slate-200 rounded-xl focus:border-teal-500 focus:outline-none text-base sm:text-sm';
?>

<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-3 mb-5">
        <a href="<?= URL_BASE ?>/?url=usuarios/index" class="text-teal-700 font-bold text-xl p-1">←</a>
        <h2 class="text-xl sm:text-2xl font-extrabold text-teal-800">
            <?= $esEdicion ? '✏️ Editar Usuario' : '➕ Nuevo Usuario' ?>
        </h2>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST"
          action="<?= URL_BASE ?>/?url=usuarios/<?= $esEdicion ? 'actualizar' : 'guardar' ?>"
          class="bg-white rounded-2xl shadow p-4 sm:p-6 space-y-4">

        <?php if ($esEdicion): ?>
            <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
        <?php endif; ?>

        <div>
            <label for="nombre" class="block text-sm font-bold text-slate-600 mb-1">Nombre completo <span class="text-red-500">*</span></label>
            <input type="text" name="nombre" id="nombre" required autocomplete="off"
                   value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>" class="<?= $claseInput ?>">
        </div>

        <div>
            <label for="email" class="block text-sm font-bold text-slate-600 mb-1">Correo electrónico <span class="text-red-500">*</span></label>
            <input type="email" name="email" id="email" required autocomplete="off" inputmode="email"
                   value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" class="<?= $claseInput ?>">
        </div>

        <div>
            <label for="password" class="block text-sm font-bold text-slate-600 mb-1">
                Contraseña <?= $esEdicion ? '<span class="text-slate-400 font-normal">(dejá en blanco para no cambiar)</span>' : '<span class="text-red-500">*</span>' ?>
            </label>
            <!-- Oculta por defecto: en un celular la pantalla la ve quien
                 esté al lado. El botón permite revisarla antes de guardar. -->
            <div class="relative">
                <input type="password" name="password" id="password" <?= !$esEdicion ? 'required' : '' ?>
                       autocomplete="new-password"
                       placeholder="<?= $esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña de acceso' ?>"
                       class="<?= $claseInput ?> pr-20">
                <button type="button" id="verPassword"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-xs font-bold text-teal-700 px-2 py-1.5 rounded-lg hover:bg-teal-50">
                    👁 ver
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="rol" class="block text-sm font-bold text-slate-600 mb-1">Rol</label>
                <select name="rol" id="rol" class="<?= $claseInput ?>">
                    <option value="trabajador" <?= ($usuario['rol'] ?? '') === 'trabajador' ? 'selected' : '' ?>>Trabajador</option>
                    <option value="admin_negocio" <?= ($usuario['rol'] ?? '') === 'admin_negocio' ? 'selected' : '' ?>>Admin Negocio</option>
                </select>
            </div>
            <div>
                <label for="sucursal_id" class="block text-sm font-bold text-slate-600 mb-1">Sucursal asignada</label>
                <select name="sucursal_id" id="sucursal_id" class="<?= $claseInput ?>">
                    <option value="">Todas las sucursales</option>
                    <?php foreach ($sucursales as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) ($usuario['sucursal_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="bg-slate-50 rounded-xl p-3 text-xs text-slate-500">
            <strong>Admin Negocio:</strong> acceso total al sistema.<br>
            <strong>Trabajador:</strong> puede crear reservas, clientes, registrar entregas y devoluciones. No accede a reportes, bajas ni configuración.
        </div>

        <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
            <a href="<?= URL_BASE ?>/?url=usuarios/index"
               class="text-center bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-6 py-3 rounded-xl transition">
                Cancelar
            </a>
            <button type="submit"
                    class="bg-teal-600 hover:bg-teal-700 text-white font-bold px-6 py-3 rounded-xl transition">
                💾 <?= $esEdicion ? 'Guardar cambios' : 'Crear usuario' ?>
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('verPassword').addEventListener('click', function () {
    const campo = document.getElementById('password');
    const ocultar = campo.type === 'text';
    campo.type = ocultar ? 'password' : 'text';
    this.textContent = ocultar ? '👁 ver' : '🙈 ocultar';
});
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
