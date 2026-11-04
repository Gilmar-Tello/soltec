<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>
<?php $etiquetaAcceso = sistemaActivo() === 'alquileres' ? 'Correo' : 'Usuario'; // Alquileres entra con correo ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=panel/clientes" class="text-sol-acento font-bold text-xl" aria-label="Volver">←</a>
        <h2 class="text-2xl font-extrabold text-sol-oscuro">🔑 Acceso de usuarios</h2>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Datos del cliente: sirven para verificar quién pide el cambio -->
    <div class="tarjeta-sol p-5 mb-5">
        <div class="text-xs text-sol-gris font-bold uppercase tracking-wide"><?= htmlspecialchars($nombreSistema) ?></div>
        <div class="text-xl font-extrabold text-sol-oscuro"><?= htmlspecialchars($cliente['nombre_negocio']) ?></div>
        <div class="text-sm text-sol-gris mt-1">
            📞 <?= htmlspecialchars($cliente['telefono'] ?: 'sin teléfono registrado') ?>
            <?php if (!empty($cliente['email_contacto'])): ?>
                &nbsp;·&nbsp; ✉️ <?= htmlspecialchars($cliente['email_contacto']) ?>
            <?php endif; ?>
        </div>
        <?php if (!$cliente['activo']): ?>
            <div class="mt-2 inline-block bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full">🚫 Negocio suspendido</div>
        <?php endif; ?>
    </div>

    <?php if ($claveNueva): ?>
        <!-- Se muestra UNA sola vez -->
        <div class="bg-green-50 border-2 border-green-300 rounded-xl p-5 mb-5">
            <div class="font-extrabold text-green-800 mb-3">✅ Contraseña restablecida</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div>
                    <div class="text-xs text-green-700 font-bold"><?= $etiquetaAcceso ?></div>
                    <div class="font-mono text-lg font-bold text-sol-oscuro break-all"><?= htmlspecialchars($claveNueva['acceso']) ?></div>
                </div>
                <div>
                    <div class="text-xs text-green-700 font-bold">Contraseña nueva</div>
                    <div class="font-mono text-lg font-bold text-sol-oscuro break-all" id="clave-nueva"><?= htmlspecialchars($claveNueva['clave']) ?></div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 mt-4">
                <button type="button" id="copiar-clave" class="btn-sol px-4 py-2 text-sm">📋 Copiar contraseña</button>
                <span class="text-xs text-green-800">Se muestra una sola vez. Entrégala al dueño por un medio seguro.</span>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-sol-suave border border-sol-borde rounded-xl p-4 mb-5 text-sm text-sol-medio">
        🛡️ <strong>Antes de restablecer:</strong> confirma que quien lo pide es de verdad el dueño
        (por ejemplo, llamando al teléfono registrado). Quedará guardado quién lo hizo y cuándo.
    </div>

    <?php if (empty($admins)): ?>
        <div class="tarjeta-sol p-6 text-center text-sol-gris">
            Este negocio no tiene usuarios registrados.
        </div>
    <?php else: ?>
        <?php foreach ($admins as $a): ?>
        <div class="tarjeta-sol p-5 mb-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <div>
                    <div class="font-extrabold text-sol-oscuro flex flex-wrap items-center gap-2">
                        <?= htmlspecialchars($a['nombre']) ?>
                        <?php if (!empty($a['rol_nombre'])): ?>
                            <span class="bg-sol-suave text-sol-acento text-[11px] font-bold px-2 py-0.5 rounded-full capitalize"><?= htmlspecialchars($a['rol_nombre']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm text-sol-gris"><?= $etiquetaAcceso ?>: <span class="font-mono font-bold text-sol-texto"><?= htmlspecialchars($a['acceso']) ?></span></div>
                </div>
                <?php if ($a['activo']): ?>
                    <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">✅ Activo</span>
                <?php else: ?>
                    <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full">🚫 Desactivado</span>
                <?php endif; ?>
            </div>

            <form method="POST" action="<?= URL_BASE ?>/?url=panel/restablecerAdmin"
                  onsubmit="return confirm('¿Restablecer la contraseña de este usuario? La anterior dejará de funcionar.')">
                <input type="hidden" name="tenant_id"  value="<?= (int) $cliente['id'] ?>">
                <input type="hidden" name="usuario_id" value="<?= (int) $a['id'] ?>">
                <input type="hidden" name="sistema"    value="<?= htmlspecialchars(sistemaActivo()) ?>">

                <label class="etiqueta-sol">Contraseña nueva</label>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="text" name="contrasena" autocomplete="off"
                           placeholder="Déjalo vacío para generar una segura"
                           class="campo-sol flex-1">
                    <button type="submit" class="btn-sol px-5 py-2 text-sm">🔑 Restablecer</button>
                </div>
                <p class="text-xs text-sol-gris mt-2">Si la escribes tú, mínimo 8 caracteres (sin claves comunes).</p>
                <?php if (!$a['activo']): ?>
                    <p class="text-xs text-red-600 mt-2">Este usuario está desactivado: aunque cambies la contraseña, no podrá entrar hasta que el administrador del negocio lo active.</p>
                <?php endif; ?>
            </form>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($claveNueva): ?>
<script>
    document.getElementById('copiar-clave').addEventListener('click', function () {
        var texto = document.getElementById('clave-nueva').textContent.trim();
        var boton = this;
        function listo() { boton.textContent = '✅ Copiada'; }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(texto).then(listo);
        } else {
            var t = document.createElement('textarea');
            t.value = texto; document.body.appendChild(t); t.select();
            document.execCommand('copy'); document.body.removeChild(t); listo();
        }
    });
</script>
<?php endif; ?>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
