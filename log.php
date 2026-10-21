<?php
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();
wajib_peran('admin');

$halaman_aktif = 'log';
$judul_halaman = 'Jejak aktivitas';

require_once __DIR__ . '/includes/header.php';

$hal   = max(1, (int)($_GET['hal'] ?? 1));
$per   = 50;
$mulai = ($hal - 1) * $per;

$total = jalankan($koneksi, "SELECT COUNT(*) AS n FROM tb_log")[0]['n'];
$log   = jalankan($koneksi, "SELECT * FROM tb_log ORDER BY waktu DESC LIMIT $mulai, $per");
$jumlah_hal = max(1, (int)ceil($total / $per));
?>

<div class="kartu overflow-hidden">
  <div class="px-4 py-3 border-b border-slate-200">
    <p class="text-sm text-slate-700"><?= number_format($total, 0, ',', '.') ?> catatan aktivitas</p>
  </div>
  <div class="overflow-x-auto">
    <table class="data w-full">
      <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Keterangan</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($log as $l): ?>
        <tr>
          <td class="whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($l['waktu'])) ?></td>
          <td><?= e($l['username'] ?: '-') ?></td>
          <td><?= e($l['aksi']) ?></td>
          <td class="text-xs text-slate-500"><?= e($l['objek']) ?></td>
          <td class="text-xs"><?= e($l['keterangan']) ?></td>
          <td class="text-xs text-slate-500"><?= e($l['ip']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$log): ?>
        <tr><td colspan="6" class="text-center text-slate-500 py-8">Belum ada aktivitas tercatat.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($jumlah_hal > 1): ?>
  <div class="px-4 py-3 border-t border-slate-200 flex flex-wrap gap-1">
    <?php for ($i = 1; $i <= min($jumlah_hal, 30); $i++): ?>
      <a href="?hal=<?= $i ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $i === $hal ? 'text-white' : 'text-slate-600 border border-slate-300' ?>"
         style="<?= $i === $hal ? 'background:#44403B' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
