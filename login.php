<?php
require_once __DIR__ . '/includes/fungsi.php';

if (!empty($_SESSION['id_user'])) {
    header('Location: ' . (($_SESSION['role'] ?? '') === 'desa' ? 'penilaian.php'
        : (($_SESSION['role'] ?? '') === 'opd' ? 'gerbang.php' : 'dashboard.php')));
    exit;
}

const BATAS_GAGAL = 5;   /* percobaan salah per alamat IP */
const JEDA_MENIT  = 5;   /* lama penguncian sementara */

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

function jumlah_gagal(mysqli $k, string $ip): int
{
    $b = jalankan($k, "SELECT COUNT(*) AS n FROM tb_log
        WHERE aksi = 'gagal masuk' AND ip = ? AND waktu > (NOW() - INTERVAL " . JEDA_MENIT . " MINUTE)", 's', [$ip]);
    return (int)($b[0]['n'] ?? 0);
}

function masukkan(mysqli $k, array $u)
{
    session_regenerate_id(true);
    $_SESSION['id_user']        = (int)$u['id_user'];
    $_SESSION['username']       = $u['username'];
    $_SESSION['nama_lengkap']   = $u['nama_lengkap'];
    $_SESSION['role']           = $u['role'];
    $_SESSION['id_kecamatan']   = (int)$u['id_kecamatan'];
    $_SESSION['nama_kecamatan'] = $u['nama_kecamatan'] ?? '';
    $_SESSION['id_desa']        = (int)($u['id_desa'] ?? 0);
    $_SESSION['nama_desa']      = $u['nama_desa'] ?? '';
    $_SESSION['id_opd']         = (int)($u['id_opd'] ?? 0);
    $_SESSION['nama_opd']       = $u['nama_opd'] ?? '';
    $jenis_akun = $u['role'] === 'desa' ? 'user desa'
        : ($u['role'] === 'opd' ? 'user perangkat daerah' : ($u['id_kecamatan'] ? 'user kecamatan' : 'master'));
    catat_log($k, 'masuk', 'tb_user', 'Masuk sebagai ' . $jenis_akun);
    header('Location: ' . ($u['role'] === 'desa' ? 'penilaian.php' : ($u['role'] === 'opd' ? 'gerbang.php' : 'dashboard.php')));
    exit;
}

/* Daftar kecamatan beserta jumlah akun kecamatan aktifnya (akun desa tidak dihitung) */
$kecamatan = jalankan($koneksi, "
    SELECT k.id_kecamatan, k.nama, COUNT(u.id_user) AS akun
    FROM tb_kecamatan k
    LEFT JOIN tb_user u ON u.id_kecamatan = k.id_kecamatan AND u.aktif = 1
                       AND u.id_desa IS NULL AND u.role <> 'desa'
    WHERE k.aktif = 1
    GROUP BY k.id_kecamatan, k.nama
    ORDER BY k.nama");

/* Desa per kecamatan beserta keberadaan akunnya, untuk pilihan User Desa */
$peta_desa_login = [];
foreach (jalankan($koneksi, "
    SELECT d.id_desa, d.id_kecamatan, d.nama, d.jenis,
           EXISTS (SELECT 1 FROM tb_user u WHERE u.id_desa = d.id_desa AND u.aktif = 1 AND u.role = 'desa') AS akun
    FROM tb_desa d WHERE d.aktif = 1 ORDER BY d.nama") as $d) {
    $peta_desa_login[(int)$d['id_kecamatan']][] = [
        'id' => (int)$d['id_desa'], 'nama' => $d['nama'], 'jenis' => $d['jenis'], 'akun' => (int)$d['akun'],
    ];
}
$jumlah_desa_login = array_sum(array_map('count', $peta_desa_login));

$jenis = $_POST['jenis'] ?? $_GET['jenis'] ?? 'master';
if (!in_array($jenis, ['master', 'kecamatan', 'desa', 'opd'], true)) {
    $jenis = 'master';
}
$pil_opd = (int)($_POST['id_opd'] ?? 0);
$daftar_opd = jalankan($koneksi, "SELECT o.id_opd, o.nama,
        EXISTS (SELECT 1 FROM tb_user u WHERE u.id_opd = o.id_opd AND u.aktif = 1 AND u.role = 'opd') AS akun
    FROM tb_opd o WHERE o.aktif = 1 ORDER BY o.nama");
$opd_berakun = count(array_filter($daftar_opd, function ($o) { return (int)$o['akun'] > 0; }));
$pil_kec  = (int)($_POST['id_kecamatan'] ?? 0);
$pil_kecd = (int)($_POST['kec_desa'] ?? 0);
$pil_desa = (int)($_POST['id_desa'] ?? 0);
$galat   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sandi = (string)($_POST['password'] ?? '');

    if (jumlah_gagal($koneksi, $ip) >= BATAS_GAGAL) {
        $galat = 'Terlalu banyak percobaan yang salah. Tunggu ' . JEDA_MENIT . ' menit lalu coba lagi.';
    } elseif ($jenis === 'master') {
        /* Master: akun tingkat kabupaten (tidak terikat kecamatan) */
        $username = trim($_POST['username'] ?? '');
        $baris = jalankan($koneksi, "SELECT u.*, NULL AS nama_kecamatan FROM tb_user u
            WHERE u.username = ? AND u.aktif = 1 AND u.id_kecamatan IS NULL LIMIT 1", 's', [$username]);
        if ($baris && password_verify($sandi, $baris[0]['password'])) {
            masukkan($koneksi, $baris[0]);
        }
        $galat = 'Nama pengguna atau kata sandi master tidak cocok. Akun kecamatan masuk melalui pilihan User Kecamatan.';
        catat_log($koneksi, 'gagal masuk', 'master', $username);
    } elseif ($jenis === 'opd') {
        /* User perangkat daerah: pilih OPD lalu isi kata sandi */
        if ($pil_opd <= 0) {
            $galat = 'Pilih perangkat daerah terlebih dahulu.';
        } else {
            $baris = jalankan($koneksi, "SELECT u.*, NULL AS nama_kecamatan, NULL AS nama_desa, o.nama AS nama_opd
                FROM tb_user u JOIN tb_opd o ON o.id_opd = u.id_opd
                WHERE u.id_opd = ? AND u.role = 'opd' AND u.aktif = 1 ORDER BY u.id_user", 'i', [$pil_opd]);
            if (!$baris) {
                $galat = 'Perangkat daerah ini belum memiliki akun aktif.';
            } else {
                foreach ($baris as $u) {
                    if (password_verify($sandi, $u['password'])) {
                        masukkan($koneksi, $u);
                    }
                }
                $galat = 'Kata sandi untuk ' . $baris[0]['nama_opd'] . ' tidak cocok.';
                catat_log($koneksi, 'gagal masuk', 'opd#' . $pil_opd, '');
            }
        }
    } elseif ($jenis === 'desa') {
        /* User desa: pilih kecamatan, pilih desa, isi kata sandi */
        if ($pil_desa <= 0) {
            $galat = 'Pilih kecamatan dan desa terlebih dahulu.';
        } else {
            $baris = jalankan($koneksi, "SELECT u.*, k.nama AS nama_kecamatan, d.nama AS nama_desa
                FROM tb_user u
                JOIN tb_desa d      ON d.id_desa = u.id_desa
                JOIN tb_kecamatan k ON k.id_kecamatan = d.id_kecamatan
                WHERE u.id_desa = ? AND u.role = 'desa' AND u.aktif = 1 AND d.aktif = 1 ORDER BY u.id_user", 'i', [$pil_desa]);
            if (!$baris) {
                $galat = 'Desa ini belum memiliki akun aktif. Hubungi operator kecamatan atau admin Bappeda.';
            } else {
                foreach ($baris as $u) {
                    if (password_verify($sandi, $u['password'])) {
                        $u['id_kecamatan'] = $u['id_kecamatan'] ?: 0;
                        masukkan($koneksi, $u);
                    }
                }
                $galat = 'Kata sandi untuk ' . $baris[0]['nama_desa'] . ' tidak cocok.';
                catat_log($koneksi, 'gagal masuk', 'desa#' . $pil_desa, '');
            }
        }
    } else {
        /* User kecamatan: cukup pilih kecamatan dan isi kata sandi */
        if ($pil_kec <= 0) {
            $galat = 'Pilih kecamatan terlebih dahulu.';
        } else {
            $baris = jalankan($koneksi, "SELECT u.*, k.nama AS nama_kecamatan FROM tb_user u
                JOIN tb_kecamatan k ON k.id_kecamatan = u.id_kecamatan
                WHERE u.id_kecamatan = ? AND u.aktif = 1 AND u.id_desa IS NULL AND u.role <> 'desa'
                ORDER BY u.id_user", 'i', [$pil_kec]);
            if (!$baris) {
                $galat = 'Kecamatan ini belum memiliki akun aktif. Hubungi admin Bappeda.';
            } else {
                foreach ($baris as $u) {
                    if (password_verify($sandi, $u['password'])) {
                        masukkan($koneksi, $u);
                    }
                }
                $galat = 'Kata sandi untuk Kecamatan ' . $baris[0]['nama_kecamatan'] . ' tidak cocok.';
                catat_log($koneksi, 'gagal masuk', 'kecamatan#' . $pil_kec, '');
            }
        }
    }
}
$jumlah_kec = count($kecamatan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk - <?= APP_NAMA ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { font-family:"Plus Jakarta Sans", ui-sans-serif, system-ui, "Segoe UI", Roboto, Arial, sans-serif; background:#F6F5F1; color:#1C2B29; }
  .isian { width:100%; border:1px solid #CBD5D1; border-radius:.55rem; padding:.65rem .75rem; font-size:.9rem; background:#fff; }
  .isian:focus { outline:2px solid #44403B; outline-offset:1px; border-color:#44403B; }
  .pilihan-jenis input { position:absolute; opacity:0; pointer-events:none; }
  .pilihan-jenis label { display:block; cursor:pointer; border:1.5px solid #E3E1DA; border-radius:.7rem; padding:.7rem .65rem; background:#fff; height:100%; }
  .pilihan-jenis label:hover { border-color:#C2BCB0; }
  .pilihan-jenis input:checked + label { border-color:#44403B; background:#F1EFE9; box-shadow:0 0 0 1px #44403B; }
  .pilihan-jenis input:focus-visible + label { outline:2px solid #B08E4F; outline-offset:2px; }
  .tombol { background:#44403B; color:#fff; font-weight:700; border-radius:.55rem; padding:.7rem; font-size:.9rem; }
  .tombol:hover { background:#2C2A26; }
</style>
</head>
<body class="min-h-screen grid lg:grid-cols-2">

  <div class="hidden lg:flex flex-col justify-between p-12 text-white" style="background:#211F1C">
    <a href="index.php" class="flex items-center gap-3">
      <svg width="38" height="38" viewBox="0 0 34 34" aria-hidden="true">
        <rect width="34" height="34" rx="9" fill="#44403B"/>
        <rect x="8" y="18" width="4" height="8" rx="1" fill="#E4E1D9"/>
        <rect x="15" y="13" width="4" height="13" rx="1" fill="#E4E1D9"/>
        <rect x="22" y="8" width="4" height="18" rx="1" fill="#B08E4F"/>
      </svg>
      <span>
        <span class="block text-xl font-bold"><?= APP_NAMA ?></span>
        <span class="block text-sm" style="color:#A9A49A"><?= APP_INSTANSI ?></span>
      </span>
    </a>
    <div class="max-w-md">
      <p class="text-3xl font-extrabold leading-tight tracking-tight">Satu pintu masuk untuk Bappeda, <?= $jumlah_kec ?> kecamatan, dan <?= $jumlah_desa_login ?> desa/kelurahan.</p>
      <ul class="mt-6 space-y-3 leading-relaxed" style="color:#D7D3CA">
        <li><b class="text-white">Master</b>: pengelola kabupaten. Mengatur bobot kriteria, data master, akun, dan verifikasi.</li>
        <li><b class="text-white">User Kecamatan</b>: menginput semua usulan prioritas untuk setiap desa di kecamatannya dan memantau progres penilaian.</li>
        <li><b class="text-white">User Desa</b>: cukup menilai usulan desa lain di kecamatannya. Nilai akhir adalah rata-rata penilaian desa.</li>
        <li><b class="text-white">User OPD</b>: mengisi realisasi indikator program GERBANG yang menjadi tanggung jawabnya.</li>
      </ul>
    </div>
    <p class="text-sm" style="color:#A9A49A">Data usulan diperbarui secara real time di semua halaman.</p>
  </div>

  <div class="flex items-center justify-center p-6 sm:p-10">
    <div class="w-full max-w-md">
      <a href="index.php" class="inline-block text-sm font-semibold mb-5" style="color:#44403B">Kembali ke beranda</a>

      <form method="post" class="bg-white border rounded-2xl p-7" style="border-color:#E3E1DA" id="formMasuk">
        <h1 class="text-xl font-bold">Masuk ke aplikasi</h1>
        <p class="text-sm mt-1 mb-5" style="color:#5B6B68">Pilih jenis akun Anda.</p>

        <?php if ($galat): ?>
          <p class="mb-4 text-sm bg-rose-50 border border-rose-200 text-rose-800 rounded-lg px-3 py-2" role="alert"><?= e($galat) ?></p>
        <?php endif; ?>

        <fieldset class="pilihan-jenis grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
          <legend class="sr-only">Jenis akun</legend>
          <div class="relative">
            <input type="radio" name="jenis" id="jenisMaster" value="master" <?= $jenis === 'master' ? 'checked' : '' ?>>
            <label for="jenisMaster">
              <span class="block font-bold text-sm">Master</span>
              <span class="block text-xs mt-0.5" style="color:#5B6B68">Bappeda kabupaten</span>
            </label>
          </div>
          <div class="relative">
            <input type="radio" name="jenis" id="jenisKec" value="kecamatan" <?= $jenis === 'kecamatan' ? 'checked' : '' ?>>
            <label for="jenisKec">
              <span class="block font-bold text-sm">User Kecamatan</span>
              <span class="block text-xs mt-0.5" style="color:#5B6B68"><?= $jumlah_kec ?> kecamatan</span>
            </label>
          </div>
          <div class="relative">
            <input type="radio" name="jenis" id="jenisOpd" value="opd" <?= $jenis === 'opd' ? 'checked' : '' ?>>
            <label for="jenisOpd">
              <span class="block font-bold text-sm">User OPD</span>
              <span class="block text-xs mt-0.5" style="color:#6B665F">Perangkat daerah</span>
            </label>
          </div>
          <div class="relative">
            <input type="radio" name="jenis" id="jenisDesa" value="desa" <?= $jenis === 'desa' ? 'checked' : '' ?>>
            <label for="jenisDesa">
              <span class="block font-bold text-sm">User Desa</span>
              <span class="block text-xs mt-0.5" style="color:#5B6B68"><?= $jumlah_desa_login ?> desa/kelurahan</span>
            </label>
          </div>
        </fieldset>

        <div id="bagianMaster" <?= $jenis !== 'master' ? 'hidden' : '' ?>>
          <label for="username" class="block text-sm font-semibold mb-1">Nama pengguna</label>
          <input id="username" name="username" autocomplete="username" class="isian mb-4"
                 value="<?= e($jenis === 'master' ? ($_POST['username'] ?? '') : '') ?>">
        </div>

        <div id="bagianKec" <?= $jenis !== 'kecamatan' ? 'hidden' : '' ?>>
          <label for="id_kecamatan" class="block text-sm font-semibold mb-1">Kecamatan</label>
          <select id="id_kecamatan" name="id_kecamatan" class="isian mb-4">
            <option value="">Pilih kecamatan</option>
            <?php foreach ($kecamatan as $k): ?>
              <option value="<?= (int)$k['id_kecamatan'] ?>"
                      <?= (int)$k['akun'] === 0 ? 'disabled' : '' ?>
                      <?= $pil_kec === (int)$k['id_kecamatan'] ? 'selected' : '' ?>>
                <?= e($k['nama']) ?><?= (int)$k['akun'] === 0 ? ' (belum ada akun)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="bagianDesa" <?= $jenis !== 'desa' ? 'hidden' : '' ?>>
          <label for="kec_desa" class="block text-sm font-semibold mb-1">Kecamatan</label>
          <select id="kec_desa" name="kec_desa" class="isian mb-4">
            <option value="">Pilih kecamatan</option>
            <?php foreach ($kecamatan as $k): ?>
              <option value="<?= (int)$k['id_kecamatan'] ?>" <?= $pil_kecd === (int)$k['id_kecamatan'] ? 'selected' : '' ?>>
                <?= e($k['nama']) ?> (<?= count($peta_desa_login[(int)$k['id_kecamatan']] ?? []) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <label for="id_desa" class="block text-sm font-semibold mb-1">Desa/kelurahan</label>
          <select id="id_desa" name="id_desa" class="isian mb-4" data-terpilih="<?= $pil_desa ?>"></select>
        </div>

        <div id="bagianOpd" <?= $jenis !== 'opd' ? 'hidden' : '' ?>>
          <label for="id_opd" class="block text-sm font-semibold mb-1">Perangkat daerah</label>
          <select id="id_opd" name="id_opd" class="isian mb-4">
            <option value="">Pilih perangkat daerah</option>
            <?php foreach ($daftar_opd as $o): ?>
              <option value="<?= (int)$o['id_opd'] ?>"
                      <?= (int)$o['akun'] === 0 ? 'disabled' : '' ?>
                      <?= $pil_opd === (int)$o['id_opd'] ? 'selected' : '' ?>>
                <?= e($o['nama']) ?><?= (int)$o['akun'] === 0 ? ' (belum ada akun)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($opd_berakun === 0): ?>
            <p class="text-xs mb-4" style="color:#9C6B5A">
              Belum ada akun perangkat daerah. Admin dapat membuatnya sekaligus di menu <b>User</b>,
              bagian "Akun perangkat daerah".
            </p>
          <?php endif; ?>
        </div>

        <label for="password" class="block text-sm font-semibold mb-1">Kata sandi</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" class="isian mb-5">

        <button class="tombol w-full" id="tombolMasuk">Masuk</button>

        <p class="mt-5 text-xs leading-relaxed" style="color:#5B6B68">
          Akun bawaan master: <b>admin / admin123</b>. Sandi awal user kecamatan: <b>kecamatan123</b>,
          user desa: <b>desa123</b>, user OPD: <b>opd123</b>.
          Segera ganti kata sandi melalui menu User setelah aplikasi dipasang.
        </p>
      </form>
    </div>
  </div>

<script>
(function () {
  const pilihan = {
    master:    { radio: document.getElementById('jenisMaster'), bagian: document.getElementById('bagianMaster'),
                 isian: [document.getElementById('username')], teks: 'Masuk sebagai master' },
    kecamatan: { radio: document.getElementById('jenisKec'), bagian: document.getElementById('bagianKec'),
                 isian: [document.getElementById('id_kecamatan')], teks: 'Masuk sebagai user kecamatan' },
    desa:      { radio: document.getElementById('jenisDesa'), bagian: document.getElementById('bagianDesa'),
                 isian: [document.getElementById('kec_desa'), document.getElementById('id_desa')], teks: 'Masuk sebagai user desa' },
    opd:       { radio: document.getElementById('jenisOpd'), bagian: document.getElementById('bagianOpd'),
                 isian: [document.getElementById('id_opd')], teks: 'Masuk sebagai perangkat daerah' },
  };
  const tombol = document.getElementById('tombolMasuk');

  /* Desa mengikuti kecamatan */
  const petaDesa = <?= json_encode($peta_desa_login, JSON_UNESCAPED_UNICODE) ?>;
  const kecDesa = document.getElementById('kec_desa');
  const selDesa = document.getElementById('id_desa');
  const awalDesa = selDesa.dataset.terpilih || '';
  function isiDesa() {
    const daftar = petaDesa[kecDesa.value] || [];
    selDesa.innerHTML = '';
    const o = document.createElement('option');
    o.value = '';
    o.textContent = kecDesa.value ? 'Pilih desa/kelurahan' : 'Pilih kecamatan terlebih dahulu';
    selDesa.appendChild(o);
    daftar.forEach(function (d) {
      const op = document.createElement('option');
      op.value = d.id;
      op.textContent = d.nama + (d.jenis === 'Kelurahan' ? ' (kelurahan)' : '') + (d.akun ? '' : ' - belum ada akun');
      op.disabled = !d.akun;
      if (String(d.id) === awalDesa) { op.selected = true; }
      selDesa.appendChild(op);
    });
    selDesa.disabled = !kecDesa.value;
  }
  kecDesa.addEventListener('change', function () { selDesa.dataset.terpilih = ''; isiDesa(); selDesa.focus(); });
  isiDesa();

  function atur(fokus) {
    let aktif = 'master';
    Object.keys(pilihan).forEach(function (kunci) {
      const p = pilihan[kunci];
      const dipilih = p.radio.checked;
      if (dipilih) { aktif = kunci; }
      p.bagian.hidden = !dipilih;
      p.isian.forEach(function (el) { el.required = dipilih; });
    });
    tombol.textContent = pilihan[aktif].teks;
    if (fokus) {
      const pertama = pilihan[aktif].isian.find(function (el) { return !el.value; }) || document.getElementById('password');
      pertama.focus();
    }
  }
  Object.keys(pilihan).forEach(function (kunci) {
    pilihan[kunci].radio.addEventListener('change', function () { atur(true); });
  });
  atur(true);
})();
</script>
</body>
</html>
