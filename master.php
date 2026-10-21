<?php
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();
wajib_peran('admin');

$halaman_aktif = 'master';
$judul_halaman = 'Master';

$tabel_sah = [
    'tb_kecamatan'     => ['id' => 'id_kecamatan', 'judul' => 'Kecamatan',       'warna' => false],
    'tb_desa'          => ['id' => 'id_desa',      'judul' => 'Desa/Kelurahan',  'warna' => false],
    'tb_sektor'        => ['id' => 'id_sektor',    'judul' => 'Sektor',          'warna' => true],
    'tb_sumber_usulan' => ['id' => 'id_sumber',    'judul' => 'Sumber usulan',   'warna' => false],
    'tb_opd'           => ['id' => 'id_opd',       'judul' => 'OPD',             'warna' => false],
];

$tabel_aktif = $_REQUEST['tabel'] ?? 'tb_kecamatan';
if (!isset($tabel_sah[$tabel_aktif])) {
    $tabel_aktif = 'tb_kecamatan';
}
$info     = $tabel_sah[$tabel_aktif];
$kolom_id = $info['id'];
$dkec     = (int)($_REQUEST['dkec'] ?? 0);   /* penyaring kecamatan pada tab desa */

$alamat_kembali = 'master.php?tabel=' . urlencode($tabel_aktif) . ($dkec ? '&dkec=' . $dkec : '');

/* Peta nama kecamatan (huruf kecil) ke id, untuk impor desa */
function peta_kecamatan(mysqli $k): array
{
    $peta = [];
    foreach (jalankan($k, "SELECT id_kecamatan, nama FROM tb_kecamatan") as $r) {
        $peta[mb_strtolower(trim($r['nama']))] = (int)$r['id_kecamatan'];
    }
    return $peta;
}

/**
 * Membaca teks tempelan Excel atau isi CSV menjadi baris [kecamatan, desa, jenis].
 * Pemisah yang dikenali: tab, titik koma, koma.
 */
function urai_baris_desa(string $teks): array
{
    $hasil = [];
    $teks  = str_replace(["\xEF\xBB\xBF", "\r\n", "\r"], ['', "\n", "\n"], $teks);
    foreach (explode("\n", $teks) as $baris) {
        if (trim($baris) === '') { continue; }
        $pemisah = strpos($baris, "\t") !== false ? "\t" : (strpos($baris, ';') !== false ? ';' : ',');
        $kol = array_map(function ($v) { return trim($v, " \"'\t"); }, str_getcsv($baris, $pemisah, "\"", ""));
        $hasil[] = $kol;
    }
    return $hasil;
}

function jenis_sah(string $j): string
{
    return stripos($j, 'kel') === 0 ? 'Kelurahan' : 'Desa';
}

/* Ubah status aktif melalui tautan */
if (isset($_GET['alih'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_GET['csrf'] ?? '')) {
        die('Permintaan ditolak.');
    }
    $id = (int)$_GET['alih'];
    mysqli_query($koneksi, "UPDATE `$tabel_aktif` SET aktif = 1 - aktif WHERE `$kolom_id` = $id");
    catat_log($koneksi, 'ubah status referensi', $tabel_aktif, 'id ' . $id);
    pesan_set('sukses', 'Status data diubah.');
    header('Location: ' . $alamat_kembali);
    exit;
}

