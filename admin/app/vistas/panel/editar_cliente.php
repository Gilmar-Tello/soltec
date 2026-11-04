<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=panel/clientes" class="text-sol-acento font-bold text-xl" aria-label="Volver">←</a>
        <h2 class="text-2xl font-extrabold text-sol-oscuro">✏️ Editar Cliente</h2>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="bg-sol-suave border border-sol-borde rounded-xl p-4 mb-5 text-sm text-sol-medio">
        🔗 Slug: <span class="font-mono font-bold"><?= htmlspecialchars($cliente['slug']) ?></span>
        (no se puede modificar)
    </div>

    <form method="POST" action="<?= URL_BASE ?>/?url=panel/actualizarCliente">
        <input type="hidden" name="id" value="<?= $cliente['id'] ?>">

        <div class="tarjeta-sol p-6">
            <div class="space-y-4">
                <div>
                    <label class="etiqueta-sol">Nombre del negocio <span class="text-red-500">*</span></label>
                    <input type="text" name="nombre_negocio" required
                           value="<?= htmlspecialchars($cliente['nombre_negocio']) ?>"
                           class="campo-sol">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="etiqueta-sol">Teléfono</label>
                        <input type="text" name="telefono" value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>"
                               class="campo-sol">
                    </div>
                    <div>
                        <label class="etiqueta-sol">Email de contacto</label>
                        <input type="email" name="email_contacto" value="<?= htmlspecialchars($cliente['email_contacto'] ?? '') ?>"
                               class="campo-sol">
                    </div>
                </div>
                <div>
                    <label class="etiqueta-sol">Dirección</label>
                    <input type="text" name="direccion" value="<?= htmlspecialchars($cliente['direccion'] ?? '') ?>"
                           class="campo-sol">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="etiqueta-sol">NIT</label>
                        <input type="text" name="nit" value="<?= htmlspecialchars($cliente['nit'] ?? 'CF') ?>"
                               class="campo-sol">
                    </div>
                    <div>
                        <label class="etiqueta-sol">Plan</label>
                        <select name="plan" class="campo-sol">
                            <?php foreach (['basico'=>'Básico','estandar'=>'Medio','premium'=>'Premium'] as $val=>$lbl): ?>
                                <option value="<?= $val ?>" <?= $cliente['plan']===$val?'selected':'' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

<?php if (array_key_exists('fecha_pago', $cliente)): ?>
        <div class="tarjeta-sol p-6 mt-5">
            <label class="etiqueta-sol">📅 Fecha de pago</label>
            <input type="date" name="fecha_pago" min="2024-01-01" max="2100-12-31"
                   value="<?= htmlspecialchars($cliente['fecha_pago'] ?? '') ?>"
                   class="campo-sol sm:max-w-xs">
            <p class="text-xs text-sol-medio mt-2">
                Desde 3 días antes, cada usuario ve un aviso al iniciar sesión. Pasada esta fecha (vale hasta las 23:59),
                el negocio ya no puede entrar. Déjala vacía para no avisar ni bloquear.
            </p>
        </div>
<?php endif; ?>

        <div class="flex gap-3 mt-5">
            <button type="submit" class="btn-sol px-6 py-3">
                💾 Guardar Cambios
            </button>
            <a href="<?= URL_BASE ?>/?url=panel/clientes" class="btn-sol-sec px-6 py-3">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
