<?php
// Router untuk aplikasi PHP multi-halaman pada Vercel.
// Semua permintaan non-aset diarahkan ke sini; halaman PHP asli tetap di root proyek.
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($uriPath);
$path = preg_replace('#/+#', '/', $path);
$path = ltrim($path, '/');
if ($path === '' || substr($path, -1) === '/') {
    $path .= 'index.php';
}

// Tolak traversal dan akses langsung ke sumber konfigurasi/internal.
if (strpos($path, '..') !== false || strpos($path, "\\") !== false) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}
$blocked = ['config/', 'includes/', 'database/'];
foreach ($blocked as $prefix) {
    if (strpos($path, $prefix) === 0 && !preg_match('/^database\/[^\/]+\.csv$/i', $path)) {
        http_response_code(404);
        exit('Halaman tidak ditemukan.');
    }
}
if (!preg_match('/\.php$/i', $path)) {
    http_response_code(404);
    exit('Halaman tidak ditemukan.');
}
$target = realpath(__DIR__ . '/../' . $path);
$root = realpath(__DIR__ . '/..');
if (!$target || !$root || strpos($target, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($target)) {
    http_response_code(404);
    exit('Halaman tidak ditemukan.');
}
require $target;
