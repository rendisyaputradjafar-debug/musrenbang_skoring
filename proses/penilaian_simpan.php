<?php
/**
 * Menyimpan penilaian satu desa terhadap satu usulan desa lain.
 */
require_once __DIR__ . '/../includes/fungsi.php';
wajib_login();
wajib_peran('desa');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../penilaian.php');
    exit;
}
periksa_csrf();

$id      = (int)($_POST['id_usulan'] ?? 0);
$skor    = is_array($_POST['skor'] ?? null) ? $_POST['skor'] : [];
$catatan = trim((string)($_POST['catatan'] ?? ''));
$catatan = function_exists('mb_substr') ? mb_substr($catatan, 0, 500) : substr($catatan, 0, 500);
$lanjut  = !empty($_POST['lanjut']);

function kembali(string $jenis, string $pesan, string $tujuan)
{
    pesan_set($jenis, $pesan);
    header('Location: ../' . $tujuan);
    exit;
}

$b = jalankan($koneksi, "SELECT id_usulan, tahun, id_kecamatan, desa, status, nama_usulan, peringkat_desa
    FROM tb_usulan WHERE id_usulan = ?", 'i', [$id]);
if (!$b) {
    kembali('galat', 'Usulan tidak ditemukan.', 'penilaian.php');
}
$u = $b[0];

/* Aturan penilaian antar-desa */
if ((int)$u['id_kecamatan'] !== kec_user()) {
    kembali('galat', 'Usulan ini berada di luar kecamatan Anda.', 'penilaian.php');
}
if ($u['desa'] === nama_desa_user()) {
    kembali('galat', 'Usulan desa sendiri tidak dapat dinilai.', 'penilaian.php');
}
if ((int)$u['peringkat_desa'] < 1 || (int)$u['peringkat_desa'] > JUMLAH_PRIORITAS_DESA) {
    kembali('galat', 'Usulan ini bukan usulan prioritas desa tersebut, jadi tidak dinilai.', 'penilaian.php');
}
if ($u['status'] !== 'Draft') {
    kembali('galat', 'Penilaian untuk usulan ini sudah ditutup karena berstatus ' . $u['status'] . '.', 'penilaian.php');
}

/* Semua kriteria aktif wajib terisi dengan skor dalam rentang */
$bersih = [];
foreach (ambil_kriteria($koneksi) as $kr) {
    $idk = (int)$kr['id_kriteria'];
    $nilai = isset($skor[$idk]) ? (int)$skor[$idk] : 0;
    if ($nilai < 1 || $nilai > (int)$kr['skala_maks']) {
        kembali('galat', 'Kriteria "' . $kr['nama'] . '" belum diberi skor.', 'penilaian_form.php?id=' . $id);
    }
    $bersih[$idk] = $nilai;
}

try {
    $hasil = simpan_penilaian_desa($koneksi, $id, desa_user(), (int)$_SESSION['id_user'], $bersih, $catatan);
} catch (Throwable $t) {
    kembali('galat', 'Penilaian gagal disimpan, silakan coba lagi. (' . $t->getMessage() . ')', 'penilaian_form.php?id=' . $id);
}

catat_log($koneksi, 'nilai usulan', 'tb_usulan#' . $id,
    nama_desa_user() . ' memberi nilai ' . $hasil['nilai_desa'] . '; rata-rata menjadi ' . $hasil['total']
    . ' dari ' . $hasil['jumlah_penilai'] . ' desa');

$pesan = 'Penilaian untuk "' . $u['nama_usulan'] . '" tersimpan dengan nilai ' . angka($hasil['nilai_desa'])
       . '. Rata-rata dari ' . $hasil['jumlah_penilai'] . ' desa sekarang ' . angka($hasil['total']) . '.';

if ($lanjut) {
    $berikut = jalankan($koneksi, "SELECT x.id_usulan FROM tb_usulan x
        WHERE x.tahun = ? AND x.id_kecamatan = ? AND x.desa <> ? AND x.status = 'Draft' AND " . syarat_prioritas('x') . "
          AND NOT EXISTS (SELECT 1 FROM tb_penilaian p WHERE p.id_usulan = x.id_usulan AND p.id_desa_penilai = ?)
        ORDER BY x.dibuat_pada, x.id_usulan LIMIT 1",
        'iisi', [(int)$u['tahun'], kec_user(), nama_desa_user(), desa_user()]);
    if ($berikut) {
        kembali('sukses', $pesan, 'penilaian_form.php?id=' . (int)$berikut[0]['id_usulan']);
    }
    $pesan .= ' Semua usulan terbuka sudah Anda nilai.';
}
kembali('sukses', $pesan, 'penilaian.php?tahun=' . (int)$u['tahun'] . '&tab=sudah');
