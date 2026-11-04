<?php include RAIZ . '/app/vistas/plantillas/header.php'; ?>

<?php
$coloresEstado = [
    'reservado' => 'bg-amber-100 text-amber-700',
    'entregado' => 'bg-teal-100 text-teal-700',
    'devuelto'  => 'bg-emerald-100 text-emerald-700',
    'cancelado' => 'bg-slate-200 text-slate-600',
];

// Para el repartidor: a dónde va, cómo llamar y cómo llegar
$dirEntrega  = direccionEntrega($reserva, '');
$telDigitos  = telefonoWhatsapp($reserva['telefono'] ?? '');
$urlLlamar   = $telDigitos !== '' ? 'tel:+' . $telDigitos : '';
$urlMapa     = $dirEntrega !== ''
    ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($dirEntrega)
    : '';

// Cronograma: cada fecha con su hora. Las reservas viejas no tienen
// hora ni fecha de entrega y se muestran como siempre (solo el día).
$fechaEntregaR = !empty($reserva['fecha_entrega']) ? $reserva['fecha_entrega'] : $reserva['fecha_uso'];
$conHoraR = function (?string $fecha, ?string $hora): string {
    $t = fechaCorta($fecha);
    if (!empty($hora)) $t .= ' · ' . horaCorta($hora);
    return $t;
};

// El total ya incluye el costo inicial: el subtotal de productos
// es total + descuento - costo inicial.
$costoIni    = (float) ($reserva['costo_inicial_monto'] ?? 0);
$costoIniTxt = trim((string) ($reserva['costo_inicial_desc'] ?? '')) ?: 'Costo inicial';
$descR       = (float) ($reserva['descuento'] ?? 0);
$subProd     = (float) $reserva['total'] + $descR - $costoIni;
?>

<div class="flex flex-wrap items-center gap-x-3 gap-y-2 mb-5">
    <a href="<?= URL_BASE ?>/?url=reservas/index" class="text-teal-700 font-bold text-xl p-1">←</a>
    <h2 class="text-xl sm:text-2xl font-extrabold text-teal-800">Reserva #<?= (int) $reserva['id'] ?></h2>
    <span class="<?= $coloresEstado[$reserva['estado']] ?? '' ?> text-xs font-bold px-3 py-1 rounded-full capitalize">
        <?= htmlspecialchars($reserva['estado']) ?>
    </span>
</div>

