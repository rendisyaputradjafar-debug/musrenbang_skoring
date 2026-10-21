<?php
/**
 * Kamus usulan: daftar baku usulan yang boleh dipilih kecamatan saat menginput.
 * Setiap entri memuat nama usulan, permasalahan, sektor, OPD, dan satuan.
 */
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();
wajib_peran('admin');

$halaman_aktif = 'kamus';
$judul_halaman = 'Kamus usulan';

$sektor_ref = ambil_daftar($koneksi, 'tb_sektor', 'id_sektor');
$gerbang_ref = jalankan($koneksi, "SELECT id_gerbang, kode, nama FROM tb_gerbang WHERE aktif = 1 ORDER BY urut");
$opd_ref    = ambil_daftar($koneksi, 'tb_opd', 'id_opd');
$peta_sektor = [];
foreach ($sektor_ref as $r) { $peta_sektor[mb_strtolower(trim($r['nama']))] = (int)$r['id_sektor']; }
$peta_opd = [];
foreach ($opd_ref as $r) { $peta_opd[mb_strtolower(trim($r['nama']))] = (int)$r['id_opd']; }

function kode_kamus_baru(mysqli $k): string
{
    $n = (int)(jalankan($k, "SELECT COALESCE(MAX(id_kamus), 0) + 1 AS n FROM tb_kamus_usulan")[0]['n'] ?? 1);
    do {
        $kode = 'K-' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
        $ada = jalankan($k, "SELECT 1 FROM tb_kamus_usulan WHERE kode = ?", 's', [$kode]);
        $n++;
    } while ($ada);
    return $kode;
}

$kembali = 'kamus.php' . (!empty($_REQUEST['q']) || !empty($_REQUEST['sektor'])
    ? '?' . http_build_query(array_filter(['q' => $_REQUEST['q'] ?? '', 'sektor' => $_REQUEST['sektor'] ?? '']))
    : '');

