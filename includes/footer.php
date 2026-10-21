    </div>
    <footer class="mt-auto px-6 py-4 text-xs text-slate-500 cetak-sembunyi">
      <?= APP_NAMA ?>, <?= APP_INSTANSI ?>. Butuh bantuan? Buka menu <a href="panduan.php" class="underline">Bantuan</a>.
    </footer>
  </main>
</div>
<script>
(function () {
  /* Menu ponsel */
  const buka = document.getElementById('bukaMenu');
  const menu = document.getElementById('menuPonsel');
  const lapis = document.getElementById('lapisMenu');
  const tutup = document.getElementById('tutupMenu');
  function atur(terbuka) {
    menu.hidden = !terbuka;
    lapis.hidden = !terbuka;
    buka.setAttribute('aria-expanded', terbuka ? 'true' : 'false');
    document.body.style.overflow = terbuka ? 'hidden' : '';
    if (terbuka) { tutup.focus(); } else { buka.focus(); }
  }
  buka.addEventListener('click', function () { atur(true); });
  tutup.addEventListener('click', function () { atur(false); });
  lapis.addEventListener('click', function () { atur(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { atur(false); } });
})();
</script>
<?php if (!empty($halaman_langsung)): ?>
<script src="assets/langsung.js" data-api="api/versi.php" data-jeda="5000"></script>
<?php endif; ?>
</body>
</html>