<?php if (isset($_SESSION['exito'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl p-4 mb-5">
        ✅ <?= htmlspecialchars($_SESSION['exito']) ?>
    </div>
    <?php unset($_SESSION['exito']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="bg-orange-50 border border-orange-200 text-orange-700 rounded-xl p-4 mb-5">
        ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- ── REPARTO (solo celular/tablet) ─────────────────────────
     Lo primero que ve el repartidor: a dónde ir, cómo contactar
     y la acción que le toca. En PC ya está todo a la vista. -->
<?php if (in_array($reserva['estado'], ['reservado', 'entregado'], true)): ?>
<div class="lg:hidden bg-white rounded-2xl shadow p-4 mb-5 border-2 border-teal-100">
    <div class="text-xs text-slate-400 font-bold mb-1">
        <?= $reserva['estado'] === 'reservado' ? '📦 ENTREGAR EN' : '🔄 RECOGER EN' ?>
    </div>
    <div class="font-extrabold text-slate-800"><?= htmlspecialchars($dirEntrega ?: 'Dirección por coordinar') ?></div>
    <div class="text-sm text-slate-500 mt-1">
        <?= htmlspecialchars($reserva['nombre_completo']) ?>
        · <?= $reserva['estado'] === 'reservado'
                ? 'entrega el ' . $conHoraR($fechaEntregaR, $reserva['hora_entrega'] ?? null)
                : 'recoger el ' . $conHoraR($reserva['fecha_recoleccion_programada'], $reserva['hora_recoleccion'] ?? null) ?>
    </div>

    <div class="grid grid-cols-2 gap-2 mt-3">
        <?php if ($urlLlamar): ?>
            <a href="<?= htmlspecialchars($urlLlamar) ?>"
               class="text-center bg-sky-500 active:bg-sky-600 text-white font-bold py-3 rounded-xl text-sm">📞 Llamar</a>
        <?php endif; ?>
        <?php if ($urlMapa): ?>
            <a href="<?= htmlspecialchars($urlMapa) ?>" target="_blank" rel="noopener"
               class="text-center bg-slate-700 active:bg-slate-800 text-white font-bold py-3 rounded-xl text-sm">🗺️ Abrir en Maps</a>
        <?php endif; ?>
    </div>

    <?php if ($reserva['estado'] === 'reservado'): ?>
        <button type="button" onclick="abrirPanelEntrega()"
                class="w-full mt-2 bg-teal-600 active:bg-teal-700 text-white font-extrabold py-3.5 rounded-xl text-base">
            📦 Registrar entrega
        </button>
    <?php else: ?>
        <a href="<?= URL_BASE ?>/?url=reservas/devolver/<?= (int) $reserva['id'] ?>"
           class="block text-center w-full mt-2 bg-emerald-600 active:bg-emerald-700 text-white font-extrabold py-3.5 rounded-xl text-base">
            🔄 Registrar devolución
        </a>
    <?php endif; ?>
    <a href="#productos" class="block text-center text-xs text-teal-700 font-bold mt-3 py-1">Ver qué productos llevar ↓</a>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 lg:gap-6">

    <!-- Info cliente y fechas -->
    <div class="space-y-5 lg:col-span-1">
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-600 mb-3 text-sm">👤 Cliente</h3>
            <div class="font-extrabold text-slate-800"><?= htmlspecialchars($reserva['nombre_completo']) ?></div>
            <div class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($reserva['telefono']) ?></div>
            <div class="text-xs text-slate-400 mt-2">Dirección del cliente</div>
            <div class="text-sm text-slate-500"><?= htmlspecialchars($reserva['direccion'] ?: '—') ?></div>
        </div>

        <!-- A donde va ESTA entrega -->
        <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="font-bold text-slate-600 mb-2 text-sm">📍 Entregar en</h3>
            <div class="text-sm text-slate-700 font-bold"><?= htmlspecialchars(direccionEntrega($reserva, 'Por coordinar')) ?></div>
            <?php
                $dirReserva = trim((string) ($reserva['direccion_entrega'] ?? ''));
                $dirCliente = trim((string) ($reserva['direccion'] ?? ''));
            ?>
            <?php if ($dirReserva !== '' && $dirCliente !== '' && $dirReserva !== $dirCliente): ?>
                <div class="text-[11px] text-amber-600 mt-1 font-bold">Distinta a la dirección del cliente</div>
            <?php endif; ?>
            <?php if ($urlMapa): ?>
                <a href="<?= htmlspecialchars($urlMapa) ?>" target="_blank" rel="noopener"
                   class="inline-block text-xs text-teal-700 font-bold mt-2 hover:underline">🗺️ Ver en Google Maps</a>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-2xl shadow p-5 space-y-3 text-sm">
            <h3 class="font-bold text-slate-600 mb-1">📋 Datos</h3>
            <div class="flex justify-between gap-3"><span class="text-slate-500">Sucursal</span><span class="font-bold text-right"><?= htmlspecialchars($reserva['sucursal_nombre']) ?></span></div>
            <div class="flex justify-between gap-3"><span class="text-slate-500">Fecha reserva</span><span class="text-right"><?= fechaHora($reserva['fecha_reservacion']) ?></span></div>
            <div class="flex justify-between gap-3"><span class="text-slate-500">Entrega</span><span class="font-bold text-right"><?= $conHoraR($fechaEntregaR, $reserva['hora_entrega'] ?? null) ?></span></div>
            <div class="flex justify-between gap-3"><span class="text-slate-500">Uso</span><span class="font-bold text-right"><?= $conHoraR($reserva['fecha_uso'], $reserva['hora_uso'] ?? null) ?></span></div>
            <div class="flex justify-between gap-3"><span class="text-slate-500">Recolección</span><span class="font-bold text-right"><?= $conHoraR($reserva['fecha_recoleccion_programada'], $reserva['hora_recoleccion'] ?? null) ?></span></div>
            <?php if ($costoIni > 0): ?>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Costo inicial</span><span class="text-right"><span class="font-bold">Q<?= number_format($costoIni, 2) ?></span><br><span class="text-xs text-slate-400"><?= htmlspecialchars($costoIniTxt) ?></span></span></div>
            <?php endif; ?>
            <div class="flex justify-between gap-3"><span class="text-slate-500">Registrado por</span><span class="text-right"><?= htmlspecialchars($reserva['usuario_nombre']) ?></span></div>
        </div>

        <!-- Acciones según estado -->
        <div id="acciones" class="bg-white rounded-2xl shadow p-5 space-y-3">
            <h3 class="font-bold text-slate-600 mb-1 text-sm">⚡ Acciones</h3>

            <!-- Comprobante: en esta misma pestaña, para que no se
                 acumulen pestañas abiertas al imprimir varios -->
            <a href="<?= URL_BASE ?>/?url=reservas/comprobante/<?= (int) $reserva['id'] ?>"
               class="block text-center bg-slate-700 hover:bg-slate-800 text-white font-bold py-3 rounded-xl transition text-sm">
                🖨️ Ver comprobante
            </a>

            <?php if ($reserva['estado'] === 'reservado'): ?>
                <!-- Mientras no se entregue, la reserva todavia se puede cambiar -->
                <a href="<?= URL_BASE ?>/?url=reservas/editar/<?= (int) $reserva['id'] ?>"
                   class="block text-center bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 rounded-xl transition text-sm">
                    ✏️ Modificar reserva
                </a>
            <?php endif; ?>

            <!-- WhatsApp — mensaje prellenado, sin emojis -->
            <?php $urlWhatsapp = urlWhatsappReserva($reserva, $detalle); ?>
            <a href="<?= htmlspecialchars($urlWhatsapp) ?>" target="_blank" rel="noopener"
               class="block text-center bg-green-500 hover:bg-green-600 text-white font-bold py-3 rounded-xl transition text-sm">
                💬 Enviar por WhatsApp
            </a>

            <?php if ($reserva['estado'] === 'reservado'): ?>
                <!-- Entrega: se puede tomar la foto del producto
                     tal como sale con el cliente -->
                <button type="button" onclick="document.getElementById('panelEntrega').classList.toggle('hidden')"
                        class="w-full text-center bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 rounded-xl transition text-sm">
                    📦 Registrar entrega
                </button>

                <form id="panelEntrega" class="hidden bg-teal-50 border border-teal-200 rounded-xl p-3 space-y-2"
                      method="POST" enctype="multipart/form-data" data-comprimir-imagenes
                      action="<?= URL_BASE ?>/?url=reservas/entregar/<?= (int) $reserva['id'] ?>"
                      onsubmit="return confirm('¿Confirmar la entrega al cliente?')">
                    <label class="block text-xs font-bold text-teal-800">
                        Foto del producto al entregarlo <span class="font-normal text-teal-600">(opcional, hasta 6)</span>
                    </label>
                    <input type="file" name="fotos[]" accept="image/*" capture="environment" multiple
                           onchange="soltecVistaPrevia(this, 'previewEntrega')"
                           class="w-full text-xs text-slate-500 file:mr-2 file:py-2.5 file:px-4 file:rounded-lg
                                  file:border-0 file:text-xs file:font-bold file:bg-white file:text-teal-700 cursor-pointer">
                    <div id="previewEntrega" class="flex gap-2 flex-wrap"></div>
                    <p class="text-[11px] text-teal-600">Desde el celular abre la cámara directamente.</p>
                    <button type="submit"
                            class="w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-3 rounded-lg text-sm transition">
                        Confirmar entrega
                    </button>
                </form>
                <a href="<?= URL_BASE ?>/?url=reservas/cancelar/<?= (int) $reserva['id'] ?>"
                   onclick="return confirm('¿Cancelar esta reserva? El inventario será restaurado.')"
                   class="block text-center bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-3 rounded-xl transition text-sm">
                    ✕ Cancelar reserva
                </a>
            <?php elseif ($reserva['estado'] === 'entregado'): ?>
                <a href="<?= URL_BASE ?>/?url=reservas/devolver/<?= (int) $reserva['id'] ?>"
                   class="block text-center bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition text-sm">
                    🔄 Registrar devolución
                </a>
            <?php else: ?>
                <p class="text-xs text-slate-400 text-center">No hay acciones disponibles para este estado.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detalle de productos -->
    <div class="lg:col-span-2 min-w-0">
        <div id="productos" class="bg-white rounded-2xl shadow overflow-hidden scroll-mt-20">
            <div class="px-5 py-4 border-b border-slate-100 font-bold text-slate-700">📦 Productos incluidos</div>

            <!-- Celular: lista (lo que el repartidor empaca) -->
            <div class="sm:hidden divide-y divide-slate-100">
                <?php foreach ($detalle as $d):
                    $porDia = ($d['tipo_precio'] ?? 'evento') === 'dia';
                    $ubic   = ubicacionTexto($d, '');
                ?>
                <div class="flex items-center gap-3 px-4 py-3">
                    <?php if (!empty($d['foto_path']) && existeImagen($d['foto_path'])): ?>
                        <img src="<?= urlImagen($d['foto_path']) ?>" loading="lazy"
                             alt="<?= htmlspecialchars($d['producto_nombre']) ?>"
                             onclick="soltecAbrirFoto(this.src, '<?= htmlspecialchars($d['producto_nombre'], ENT_QUOTES) ?>')"
                             class="w-12 h-12 object-cover rounded-lg border border-slate-200 shrink-0">
                    <?php endif; ?>
                    <div class="min-w-0 flex-1">
                        <div class="font-bold text-slate-800"><?= htmlspecialchars($d['producto_nombre']) ?></div>
                        <?php if ($ubic !== ''): ?>
                            <div class="text-[11px] text-slate-400">📍 <?= htmlspecialchars($ubic) ?></div>
                        <?php endif; ?>
                        <div class="text-xs text-slate-500">
                            Q<?= number_format((float) $d['precio_unitario'], 2) ?><?= $porDia ? '/día × ' . (int) $d['dias'] . ' día' . ($d['dias'] > 1 ? 's' : '') : '' ?>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-lg font-extrabold text-teal-700">× <?= (int) $d['cantidad'] ?></div>
                        <div class="text-xs font-bold text-slate-600">Q<?= number_format((float) $d['subtotal'], 2) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="px-4 py-3 bg-slate-50 space-y-1 text-sm">
                    <?php if ($descR > 0 || $costoIni > 0): ?>
                        <div class="flex justify-between text-slate-500"><span>Subtotal</span><span>Q<?= number_format($subProd, 2) ?></span></div>
                    <?php endif; ?>
                    <?php if ($descR > 0): ?>
                        <div class="flex justify-between text-red-500"><span>Descuento</span><span class="font-bold">-Q<?= number_format($descR, 2) ?></span></div>
                    <?php endif; ?>
                    <?php if ($costoIni > 0): ?>
                        <div class="flex justify-between text-slate-600"><span><?= htmlspecialchars($costoIniTxt) ?></span><span class="font-bold">+Q<?= number_format($costoIni, 2) ?></span></div>
                    <?php endif; ?>
                    <div class="flex justify-between font-extrabold text-teal-800 text-lg"><span>TOTAL</span><span>Q<?= number_format($reserva['total'], 2) ?></span></div>
                </div>
            </div>

            <!-- Tablet / PC: tabla -->
            <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-slate-500">Producto</th>
                        <th class="px-4 py-3 text-center text-slate-500">Cantidad</th>
                        <th class="px-4 py-3 text-right text-slate-500">Precio unit.</th>
                        <th class="px-4 py-3 text-right text-slate-500">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($detalle as $d): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <?php if (!empty($d['foto_path']) && existeImagen($d['foto_path'])): ?>
                                    <img src="<?= urlImagen($d['foto_path']) ?>" loading="lazy"
                                         alt="<?= htmlspecialchars($d['producto_nombre']) ?>"
                                         onclick="soltecAbrirFoto(this.src, '<?= htmlspecialchars($d['producto_nombre'], ENT_QUOTES) ?>')"
                                         class="w-11 h-11 object-cover rounded-lg border border-slate-200 cursor-zoom-in hover:ring-2 hover:ring-teal-400 transition shrink-0">
                                <?php endif; ?>
                                <div>
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($d['producto_nombre']) ?></div>
                                    <?php $ubic = ubicacionTexto($d, ''); ?>
                                    <?php if ($ubic !== ''): ?>
                                        <div class="text-[11px] text-slate-400">📍 <?= htmlspecialchars($ubic) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center text-slate-600 whitespace-nowrap">
                            <?= (int) $d['cantidad'] ?>
                            <?php if (($d['tipo_precio'] ?? 'evento') === 'dia'): ?>
                                <span class="text-xs text-teal-600 ml-1">× <?= (int) $d['dias'] ?> día<?= $d['dias'] > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right text-slate-600 whitespace-nowrap">
                            Q<?= number_format($d['precio_unitario'], 2) ?>
                            <?php if (($d['tipo_precio'] ?? 'evento') === 'dia'): ?>
                                <span class="text-xs text-slate-400">/día</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-slate-800 whitespace-nowrap">Q<?= number_format($d['subtotal'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php if ($descR > 0 || $costoIni > 0): ?>
                    <tr class="bg-slate-50">
                        <td colspan="3" class="px-4 py-2 text-right text-slate-500 text-sm">Subtotal</td>
                        <td class="px-4 py-2 text-right text-slate-600">Q<?= number_format($subProd, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($descR > 0): ?>
                    <tr class="bg-red-50">
                        <td colspan="3" class="px-4 py-2 text-right text-red-500 text-sm">Descuento</td>
                        <td class="px-4 py-2 text-right text-red-500 font-bold">-Q<?= number_format($descR, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($costoIni > 0): ?>
                    <tr class="bg-slate-50">
                        <td colspan="3" class="px-4 py-2 text-right text-slate-600 text-sm"><?= htmlspecialchars($costoIniTxt) ?></td>
                        <td class="px-4 py-2 text-right text-slate-700 font-bold">+Q<?= number_format($costoIni, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="bg-teal-50">
                        <td colspan="3" class="px-4 py-4 text-right font-extrabold text-teal-800">TOTAL</td>
                        <td class="px-4 py-4 text-right font-extrabold text-teal-800 text-lg">Q<?= number_format($reserva['total'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>

        <!-- ── Evidencia fotográfica ───────────────────────── -->
        <?php
            $fotos          = $fotos ?? [];
            $fotosEntrega   = array_filter($fotos, fn($f) => $f['tipo'] === 'entrega');
            $fotosDevolucion= array_filter($fotos, fn($f) => $f['tipo'] === 'devolucion');
        ?>
        <?php if ($fotos): ?>
        <div class="bg-white rounded-2xl shadow mt-5 lg:mt-6 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 font-bold text-slate-700">
                📸 Evidencia fotográfica
                <span class="text-xs font-normal text-slate-400 ml-1">(<?= count($fotos) ?>)</span>
            </div>
            <div class="p-4 sm:p-5 space-y-5">
                <?php
                $grupos = [
                    ['titulo' => 'Al entregar',  'color' => 'text-teal-700',    'lista' => $fotosEntrega],
                    ['titulo' => 'Al recibir',   'color' => 'text-emerald-700', 'lista' => $fotosDevolucion],
                ];
                foreach ($grupos as $g):
                    if (!$g['lista']) continue;
                ?>
                <div>
                    <div class="text-xs font-bold <?= $g['color'] ?> mb-2"><?= $g['titulo'] ?></div>
                    <div class="grid grid-cols-3 sm:flex sm:flex-wrap gap-3">
                        <?php foreach ($g['lista'] as $f): ?>
                            <div class="text-center">
                                <img src="<?= urlImagen($f['ruta']) ?>" loading="lazy" alt="Evidencia"
                                     onclick="soltecAbrirFoto(this.src, '<?= htmlspecialchars($g['titulo'] . ' — ' . fechaHora($f['creado_en']), ENT_QUOTES) ?>')"
                                     class="w-full aspect-square sm:w-24 sm:h-24 object-cover rounded-xl border-2 border-slate-200 cursor-zoom-in hover:ring-2 hover:ring-teal-400 transition">
                                <div class="text-[10px] text-slate-400 mt-1"><?= fechaHora($f['creado_en']) ?></div>
                                <?php if (!empty($f['usuario_nombre'])): ?>
                                    <div class="text-[10px] text-slate-300 truncate"><?= htmlspecialchars($f['usuario_nombre']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Desde la tarjeta de reparto: abre el formulario de entrega (que vive
// en "Acciones", más abajo) y lleva la pantalla hasta él.
function abrirPanelEntrega() {
    const panel = document.getElementById('panelEntrega');
    if (!panel) return;
    panel.classList.remove('hidden');
    panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<?php include RAIZ . '/app/vistas/plantillas/footer.php'; ?>
