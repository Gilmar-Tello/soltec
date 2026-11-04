<?php
// Aviso de pago: sale UNA sola vez, justo despues de iniciar sesion, y se quita solo a los 10 segundos.
// El login deja $_SESSION['aviso_pago_dias'] (0 a 3) solo cuando faltan 3 dias o menos; aqui se consume.
if (isset($_SESSION['aviso_pago_dias'])):
    $diasAviso = max(0, (int) $_SESSION['aviso_pago_dias']);
    unset($_SESSION['aviso_pago_dias']);
    if ($diasAviso === 0)      $textoAviso = 'Hoy es el último día para realizar su pago.';
    elseif ($diasAviso === 1)  $textoAviso = 'Le queda 1 día para realizar su pago.';
    else                       $textoAviso = 'Le quedan ' . $diasAviso . ' días para realizar su pago.';
?>
<div id="avisoPago" role="alert"
     style="position:fixed;top:16px;right:16px;left:16px;max-width:380px;margin-left:auto;z-index:90;background:#fffbeb;border:1px solid #f59e0b;color:#92400e;border-radius:12px;padding:14px 16px;box-shadow:0 8px 24px rgba(0,0,0,.18);font-size:15px;font-weight:700;line-height:1.35;cursor:pointer;transition:opacity .4s;">
    ⏰ <?= htmlspecialchars($textoAviso) ?>
</div>
<script>
(function () {
    var aviso = document.getElementById('avisoPago');
    if (!aviso) return;
    function quitar() {
        aviso.style.opacity = '0';
        setTimeout(function () { if (aviso.parentNode) aviso.parentNode.removeChild(aviso); }, 450);
    }
    aviso.addEventListener('click', quitar);
    setTimeout(quitar, 10000);   // a los 10 segundos desaparece
})();
</script>
<?php endif; ?>
