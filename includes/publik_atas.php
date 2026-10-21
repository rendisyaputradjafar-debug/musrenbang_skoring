<?php
/**
 * Kepala dan navbar bersama untuk halaman publik.
 * Variabel yang dapat diatur sebelum include:
 *   $judul_publik       judul tab browser
 *   $menu_publik_aktif  'beranda' | 'jelajah' | 'prioritas' | 'penilaian'
 */
$sudah_masuk       = !empty($_SESSION['id_user']);
$judul_publik      = $judul_publik ?? 'Data prioritas usulan Musrenbang';
$menu_publik_aktif = $menu_publik_aktif ?? 'beranda';
$menu_publik = [
    'beranda'   => ['Beranda',          'index.php'],
    'jelajah'   => ['Lihat usulan',     'lihat.php'],
    'papan'     => ['Papan pantau',     'papan.php'],
    'program'   => ['Program GERBANG',  'program.php'],
    'penilaian' => ['Cara penilaian',   'index.php#penilaian'],
];
$tujuan_masuk = 'dashboard.php';
if ($sudah_masuk && ($_SESSION['role'] ?? '') === 'desa') {
    $tujuan_masuk = 'penilaian.php';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul_publik) ?> - <?= APP_NAMA ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/publik.css">
<script>
/**
 * Menghubungkan pilihan kecamatan dan desa pada halaman publik.
 * Hanya desa milik kecamatan terpilih yang ditampilkan.
 */
function hubungkanDesa(idKec, idDesa, peta) {
  const kec = document.getElementById(idKec);
  const desa = document.getElementById(idDesa);
  if (!kec || !desa) { return; }
  const awal = desa.dataset.terpilih || '';
  function isi(pertama) {
    const daftar = peta[kec.value] || [];
    desa.innerHTML = '';
    const o = document.createElement('option');
    o.value = '';
    o.textContent = kec.value && kec.value !== '0'
      ? (daftar.length ? 'Semua desa di kecamatan ini' : 'Belum ada data desa')
      : 'Pilih kecamatan terlebih dahulu';
    desa.appendChild(o);
    daftar.forEach(function (n) {
      const op = document.createElement('option');
      op.value = n;
      op.textContent = n;
      if (pertama && n === awal) { op.selected = true; }
      desa.appendChild(op);
    });
    desa.disabled = !kec.value || kec.value === '0' || !daftar.length;
  }
  kec.addEventListener('change', function () { isi(false); });
  isi(true);
}
</script>
</head>
<body>

<a href="#isi-utama" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 bg-white px-3 py-2 rounded">Lompat ke isi</a>

<header class="nav sticky top-0 z-30">
  <div class="wadah h-16 flex items-center gap-6">
    <a href="index.php" class="flex items-center gap-3 shrink-0">
      <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true">
        <rect width="34" height="34" rx="9" fill="#44403B"/>
        <rect x="8"  y="18" width="4" height="8"  rx="1" fill="#E4E1D9"/>
        <rect x="15" y="13" width="4" height="13" rx="1" fill="#E4E1D9"/>
        <rect x="22" y="8"  width="4" height="18" rx="1" fill="#B08E4F"/>
      </svg>
      <span class="leading-tight">
        <span class="block text-white font-bold text-[15px]"><?= APP_NAMA ?></span>
        <span class="hidden sm:block text-[11px]" style="color:#A9A49A"><?= APP_INSTANSI ?></span>
      </span>
    </a>

    <nav class="hidden md:flex items-center gap-6 ml-4" aria-label="Menu utama">
      <?php foreach ($menu_publik as $kunci => $m): ?>
        <a class="menu <?= $menu_publik_aktif === $kunci ? 'aktif' : '' ?>" href="<?= $m[1] ?>"
           <?= $menu_publik_aktif === $kunci ? 'aria-current="page"' : '' ?>><?= $m[0] ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="ml-auto flex items-center gap-4">
      <span data-langsung-status class="hidden lg:inline-flex"></span>
      <?php if ($sudah_masuk): ?>
        <span class="hidden xl:block text-xs text-right" style="color:#A9A49A"><?= e($_SESSION['nama_lengkap'] ?? '') ?></span>
        <a href="<?= $tujuan_masuk ?>" class="btn-masuk"><?= $tujuan_masuk === 'penilaian.php' ? 'Mulai menilai' : 'Buka dashboard' ?></a>
      <?php else: ?>
        <a href="login.php" class="btn-masuk">Masuk</a>
      <?php endif; ?>
      <button type="button" id="tombolMenu" class="md:hidden text-white p-2 -mr-2" aria-expanded="false" aria-controls="menuHp" aria-label="Buka menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
    </div>
  </div>
  <nav id="menuHp" class="md:hidden hidden border-t border-white/10 px-5 py-3 space-y-2" aria-label="Menu ponsel">
    <?php foreach ($menu_publik as $kunci => $m): ?>
      <a class="menu block <?= $menu_publik_aktif === $kunci ? 'aktif' : '' ?>" href="<?= $m[1] ?>"><?= $m[0] ?></a>
    <?php endforeach; ?>
  </nav>
</header>
