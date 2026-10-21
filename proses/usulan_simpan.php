<?php
/**
 * Menyimpan usulan. Isi usulan (nama, permasalahan, sektor, OPD, satuan) selalu diambil
 * dari kamus usulan, sehingga penulisan seragam dan tidak ada salah ketik.
 */
require_once __DIR__ . '/../includes/fungsi.php';
wajib_login();
wajib_peran('admin', 'verifikator', 'operator');
periksa_csrf();

function pesan_nilai(array $h): string
{
    if ($h['total'] === null) {
        return 'Usulan menunggu penilaian dari desa-desa lain di kecamatan ini.';
    }
    $sumber = $h['jumlah_penilai'] > 0 ? 'rata-rata ' . $h['jumlah_penilai'] . ' desa penilai' : 'nilai awal';
    return 'Nilai saat ini ' . angka($h['total']) . ' (' . $sumber . ').';
}

function balik(string $jenis, string $pesan, string $tujuan)
{
    pesan_set($jenis, $pesan);
    header('Location: ../' . $tujuan);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../usulan.php');
    exit;
}

$id         = (int)($_POST['id_usulan'] ?? 0);
$form       = 'usulan_form.php' . ($id ? '?id=' . $id : '');
$tahun      = (int)($_POST['tahun'] ?? APP_TAHUN_DEFAULT);
$id_kec     = kec_user() > 0 ? kec_user() : (int)($_POST['id_kecamatan'] ?? 0);
$desa       = trim((string)($_POST['desa'] ?? ''));
$id_sumber  = (int)($_POST['id_sumber'] ?? 0);
$id_kamus   = (int)($_POST['id_kamus'] ?? 0);
$volume     = (float)str_replace(',', '.', (string)($_POST['volume'] ?? '0'));
$anggaran   = (float)preg_replace('/[^0-9]/', '', (string)($_POST['estimasi_anggaran'] ?? '0'));
$keterangan = trim((string)($_POST['keterangan_lokasi'] ?? ''));
$keterangan = mb_substr($keterangan, 0, 255);
$verifikator = boleh('admin', 'verifikator');
$skor       = $verifikator && !empty($_POST['isi_nilai_awal']) ? (array)($_POST['skor'] ?? []) : [];
$status     = $verifikator && in_array($_POST['status'] ?? '', ['Draft', 'Terverifikasi', 'Ditolak'], true) ? $_POST['status'] : 'Draft';
$catatan    = $verifikator ? trim((string)($_POST['catatan_verifikasi'] ?? '')) : '';

/* Data lama (untuk ubah) */
$lama = null;
if ($id > 0) {
    $b = jalankan($koneksi, "SELECT * FROM tb_usulan WHERE id_usulan = ?", 'i', [$id]);
    if (!$b) {
        balik('galat', 'Usulan tidak ditemukan.', 'usulan.php');
    }
    $lama = $b[0];
    if (!boleh_kecamatan((int)$lama['id_kecamatan'])) {
        balik('galat', 'Usulan ini berada di luar kecamatan akun Anda.', 'usulan.php');
    }
    if (peran() === 'operator' && kec_user() === 0 && (int)$lama['id_user'] !== (int)$_SESSION['id_user']) {
        balik('galat', 'Operator hanya dapat mengubah usulan yang diinputnya sendiri.', 'usulan.php');
    }
    if (!$verifikator) {
        $status  = $lama['status'];
        $catatan = (string)$lama['catatan_verifikasi'];
    }
}

/* Pemeriksaan isian */
if ($id_kec <= 0 || $desa === '' || $id_sumber <= 0) {
    balik('galat', 'Lengkapi lokasi usulan: kecamatan, desa, dan sumber usulan.', $form);
}
if ($id_kamus <= 0) {
    balik('galat', 'Pilih usulan dari kamus terlebih dahulu.', $form);
}
if ($volume <= 0) {
    balik('galat', 'Isi jumlah (volume) usulan dengan angka lebih dari nol.', $form);
}
if ($anggaran < 0) {
    balik('galat', 'Perkiraan biaya tidak boleh negatif.', $form);
}

