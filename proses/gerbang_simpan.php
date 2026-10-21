<?php
/**
 * Menyimpan realisasi indikator GERBANG dan penataan indikator oleh admin.
 */
require_once __DIR__ . '/../includes/fungsi.php';
wajib_login();
periksa_csrf();

function balik_gerbang(string $jenis, string $pesan, string $tujuan)
{
    pesan_set($jenis, $pesan);
    header('Location: ../' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../gerbang.php');
    exit;
}

$aksi = $_POST['aksi'] ?? '';
$kembali = (string)($_POST['kembali'] ?? 'gerbang.php');
if (!preg_match('/^gerbang\.php(\?[\w=&.-]*)?$/', $kembali)) {
    $kembali = 'gerbang.php';
}

if ($aksi === 'realisasi') {
    $id_i      = (int)($_POST['id_indikator'] ?? 0);
    $triwulan  = (int)($_POST['triwulan'] ?? 0);
    $realisasi = (float)str_replace(',', '.', (string)($_POST['realisasi'] ?? ''));
    $catatan   = mb_substr(trim((string)($_POST['catatan'] ?? '')), 0, 400);

    if ($triwulan < 1 || $triwulan > 4) {
        balik_gerbang('galat', 'Triwulan harus 1 sampai 4.', $kembali);
    }
    if ($realisasi < 0) {
        balik_gerbang('galat', 'Realisasi tidak boleh negatif.', $kembali);
    }

    $b = jalankan($koneksi, "SELECT i.*, g.nama AS gerbang FROM tb_gerbang_indikator i
        JOIN tb_gerbang g ON g.id_gerbang = i.id_gerbang WHERE i.id_indikator = ?", 'i', [$id_i]);
    if (!$b) {
        balik_gerbang('galat', 'Indikator tidak ditemukan.', $kembali);
    }
    $i = $b[0];

    /* Akun perangkat daerah hanya boleh mengisi indikator miliknya */
    $boleh = boleh('admin', 'verifikator') || (opd_user() > 0 && (int)$i['id_opd'] === opd_user());
    if (!$boleh) {
        balik_gerbang('galat', 'Indikator ini diisi oleh perangkat daerah penanggung jawabnya.', $kembali);
    }

    $stmt = mysqli_prepare($koneksi, "INSERT INTO tb_gerbang_capaian
            (id_indikator, triwulan, realisasi, catatan, id_user)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE realisasi = VALUES(realisasi), catatan = VALUES(catatan),
                                id_user = VALUES(id_user), diubah_pada = NOW()");
    $id_user = (int)$_SESSION['id_user'];
    mysqli_stmt_bind_param($stmt, 'iidsi', $id_i, $triwulan, $realisasi, $catatan, $id_user);
    if (!mysqli_stmt_execute($stmt)) {
        balik_gerbang('galat', 'Realisasi gagal disimpan: ' . mysqli_error($koneksi), $kembali);
    }
    mysqli_stmt_close($stmt);

    $persen = (float)$i['target'] > 0 ? min(100, $realisasi / (float)$i['target'] * 100) : 0;
    catat_log($koneksi, 'isi realisasi gerbang', 'tb_gerbang_indikator#' . $id_i,
        $i['gerbang'] . ', TW' . $triwulan . ', realisasi ' . $realisasi);
    balik_gerbang('sukses', 'Realisasi "' . $i['nama'] . '" triwulan ' . $triwulan . ' tersimpan. Capaian indikator '
        . angka($persen, 1) . '%. Halaman publik ikut diperbarui.', $kembali);
}

if ($aksi === 'indikator') {
    wajib_peran('admin');
    $id_g   = (int)($_POST['id_gerbang'] ?? 0);
    $tahun  = (int)($_POST['tahun'] ?? date('Y'));
    $nama   = trim((string)($_POST['nama'] ?? ''));
    $satuan = trim((string)($_POST['satuan'] ?? 'Persen')) ?: 'Persen';
    $target = (float)str_replace(',', '.', (string)($_POST['target'] ?? '100'));
    $id_opd = ($_POST['id_opd'] ?? '') !== '' ? (int)$_POST['id_opd'] : null;

    if ($nama === '' || $target <= 0) {
        balik_gerbang('galat', 'Nama indikator wajib diisi dan target harus lebih dari nol.', $kembali);
    }
    $stmt = mysqli_prepare($koneksi, "INSERT INTO tb_gerbang_indikator
        (id_gerbang, id_opd, nama, satuan, target, tahun) VALUES (?,?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'iissdi', $id_g, $id_opd, $nama, $satuan, $target, $tahun);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    catat_log($koneksi, 'tambah indikator gerbang', 'tb_gerbang#' . $id_g, $nama);
    balik_gerbang('sukses', 'Indikator "' . $nama . '" ditambahkan untuk tahun ' . $tahun . '.', $kembali);
}

header('Location: ../gerbang.php');
