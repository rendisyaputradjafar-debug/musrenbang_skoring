<?php
require_once __DIR__ . '/includes/fungsi.php';
if (!empty($_SESSION['id_user'])) {
    catat_log($koneksi, 'keluar', 'tb_user', 'Keluar dari aplikasi');
}
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
