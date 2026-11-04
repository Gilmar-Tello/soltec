<?php
$tituloPagina = 'Abrir Caja';
include RAIZ . '/app/vistas/plantillas/header.php';
?>

<div class="max-w-md mx-auto mt-10">
    <div class="text-center mb-8">
        <div class="text-6xl mb-4">🏧</div>
        <h2 class="text-2xl font-extrabold text-green-800">Abrir Caja</h2>
        <?php if (esAdminConSucursales()): ?>
            <p class="inline-block mt-2 bg-green-100 text-green-800 font-bold px-3 py-1 rounded-lg">🏬 Vas a abrir caja en: <?= htmlspecialchars(nombreSucursalTrabajo()) ?></p>
        <?php endif; ?>
        <p class="text-gray-500 mt-2">Ingresá el monto inicial para comenzar a vender.</p>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl p-4 mb-5">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow p-8">
        <form method="POST" action="<?= URL_BASE ?>/?url=pos/abrirCaja">
            <div class="mb-6">
                <label class="block text-sm font-700 text-gray-600 mb-2">
                    💵 Monto inicial en caja (Q)
                </label>
                <input type="number" name="monto_inicial" step="0.01" min="0" value="0"
                       class="w-full px-4 py-4 text-2xl text-center border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none font-bold">
            </div>
            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-extrabold py-4 rounded-xl text-lg transition">
                ✅ Abrir Caja y Comenzar
            </button>
        </form>
    </div>

    <!-- Accesos sin necesidad de abrir caja -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-5">
        <a href="<?= URL_BASE ?>/?url=pos/historial"
           class="bg-white hover:shadow-md rounded-2xl p-4 flex items-center gap-3 transition shadow border-2 border-transparent hover:border-green-300">
            <div class="text-3xl">📋</div>
            <div>
                <div class="font-bold text-gray-800 text-sm">
                    <?= $_SESSION['usuario_rol_id'] == 1 ? 'Historial de Ventas' : 'Mi Historial de Ventas' ?>
                </div>
                <div class="text-gray-400 text-xs">Consultar sin abrir caja</div>
            </div>
        </a>
        <a href="<?= URL_BASE ?>/?url=pos/historialCaja"
           class="bg-white hover:shadow-md rounded-2xl p-4 flex items-center gap-3 transition shadow border-2 border-transparent hover:border-green-300">
            <div class="text-3xl">🏧</div>
            <div>
                <div class="font-bold text-gray-800 text-sm">
                    <?= $_SESSION['usuario_rol_id'] == 1 ? 'Historial de Caja' : 'Mi Historial de Caja' ?>
                </div>
                <div class="text-gray-400 text-xs">Aperturas y cierres</div>
            </div>
        </a>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>