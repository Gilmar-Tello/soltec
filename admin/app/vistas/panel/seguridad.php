<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <h2 class="text-2xl font-extrabold text-sol-oscuro">🔐 Seguridad de tu cuenta</h2>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['exito'])): ?>
        <div class="bg-green-50 border border-green-300 text-green-800 rounded-xl p-4 mb-5">
            ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
        </div>
        <?php unset($_SESSION['exito']); ?>
    <?php endif; ?>

    <?php if ($codigosNuevos): ?>
        <!-- Se muestran UNA sola vez -->
        <div class="bg-amber-50 border-2 border-amber-300 rounded-xl p-5 mb-5">
            <div class="font-extrabold text-amber-900 mb-1">🗝️ Códigos de recuperación</div>
            <p class="text-sm text-amber-900 mb-3">
                Guárdalos en un lugar seguro (gestor de contraseñas o impresos). Cada uno sirve <strong>una sola vez</strong>
                si pierdes el teléfono. <strong>No se vuelven a mostrar.</strong>
            </p>
            <div class="grid grid-cols-2 gap-2 font-mono text-lg font-bold text-sol-oscuro" id="lista-codigos">
                <?php foreach ($codigosNuevos as $c): ?>
                    <div class="bg-white border border-amber-200 rounded-lg px-3 py-2 text-center"><?= htmlspecialchars($c) ?></div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="copiar-codigos" class="btn-sol px-4 py-2 text-sm mt-4">📋 Copiar códigos</button>
        </div>
    <?php endif; ?>

    <?php if (!$migrado): ?>
        <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-5">
            Falta un paso en la base de datos: ejecuta <code>migracion_2fa_panel_2026-10-26.sql</code> en phpMyAdmin
            (base <code>u923763167_admin</code>) y recarga esta página.
        </div>

    <?php elseif ($activo): ?>
        <div class="tarjeta-sol p-6 mb-5">
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-block bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ Activa</span>
                <span class="font-extrabold text-sol-oscuro">Verificación en dos pasos</span>
            </div>
            <p class="text-sm text-sol-gris">
                Te quedan <strong><?= (int) $restantes ?></strong> código(s) de recuperación sin usar.
                <?php if ($restantes <= 2): ?>
                    <span class="text-red-600 font-bold">Genera códigos nuevos pronto.</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="tarjeta-sol p-6 mb-5">
            <div class="font-extrabold text-sol-oscuro mb-3">Generar códigos de recuperación nuevos</div>
            <form action="<?= URL_BASE ?>/?url=panel/regenerarCodigos" method="POST" class="space-y-3">
                <input type="password" name="contrasena" required autocomplete="current-password" placeholder="Tu contraseña" class="campo-sol">
                <input type="text" name="codigo" required autocomplete="one-time-code" placeholder="Código de la app (6 dígitos)" class="campo-sol font-mono">
                <button type="submit" class="btn-sol px-4 py-2 text-sm">Generar códigos nuevos</button>
            </form>
        </div>

        <div class="tarjeta-sol p-6 border border-red-200">
            <div class="font-extrabold text-red-700 mb-3">Desactivar la verificación en dos pasos</div>
            <form action="<?= URL_BASE ?>/?url=panel/desactivar2fa" method="POST" class="space-y-3"
                  onsubmit="return confirm('¿Desactivar el 2FA? Tu cuenta quedará protegida solo por la contraseña.')">
                <input type="password" name="contrasena" required autocomplete="current-password" placeholder="Tu contraseña" class="campo-sol">
                <input type="text" name="codigo" required autocomplete="one-time-code" placeholder="Código de la app o de recuperación" class="campo-sol font-mono">
                <button type="submit" class="px-4 py-2 text-sm font-bold rounded-lg bg-red-600 hover:bg-red-700 text-white">Desactivar 2FA</button>
            </form>
        </div>

    <?php else: ?>
        <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded-xl p-4 mb-5 text-sm">
            🛡️ Esta cuenta controla a <strong>todos tus clientes</strong>. Con el 2FA, una contraseña robada ya no basta para entrar.
        </div>

        <div class="tarjeta-sol p-6">
            <div class="font-extrabold text-sol-oscuro mb-4">Activar verificación en dos pasos</div>
            <ol class="list-decimal ml-5 space-y-3 text-sm text-sol-medio mb-5">
                <li>Instala una app de autenticación (Google Authenticator, Microsoft Authenticator, Authy, 1Password…).</li>
                <li>En la app elige <strong>“Introducir clave de configuración”</strong> (o “Ingresar clave manualmente”) y pon:
                    <div class="mt-2 bg-sol-suave border border-sol-borde rounded-lg p-3">
                        <div class="text-xs text-sol-gris">Cuenta: <?= htmlspecialchars($admin['usuario']) ?> · Tipo: basado en tiempo</div>
                        <div class="font-mono text-lg font-bold text-sol-oscuro break-all tracking-wider" id="clave-secreta"><?= htmlspecialchars(trim(chunk_split($secretoNuevo, 4, ' '))) ?></div>
                    </div>
                    <details class="mt-2 text-xs text-sol-gris">
                        <summary class="cursor-pointer">Enlace otpauth (1Password, Aegis y similares)</summary>
                        <div class="font-mono break-all mt-1"><?= htmlspecialchars($uri) ?></div>
                    </details>
                </li>
                <li>Escribe aquí tu contraseña y el código de 6 dígitos que muestra la app.</li>
            </ol>

            <form action="<?= URL_BASE ?>/?url=panel/activar2fa" method="POST" class="space-y-3">
                <input type="password" name="contrasena" required autocomplete="current-password" placeholder="Tu contraseña" class="campo-sol">
                <input type="text" name="codigo" required autocomplete="one-time-code" inputmode="numeric" maxlength="7" placeholder="Código de 6 dígitos" class="campo-sol font-mono">
                <button type="submit" class="btn-sol px-4 py-2 text-sm">Activar 2FA</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        var b = document.getElementById('copiar-codigos');
        if (!b) return;
        b.addEventListener('click', function () {
            var texto = Array.prototype.map.call(document.querySelectorAll('#lista-codigos div'), function (d) { return d.textContent.trim(); }).join('\n');
            if (navigator.clipboard) navigator.clipboard.writeText(texto).then(function () { b.textContent = '✅ Copiados'; });
        });
    })();
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
