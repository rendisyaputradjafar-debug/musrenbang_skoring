<?php
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();
wajib_peran('admin', 'verifikator');

$halaman_aktif = 'impor';
$judul_halaman = 'Impor dari Excel';

$ringkasan = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['berkas'])) {
    periksa_csrf();
    $tahun = (int)($_POST['tahun'] ?? APP_TAHUN_DEFAULT);

    if ($_FILES['berkas']['error'] !== UPLOAD_ERR_OK) {
        pesan_set('galat', 'Berkas gagal diunggah. Ukuran berkas mungkin melebihi batas server.');
        header('Location: impor.php');
        exit;
    }

    /* Peta referensi, pencocokan tanpa membedakan huruf besar kecil */
    $peta = ['kec' => [], 'sektor' => [], 'sumber' => [], 'opd' => []];
    foreach (jalankan($koneksi, "SELECT id_kecamatan, nama FROM tb_kecamatan") as $r) {
        $peta['kec'][mb_strtolower(trim($r['nama']))] = (int)$r['id_kecamatan'];
    }
    foreach (jalankan($koneksi, "SELECT id_sektor, nama FROM tb_sektor") as $r) {
        $peta['sektor'][mb_strtolower(trim($r['nama']))] = (int)$r['id_sektor'];
    }
    foreach (jalankan($koneksi, "SELECT id_sumber, nama FROM tb_sumber_usulan") as $r) {
        $peta['sumber'][mb_strtolower(trim($r['nama']))] = (int)$r['id_sumber'];
    }
    foreach (jalankan($koneksi, "SELECT id_opd, nama FROM tb_opd") as $r) {
        $peta['opd'][mb_strtolower(trim($r['nama']))] = (int)$r['id_opd'];
    }

    $kriteria = ambil_kriteria($koneksi);

    $fh = fopen($_FILES['berkas']['tmp_name'], 'r');
    $baris_pertama = fgets($fh);
    $pemisah = substr_count($baris_pertama, ';') > substr_count($baris_pertama, ',') ? ';' : ',';
    rewind($fh);

    $judul = fgetcsv($fh, 0, $pemisah);
    if (!$judul) {
        pesan_set('galat', 'Berkas kosong atau bukan CSV.');
        header('Location: impor.php');
        exit;
    }
    $judul = array_map(function ($t) {
        return mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string)$t)));
    }, $judul);

    $kol = function (array $kandidat) use ($judul) {
        foreach ($kandidat as $c) {
            $i = array_search(mb_strtolower($c), $judul, true);
            if ($i !== false) { return $i; }
        }
        foreach ($judul as $i => $j) {
            foreach ($kandidat as $c) {
                if ($j !== '' && strpos($j, mb_strtolower($c)) === 0) { return $i; }
            }
        }
        return null;
    };

    $i_kec    = $kol(['kecamatan']);
    $i_desa   = $kol(['desa/kelurahan', 'desa']);
    $i_sumber = $kol(['sumber usulan']);
    $i_sektor = $kol(['sektor']);
    $i_opd    = $kol(['opd penanggung jawab', 'opd']);
    $i_nama   = $kol(['nama usulan']);
    $i_uraian = $kol(['uraian masalah/kondisi eksisting', 'uraian masalah', 'uraian']);
    $i_vol    = $kol(['volume']);
    $i_satuan = $kol(['satuan']);
    $i_angg   = $kol(['estimasi anggaran (rp)', 'estimasi anggaran']);

    $i_skor = [
        $kol(['skor urgensi (1-5)', 'skor urgensi']),
        $kol(['skor dampak/manfaat (1-5)', 'skor dampak']),
        $kol(['skor prioritas pembangunan (1-5)', 'skor prioritas']),
        $kol(['skor kelayakan teknis/kewenangan (1-5)', 'skor kelayakan']),
        $kol(['skor partisipasi (1-5)', 'skor partisipasi']),
        $kol(['skor kesiapan (1-5)', 'skor kesiapan']),
    ];

    /* Kamus: nama usulan (dan permasalahan bila ada) harus cocok dengan kamus */
    $kamus_nama = [];
    $kamus_pasangan = [];
    foreach (jalankan($koneksi, "SELECT * FROM tb_kamus_usulan WHERE aktif = 1") as $km) {
        $kamus_nama[mb_strtolower(trim($km['nama_usulan']))][] = $km;
        $kamus_pasangan[mb_strtolower(trim($km['nama_usulan'])) . '|' . mb_strtolower(trim($km['permasalahan']))] = $km;
    }

    $masuk = 0;
    $lewat = 0;
    $catatan = [];
    $nomor = 1;

    while (($b = fgetcsv($fh, 0, $pemisah)) !== false) {
        $nomor++;
        $ambil = function ($i) use ($b) {
            return $i !== null && isset($b[$i]) ? trim((string)$b[$i]) : '';
        };

        $nama_usulan = $ambil($i_nama);
        if ($nama_usulan === '') { continue; }

        $nk = mb_strtolower($ambil($i_kec));
        $nu = mb_strtolower($ambil($i_sumber));

        /* Cocokkan ke kamus: pasangan nama + permasalahan, atau nama saja bila hanya ada satu entri */
        $kunci_nama = mb_strtolower($nama_usulan);
        $km = $kamus_pasangan[$kunci_nama . '|' . mb_strtolower($ambil($i_uraian))] ?? null;
        if (!$km && isset($kamus_nama[$kunci_nama]) && count($kamus_nama[$kunci_nama]) === 1) {
            $km = $kamus_nama[$kunci_nama][0];
        }

        $gagal = [];
        if (!isset($peta['kec'][$nk]))    { $gagal[] = 'kecamatan "' . $ambil($i_kec) . '"'; }
        if (!isset($peta['sumber'][$nu])) { $gagal[] = 'sumber usulan "' . $ambil($i_sumber) . '"'; }
        if (!$km)                         { $gagal[] = 'usulan "' . $nama_usulan . '" tidak ada di kamus'; }

        if ($gagal) {
            $lewat++;
            if (count($catatan) < 25) {
                $catatan[] = 'Baris ' . $nomor . ' dilewati, tidak cocok dengan referensi: ' . implode(', ', $gagal);
            }
            continue;
        }

        $id_kec  = $peta['kec'][$nk];
        $desa    = $ambil($i_desa) !== '' ? $ambil($i_desa) : '-';
        $vol     = (float)str_replace(',', '.', str_replace('.', '', $ambil($i_vol)));
        $angg    = (float)preg_replace('/[^0-9]/', '', $ambil($i_angg));
        $satuan  = $km['satuan'];
        $uraian  = $km['permasalahan'];
        $nama_usulan = $km['nama_usulan'];
        $id_kamus = (int)$km['id_kamus'];

        $kode = buat_kode_usulan($koneksi, $tahun, $ambil($i_kec));
        $id_user = (int)$_SESSION['id_user'];
        $status = 'Draft';
        $catatan_v = '';
        $id_sumber = $peta['sumber'][$nu];
        $id_sektor = (int)$km['id_sektor'];
        $id_opd    = (int)$km['id_opd'];

        $stmt = mysqli_prepare($koneksi, "INSERT INTO tb_usulan
            (kode_usulan, tahun, id_kecamatan, desa, id_sumber, id_sektor, id_opd, id_kamus,
             nama_usulan, uraian_masalah, volume, satuan, estimasi_anggaran,
             status, catatan_verifikasi, id_user)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'siisiiiissdsdssi',
            $kode, $tahun, $id_kec, $desa, $id_sumber, $id_sektor, $id_opd, $id_kamus,
            $nama_usulan, $uraian, $vol, $satuan, $angg, $status, $catatan_v, $id_user);
        mysqli_stmt_execute($stmt);
        $id_baru = mysqli_insert_id($koneksi);
        mysqli_stmt_close($stmt);

        $skor = [];
        foreach ($kriteria as $n => $kr) {
            $nilai = isset($i_skor[$n]) ? (int)$ambil($i_skor[$n]) : 0;
            $skor[(int)$kr['id_kriteria']] = $nilai > 0 ? $nilai : 1;
        }
        simpan_skor($koneksi, $id_baru, $skor);
        $masuk++;
    }
    fclose($fh);

    catat_log($koneksi, 'impor usulan', 'tb_usulan', $masuk . ' baris masuk, ' . $lewat . ' dilewati');
    $ringkasan = ['masuk' => $masuk, 'lewat' => $lewat, 'catatan' => $catatan];
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($ringkasan): ?>
  <div class="kartu p-5 mb-5">
    <p class="font-semibold text-sm text-slate-800 mb-2">Hasil impor</p>
    <p class="text-sm text-slate-700"><?= $ringkasan['masuk'] ?> usulan tersimpan,
       <?= $ringkasan['lewat'] ?> baris dilewati.</p>
    <?php if ($ringkasan['catatan']): ?>
      <ul class="mt-3 text-xs text-rose-700 space-y-1">
        <?php foreach ($ringkasan['catatan'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?>
      </ul>
      <p class="text-xs text-slate-500 mt-2">
        Perbaiki penulisan pada berkas. Nama usulan harus sama persis dengan menu Kamus usulan; kecamatan dan
        sumber usulan harus sama dengan menu Data master. Lalu ulangi impor.
      </p>
    <?php endif; ?>
    <a href="usulan.php" class="tombol inline-block mt-4">Lihat daftar usulan</a>
  </div>
<?php endif; ?>

<div class="grid gap-5 lg:grid-cols-2">
  <form method="post" enctype="multipart/form-data" class="kartu p-5">
    <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
    <p class="font-semibold text-sm text-slate-800 mb-3">Unggah berkas CSV</p>

    <label class="block text-xs text-slate-600 mb-1">Tahun rencana</label>
    <input type="number" name="tahun" value="<?= APP_TAHUN_DEFAULT ?>" class="isian mb-3">

    <label class="block text-xs text-slate-600 mb-1">Berkas CSV</label>
    <input type="file" name="berkas" accept=".csv,text/csv" required class="isian mb-4">

    <button class="tombol">Impor sekarang</button>
  </form>

  <div class="kartu p-5">
    <p class="font-semibold text-sm text-slate-800 mb-3">Cara menyiapkan berkas</p>
    <ol class="text-sm text-slate-600 space-y-2 list-decimal pl-5 leading-relaxed">
      <li>Buka lembar kerja <b>Sistem Skoring Musrenbang</b> di Excel, pilih sheet <b>Input Usulan</b>.</li>
      <li>Simpan sebagai <b>CSV UTF-8 (Comma delimited)</b>.</li>
      <li>Pastikan baris pertama tetap berisi judul kolom asli.</li>
      <li>Unggah berkas melalui formulir di sebelah kiri.</li>
    </ol>

    <p class="font-semibold text-sm text-slate-800 mt-5 mb-2">Kolom yang dibaca</p>
    <p class="text-xs text-slate-600 leading-relaxed">
      Kecamatan, Desa/Kelurahan, Sumber Usulan, Sektor, OPD Penanggung Jawab, Nama Usulan,
      Uraian Masalah/Kondisi Eksisting, Volume, Satuan, Estimasi Anggaran (Rp), serta enam kolom Skor.
      Kolom Nilai, Total, Ranking, dan Kategori pada lembar kerja tidak perlu diisi karena dihitung ulang oleh aplikasi.
    </p>

    <p class="text-xs text-slate-500 mt-4">
      Nama kecamatan, sektor, sumber usulan, dan OPD harus sama persis dengan Data referensi.
      Baris yang tidak cocok dilewati dan dilaporkan agar tidak ada data yang masuk secara diam-diam.
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