if (isset($_GET['alih']) || isset($_GET['hapus'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_GET['csrf'] ?? '')) {
        die('Permintaan ditolak.');
    }
    if (isset($_GET['alih'])) {
        $id = (int)$_GET['alih'];
        jalankan($koneksi, "UPDATE tb_kamus_usulan SET aktif = 1 - aktif WHERE id_kamus = ?", 'i', [$id]);
        catat_log($koneksi, 'ubah status kamus', 'tb_kamus_usulan#' . $id, '');
        pesan_set('sukses', 'Status entri kamus diubah.');
    } else {
        $id = (int)$_GET['hapus'];
        $dipakai = (int)jalankan($koneksi, "SELECT COUNT(*) AS n FROM tb_usulan WHERE id_kamus = ?", 'i', [$id])[0]['n'];
        if ($dipakai > 0) {
            pesan_set('peringatan', 'Entri ini sudah dipakai ' . $dipakai . ' usulan, jadi tidak dapat dihapus. Nonaktifkan saja agar tidak muncul lagi di pilihan.');
        } else {
            jalankan($koneksi, "DELETE FROM tb_kamus_usulan WHERE id_kamus = ?", 'i', [$id]);
            catat_log($koneksi, 'hapus kamus', 'tb_kamus_usulan#' . $id, '');
            pesan_set('sukses', 'Entri kamus dihapus.');
        }
    }
    header('Location: ' . $kembali);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksa_csrf();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan') {
        $id     = (int)($_POST['id_kamus'] ?? 0);
        $kode   = strtoupper(trim($_POST['kode'] ?? ''));
        $nama   = trim($_POST['nama_usulan'] ?? '');
        $masalah = trim($_POST['permasalahan'] ?? '');
        $sektor = (int)($_POST['id_sektor'] ?? 0);
        $opd    = (int)($_POST['id_opd'] ?? 0);
        $satuan = trim($_POST['satuan'] ?? '');
        $gerbang = ($_POST['id_gerbang'] ?? '') !== '' ? (int)$_POST['id_gerbang'] : null;

        if ($nama === '' || $masalah === '' || !$sektor || !$opd || $satuan === '') {
            pesan_set('galat', 'Semua isian kamus wajib diisi.');
            header('Location: kamus.php' . ($id ? '?ubah=' . $id : ''));
            exit;
        }
        if ($kode === '') {
            $kode = $id ? (jalankan($koneksi, "SELECT kode FROM tb_kamus_usulan WHERE id_kamus = ?", 'i', [$id])[0]['kode'] ?? kode_kamus_baru($koneksi)) : kode_kamus_baru($koneksi);
        }
        $bentrok = jalankan($koneksi, "SELECT id_kamus FROM tb_kamus_usulan
            WHERE (kode = ? OR (nama_usulan = ? AND permasalahan = ?)) AND id_kamus <> ?", 'sssi', [$kode, $nama, $masalah, $id]);
        if ($bentrok) {
            pesan_set('galat', 'Kode atau pasangan usulan dan permasalahan ini sudah ada di kamus.');
            header('Location: kamus.php' . ($id ? '?ubah=' . $id : ''));
            exit;
        }
        if ($id) {
            jalankan($koneksi, "UPDATE tb_kamus_usulan SET kode = ?, nama_usulan = ?, permasalahan = ?, id_sektor = ?, id_opd = ?, satuan = ?, id_gerbang = ?
                WHERE id_kamus = ?", 'sssiisii', [$kode, $nama, $masalah, $sektor, $opd, $satuan, $gerbang, $id]);
            jalankan($koneksi, "UPDATE tb_usulan SET id_gerbang = ? WHERE id_kamus = ?", 'ii', [$gerbang, $id]);
            /* Usulan yang memakai entri ini ikut diperbarui agar tetap seragam */
            jalankan($koneksi, "UPDATE tb_usulan SET nama_usulan = ?, uraian_masalah = ?, id_sektor = ?, id_opd = ?, satuan = ?
                WHERE id_kamus = ?", 'ssiisi', [$nama, $masalah, $sektor, $opd, $satuan, $id]);
            catat_log($koneksi, 'ubah kamus', 'tb_kamus_usulan#' . $id, $nama);
            pesan_set('sukses', 'Entri kamus ' . $kode . ' diperbarui. Usulan yang memakainya ikut diperbarui.');
        } else {
            jalankan($koneksi, "INSERT INTO tb_kamus_usulan (kode, nama_usulan, permasalahan, id_sektor, id_opd, satuan, id_gerbang)
                VALUES (?,?,?,?,?,?,?)", 'sssiisi', [$kode, $nama, $masalah, $sektor, $opd, $satuan, $gerbang]);
            catat_log($koneksi, 'tambah kamus', 'tb_kamus_usulan', $kode . ' ' . $nama);
            pesan_set('sukses', 'Entri kamus ' . $kode . ' ditambahkan.');
        }
    }

    if ($aksi === 'impor') {
        $teks = str_replace(["\xEF\xBB\xBF", "\r\n", "\r"], ['', "\n", "\n"], (string)($_POST['teks'] ?? ''));
        if (!empty($_FILES['berkas']['tmp_name']) && $_FILES['berkas']['error'] === UPLOAD_ERR_OK) {
            $teks .= "\n" . str_replace(["\xEF\xBB\xBF", "\r\n", "\r"], ['', "\n", "\n"], file_get_contents($_FILES['berkas']['tmp_name']));
        }
        $masuk = 0; $ada = 0; $gagal = [];
        foreach (explode("\n", $teks) as $no => $baris) {
            if (trim($baris) === '') { continue; }
            $pemisah = strpos($baris, "\t") !== false ? "\t" : (strpos($baris, ';') !== false ? ';' : ',');
            $kol = array_map('trim', str_getcsv($baris, $pemisah, '"', ''));
            [$nama, $masalah, $ns, $no_, $satuan] = array_pad($kol, 5, '');
            if (in_array(mb_strtolower($nama), ['nama usulan', 'usulan'], true)) { continue; }
            $sektor = $peta_sektor[mb_strtolower($ns)] ?? 0;
            $opd    = $peta_opd[mb_strtolower($no_)] ?? 0;
            if ($nama === '' || $masalah === '' || $satuan === '' || !$sektor || !$opd) {
                $gagal[] = 'baris ' . ($no + 1) . (!$sektor ? ' (sektor "' . $ns . '" tidak dikenal)' : '') . (!$opd ? ' (OPD "' . $no_ . '" tidak dikenal)' : '');
                continue;
            }
            $sudah = jalankan($koneksi, "SELECT 1 FROM tb_kamus_usulan WHERE nama_usulan = ? AND permasalahan = ?", 'ss', [$nama, $masalah]);
            if ($sudah) { $ada++; continue; }
            jalankan($koneksi, "INSERT INTO tb_kamus_usulan (kode, nama_usulan, permasalahan, id_sektor, id_opd, satuan)
                VALUES (?,?,?,?,?,?)", 'sssiis', [kode_kamus_baru($koneksi), $nama, $masalah, $sektor, $opd, $satuan]);
            $masuk++;
        }
        catat_log($koneksi, 'impor kamus', 'tb_kamus_usulan', "$masuk baru, $ada sudah ada, " . count($gagal) . ' gagal');
        pesan_set($gagal ? 'peringatan' : 'sukses', "$masuk entri ditambahkan, $ada sudah ada sebelumnya."
            . ($gagal ? ' Dilewati: ' . implode('; ', array_slice($gagal, 0, 8)) . '. Samakan nama sektor dan OPD dengan menu Master.' : ''));
    }

    header('Location: ' . $kembali);
    exit;
}

$q = trim($_GET['q'] ?? '');
$f_sektor = (int)($_GET['sektor'] ?? 0);
$syarat = ['1=1']; $tipe = ''; $nilai = [];
if ($q !== '') {
    $syarat[] = '(km.nama_usulan LIKE ? OR km.permasalahan LIKE ? OR km.kode LIKE ?)';
    $tipe .= 'sss'; $kata = '%' . $q . '%'; array_push($nilai, $kata, $kata, $kata);
}
if ($f_sektor) { $syarat[] = 'km.id_sektor = ?'; $tipe .= 'i'; $nilai[] = $f_sektor; }
$daftar = jalankan($koneksi, "
    SELECT km.*, s.nama AS sektor, s.warna, o.nama AS opd,
           (SELECT COUNT(*) FROM tb_usulan u WHERE u.id_kamus = km.id_kamus) AS dipakai
    FROM tb_kamus_usulan km
    JOIN tb_sektor s ON s.id_sektor = km.id_sektor
    JOIN tb_opd o ON o.id_opd = km.id_opd
    WHERE " . implode(' AND ', $syarat) . "
    ORDER BY s.nama, km.nama_usulan", $tipe, $nilai);

$ubah = null;
if (!empty($_GET['ubah'])) {
    $ubah = jalankan($koneksi, "SELECT * FROM tb_kamus_usulan WHERE id_kamus = ?", 'i', [(int)$_GET['ubah']])[0] ?? null;
}
$e = $ubah ?: ['id_kamus' => 0, 'kode' => '', 'nama_usulan' => '', 'permasalahan' => '', 'id_sektor' => 0, 'id_opd' => 0, 'satuan' => '', 'id_gerbang' => 0];

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-wrap gap-2 mb-5">
  <?php foreach (['tb_kecamatan' => 'Kecamatan', 'tb_desa' => 'Desa/Kelurahan', 'tb_sektor' => 'Sektor', 'tb_sumber_usulan' => 'Sumber usulan', 'tb_opd' => 'OPD'] as $t => $j): ?>
    <a href="master.php?tabel=<?= $t ?>" class="tombol-2"><?= $j ?></a>
  <?php endforeach; ?>
  <a href="kamus.php" class="tombol">Kamus usulan</a>
</div>

<div class="grid gap-5 lg:grid-cols-3">
  <div class="space-y-5">
    <form method="post" class="kartu p-5" id="formKamus">
      <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id_kamus" value="<?= (int)$e['id_kamus'] ?>">
      <p class="font-semibold text-slate-800"><?= $ubah ? 'Ubah entri ' . e($e['kode']) : 'Tambah entri kamus' ?></p>
      <p class="text-xs text-slate-500 mt-1 mb-4">Kecamatan hanya dapat memilih usulan dari kamus ini, sehingga penulisan selalu seragam.</p>

      <label class="block text-sm font-medium mb-1" for="k_nama">Nama usulan</label>
      <input id="k_nama" name="nama_usulan" required maxlength="200" value="<?= e($e['nama_usulan']) ?>" class="isian mb-3" placeholder="Contoh: Pembangunan Jalan">

      <label class="block text-sm font-medium mb-1" for="k_masalah">Permasalahan yang diatasi</label>
      <input id="k_masalah" name="permasalahan" required maxlength="255" value="<?= e($e['permasalahan']) ?>" class="isian mb-3" placeholder="Contoh: Tidak ada jalan penghubung">

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium mb-1" for="k_sektor">Sektor</label>
          <select id="k_sektor" name="id_sektor" required class="isian mb-3">
            <option value="">Pilih</option>
            <?php foreach ($sektor_ref as $r): ?>
              <option value="<?= $r['id_sektor'] ?>" <?= (int)$e['id_sektor'] === (int)$r['id_sektor'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1" for="k_satuan">Satuan</label>
          <input id="k_satuan" name="satuan" required maxlength="30" value="<?= e($e['satuan']) ?>" class="isian mb-3" placeholder="Meter, Unit" list="daftarSatuan">
          <datalist id="daftarSatuan"><option>Meter</option><option>Unit</option><option>Paket</option><option>Titik</option><option>Ruang</option><option>Hektar</option><option>Orang</option></datalist>
        </div>
      </div>

      <label class="block text-sm font-medium mb-1" for="k_opd">OPD penanggung jawab</label>
      <select id="k_opd" name="id_opd" required class="isian mb-3">
        <option value="">Pilih</option>
        <?php foreach ($opd_ref as $r): ?>
          <option value="<?= $r['id_opd'] ?>" <?= (int)$e['id_opd'] === (int)$r['id_opd'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="block text-sm font-medium mb-1" for="k_gerbang">Mendukung program GERBANG</label>
      <select id="k_gerbang" name="id_gerbang" class="isian mb-3">
        <option value="">Belum dikaitkan</option>
        <?php foreach ($gerbang_ref as $g): ?>
          <option value="<?= (int)$g['id_gerbang'] ?>" <?= (int)($e['id_gerbang'] ?? 0) === (int)$g['id_gerbang'] ? 'selected' : '' ?>><?= e($g['kode']) ?> - <?= e($g['nama']) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="block text-sm font-medium mb-1" for="k_kode">Kode <span class="font-normal text-slate-500">(boleh dikosongkan)</span></label>
      <input id="k_kode" name="kode" maxlength="20" value="<?= e($e['kode']) ?>" class="isian mb-4" placeholder="Dibuat otomatis">

      <button class="tombol w-full"><?= $ubah ? 'Simpan perubahan' : 'Tambahkan ke kamus' ?></button>
      <?php if ($ubah): ?><a href="kamus.php" class="tombol-2 block text-center mt-2">Batal</a><?php endif; ?>
    </form>

    <details class="kartu p-5">
      <summary class="font-semibold text-slate-800 cursor-pointer">Tambah banyak sekaligus dari Excel</summary>
      <form method="post" enctype="multipart/form-data" class="mt-3">
        <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
        <input type="hidden" name="aksi" value="impor">
        <p class="text-xs text-slate-500 mb-2">
          Salin lima kolom dari Excel dengan urutan: <b>Nama usulan, Permasalahan, Sektor, OPD, Satuan</b>, lalu tempel di bawah.
          Nama sektor dan OPD harus sama dengan menu Master.
        </p>
        <textarea name="teks" rows="5" class="isian mb-2" placeholder="Pembangunan Jalan	Tidak ada jalan penghubung	Infrastruktur	Dinas PUPR	Meter"></textarea>
        <label class="block text-xs text-slate-600 mb-1">Atau unggah CSV</label>
        <input type="file" name="berkas" accept=".csv,.txt" class="text-xs mb-3">
        <button class="tombol w-full">Impor ke kamus</button>
      </form>
    </details>
  </div>

  <div class="lg:col-span-2 kartu overflow-hidden h-fit">
    <form method="get" class="px-4 py-3 border-b border-slate-200 flex flex-wrap items-center gap-2">
      <p class="font-semibold text-slate-800 mr-auto"><?= count($daftar) ?> entri kamus</p>
      <select name="sektor" class="isian" style="width:auto" onchange="this.form.submit()">
        <option value="">Semua sektor</option>
        <?php foreach ($sektor_ref as $r): ?>
          <option value="<?= $r['id_sektor'] ?>" <?= $f_sektor === (int)$r['id_sektor'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <input name="q" value="<?= e($q) ?>" placeholder="Cari usulan" class="isian" style="width:180px">
      <button class="tombol-2">Cari</button>
    </form>
    <div class="overflow-x-auto max-h-[680px] overflow-y-auto">
      <table class="data w-full">
        <thead><tr><th>Usulan dan permasalahan</th><th>Sektor dan OPD</th><th>Satuan</th><th>Dipakai</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($daftar as $r): ?>
          <tr class="<?= $r['aktif'] ? '' : 'opacity-50' ?>">
            <td>
              <span class="text-xs text-slate-500"><?= e($r['kode']) ?></span>
              <div class="font-medium text-slate-900"><?= e($r['nama_usulan']) ?></div>
              <div class="text-xs text-slate-600"><?= e($r['permasalahan']) ?></div>
            </td>
            <td>
              <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm" style="background:<?= e($r['warna']) ?>"></span><?= e($r['sektor']) ?></span>
              <div class="text-xs text-slate-500"><?= e($r['opd']) ?></div>
            </td>
            <td><?= e($r['satuan']) ?></td>
            <td><?= (int)$r['dipakai'] ?> usulan</td>
            <td class="text-right whitespace-nowrap text-xs">
              <a href="?ubah=<?= (int)$r['id_kamus'] ?>" style="color:#44403B">Ubah</a>
              <span class="text-slate-300">|</span>
              <a href="?alih=<?= (int)$r['id_kamus'] ?>&amp;csrf=<?= token_csrf() ?>" class="<?= $r['aktif'] ? 'text-slate-600' : 'text-rose-600' ?>"><?= $r['aktif'] ? 'Aktif' : 'Nonaktif' ?></a>
              <?php if (!(int)$r['dipakai']): ?>
                <span class="text-slate-300">|</span>
                <a href="?hapus=<?= (int)$r['id_kamus'] ?>&amp;csrf=<?= token_csrf() ?>" class="text-rose-600"
                   onclick="return confirm('Hapus entri kamus ini?')">Hapus</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$daftar): ?>
          <tr><td colspan="5" class="text-center text-slate-500 py-10">Belum ada entri kamus yang cocok.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
