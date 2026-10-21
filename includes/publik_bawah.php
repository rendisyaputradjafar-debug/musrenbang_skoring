<?php
/* Kaki halaman publik bersama. $jeda_publik mengatur selang pemeriksaan data baru (ms). */
$jeda_publik = $jeda_publik ?? 8000;
?>
<footer style="background:var(--tinta)">
  <div class="wadah py-8 flex flex-wrap items-center justify-between gap-4 text-sm" style="color:#A9A49A">
    <p><span class="text-white font-semibold"><?= APP_NAMA ?></span>, <?= APP_SUBJUDUL ?>. <?= APP_INSTANSI ?>.</p>
    <a href="<?= $sudah_masuk ? $tujuan_masuk : 'login.php' ?>" class="text-white font-semibold"><?= $sudah_masuk ? 'Kembali ke aplikasi' : 'Masuk untuk petugas' ?></a>
  </div>
</footer>

<script>
(function () {
  /* Menu ponsel */
  const tombol = document.getElementById('tombolMenu');
  const menu = document.getElementById('menuHp');
  tombol.addEventListener('click', function () {
    const terbuka = !menu.classList.toggle('hidden');
    tombol.setAttribute('aria-expanded', terbuka ? 'true' : 'false');
  });
  menu.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('click', function () {
      menu.classList.add('hidden');
      tombol.setAttribute('aria-expanded', 'false');
    });
  });
})();
</script>
<script src="assets/langsung.js" data-api="api/versi.php" data-jeda="<?= (int)$jeda_publik ?>"></script>
</body>
</html>
