<?php
// Fragmento HTML (sin encabezado ni pie): se carga dentro del cuadro "Funciones avanzadas".
// Variables: $planActual, $planes, $usuariosActivos, $limiteUsuarios, $sucursalesActivas, $items, $enlacesPlan
?>

<!-- Plan actual -->
<div class="rounded-xl border-2 border-green-300 bg-green-50 p-4 mb-5">
    <div class="text-xs font-bold text-green-700 uppercase tracking-wide">Tu plan actual</div>
    <div class="flex flex-wrap items-baseline gap-x-3">
        <span class="text-2xl font-extrabold text-green-800"><?= htmlspecialchars($planes[$planActual]['nombre']) ?></span>
        <span class="text-sm text-green-700">Q<?= (int) $planes[$planActual]['precio'] ?> al mes</span>
    </div>
    <div class="text-sm text-gray-600 mt-1">
        Usuarios: <strong><?= (int) $usuariosActivos ?></strong> de
        <strong><?= $limiteUsuarios === null ? 'ilimitados' : (int) $limiteUsuarios ?></strong>
        <?php if ($sucursalesActivas): ?>
            &nbsp;·&nbsp; Sucursales adicionales: <strong><?= (int) $sucursalesActivas ?></strong>
        <?php endif; ?>
    </div>
</div>

<!-- Planes -->
<h4 class="font-extrabold text-gray-800 mb-2">Planes</h4>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
<?php foreach ($planes as $clave => $p):
    $esActual = ($clave === $planActual); ?>
    <div class="rounded-xl border-2 p-4 flex flex-col <?= $esActual ? 'border-green-400 bg-green-50' : 'border-gray-200' ?>">
        <div class="font-extrabold text-gray-800"><?= htmlspecialchars($p['nombre']) ?>
            <?php if ($esActual): ?><span class="ml-1 bg-green-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full align-middle">Tu plan</span><?php endif; ?>
        </div>
        <div class="text-2xl font-extrabold text-green-700 my-1">Q<?= (int) $p['precio'] ?><span class="text-xs font-semibold text-gray-500"> /mes</span></div>
        <ul class="text-xs text-gray-600 space-y-1 mb-3 flex-1">
            <?php foreach (PLAN_ETIQUETAS_MODULOS as $mod => $etq):
                $incluye = in_array($mod, $p['modulos'], true); ?>
                <li class="<?= $incluye ? '' : 'text-gray-300 line-through' ?>"><?= $incluye ? '✅' : '➖' ?> <?= htmlspecialchars($etq) ?></li>
            <?php endforeach; ?>
            <li>👥 <?= $p['usuarios'] === null ? 'Usuarios ilimitados' : ((int) $p['usuarios'] . ' usuario' . ((int) $p['usuarios'] === 1 ? '' : 's')) ?></li>
        </ul>
        <?php if (!$esActual): ?>
            <a href="<?= htmlspecialchars($enlacesPlan[$clave]) ?>" target="_blank" rel="noopener noreferrer"
               class="text-center bg-green-600 hover:bg-green-700 text-white text-xs font-bold px-3 py-2 rounded-lg transition">
                💬 Quiero este plan
            </a>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<!-- Funciones extra -->
<h4 class="font-extrabold text-gray-800 mb-1">Funciones extra</h4>
<p class="text-sm text-gray-500 mb-3">Se agregan a <strong>cualquier plan</strong>. Las habilita nuestro equipo: escríbenos y te las activamos.</p>

<div class="space-y-3">
<?php foreach ($items as $it): ?>
    <div class="border-2 <?= $it['activa'] ? 'border-emerald-300 bg-emerald-50' : 'border-gray-200' ?> rounded-xl p-4">
        <div class="flex flex-wrap items-center gap-2 mb-1">
            <span class="text-xl"><?= htmlspecialchars($it['icono']) ?></span>
            <span class="font-extrabold text-gray-800"><?= htmlspecialchars($it['nombre']) ?></span>
            <?php if ($it['activa']): ?>
                <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-0.5 rounded-full">✅ Activa en tu negocio</span>
            <?php endif; ?>
            <span class="ml-auto text-sm font-extrabold text-amber-700">💰 <?= htmlspecialchars($it['precio']) ?></span>
        </div>
        <p class="text-sm text-gray-600"><?= htmlspecialchars($it['descripcion']) ?></p>
        <?php if (!$it['activa']): ?>
            <a href="<?= htmlspecialchars($it['enlace']) ?>" target="_blank" rel="noopener noreferrer"
               class="inline-block mt-3 bg-green-600 hover:bg-green-700 text-white text-sm font-bold px-4 py-2 rounded-lg transition">
                💬 Pedir información por WhatsApp
            </a>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<p class="text-xs text-gray-400 mt-4">
    ¿Dudas? Escríbenos al <?= htmlspecialchars(WHATSAPP_SOLTEC_VISIBLE) ?>.
</p>
