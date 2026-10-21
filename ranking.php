<?php
$halaman_aktif = 'ranking';
$judul_halaman = 'Peringkat usulan';
$halaman_langsung = true;
require_once __DIR__ . '/includes/header.php';

$g = $_GET;
$g['tahun'] = $g['tahun'] ?? APP_TAHUN_DEFAULT;
/* Bawaan: hanya 5 usulan prioritas setiap desa */
$g['prioritas'] = in_array($g['prioritas'] ?? 'ya', ['ya', 'semua'], true) ? ($g['prioritas'] ?? 'ya') : 'ya';
$f = bangun_filter($g);

$daftar = jalankan($koneksi,
    SQL_USULAN_DASAR . " WHERE " . $f['where'] . " AND u.total_nilai IS NOT NULL
     ORDER BY u.total_nilai DESC, u.estimasi_anggaran ASC, u.nama_usulan ASC",
    $f['tipe'], $f['nilai']);

$kecamatan_ref = ambil_kecamatan_pilihan($koneksi);
if (kec_user() > 0) {
    $g['kecamatan'] = kec_user();
}
$peta_desa = ambil_peta_desa($koneksi);
foreach (jalankan($koneksi, "SELECT DISTINCT id_kecamatan, desa FROM tb_usulan ORDER BY desa") as $r) {
    $ik = (int)$r['id_kecamatan'];
    if (empty($peta_desa[$ik]) || !in_array($r['desa'], $peta_desa[$ik], true)) {
        $peta_desa[$ik][] = $r['desa'];
    }
}
foreach ($peta_desa as &$daftar_d) { sort($daftar_d, SORT_NATURAL | SORT_FLAG_CASE); }
unset($daftar_d);
$sektor_ref    = ambil_daftar($koneksi, 'tb_sektor', 'id_sektor');
$kategori_ref  = ambil_kategori($koneksi);

/* Peringkat sama untuk nilai sama, nomor urut mengikuti metode kompetisi */
$peringkat = [];
$nomor = 0;
$nilai_sebelum = null;
foreach ($daftar as $i => $d) {
    if ($nilai_sebelum === null || (float)$d['total_nilai'] < (float)$nilai_sebelum) {
        $nomor = $i + 1;
        $nilai_sebelum = $d['total_nilai'];
    }
    $peringkat[$i] = $nomor;
}

$total_anggaran = array_sum(array_column($daftar, 'estimasi_anggaran'));
$pagu = (float)($g['pagu'] ?? 0);
?>