/* Kamus: harus aktif, kecuali entri yang memang sudah dipakai usulan ini sebelumnya */
$km = jalankan($koneksi, "SELECT * FROM tb_kamus_usulan WHERE id_kamus = ?", 'i', [$id_kamus]);
if (!$km || (!(int)$km[0]['aktif'] && (!$lama || (int)$lama['id_kamus'] !== $id_kamus))) {
    balik('galat', 'Usulan yang dipilih tidak ada atau sudah tidak aktif di kamus.', $form);
}
$km = $km[0];

/* Desa harus terdaftar pada kecamatan tersebut (bila master desa sudah diisi) */
$peta_desa = ambil_peta_desa($koneksi);
$desa_sama = $lama && (int)$lama['id_kecamatan'] === $id_kec && $lama['desa'] === $desa;
if (!empty($peta_desa[$id_kec]) && !in_array($desa, $peta_desa[$id_kec], true) && !$desa_sama) {
    balik('galat', 'Desa "' . $desa . '" tidak terdaftar pada kecamatan ini. Pilih desa dari daftar.', $form);
}

/* Usulan yang sama untuk desa yang sama pada tahun yang sama tidak boleh ganda */
$ganda = jalankan($koneksi, "SELECT kode_usulan FROM tb_usulan
    WHERE tahun = ? AND id_kecamatan = ? AND desa = ? AND id_kamus = ? AND status <> 'Ditolak' AND id_usulan <> ?",
    'iisii', [$tahun, $id_kec, $desa, $id_kamus, $id]);
if ($ganda) {
    balik('galat', '"' . $km['nama_usulan'] . '" sudah diusulkan untuk ' . $desa . ' tahun ' . $tahun
        . ' (kode ' . $ganda[0]['kode_usulan'] . '). Pilih usulan lain.', $form);
}

$nama   = $km['nama_usulan'];
$uraian = $km['permasalahan'];
$id_sektor = (int)$km['id_sektor'];
$id_gerbang = $km['id_gerbang'] !== null ? (int)$km['id_gerbang'] : null;
$id_opd    = (int)$km['id_opd'];
$satuan    = $km['satuan'];

