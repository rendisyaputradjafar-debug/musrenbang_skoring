<?php
/**
 * Jelajah usulan (publik).
 * Menampilkan semua usulan yang diinput kecamatan, lengkap dengan nilai rata-rata
 * dan progres penilaian antar-desa. Dapat ditampilkan sebagai kartu atau tabel.
 */
require_once __DIR__ . '/includes/fungsi_publik.php';

$daftar_tahun = daftar_tahun_publik($koneksi);
$tahun   = (int)($_GET['tahun'] ?? APP_TAHUN_DEFAULT);
$kec     = (int)($_GET['kec'] ?? 0);
$desa    = $kec > 0 ? trim((string)($_GET['desa'] ?? '')) : '';
$sektor  = (int)($_GET['sektor'] ?? 0);
$kat     = (string)($_GET['kat'] ?? '');
$cari    = trim((string)($_GET['cari'] ?? ''));
$gerbang = (int)($_GET['gerbang'] ?? 0);
$urut    = (string)($_GET['urut'] ?? 'nilai');
$tampil  = ($_GET['tampilan'] ?? 'kartu') === 'tabel' ? 'tabel' : 'kartu';
$hal     = max(1, (int)($_GET['hal'] ?? 1));
$per     = $tampil === 'tabel' ? 50 : 24;

$ref_kecamatan = ambil_daftar($koneksi, 'tb_kecamatan', 'id_kecamatan');
$ref_sektor    = ambil_daftar($koneksi, 'tb_sektor', 'id_sektor');
$ref_kategori  = ambil_kategori($koneksi);
$ref_gerbang   = jalankan($koneksi, "SELECT id_gerbang, kode, nama FROM tb_gerbang WHERE aktif = 1 ORDER BY urut");
$nama_gerbang  = '';
foreach ($ref_gerbang as $g) {
    if ((int)$g['id_gerbang'] === $gerbang) { $nama_gerbang = $g['nama']; }
}
if ($nama_gerbang === '') { $gerbang = 0; }
$peta_desa     = peta_desa_publik($koneksi, $tahun);

$nama_kec = '';
foreach ($ref_kecamatan as $r) {
    if ((int)$r['id_kecamatan'] === $kec) { $nama_kec = $r['nama']; }
}
if ($nama_kec === '') { $kec = 0; $desa = ''; }

/* ---------------- Penyaring ---------------- */
$syarat = [SYARAT_PUBLIK];
$tipe   = 'i';
$nilai  = [$tahun];
if ($kec > 0)     { $syarat[] = 'u.id_kecamatan = ?'; $tipe .= 'i'; $nilai[] = $kec; }
if ($desa !== '') { $syarat[] = 'u.desa = ?';         $tipe .= 's'; $nilai[] = $desa; }
if ($sektor > 0)  { $syarat[] = 'u.id_sektor = ?';    $tipe .= 'i'; $nilai[] = $sektor; }
if ($gerbang > 0) { $syarat[] = 'u.id_gerbang = ?';   $tipe .= 'i'; $nilai[] = $gerbang; }
if ($cari !== '') {
    $syarat[] = '(u.nama_usulan LIKE ? OR u.uraian_masalah LIKE ? OR u.desa LIKE ?)';
    $tipe .= 'sss';
    $kata = '%' . $cari . '%';
    array_push($nilai, $kata, $kata, $kata);
}
/* Syarat sebelum penyaring kategori, dipakai untuk menghitung jumlah per kategori */
$where_dasar = implode(' AND ', $syarat);
$tipe_dasar  = $tipe;
$nilai_dasar = $nilai;

if ($kat === 'menunggu') {
    $syarat[] = 'u.total_nilai IS NULL';
} elseif ((int)$kat > 0) {
    $syarat[] = 'u.id_kategori = ? AND u.total_nilai IS NOT NULL';
    $tipe .= 'i';
    $nilai[] = (int)$kat;
} else {
    $kat = '';
}
$where = implode(' AND ', $syarat);

$pilihan_urut = [
    'nilai'    => ['Nilai tertinggi dulu',        '(u.total_nilai IS NULL), u.total_nilai DESC, u.estimasi_anggaran ASC'],
    'terbaru'  => ['Terbaru diinput',        'u.dibuat_pada DESC, u.id_usulan DESC'],
    'progres'  => ['Paling sedikit dinilai', 'u.jumlah_penilai ASC, u.dibuat_pada ASC'],
    'anggaran' => ['Anggaran terkecil',      'u.estimasi_anggaran ASC, u.total_nilai DESC'],
    'nama'     => ['Nama usulan (A-Z)',      'u.nama_usulan ASC'],
];
if (!isset($pilihan_urut[$urut])) { $urut = 'nilai'; }

