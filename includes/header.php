<?php
require_once __DIR__ . '/fungsi.php';
wajib_login();

$halaman_aktif = $halaman_aktif ?? '';
$judul_halaman = $judul_halaman ?? 'Beranda';
$hdr_peran_saya    = $_SESSION['role'] ?? '';

/* Ikon garis sederhana (24x24) */
$hdr_ikon = [
    'beranda'  => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/>',
    'tambah'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
    'daftar'   => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
    'nilai'    => '<path d="M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z"/>',
    'peringkat'=> '<path d="M6 20V10M12 20V4M18 20v-6"/>',
    'impor'    => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 21h16"/>',
    'bobot'    => '<path d="M4 21V14M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
    'kamus'    => '<path d="M4 4h11a3 3 0 013 3v13H7a3 3 0 01-3-3z"/><path d="M4 17a3 3 0 013-3h11"/>',
    'master'   => '<rect x="3" y="4" width="18" height="6" rx="1"/><rect x="3" y="14" width="18" height="6" rx="1"/>',
    'pengguna' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0113 0"/><path d="M16 4.5a3.5 3.5 0 010 7M22 20a6.5 6.5 0 00-4-6"/>',
    'riwayat'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'bantuan'  => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 115 .5c0 1.5-2.5 2-2.5 3.5M12 17h.01"/>',
    'situs'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',
    'keluar'   => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
    'gerbang'  => '<path d="M4 21V9l8-6 8 6v12"/><path d="M9 21v-7h6v7"/>',
];
function ikon_svg(string $isi, int $ukuran = 20): string
{
    return '<svg width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $isi . '</svg>';
}

/* Menu berkelompok dengan bahasa sehari-hari */
$hdr_semua = ['admin', 'verifikator', 'operator', 'desa'];
$hdr_semua_plus = ['admin', 'verifikator', 'operator', 'desa', 'opd'];
$hdr_menu = [
    'Utama' => [
        ['kunci' => 'dashboard', 'label' => 'Beranda',       'ikon' => 'beranda',   'url' => 'dashboard.php',   'peran' => $hdr_semua],
        ['kunci' => 'penilaian', 'label' => $hdr_peran_saya === 'desa' ? 'Nilai usulan' : 'Pantau penilaian', 'ikon' => 'nilai', 'url' => 'penilaian.php', 'peran' => $hdr_semua],
        ['kunci' => 'prioritas', 'label' => 'Prioritas desa', 'ikon' => 'daftar', 'url' => 'prioritas.php', 'peran' => ['admin', 'verifikator', 'operator']],
        ['kunci' => 'input',     'label' => 'Tambah usulan', 'ikon' => 'tambah',    'url' => 'usulan_form.php', 'peran' => ['admin', 'verifikator', 'operator']],
        ['kunci' => 'usulan',    'label' => 'Daftar usulan', 'ikon' => 'daftar',    'url' => 'usulan.php',      'peran' => $hdr_semua],
        ['kunci' => 'ranking',   'label' => 'Peringkat',     'ikon' => 'peringkat', 'url' => 'ranking.php',     'peran' => $hdr_semua],
    ],
    'Evaluasi' => [
        ['kunci' => 'gerbang', 'label' => 'Evaluasi GERBANG', 'ikon' => 'gerbang', 'url' => 'gerbang.php', 'peran' => $hdr_semua_plus],
    ],
    'Pengaturan' => [
        ['kunci' => 'kamus',    'label' => 'Kamus usulan',    'ikon' => 'kamus',    'url' => 'kamus.php',    'peran' => ['admin']],
        ['kunci' => 'master',   'label' => 'Data master',     'ikon' => 'master',   'url' => 'master.php',   'peran' => ['admin']],
        ['kunci' => 'pengguna', 'label' => 'Pengguna',        'ikon' => 'pengguna', 'url' => 'pengguna.php', 'peran' => ['admin']],
        ['kunci' => 'bobot',    'label' => 'Bobot kriteria',  'ikon' => 'bobot',    'url' => 'bobot.php',    'peran' => ['admin']],
        ['kunci' => 'impor',    'label' => 'Impor Excel',     'ikon' => 'impor',    'url' => 'impor.php',    'peran' => ['admin', 'verifikator']],
        ['kunci' => 'log',      'label' => 'Riwayat aktivitas','ikon' => 'riwayat', 'url' => 'log.php',      'peran' => ['admin']],
    ],
    'Lainnya' => [
        ['kunci' => 'panduan', 'label' => 'Bantuan',         'ikon' => 'bantuan', 'url' => 'panduan.php', 'peran' => $hdr_semua_plus],
        ['kunci' => 'publik',  'label' => 'Lihat situs publik', 'ikon' => 'situs', 'url' => 'index.php',  'peran' => $hdr_semua_plus],
    ],
];

$hdr_peran = [
    'admin' => 'Administrator', 'verifikator' => 'Verifikator',
    'operator' => kec_user() > 0 ? 'Operator kecamatan' : 'Operator', 'desa' => 'Operator desa',
    'opd' => 'Perangkat daerah',
][$hdr_peran_saya] ?? $hdr_peran_saya;
$hdr_wilayah = opd_user() ? nama_opd_user() : (desa_user()
    ? nama_desa_user() . ', Kec. ' . ($_SESSION['nama_kecamatan'] ?? '')
    : (kec_user() ? 'Kecamatan ' . ($_SESSION['nama_kecamatan'] ?? '') : 'Kabupaten Banggai'));
$hdr_inisial = strtoupper(mb_substr(trim($_SESSION['nama_lengkap'] ?? 'U'), 0, 1));

$pesan = pesan_ambil();

