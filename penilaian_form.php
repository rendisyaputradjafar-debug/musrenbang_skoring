<?php
/**
 * Formulir penilaian satu usulan oleh akun desa.
 */
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();
wajib_peran('desa');

$halaman_aktif = 'penilaian';
$judul_halaman = 'Nilai usulan desa lain';

$id = (int)($_GET['id'] ?? 0);
$baris = jalankan($koneksi, SQL_USULAN_DASAR . " WHERE u.id_usulan = ?", 'i', [$id]);
$u = $baris[0] ?? null;

/* Pemeriksaan hak menilai */
$alasan = '';
if (!$u) {
    $alasan = 'Usulan tidak ditemukan.';
} elseif ((int)$u['id_kecamatan'] !== kec_user()) {
    $alasan = 'Usulan ini berada di luar kecamatan Anda.';
} elseif ($u['desa'] === nama_desa_user()) {
    $alasan = 'Usulan desa sendiri tidak dapat dinilai. Usulan ini dinilai oleh desa lain.';
} elseif ($u['status'] === 'Ditolak') {
    $alasan = 'Usulan ini ditolak sehingga tidak dinilai.';
} elseif ((int)$u['peringkat_desa'] < 1 || (int)$u['peringkat_desa'] > JUMLAH_PRIORITAS_DESA) {
    $alasan = 'Usulan ini bukan termasuk ' . JUMLAH_PRIORITAS_DESA . ' usulan prioritas desa tersebut.';
}
if ($alasan !== '') {
    pesan_set('galat', $alasan);
    header('Location: penilaian.php');
    exit;
}
$terbuka = $u['status'] === 'Draft';

$milik = jalankan($koneksi, "SELECT * FROM tb_penilaian WHERE id_usulan = ? AND id_desa_penilai = ?",
    'ii', [$id, desa_user()]);
$milik = $milik[0] ?? null;
$skor_saya = [];
if ($milik) {
    foreach (jalankan($koneksi, "SELECT id_kriteria, skor FROM tb_penilaian_skor WHERE id_penilaian = ?",
                 'i', [(int)$milik['id_penilaian']]) as $s) {
        $skor_saya[(int)$s['id_kriteria']] = (int)$s['skor'];
    }
}

$kriteria = ambil_kriteria($koneksi);
$kategori = ambil_kategori($koneksi);
$panduan  = jalankan($koneksi, "SELECT * FROM tb_panduan_skor ORDER BY skor DESC");
$label_skor = array_column($panduan, 'keterangan', 'skor');
$target   = target_penilai($koneksi, (int)$u['id_kecamatan'], $u['desa']);

/* Sisa usulan yang belum dinilai desa ini */
$sisa = (int)jalankan($koneksi, "SELECT COUNT(*) AS n FROM tb_usulan x
    WHERE x.tahun = ? AND x.id_kecamatan = ? AND x.desa <> ? AND x.status = 'Draft' AND x.id_usulan <> ? AND " . syarat_prioritas('x') . "
      AND NOT EXISTS (SELECT 1 FROM tb_penilaian p WHERE p.id_usulan = x.id_usulan AND p.id_desa_penilai = ?)",
    'iisii', [(int)$u['tahun'], kec_user(), nama_desa_user(), $id, desa_user()])[0]['n'];

require_once __DIR__ . '/includes/header.php';
?>

<a href="penilaian.php?tahun=<?= (int)$u['tahun'] ?>" class="tombol-2 inline-block mb-4">Kembali ke daftar penilaian</a>

