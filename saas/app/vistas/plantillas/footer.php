    </main>
</div>

<!-- Compresion de fotos antes de subirlas + modal de imagenes -->
<script src="<?= URL_BASE ?>/app/public/js/imagenes.js?v=20260918"></script>

<script>
document.addEventListener('click', function (e) {
    const sidebar = document.getElementById('sidebar');
    const boton = e.target.closest('button');
    if (window.innerWidth < 769 && sidebar.classList.contains('abierto') && !sidebar.contains(e.target) && !boton) {
        sidebar.classList.remove('abierto');
    }
});
</script>

<?php include __DIR__ . '/aviso_pago.php'; ?>
</body>
</html>
