<?php
/**
 * Konfigurasi dan koneksi basis data.
 * Ubah nilai di bawah bila server basis data berbeda.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAMA',      'SIMUSREN Banggai');
define('APP_SUBJUDUL',  'Sistem Penjaringan dan Skoring Usulan Musrenbang');
define('APP_INSTANSI',  'Bappeda Kabupaten Banggai');
define('APP_TAHUN_DEFAULT', 2027);
/* Jumlah usulan prioritas yang ditampilkan untuk setiap desa (nilai tertinggi) */
define('JUMLAH_PRIORITAS_DESA', 5);

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_nama = getenv('DB_NAME') ?: 'db_musrenbang';

date_default_timezone_set('Asia/Makassar');
mysqli_report(MYSQLI_REPORT_OFF);

$koneksi = @mysqli_connect($db_host, $db_user, $db_pass, $db_nama);
if (!$koneksi) {
    die('<div style="font-family:system-ui;padding:24px;max-width:640px;margin:60px auto;'
      . 'border:1px solid #e2e8f0;border-radius:12px">'
      . '<h2 style="margin:0 0 8px">Basis data belum terhubung</h2>'
      . '<p style="color:#475569;line-height:1.6">Jalankan MySQL pada XAMPP, lalu impor berkas '
      . '<code>database/db_musrenbang.sql</code> melalui phpMyAdmin. Sesuaikan juga nama pengguna '
      . 'dan kata sandi pada <code>config/koneksi.php</code>.</p>'
      . '<p style="color:#9f1239">Pesan sistem: ' . htmlspecialchars(mysqli_connect_error()) . '</p>'
      . '</div>');
}
mysqli_set_charset($koneksi, 'utf8mb4');
/* Samakan zona waktu MySQL dengan PHP (WITA) agar waktu penilaian konsisten */
mysqli_query($koneksi, "SET time_zone = '+08:00'");
