<?php
/**
 * Halaman publik: dapat dibuka tanpa masuk.
 * Menampilkan 5 usulan prioritas setiap desa, diurutkan menurut nilai,
 * per kecamatan dan per desa, dengan bahasa yang mudah dipahami.
 */
require_once __DIR__ . '/includes/fungsi_publik.php';


/* ---------------- Penyaring ---------------- */
$daftar_tahun = daftar_tahun_publik($koneksi);

$tahun  = (int)($_GET['tahun'] ?? APP_TAHUN_DEFAULT);
$kec    = (int)($_GET['kec'] ?? 0);
$desa   = $kec > 0 ? trim((string)($_GET['desa'] ?? '')) : '';
/* Bawaan: dikelompokkan per desa (paling mudah dibaca); pilihan lain: urutan se-kecamatan */
$tampil = ($_GET['tampil'] ?? 'desa') === 'kecamatan' ? 'kecamatan' : 'desa';

$ref_kecamatan = ambil_daftar($koneksi, 'tb_kecamatan', 'id_kecamatan');
$nama_kec = '';
foreach ($ref_kecamatan as $r) {
    if ((int)$r['id_kecamatan'] === $kec) { $nama_kec = $r['nama']; }
}
if ($nama_kec === '') { $kec = 0; $desa = ''; }

/* Syarat data publik: 5 usulan prioritas setiap desa, termasuk yang masih menunggu penilaian */
$syarat_publik = SYARAT_PUBLIK;
$peta_desa = peta_desa_publik($koneksi, $tahun);

