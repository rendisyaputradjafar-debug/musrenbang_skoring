<?php
$halaman_aktif = 'usulan';
$judul_halaman = 'Daftar usulan';
$halaman_langsung = true;
require_once __DIR__ . '/includes/header.php';

$g = $_GET;
$g['tahun'] = $g['tahun'] ?? APP_TAHUN_DEFAULT;
$f = bangun_filter($g);

$hal    = max(1, (int)($g['hal'] ?? 1));
$per    = 25;
$mulai  = ($hal - 1) * $per;

$total = jalankan($koneksi, "SELECT COUNT(*) AS n FROM tb_usulan u WHERE " . $f['where'],
                  $f['tipe'], $f['nilai'])[0]['n'];

$urut_pilihan = [
    'nilai'    => 'u.total_nilai DESC, u.estimasi_anggaran ASC',
    'terbaru'  => 'u.dibuat_pada DESC',
    'anggaran' => 'u.estimasi_anggaran DESC',
    'kecamatan'=> 'kec.nama ASC, u.total_nilai DESC',
];
$urut_kunci = $g['urut'] ?? 'nilai';
$urut = $urut_pilihan[$urut_kunci] ?? $urut_pilihan['nilai'];

$daftar = jalankan($koneksi,
    SQL_USULAN_DASAR . " WHERE " . $f['where'] . " ORDER BY $urut LIMIT $mulai, $per",
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
$opd_ref       = ambil_daftar($koneksi, 'tb_opd', 'id_opd');
$kategori_ref  = ambil_kategori($koneksi);

function tautan_halaman(array $g, int $hal): string
{
    $g['hal'] = $hal;
    return '?' . http_build_query($g);
}
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
      <label class="block text-xs text-slate-600 mb-1">Prioritas desa</label>
      <select name="prioritas" class="isian">
        <option value="">Semua usulan</option>
        <option value="ya" <?= ($g['prioritas'] ?? '') === 'ya' ? 'selected' : '' ?>>Usulan prioritas saja</option>
        <option value="bukan" <?= ($g['prioritas'] ?? '') === 'bukan' ? 'selected' : '' ?>>Cadangan saja</option>
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
      <label class="block text-xs text-slate-600 mb-1">OPD</label>
      <select name="opd" class="isian">
        <option value="">Semua</option>
        <?php foreach ($opd_ref as $r): ?>
          <option value="<?= $r['id_opd'] ?>" <?= ($g['opd'] ?? '') == $r['id_opd'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
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
      <label class="block text-xs text-slate-600 mb-1">Status</label>
      <select name="status" class="isian">
        <option value="">Semua</option>
        <?php foreach (['Draft','Terverifikasi','Ditolak'] as $s): ?>
          <option value="<?= $s ?>" <?= ($g['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-600 mb-1">Penilaian</label>
      <select name="penilaian" class="isian">
        <option value="">Semua</option>
        <option value="belum" <?= ($g['penilaian'] ?? '') === 'belum' ? 'selected' : '' ?>>Belum dinilai</option>
        <option value="sudah" <?= ($g['penilaian'] ?? '') === 'sudah' ? 'selected' : '' ?>>Sudah dinilai</option>
      </select>
    </div>
  </div>
  <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6 mt-3">
    <div class="md:col-span-2 xl:col-span-3">
      <label class="block text-xs text-slate-600 mb-1">Cari nama usulan, desa, atau kode</label>
      <input name="cari" value="<?= e($g['cari'] ?? '') ?>" class="isian" placeholder="Contoh: jalan Pisou">
    </div>
    <div>
      <label class="block text-xs text-slate-600 mb-1">Urutkan</label>
      <select name="urut" class="isian">
        <option value="nilai"     <?= $urut_kunci === 'nilai' ? 'selected' : '' ?>>Nilai tertinggi</option>
        <option value="terbaru"   <?= $urut_kunci === 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
        <option value="anggaran"  <?= $urut_kunci === 'anggaran' ? 'selected' : '' ?>>Anggaran terbesar</option>
        <option value="kecamatan" <?= $urut_kunci === 'kecamatan' ? 'selected' : '' ?>>Kecamatan</option>
      </select>
    </div>
    <div class="flex items-end gap-2">
      <button class="tombol">Terapkan</button>
      <a href="usulan.php" class="tombol-2">Atur ulang</a>
    </div>
    <div class="flex items-end">
      <a class="tombol-2" href="ekspor.php?<?= http_build_query($g) ?>">Unduh CSV</a>
    </div>
  </div>
</form>

<div id="langsung-usulan" data-langsung>
<div class="kartu overflow-hidden">
  <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
    <p class="text-sm text-slate-700"><?= number_format($total, 0, ',', '.') ?> usulan ditemukan</p>
    <?php if (peran() !== 'desa'): ?><a href="usulan_form.php" class="tombol cetak-sembunyi">Input usulan baru</a><?php endif; ?>
  </div>

  <div class="overflow-x-auto">
    <table class="data w-full">
      <thead>
        <tr>
          <th>Kode</th><th>Usulan</th><th>Lokasi</th><th>Sektor</th><th>OPD</th>
          <th class="text-right">Volume</th><th class="text-right">Anggaran</th>
          <th class="text-right">Nilai</th><th>Kategori</th><th>Status</th><th class="cetak-sembunyi">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$daftar): ?>
        <tr><td colspan="11" class="text-center text-slate-500 py-8">
          Belum ada usulan yang cocok dengan penyaring. Ubah penyaring atau tambahkan usulan baru.
        </td></tr>
      <?php endif; ?>
      <?php foreach ($daftar as $u): ?>
        <tr>
          <td class="text-xs text-slate-500"><?= e($u['kode_usulan']) ?></td>
          <td>
            <a class="font-medium" style="color:#44403B" href="usulan_detail.php?id=<?= (int)$u['id_usulan'] ?>"><?= e($u['nama_usulan']) ?></a>
            <div class="mt-0.5"><?= lencana_prioritas($u['peringkat_desa']) ?></div>
            <div class="text-xs text-slate-500"><?= e(mb_strimwidth((string)$u['uraian_masalah'], 0, 70, '...')) ?></div>
          </td>
          <td><?= e($u['desa']) ?><div class="text-xs text-slate-500"><?= e($u['kecamatan']) ?></div></td>
          <td><span class="lencana" style="background:<?= e($u['warna_sektor']) ?>"><?= e($u['sektor']) ?></span></td>
          <td><?= e($u['opd']) ?></td>
          <td class="text-right"><?= angka($u['volume'], 0) ?> <?= e($u['satuan']) ?></td>
          <td class="text-right"><?= rupiah($u['estimasi_anggaran']) ?></td>
          <td class="text-right"><span class="font-semibold"><?= $u['total_nilai'] !== null ? angka($u['total_nilai']) : '-' ?></span>
              <div class="text-xs text-slate-500 whitespace-nowrap"><?= (int)$u['jumlah_penilai'] > 0 ? (int)$u['jumlah_penilai'] . ' desa' : ($u['total_nilai'] !== null ? 'nilai awal' : 'menunggu') ?></div></td>
          <td><?php if ($u['kategori']): ?>
                <span class="lencana" style="background:<?= e($u['warna_kategori']) ?>"><?= e($u['kategori']) ?></span>
              <?php else: ?><span class="text-xs text-slate-400">Belum dinilai</span><?php endif; ?></td>
          <td class="text-xs"><?= e($u['status']) ?></td>
          <td class="cetak-sembunyi whitespace-nowrap">
            <a href="usulan_detail.php?id=<?= (int)$u['id_usulan'] ?>" class="text-xs text-slate-600">Detail</a>
            <?php if ($aksi = aksi_usulan($u)): ?>
              <span class="text-slate-300">|</span> <?= $aksi ?>
            <?php endif; ?>
            <?php if (boleh('admin')): ?>
              <span class="text-slate-300">|</span>
              <a href="proses/usulan_hapus.php?id=<?= (int)$u['id_usulan'] ?>&csrf=<?= token_csrf() ?>"
                 onclick="return confirm('Hapus usulan ini beserta rincian skornya?')"
                 class="text-xs text-rose-600">Hapus</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php $jumlah_hal = max(1, (int)ceil($total / $per)); if ($jumlah_hal > 1): ?>
  <div class="px-4 py-3 border-t border-slate-200 flex flex-wrap gap-1 cetak-sembunyi">
    <?php for ($i = 1; $i <= $jumlah_hal; $i++): ?>
      <a href="<?= e(tautan_halaman($g, $i)) ?>"
         class="px-3 py-1.5 rounded-lg text-sm <?= $i === $hal ? 'text-white' : 'text-slate-600 border border-slate-300' ?>"
         style="<?= $i === $hal ? 'background:#44403B' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</div>

<script>
  pasangDesa('f_kec', 'f_desa', <?= json_encode($peta_desa, JSON_UNESCAPED_UNICODE) ?>,
             'Semua desa', 'Pilih kecamatan dahulu');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>