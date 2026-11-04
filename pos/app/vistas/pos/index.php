<?php
$tituloPagina = 'Punto de Venta';
include RAIZ . '/app/vistas/plantillas/header.php';
?>

<div class="mb-6">
    <h2 class="text-2xl font-extrabold text-green-800">👋 Bienvenido, <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></h2>
    <p class="text-gray-500 mt-1"><?= esAdminConSucursales() ? '🏬 ' . htmlspecialchars(nombreSucursalTrabajo()) . ' · ' : '' ?>Caja abierta desde <?= date('H:i', strtotime($caja['abierta_en'])) ?> · Monto inicial: Q<?= number_format($caja['monto_inicial'], 2) ?></p>
</div>

<?php if (isset($_SESSION['exito'])): ?>
    <div class="bg-green-50 border border-green-300 text-green-800 rounded-xl p-4 mb-5">
        ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
    </div>
    <?php unset($_SESSION['exito']); ?>
<?php endif; ?>

<!-- Acciones rápidas -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
    <a href="<?= URL_BASE ?>/?url=pos/venta"
       class="bg-green-600 hover:bg-green-700 text-white rounded-2xl p-6 flex items-center gap-4 transition shadow-md">
        <div class="text-5xl">🛍️</div>
        <div>
            <div class="font-extrabold text-xl">Nueva Venta</div>
            <div class="text-green-200 text-sm">Registrar una venta</div>
        </div>
    </a>
    <a href="<?= URL_BASE ?>/?url=pos/historial"
       class="bg-white hover:shadow-md rounded-2xl p-6 flex items-center gap-4 transition shadow border-2 border-transparent hover:border-green-300">
        <div class="text-5xl">📋</div>
        <div>
            <div class="font-bold text-gray-800 text-lg">
                <?= $_SESSION['usuario_rol_id'] == 1 ? 'Historial' : 'Mi Historial' ?>
            </div>
            <div class="text-gray-500 text-sm">Ver ventas del día</div>
        </div>
    </a>
    <a href="<?= URL_BASE ?>/?url=pos/historialCaja"
       class="bg-white hover:shadow-md rounded-2xl p-6 flex items-center gap-4 transition shadow border-2 border-transparent hover:border-green-300">
        <div class="text-5xl">🏧</div>
        <div>
            <div class="font-bold text-gray-800 text-lg">
                <?= $_SESSION['usuario_rol_id'] == 1 ? 'Historial de Caja' : 'Mi Historial de Caja' ?>
            </div>
            <div class="text-gray-500 text-sm">Aperturas y cierres</div>
        </div>
    </a>
    <a href="<?= URL_BASE ?>/?url=pos/cerrarCaja"
       class="bg-white hover:shadow-md rounded-2xl p-6 flex items-center gap-4 transition shadow border-2 border-transparent hover:border-red-300">
        <div class="text-5xl">🔒</div>
        <div>
            <div class="font-bold text-gray-800 text-lg">Cerrar Caja</div>
            <div class="text-gray-500 text-sm">Finalizar turno</div>
        </div>
    </a>
</div>

<!-- Ventas rápidas del día -->
<?php
$stmt = obtenerConexion()->prepare(
    "SELECT COUNT(*) AS total_ventas, COALESCE(SUM(total),0) AS total_monto
     FROM ventas WHERE caja_sesion_id=:id AND anulada=0");
$stmt->execute([':id' => $caja['id']]);
$resumen = $stmt->fetch();
?>
<div class="grid grid-cols-2 gap-4">
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-3xl font-extrabold text-green-700"><?= $resumen['total_ventas'] ?></div>
        <div class="text-gray-500 text-sm mt-1">Ventas en este turno</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5 text-center">
        <div class="text-3xl font-extrabold text-green-700">Q<?= number_format($resumen['total_monto'], 2) ?></div>
        <div class="text-gray-500 text-sm mt-1">Total vendido</div>
    </div>
</div>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>