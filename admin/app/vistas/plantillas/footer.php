</main>
    <footer class="text-center text-xs text-sol-gris py-4">
        SOLTEC &copy; <?= date('Y') ?> — Tecnología que impulsa tu negocio
    </footer>
</div>

<script>
    // ── Menu lateral en telefono ──────────────────────────────
    function alternarMenu() {
        var abierto = document.getElementById('sidebar').classList.toggle('abierto');
        document.getElementById('velo').classList.toggle('hidden', !abierto);
        document.documentElement.classList.toggle('menu-abierto', abierto);
    }
    function cerrarMenu() {
        document.getElementById('sidebar').classList.remove('abierto');
        document.getElementById('velo').classList.add('hidden');
        document.documentElement.classList.remove('menu-abierto');
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrarMenu(); });

    // ── Tablas: en pantallas chicas se desplazan hacia los lados ──
    document.querySelectorAll('main table').forEach(function (t) {
        if (t.closest('.overflow-x-auto')) return;
        var envoltorio = document.createElement('div');
        envoltorio.className = 'overflow-x-auto';
        t.parentNode.insertBefore(envoltorio, t);
        envoltorio.appendChild(t);
    });
</script>
</body>
</html>
