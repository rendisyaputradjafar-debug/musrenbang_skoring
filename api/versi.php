<?php
/**
 * Sidik jari data untuk pembaruan real time.
 * Halaman memanggil berkas ini berkala; bila nilai "versi" berubah,
 * halaman memuat ulang bagian datanya. Tidak memuat data sensitif.
 */
require_once __DIR__ . '/../config/koneksi.php';
session_write_close(); /* jangan mengunci sesi selama polling */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$bagian = [];
$kueri = [
    /* jumlah, id terakhir, waktu ubah terakhir, total nilai, status */
    "SELECT CONCAT_WS('|', COUNT(*), COALESCE(MAX(id_usulan),0), COALESCE(MAX(diubah_pada),''),
            COALESCE(SUM(total_nilai),0), COALESCE(SUM(status = 'Terverifikasi'),0), COALESCE(SUM(status = 'Ditolak'),0))
     FROM tb_usulan",
    /* perubahan terakhir yang tercatat, selain aktivitas masuk/keluar */
    "SELECT id_log FROM tb_log WHERE aksi NOT IN ('masuk','keluar','gagal masuk') ORDER BY id_log DESC LIMIT 1",
    /* menit berjalan: agar tulisan 'x menit lalu' dan penanda BARU ikut diperbarui */
    "SELECT DATE_FORMAT(NOW(), '%Y%m%d%H%i')",
    "SELECT CONCAT_WS('|', COUNT(*), COALESCE(SUM(aktif),0)) FROM tb_desa",
    /* penilaian antar-desa: jumlah, terakhir diubah, total nilai */
    "SELECT CONCAT_WS('|', COUNT(*), COALESCE(MAX(id_penilaian),0), COALESCE(MAX(diubah_pada),''), COALESCE(SUM(total_nilai),0))
     FROM tb_penilaian",
    /* capaian GERBANG */
    "SELECT CONCAT_WS('|', COUNT(*), COALESCE(MAX(diubah_pada),''), COALESCE(SUM(realisasi),0)) FROM tb_gerbang_capaian",
    "SELECT CONCAT_WS('|', COUNT(*), COALESCE(SUM(target),0)) FROM tb_gerbang_indikator WHERE aktif = 1",
    /* akun (agar status akun desa di halaman pemantauan ikut diperbarui) */
    "SELECT CONCAT_WS('|', COUNT(*), COALESCE(SUM(aktif),0)) FROM tb_user",
    "SELECT CONCAT_WS('|', COALESCE(SUM(bobot),0), COUNT(*)) FROM tb_kriteria WHERE aktif = 1",
    "SELECT GROUP_CONCAT(batas_bawah ORDER BY id_kategori) FROM tb_kategori",
];
foreach ($kueri as $sql) {
    $q = mysqli_query($koneksi, $sql);
    $bagian[] = $q ? (string)(mysqli_fetch_row($q)[0] ?? '') : '-';
}

echo json_encode([
    'versi' => md5(implode('#', $bagian)),
    'waktu' => date('H:i:s'),
]);