<form method="post" action="proses/penilaian_simpan.php" class="grid gap-5 lg:grid-cols-3">
  <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
  <input type="hidden" name="id_usulan" value="<?= $id ?>">

  <div class="lg:col-span-2 space-y-5">

    <div class="kartu p-5">
      <p class="text-xs text-slate-500">Prioritas ke-<?= (int)$u['peringkat_desa'] ?> dari <?= e($u['desa']) ?>, tahun rencana <?= (int)$u['tahun'] ?></p>
      <h2 class="text-xl font-bold text-slate-900 mt-1"><?= e($u['nama_usulan']) ?></h2>
      <p class="text-sm text-slate-600 mt-2 leading-relaxed"><?= $u['uraian_masalah'] ? nl2br(e($u['uraian_masalah'])) : 'Tidak ada uraian masalah.' ?></p>
      <div class="grid gap-4 sm:grid-cols-3 mt-5 text-sm">
        <div><p class="text-xs text-slate-500">Desa pengusul</p><p class="font-medium"><?= e($u['desa']) ?></p></div>
        <div><p class="text-xs text-slate-500">Sektor</p><p class="font-medium"><?= e($u['sektor']) ?></p></div>
        <div><p class="text-xs text-slate-500">OPD</p><p class="font-medium"><?= e($u['opd']) ?></p></div>
        <div><p class="text-xs text-slate-500">Volume</p><p class="font-medium"><?= angka($u['volume'], 0) ?> <?= e($u['satuan']) ?></p></div>
        <div><p class="text-xs text-slate-500">Estimasi anggaran</p><p class="font-medium"><?= rupiah($u['estimasi_anggaran']) ?></p></div>
        <div><p class="text-xs text-slate-500">Sumber</p><p class="font-medium"><?= e($u['sumber']) ?></p></div>
      </div>
    </div>

    <?php if (!$terbuka): ?>
      <p class="rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm px-4 py-3">
        Usulan ini sudah <?= e(strtolower($u['status'])) ?>, sehingga penilaian ditutup. Nilai Anda di bawah hanya untuk dilihat.
      </p>
    <?php endif; ?>

    <fieldset class="kartu p-5" <?= $terbuka ? '' : 'disabled' ?>>
      <legend class="sr-only">Skor setiap kriteria</legend>
      <p class="font-bold text-lg">Seberapa penting usulan ini?</p>
      <p class="text-sm text-slate-500 mt-1 mb-4">Untuk setiap pertanyaan, pilih angka 1 (paling rendah) sampai 5 (paling tinggi). Semua wajib diisi.</p>

      <div class="space-y-3">
      <?php foreach ($kriteria as $kr): $terpilih = $skor_saya[$kr['id_kriteria']] ?? 0; ?>
        <div class="border border-slate-200 rounded-xl p-4" data-kriteria="<?= (int)$kr['id_kriteria'] ?>">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <p class="text-sm font-medium text-slate-800"><?= e($kr['nama']) ?>
                <span class="text-xs font-normal text-slate-500">bobot <?= angka($kr['bobot'], 0) ?></span></p>
              <p class="text-xs text-slate-500 mt-0.5"><?= e($kr['deskripsi']) ?></p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-xs text-slate-500">Nilai</p>
              <p class="text-sm font-semibold" data-nilai="<?= (int)$kr['id_kriteria'] ?>">-</p>
            </div>
          </div>
          <div class="pilih-skor mt-3" role="radiogroup" aria-label="Skor <?= e($kr['nama']) ?>">
            <?php for ($s = 1; $s <= (int)$kr['skala_maks']; $s++): ?>
              <label>
                <input type="radio" class="sr-only skor" name="skor[<?= (int)$kr['id_kriteria'] ?>]" value="<?= $s ?>"
                       data-bobot="<?= e($kr['bobot']) ?>" data-skala="<?= (int)$kr['skala_maks'] ?>"
                       data-id="<?= (int)$kr['id_kriteria'] ?>" <?= $terpilih === $s ? 'checked' : '' ?> <?= $s === 1 ? 'required' : '' ?>>
                <span class="kotak"><b><?= $s ?></b><small><?= e(explode(' / ', $label_skor[$s] ?? '')[0]) ?></small></span>
              </label>
            <?php endfor; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>

      <label for="catatan" class="block text-sm font-medium text-slate-800 mt-5 mb-1">Catatan untuk verifikator (opsional)</label>
      <textarea id="catatan" name="catatan" rows="3" maxlength="500" class="isian"
                placeholder="Misalnya kondisi lapangan yang Anda ketahui"><?= e($milik['catatan'] ?? '') ?></textarea>
    </fieldset>
  </div>

  <div class="space-y-5">
    <div class="kartu p-5 sticky top-4">
      <p class="font-semibold text-sm text-slate-800 mb-3">Nilai dari <?= e(nama_desa_user()) ?></p>
      <div class="rounded-lg p-4 text-white mb-3" style="background:#211F1C">
        <p class="text-4xl font-bold" id="totalNilai">-</p>
        <p class="text-xs mt-2 inline-block px-2 py-0.5 rounded-full" id="labelKategori" style="background:#44403B">Lengkapi semua kriteria</p>
        <p class="text-xs mt-2 text-slate-300" id="sisaKriteria"></p>
      </div>

      <p class="text-xs text-slate-600 mb-4">
        <?= (int)$u['jumlah_penilai'] ?> dari <?= $target ?> desa sudah menilai usulan ini.
        <?php if ($milik): ?>
          Nilai rata-rata saat ini <b><?= angka($u['total_nilai']) ?></b>.
          Anda terakhir menilai <?= e(waktu_lalu($milik['diubah_pada'])) ?>.
        <?php else: ?>
          Nilai rata-rata desa lain ditampilkan setelah Anda menilai, agar penilaian Anda tidak terpengaruh.
        <?php endif; ?>
      </p>

      <?php if ($terbuka): ?>
        <?php if ($sisa > 0): ?>
          <button name="lanjut" value="1" class="tombol w-full">Simpan dan nilai berikutnya</button>
          <button class="tombol-2 w-full mt-2">Simpan saja</button>
          <p class="text-xs text-slate-500 mt-2 text-center">Masih <?= $sisa ?> usulan lain yang belum Anda nilai.</p>
        <?php else: ?>
          <button class="tombol w-full"><?= $milik ? 'Simpan perubahan' : 'Simpan penilaian' ?></button>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="kartu p-5">
      <p class="font-semibold text-sm text-slate-800 mb-3">Arti skor</p>
      <ul class="text-xs text-slate-600 space-y-1">
        <?php foreach ($panduan as $p): ?>
          <li><b><?= (int)$p['skor'] ?></b>: <?= e($p['keterangan']) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</form>


