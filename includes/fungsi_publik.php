<?php
/**
 * Fungsi bantu halaman publik (index.php, lihat.php, lihat_usulan.php).
 * Publik hanya melihat usulan prioritas desa (5 teratas per desa), termasuk yang masih menunggu penilaian.
 */
require_once __DIR__ . '/fungsi.php';

/* Syarat dasar data publik; parameter pertama selalu tahun.
   Publik hanya melihat usulan prioritas desa: 5 usulan bernilai tertinggi di setiap desa. */
define('SYARAT_PUBLIK', "u.tahun = ? AND u.status <> 'Ditolak' AND " . syarat_prioritas('u'));

/* Jumlah desa yang semestinya menilai: semua desa aktif sekecamatan kecuali desa pengusul */
const SQL_TARGET_PENILAI = "(SELECT COUNT(*) FROM tb_desa dt
    WHERE dt.id_kecamatan = u.id_kecamatan AND dt.aktif = 1 AND dt.nama <> u.desa)";

/**
 * Peringkat kompetisi: nilai sama mendapat nomor sama.
 * Usulan yang belum bernilai tidak diberi peringkat.
 */
function beri_peringkat(array $baris): array
{
    $nomor = 0;
    $sebelum = null;
    $urut = 0;
    foreach ($baris as $i => $b) {
        if ($b['total_nilai'] === null) {
            $baris[$i]['peringkat'] = null;
            continue;
        }
        $urut++;
        if ($sebelum === null || (float)$b['total_nilai'] < (float)$sebelum) {
            $nomor = $urut;
            $sebelum = $b['total_nilai'];
        }
        $baris[$i]['peringkat'] = $nomor;
    }
    return $baris;
}

/** Sel nilai berbatang untuk tabel publik */
function sel_nilai(array $u): string
{
    $n_penilai = (int)($u['jumlah_penilai'] ?? 0);
    if ($u['total_nilai'] === null) {
        $target = isset($u['target']) ? ' dari ' . (int)$u['target'] : '';
        return '<div class="nilai"><span class="status-tunggu">Belum dinilai</span>'
             . '<span class="sumber">' . $n_penilai . $target . ' desa menilai</span></div>';
    }
    $n = (float)$u['total_nilai'];
    $w = max(2, min(100, $n));
    $sumber = $n_penilai > 0 ? $n_penilai . ' desa menilai' : 'nilai awal';
    return '<div class="nilai"><span class="angka">' . angka($n) . '</span>'
         . '<span class="batang"><i style="width:' . $w . '%;background:' . e($u['warna_kategori'] ?: '#64748B') . '"></i></span>'
         . '<span class="sumber">' . $sumber . '</span></div>';
}

/** Lencana kategori atau tanda menunggu */
function lencana_kategori(array $u): string
{
    if ($u['total_nilai'] === null) {
        return '<span class="status-tunggu">Menunggu</span>';
    }
    return '<span class="lencana" style="background:' . e($u['warna_kategori'] ?: '#64748B') . '">'
         . e($u['kategori'] ?: 'Tanpa kategori') . '</span>';
}

/** Tahun yang tersedia, termasuk tahun bawaan */
function daftar_tahun_publik(mysqli $k): array
{
    $t = array_map('intval', array_column(
        jalankan($k, "SELECT DISTINCT tahun FROM tb_usulan ORDER BY tahun DESC"), 'tahun'));
    if (!in_array(APP_TAHUN_DEFAULT, $t, true)) { $t[] = APP_TAHUN_DEFAULT; }
    rsort($t);
    return $t;
}

/** Peta desa per kecamatan: master desa, dilengkapi desa yang pernah diinput */
function peta_desa_publik(mysqli $k, int $tahun): array
{
    $peta = ambil_peta_desa($k);
    foreach (jalankan($k, "SELECT DISTINCT id_kecamatan, desa FROM tb_usulan u WHERE " . SYARAT_PUBLIK, 'i', [$tahun]) as $r) {
        $ik = (int)$r['id_kecamatan'];
        if (empty($peta[$ik]) || !in_array($r['desa'], $peta[$ik], true)) {
            $peta[$ik][] = $r['desa'];
        }
    }
    foreach ($peta as &$dd) { sort($dd, SORT_NATURAL | SORT_FLAG_CASE); }
    unset($dd);
    return $peta;
}
