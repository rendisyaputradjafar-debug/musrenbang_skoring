<?php
$halaman_aktif = 'panduan';
$judul_halaman = 'Panduan';
require_once __DIR__ . '/includes/header.php';

$kriteria = ambil_kriteria($koneksi);
$kategori = ambil_kategori($koneksi);
$panduan  = jalankan($koneksi, "SELECT * FROM tb_panduan_skor ORDER BY skor DESC");
?>

<div class="grid gap-5 lg:grid-cols-3">

  <div class="lg:col-span-2 space-y-5">
    <div class="kartu p-5">
      <p class="font-semibold text-sm text-slate-800 mb-3">Alur kerja</p>
      <ol class="text-sm text-slate-700 space-y-2 list-decimal pl-5 leading-relaxed">
        <li>Operator kecamatan memasukkan satu usulan per formulir, lengkap dengan volume dan estimasi anggaran.</li>
        <li>Verifikator memeriksa kewenangan daerah, kesesuaian tata ruang, status lahan, duplikasi kegiatan, standar teknis, dan kesiapan pelaksanaan.</li>
        <li>Skor 1 sampai 5 diisi berdasarkan hasil verifikasi tersebut, bukan berdasarkan popularitas usulan.</li>
        <li>Aplikasi menghitung nilai tiap kriteria, nilai akhir, kategori prioritas, dan peringkat.</li>
        <li>Hasil peringkat menjadi bahan penyusunan Renja dan RKPD, serta dapat diunduh sebagai CSV.</li>
      </ol>
    </div>

    <div class="kartu p-5">
      <p class="font-semibold text-sm text-slate-800 mb-3">Kriteria dan bobot</p>
      <table class="data w-full">
        <thead><tr><th>Kode</th><th>Kriteria</th><th class="text-right">Bobot</th><th>Yang dinilai</th></tr></thead>
        <tbody>
        <?php foreach ($kriteria as $kr): ?>
          <tr>
            <td class="text-slate-500"><?= e($kr['kode']) ?></td>
            <td class="font-medium"><?= e($kr['nama']) ?></td>
            <td class="text-right"><?= angka($kr['bobot'], 0) ?></td>
            <td class="text-slate-600"><?= e($kr['deskripsi']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="bg-slate-50"><td colspan="2" class="font-semibold">Total</td>
          <td class="text-right font-bold"><?= angka(total_bobot($koneksi), 0) ?></td><td></td></tr></tfoot>
      </table>
    </div>

    <div class="kartu p-5">
      <p class="font-semibold text-sm text-slate-800 mb-3">Rumus perhitungan</p>
      <p class="text-sm text-slate-700 leading-relaxed">
        Nilai satu kriteria dihitung dengan membagi skor dengan skala maksimum, lalu mengalikannya dengan bobot kriteria.
        Nilai akhir adalah jumlah nilai seluruh kriteria dan berskala 0 sampai 100 selama total bobot tetap 100.
      </p>
      <div class="mt-3 bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm text-slate-800">
        <p class="font-mono">nilai kriteria = (skor &divide; skala maksimum) &times; bobot</p>
        <p class="font-mono mt-1">nilai akhir = jumlah seluruh nilai kriteria</p>
      </div>
      <p class="text-sm text-slate-700 mt-3 leading-relaxed">
        Contoh: usulan dengan skor urgensi 5, dampak 3, prioritas 3, teknis 2, partisipasi 4, dan kesiapan 5
        menghasilkan nilai 25 + 12 + 9 + 8 + 8 + 10 sama dengan 72, sehingga masuk kategori prioritas sedang.
      </p>
      <p class="text-sm text-slate-700 mt-3 leading-relaxed">
        Bobot yang berlaku saat penilaian disimpan ikut dicatat pada setiap baris rincian skor.
        Perubahan bobot di kemudian hari memicu perhitungan ulang, dan rincian lama tetap dapat ditelusuri melalui jejak aktivitas.
      </p>
    </div>
  </div>

  <div class="space-y-5">
    <div class="kartu p-5">
      <p class="font-semibold text-sm text-slate-800 mb-3">Arti skor 1 sampai 5</p>
      <ul class="text-sm text-slate-700 space-y-2">
        <?php foreach ($panduan as $p): ?>
          <li class="flex gap-3">
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-white text-xs font-bold shrink-0"
                  style="background:#44403B"><?= (int)$p['skor'] ?></span>
            <span><?= e($p['keterangan']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="kartu p-5">
      <p class="font-semibold text-sm text-slate-800 mb-3">Kategori prioritas</p>
      <ul class="text-sm space-y-2">
        <?php foreach ($kategori as $k): ?>
          <li class="flex items-center justify-between">
            <span class="lencana" style="background:<?= e($k['warna']) ?>"><?= e($k['nama']) ?></span>
            <span class="text-slate-600 text-xs">nilai <?= angka($k['batas_bawah'], 0) ?> ke atas</span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="kartu p-5">
      <p class="font-semibold text-sm text-slate-800 mb-3">Peran akun</p>
      <ul class="text-sm text-slate-700 space-y-2">
        <li><b>User kecamatan</b> menginput semua usulan prioritas untuk setiap desa di kecamatannya dan memantau progres penilaian.</li>
        <li><b>User desa</b> cukup menilai usulan desa lain di kecamatannya. Akun desa tidak menginput usulan.</li>
        <li><b>Verifikator</b> memverifikasi, mengubah status, mengisi nilai awal bila perlu, dan mengimpor data.</li>
        <li><b>Administrator</b> mengatur bobot, data master, akun, dan melihat jejak aktivitas.</li>
        <li><b>User OPD</b> mengisi realisasi indikator program GERBANG yang menjadi tanggung jawab perangkat daerahnya.</li>
      </ul>
    </div>

    <div class="kartu p-5">
      <p class="font-semibold text-sm text-slate-800 mb-3">Penilaian antar-desa</p>
      <ol class="text-sm text-slate-700 space-y-2 list-decimal pl-5">
        <li>Operator kecamatan menambah usulan untuk setiap desa dengan memilih dari <b>Kamus usulan</b>. Nama, permasalahan, bidang, OPD, dan satuan terisi otomatis sehingga tidak ada salah tulis. Usulan yang sama tidak bisa dicatat dua kali untuk satu desa.</li>
        <li>Setiap desa/kelurahan menilai usulan desa lain dalam kecamatan yang sama. Usulan untuk desa sendiri tidak dapat dinilai.</li>
        <li>Nilai satu desa dihitung dengan rumus yang sama: skor dibagi skala maksimum lalu dikali bobot, dijumlahkan.</li>
        <li>Nilai akhir usulan adalah rata-rata nilai dari semua desa yang sudah menilai, dan diperbarui seketika.</li>
        <li>Setiap desa memiliki <b><?= JUMLAH_PRIORITAS_DESA ?> usulan prioritas</b> yang ditetapkan operator kecamatan di menu <b>Prioritas desa</b>. Hanya kelima usulan itu yang dinilai desa lain dan ditampilkan ke publik.</li>
        <li>Dalam satu kecamatan berisi 10 desa, setiap usulan dinilai oleh 9 desa lainnya. Usulan milik desa sendiri tidak dinilai.</li>
        <li>Usulan di luar kelima slot tersebut tersimpan sebagai <b>cadangan</b>: tidak dinilai dan tidak tampil di publik, tetapi bisa dimasukkan kembali kapan saja.</li>
        <li>Selama belum ada desa yang menilai, nilai awal dari verifikator atau impor Excel dipakai sementara.</li>
        <li>Penilaian dapat diubah selama usulan berstatus Draft. Setelah Terverifikasi atau Ditolak, penilaian ditutup.</li>
        <li>Nilai per desa hanya terlihat oleh administrator dan verifikator. Publik melihat rata-rata dan jumlah penilai.</li>
      </ol>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