/* Hapus desa (khusus tab desa; usulan menyimpan nama desa sehingga tidak ikut terhapus) */
if (isset($_GET['hapus_desa']) && $tabel_aktif === 'tb_desa') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_GET['csrf'] ?? '')) {
        die('Permintaan ditolak.');
    }
    $id = (int)$_GET['hapus_desa'];
    $b = jalankan($koneksi, "SELECT d.nama,
            (SELECT COUNT(*) FROM tb_penilaian p WHERE p.id_desa_penilai = d.id_desa) AS penilaian,
            (SELECT COUNT(*) FROM tb_user u WHERE u.id_desa = d.id_desa) AS akun
        FROM tb_desa d WHERE d.id_desa = ?", 'i', [$id]);
    if ($b && ((int)$b[0]['penilaian'] > 0 || (int)$b[0]['akun'] > 0)) {
        pesan_set('peringatan', $b[0]['nama'] . ' tidak dapat dihapus karena sudah memiliki akun atau penilaian ('
            . (int)$b[0]['penilaian'] . ' penilaian). Gunakan status Nonaktif agar tidak muncul lagi di pilihan.');
        header('Location: ' . $alamat_kembali);
        exit;
    }
    if ($b) {
        jalankan($koneksi, "DELETE FROM tb_desa WHERE id_desa = ?", 'i', [$id]);
        catat_log($koneksi, 'hapus desa', 'tb_desa#' . $id, $b[0]['nama']);
        pesan_set('sukses', 'Desa ' . $b[0]['nama'] . ' dihapus dari master.');
    }
    header('Location: ' . $alamat_kembali);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksa_csrf();
    $aksi = $_POST['aksi'] ?? '';

    /* ---------------- Tab desa ---------------- */
    if ($tabel_aktif === 'tb_desa') {

        if ($aksi === 'tambah') {
            $id_kec = (int)($_POST['id_kecamatan'] ?? 0);
            $nama   = trim($_POST['nama'] ?? '');
            $jenis  = jenis_sah($_POST['jenis'] ?? 'Desa');
            if ($id_kec <= 0 || $nama === '') {
                pesan_set('galat', 'Kecamatan dan nama desa wajib diisi.');
            } else {
                $stmt = mysqli_prepare($koneksi, "INSERT IGNORE INTO tb_desa (id_kecamatan, nama, jenis) VALUES (?,?,?)");
                mysqli_stmt_bind_param($stmt, 'iss', $id_kec, $nama, $jenis);
                mysqli_stmt_execute($stmt);
                $masuk = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                catat_log($koneksi, 'tambah desa', 'tb_desa', $nama);
                pesan_set($masuk ? 'sukses' : 'peringatan',
                    $masuk ? $jenis . ' ' . $nama . ' ditambahkan.' : $nama . ' sudah terdaftar di kecamatan tersebut.');
            }
            $alamat_kembali = 'master.php?tabel=tb_desa&dkec=' . $id_kec;
        }

        if ($aksi === 'impor') {
            $teks = (string)($_POST['teks'] ?? '');
            if (!empty($_FILES['berkas']['tmp_name']) && $_FILES['berkas']['error'] === UPLOAD_ERR_OK) {
                $teks .= "\n" . file_get_contents($_FILES['berkas']['tmp_name']);
            }
            $kec_tetap = (int)($_POST['kec_tetap'] ?? 0);
            $peta_kec  = peta_kecamatan($koneksi);

            $baru = 0; $ada = 0; $gagal = [];
            $stmt = mysqli_prepare($koneksi, "INSERT IGNORE INTO tb_desa (id_kecamatan, nama, jenis) VALUES (?,?,?)");
            foreach (urai_baris_desa($teks) as $no => $kol) {
                if ($kec_tetap > 0) {
                    /* Satu kecamatan: kolom pertama nama desa, kolom kedua jenis (opsional) */
                    $id_kec = $kec_tetap;
                    $nama   = $kol[0] ?? '';
                    $jenis  = jenis_sah($kol[1] ?? 'Desa');
                } else {
                    /* Banyak kecamatan: kecamatan, desa, jenis (opsional) */
                    $nama_kec = mb_strtolower($kol[0] ?? '');
                    $nama     = $kol[1] ?? '';
                    $jenis    = jenis_sah($kol[2] ?? 'Desa');
                    if (in_array($nama_kec, ['kecamatan', 'kec', 'nama kecamatan'], true)) {
                        continue; /* baris judul */
                    }
                    $nama_kec = preg_replace('/^kec(amatan)?\.?\s+/i', '', $nama_kec);
                    $id_kec   = $peta_kec[$nama_kec] ?? 0;
                    if (!$id_kec) {
                        $gagal[] = 'baris ' . ($no + 1) . ' (kecamatan "' . ($kol[0] ?? '') . '" tidak dikenal)';
                        continue;
                    }
                }
                if ($nama === '' || in_array(mb_strtolower($nama), ['desa', 'desa/kelurahan', 'nama desa'], true)) {
                    continue;
                }
                mysqli_stmt_bind_param($stmt, 'iss', $id_kec, $nama, $jenis);
                mysqli_stmt_execute($stmt);
                if (mysqli_stmt_affected_rows($stmt) > 0) { $baru++; } else { $ada++; }
            }
            mysqli_stmt_close($stmt);
            catat_log($koneksi, 'impor desa', 'tb_desa', "$baru baru, $ada sudah ada, " . count($gagal) . ' gagal');

            $teks_pesan = "$baru desa ditambahkan, $ada sudah terdaftar sebelumnya.";
            if ($gagal) {
                $teks_pesan .= ' Dilewati: ' . implode('; ', array_slice($gagal, 0, 8))
                    . (count($gagal) > 8 ? ' dan ' . (count($gagal) - 8) . ' baris lain' : '')
                    . '. Samakan nama kecamatan dengan tab Kecamatan.';
            }
            pesan_set($gagal ? 'peringatan' : 'sukses', $teks_pesan);
            $alamat_kembali = 'master.php?tabel=tb_desa' . ($kec_tetap ? '&dkec=' . $kec_tetap : '');
        }

        if ($aksi === 'ubah_semua') {
            $jumlah = 0;
            $stmt = mysqli_prepare($koneksi, "UPDATE tb_desa SET nama = ?, jenis = ?, id_kecamatan = ? WHERE id_desa = ?");
            $lama_desa = [];
            foreach (jalankan($koneksi, "SELECT id_desa, id_kecamatan, nama FROM tb_desa") as $r) {
                $lama_desa[(int)$r['id_desa']] = $r;
            }
            $dipindah = [];
            foreach ($_POST['nama_baris'] ?? [] as $id => $nama) {
                $id    = (int)$id;
                $nama  = trim($nama);
                $jenis = jenis_sah($_POST['jenis_baris'][$id] ?? 'Desa');
                $ik    = (int)($_POST['kec_baris'][$id] ?? 0);
                if ($nama === '' || $ik <= 0 || !isset($lama_desa[$id])) { continue; }
                $lama = $lama_desa[$id];
                mysqli_stmt_bind_param($stmt, 'ssii', $nama, $jenis, $ik, $id);
                if (!mysqli_stmt_execute($stmt)) { continue; }
                $jumlah++;
                /* Nama desa berubah: ikutkan usulan dan nama akun desa tersebut */
                if ($nama !== $lama['nama'] && $ik === (int)$lama['id_kecamatan']) {
                    jalankan($koneksi, "UPDATE tb_usulan SET desa = ? WHERE id_kecamatan = ? AND desa = ?",
                        'sis', [$nama, $ik, $lama['nama']]);
                }
                if ($ik !== (int)$lama['id_kecamatan']) {
                    /* Akun desa ikut pindah kecamatan; penilaian lama tetap tercatat */
                    jalankan($koneksi, "UPDATE tb_user SET id_kecamatan = ? WHERE id_desa = ?", 'ii', [$ik, $id]);
                    $dipindah[] = $nama;
                }
            }
            mysqli_stmt_close($stmt);
            catat_log($koneksi, 'ubah desa', 'tb_desa', $jumlah . ' baris');
            pesan_set('sukses', $jumlah . ' baris desa diperbarui. Perubahan nama ikut diterapkan pada usulan yang sudah ada.'
                . ($dipindah ? ' Desa yang pindah kecamatan (' . implode(', ', $dipindah)
                    . '): usulan lamanya tetap tercatat di kecamatan asal.' : ''));
        }

        header('Location: ' . $alamat_kembali);
        exit;
    }

    /* ---------------- Tab referensi lain ---------------- */
    if ($aksi === 'tambah') {
        $nama  = trim($_POST['nama'] ?? '');
        $warna = $_POST['warna'] ?? '#44403B';
        if ($nama === '') {
            pesan_set('galat', 'Nama tidak boleh kosong.');
        } else {
            if ($info['warna']) {
                $stmt = mysqli_prepare($koneksi, "INSERT INTO `$tabel_aktif` (nama, warna) VALUES (?,?)");
                mysqli_stmt_bind_param($stmt, 'ss', $nama, $warna);
            } else {
                $stmt = mysqli_prepare($koneksi, "INSERT INTO `$tabel_aktif` (nama) VALUES (?)");
                mysqli_stmt_bind_param($stmt, 's', $nama);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            catat_log($koneksi, 'tambah referensi', $tabel_aktif, $nama);
            pesan_set('sukses', $nama . ' ditambahkan.');
        }
    }

    if ($aksi === 'ubah_semua') {
        $jumlah = 0;
        if ($info['warna']) {
            $stmt = mysqli_prepare($koneksi, "UPDATE `$tabel_aktif` SET nama = ?, warna = ? WHERE `$kolom_id` = ?");
        } else {
            $stmt = mysqli_prepare($koneksi, "UPDATE `$tabel_aktif` SET nama = ? WHERE `$kolom_id` = ?");
        }
        foreach ($_POST['nama_baris'] ?? [] as $id => $nama) {
            $id   = (int)$id;
            $nama = trim($nama);
            if ($nama === '') { continue; }
            if ($info['warna']) {
                $warna = $_POST['warna_baris'][$id] ?? '#44403B';
                mysqli_stmt_bind_param($stmt, 'ssi', $nama, $warna, $id);
            } else {
                mysqli_stmt_bind_param($stmt, 'si', $nama, $id);
            }
            mysqli_stmt_execute($stmt);
            $jumlah++;
        }
        mysqli_stmt_close($stmt);
        catat_log($koneksi, 'ubah referensi', $tabel_aktif, $jumlah . ' baris');
        pesan_set('sukses', $jumlah . ' baris data referensi diperbarui.');
    }

    header('Location: ' . $alamat_kembali);
    exit;
}

$semua_kec = jalankan($koneksi, "SELECT k.*, (SELECT COUNT(*) FROM tb_desa d WHERE d.id_kecamatan = k.id_kecamatan) AS jumlah_desa
    FROM tb_kecamatan k ORDER BY k.nama");

if ($tabel_aktif === 'tb_desa') {
    $isi = $dkec > 0
        ? jalankan($koneksi, "SELECT d.*, k.nama AS kecamatan FROM tb_desa d JOIN tb_kecamatan k ON k.id_kecamatan = d.id_kecamatan
                              WHERE d.id_kecamatan = ? ORDER BY d.nama", 'i', [$dkec])
        : jalankan($koneksi, "SELECT d.*, k.nama AS kecamatan FROM tb_desa d JOIN tb_kecamatan k ON k.id_kecamatan = d.id_kecamatan
                              ORDER BY k.nama, d.nama");
    $total_desa = array_sum(array_column($semua_kec, 'jumlah_desa'));
} else {
    $isi = jalankan($koneksi, "SELECT * FROM `$tabel_aktif` ORDER BY nama");
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-wrap gap-2 mb-5">
  <?php foreach ($tabel_sah as $t => $i): ?>
    <a href="?tabel=<?= $t ?>" class="<?= $t === $tabel_aktif ? 'tombol' : 'tombol-2' ?>"><?= $i['judul'] ?></a>
  <?php endforeach; ?>
  <a href="kamus.php" class="tombol-2">Kamus usulan</a>
</div>

<?php if ($tabel_aktif === 'tb_desa'): ?>

<div class="grid gap-5 lg:grid-cols-4">

  <div class="kartu overflow-hidden h-fit">
    <div class="px-4 py-3 border-b border-slate-200">
      <p class="text-sm font-semibold text-slate-800">Kecamatan</p>
      <p class="text-xs text-slate-500"><?= number_format($total_desa, 0, ',', '.') ?> desa/kelurahan terdaftar</p>
    </div>
    <nav class="p-2 max-h-[560px] overflow-y-auto">
      <a href="?tabel=tb_desa" class="flex justify-between px-3 py-1.5 rounded-lg text-sm <?= $dkec === 0 ? 'bg-stone-100 font-semibold text-stone-900' : 'text-slate-700 hover:bg-slate-50' ?>">
        <span>Semua kecamatan</span><span><?= $total_desa ?></span>
      </a>
      <?php foreach ($semua_kec as $k): ?>
        <a href="?tabel=tb_desa&dkec=<?= (int)$k['id_kecamatan'] ?>"
           class="flex justify-between px-3 py-1.5 rounded-lg text-sm <?= $dkec === (int)$k['id_kecamatan'] ? 'bg-stone-100 font-semibold text-stone-900' : 'text-slate-700 hover:bg-slate-50' ?>">
          <span><?= e($k['nama']) ?></span>
          <span class="<?= (int)$k['jumlah_desa'] === 0 ? 'text-amber-600' : 'text-slate-400' ?>"><?= (int)$k['jumlah_desa'] ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="lg:col-span-3 space-y-5">
    <div class="grid gap-5 md:grid-cols-2">
      <form method="post" class="kartu p-5">
        <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
        <input type="hidden" name="tabel" value="tb_desa">
        <input type="hidden" name="aksi" value="tambah">
        <p class="font-semibold text-sm text-slate-800 mb-3">Tambah satu desa</p>
        <label class="block text-xs text-slate-600 mb-1">Kecamatan</label>
        <select name="id_kecamatan" required class="isian mb-3">
          <option value="">Pilih kecamatan</option>
          <?php foreach ($semua_kec as $k): ?>
            <option value="<?= $k['id_kecamatan'] ?>" <?= $dkec === (int)$k['id_kecamatan'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="grid grid-cols-3 gap-2 mb-3">
          <div class="col-span-2">
            <label class="block text-xs text-slate-600 mb-1">Nama desa/kelurahan</label>
            <input name="nama" required class="isian">
          </div>
          <div>
            <label class="block text-xs text-slate-600 mb-1">Jenis</label>
            <select name="jenis" class="isian"><option>Desa</option><option>Kelurahan</option></select>
          </div>
        </div>
        <button class="tombol w-full">Tambahkan</button>
      </form>

      <form method="post" enctype="multipart/form-data" class="kartu p-5">
        <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
        <input type="hidden" name="tabel" value="tb_desa">
        <input type="hidden" name="aksi" value="impor">
        <p class="font-semibold text-sm text-slate-800">Tambah banyak sekaligus</p>
        <p class="text-xs text-slate-500 mt-1 mb-3">
          Salin kolom dari Excel lalu tempel di bawah. Tanpa kecamatan tetap, susunan kolom:
          <b>Kecamatan, Desa, Jenis</b>. Dengan kecamatan tetap: <b>Desa, Jenis</b>. Kolom jenis boleh dikosongkan.
        </p>
        <label class="block text-xs text-slate-600 mb-1">Kecamatan tetap</label>
        <select name="kec_tetap" class="isian mb-2">
          <option value="0">Tidak, kecamatan ada di kolom pertama</option>
          <?php foreach ($semua_kec as $k): ?>
            <option value="<?= $k['id_kecamatan'] ?>" <?= $dkec === (int)$k['id_kecamatan'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
          <?php endforeach; ?>
        </select>
        <textarea name="teks" rows="4" class="isian mb-2" placeholder="Pagimana	Pisou	Desa"></textarea>
        <label class="block text-xs text-slate-600 mb-1">Atau unggah CSV</label>
        <input type="file" name="berkas" accept=".csv,.txt" class="text-xs mb-3">
        <button class="tombol w-full">Impor desa</button>
        <p class="text-xs text-slate-500 mt-2">Desa yang sudah terdaftar di kecamatan yang sama dilewati. Contoh berkas: database/template_desa.csv</p>
      </form>
    </div>

    <form method="post" class="kartu overflow-hidden">
      <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
      <input type="hidden" name="tabel" value="tb_desa">
      <input type="hidden" name="dkec" value="<?= $dkec ?>">
      <input type="hidden" name="aksi" value="ubah_semua">
      <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
        <p class="text-sm font-semibold text-slate-800">
          <?= $dkec ? 'Desa di Kecamatan ' . e(array_column($semua_kec, 'nama', 'id_kecamatan')[$dkec] ?? '') : 'Semua desa' ?>
          (<?= count($isi) ?>)
        </p>
        <?php if ($isi): ?><button class="tombol">Simpan perubahan</button><?php endif; ?>
      </div>
      <div class="overflow-x-auto max-h-[640px] overflow-y-auto">
        <table class="data w-full">
          <thead><tr><th>Nama</th><th style="width:130px">Jenis</th><th style="width:190px">Kecamatan</th><th style="width:130px">Status</th></tr></thead>
          <tbody>
          <?php foreach ($isi as $r): $id = (int)$r['id_desa']; ?>
            <tr>
              <td><input name="nama_baris[<?= $id ?>]" value="<?= e($r['nama']) ?>" class="isian"></td>
              <td>
                <select name="jenis_baris[<?= $id ?>]" class="isian">
                  <?php foreach (['Desa','Kelurahan'] as $j): ?>
                    <option <?= $r['jenis'] === $j ? 'selected' : '' ?>><?= $j ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <select name="kec_baris[<?= $id ?>]" class="isian">
                  <?php foreach ($semua_kec as $k): ?>
                    <option value="<?= $k['id_kecamatan'] ?>" <?= (int)$r['id_kecamatan'] === (int)$k['id_kecamatan'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="whitespace-nowrap">
                <a href="?tabel=tb_desa&dkec=<?= $dkec ?>&alih=<?= $id ?>&csrf=<?= token_csrf() ?>"
                   class="text-xs <?= $r['aktif'] ? 'text-slate-600' : 'text-rose-600' ?>"><?= $r['aktif'] ? 'Aktif' : 'Nonaktif' ?></a>
                <span class="text-slate-300">|</span>
                <a href="?tabel=tb_desa&dkec=<?= $dkec ?>&hapus_desa=<?= $id ?>&csrf=<?= token_csrf() ?>"
                   onclick="return confirm('Hapus <?= e(addslashes($r['nama'])) ?> dari master? Usulan yang sudah tersimpan tidak ikut terhapus.')"
                   class="text-xs text-rose-600">Hapus</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$isi): ?>
            <tr><td colspan="4" class="text-center text-slate-500 py-8">
              Belum ada desa<?= $dkec ? ' untuk kecamatan ini' : '' ?>. Tambahkan melalui formulir di atas.
            </td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </form>
  </div>
</div>

<?php else: ?>

<div class="grid gap-5 lg:grid-cols-3">

  <form method="post" class="kartu p-5 h-fit">
    <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
    <input type="hidden" name="tabel" value="<?= e($tabel_aktif) ?>">
    <input type="hidden" name="aksi" value="tambah">
    <p class="font-semibold text-sm text-slate-800 mb-3">Tambah <?= strtolower($info['judul']) ?></p>

    <label class="block text-xs text-slate-600 mb-1">Nama</label>
    <input name="nama" required class="isian mb-3">

    <?php if ($info['warna']): ?>
      <label class="block text-xs text-slate-600 mb-1">Warna penanda</label>
      <input type="color" name="warna" value="#44403B" class="isian p-1 mb-3" style="width:70px">
    <?php endif; ?>

    <button class="tombol w-full">Tambahkan</button>
    <?php if ($tabel_aktif === 'tb_kecamatan'): ?>
      <p class="text-xs text-slate-500 mt-3">Setelah menambah kecamatan, buat akunnya di menu User dan isi desanya di tab Desa/Kelurahan.</p>
    <?php endif; ?>
  </form>

  <form method="post" class="lg:col-span-2 kartu overflow-hidden">
    <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
    <input type="hidden" name="tabel" value="<?= e($tabel_aktif) ?>">
    <input type="hidden" name="aksi" value="ubah_semua">

    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
      <p class="text-sm font-semibold text-slate-800"><?= $info['judul'] ?> (<?= count($isi) ?>)</p>
      <button class="tombol">Simpan perubahan</button>
    </div>

    <div class="overflow-x-auto">
      <table class="data w-full">
        <thead>
          <tr><th>Nama</th>
              <?php if ($tabel_aktif === 'tb_kecamatan'): ?><th style="width:120px">Desa</th><?php endif; ?>
              <?php if ($info['warna']): ?><th style="width:80px">Warna</th><?php endif; ?>
              <th style="width:110px">Status</th></tr>
        </thead>
        <tbody>
        <?php
        $jumlah_desa = array_column($semua_kec, 'jumlah_desa', 'id_kecamatan');
        foreach ($isi as $r): $id = (int)$r[$kolom_id]; ?>
          <tr>
            <td><input name="nama_baris[<?= $id ?>]" value="<?= e($r['nama']) ?>" class="isian"></td>
            <?php if ($tabel_aktif === 'tb_kecamatan'): ?>
              <td><a href="?tabel=tb_desa&dkec=<?= $id ?>" class="text-xs" style="color:#44403B"><?= (int)($jumlah_desa[$id] ?? 0) ?> desa</a></td>
            <?php endif; ?>
            <?php if ($info['warna']): ?>
              <td><input type="color" name="warna_baris[<?= $id ?>]" value="<?= e($r['warna']) ?>"
                         class="isian p-1" style="width:56px"></td>
            <?php endif; ?>
            <td>
              <a href="?tabel=<?= e($tabel_aktif) ?>&alih=<?= $id ?>&csrf=<?= token_csrf() ?>"
                 class="text-xs <?= $r['aktif'] ? 'text-slate-600' : 'text-rose-600' ?>">
                <?= $r['aktif'] ? 'Aktif' : 'Nonaktif' ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
</div>

<?php endif; ?>

<p class="text-xs text-slate-500 mt-4">
  Data referensi tidak dihapus permanen karena sudah dipakai oleh usulan yang tersimpan.
  Klik status Aktif untuk menonaktifkan, sehingga pilihan tidak lagi muncul pada formulir input.
</p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