ob_start();
foreach ($hdr_menu as $hdr_judul => $hdr_item) {
    $hdr_tampil = array_filter($hdr_item, function ($hdr_m) { return boleh(...$hdr_m['peran']); });
    if (!$hdr_tampil) { continue; }
    echo '<p class="menu-judul">' . e($hdr_judul) . '</p>';
    foreach ($hdr_tampil as $hdr_m) {
        $hdr_aktif = $halaman_aktif === $hdr_m['kunci'];
        echo '<a href="' . $hdr_m['url'] . '" class="menu-item' . ($hdr_aktif ? ' aktif' : '') . '"' . ($hdr_aktif ? ' aria-current="page"' : '') . '>'
           . ikon_svg($hdr_ikon[$hdr_m['ikon']]) . '<span>' . e($hdr_m['label']) . '</span></a>';
    }
}
$hdr_menu_html = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul_halaman) ?> - <?= APP_NAMA ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="assets/aplikasi.css">
<script>
/**
 * Menghubungkan pilihan kecamatan dengan pilihan desa.
 * peta: { id_kecamatan: ["Desa A", "Desa B"] }
 * Hanya desa milik kecamatan terpilih yang ditampilkan.
 */
function pasangDesa(idKec, idDesa, peta, labelKosong, labelTanpaKec) {
  const kec = document.getElementById(idKec);
  const desa = document.getElementById(idDesa);
  if (!kec || !desa) { return; }
  const awal = desa.dataset.terpilih || '';
  function isi() {
    const daftar = peta[kec.value] || [];
    desa.innerHTML = '';
    const o = document.createElement('option');
    o.value = '';
    o.textContent = kec.value && kec.value !== '0' ? labelKosong : labelTanpaKec;
    desa.appendChild(o);
    daftar.forEach(function (n) {
      const op = document.createElement('option');
      op.value = n; op.textContent = n;
      if (n === awal) { op.selected = true; }
      desa.appendChild(op);
    });
    desa.disabled = !(kec.value && kec.value !== '0');
  }
  kec.addEventListener('change', function () { desa.dataset.terpilih = ''; isi(); });
  isi();
}
</script>
</head>
<body>
<a href="#isi-utama" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 bg-white px-3 py-2 rounded">Lompat ke isi</a>

<div class="flex min-h-screen">

  <aside class="sisi hidden lg:flex" aria-label="Menu aplikasi">
    <a href="dashboard.php" class="merek">
      <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true"><rect width="34" height="34" rx="9" fill="#44403B"/><rect x="8" y="18" width="4" height="8" rx="1" fill="#E4E1D9"/><rect x="15" y="13" width="4" height="13" rx="1" fill="#E4E1D9"/><rect x="22" y="8" width="4" height="18" rx="1" fill="#B08E4F"/></svg>
      <span><b><?= APP_NAMA ?></b><small>Musrenbang Banggai</small></span>
    </a>
    <nav class="flex-1 overflow-y-auto"><?= $hdr_menu_html ?></nav>
    <div class="profil">
      <span class="avatar" aria-hidden="true"><?= e($hdr_inisial) ?></span>
      <span class="min-w-0">
        <b class="block truncate"><?= e($_SESSION['nama_lengkap'] ?? '') ?></b>
        <small class="block truncate"><?= e($hdr_peran) ?></small>
      </span>
      <a href="logout.php" class="keluar" title="Keluar" aria-label="Keluar"><?= ikon_svg($hdr_ikon['keluar'], 18) ?></a>
    </div>
  </aside>

  <!-- Menu ponsel -->
  <div id="lapisMenu" class="lapis-menu" hidden></div>
  <aside id="menuPonsel" class="sisi sisi-ponsel" aria-label="Menu aplikasi" hidden>
    <div class="flex items-center justify-between mb-2">
      <span class="merek" style="margin:0"><span><b><?= APP_NAMA ?></b><small><?= e($hdr_wilayah) ?></small></span></span>
      <button type="button" id="tutupMenu" class="text-white p-2" aria-label="Tutup menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </div>
    <nav class="flex-1 overflow-y-auto"><?= $hdr_menu_html ?></nav>
    <a href="logout.php" class="menu-item mt-2"><?= ikon_svg($hdr_ikon['keluar']) ?><span>Keluar</span></a>
  </aside>

  <main class="flex-1 min-w-0 flex flex-col">
    <header class="atas cetak-sembunyi">
      <button type="button" id="bukaMenu" class="lg:hidden tombol-ikon" aria-label="Buka menu" aria-controls="menuPonsel" aria-expanded="false">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <div class="min-w-0">
        <h1 class="judul-hal truncate"><?= e($judul_halaman) ?></h1>
        <p class="text-xs text-slate-500 truncate"><?= e($hdr_wilayah) ?></p>
      </div>
      <div class="ml-auto flex items-center gap-3">
        <?php if (!empty($halaman_langsung)): ?>
          <span data-langsung-status class="hidden md:inline-flex"></span>
        <?php endif; ?>
        <span class="chip-tahun" title="Tahun rencana">Tahun <?= e($_GET['tahun'] ?? APP_TAHUN_DEFAULT) ?></span>
      </div>
    </header>

    <div class="isi" id="isi-utama">
    <?php if ($pesan): ?>
      <div role="status" class="pesan mb-5 rounded-xl px-4 py-3 text-sm <?=
        $pesan['jenis'] === 'sukses' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
        : ($pesan['jenis'] === 'galat' ? 'bg-rose-50 text-rose-800 border border-rose-200'
        : 'bg-amber-50 text-amber-800 border border-amber-200') ?>">
        <?= e($pesan['teks']) ?>
      </div>
    <?php endif; ?>
