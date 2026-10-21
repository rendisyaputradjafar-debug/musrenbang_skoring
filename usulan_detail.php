<?php
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();

$halaman_aktif = 'usulan';
$judul_halaman = 'Detail usulan';
$halaman_langsung = true;

$id = (int)($_GET['id'] ?? 0);
$baris = jalankan($koneksi, SQL_USULAN_DASAR . " WHERE u.id_usulan = ?", 'i', [$id]);
if (!$baris) {
    pesan_set('galat', 'Usulan tidak ditemukan.');
    header('Location: usulan.php');
    exit;
}
$u = $baris[0];
if (!boleh_kecamatan((int)$u['id_kecamatan'])) {
    pesan_set('galat', 'Usulan ini berada di luar kecamatan akun Anda.');
    header('Location: usulan.php');
    exit;
}

/* Hasil penilaian antar-desa */
$jumlah_penilai = (int)$u['jumlah_penilai'];
$target = target_penilai($koneksi, (int)$u['id_kecamatan'], $u['desa']);
$rata_kriteria = jalankan($koneksi, "
    SELECT k.kode, k.nama, k.bobot, k.skala_maks,
           ROUND(AVG(ps.skor), 2) AS rata_skor, ROUND(AVG(ps.nilai), 2) AS rata_nilai,
           MIN(ps.skor) AS skor_min, MAX(ps.skor) AS skor_maks
    FROM tb_penilaian_skor ps
    JOIN tb_penilaian p ON p.id_penilaian = ps.id_penilaian
    JOIN tb_kriteria k  ON k.id_kriteria = ps.id_kriteria
    WHERE p.id_usulan = ?
    GROUP BY k.id_kriteria, k.kode, k.nama, k.bobot, k.skala_maks
    ORDER BY k.urut", 'i', [$id]);

/* Siapa melihat apa: admin/verifikator melihat nilai tiap desa; kecamatan melihat siapa yang sudah menilai;
   akun desa hanya melihat nilainya sendiri. */
$lihat_nilai_desa = boleh('admin', 'verifikator');
$penilai = jalankan($koneksi, "
    SELECT d.id_desa, d.nama, p.total_nilai, p.catatan, p.diubah_pada
    FROM tb_desa d
    LEFT JOIN tb_penilaian p ON p.id_desa_penilai = d.id_desa AND p.id_usulan = ?
    WHERE d.id_kecamatan = ? AND d.aktif = 1 AND d.nama <> ?
    ORDER BY (p.id_penilaian IS NULL), p.diubah_pada DESC, d.nama", 'iis', [$id, (int)$u['id_kecamatan'], $u['desa']]);
$nilai_saya = null;
foreach ($penilai as $pn) {
    if ((int)$pn['id_desa'] === desa_user()) { $nilai_saya = $pn; }
}
$boleh_ubah = peran() !== 'desa';
$boleh_nilai = desa_user() > 0 && $u['desa'] !== nama_desa_user() && $u['status'] === 'Draft';

$rincian = jalankan($koneksi, "
    SELECT s.*, k.kode, k.nama
    FROM tb_usulan_skor s
    JOIN tb_kriteria k ON k.id_kriteria = s.id_kriteria
    WHERE s.id_usulan = ? ORDER BY k.urut", 'i', [$id]);

/* Peringkat dihitung terhadap seluruh usulan pada tahun yang sama */
$peringkat = jalankan($koneksi, "
    SELECT COUNT(*) + 1 AS posisi FROM tb_usulan
    WHERE tahun = ? AND total_nilai > ?", 'id', [$u['tahun'], $u['total_nilai']])[0]['posisi'];
$jumlah_tahun = jalankan($koneksi, "
    SELECT COUNT(*) AS n FROM tb_usulan WHERE tahun = ? AND total_nilai IS NOT NULL",
    'i', [$u['tahun']])[0]['n'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-wrap gap-2 mb-4 cetak-sembunyi">
  <a href="usulan.php" class="tombol-2">Kembali ke daftar</a>
  <?php if ($boleh_nilai): ?>
    <a href="penilaian_form.php?id=<?= $id ?>" class="tombol"><?= $nilai_saya && $nilai_saya['total_nilai'] !== null ? 'Ubah nilai dari desa saya' : 'Nilai usulan ini' ?></a>
  <?php endif; ?>
  <?php if ($boleh_ubah): ?>
    <a href="usulan_form.php?id=<?= $id ?>" class="<?= $boleh_nilai ? 'tombol-2' : 'tombol' ?>">Ubah usulan</a>
  <?php endif; ?>
  <button onclick="window.print()" class="tombol-2">Cetak lembar verifikasi</button>
</div>

<div id="langsung-detail" data-langsung class="grid gap-5 lg:grid-cols-3">
  <div class="lg:col-span-2 space-y-5">

    <div class="kartu p-5">
      <p class="text-xs text-slate-500"><?= e($u['kode_usulan']) ?> &middot; tahun rencana <?= (int)$u['tahun'] ?></p>
      <h2 class="text-xl font-bold text-slate-900 mt-1"><?= e($u['nama_usulan']) ?></h2>
      <p class="mt-1"><?= lencana_prioritas($u['peringkat_desa']) ?></p>
      <?php if (!empty($u['keterangan_lokasi'])): ?>
        <p class="text-sm text-slate-600 mt-1">Lokasi: <?= e($u['keterangan_lokasi']) ?></p>
      <?php endif; ?>
      <p class="text-sm text-slate-600 mt-2"><?= nl2br(e($u['uraian_masalah'])) ?></p>

      <div class="grid gap-4 md:grid-cols-3 mt-5 text-sm">
        <div><p class="text-xs text-slate-500">Lokasi</p><p class="font-medium"><?= e($u['desa']) ?>, <?= e($u['kecamatan']) ?></p></div>
        <div><p class="text-xs text-slate-500">Sumber usulan</p><p class="font-medium"><?= e($u['sumber']) ?></p></div>
        <div><p class="text-xs text-slate-500">Sektor</p><p class="font-medium"><?= e($u['sektor']) ?></p></div>
        <div><p class="text-xs text-slate-500">OPD penanggung jawab</p><p class="font-medium"><?= e($u['opd']) ?></p></div>
        <div><p class="text-xs text-slate-500">Volume</p><p class="font-medium"><?= angka($u['volume'], 0) ?> <?= e($u['satuan']) ?></p></div>
        <div><p class="text-xs text-slate-500">Estimasi anggaran</p><p class="font-medium"><?= rupiah($u['estimasi_anggaran']) ?></p></div>
        <div><p class="text-xs text-slate-500">Status</p><p class="font-medium"><?= e($u['status']) ?></p></div>
        <div class="md:col-span-2"><p class="text-xs text-slate-500">Catatan verifikasi</p>
            <p class="font-medium"><?= $u['catatan_verifikasi'] ? nl2br(e($u['catatan_verifikasi'])) : '-' ?></p></div>
      </div>
    </div>

    <?php if ($jumlah_penilai > 0): ?>
    <div class="kartu p-5">
      <p class="font-semibold text-sm text-slate-800 mb-1">Rata-rata penilaian antar-desa</p>
      <p class="text-xs text-slate-500 mb-4">
        Dihitung dari <?= $jumlah_penilai ?> desa penilai. Nilai akhir adalah rata-rata nilai total setiap desa.
      </p>
      <div class="overflow-x-auto">
      <table class="data w-full">
        <thead>
          <tr><th>Kode</th><th>Kriteria</th><th class="text-right">Rata-rata skor</th>
              <th class="text-right">Rentang skor</th><th class="text-right">Bobot</th>
              <th class="text-right">Rata-rata nilai</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rata_kriteria as $r): ?>
          <tr>
            <td class="text-slate-500"><?= e($r['kode']) ?></td>
            <td><?= e($r['nama']) ?></td>
            <td class="text-right"><?= angka($r['rata_skor']) ?> <span class="text-slate-400">/ <?= (int)$r['skala_maks'] ?></span></td>
            <td class="text-right"><?= (int)$r['skor_min'] ?> sampai <?= (int)$r['skor_maks'] ?></td>
            <td class="text-right"><?= angka($r['bobot']) ?></td>
            <td class="text-right font-semibold"><?= angka($r['rata_nilai']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="bg-slate-50">
            <td colspan="5" class="font-semibold">Nilai akhir</td>
            <td class="text-right font-bold"><?= angka($u['total_nilai']) ?></td>
          </tr>
        </tfoot>
      </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if (desa_user() === 0 || $nilai_saya): ?>
    <div class="kartu p-5">
      <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
        <p class="font-semibold text-sm text-slate-800">
          <?= desa_user() ? 'Penilaian dari desa Anda' : 'Desa penilai' ?>
        </p>
        <p class="text-xs text-slate-500"><?= $jumlah_penilai ?> dari <?= $target ?> desa sudah menilai</p>
      </div>
      <?php if (desa_user()): ?>
        <p class="text-sm">Nilai dari <?= e(nama_desa_user()) ?>:
          <b><?= $nilai_saya['total_nilai'] !== null ? angka($nilai_saya['total_nilai']) : 'belum menilai' ?></b>
          <?php if ($nilai_saya['diubah_pada']): ?><span class="text-xs text-slate-500">(<?= e(waktu_lalu($nilai_saya['diubah_pada'])) ?>)</span><?php endif; ?>
        </p>
      <?php else: ?>
        <div class="overflow-x-auto max-h-96 overflow-y-auto">
        <table class="data w-full">
          <thead><tr><th>Desa</th>
            <?php if ($lihat_nilai_desa): ?><th class="text-right">Nilai</th><th>Catatan</th><?php endif; ?>
            <th>Waktu</th></tr></thead>
          <tbody>
          <?php foreach ($penilai as $pn): $sudah = $pn['total_nilai'] !== null; ?>
            <tr>
              <td class="<?= $sudah ? 'font-medium' : 'text-slate-400' ?>"><?= e($pn['nama']) ?></td>
              <?php if ($lihat_nilai_desa): ?>
                <td class="text-right"><?= $sudah ? angka($pn['total_nilai']) : '' ?></td>
                <td class="text-xs text-slate-600"><?= e($pn['catatan'] ?? '') ?></td>
              <?php endif; ?>
              <td class="text-xs <?= $sudah ? 'text-slate-500' : 'text-slate-400' ?>"><?= $sudah ? e(waktu_lalu($pn['diubah_pada'])) : 'Belum menilai' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$penilai): ?>
            <tr><td colspan="4" class="text-center text-slate-500 py-6">Data desa kecamatan ini belum diisi di menu Master.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($rincian && boleh('admin', 'verifikator')): ?>
    <div class="kartu p-5">
      <p class="font-semibold text-sm text-slate-800 mb-1">Nilai awal: <?= angka($u['nilai_awal']) ?></p>
      <p class="text-xs text-slate-500 mb-4">
        <?= $jumlah_penilai > 0
            ? 'Sebagai pembanding saja. Nilai akhir sudah memakai rata-rata penilaian desa.'
            : 'Dipakai sebagai nilai akhir sementara sampai ada desa yang menilai.' ?>
      </p>
      <div class="overflow-x-auto">
      <table class="data w-full">
        <thead>
          <tr><th>Kode</th><th>Kriteria</th><th class="text-right">Skor</th>
              <th class="text-right">Skala</th><th class="text-right">Bobot</th>
              <th class="text-right">Nilai</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rincian as $r): ?>
          <tr>
            <td class="text-slate-500"><?= e($r['kode']) ?></td>
            <td><?= e($r['nama']) ?></td>
            <td class="text-right"><?= (int)$r['skor'] ?></td>
            <td class="text-right"><?= (int)$r['skala_maks'] ?></td>
            <td class="text-right"><?= angka($r['bobot']) ?></td>
            <td class="text-right font-semibold"><?= angka($r['nilai']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <?php endif; ?>

    <div class="kartu p-5 hidden print:block">
      <p class="font-semibold text-sm mb-8">Mengetahui,</p>
      <div class="grid grid-cols-2 gap-8 text-sm">
        <div><p>Verifikator</p><div style="height:60px"></div><p>(..............................)</p></div>
        <div><p>Kepala Bappeda Kabupaten Banggai</p><div style="height:60px"></div><p>(..............................)</p></div>
      </div>
    </div>

  </div>

  <div class="space-y-5">
    <div class="kartu p-5">
      <p class="text-xs text-slate-500">Nilai akhir</p>
      <p class="text-4xl font-bold text-slate-900"><?= $u['total_nilai'] !== null ? angka($u['total_nilai']) : '-' ?></p>
      <p class="mt-2"><span class="lencana" style="background:<?= e($u['warna_kategori'] ?: '#64748B') ?>">
        <?= e($u['kategori'] ?: 'Belum dinilai') ?></span></p>
      <p class="text-xs text-slate-500 mt-2">
        <?php if ($jumlah_penilai > 0): ?>
          Rata-rata dari <?= $jumlah_penilai ?> desa penilai
        <?php elseif ($u['total_nilai'] !== null): ?>
          Nilai awal, menunggu penilaian desa
        <?php else: ?>
          Menunggu penilaian dari desa lain
        <?php endif; ?>
      </p>
      <div class="mt-3"><?php
        $persen = $target > 0 ? min(100, round($jumlah_penilai / $target * 100)) : 0; ?>
        <div class="h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full" style="width:<?= $persen ?>%;background:#44403B"></div></div>
        <p class="text-xs text-slate-500 mt-1"><?= $jumlah_penilai ?> dari <?= $target ?> desa (<?= $persen ?>%)</p>
      </div>
      <div class="mt-4 pt-4 border-t border-slate-200 text-sm">
        <p class="text-xs text-slate-500">Peringkat tahun <?= (int)$u['tahun'] ?></p>
        <p class="font-semibold"><?= $u['total_nilai'] !== null ? (int)$peringkat . ' dari ' . (int)$jumlah_tahun . ' usulan' : 'Belum ada' ?></p>
      </div>
      <div class="mt-4 pt-4 border-t border-slate-200 text-xs text-slate-500">
        <p>Dibuat <?= date('d/m/Y H:i', strtotime($u['dibuat_pada'])) ?></p>
        <p>Diubah <?= date('d/m/Y H:i', strtotime($u['diubah_pada'])) ?></p>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
