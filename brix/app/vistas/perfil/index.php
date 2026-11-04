<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-900">Mi contraseña</h2>
    <p class="text-sm text-slate-500">
        Cambia la contraseña de <strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? '') ?></strong>.
        Si te la entregó soporte, cámbiala por una que solo tú conozcas.
    </p>
</div>

<form method="POST" action="<?= URL_BASE ?>/?url=perfil/guardar" class="bg-white rounded-lg border border-slate-200 p-6 max-w-md">
    <div class="space-y-4">
        <div>
            <label class="block text-xs text-slate-500 mb-1" for="contrasena_actual">Contraseña actual *</label>
            <input type="password" name="contrasena_actual" id="contrasena_actual" required
                   autocomplete="current-password"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1" for="contrasena_nueva">Contraseña nueva *</label>
            <input type="password" name="contrasena_nueva" id="contrasena_nueva" required minlength="8" maxlength="72"
                   autocomplete="new-password"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <p class="text-xs text-slate-400 mt-1">Mínimo 8 caracteres.</p>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1" for="repetir">Repite la contraseña nueva *</label>
            <input type="password" name="repetir" id="repetir" required minlength="8" maxlength="72"
                   autocomplete="new-password"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
            <input type="checkbox" id="ver-claves" class="rounded border-slate-300">
            Mostrar lo que escribo
        </label>
    </div>

    <div class="mt-6">
        <button type="submit" class="rounded-md bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold px-5 py-2.5">
            Cambiar contraseña
        </button>
    </div>
</form>

<script>
    document.getElementById('ver-claves').addEventListener('change', function () {
        var tipo = this.checked ? 'text' : 'password';
        ['contrasena_actual', 'contrasena_nueva', 'repetir'].forEach(function (id) {
            document.getElementById(id).type = tipo;
        });
    });
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