/* ---------------- Ringkasan untuk penyaring aktif ---------------- */
$ringkas = jalankan($koneksi, "
    SELECT COUNT(*) AS jumlah,
           COALESCE(SUM(u.total_nilai IS NOT NULL), 0) AS bernilai,
           COALESCE(SUM(u.total_nilai IS NULL), 0) AS menunggu,
           COALESCE(SUM(u.estimasi_anggaran), 0) AS anggaran,
           AVG(u.total_nilai) AS rata
    FROM tb_usulan u WHERE $where_dasar", $tipe_dasar, $nilai_dasar)[0];

$per_kategori = [];
foreach (jalankan($koneksi, "SELECT u.id_kategori, COUNT(*) AS n FROM tb_usulan u
    WHERE $where_dasar AND u.total_nilai IS NOT NULL GROUP BY u.id_kategori", $tipe_dasar, $nilai_dasar) as $r) {
    $per_kategori[(int)$r['id_kategori']] = (int)$r['n'];
}

$total = (int)jalankan($koneksi, "SELECT COUNT(*) AS n FROM tb_usulan u WHERE $where", $tipe, $nilai)[0]['n'];
$jumlah_hal = max(1, (int)ceil($total / $per));
$hal = min($hal, $jumlah_hal);
$mulai = ($hal - 1) * $per;

/* Peringkat di kecamatan dihitung terhadap usulan publik sekecamatan pada tahun yang sama */
$sql_daftar = str_replace('SELECT u.*,', 'SELECT u.*, ' . SQL_TARGET_PENILAI . " AS target,
        CASE WHEN u.total_nilai IS NULL THEN NULL ELSE
            (SELECT COUNT(*) + 1 FROM tb_usulan r
             WHERE r.tahun = u.tahun AND r.id_kecamatan = u.id_kecamatan AND r.status <> 'Ditolak'
               AND " . syarat_prioritas('r') . " AND r.total_nilai > u.total_nilai) END AS peringkat_kec,", SQL_USULAN_DASAR);
$daftar = jalankan($koneksi, $sql_daftar . " WHERE $where ORDER BY " . $pilihan_urut[$urut][1]
    . " LIMIT $mulai, $per", $tipe, $nilai);

function tautan_jelajah(array $ubah): string
{
    global $tahun, $kec, $desa, $sektor, $gerbang, $kat, $cari, $urut, $tampil, $hal;
    $q = array_merge([
        'tahun' => $tahun, 'kec' => $kec ?: null, 'desa' => $desa, 'sektor' => $sektor ?: null, 'gerbang' => $gerbang ?: null,
        'kat' => $kat, 'cari' => $cari, 'urut' => $urut !== 'nilai' ? $urut : null,
        'tampilan' => $tampil !== 'kartu' ? $tampil : null, 'hal' => $hal > 1 ? $hal : null,
    ], $ubah);
    $q = array_filter($q, function ($v) { return $v !== null && $v !== ''; });
    return 'lihat.php?' . http_build_query($q) . '#hasil';
}

$judul_wilayah = $kec ? ($desa !== '' ? 'Desa ' . $desa . ', Kecamatan ' . $nama_kec : 'Kecamatan ' . $nama_kec) : 'Seluruh Kabupaten Banggai';
$ada_saring = $kec || $sektor || $gerbang || $kat !== '' || $cari !== '';

$judul_publik = 'Lihat usulan';
$menu_publik_aktif = 'jelajah';
$jeda_publik = 8000;
require __DIR__ . '/includes/publik_atas.php';
?>

<section class="kepala-hal">
  <div class="wadah pt-10 pb-20" id="isi-utama">
    <p class="text-sm font-medium" style="color:#A9A49A">Tahun rencana <?= $tahun ?></p>
    <h1 class="text-3xl md:text-4xl mt-1">Lihat usulan prioritas</h1>
    <p class="mt-3 max-w-2xl text-lg leading-relaxed" style="color:#D7D3CA">
<?= JUMLAH_PRIORITAS_DESA ?> usulan prioritas dari setiap desa, dinilai oleh desa-desa tetangga.
      Angka di lingkaran adalah nilainya (0 sampai 100): makin tinggi, makin dibutuhkan.
    </p>
  </div>
</section>

<main class="wadah -mt-12 pb-16 relative z-10">

  <form method="get" action="lihat.php#hasil" class="bar-saring p-4 md:p-5 mb-8" role="search">
    <div class="grid gap-3 md:grid-cols-4 items-end">
      <div>
        <label for="j_kec">Kecamatan</label>
        <select id="j_kec" name="kec" class="isian">
          <option value="0">Semua kecamatan</option>
          <?php foreach ($ref_kecamatan as $r): ?>
            <option value="<?= $r['id_kecamatan'] ?>" <?= $kec === (int)$r['id_kecamatan'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="j_desa">Desa/kelurahan</label>
        <select id="j_desa" name="desa" class="isian" data-terpilih="<?= e($desa) ?>"></select>
      </div>
      <div>
        <label for="j_cari">Cari usulan</label>
        <input id="j_cari" name="cari" type="search" value="<?= e($cari) ?>" class="isian" placeholder="Contoh: jalan, air bersih">
      </div>
      <button class="tombol w-full">Tampilkan</button>
    </div>

    <details class="mt-3" <?= ($sektor || $gerbang || $urut !== 'nilai' || $tahun !== APP_TAHUN_DEFAULT) ? 'open' : '' ?>>
      <summary class="cursor-pointer text-sm font-semibold" style="color:var(--laut)">Penyaring lainnya</summary>
      <div class="grid gap-3 md:grid-cols-4 mt-3">
        <div>
          <label for="j_sektor">Bidang</label>
          <select id="j_sektor" name="sektor" class="isian">
            <option value="">Semua bidang</option>
            <?php foreach ($ref_sektor as $r): ?>
              <option value="<?= $r['id_sektor'] ?>" <?= $sektor === (int)$r['id_sektor'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="j_gerbang">Program GERBANG</label>
          <select id="j_gerbang" name="gerbang" class="isian">
            <option value="">Semua program</option>
            <?php foreach ($ref_gerbang as $g): ?>
              <option value="<?= (int)$g['id_gerbang'] ?>" <?= $gerbang === (int)$g['id_gerbang'] ? 'selected' : '' ?>><?= e($g['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="j_urut">Urutkan</label>
          <select id="j_urut" name="urut" class="isian">
            <?php foreach ($pilihan_urut as $k => $v): ?>
              <option value="<?= $k ?>" <?= $urut === $k ? 'selected' : '' ?>><?= $v[0] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="j_tahun">Tahun</label>
          <select id="j_tahun" name="tahun" class="isian">
            <?php foreach ($daftar_tahun as $t): ?>
              <option value="<?= $t ?>" <?= $t === $tahun ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </details>
    <?php if ($kat !== ''): ?><input type="hidden" name="kat" value="<?= e($kat) ?>"><?php endif; ?>
    <?php if ($tampil !== 'kartu'): ?><input type="hidden" name="tampilan" value="<?= e($tampil) ?>"><?php endif; ?>
    <?php if ($ada_saring || $urut !== 'nilai'): ?>
      <a href="lihat.php?tahun=<?= $tahun ?>#hasil" class="inline-block text-sm mt-3" style="color:var(--redup)">Hapus semua penyaring</a>
    <?php endif; ?>
  </form>

  <div id="hasil" class="scroll-mt-20" data-langsung>

    <div class="flex flex-wrap items-end justify-between gap-4 mb-4">
      <div>
        <h2 class="text-xl font-extrabold tracking-tight"><?= e($judul_wilayah) ?><?= $nama_gerbang !== '' ? ', ' . e($nama_gerbang) : '' ?></h2>
        <p class="text-sm mt-1" style="color:var(--redup)">
          <?= number_format((int)$ringkas['jumlah'], 0, ',', '.') ?> usulan prioritas.
          <?= number_format((int)$ringkas['bernilai'], 0, ',', '.') ?> sudah dinilai,
          <?= number_format((int)$ringkas['menunggu'], 0, ',', '.') ?> masih menunggu.
        </p>
      </div>
      <div class="saklar inline-flex p-1 rounded-lg cetak-sembunyi" style="background:#ECEAE3" aria-label="Pilihan tampilan">
        <a href="<?= e(tautan_jelajah(['tampilan' => null, 'hal' => null])) ?>" class="<?= $tampil === 'kartu' ? 'aktif' : '' ?>" <?= $tampil === 'kartu' ? 'aria-current="true"' : '' ?>>Kartu</a>
        <a href="<?= e(tautan_jelajah(['tampilan' => 'tabel', 'hal' => null])) ?>" class="<?= $tampil === 'tabel' ? 'aktif' : '' ?>" <?= $tampil === 'tabel' ? 'aria-current="true"' : '' ?>>Tabel</a>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-6 cetak-sembunyi" aria-label="Saring menurut kategori">
      <a href="<?= e(tautan_jelajah(['kat' => null, 'hal' => null])) ?>" class="pil <?= $kat === '' ? 'aktif' : '' ?>">Semua <span><?= (int)$ringkas['jumlah'] ?></span></a>
      <?php foreach ($ref_kategori as $k): $n = $per_kategori[(int)$k['id_kategori']] ?? 0; ?>
        <a href="<?= e(tautan_jelajah(['kat' => (int)$k['id_kategori'], 'hal' => null])) ?>"
           class="pil <?= $kat === (string)(int)$k['id_kategori'] ? 'aktif' : '' ?>">
          <span class="w-2 h-2 rounded-full inline-block" style="background:<?= e($k['warna']) ?>"></span>
          <?= e($k['nama']) ?> <span><?= $n ?></span>
        </a>
      <?php endforeach; ?>
      <a href="<?= e(tautan_jelajah(['kat' => 'menunggu', 'hal' => null])) ?>" class="pil <?= $kat === 'menunggu' ? 'aktif' : '' ?>">
        <span class="w-2 h-2 rounded-full inline-block" style="background:#F59E0B"></span>
        Menunggu penilaian <span><?= (int)$ringkas['menunggu'] ?></span>
      </a>
    </div>

    <?php if (!$daftar): ?>
      <div class="kartu p-10 text-center">
        <p class="font-semibold">Belum ada usulan yang cocok.</p>
        <p class="text-sm mt-1" style="color:var(--redup)">
          <?= $ada_saring ? 'Coba ubah atau hapus penyaring.' : 'Kecamatan belum menginput usulan untuk tahun ' . $tahun . '.' ?>
        </p>
      </div>

    <?php elseif ($tampil === 'kartu'): ?>
      <ul class="grid-usulan">
        <?php foreach ($daftar as $u):
            $bernilai = $u['total_nilai'] !== null;
            $target = (int)$u['target'];
            $persen = $target > 0 ? min(100, round((int)$u['jumlah_penilai'] / $target * 100)) : 0;
            $pk = $u['peringkat_kec']; ?>
          <li class="kartu-usulan">
            <?php if ($pk !== null && $urut === 'nilai'): ?>
              <span class="nomor-urut <?= (int)$pk === 1 ? 'emas' : '' ?>">#<?= (int)$pk ?> di Kec. <?= e($u['kecamatan']) ?></span>
            <?php endif; ?>
            <div class="atas">
              <div class="min-w-0">
                <h3><a href="lihat_usulan.php?id=<?= (int)$u['id_usulan'] ?>"><?= e($u['nama_usulan']) ?></a></h3>
                <p class="lokasi"><?= e($u['desa']) ?>, Kec. <?= e($u['kecamatan']) ?></p>
                <p class="lokasi">Prioritas ke-<?= (int)$u['peringkat_desa'] ?> desa ini</p>
              </div>
              <?php if ($bernilai): ?>
                <div class="cincin" style="--p:<?= max(0, min(100, (float)$u['total_nilai'])) ?>;--warna:<?= e($u['warna_kategori'] ?: '#64748B') ?>"
                     role="img" aria-label="Nilai <?= angka($u['total_nilai']) ?> dari 100">
                  <span><?= angka($u['total_nilai'], 1) ?></span>
                </div>
              <?php else: ?>
                <div class="cincin kosong" role="img" aria-label="Belum bernilai"><span>Belum<br>dinilai</span></div>
              <?php endif; ?>
            </div>

            <div class="flex flex-wrap gap-2 items-center">
              <?= lencana_kategori($u) ?>
              <span class="sektor"><i style="background:<?= e($u['warna_sektor']) ?>"></i><?= e($u['sektor']) ?></span>
            </div>

            <?php if ($u['uraian_masalah']): ?>
              <p class="text-sm leading-snug" style="color:var(--redup)"><?= e(mb_strimwidth((string)$u['uraian_masalah'], 0, 110, '...')) ?></p>
            <?php endif; ?>

            <dl class="info">
              <div><dt>Volume</dt><dd><?= angka($u['volume'], 0) ?> <?= e($u['satuan']) ?></dd></div>
              <div><dt>Estimasi anggaran</dt><dd><?= rupiah($u['estimasi_anggaran']) ?></dd></div>
            </dl>

            <div class="bawah">
              <div class="flex justify-between text-xs mb-1.5" style="color:var(--redup)">
                <span><?= (int)$u['jumlah_penilai'] ?> dari <?= $target ?> desa menilai</span>
                <span><?= $persen ?>%</span>
              </div>
              <div class="jalur-progres"><i style="width:<?= $persen ?>%"></i></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

    <?php else: ?>
      <div class="kartu overflow-x-auto">
        <table class="data">
          <thead><tr>
            <th>Usulan</th><th>Lokasi</th><th>Sektor</th><th class="text-right">Estimasi anggaran</th>
            <th>Penilaian desa</th><th>Nilai</th><th>Kategori</th>
          </tr></thead>
          <tbody>
          <?php foreach ($daftar as $u):
              $target = (int)$u['target'];
              $persen = $target > 0 ? min(100, round((int)$u['jumlah_penilai'] / $target * 100)) : 0; ?>
            <tr>
              <td>
                <a href="lihat_usulan.php?id=<?= (int)$u['id_usulan'] ?>" class="font-semibold hover:underline"><?= e($u['nama_usulan']) ?></a>
                <div class="text-xs" style="color:var(--redup)">
                  <?= angka($u['volume'], 0) ?> <?= e($u['satuan']) ?><?= $u['peringkat_kec'] !== null ? ', peringkat ' . (int)$u['peringkat_kec'] . ' di kecamatan' : '' ?>
                </div>
              </td>
              <td><?= e($u['desa']) ?><div class="text-xs" style="color:var(--redup)">Kec. <?= e($u['kecamatan']) ?></div></td>
              <td><span class="sektor"><i style="background:<?= e($u['warna_sektor']) ?>"></i><?= e($u['sektor']) ?></span></td>
              <td class="text-right whitespace-nowrap"><?= rupiah($u['estimasi_anggaran']) ?></td>
              <td style="min-width:140px">
                <div class="jalur-progres"><i style="width:<?= $persen ?>%"></i></div>
                <div class="text-xs mt-1" style="color:var(--redup)"><?= (int)$u['jumlah_penilai'] ?> dari <?= $target ?> desa</div>
              </td>
              <td><?= sel_nilai($u) ?></td>
              <td><?= lencana_kategori($u) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($jumlah_hal > 1): ?>
      <nav class="halaman flex flex-wrap items-center gap-1.5 mt-8 cetak-sembunyi" aria-label="Halaman hasil">
        <?php if ($hal > 1): ?><a href="<?= e(tautan_jelajah(['hal' => $hal - 1])) ?>">Sebelumnya</a><?php endif; ?>
        <?php for ($i = 1; $i <= $jumlah_hal; $i++):
            if ($i !== 1 && $i !== $jumlah_hal && abs($i - $hal) > 2) {
                if (abs($i - $hal) === 3) { echo '<span style="color:var(--redup)">...</span>'; }
                continue;
            } ?>
          <?php if ($i === $hal): ?>
            <span class="aktif" aria-current="page"><?= $i ?></span>
          <?php else: ?>
            <a href="<?= e(tautan_jelajah(['hal' => $i])) ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($hal < $jumlah_hal): ?><a href="<?= e(tautan_jelajah(['hal' => $hal + 1])) ?>">Berikutnya</a><?php endif; ?>
        <span class="text-sm ml-2" style="color:var(--redup);background:none;border:0">
          <?= $mulai + 1 ?> sampai <?= min($total, $mulai + $per) ?> dari <?= $total ?>
        </span>
      </nav>
    <?php endif; ?>
  </div>
</main>

<script>
  hubungkanDesa('j_kec', 'j_desa', <?= json_encode($peta_desa, JSON_UNESCAPED_UNICODE) ?>);
</script>

<?php require __DIR__ . '/includes/publik_bawah.php'; ?>