<script>
(function () {
  const kategori = <?= json_encode(array_map(function ($k) {
      return ['nama' => $k['nama'], 'batas' => (float)$k['batas_bawah'], 'warna' => $k['warna']];
  }, $kategori), JSON_UNESCAPED_UNICODE) ?>;
  const idKriteria = <?= json_encode(array_map(function ($k) { return (int)$k['id_kriteria']; }, $kriteria)) ?>;
  const fmt = n => n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function hitung() {
    let total = 0, terisi = 0;
    idKriteria.forEach(function (id) {
      const r = document.querySelector('input[name="skor[' + id + ']"]:checked');
      const sel = document.querySelector('[data-nilai="' + id + '"]');
      if (!r) { sel.textContent = '-'; return; }
      const nilai = Math.round(Number(r.value) / (Number(r.dataset.skala) || 5) * Number(r.dataset.bobot) * 100) / 100;
      sel.textContent = fmt(nilai);
      total += nilai;
      terisi++;
    });
    const lengkap = terisi === idKriteria.length;
    const label = document.getElementById('labelKategori');
    document.getElementById('totalNilai').textContent = terisi ? fmt(Math.round(total * 100) / 100) : '-';
    document.getElementById('sisaKriteria').textContent = lengkap ? '' : (idKriteria.length - terisi) + ' kriteria belum diisi';
    if (lengkap) {
      const k = kategori.find(x => total >= x.batas);
      label.textContent = k ? k.nama : 'Tanpa kategori';
      label.style.background = k ? k.warna : '#44403B';
    } else {
      label.textContent = 'Lengkapi semua kriteria';
      label.style.background = '#44403B';
    }
  }
  document.querySelectorAll('.skor').forEach(el => el.addEventListener('change', hitung));
  hitung();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
