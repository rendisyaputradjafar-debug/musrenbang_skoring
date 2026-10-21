<?php
require_once __DIR__ . '/../includes/fungsi.php';
wajib_login();
wajib_peran('admin');

if (!hash_equals($_SESSION['csrf'] ?? '', $_GET['csrf'] ?? '')) {
    die('Permintaan ditolak.');
}

$id = (int)($_GET['id'] ?? 0);
$baris = jalankan($koneksi, "SELECT kode_usulan, tahun, id_kecamatan, desa FROM tb_usulan WHERE id_usulan = ?", 'i', [$id]);

if ($baris) {
    $stmt = mysqli_prepare($koneksi, "DELETE FROM tb_usulan WHERE id_usulan = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    /* Urutan prioritas desa dihitung ulang tanpa usulan yang dihapus */
    rapikan_urutan_prioritas($koneksi, (int)$baris[0]['tahun'], (int)$baris[0]['id_kecamatan'], $baris[0]['desa']);
    catat_log($koneksi, 'hapus usulan', 'tb_usulan#' . $id, $baris[0]['kode_usulan']);
    pesan_set('sukses', 'Usulan ' . $baris[0]['kode_usulan'] . ' dihapus.');
} else {
    pesan_set('galat', 'Usulan tidak ditemukan.');
}

$halaman_kembali = ($_GET['kembali'] ?? '') === 'ranking' ? 'ranking.php' : 'usulan.php';
header('Location: ../' . $halaman_kembali);
exit;