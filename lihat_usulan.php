<?php
/**
 * Rincian satu usulan untuk publik.
 * Menampilkan rata-rata penilaian per kriteria dan progres penilaian,
 * tanpa menyebut desa penilai maupun nilai dari masing-masing desa.
 */
require_once __DIR__ . '/includes/fungsi_publik.php';

$id = (int)($_GET['id'] ?? 0);
$sql = str_replace('SELECT u.*,', 'SELECT u.*, ' . SQL_TARGET_PENILAI . ' AS target,', SQL_USULAN_DASAR);
$baris = jalankan($koneksi, $sql . " WHERE u.id_usulan = ? AND u.status <> 'Ditolak' AND " . syarat_prioritas('u'), 'i', [$id]);
$u = $baris[0] ?? null;

if ($u) {
    $target   = (int)$u['target'];
    $jumlah   = (int)$u['jumlah_penilai'];
    $persen   = $target > 0 ? min(100, round($jumlah / $target * 100)) : 0;
    $bernilai = $u['total_nilai'] !== null;

    /* Rata-rata per kriteria dari penilaian desa; bila belum ada, nilai awal */
    $kriteria_nilai = jalankan($koneksi, "
        SELECT k.nama, k.deskripsi, k.bobot, ROUND(AVG(ps.nilai), 2) AS nilai, ROUND(AVG(ps.skor), 2) AS skor
        FROM tb_penilaian_skor ps
        JOIN tb_penilaian p ON p.id_penilaian = ps.id_penilaian
        JOIN tb_kriteria k  ON k.id_kriteria = ps.id_kriteria
        WHERE p.id_usulan = ?
        GROUP BY k.id_kriteria, k.nama, k.deskripsi, k.bobot, k.urut
        ORDER BY k.urut", 'i', [$id]);
    $sumber_rincian = 'desa';
    if (!$kriteria_nilai && $bernilai) {
        $kriteria_nilai = jalankan($koneksi, "
            SELECT k.nama, k.deskripsi, s.bobot, s.nilai, s.skor
            FROM tb_usulan_skor s JOIN tb_kriteria k ON k.id_kriteria = s.id_kriteria
            WHERE s.id_usulan = ? ORDER BY k.urut", 'i', [$id]);
        $sumber_rincian = 'awal';
    }

    /* Peringkat di kecamatan dan kabupaten */
    $posisi_kec = $posisi_kab = null;
    $jml_kec = $jml_kab = 0;
    if ($bernilai) {
        $r = jalankan($koneksi, "
            SELECT
              (SELECT COUNT(*) + 1 FROM tb_usulan x WHERE x.tahun = ? AND x.status <> 'Ditolak' AND " . syarat_prioritas('x') . "
                 AND x.id_kecamatan = ? AND x.total_nilai > ?) AS pos_kec,
              (SELECT COUNT(*) FROM tb_usulan x WHERE x.tahun = ? AND x.status <> 'Ditolak' AND " . syarat_prioritas('x') . "
                 AND x.id_kecamatan = ? AND x.total_nilai IS NOT NULL) AS jml_kec,
              (SELECT COUNT(*) + 1 FROM tb_usulan x WHERE x.tahun = ? AND x.status <> 'Ditolak' AND " . syarat_prioritas('x') . "
                 AND x.total_nilai > ?) AS pos_kab,
              (SELECT COUNT(*) FROM tb_usulan x WHERE x.tahun = ? AND x.status <> 'Ditolak' AND " . syarat_prioritas('x') . "
                 AND x.total_nilai IS NOT NULL) AS jml_kab",
            'iidiiidi', [
                $u['tahun'], $u['id_kecamatan'], $u['total_nilai'],
                $u['tahun'], $u['id_kecamatan'],
                $u['tahun'], $u['total_nilai'],
                $u['tahun'],
            ])[0];
        [$posisi_kec, $jml_kec, $posisi_kab, $jml_kab] = [(int)$r['pos_kec'], (int)$r['jml_kec'], (int)$r['pos_kab'], (int)$r['jml_kab']];
    }

    /* Riwayat masuknya penilaian, tanpa identitas desa */
    $riwayat = jalankan($koneksi, "SELECT diubah_pada, (diubah_pada > dibuat_pada) AS diperbarui
        FROM tb_penilaian WHERE id_usulan = ? ORDER BY diubah_pada DESC LIMIT 8", 'i', [$id]);

    /* Usulan lain dari desa yang sama */
    $lainnya = jalankan($koneksi, "SELECT u.id_usulan, u.nama_usulan, u.total_nilai, kat.warna AS warna_kategori
        FROM tb_usulan u LEFT JOIN tb_kategori kat ON kat.id_kategori = u.id_kategori
        WHERE u.tahun = ? AND u.id_kecamatan = ? AND u.desa = ? AND u.id_usulan <> ? AND u.status <> 'Ditolak'
          AND " . syarat_prioritas('u') . "
        ORDER BY u.peringkat_desa LIMIT 6",
        'iisi', [$u['tahun'], $u['id_kecamatan'], $u['desa'], $id]);
}

$judul_publik = $u ? $u['nama_usulan'] : 'Usulan tidak ditemukan';
$menu_publik_aktif = 'jelajah';
$jeda_publik = 6000;
require __DIR__ . '/includes/publik_atas.php';
?>

<?php if (!$u): ?>
  <main id="isi-utama" class="max-w-3xl mx-auto px-5 py-20 text-center">
    <h1 class="text-2xl font-extrabold">Usulan tidak ditemukan</h1>
    <p class="mt-2" style="color:var(--redup)">Usulan mungkin sudah dihapus atau tidak ditampilkan untuk publik.</p>
    <a href="lihat.php" class="tombol inline-block mt-6">Kembali ke daftar usulan</a>
  </main>
<?php else: ?>

<section class="kepala-hal">
  <div class="wadah pt-8 pb-20" id="isi-utama">
    <nav class="text-sm" aria-label="Jejak halaman" style="color:#A9A49A">
      <a href="lihat.php?tahun=<?= (int)$u['tahun'] ?>" class="hover:text-white">Lihat usulan</a>
      <span aria-hidden="true"> / </span>
      <a href="lihat.php?tahun=<?= (int)$u['tahun'] ?>&amp;kec=<?= (int)$u['id_kecamatan'] ?>" class="hover:text-white">Kec. <?= e($u['kecamatan']) ?></a>
      <span aria-hidden="true"> / </span>
      <a href="lihat.php?tahun=<?= (int)$u['tahun'] ?>&amp;kec=<?= (int)$u['id_kecamatan'] ?>&amp;desa=<?= urlencode($u['desa']) ?>" class="hover:text-white"><?= e($u['desa']) ?></a>
    </nav>
    <h1 class="text-3xl md:text-4xl mt-3 max-w-3xl"><?= e($u['nama_usulan']) ?></h1>
    <p class="mt-2 text-sm" style="color:#D7D3CA">
      Prioritas ke-<?= (int)$u['peringkat_desa'] ?> dari <?= e($u['desa']) ?>. Tahun rencana <?= (int)$u['tahun'] ?>.
      Status: <?= $u['status'] === 'Draft' ? 'terbuka untuk dinilai' : e(strtolower($u['status'])) ?>.
    </p>
  </div>
</section>

<main class="wadah -mt-12 pb-16 relative z-10">
  <div id="langsung-rincian" data-langsung class="grid gap-5 lg:grid-cols-3">

    <div class="lg:col-span-2 space-y-5">
      <div class="kartu p-6">
        <h2 class="font-bold">Uraian masalah</h2>
        <p class="mt-2 leading-relaxed" style="color:var(--redup)"><?= $u['uraian_masalah'] ? nl2br(e($u['uraian_masalah'])) : 'Tidak ada uraian.' ?></p>
        <dl class="grid gap-4 grid-cols-2 md:grid-cols-3 mt-6 text-sm">
          <div><dt class="text-xs" style="color:var(--redup)">Lokasi</dt><dd class="font-semibold"><?= e($u['desa']) ?>, Kec. <?= e($u['kecamatan']) ?></dd></div>
          <div><dt class="text-xs" style="color:var(--redup)">Sektor</dt><dd class="font-semibold"><span class="sektor" style="color:inherit"><i style="background:<?= e($u['warna_sektor']) ?>"></i><?= e($u['sektor']) ?></span></dd></div>
          <div><dt class="text-xs" style="color:var(--redup)">OPD penanggung jawab</dt><dd class="font-semibold"><?= e($u['opd']) ?></dd></div>
          <div><dt class="text-xs" style="color:var(--redup)">Volume</dt><dd class="font-semibold"><?= angka($u['volume'], 0) ?> <?= e($u['satuan']) ?></dd></div>
          <div><dt class="text-xs" style="color:var(--redup)">Estimasi anggaran</dt><dd class="font-semibold"><?= rupiah($u['estimasi_anggaran']) ?></dd></div>
          <div><dt class="text-xs" style="color:var(--redup)">Sumber usulan</dt><dd class="font-semibold"><?= e($u['sumber']) ?></dd></div>
        </dl>
      </div>

      <div class="kartu p-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <h2 class="font-bold">Nilai per kriteria</h2>
          <p class="text-xs" style="color:var(--redup)">
            <?= $sumber_rincian === 'desa' && $kriteria_nilai
                ? 'Rata-rata dari ' . $jumlah . ' desa penilai'
                : ($kriteria_nilai ? 'Nilai awal, belum ada desa yang menilai' : '') ?>
          </p>
        </div>
        <?php if ($kriteria_nilai): ?>
          <div class="mt-3">
            <?php foreach ($kriteria_nilai as $k):
                $bobot = (float)$k['bobot'];
                $isi = $bobot > 0 ? min(100, (float)$k['nilai'] / $bobot * 100) : 0; ?>
              <div class="batang-kriteria">
                <div>
                  <p class="font-semibold"><?= e($k['nama']) ?></p>
                  <p class="text-xs" style="color:var(--redup)">Skor rata-rata <?= angka($k['skor'], 1) ?> dari 5</p>
                </div>
                <div class="jalur-progres" role="img" aria-label="<?= angka($k['nilai']) ?> dari bobot <?= angka($bobot, 0) ?>">
                  <i style="width:<?= $isi ?>%;background:<?= e($u['warna_kategori'] ?: '#44403B') ?>"></i>
                </div>
                <p class="text-right"><b><?= angka($k['nilai']) ?></b><span class="text-xs" style="color:var(--redup)"> / <?= angka($bobot, 0) ?></span></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="mt-3 text-sm" style="color:var(--redup)">Belum ada penilaian. Rincian akan muncul setelah desa pertama menilai.</p>
        <?php endif; ?>
      </div>

      <?php if ($lainnya): ?>
      <div class="kartu p-6">
        <h2 class="font-bold">Usulan prioritas lain dari <?= e($u['desa']) ?></h2>
        <ul class="mt-3 divide-y" style="border-color:#EFEDE7">
          <?php foreach ($lainnya as $l): ?>
            <li class="py-2.5 flex items-center justify-between gap-3">
              <a href="lihat_usulan.php?id=<?= (int)$l['id_usulan'] ?>" class="font-medium hover:underline"><?= e($l['nama_usulan']) ?></a>
              <?php if ($l['total_nilai'] !== null): ?>
                <span class="font-bold" style="color:<?= e($l['warna_kategori'] ?: 'inherit') ?>"><?= angka($l['total_nilai']) ?></span>
              <?php else: ?>
                <span class="status-tunggu">Menunggu</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>

    <aside class="space-y-5">
      <div class="kartu p-6 text-center">
        <?php if ($bernilai): ?>
          <div class="cincin mx-auto" style="--p:<?= max(0, min(100, (float)$u['total_nilai'])) ?>;--warna:<?= e($u['warna_kategori'] ?: '#64748B') ?>;width:132px;height:132px">
            <span style="width:108px;height:108px;font-size:1.9rem"><?= angka($u['total_nilai']) ?></span>
          </div>
          <p class="mt-3"><?= lencana_kategori($u) ?></p>
          <p class="text-xs mt-2" style="color:var(--redup)"><?= $jumlah > 0 ? 'Rata-rata dari ' . $jumlah . ' desa penilai' : 'Nilai awal, menunggu penilaian desa' ?></p>
        <?php else: ?>
          <div class="cincin kosong mx-auto" style="width:132px;height:132px">
            <span style="width:108px;height:108px;font-size:.85rem">Menunggu<br>penilaian</span>
          </div>
          <p class="text-sm mt-3" style="color:var(--redup)">Nilai muncul setelah desa pertama menilai.</p>
        <?php endif; ?>

        <div class="mt-5 text-left">
          <div class="flex justify-between text-xs mb-1.5" style="color:var(--redup)">
            <span>Progres penilaian desa</span><span><?= $persen ?>%</span>
          </div>
          <div class="jalur-progres"><i style="width:<?= $persen ?>%"></i></div>
          <p class="text-sm mt-1.5"><b><?= $jumlah ?></b> dari <?= $target ?> desa di Kec. <?= e($u['kecamatan']) ?> sudah menilai</p>
        </div>

        <?php if ($bernilai): ?>
          <dl class="grid grid-cols-2 gap-3 mt-5 pt-5 border-t text-left" style="border-color:#EFEDE7">
            <div><dt class="text-xs" style="color:var(--redup)">Peringkat kecamatan</dt><dd class="text-xl font-extrabold"><?= $posisi_kec ?><span class="text-sm font-medium" style="color:var(--redup)"> / <?= $jml_kec ?></span></dd></div>
            <div><dt class="text-xs" style="color:var(--redup)">Peringkat kabupaten</dt><dd class="text-xl font-extrabold"><?= $posisi_kab ?><span class="text-sm font-medium" style="color:var(--redup)"> / <?= $jml_kab ?></span></dd></div>
          </dl>
        <?php endif; ?>
      </div>

      <div class="kartu p-6">
        <h2 class="font-bold flex items-center gap-2"><span class="langsung-titik" aria-hidden="true"></span>Penilaian masuk</h2>
        <?php if ($riwayat): ?>
          <ol class="mt-3 space-y-2 text-sm">
            <?php foreach ($riwayat as $rw): ?>
              <li class="flex justify-between gap-3">
                <span><?= $rw['diperbarui'] ? 'Satu desa memperbarui nilai' : 'Satu desa memberi nilai' ?></span>
                <span class="text-xs whitespace-nowrap" style="color:var(--redup)"><?= e(waktu_lalu($rw['diubah_pada'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
          <p class="text-xs mt-3" style="color:var(--redup)">Nama desa penilai dan nilainya masing-masing tidak ditampilkan.</p>
        <?php else: ?>
          <p class="mt-2 text-sm" style="color:var(--redup)">Belum ada penilaian. Halaman ini diperbarui sendiri saat penilaian masuk.</p>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</main>
<?php endif; ?>

<?php require __DIR__ . '/includes/publik_bawah.php'; ?>