<form method="get" class="kartu p-4 mb-5 cetak-sembunyi">
  <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
    <div>
      <label class="block text-xs text-slate-600 mb-1">Tahun</label>
      <input type="number" name="tahun" value="<?= e($g['tahun']) ?>" class="isian">
    </div>
    <div>
      <label class="block text-xs text-slate-600 mb-1">Kecamatan</label>
      <select name="kecamatan" id="f_kec" class="isian">
        <?php if (kec_user() === 0): ?><option value="">Semua</option><?php endif; ?>
        <?php foreach ($kecamatan_ref as $r): ?>
          <option value="<?= $r['id_kecamatan'] ?>" <?= ($g['kecamatan'] ?? '') == $r['id_kecamatan'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-600 mb-1">Cakupan</label>
      <select name="prioritas" class="isian">
        <option value="ya" <?= $g['prioritas'] === 'ya' ? 'selected' : '' ?>>Usulan prioritas saja</option>
        <option value="semua" <?= $g['prioritas'] === 'semua' ? 'selected' : '' ?>>Semua usulan</option>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-600 mb-1">Desa/kelurahan</label>
      <select name="desa" id="f_desa" class="isian" data-terpilih="<?= e($g['desa'] ?? '') ?>"></select>
    </div>
    <div>
      <label class="block text-xs text-slate-600 mb-1">Sektor</label>
      <select name="sektor" class="isian">
        <option value="">Semua</option>
        <?php foreach ($sektor_ref as $r): ?>
          <option value="<?= $r['id_sektor'] ?>" <?= ($g['sektor'] ?? '') == $r['id_sektor'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-600 mb-1">Kategori</label>
      <select name="kategori" class="isian">
        <option value="">Semua</option>
        <?php foreach ($kategori_ref as $r): ?>
          <option value="<?= $r['id_kategori'] ?>" <?= ($g['kategori'] ?? '') == $r['id_kategori'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-600 mb-1">Pagu indikatif (Rp)</label>
      <input type="number" name="pagu" value="<?= e($g['pagu'] ?? '') ?>" class="isian" placeholder="Opsional">
    </div>
  </div>
  <div class="flex gap-2 mt-3">
    <button class="tombol">Terapkan</button>
    <a href="ranking.php" class="tombol-2">Atur ulang</a>
    <a href="ekspor.php?<?= http_build_query($g) ?>" class="tombol-2">Unduh CSV</a>
    <button type="button" onclick="window.print()" class="tombol-2">Cetak</button>
  </div>
  <p class="text-xs text-slate-500 mt-3">
    Isi pagu indikatif untuk menandai batas usulan yang masih tertampung anggaran.
  </p>
</form>

<div id="langsung-peringkat" data-langsung>
<div class="grid gap-4 md:grid-cols-3 mb-5">
  <div class="kartu p-4"><p class="text-xs text-slate-500">Usulan dinilai</p>
    <p class="text-2xl font-bold"><?= count($daftar) ?></p></div>
  <div class="kartu p-4"><p class="text-xs text-slate-500">Total kebutuhan anggaran</p>
    <p class="text-2xl font-bold"><?= rupiah($total_anggaran) ?></p></div>
  <div class="kartu p-4"><p class="text-xs text-slate-500">Pagu indikatif</p>
    <p class="text-2xl font-bold"><?= $pagu > 0 ? rupiah($pagu) : '-' ?></p></div>
</div>

<div class="kartu overflow-hidden">
  <div class="overflow-x-auto">
    <table class="data w-full">
      <thead>
        <tr><th>Peringkat</th><th>Kode</th><th>Usulan</th><th>Kecamatan</th><th>Desa/Kelurahan</th>
            <th>Sektor</th><th>OPD</th><th class="text-right">Anggaran</th>
            <th class="text-right">Akumulasi</th><th class="text-right">Nilai</th><th>Kategori</th>
            <th class="cetak-sembunyi">Aksi</th></tr>
      </thead>
      <tbody>
      <?php
      $akumulasi = 0;
      $garis_pagu_ditandai = false;
      foreach ($daftar as $i => $d):
          $akumulasi += (float)$d['estimasi_anggaran'];
          $lewat_pagu = $pagu > 0 && $akumulasi > $pagu;
      ?>
        <?php if ($pagu > 0 && $lewat_pagu && !$garis_pagu_ditandai): $garis_pagu_ditandai = true; ?>
          <tr><td colspan="12" class="text-xs font-semibold text-rose-700 bg-rose-50">
            Batas pagu indikatif <?= rupiah($pagu) ?> terlampaui pada baris berikut
          </td></tr>
        <?php endif; ?>
        <tr>
          <td class="font-semibold"><?= $peringkat[$i] ?></td>
          <td class="text-xs text-slate-500"><?= e($d['kode_usulan']) ?></td>
          <td><a style="color:#44403B" href="usulan_detail.php?id=<?= (int)$d['id_usulan'] ?>"><?= e($d['nama_usulan']) ?></a>
              <div class="mt-0.5"><?= lencana_prioritas($d['peringkat_desa']) ?></div></td>
          <td><?= e($d['kecamatan']) ?></td>
          <td><?= e($d['desa']) ?></td>
          <td><?= e($d['sektor']) ?></td>
          <td><?= e($d['opd']) ?></td>
          <td class="text-right"><?= rupiah($d['estimasi_anggaran']) ?></td>
          <td class="text-right <?= $lewat_pagu ? 'text-rose-600' : 'text-slate-500' ?>"><?= rupiah($akumulasi) ?></td>
          <td class="text-right"><span class="font-semibold"><?= angka($d['total_nilai']) ?></span>
              <div class="text-xs text-slate-500 whitespace-nowrap"><?= (int)$d['jumlah_penilai'] > 0 ? (int)$d['jumlah_penilai'] . ' desa' : 'nilai awal' ?></div></td>
          <td><span class="lencana" style="background:<?= e($d['warna_kategori'] ?: '#64748B') ?>"><?= e($d['kategori']) ?></span></td>
          <td class="cetak-sembunyi whitespace-nowrap">
            <a href="usulan_detail.php?id=<?= (int)$d['id_usulan'] ?>" class="text-xs text-slate-600">Detail</a>
            <?php if ($aksi = aksi_usulan($d)): ?>
              <span class="text-slate-300">|</span> <?= $aksi ?>
            <?php endif; ?>
            <?php if (boleh('admin')): ?>
              <span class="text-slate-300">|</span>
              <a href="proses/usulan_hapus.php?id=<?= (int)$d['id_usulan'] ?>&csrf=<?= token_csrf() ?>&kembali=ranking"
                 onclick="return confirm('Hapus usulan <?= e(addslashes($d['nama_usulan'])) ?> beserta rincian skornya?')"
                 class="text-xs text-rose-600">Hapus</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$daftar): ?>
        <tr><td colspan="12" class="text-center text-slate-500 py-8">Belum ada usulan yang dinilai pada penyaring ini.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div>

<script>
  pasangDesa('f_kec', 'f_desa', <?= json_encode($peta_desa, JSON_UNESCAPED_UNICODE) ?>,
             'Semua desa', 'Pilih kecamatan dahulu');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>