/* ---------------- Ringkasan kabupaten ---------------- */
$ringkas = jalankan($koneksi, "
    SELECT COUNT(*) AS jumlah,
           COALESCE(SUM(u.total_nilai IS NOT NULL), 0) AS dinilai,
           COUNT(DISTINCT CONCAT(u.id_kecamatan, '|', u.desa)) AS jml_desa,
           COALESCE(SUM(CASE WHEN u.total_nilai >= 80 THEN 1 ELSE 0 END),0) AS tinggi
    FROM tb_usulan u WHERE $syarat_publik", 'i', [$tahun])[0];
$ringkas['desa_menilai'] = (int)jalankan($koneksi, "SELECT COUNT(DISTINCT p.id_desa_penilai) AS n
    FROM tb_penilaian p JOIN tb_usulan u ON u.id_usulan = p.id_usulan WHERE u.tahun = ?", 'i', [$tahun])[0]['n'];

$kategori = ambil_kategori($koneksi);
$kriteria = ambil_kriteria($koneksi);

/* ---------------- Umpan penilaian real time ----------------
   Menampilkan setiap penilaian desa terbaru beserta nilai rata-rata terkini usulannya.
   Identitas desa penilai dan nilai per desa tidak ditampilkan untuk menjaga kejujuran penilaian. */
$syarat_umpan = SYARAT_PUBLIK;
$tipe_umpan   = 'i';
$nilai_umpan  = [$tahun];
if ($kec > 0) {
    $syarat_umpan .= ' AND u.id_kecamatan = ?';
    $tipe_umpan   .= 'i';
    $nilai_umpan[] = $kec;
    if ($desa !== '') {
        $syarat_umpan .= ' AND u.desa = ?';
        $tipe_umpan   .= 's';
        $nilai_umpan[] = $desa;
    }
}
$umpan = jalankan($koneksi, "
    SELECT p.id_penilaian, p.diubah_pada, (p.diubah_pada > p.dibuat_pada) AS diperbarui,
           u.id_usulan, u.nama_usulan, u.desa, u.total_nilai, u.jumlah_penilai, u.id_kecamatan,
           kec.nama AS kecamatan, s.nama AS sektor, s.warna AS warna_sektor,
           kat.nama AS kategori, kat.warna AS warna_kategori,
           (SELECT COUNT(*) FROM tb_desa d WHERE d.id_kecamatan = u.id_kecamatan AND d.aktif = 1 AND d.nama <> u.desa) AS target
    FROM tb_penilaian p
    JOIN tb_usulan u      ON u.id_usulan = p.id_usulan
    JOIN tb_kecamatan kec ON kec.id_kecamatan = u.id_kecamatan
    JOIN tb_sektor s      ON s.id_sektor = u.id_sektor
    LEFT JOIN tb_kategori kat ON kat.id_kategori = u.id_kategori
    WHERE $syarat_umpan
    ORDER BY p.diubah_pada DESC, p.id_penilaian DESC
    LIMIT 12", $tipe_umpan, $nilai_umpan);

$statistik_umpan = jalankan($koneksi, "
    SELECT COUNT(*) AS total,
           COALESCE(SUM(DATE(p.diubah_pada) = CURDATE()), 0) AS hari_ini,
           COUNT(DISTINCT CASE WHEN DATE(p.diubah_pada) = CURDATE() THEN p.id_desa_penilai END) AS desa_hari_ini,
           COUNT(DISTINCT p.id_usulan) AS usulan_dinilai
    FROM tb_penilaian p JOIN tb_usulan u ON u.id_usulan = p.id_usulan
    WHERE $syarat_umpan", $tipe_umpan, $nilai_umpan)[0];

if ($kec > 0) {
    /* Semua usulan kecamatan ini (untuk hitungan per desa) */
    $semua_kec = beri_peringkat(jalankan($koneksi, str_replace('SELECT u.*,', 'SELECT u.*, ' . SQL_TARGET_PENILAI . ' AS target,', SQL_USULAN_DASAR) . "
        WHERE $syarat_publik AND u.id_kecamatan = ?
        ORDER BY (u.total_nilai IS NULL), u.total_nilai DESC, u.estimasi_anggaran ASC, u.nama_usulan ASC", 'ii', [$tahun, $kec]));

    $per_desa = [];
    foreach ($peta_desa[$kec] ?? [] as $nd) { $per_desa[$nd] = []; }
    foreach ($semua_kec as $u) { $per_desa[$u['desa']][] = $u; }
    /* Di dalam satu desa, tampilkan sesuai urutan prioritas yang ditetapkan kecamatan */
    foreach ($per_desa as &$isi_desa) {
        usort($isi_desa, function ($a, $b) { return (int)$a['peringkat_desa'] <=> (int)$b['peringkat_desa']; });
    }
    unset($isi_desa);
    uksort($per_desa, 'strnatcasecmp');

    $daftar = $desa !== ''
        ? array_values(array_filter($semua_kec, function ($u) use ($desa) { return $u['desa'] === $desa; }))
        : $semua_kec;
    if ($desa !== '') {
        usort($daftar, function ($a, $b) { return (int)$a['peringkat_desa'] <=> (int)$b['peringkat_desa']; });
    }

    $desa_berusulan = count(array_filter($per_desa));
    $anggaran_kec   = array_sum(array_column($daftar, 'estimasi_anggaran'));
    $bernilai       = array_filter(array_column($daftar, 'total_nilai'), function ($v) { return $v !== null; });
    $rata_kec       = $bernilai ? array_sum($bernilai) / count($bernilai) : null;
    $menunggu_kec   = count($daftar) - count($bernilai);
} else {
    $rekap_kec = jalankan($koneksi, "
        SELECT kec.id_kecamatan, kec.nama,
               COUNT(u.id_usulan) AS jumlah,
               COALESCE(SUM(u.total_nilai IS NULL), 0) AS menunggu,
               COALESCE(SUM(u.total_nilai IS NOT NULL), 0) AS dinilai,
               MAX(u.total_nilai) AS maks,
               COALESCE(SUM(CASE WHEN u.total_nilai >= 80 THEN 1 ELSE 0 END),0) AS tinggi,
               COUNT(DISTINCT u.desa) AS desa_berusulan
        FROM tb_kecamatan kec
        LEFT JOIN tb_usulan u ON u.id_kecamatan = kec.id_kecamatan AND $syarat_publik
        WHERE kec.aktif = 1
        GROUP BY kec.id_kecamatan, kec.nama
        ORDER BY kec.nama", 'i', [$tahun]);
    $maks_jumlah = max(1, (int)max(array_merge([1], array_map('intval', array_column($rekap_kec, 'jumlah')))));

    $teratas = beri_peringkat(jalankan($koneksi, SQL_USULAN_DASAR . "
        WHERE $syarat_publik AND u.total_nilai IS NOT NULL
        ORDER BY u.total_nilai DESC, u.estimasi_anggaran ASC LIMIT 10", 'i', [$tahun]));
}

/* Tautan dengan penyaring saat ini, sebagian nilainya dapat diganti */
function tautan(array $ubah): string
{
    global $tahun, $kec, $desa, $tampil;
    $q = array_merge([
        'tahun'  => $tahun,
        'kec'    => $kec ?: null,
        'desa'   => $desa !== '' ? $desa : null,
        'tampil' => $tampil === 'kecamatan' ? 'kecamatan' : null,
    ], $ubah);
    $q = array_filter($q, function ($v) { return $v !== null && $v !== ''; });
    return 'index.php?' . http_build_query($q) . '#data';
}

$warna_bobot = ['#211F1C', '#44403B', '#2F8F83', '#4FA99B', '#7DBFB3', '#A9D3CA'];
?>
<?php
$judul_publik = 'Usulan prioritas desa';
$menu_publik_aktif = 'beranda';
require __DIR__ . '/includes/publik_atas.php';

function langkah_ikon(string $isi): string
{
    return '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $isi . '</svg>';
}
?>

<section id="beranda" class="hero hero-penuh">
  <span id="isi-utama"></span>
  <div class="wadah pt-12 pb-28 grid gap-10 lg:gap-16 lg:grid-cols-12 items-center relative z-10 w-full">
    <div class="lg:col-span-7">
      <p class="sapaan">Musrenbang Kabupaten Banggai, tahun <?= $tahun ?></p>
      <h1 class="text-4xl md:text-5xl xl:text-6xl mt-3">Apa yang paling dibutuhkan desa Anda?</h1>
      <p class="mt-5 text-lg leading-relaxed max-w-xl" style="color:#D7D3CA">
        Di sini Anda bisa melihat <b class="text-white"><?= JUMLAH_PRIORITAS_DESA ?> usulan pembangunan terpenting</b>
        dari setiap desa. Nilainya diberikan oleh desa-desa tetangga dan diperbarui secara langsung.
      </p>

      <dl id="langsung-statistik" data-langsung class="angka-ringkas mt-8">
        <div><dt>Usulan prioritas</dt><dd><?= number_format((int)$ringkas['jumlah'], 0, ',', '.') ?></dd></div>
        <div><dt>Sudah dinilai</dt><dd><?= number_format((int)$ringkas['dinilai'], 0, ',', '.') ?></dd></div>
        <div><dt>Desa ikut menilai</dt><dd><?= number_format((int)$ringkas['desa_menilai'], 0, ',', '.') ?></dd></div>
      </dl>
    </div>

    <form method="get" action="index.php#data" class="panel p-6 lg:col-span-5" aria-labelledby="judulCari">
      <p id="judulCari" class="font-bold text-xl">Cari usulan desa Anda</p>
      <p class="text-sm mt-1 mb-5" style="color:var(--redup)">Pilih kecamatan, lalu desa.</p>

      <label for="f_kec" class="label-besar"><span class="nomor">1</span> Kecamatan</label>
      <select id="f_kec" name="kec" class="isian isian-besar mb-4">
        <option value="0">Semua kecamatan</option>
        <?php foreach ($ref_kecamatan as $r): ?>
          <option value="<?= $r['id_kecamatan'] ?>" <?= $kec === (int)$r['id_kecamatan'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="f_desa" class="label-besar"><span class="nomor">2</span> Desa/kelurahan</label>
      <select id="f_desa" name="desa" class="isian isian-besar mb-5" data-terpilih="<?= e($desa) ?>"></select>

      <?php if (count($daftar_tahun) > 1): ?>
        <details class="mb-4 text-sm">
          <summary class="cursor-pointer" style="color:var(--laut)">Ganti tahun (sekarang <?= $tahun ?>)</summary>
          <select id="f_tahun" name="tahun" class="isian mt-2">
            <?php foreach ($daftar_tahun as $t): ?>
              <option value="<?= $t ?>" <?= $t === $tahun ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </details>
      <?php else: ?>
        <input type="hidden" name="tahun" value="<?= $tahun ?>">
      <?php endif; ?>

      <button class="tombol tombol-besar w-full">Lihat usulan</button>
      <a href="lihat.php?tahun=<?= $tahun ?>" class="block text-center text-sm mt-3" style="color:var(--laut)">atau jelajahi semua usulan</a>
    </form>
  </div>
  <a href="#data" class="gulir cetak-sembunyi" aria-label="Gulir ke daftar usulan">
    <span>Lihat usulan</span>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
  </a>
</section>

<main id="data" class="wadah -mt-10 pb-16 relative z-10 scroll-mt-20">

  <!-- Cara kerja -->
  <ol class="langkah mb-12" aria-label="Cara kerja">
    <li>
      <span class="ikon"><?= langkah_ikon('<path d="M4 4h11a3 3 0 013 3v13H7a3 3 0 01-3-3z"/><path d="M4 17a3 3 0 013-3h11"/>') ?></span>
      <div><b>1. Tiap desa punya <?= JUMLAH_PRIORITAS_DESA ?> usulan</b><p>Kecamatan mencatatnya dari daftar baku, jadi tidak ada salah tulis.</p></div>
    </li>
    <li>
      <span class="ikon"><?= langkah_ikon('<path d="M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z"/>') ?></span>
      <div><b>2. Desa tetangga memberi nilai</b><p>Dalam satu kecamatan berisi 10 desa, setiap usulan dinilai 9 desa lainnya.</p></div>
    </li>
    <li>
      <span class="ikon"><?= langkah_ikon('<path d="M6 20V10M12 20V4M18 20v-6"/>') ?></span>
      <div><b>3. Nilainya tampil di sini</b><p>Nilai akhir adalah rata-rata dari seluruh desa yang menilai, langsung terlihat publik.</p></div>
    </li>
  </ol>

  <div data-langsung id="langsung-data">

  <!-- Penilaian terbaru -->
  <section class="umpan mb-12" aria-labelledby="judulUmpan">
    <div class="umpan-kepala">
      <div>
        <h2 id="judulUmpan" class="text-lg font-bold flex items-center gap-2">
          <span class="langsung-titik" aria-hidden="true"></span>
          Penilaian terbaru<?= $kec > 0 ? ' di Kecamatan ' . e($nama_kec) : '' ?>
        </h2>
        <p class="text-sm mt-1" style="color:#A9A49A">Daftar ini bertambah sendiri setiap kali ada desa yang memberi nilai.</p>
      </div>
      <dl class="umpan-angka">
        <div><dt>Nilai masuk hari ini</dt><dd><?= number_format((int)$statistik_umpan['hari_ini'], 0, ',', '.') ?></dd></div>
        <div><dt>Desa aktif hari ini</dt><dd><?= number_format((int)$statistik_umpan['desa_hari_ini'], 0, ',', '.') ?></dd></div>
      </dl>
    </div>
    <?php if ($umpan): ?>
      <ol class="umpan-daftar">
        <?php foreach (array_slice($umpan, 0, 6) as $f):
            $baru = (time() - strtotime($f['diubah_pada'])) < 120;
            $target = max(1, (int)$f['target']);
            $persen = min(100, round((int)$f['jumlah_penilai'] / $target * 100)); ?>
          <li class="umpan-item <?= $baru ? 'baru' : '' ?>">
            <a class="judul hover:underline" href="lihat_usulan.php?id=<?= (int)$f['id_usulan'] ?>"><?= $baru ? '<span class="label-baru">BARU</span>' : '' ?><?= e($f['nama_usulan']) ?></a>
            <span class="meta"><?= e($f['desa']) ?>, Kec. <?= e($f['kecamatan']) ?>. <?= e(ucfirst(waktu_lalu($f['diubah_pada']))) ?>.</span>
            <span class="skor">
              <b><?= $f['total_nilai'] !== null ? angka($f['total_nilai'], 1) : '-' ?></b>
              <span>nilai</span>
            </span>
            <span class="progres">
              <span class="jalur"><i style="width:<?= $persen ?>%"></i></span>
              <small><?= (int)$f['jumlah_penilai'] ?> dari <?= (int)$f['target'] ?> desa</small>
            </span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <p class="px-5 py-8 text-center" style="color:#A9A49A">Belum ada penilaian. Nilai yang masuk akan langsung muncul di sini.</p>
    <?php endif; ?>
  </section>

<?php if ($kec === 0): ?>

  <h2 class="judul-bagian">Pilih kecamatan</h2>
  <p class="sub-bagian">Klik nama kecamatan untuk melihat <?= JUMLAH_PRIORITAS_DESA ?> usulan prioritas setiap desanya.</p>

  <ul class="petak-kec mb-14">
    <?php foreach ($rekap_kec as $r):
        $j = (int)$r['jumlah'];
        $persen = $j ? round((int)$r['dinilai'] / $j * 100) : 0; ?>
      <li>
        <a href="index.php?tahun=<?= $tahun ?>&amp;kec=<?= (int)$r['id_kecamatan'] ?>#data">
          <span class="flex items-start justify-between gap-2">
            <b><?= e($r['nama']) ?></b>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="panah"><path d="M9 6l6 6-6 6"/></svg>
          </span>
          <?php if ($j): ?>
            <span class="ket"><?= $j ?> usulan dari <?= (int)$r['desa_berusulan'] ?> desa</span>
            <span class="jalur-progres mt-3"><i style="width:<?= $persen ?>%"></i></span>
            <span class="ket mt-1"><?= $persen ?>% sudah dinilai</span>
          <?php else: ?>
            <span class="ket">Belum ada usulan</span>
          <?php endif; ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <h2 class="judul-bagian">10 usulan dengan nilai tertinggi</h2>
  <p class="sub-bagian">Dari seluruh kecamatan, tahun <?= $tahun ?>.</p>
  <?php if ($teratas): ?>
    <ol class="daftar-sederhana kartu">
      <?php foreach ($teratas as $u): $p = (int)$u['peringkat']; ?>
        <li>
          <span class="rank <?= $p <= 3 ? 'r' . $p : '' ?>"><?= $p ?></span>
          <span class="min-w-0 flex-1">
            <a href="lihat_usulan.php?id=<?= (int)$u['id_usulan'] ?>" class="font-semibold hover:underline"><?= e($u['nama_usulan']) ?></a>
            <span class="block text-sm" style="color:var(--redup)"><?= e($u['desa']) ?>, Kec. <?= e($u['kecamatan']) ?></span>
          </span>
          <span class="w-32 shrink-0"><?= sel_nilai($u) ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php else: ?>
    <p class="kartu p-8 text-center" style="color:var(--redup)">Belum ada usulan yang dinilai untuk tahun <?= $tahun ?>.</p>
  <?php endif; ?>

<?php else: ?>

  <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
    <div>
      <a href="index.php?tahun=<?= $tahun ?>#data" class="text-sm font-semibold cetak-sembunyi" style="color:var(--laut)">&larr; Semua kecamatan</a>
      <h2 class="text-3xl font-extrabold tracking-tight mt-1">
        <?= $desa !== '' ? e($desa) : 'Kecamatan ' . e($nama_kec) ?>
      </h2>
      <p class="mt-1" style="color:var(--redup)">
        <?php if ($desa !== ''): ?>
          <?= count($daftar) ?> dari <?= JUMLAH_PRIORITAS_DESA ?> usulan prioritas desa ini, Kecamatan <?= e($nama_kec) ?>.
        <?php else: ?>
          <?= count($daftar) ?> usulan prioritas dari <?= $desa_berusulan ?> desa.
          <?= $menunggu_kec ? $menunggu_kec . ' masih menunggu penilaian.' : 'Semuanya sudah dinilai.' ?>
        <?php endif; ?>
      </p>
    </div>
    <?php if ($desa === ''): ?>
      <div class="saklar inline-flex p-1 rounded-xl cetak-sembunyi" style="background:#ECEAE3" aria-label="Cara tampil">
        <a href="<?= e(tautan(['tampil' => null])) ?>" class="<?= $tampil === 'desa' ? 'aktif' : '' ?>">Per desa</a>
        <a href="<?= e(tautan(['tampil' => 'kecamatan'])) ?>" class="<?= $tampil === 'kecamatan' ? 'aktif' : '' ?>">Urutan se-kecamatan</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($per_desa && $desa === ''): ?>
    <div class="flex flex-wrap gap-2 mb-6 cetak-sembunyi" aria-label="Pilih desa">
      <?php foreach ($per_desa as $nd => $isi): $nd = (string)$nd; ?>
        <a href="<?= e(tautan(['desa' => $nd])) ?>" class="chip <?= !$isi ? 'kosong' : '' ?>"><?= e($nd) ?> <b><?= count($isi) ?></b></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($desa !== '' || $tampil === 'desa'): ?>

    <div class="grid gap-4 <?= $desa !== '' ? 'xl:grid-cols-2' : 'md:grid-cols-2 xl:grid-cols-3 min-[1800px]:grid-cols-4' ?>">
    <?php
    $kelompok = $desa !== '' ? [$desa => $daftar] : $per_desa;
    foreach ($kelompok as $nd => $isi): $nd = (string)$nd; ?>
      <section class="kartu overflow-hidden">
        <div class="px-5 py-3.5 flex items-center justify-between border-b" style="border-color:var(--garis)">
          <h3 class="font-bold text-lg <?= $isi ? '' : 'text-slate-400' ?>"><?= e($nd) ?></h3>
          <?php if ($desa === '' && $isi): ?>
            <a href="<?= e(tautan(['desa' => $nd])) ?>" class="text-sm font-semibold" style="color:var(--laut)">Buka</a>
          <?php endif; ?>
        </div>
        <?php if ($isi): ?>
          <ol class="daftar-sederhana">
            <?php foreach ($isi as $u): ?>
              <li>
                <span class="rank <?= (int)$u['peringkat_desa'] === 1 && $u['total_nilai'] !== null ? 'r1' : '' ?>"><?= (int)$u['peringkat_desa'] ?></span>
                <span class="min-w-0 flex-1">
                  <a href="lihat_usulan.php?id=<?= (int)$u['id_usulan'] ?>" class="font-semibold hover:underline"><?= e($u['nama_usulan']) ?></a>
                  <span class="block text-sm" style="color:var(--redup)"><?= e($u['uraian_masalah']) ?></span>
                  <?php if ($desa !== ''): ?>
                    <span class="block text-sm mt-1" style="color:var(--redup)">
                      <?= angka($u['volume'], 0) ?> <?= e($u['satuan']) ?>, perkiraan <?= rupiah($u['estimasi_anggaran']) ?>
                    </span>
                  <?php endif; ?>
                </span>
                <span class="w-32 shrink-0"><?= sel_nilai($u) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p class="px-5 py-5" style="color:var(--redup)">Belum ada usulan untuk desa ini.</p>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
    <?php if (!$kelompok): ?>
      <p class="kartu p-8 text-center md:col-span-2" style="color:var(--redup)">Belum ada data desa maupun usulan untuk kecamatan ini.</p>
    <?php endif; ?>
    </div>

  <?php else: ?>

    <?php if ($daftar): ?>
      <ol class="daftar-sederhana kartu">
        <?php foreach ($daftar as $u): $p = $u['peringkat']; ?>
          <li>
            <?php if ($p !== null): ?>
              <span class="rank <?= $p <= 3 ? 'r' . $p : '' ?>"><?= $p ?></span>
            <?php else: ?>
              <span class="rank" style="background:#F3F1EC;color:#9AA5A2">-</span>
            <?php endif; ?>
            <span class="min-w-0 flex-1">
              <a href="lihat_usulan.php?id=<?= (int)$u['id_usulan'] ?>" class="font-semibold hover:underline"><?= e($u['nama_usulan']) ?></a>
              <span class="block text-sm" style="color:var(--redup)"><?= e($u['desa']) ?>, prioritas ke-<?= (int)$u['peringkat_desa'] ?> desa ini</span>
            </span>
            <span class="w-32 shrink-0"><?= sel_nilai($u) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <p class="kartu p-8 text-center" style="color:var(--redup)">Kecamatan ini belum mencatat usulan untuk tahun <?= $tahun ?>.</p>
    <?php endif; ?>

  <?php endif; ?>
<?php endif; ?>

  <p class="text-sm mt-8 max-w-3xl" style="color:var(--redup)">
    Yang ditampilkan hanya <?= JUMLAH_PRIORITAS_DESA ?> usulan prioritas yang ditetapkan setiap desa bersama kecamatan.
    Usulan bertanda "Belum dinilai" belum mendapat nilai dari desa lain.
  </p>
  </div>
</main>

<section id="penilaian" class="bg-white border-t scroll-mt-16" style="border-color:var(--garis)">
  <div class="wadah py-14 grid gap-10 lg:grid-cols-12">
    <div class="lg:col-span-4">
      <h2 class="judul-bagian">Bagaimana nilai dihitung?</h2>
      <p class="mt-3 text-base leading-relaxed" style="color:var(--redup)">
        Setiap desa menjawab enam pertanyaan dengan angka 1 sampai 5. Pertanyaan yang lebih penting punya bobot lebih besar.
        Hasilnya menjadi nilai 0 sampai 100. Nilai akhir adalah rata-rata dari semua desa yang menilai.
      </p>
    </div>
    <div class="lg:col-span-8">
      <p class="text-sm font-semibold mb-2">Besar pengaruh setiap pertanyaan (total 100)</p>
      <div class="bobot" role="img" aria-label="Pembagian bobot pertanyaan penilaian">
        <?php foreach ($kriteria as $i => $k): ?>
          <div style="width:<?= (float)$k['bobot'] ?>%;background:<?= $warna_bobot[$i % 6] ?>;<?= $i % 6 >= 4 ? 'color:#211F1C' : '' ?>"
               title="<?= e($k['nama']) ?>: <?= angka($k['bobot'], 0) ?>">
            <b><?= angka($k['bobot'], 0) ?></b><span><?= e($k['nama']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <ul class="mt-6 grid gap-x-8 gap-y-4 sm:grid-cols-2">
        <?php foreach ($kriteria as $i => $k): ?>
          <li class="flex gap-3">
            <span class="mt-1.5 w-3 h-3 rounded-sm shrink-0" style="background:<?= $warna_bobot[$i % 6] ?>"></span>
            <span><b><?= e($k['nama']) ?></b>
              <span class="block text-sm mt-0.5" style="color:var(--redup)"><?= e($k['deskripsi']) ?></span></span>
          </li>
        <?php endforeach; ?>
      </ul>

      <p class="text-sm font-semibold mt-10 mb-3">Arti warna nilai</p>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($kategori as $k): ?>
          <span class="inline-flex items-center gap-2 text-sm px-3 py-1.5 rounded-full border" style="border-color:var(--garis)">
            <span class="w-3 h-3 rounded-full" style="background:<?= e($k['warna']) ?>"></span>
            <?= e($k['nama']) ?>
            <span style="color:var(--redup)"><?= (float)$k['batas_bawah'] > 0 ? '(' . angka($k['batas_bawah'], 0) . ' ke atas)' : '' ?></span>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<script>
  hubungkanDesa('f_kec', 'f_desa', <?= json_encode($peta_desa, JSON_UNESCAPED_UNICODE) ?>);
</script>

<?php require __DIR__ . '/includes/publik_bawah.php'; ?>
