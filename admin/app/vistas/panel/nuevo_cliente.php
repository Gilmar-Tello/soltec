<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= URL_BASE ?>/?url=panel/clientes" class="text-sol-acento font-bold text-xl" aria-label="Volver">←</a>
        <h2 class="text-2xl font-extrabold text-sol-oscuro">➕ Nuevo Cliente</h2>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST" action="<?= URL_BASE ?>/?url=panel/guardarCliente">

        <div class="tarjeta-sol p-6 mb-5">
            <h3 class="font-extrabold text-sol-oscuro mb-4">🏢 Datos del negocio</h3>
            <div class="space-y-4">
                <div>
                    <label class="etiqueta-sol">Nombre del negocio <span class="text-red-500">*</span></label>
                    <input type="text" name="nombre_negocio" required
                           placeholder="Ej: Tienda Don Pedro"
                           class="campo-sol">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="etiqueta-sol">Teléfono</label>
                        <input type="text" name="telefono" placeholder="00000000" class="campo-sol">
                    </div>
                    <div>
                        <label class="etiqueta-sol">Email de contacto</label>
                        <input type="email" name="email_contacto" placeholder="correo@ejemplo.com" class="campo-sol">
                    </div>
                </div>
                <div>
                    <label class="etiqueta-sol">Dirección</label>
                    <input type="text" name="direccion" placeholder="Zona, municipio, departamento" class="campo-sol">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="etiqueta-sol">NIT</label>
                        <input type="text" name="nit" value="CF" class="campo-sol">
                    </div>
                    <div>
                        <label class="etiqueta-sol">Plan</label>
                        <select name="plan" class="campo-sol">
                            <option value="basico">Básico</option>
                            <option value="estandar">Medio</option>
                            <option value="premium">Premium</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="tarjeta-sol p-6 mb-5">
            <h3 class="font-extrabold text-sol-oscuro mb-4">👤 Usuario administrador inicial</h3>
            <p class="text-xs text-sol-gris mb-4">Este será el primer usuario con acceso total al sistema de este negocio.</p>
            <?php $esAlquileres = sistemaActivo() === 'alquileres'; ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="etiqueta-sol">
                        <?= $esAlquileres ? 'Correo del administrador' : 'Usuario' ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="<?= $esAlquileres ? 'email' : 'text' ?>" name="usuario_admin" required
                           placeholder="<?= $esAlquileres ? 'admin@negocio.com' : 'admin' ?>"
                           class="campo-sol">
                    <?php if ($esAlquileres): ?>
                        <p class="text-xs text-sol-gris mt-1">Debe ser un correo válido — con eso inicia sesión el negocio.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="etiqueta-sol">Contraseña <span class="text-red-500">*</span></label>
                    <input type="text" name="contrasena" required placeholder="Mínimo 8 caracteres" class="campo-sol">
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-sol px-6 py-3">
                💾 Crear Cliente
            </button>
            <a href="<?= URL_BASE ?>/?url=panel/clientes" class="btn-sol-sec px-6 py-3">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
