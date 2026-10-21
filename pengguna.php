<?php
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();
wajib_peran('admin');

$halaman_aktif = 'pengguna';
$judul_halaman = 'User';

/* Nama pengguna otomatis untuk akun kecamatan: kec_luwuk_selatan */
function username_kecamatan(string $nama): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $nama), '_'));
    return 'kec_' . $slug;
}

/* Nama pengguna otomatis untuk akun desa: pagimana.pisou (sama dengan skrip SQL) */
function username_desa(string $kec, string $desa): string
{
    $slug = function ($t) {
        return strtolower(str_replace([' ', '-', "'", '.'], ['_', '_', '', ''], $t));
    };
    return $slug($kec) . '.' . $slug($desa);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksa_csrf();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $username = trim($_POST['username'] ?? '');
        $nama     = trim($_POST['nama_lengkap'] ?? '');
        $jabatan  = trim($_POST['jabatan'] ?? '');
        $role     = in_array($_POST['role'] ?? '', ['admin','verifikator','operator','desa'], true) ? $_POST['role'] : 'operator';
        $sandi    = $_POST['password'] ?? '';
        $id_kec   = ($_POST['id_kecamatan'] ?? '') !== '' ? (int)$_POST['id_kecamatan'] : null;
        $id_desa  = null;
        if ($role === 'desa') {
            $dd = jalankan($koneksi, "SELECT id_desa, id_kecamatan FROM tb_desa WHERE id_desa = ?", 'i', [(int)($_POST['id_desa'] ?? 0)]);
            $id_desa = $dd ? (int)$dd[0]['id_desa'] : null;
            $id_kec  = $dd ? (int)$dd[0]['id_kecamatan'] : null;
        }

        if ($username === '' || strlen($sandi) < 6) {
            pesan_set('galat', 'Nama pengguna wajib diisi dan kata sandi minimal enam karakter.');
        } elseif ($role === 'desa' && !$id_desa) {
            pesan_set('galat', 'Untuk peran desa, pilih desa/kelurahannya.');
        } else {
            $hash = password_hash($sandi, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($koneksi, "INSERT INTO tb_user
                (username, password, nama_lengkap, jabatan, role, id_kecamatan, id_desa) VALUES (?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'sssssii', $username, $hash, $nama, $jabatan, $role, $id_kec, $id_desa);
            if (mysqli_stmt_execute($stmt)) {
                catat_log($koneksi, 'tambah pengguna', 'tb_user', $username);
                pesan_set('sukses', 'Akun ' . $username . ' dibuat.');
            } else {
                pesan_set('galat', 'Akun gagal dibuat. Nama pengguna mungkin sudah dipakai.');
            }
            mysqli_stmt_close($stmt);
        }
    }

    /* Membuat satu akun operator untuk setiap kecamatan yang belum punya akun */
    if ($aksi === 'akun_kecamatan') {
        $sandi = $_POST['sandi_awal'] ?? '';
        if (strlen($sandi) < 6) {
            pesan_set('galat', 'Sandi awal minimal enam karakter.');
        } else {
            $hash = password_hash($sandi, PASSWORD_DEFAULT);
            $belum = jalankan($koneksi, "
                SELECT k.id_kecamatan, k.nama FROM tb_kecamatan k
                WHERE k.aktif = 1
                  AND NOT EXISTS (SELECT 1 FROM tb_user u WHERE u.id_kecamatan = k.id_kecamatan
                                  AND u.id_desa IS NULL AND u.role <> 'desa')
                ORDER BY k.nama");
            $dibuat = [];
            $stmt = mysqli_prepare($koneksi, "INSERT IGNORE INTO tb_user
                (username, password, nama_lengkap, jabatan, role, id_kecamatan)
                VALUES (?,?,?,'Operator Kecamatan','operator',?)");
            foreach ($belum as $k) {
                $un    = username_kecamatan($k['nama']);
                $nl    = 'Operator Kecamatan ' . $k['nama'];
                $idk   = (int)$k['id_kecamatan'];
                mysqli_stmt_bind_param($stmt, 'sssi', $un, $hash, $nl, $idk);
                mysqli_stmt_execute($stmt);
                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    $dibuat[] = $un;
                }
            }
            mysqli_stmt_close($stmt);
            catat_log($koneksi, 'buat akun kecamatan', 'tb_user', count($dibuat) . ' akun');
            pesan_set('sukses', $dibuat
                ? count($dibuat) . ' akun kecamatan dibuat: ' . implode(', ', $dibuat) . '.'
                : 'Semua kecamatan aktif sudah memiliki akun.');
        }
    }

    /* Membuat satu akun untuk setiap desa/kelurahan yang belum punya akun */
    if ($aksi === 'akun_desa') {
        $sandi = $_POST['sandi_awal'] ?? '';
        if (strlen($sandi) < 6) {
            pesan_set('galat', 'Sandi awal minimal enam karakter.');
        } else {
            $hash  = password_hash($sandi, PASSWORD_DEFAULT);
            $belum = jalankan($koneksi, "
                SELECT d.id_desa, d.id_kecamatan, d.nama, d.jenis, k.nama AS kecamatan
                FROM tb_desa d JOIN tb_kecamatan k ON k.id_kecamatan = d.id_kecamatan
                WHERE d.aktif = 1 AND NOT EXISTS (SELECT 1 FROM tb_user u WHERE u.id_desa = d.id_desa)
                ORDER BY k.nama, d.nama");
            $stmt = mysqli_prepare($koneksi, "INSERT IGNORE INTO tb_user
                (username, password, nama_lengkap, jabatan, role, id_kecamatan, id_desa)
                VALUES (?,?,?,?,'desa',?,?)");
            $dibuat = 0;
            $bentrok = [];
            foreach ($belum as $d) {
                $un  = username_desa($d['kecamatan'], $d['nama']);
                $nl  = $d['jenis'] . ' ' . $d['nama'] . ', Kec. ' . $d['kecamatan'];
                $jb  = 'Operator ' . $d['jenis'];
                $idk = (int)$d['id_kecamatan'];
                $idd = (int)$d['id_desa'];
                mysqli_stmt_bind_param($stmt, 'ssssii', $un, $hash, $nl, $jb, $idk, $idd);
                mysqli_stmt_execute($stmt);
                if (mysqli_stmt_affected_rows($stmt) > 0) { $dibuat++; } else { $bentrok[] = $un; }
            }
            mysqli_stmt_close($stmt);
            catat_log($koneksi, 'buat akun desa', 'tb_user', $dibuat . ' akun');
            pesan_set($bentrok ? 'peringatan' : 'sukses', $dibuat . ' akun desa dibuat.'
                . ($bentrok ? ' Nama pengguna sudah dipakai akun lain: ' . implode(', ', array_slice($bentrok, 0, 10)) . '.' : ''));
        }
    }

    /* Membuat satu akun untuk setiap perangkat daerah yang belum punya akun */
    if ($aksi === 'akun_opd') {
        $sandi = $_POST['sandi_awal'] ?? '';
        if (strlen($sandi) < 6) {
            pesan_set('galat', 'Sandi awal minimal enam karakter.');
        } else {
            $hash  = password_hash($sandi, PASSWORD_DEFAULT);
            $belum = jalankan($koneksi, "SELECT o.id_opd, o.nama FROM tb_opd o
                WHERE o.aktif = 1 AND NOT EXISTS (SELECT 1 FROM tb_user u WHERE u.id_opd = o.id_opd)
                ORDER BY o.nama");
            $stmt = mysqli_prepare($koneksi, "INSERT IGNORE INTO tb_user
                (username, password, nama_lengkap, jabatan, role, id_opd)
                VALUES (?,?,?,'Operator Perangkat Daerah','opd',?)");
            $dibuat = 0;
            $bentrok = [];
            foreach ($belum as $o) {
                $un  = 'opd_' . strtolower(str_replace([' ', '-', '.', "'"], ['_', '_', '', ''], $o['nama']));
                $idp = (int)$o['id_opd'];
                mysqli_stmt_bind_param($stmt, 'sssi', $un, $hash, $o['nama'], $idp);
                mysqli_stmt_execute($stmt);
                if (mysqli_stmt_affected_rows($stmt) > 0) { $dibuat++; } else { $bentrok[] = $un; }
            }
            mysqli_stmt_close($stmt);
            catat_log($koneksi, 'buat akun perangkat daerah', 'tb_user', $dibuat . ' akun');
            pesan_set($bentrok ? 'peringatan' : 'sukses', $dibuat . ' akun perangkat daerah dibuat.'
                . ($bentrok ? ' Nama pengguna sudah dipakai: ' . implode(', ', array_slice($bentrok, 0, 10)) . '.' : ''));
        }
    }

    if ($aksi === 'ubah') {
        $id      = (int)$_POST['id_user'];
        $role    = in_array($_POST['role'] ?? '', ['admin','verifikator','operator'], true) ? $_POST['role'] : 'operator';
        $id_kec  = ($_POST['id_kecamatan'] ?? '') !== '' ? (int)$_POST['id_kecamatan'] : null;
        $nama    = trim($_POST['nama_lengkap'] ?? '');
        $lama_u  = jalankan($koneksi, "SELECT role, id_kecamatan FROM tb_user WHERE id_user = ?", 'i', [$id]);
        if ($lama_u && in_array($lama_u[0]['role'], ['desa', 'opd'], true)) {
            /* Peran dan penugasan akun desa maupun perangkat daerah mengikuti datanya sendiri */
            $role   = $lama_u[0]['role'];
            $id_kec = $lama_u[0]['id_kecamatan'] !== null ? (int)$lama_u[0]['id_kecamatan'] : null;
        }
        if ($id === (int)$_SESSION['id_user'] && $role !== 'admin') {
            pesan_set('galat', 'Peran akun yang sedang dipakai tidak dapat diturunkan.');
        } elseif ($nama === '') {
            pesan_set('galat', 'Nama lengkap tidak boleh kosong.');
        } else {
            $stmt = mysqli_prepare($koneksi, "UPDATE tb_user SET nama_lengkap = ?, role = ?, id_kecamatan = ? WHERE id_user = ?");
            mysqli_stmt_bind_param($stmt, 'ssii', $nama, $role, $id_kec, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            catat_log($koneksi, 'ubah pengguna', 'tb_user#' . $id, $role . ' kec ' . ($id_kec ?? '-'));
            pesan_set('sukses', 'Data akun diperbarui. Perubahan berlaku saat akun tersebut masuk kembali.');
        }
    }

    if ($aksi === 'sandi') {
        $id    = (int)$_POST['id_user'];
        $sandi = $_POST['password_baru'] ?? '';
        if (strlen($sandi) < 6) {
            pesan_set('galat', 'Kata sandi baru minimal enam karakter.');
        } else {
            $hash = password_hash($sandi, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($koneksi, "UPDATE tb_user SET password = ? WHERE id_user = ?");
            mysqli_stmt_bind_param($stmt, 'si', $hash, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            catat_log($koneksi, 'ganti sandi', 'tb_user#' . $id, '');
            pesan_set('sukses', 'Kata sandi diperbarui.');
        }
    }

    if ($aksi === 'status') {
        $id = (int)$_POST['id_user'];
        if ($id === (int)$_SESSION['id_user']) {
            pesan_set('galat', 'Akun yang sedang dipakai tidak dapat dinonaktifkan.');
        } else {
            mysqli_query($koneksi, "UPDATE tb_user SET aktif = 1 - aktif WHERE id_user = $id");
            catat_log($koneksi, 'ubah status pengguna', 'tb_user#' . $id, '');
            pesan_set('sukses', 'Status akun diubah.');
        }
    }

    header('Location: pengguna.php' . (!empty($_POST['kembali']) ? '?' . $_POST['kembali'] : ''));
    exit;
}

/* Penyaring daftar. Bawaan: akun kabupaten dan kecamatan (akun desa jumlahnya ratusan) */
$f_jenis = $_GET['jenis'] ?? '';
$f_kec   = (int)($_GET['fkec'] ?? 0);
$f_cari  = trim($_GET['cari'] ?? '');
$syarat = ['1=1'];
$tipe = '';
$nilai = [];
if ($f_jenis === '')          { $syarat[] = "u.role NOT IN ('desa','opd')"; }
if ($f_jenis === 'kecamatan') { $syarat[] = "u.id_kecamatan IS NOT NULL AND u.role <> 'desa'"; }
if ($f_jenis === 'kabupaten') { $syarat[] = 'u.id_kecamatan IS NULL'; }
if ($f_jenis === 'desa')      { $syarat[] = "u.role = 'desa'"; }
if ($f_jenis === 'opd')       { $syarat[] = "u.role = 'opd'"; }
if ($f_kec > 0) {
    $syarat[] = 'u.id_kecamatan = ?';
    $tipe .= 'i';
    $nilai[] = $f_kec;
}
if ($f_cari !== '') {
    $syarat[] = '(u.username LIKE ? OR u.nama_lengkap LIKE ? OR k.nama LIKE ?)';
    $tipe .= 'sss';
    $kata = '%' . $f_cari . '%';
    array_push($nilai, $kata, $kata, $kata);
}

$pengguna = jalankan($koneksi, "SELECT u.*, k.nama AS kecamatan, ds.nama AS nama_desa, op.nama AS nama_opd,
        (SELECT COUNT(*) FROM tb_desa d WHERE d.id_kecamatan = u.id_kecamatan AND d.aktif = 1) AS jumlah_desa
    FROM tb_user u
    LEFT JOIN tb_kecamatan k ON k.id_kecamatan = u.id_kecamatan
    LEFT JOIN tb_desa ds     ON ds.id_desa = u.id_desa
    LEFT JOIN tb_opd op      ON op.id_opd = u.id_opd
    WHERE " . implode(' AND ', $syarat) . "
    ORDER BY (u.id_kecamatan IS NULL) DESC, (u.role = 'desa'), u.role, k.nama, ds.nama, u.username
    LIMIT 500", $tipe, $nilai);

$kecamatan = ambil_daftar($koneksi, 'tb_kecamatan', 'id_kecamatan');
$tanpa_akun = jalankan($koneksi, "SELECT COUNT(*) AS n FROM tb_kecamatan k WHERE k.aktif = 1
    AND NOT EXISTS (SELECT 1 FROM tb_user u WHERE u.id_kecamatan = k.id_kecamatan
                    AND u.id_desa IS NULL AND u.role <> 'desa')")[0]['n'];
$desa_tanpa_akun = (int)jalankan($koneksi, "SELECT COUNT(*) AS n FROM tb_desa d WHERE d.aktif = 1
    AND NOT EXISTS (SELECT 1 FROM tb_user u WHERE u.id_desa = d.id_desa)")[0]['n'];
$opd_tanpa_akun = (int)jalankan($koneksi, "SELECT COUNT(*) AS n FROM tb_opd o WHERE o.aktif = 1
    AND NOT EXISTS (SELECT 1 FROM tb_user u WHERE u.id_opd = o.id_opd)")[0]['n'];
$kembali = http_build_query(array_filter(['jenis' => $f_jenis, 'fkec' => $f_kec ?: null, 'cari' => $f_cari]));
$peta_desa_id = [];
foreach (jalankan($koneksi, "SELECT d.id_desa, d.nama, k.nama AS kecamatan FROM tb_desa d
    JOIN tb_kecamatan k ON k.id_kecamatan = d.id_kecamatan WHERE d.aktif = 1 ORDER BY k.nama, d.nama") as $d) {
    $peta_desa_id[$d['kecamatan']][] = $d;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="grid gap-5 lg:grid-cols-3">
  <div class="space-y-5">
    <form method="post" class="kartu p-5">
      <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
      <input type="hidden" name="aksi" value="akun_kecamatan">
      <p class="font-semibold text-sm text-slate-800">Akun per kecamatan</p>
      <p class="text-xs text-slate-500 mt-1 mb-3">
        Setiap akun kecamatan hanya melihat usulan kecamatannya, dan pilihan desa pada formulir
        otomatis berisi desa milik kecamatan tersebut.
      </p>
      <?php if ((int)$tanpa_akun > 0): ?>
        <p class="text-sm text-slate-700 mb-3"><b><?= (int)$tanpa_akun ?></b> kecamatan belum memiliki akun.</p>
        <label class="block text-xs text-slate-600 mb-1">Sandi awal untuk akun baru</label>
        <input type="text" name="sandi_awal" value="kecamatan123" minlength="6" required class="isian mb-3">
        <button class="tombol w-full">Buat akun untuk <?= (int)$tanpa_akun ?> kecamatan</button>
        <p class="text-xs text-slate-500 mt-2">Nama pengguna dibuat otomatis, misalnya kec_luwuk_selatan.</p>
      <?php else: ?>
        <p class="text-sm text-stone-700">Semua kecamatan aktif sudah memiliki akun.</p>
      <?php endif; ?>
    </form>

    <form method="post" class="kartu p-5">
      <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
      <input type="hidden" name="aksi" value="akun_desa">
      <p class="font-semibold text-sm text-slate-800">Akun per desa/kelurahan</p>
      <p class="text-xs text-slate-500 mt-1 mb-3">
        Akun desa menilai usulan desa lain di kecamatannya. Saat login cukup memilih kecamatan dan desa.
      </p>
      <?php if ($desa_tanpa_akun > 0): ?>
        <p class="text-sm text-slate-700 mb-3"><b><?= $desa_tanpa_akun ?></b> desa/kelurahan belum memiliki akun.</p>
        <label class="block text-xs text-slate-600 mb-1">Sandi awal untuk akun baru</label>
        <input type="text" name="sandi_awal" value="desa123" minlength="6" required class="isian mb-3">
        <button class="tombol w-full">Buat akun untuk <?= $desa_tanpa_akun ?> desa</button>
        <p class="text-xs text-slate-500 mt-2">Nama pengguna dibuat otomatis, misalnya pagimana.pisou.</p>
      <?php else: ?>
        <p class="text-sm text-stone-700">Semua desa/kelurahan aktif sudah memiliki akun.</p>
      <?php endif; ?>
      <a href="?jenis=desa" class="text-xs mt-2 inline-block" style="color:#44403B">Lihat daftar akun desa</a>
    </form>

    <form method="post" class="kartu p-5">
      <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
      <input type="hidden" name="aksi" value="akun_opd">
      <p class="font-semibold text-sm text-slate-800">Akun perangkat daerah</p>
      <p class="text-xs text-slate-500 mt-1 mb-3">
        Dipakai untuk mengisi realisasi indikator program GERBANG. Saat login, pilih <b>User OPD</b> lalu pilih perangkat daerahnya.
      </p>
      <?php if ($opd_tanpa_akun > 0): ?>
        <p class="text-sm text-slate-700 mb-3"><b><?= $opd_tanpa_akun ?></b> perangkat daerah belum memiliki akun.</p>
        <label class="block text-xs text-slate-600 mb-1">Sandi awal untuk akun baru</label>
        <input type="text" name="sandi_awal" value="opd123" minlength="6" required class="isian mb-3">
        <button class="tombol w-full">Buat akun untuk <?= $opd_tanpa_akun ?> perangkat daerah</button>
        <p class="text-xs text-slate-500 mt-2">Nama pengguna dibuat otomatis, misalnya opd_dinas_pupr.</p>
      <?php else: ?>
        <p class="text-sm text-stone-700">Semua perangkat daerah aktif sudah memiliki akun.</p>
      <?php endif; ?>
      <a href="?jenis=opd" class="text-xs mt-2 inline-block" style="color:#44403B">Lihat daftar akun perangkat daerah</a>
    </form>

    <form method="post" class="kartu p-5">
      <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
      <input type="hidden" name="aksi" value="tambah">
      <p class="font-semibold text-sm text-slate-800 mb-3">Buat akun baru</p>

      <label class="block text-xs text-slate-600 mb-1">Nama pengguna</label>
      <input name="username" required class="isian mb-3">

      <label class="block text-xs text-slate-600 mb-1">Nama lengkap</label>
      <input name="nama_lengkap" required class="isian mb-3">

      <label class="block text-xs text-slate-600 mb-1">Jabatan</label>
      <input name="jabatan" class="isian mb-3">

      <label class="block text-xs text-slate-600 mb-1">Peran</label>
      <select name="role" class="isian mb-3">
        <option value="operator">Operator, input usulan</option>
        <option value="verifikator">Verifikator, menilai dan memverifikasi</option>
        <option value="admin">Administrator, akses penuh</option>
        <option value="desa">Desa, menilai usulan desa lain</option>
      </select>

      <div id="pilihDesaBaru" hidden>
        <label class="block text-xs text-slate-600 mb-1">Desa/kelurahan</label>
        <select name="id_desa" class="isian mb-3">
          <option value="">Pilih desa</option>
          <?php foreach ($peta_desa_id as $nk => $daftar): ?>
            <optgroup label="Kec. <?= e($nk) ?>">
              <?php foreach ($daftar as $d): ?>
                <option value="<?= (int)$d['id_desa'] ?>"><?= e($d['nama']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="pilihKecBaru">
      <label class="block text-xs text-slate-600 mb-1">Kecamatan penugasan</label>
      <select name="id_kecamatan" class="isian mb-3">
        <option value="">Tingkat kabupaten (semua kecamatan)</option>
        <?php foreach ($kecamatan as $k): ?>
          <option value="<?= $k['id_kecamatan'] ?>"><?= e($k['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      </div>

      <label class="block text-xs text-slate-600 mb-1">Kata sandi awal</label>
      <input type="password" name="password" required minlength="6" class="isian mb-4">

      <button class="tombol w-full">Buat akun</button>
    </form>
  </div>

  <div class="lg:col-span-2 kartu overflow-hidden h-fit">
    <form method="get" class="px-4 py-3 border-b border-slate-200 flex flex-wrap items-end gap-3">
      <div>
        <p class="text-sm font-semibold text-slate-800">Daftar akun (<?= count($pengguna) ?>)</p>
      </div>
      <div class="ml-auto flex flex-wrap gap-2">
        <select name="jenis" class="isian" style="width:auto">
          <option value="">Kabupaten dan kecamatan</option>
          <option value="kabupaten" <?= $f_jenis === 'kabupaten' ? 'selected' : '' ?>>Tingkat kabupaten</option>
          <option value="kecamatan" <?= $f_jenis === 'kecamatan' ? 'selected' : '' ?>>Akun kecamatan</option>
          <option value="desa" <?= $f_jenis === 'desa' ? 'selected' : '' ?>>Akun desa</option>
          <option value="opd" <?= $f_jenis === 'opd' ? 'selected' : '' ?>>Akun perangkat daerah</option>
          <option value="semua" <?= $f_jenis === 'semua' ? 'selected' : '' ?>>Semua akun</option>
        </select>
        <select name="fkec" class="isian" style="width:auto">
          <option value="0">Semua kecamatan</option>
          <?php foreach ($kecamatan as $k): ?>
            <option value="<?= $k['id_kecamatan'] ?>" <?= $f_kec === (int)$k['id_kecamatan'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
          <?php endforeach; ?>
        </select>
        <input name="cari" value="<?= e($f_cari) ?>" placeholder="Cari nama atau kecamatan" class="isian" style="width:200px">
        <button class="tombol-2">Cari</button>
      </div>
    </form>
    <div class="overflow-x-auto">
      <table class="data w-full">
        <thead><tr><th>Pengguna</th><th>Peran dan kecamatan</th><th>Status</th><th>Ganti sandi</th></tr></thead>
        <tbody>
        <?php foreach ($pengguna as $p): $fid = 'ubah' . (int)$p['id_user']; ?>
          <tr>
            <td>
              <span class="font-medium"><?= e($p['username']) ?></span>
              <input form="<?= $fid ?>" name="nama_lengkap" value="<?= e($p['nama_lengkap']) ?>" class="isian mt-1" style="min-width:190px">
              <?php if ($p['id_kecamatan'] && !in_array($p['role'], ['desa', 'opd'], true)): ?>
                <div class="text-xs mt-1 <?= (int)$p['jumlah_desa'] > 0 ? 'text-slate-500' : 'text-amber-700' ?>">
                  <?= (int)$p['jumlah_desa'] > 0
                      ? (int)$p['jumlah_desa'] . ' desa terdaftar di Master'
                      : 'Desa kecamatan ini belum diisi di Master' ?>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <form method="post" id="<?= $fid ?>" class="flex flex-wrap gap-1 items-center">
                <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
                <input type="hidden" name="aksi" value="ubah">
                <input type="hidden" name="kembali" value="<?= e($kembali) ?>">
                <input type="hidden" name="id_user" value="<?= (int)$p['id_user'] ?>">
                <?php if ($p['role'] === 'desa'): ?>
                  <span class="text-sm"><b>Desa</b> <?= e($p['nama_desa'] ?? '-') ?>, Kec. <?= e($p['kecamatan'] ?? '-') ?></span>
                  <button class="tombol-2 text-xs">Simpan nama</button>
                <?php elseif ($p['role'] === 'opd'): ?>
                  <span class="text-sm"><b>Perangkat daerah</b> <?= e($p['nama_opd'] ?? '-') ?></span>
                  <button class="tombol-2 text-xs">Simpan nama</button>
                <?php else: ?>
                <select name="role" class="isian" style="width:120px">
                  <?php foreach (['operator','verifikator','admin'] as $r): ?>
                    <option value="<?= $r ?>" <?= $p['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="id_kecamatan" class="isian" style="width:170px">
                  <option value="">Kabupaten</option>
                  <?php foreach ($kecamatan as $k): ?>
                    <option value="<?= $k['id_kecamatan'] ?>" <?= (int)$p['id_kecamatan'] === (int)$k['id_kecamatan'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="tombol-2 text-xs">Simpan</button>
                <?php endif; ?>
              </form>
            </td>
            <td>
              <form method="post" class="inline">
                <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
                <input type="hidden" name="aksi" value="status">
                <input type="hidden" name="kembali" value="<?= e($kembali) ?>">
                <input type="hidden" name="id_user" value="<?= (int)$p['id_user'] ?>">
                <button class="text-xs <?= $p['aktif'] ? 'text-slate-600' : 'text-rose-600' ?>">
                  <?= $p['aktif'] ? 'Aktif' : 'Nonaktif' ?>
                </button>
              </form>
            </td>
            <td>
              <form method="post" class="flex gap-1">
                <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
                <input type="hidden" name="aksi" value="sandi">
                <input type="hidden" name="kembali" value="<?= e($kembali) ?>">
                <input type="hidden" name="id_user" value="<?= (int)$p['id_user'] ?>">
                <input type="password" name="password_baru" minlength="6" placeholder="Sandi baru"
                       class="isian" style="width:120px">
                <button class="tombol-2 text-xs">Simpan</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$pengguna): ?>
          <tr><td colspan="4" class="text-center text-slate-500 py-8">Tidak ada akun yang cocok dengan pencarian.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  const peran = document.querySelector('form input[value="tambah"]').form.querySelector('select[name="role"]');
  const desa = document.getElementById('pilihDesaBaru');
  const kec  = document.getElementById('pilihKecBaru');
  function atur() {
    const isDesa = peran.value === 'desa';
    desa.hidden = !isDesa;
    kec.hidden = isDesa;
    desa.querySelector('select').required = isDesa;
  }
  peran.addEventListener('change', atur);
  atur();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
