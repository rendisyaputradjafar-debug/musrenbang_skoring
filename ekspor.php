<?php
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();

$g = $_GET;
$g['tahun'] = $g['tahun'] ?? APP_TAHUN_DEFAULT;
$f = bangun_filter($g);

$mode = ($g['mode'] ?? 'kabupaten') === 'kecamatan' ? 'kecamatan' : 'kabupaten';
$urutan = $mode === 'kecamatan'
    ? 'kec.nama ASC, u.total_nilai DESC, u.estimasi_anggaran ASC'
    : 'u.total_nilai DESC, u.estimasi_anggaran ASC';

$daftar = jalankan($koneksi,
    SQL_USULAN_DASAR . " WHERE " . $f['where'] . " ORDER BY " . $urutan,
    $f['tipe'], $f['nilai']);

$kriteria = ambil_kriteria($koneksi);

/* Rincian skor per usulan */
$skor = [];
if ($daftar) {
    $id_list = implode(',', array_map('intval', array_column($daftar, 'id_usulan')));
    foreach (jalankan($koneksi, "SELECT id_usulan, id_kriteria, skor, nilai
                                 FROM tb_usulan_skor WHERE id_usulan IN ($id_list)") as $s) {
        $skor[(int)$s['id_usulan']][(int)$s['id_kriteria']] = $s;
    }
    /* Usulan yang sudah dinilai desa memakai rata-rata penilaian antar-desa */
    foreach (jalankan($koneksi, "SELECT p.id_usulan, ps.id_kriteria,
                                        ROUND(AVG(ps.skor), 2) AS skor, ROUND(AVG(ps.nilai), 2) AS nilai
                                 FROM tb_penilaian_skor ps JOIN tb_penilaian p ON p.id_penilaian = ps.id_penilaian
                                 WHERE p.id_usulan IN ($id_list)
                                 GROUP BY p.id_usulan, ps.id_kriteria") as $s) {
        $skor[(int)$s['id_usulan']][(int)$s['id_kriteria']] = $s;
    }
}

$nama_berkas = 'peringkat_musrenbang_' . $mode . '_' . $g['tahun'] . '_' . date('Ymd_Hi') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nama_berkas . '"');

$keluar = fopen('php://output', 'w');
fwrite($keluar, "\xEF\xBB\xBF"); // penanda UTF-8 agar Excel membaca huruf beraksen dengan benar

$judul = [$mode === 'kecamatan' ? 'Peringkat Kecamatan' : 'Peringkat Kabupaten','Kode Usulan','Tahun','Kecamatan','Desa/Kelurahan','Sumber Usulan','Sektor',
          'OPD Penanggung Jawab','Nama Usulan','Uraian Masalah','Volume','Satuan','Estimasi Anggaran'];
foreach ($kriteria as $kr) { $judul[] = 'Skor ' . $kr['nama'] . ' (rata-rata)'; }
foreach ($kriteria as $kr) { $judul[] = 'Nilai ' . $kr['nama'] . ' (bobot ' . rtrim(rtrim((string)$kr['bobot'], '0'), '.') . ')'; }
$judul[] = 'Total 0-100';
$judul[] = 'Sumber Nilai';
$judul[] = 'Jumlah Desa Penilai';
$judul[] = 'Kategori Prioritas';
$judul[] = 'Status';
$judul[] = 'Catatan Verifikasi';
fputcsv($keluar, $judul, ';');

$nomor = 0;
$nilai_sebelum = null;
$kelompok_sebelum = null;
$awal_kelompok = 0;

foreach ($daftar as $i => $d) {
    /* Mode per kecamatan menomori ulang peringkat pada setiap kecamatan */
    if ($mode === 'kecamatan' && $d['kecamatan'] !== $kelompok_sebelum) {
        $kelompok_sebelum = $d['kecamatan'];
        $awal_kelompok = $i;
        $nilai_sebelum = null;
    }
    if ($nilai_sebelum === null || (float)$d['total_nilai'] < (float)$nilai_sebelum) {
        $nomor = $i - $awal_kelompok + 1;
        $nilai_sebelum = $d['total_nilai'];
    }

    $baris = [
        $nomor, $d['kode_usulan'], $d['tahun'], $d['kecamatan'], $d['desa'], $d['sumber'],
        $d['sektor'], $d['opd'], $d['nama_usulan'], $d['uraian_masalah'],
        $d['volume'], $d['satuan'], $d['estimasi_anggaran'],
    ];
    foreach ($kriteria as $kr) {
        $baris[] = $skor[(int)$d['id_usulan']][(int)$kr['id_kriteria']]['skor'] ?? '';
    }
    foreach ($kriteria as $kr) {
        $baris[] = $skor[(int)$d['id_usulan']][(int)$kr['id_kriteria']]['nilai'] ?? '';
    }
    $baris[] = $d['total_nilai'];
    $baris[] = (int)$d['jumlah_penilai'] > 0 ? 'Rata-rata penilaian desa' : ($d['total_nilai'] !== null ? 'Nilai awal' : 'Belum dinilai');
    $baris[] = (int)$d['jumlah_penilai'];
    $baris[] = $d['kategori'];
    $baris[] = $d['status'];
    $baris[] = $d['catatan_verifikasi'];

    fputcsv($keluar, $baris, ';');
}

fclose($keluar);
catat_log($koneksi, 'ekspor', 'tb_usulan', count($daftar) . ' baris');
exit;