if ($lama) {
    /* Lokasi tidak boleh dipindah setelah dinilai, agar desa pengusul tidak ikut menilai usulannya sendiri */
    if ((int)$lama['jumlah_penilai'] > 0 && ((int)$lama['id_kecamatan'] !== $id_kec || $lama['desa'] !== $desa)) {
        balik('galat', 'Lokasi tidak dapat diubah karena usulan sudah dinilai ' . (int)$lama['jumlah_penilai'] . ' desa.', $form);
    }

    $stmt = mysqli_prepare($koneksi, "UPDATE tb_usulan SET
        tahun=?, id_kecamatan=?, desa=?, id_sumber=?, id_sektor=?, id_opd=?, id_kamus=?, id_gerbang=?,
        nama_usulan=?, uraian_masalah=?, keterangan_lokasi=?, volume=?, satuan=?, estimasi_anggaran=?,
        status=?, catatan_verifikasi=? WHERE id_usulan=?");
    mysqli_stmt_bind_param($stmt, 'iisiiiiisssdsdssi',
        $tahun, $id_kec, $desa, $id_sumber, $id_sektor, $id_opd, $id_kamus, $id_gerbang,
        $nama, $uraian, $keterangan, $volume, $satuan, $anggaran, $status, $catatan, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $hasil = $skor ? simpan_skor($koneksi, $id, $skor) : perbarui_nilai_akhir($koneksi, $id);

    /* Bila tahun atau lokasi berubah, urutan prioritas di desa lama ikut dihitung ulang */
    if ((int)$lama['tahun'] !== $tahun || (int)$lama['id_kecamatan'] !== $id_kec || $lama['desa'] !== $desa) {
        rapikan_urutan_prioritas($koneksi, (int)$lama['tahun'], (int)$lama['id_kecamatan'], $lama['desa']);
        rapikan_urutan_prioritas($koneksi, $tahun, $id_kec, $desa);
    }

    catat_log($koneksi, 'ubah usulan', 'tb_usulan#' . $id, $km['kode'] . ' ' . $nama . ', nilai ' . ($hasil['total'] ?? '-'));
    balik('sukses', 'Perubahan tersimpan. ' . pesan_nilai($hasil), 'usulan_detail.php?id=' . $id);
}

/* Usulan baru: satu desa paling banyak JUMLAH_PRIORITAS_DESA usulan prioritas */
$sudah = jumlah_prioritas_desa($koneksi, $tahun, $id_kec, $desa);
if ($sudah >= JUMLAH_PRIORITAS_DESA) {
    balik('peringatan', $desa . ' sudah memiliki ' . JUMLAH_PRIORITAS_DESA . ' usulan prioritas untuk tahun ' . $tahun
        . '. Hapus atau ganti salah satunya lebih dulu di menu Prioritas desa.', 'prioritas.php?desa=' . urlencode($desa) . '&tahun=' . $tahun);
}

$nama_kec = jalankan($koneksi, "SELECT nama FROM tb_kecamatan WHERE id_kecamatan = ?", 'i', [$id_kec]);
$kode = buat_kode_usulan($koneksi, $tahun, $nama_kec[0]['nama'] ?? 'BANGGAI');
$id_user = (int)$_SESSION['id_user'];

$stmt = mysqli_prepare($koneksi, "INSERT INTO tb_usulan
    (kode_usulan, tahun, id_kecamatan, desa, id_sumber, id_sektor, id_opd, id_kamus, id_gerbang,
     nama_usulan, uraian_masalah, keterangan_lokasi, volume, satuan, estimasi_anggaran,
     status, catatan_verifikasi, id_user)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
mysqli_stmt_bind_param($stmt, 'siisiiiiisssdsdssi',
    $kode, $tahun, $id_kec, $desa, $id_sumber, $id_sektor, $id_opd, $id_kamus, $id_gerbang,
    $nama, $uraian, $keterangan, $volume, $satuan, $anggaran, $status, $catatan, $id_user);
if (!mysqli_stmt_execute($stmt)) {
    balik('galat', 'Usulan gagal disimpan: ' . mysqli_error($koneksi), $form);
}
$id_baru = mysqli_insert_id($koneksi);
mysqli_stmt_close($stmt);

rapikan_urutan_prioritas($koneksi, $tahun, $id_kec, $desa);
$hasil = $skor ? simpan_skor($koneksi, $id_baru, $skor) : perbarui_nilai_akhir($koneksi, $id_baru);
catat_log($koneksi, 'tambah usulan', 'tb_usulan#' . $id_baru, $kode . ' ' . $nama . ' untuk ' . $desa);

$jumlah_desa = jumlah_prioritas_desa($koneksi, $tahun, $id_kec, $desa);
$sisa_slot   = max(0, JUMLAH_PRIORITAS_DESA - $jumlah_desa);

$pesan = '"' . $nama . '" tersimpan sebagai prioritas ke-' . $jumlah_desa . ' untuk ' . $desa . '. '
       . 'Desa lain di kecamatan ini sudah bisa menilainya. '
       . ($sisa_slot > 0 ? 'Sisa ' . $sisa_slot . ' usulan prioritas lagi untuk desa ini.'
                         : 'Usulan prioritas ' . $desa . ' sudah lengkap ' . JUMLAH_PRIORITAS_DESA . '.');

if (!empty($_POST['lagi'])) {
    $_SESSION['form_terakhir'] = ['tahun' => $tahun, 'id_kecamatan' => $id_kec, 'desa' => $desa, 'id_sumber' => $id_sumber];
    balik('sukses', $pesan, 'usulan_form.php');
}
unset($_SESSION['form_terakhir']);
balik('sukses', $pesan, 'usulan_detail.php?id=' . $id_baru);
