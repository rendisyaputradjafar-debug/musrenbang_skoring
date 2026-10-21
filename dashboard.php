<?php
$halaman_aktif = 'dashboard';
$judul_halaman = 'Beranda';
$halaman_langsung = true;
require_once __DIR__ . '/includes/header.php';

$tahun = (int)($_GET['tahun'] ?? APP_TAHUN_DEFAULT);

/* Penyaring wilayah. Akun kecamatan selalu terkunci pada kecamatannya. */
$kec  = kec_user() > 0 ? kec_user() : (int)($_GET['kec'] ?? 0);
$desa = $kec > 0 ? trim((string)($_GET['desa'] ?? '')) : '';

$w  = 'u.tahun = ?';
$tp = 'i';
$pv = [$tahun];
if ($kec > 0) {
    $w .= ' AND u.id_kecamatan = ?';
    $tp .= 'i';
    $pv[] = $kec;
}
if ($desa !== '') {
    $w .= ' AND u.desa = ?';
    $tp .= 's';
    $pv[] = $desa;
}

/* Usulan di luar 5 prioritas desa tidak ikut dihitung; jumlahnya disebutkan terpisah */
$tersembunyi = (int)jalankan($koneksi, "SELECT COUNT(*) AS n FROM tb_usulan u WHERE $w AND u.status <> 'Ditolak'
    AND NOT (" . syarat_prioritas('u') . ")", $tp, $pv)[0]['n'];
$w .= ' AND ' . syarat_prioritas('u');

$nama_kec = '';
if ($kec > 0) {
    $nama_kec = jalankan($koneksi, "SELECT nama FROM tb_kecamatan WHERE id_kecamatan = ?", 'i', [$kec])[0]['nama'] ?? '';
}
$label_wilayah = $kec > 0
    ? ($desa !== '' ? 'Desa ' . $desa . ', Kecamatan ' . $nama_kec : 'Kecamatan ' . $nama_kec)
    : 'Seluruh kecamatan';

/* Ringkasan utama */
$ringkas = jalankan($koneksi, "
    SELECT COUNT(*) AS jumlah,
           COALESCE(SUM(estimasi_anggaran),0) AS anggaran,
           COALESCE(AVG(total_nilai),0) AS rata,
           SUM(CASE WHEN total_nilai >= 80 THEN 1 ELSE 0 END) AS prioritas,
           SUM(CASE WHEN status = 'Terverifikasi' THEN 1 ELSE 0 END) AS terverifikasi,
           SUM(CASE WHEN total_nilai IS NULL THEN 1 ELSE 0 END) AS belum_dinilai
    FROM tb_usulan u WHERE $w", $tp, $pv)[0];

/* Sebaran kategori, hanya kategori yang terisi */
$kategori = jalankan($koneksi, "
    SELECT k.nama, k.warna, COUNT(u.id_usulan) AS jumlah
    FROM tb_kategori k
    JOIN tb_usulan u ON u.id_kategori = k.id_kategori AND $w
    GROUP BY k.id_kategori ORDER BY k.batas_bawah DESC", $tp, $pv);

/* Sebaran sektor, hanya sektor yang terisi */
$sektor = jalankan($koneksi, "
    SELECT s.nama, s.warna, COUNT(u.id_usulan) AS jumlah,
           COALESCE(AVG(u.total_nilai),0) AS rata,
           COALESCE(SUM(u.estimasi_anggaran),0) AS anggaran
    FROM tb_sektor s
    JOIN tb_usulan u ON u.id_sektor = s.id_sektor AND $w
    GROUP BY s.id_sektor ORDER BY jumlah DESC, s.nama", $tp, $pv);

/* Sebaran wilayah: per kecamatan bila belum memilih kecamatan, per desa bila sudah */
if ($kec > 0) {
    $judul_wilayah = 'Usulan per desa di Kecamatan ' . $nama_kec;
    $ket_wilayah   = 'Hanya desa di kecamatan ini yang ditampilkan';
    $wilayah = jalankan($koneksi, "
        SELECT u.desa AS nama, COUNT(u.id_usulan) AS jumlah
        FROM tb_usulan u WHERE $w
        GROUP BY u.desa ORDER BY jumlah DESC, u.desa", $tp, $pv);
} else {
    $judul_wilayah = 'Usulan per kecamatan';
    $ket_wilayah   = 'Dua belas kecamatan dengan usulan terbanyak';
    $wilayah = jalankan($koneksi, "
        SELECT kec.nama, COUNT(u.id_usulan) AS jumlah
        FROM tb_kecamatan kec
        JOIN tb_usulan u ON u.id_kecamatan = kec.id_kecamatan AND $w
        GROUP BY kec.id_kecamatan ORDER BY jumlah DESC, kec.nama LIMIT 12", $tp, $pv);
}

/* Sebaran OPD */
$opd = jalankan($koneksi, "
    SELECT o.nama, COUNT(u.id_usulan) AS jumlah, COALESCE(AVG(u.total_nilai),0) AS rata
    FROM tb_opd o
    JOIN tb_usulan u ON u.id_opd = o.id_opd AND $w
    GROUP BY o.id_opd ORDER BY jumlah DESC, o.nama LIMIT 8", $tp, $pv);

/* Usulan bernilai tertinggi, dengan penyaring tambahan */
$t_sektor   = (int)($_GET['t_sektor'] ?? 0);
$t_opd      = (int)($_GET['t_opd'] ?? 0);
$t_kategori = (int)($_GET['t_kategori'] ?? 0);
$t_jumlah   = (int)($_GET['t_jumlah'] ?? 10);
if (!in_array($t_jumlah, [5, 10, 25, 50], true)) {
    $t_jumlah = 10;
}

$syarat_t = [$w, 'u.total_nilai IS NOT NULL'];
$tipe_t   = $tp;
$nilai_t  = $pv;
foreach ([
    'u.id_sektor'    => $t_sektor,
    'u.id_opd'       => $t_opd,
    'u.id_kategori'  => $t_kategori,
] as $kolom => $nilai_filter) {
    if ($nilai_filter > 0) {
        $syarat_t[] = $kolom . ' = ?';
        $tipe_t    .= 'i';
        $nilai_t[]  = $nilai_filter;
    }
}

$teratas = jalankan($koneksi, SQL_USULAN_DASAR . "
    WHERE " . implode(' AND ', $syarat_t) . "
    ORDER BY u.total_nilai DESC, u.estimasi_anggaran ASC LIMIT " . $t_jumlah,
    $tipe_t, $nilai_t);

$ref_kecamatan = ambil_kecamatan_pilihan($koneksi);
$ref_sektor    = ambil_daftar($koneksi, 'tb_sektor', 'id_sektor');
$ref_opd       = ambil_daftar($koneksi, 'tb_opd', 'id_opd');
$ref_kategori  = ambil_kategori($koneksi);

/* Pilihan desa: dari master; bila master kosong, dari desa yang pernah diinput */
$peta_desa = ambil_peta_desa($koneksi);
foreach (jalankan($koneksi, "SELECT DISTINCT id_kecamatan, desa FROM tb_usulan ORDER BY desa") as $r) {
    $ik = (int)$r['id_kecamatan'];
    if (empty($peta_desa[$ik]) || !in_array($r['desa'], $peta_desa[$ik], true)) {
        $peta_desa[$ik][] = $r['desa'];
    }
}
foreach ($peta_desa as &$daftar_d) { sort($daftar_d, SORT_NATURAL | SORT_FLAG_CASE); }
unset($daftar_d);

$daftar_tahun = jalankan($koneksi, "SELECT DISTINCT tahun FROM tb_usulan ORDER BY tahun DESC");

/* Pengingat penilaian untuk akun desa */
$tugas_desa = null;
if (desa_user() > 0) {
    $tugas_desa = jalankan($koneksi, "SELECT COUNT(*) AS terbuka,
            COALESCE(SUM(EXISTS (SELECT 1 FROM tb_penilaian p WHERE p.id_usulan = u.id_usulan AND p.id_desa_penilai = ?)), 0) AS sudah
        FROM tb_usulan u
        WHERE u.tahun = ? AND u.id_kecamatan = ? AND u.desa <> ? AND u.status = 'Draft' AND " . syarat_prioritas('u') . "",
        'iiis', [desa_user(), $tahun, kec_user(), nama_desa_user()])[0];
}
$bobot_total  = total_bobot($koneksi);

/* Parameter wilayah untuk tautan ke halaman lain */
$q_wilayah = http_build_query(array_filter([
    'tahun' => $tahun, 'kecamatan' => $kec ?: null, 'desa' => $desa ?: null,
]));

/* Tinggi wadah grafik batang mengikuti jumlah baris data */
$tinggi_sektor  = max(180, count($sektor) * 34 + 30);
$tinggi_wilayah = max(180, count($wilayah) * 30 + 30);
$tinggi_opd     = max(180, count($opd) * 34 + 30);
?>

<form method="get" class="mb-5 flex flex-wrap items-end gap-3 cetak-sembunyi">
  <div>
    <label class="block text-xs text-slate-600 mb-1">Tahun rencana</label>
    <select name="tahun" class="isian" style="min-width:140px">
      <?php
      $opsi_tahun = array_map('intval', array_column($daftar_tahun, 'tahun'));
      if (!in_array(APP_TAHUN_DEFAULT, $opsi_tahun, true)) { $opsi_tahun[] = APP_TAHUN_DEFAULT; }
      if (!in_array($tahun, $opsi_tahun, true)) { $opsi_tahun[] = $tahun; }
      rsort($opsi_tahun);
      foreach ($opsi_tahun as $t): ?>
        <option value="<?= $t ?>" <?= $t === $tahun ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="block text-xs text-slate-600 mb-1">Kecamatan</label>
    <?php if (kec_user() > 0): ?>
      <input type="hidden" name="kec" id="d_kec" value="<?= $kec ?>">
      <input class="isian bg-slate-50" style="min-width:180px" value="<?= e($nama_kec) ?>" disabled>
    <?php else: ?>
      <select name="kec" id="d_kec" class="isian" style="min-width:180px">
        <option value="0">Semua kecamatan</option>
        <?php foreach ($ref_kecamatan as $r): ?>
          <option value="<?= $r['id_kecamatan'] ?>" <?= $kec === (int)$r['id_kecamatan'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
  </div>
  <div>
    <label class="block text-xs text-slate-600 mb-1">Desa/kelurahan</label>
    <select name="desa" id="d_desa" class="isian" style="min-width:180px" data-terpilih="<?= e($desa) ?>"></select>
  </div>
  <button class="tombol">Terapkan</button>
  <a href="dashboard.php" class="tombol-2">Atur ulang</a>
  <?php if (peran() !== 'desa'): ?><a href="usulan_form.php" class="tombol-2">Input usulan baru</a><?php endif; ?>
  <a href="ranking.php?<?= e($q_wilayah) ?>" class="tombol-2">Lihat peringkat lengkap</a>
</form>
<script>
  pasangDesa('d_kec', 'd_desa', <?= json_encode($peta_desa, JSON_UNESCAPED_UNICODE) ?>,
             'Semua desa', 'Pilih kecamatan dahulu');
</script>

<p class="text-sm text-slate-600 mb-4">
  Menampilkan <?= JUMLAH_PRIORITAS_DESA ?> usulan prioritas setiap desa untuk <b class="text-slate-900"><?= e($label_wilayah) ?></b>, tahun <?= $tahun ?>.
  <?php if ($tersembunyi): ?>
    <a href="usulan.php?<?= e($q_wilayah) ?>&amp;prioritas=bukan" class="underline" style="color:#44403B"><?= $tersembunyi ?> usulan cadangan</a> tidak ikut dihitung.
  <?php endif; ?>
</p>

<div id="langsung-dashboard" data-langsung>
<?php if ($tugas_desa): $sisa_tugas = (int)$tugas_desa['terbuka'] - (int)$tugas_desa['sudah']; ?>
  <div class="kartu p-4 mb-5 flex flex-wrap items-center justify-between gap-3"
       style="<?= $sisa_tugas > 0 ? 'border-color:#F59E0B;background:#FFFBEB' : '' ?>">
    <div>
      <p class="font-semibold text-slate-900">
        <?= $sisa_tugas > 0
            ? $sisa_tugas . ' usulan desa lain menunggu penilaian dari ' . e(nama_desa_user())
            : 'Semua usulan terbuka sudah dinilai oleh ' . e(nama_desa_user()) ?>
      </p>
      <p class="text-xs text-slate-600">Sudah menilai <?= (int)$tugas_desa['sudah'] ?> dari <?= (int)$tugas_desa['terbuka'] ?> usulan terbuka di kecamatan Anda, tahun <?= $tahun ?>.</p>
    </div>
    <a href="penilaian.php?tahun=<?= $tahun ?>" class="tombol"><?= $sisa_tugas > 0 ? 'Mulai menilai' : 'Lihat penilaian' ?></a>
  </div>
<?php endif; ?>
<?php if (abs($bobot_total - 100) > 0.01): ?>
  <div class="mb-5 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
    Total bobot kriteria saat ini <?= angka($bobot_total) ?>, bukan 100. Nilai akhir tidak berskala 0 sampai 100
    sampai bobot diperbaiki pada halaman Bobot kriteria.
  </div>
<?php endif; ?>

<?php if ((int)$ringkas['belum_dinilai'] > 0): ?>
  <div class="mb-5 rounded-lg bg-sky-50 border border-sky-200 px-4 py-3 text-sm text-sky-900 flex flex-wrap items-center justify-between gap-3">
    <span><?= number_format($ringkas['belum_dinilai'], 0, ',', '.') ?> usulan belum diberi skor,
      sehingga belum masuk grafik kategori dan peringkat.</span>
    <a href="usulan.php?<?= e($q_wilayah) ?>&penilaian=belum" class="tombol">Buka antrean penilaian</a>
  </div>
<?php endif; ?>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
  <div class="kartu p-4">
    <p class="text-xs text-slate-500">Jumlah usulan</p>
    <p class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($ringkas['jumlah'], 0, ',', '.') ?></p>
    <p class="text-xs text-slate-500 mt-1"><?= number_format($ringkas['terverifikasi'], 0, ',', '.') ?> sudah diverifikasi</p>
  </div>
  <div class="kartu p-4">
    <p class="text-xs text-slate-500">Total estimasi anggaran</p>
    <p class="text-2xl font-bold text-slate-900 mt-1 break-words"><?= rupiah($ringkas['anggaran']) ?></p>
    <p class="text-xs text-slate-500 mt-1">Akumulasi usulan tahun <?= $tahun ?></p>
  </div>
  <div class="kartu p-4">
    <p class="text-xs text-slate-500">Rata-rata nilai</p>
    <p class="text-2xl font-bold text-slate-900 mt-1"><?= angka($ringkas['rata']) ?></p>
    <p class="text-xs text-slate-500 mt-1">Skala 0 sampai 100</p>
  </div>
  <div class="kartu p-4">
    <p class="text-xs text-slate-500">Nilai 80 ke atas</p>
    <p class="text-2xl font-bold mt-1" style="color:#44403B"><?= number_format($ringkas['prioritas'], 0, ',', '.') ?></p>
    <p class="text-xs text-slate-500 mt-1">
      Dari <?= number_format($ringkas['jumlah'] - $ringkas['belum_dinilai'], 0, ',', '.') ?> usulan yang sudah dinilai
    </p>
  </div>
</div>

<?php if ((int)$ringkas['jumlah'] === 0): ?>

  <div class="kartu p-10 text-center">
    <p class="text-slate-700 font-medium">Belum ada usulan untuk <?= e($label_wilayah) ?> pada tahun <?= $tahun ?>.</p>
    <p class="text-sm text-slate-500 mt-1 mb-4">Mulai dengan menginput satu usulan atau mengimpor data dari lembar kerja Excel.</p>
    <?php if (peran() !== 'desa'): ?><a href="usulan_form.php" class="tombol">Input usulan baru</a><?php endif; ?>
  </div>

<?php else: ?>

<div class="grid gap-4 lg:grid-cols-5 mb-4">
  <div class="kartu p-4 lg:col-span-2">
    <p class="font-semibold text-sm text-slate-800">Sebaran kategori prioritas</p>
    <p class="text-xs text-slate-500 mb-3">Berdasarkan nilai akhir tiap usulan</p>
    <?php if ($kategori): ?>
      <div class="relative" style="height:260px">
        <canvas id="grafikKategori"></canvas>
      </div>
    <?php else: ?>
      <div class="flex flex-col items-center justify-center text-center" style="height:260px">
        <p class="text-sm text-slate-600">Belum ada usulan yang diberi skor.</p>
        <p class="text-xs text-slate-500 mt-1 mb-3">Kategori prioritas muncul setelah penilaian kriteria diisi.</p>
        <a href="usulan.php?<?= e($q_wilayah) ?>&penilaian=belum" class="tombol-2">Mulai menilai</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="kartu p-4 lg:col-span-3">
    <p class="font-semibold text-sm text-slate-800">Jumlah usulan per sektor</p>
    <p class="text-xs text-slate-500 mb-3">Diurutkan dari sektor dengan usulan terbanyak</p>
    <div class="relative" style="height:<?= $tinggi_sektor ?>px">
      <canvas id="grafikSektor"></canvas>
    </div>
  </div>
</div>

<div class="grid gap-4 lg:grid-cols-2 mb-6">
  <div class="kartu p-4">
    <p class="font-semibold text-sm text-slate-800"><?= e($judul_wilayah) ?></p>
    <p class="text-xs text-slate-500 mb-3"><?= e($ket_wilayah) ?></p>
    <div class="relative" style="height:<?= $tinggi_wilayah ?>px">
      <canvas id="grafikWilayah"></canvas>
    </div>
  </div>

  <div class="kartu p-4">
    <p class="font-semibold text-sm text-slate-800">Beban usulan per OPD</p>
    <p class="text-xs text-slate-500 mb-3">Delapan OPD dengan usulan terbanyak</p>
    <div class="relative" style="height:<?= $tinggi_opd ?>px">
      <canvas id="grafikOpd"></canvas>
    </div>
  </div>
</div>

<div class="kartu overflow-hidden">
  <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
    <p class="font-semibold text-sm text-slate-800"><?= $t_jumlah ?> usulan bernilai tertinggi</p>
    <a href="ranking.php?<?= e($q_wilayah) ?>&sektor=<?= $t_sektor ?: '' ?>&kategori=<?= $t_kategori ?: '' ?>"
       class="text-xs" style="color:#44403B">Peringkat lengkap</a>
  </div>

  <form method="get" class="px-4 py-3 border-b border-slate-200 bg-slate-50 cetak-sembunyi">
    <input type="hidden" name="tahun" value="<?= $tahun ?>">
    <input type="hidden" name="kec" value="<?= $kec ?>">
    <input type="hidden" name="desa" value="<?= e($desa) ?>">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
      <div>
        <label class="block text-xs text-slate-600 mb-1">Sektor</label>
        <select name="t_sektor" class="isian">
          <option value="0">Semua</option>
          <?php foreach ($ref_sektor as $r): ?>
            <option value="<?= $r['id_sektor'] ?>" <?= $t_sektor === (int)$r['id_sektor'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1">OPD</label>
        <select name="t_opd" class="isian">
          <option value="0">Semua</option>
          <?php foreach ($ref_opd as $r): ?>
            <option value="<?= $r['id_opd'] ?>" <?= $t_opd === (int)$r['id_opd'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1">Kategori</label>
        <select name="t_kategori" class="isian">
          <option value="0">Semua</option>
          <?php foreach ($ref_kategori as $r): ?>
            <option value="<?= $r['id_kategori'] ?>" <?= $t_kategori === (int)$r['id_kategori'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1">Tampilkan</label>
        <select name="t_jumlah" class="isian">
          <?php foreach ([5, 10, 25, 50] as $n): ?>
            <option value="<?= $n ?>" <?= $t_jumlah === $n ? 'selected' : '' ?>><?= $n ?> baris</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="flex flex-wrap gap-2 mt-3">
      <button class="tombol">Terapkan</button>
      <a href="dashboard.php?tahun=<?= $tahun ?>&kec=<?= $kec ?>&desa=<?= urlencode($desa) ?>" class="tombol-2">Atur ulang</a>
    </div>
  </form>
  <?php if ($teratas): ?>
  <div class="overflow-x-auto">
    <table class="data w-full">
      <thead>
        <tr><th>#</th><th>Usulan</th><th>Lokasi</th><th>Sektor</th><th>OPD</th>
            <th class="text-right">Anggaran</th><th class="text-right">Nilai</th><th>Kategori</th></tr>
      </thead>
      <tbody>
      <?php foreach ($teratas as $i => $t): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><a class="font-medium" style="color:#44403B" href="usulan_detail.php?id=<?= (int)$t['id_usulan'] ?>"><?= e($t['nama_usulan']) ?></a>
              <div class="text-xs text-slate-500"><?= e($t['kode_usulan']) ?></div></td>
          <td><?= e($t['desa']) ?><div class="text-xs text-slate-500">Kec. <?= e($t['kecamatan']) ?></div></td>
          <td><?= e($t['sektor']) ?></td>
          <td><?= e($t['opd']) ?></td>
          <td class="text-right whitespace-nowrap"><?= rupiah($t['estimasi_anggaran']) ?></td>
          <td class="text-right font-semibold"><?= angka($t['total_nilai']) ?></td>
          <td><span class="lencana" style="background:<?= e($t['warna_kategori'] ?: '#64748B') ?>"><?= e($t['kategori'] ?: 'Belum dinilai') ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="text-sm text-slate-500 p-6">Tidak ada usulan yang cocok dengan penyaring ini,
      atau usulan yang ada belum diberi skor.</p>
  <?php endif; ?>
</div>

<script>
(function () {
const dataKategori = <?= json_encode($kategori, JSON_UNESCAPED_UNICODE) ?>;
const dataSektor   = <?= json_encode($sektor, JSON_UNESCAPED_UNICODE) ?>;
const dataWilayah  = <?= json_encode($wilayah, JSON_UNESCAPED_UNICODE) ?>;
const dataOpd      = <?= json_encode($opd, JSON_UNESCAPED_UNICODE) ?>;

Chart.defaults.font.family = 'ui-sans-serif, system-ui, Segoe UI, Roboto, Arial, sans-serif';
Chart.defaults.font.size = 11;
Chart.defaults.color = '#475569';

/* Pengaturan dasar batang mendatar: satu label per baris, tanpa tumpang tindih */
function opsiBatangMendatar(satuanLabel) {
  return {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    layout: { padding: { right: 12 } },
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: function (k) { return k.parsed.x + ' ' + satuanLabel; }
        }
      }
    },
    scales: {
      x: { beginAtZero: true, ticks: { precision: 0, stepSize: 1 },
           grid: { color: '#F1F5F9' }, border: { display: false } },
      y: { grid: { display: false }, border: { display: false },
           ticks: { autoSkip: false, crossAlign: 'far' } }
    },
    barThickness: 18
  };
}

if (dataKategori.length) {
  new Chart(document.getElementById('grafikKategori'), {
    type: 'doughnut',
    data: {
      labels: dataKategori.map(d => d.nama),
      datasets: [{
        data: dataKategori.map(d => Number(d.jumlah)),
        backgroundColor: dataKategori.map(d => d.warna),
        borderColor: '#fff', borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '60%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: { boxWidth: 10, boxHeight: 10, padding: 12, usePointStyle: true, pointStyle: 'circle' }
        },
        tooltip: {
          callbacks: {
            label: function (k) {
              const total = k.dataset.data.reduce((a, b) => a + b, 0);
              const persen = total ? Math.round(k.parsed / total * 100) : 0;
              return k.label + ': ' + k.parsed + ' usulan (' + persen + '%)';
            }
          }
        }
      }
    }
  });
}

if (dataSektor.length) {
  new Chart(document.getElementById('grafikSektor'), {
    type: 'bar',
    data: {
      labels: dataSektor.map(d => d.nama),
      datasets: [{
        data: dataSektor.map(d => Number(d.jumlah)),
        backgroundColor: dataSektor.map(d => d.warna),
        borderRadius: 3
      }]
    },
    options: opsiBatangMendatar('usulan')
  });
}

if (dataWilayah.length) {
  new Chart(document.getElementById('grafikWilayah'), {
    type: 'bar',
    data: {
      labels: dataWilayah.map(d => d.nama),
      datasets: [{ data: dataWilayah.map(d => Number(d.jumlah)), backgroundColor: '#44403B', borderRadius: 3 }]
    },
    options: opsiBatangMendatar('usulan')
  });
}

if (dataOpd.length) {
  new Chart(document.getElementById('grafikOpd'), {
    type: 'bar',
    data: {
      labels: dataOpd.map(d => d.nama),
      datasets: [{ data: dataOpd.map(d => Number(d.jumlah)), backgroundColor: '#211F1C', borderRadius: 3 }]
    },
    options: opsiBatangMendatar('usulan')
  });
}
})();
</script>

<?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>