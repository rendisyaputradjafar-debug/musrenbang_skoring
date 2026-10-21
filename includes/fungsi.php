<?php
/**
 * Fungsi bantu aplikasi.
 * Inti perhitungan skor ada pada hitung_total_nilai() dan tentukan_kategori().
 */

require_once __DIR__ . '/../config/koneksi.php';

/* ------------------------------------------------------------------ */
/* Keamanan sesi                                                       */
/* ------------------------------------------------------------------ */

function wajib_login()
{
    if (empty($_SESSION['id_user'])) {
        header('Location: login.php');
        exit;
    }
}

function peran(): string
{
    return $_SESSION['role'] ?? 'operator';
}

function boleh(string ...$peran_diizinkan): bool
{
    return in_array(peran(), $peran_diizinkan, true);
}

function wajib_peran(string ...$peran_diizinkan)
{
    if (!boleh(...$peran_diizinkan)) {
        http_response_code(403);
        die('<p style="font-family:system-ui;padding:32px">Akun Anda tidak berwenang membuka halaman ini.</p>');
    }
}

/**
 * Kecamatan penugasan akun yang sedang masuk.
 * Nilai 0 berarti akun tidak terikat kecamatan (admin/verifikator kabupaten).
 */
function kec_user(): int
{
    return (int)($_SESSION['id_kecamatan'] ?? 0);
}

/**
 * Desa asal akun desa yang sedang masuk (0 bila bukan akun desa).
 */
function desa_user(): int
{
    return (int)($_SESSION['id_desa'] ?? 0);
}

function nama_desa_user(): string
{
    return (string)($_SESSION['nama_desa'] ?? '');
}

/**
 * Apakah akun boleh melihat atau mengubah usulan pada kecamatan tertentu.
 */
function boleh_kecamatan(int $id_kecamatan): bool
{
    return kec_user() === 0 || kec_user() === $id_kecamatan;
}

/**
 * Tautan aksi untuk satu usulan pada daftar/peringkat, sesuai jenis akun:
 * akun desa hanya menilai usulan desa lain; input dan ubah dilakukan kecamatan.
 */
function aksi_usulan(array $u): string
{
    $id = (int)$u['id_usulan'];
    if (desa_user() > 0) {
        /* Akun desa hanya menilai usulan desa lain */
        if ($u['desa'] === nama_desa_user()) {
            return '';
        }
        if ($u['status'] === 'Draft') {
            return '<a href="penilaian_form.php?id=' . $id . '" class="text-xs font-semibold" style="color:#44403B">Nilai</a>';
        }
        return '';
    }
    if (boleh('admin', 'verifikator') || kec_user() > 0 || (int)$u['id_user'] === (int)($_SESSION['id_user'] ?? 0)) {
        return '<a href="usulan_form.php?id=' . $id . '" class="text-xs" style="color:#44403B">Ubah</a>';
    }
    return '';
}

function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function periksa_csrf()
{
    $kiriman = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $kiriman)) {
        die('<p style="font-family:system-ui;padding:32px">Permintaan ditolak. Muat ulang halaman lalu ulangi.</p>');
    }
}

/* ------------------------------------------------------------------ */
/* Format keluaran                                                     */
/* ------------------------------------------------------------------ */

function e($teks): string
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka): string
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function angka($nilai, int $desimal = 2): string
{
    return number_format((float)$nilai, $desimal, ',', '.');
}

function pesan_set(string $jenis, string $teks)
{
    $_SESSION['pesan'] = ['jenis' => $jenis, 'teks' => $teks];
}

function pesan_ambil(): ?array
{
    if (empty($_SESSION['pesan'])) {
        return null;
    }
    $p = $_SESSION['pesan'];
    unset($_SESSION['pesan']);
    return $p;
}

/* ------------------------------------------------------------------ */
/* Referensi                                                           */
/* ------------------------------------------------------------------ */

function ambil_daftar(mysqli $k, string $tabel, string $kolom_id, string $urut = 'nama'): array
{
    $hasil = [];
    $q = mysqli_query($k, "SELECT * FROM `$tabel` WHERE aktif = 1 ORDER BY $urut");
    while ($b = mysqli_fetch_assoc($q)) {
        $hasil[] = $b;
    }
    return $hasil;
}

function ambil_kriteria(mysqli $k): array
{
    $hasil = [];
    $q = mysqli_query($k, "SELECT * FROM tb_kriteria WHERE aktif = 1 ORDER BY urut, id_kriteria");
    while ($b = mysqli_fetch_assoc($q)) {
        $hasil[] = $b;
    }
    return $hasil;
}

function ambil_kategori(mysqli $k): array
{
    $hasil = [];
    $q = mysqli_query($k, "SELECT * FROM tb_kategori ORDER BY batas_bawah DESC");
    while ($b = mysqli_fetch_assoc($q)) {
        $hasil[] = $b;
    }
    return $hasil;
}

/**
 * Daftar kecamatan untuk pilihan formulir.
 * Akun kecamatan hanya mendapat kecamatannya sendiri.
 */
function ambil_kecamatan_pilihan(mysqli $k): array
{
    $semua = ambil_daftar($k, 'tb_kecamatan', 'id_kecamatan');
    if (kec_user() === 0) {
        return $semua;
    }
    return array_values(array_filter($semua, function ($r) {
        return (int)$r['id_kecamatan'] === kec_user();
    }));
}

/**
 * Peta desa per kecamatan dari tabel master: [id_kecamatan => [nama desa, ...]].
 * Dipakai untuk pilihan desa yang bergantung pada kecamatan.
 */
function ambil_peta_desa(mysqli $k, bool $hanya_aktif = true): array
{
    $peta = [];
    $syarat = $hanya_aktif ? 'WHERE aktif = 1' : '';
    $q = mysqli_query($k, "SELECT id_kecamatan, nama FROM tb_desa $syarat ORDER BY nama");
    if (!$q) {
        return $peta;
    }
    while ($b = mysqli_fetch_assoc($q)) {
        $peta[(int)$b['id_kecamatan']][] = $b['nama'];
    }
    return $peta;
}

function total_bobot(mysqli $k): float
{
    $q = mysqli_query($k, "SELECT COALESCE(SUM(bobot),0) AS t FROM tb_kriteria WHERE aktif = 1");
    return (float)mysqli_fetch_assoc($q)['t'];
}

/* ------------------------------------------------------------------ */
/* Inti skoring                                                        */
/* ------------------------------------------------------------------ */

/**
 * Nilai satu kriteria = (skor / skala maksimum) x bobot.
 * Rumus ini sama dengan lembar kerja Excel: M2/5*25 dan seterusnya.
 */
function hitung_nilai_kriteria($skor, $bobot, $skala_maks = 5): float
{
    $skala_maks = (int)$skala_maks > 0 ? (int)$skala_maks : 5;
    return round(((float)$skor / $skala_maks) * (float)$bobot, 2);
}

/**
 * Total nilai 0-100 dari pasangan id_kriteria => skor.
 * Mengembalikan array rincian dan total agar perhitungan dapat ditelusuri.
 */
function hitung_total_nilai(array $daftar_kriteria, array $skor_masukan): array
{
    $rincian = [];
    $total   = 0.0;

    foreach ($daftar_kriteria as $kr) {
        $id   = (int)$kr['id_kriteria'];
        $skor = isset($skor_masukan[$id]) ? (int)$skor_masukan[$id] : 0;
        if ($skor < 1) {
            $skor = 1;
        }
        if ($skor > (int)$kr['skala_maks']) {
            $skor = (int)$kr['skala_maks'];
        }
        $nilai = hitung_nilai_kriteria($skor, $kr['bobot'], $kr['skala_maks']);
        $total += $nilai;

        $rincian[] = [
            'id_kriteria' => $id,
            'kode'        => $kr['kode'],
            'nama'        => $kr['nama'],
            'skor'        => $skor,
            'bobot'       => (float)$kr['bobot'],
            'skala_maks'  => (int)$kr['skala_maks'],
            'nilai'       => $nilai,
        ];
    }

    return ['rincian' => $rincian, 'total' => round($total, 2)];
}

/**
 * Kategori prioritas ditentukan dari ambang batas bawah pada tb_kategori.
 */
function tentukan_kategori(array $daftar_kategori, float $total): ?array
{
    foreach ($daftar_kategori as $kat) {
        if ($total >= (float)$kat['batas_bawah']) {
            return $kat;
        }
    }
    return null;
}

/**
 * Simpan skor awal (dari form input admin/verifikator atau impor Excel).
 * Nilai awal hanya dipakai sebagai nilai akhir selama belum ada desa yang menilai.
 */
function simpan_skor(mysqli $k, int $id_usulan, array $skor_masukan): array
{
    $kriteria = ambil_kriteria($k);
    $hitung   = hitung_total_nilai($kriteria, $skor_masukan);

    mysqli_query($k, "DELETE FROM tb_usulan_skor WHERE id_usulan = " . (int)$id_usulan);

    $stmt = mysqli_prepare($k, "INSERT INTO tb_usulan_skor
        (id_usulan, id_kriteria, skor, bobot, skala_maks, nilai)
        VALUES (?,?,?,?,?,?)");
    foreach ($hitung['rincian'] as $r) {
        mysqli_stmt_bind_param(
            $stmt, 'iiidid',
            $id_usulan, $r['id_kriteria'], $r['skor'], $r['bobot'], $r['skala_maks'], $r['nilai']
        );
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($k, "UPDATE tb_usulan SET nilai_awal = ? WHERE id_usulan = ?");
    mysqli_stmt_bind_param($stmt, 'di', $hitung['total'], $id_usulan);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return perbarui_nilai_akhir($k, $id_usulan);
}

/**
 * Nilai akhir usulan:
 *   - rata-rata seluruh penilaian antar-desa, bila sudah ada desa yang menilai;
 *   - nilai awal, bila belum ada desa yang menilai;
 *   - kosong, bila keduanya belum ada.
 * Kategori mengikuti ambang pada tb_kategori.
 */
function perbarui_nilai_akhir(mysqli $k, int $id_usulan): array
{
    $id = (int)$id_usulan;
    mysqli_query($k, "
        UPDATE tb_usulan u
        LEFT JOIN (SELECT id_usulan, COUNT(*) AS n, ROUND(AVG(total_nilai), 2) AS rata
                   FROM tb_penilaian WHERE id_usulan = $id GROUP BY id_usulan) p
               ON p.id_usulan = u.id_usulan
        SET u.jumlah_penilai = COALESCE(p.n, 0),
            u.total_nilai    = IF(COALESCE(p.n, 0) > 0, p.rata, u.nilai_awal)
        WHERE u.id_usulan = $id");
    mysqli_query($k, "
        UPDATE tb_usulan u
        SET u.id_kategori = (SELECT kt.id_kategori FROM tb_kategori kt
                             WHERE u.total_nilai IS NOT NULL AND kt.batas_bawah <= u.total_nilai
                             ORDER BY kt.batas_bawah DESC LIMIT 1)
        WHERE u.id_usulan = $id");

    $b = jalankan($k, "SELECT u.total_nilai, u.jumlah_penilai, u.peringkat_desa, kt.nama, kt.warna
        FROM tb_usulan u LEFT JOIN tb_kategori kt ON kt.id_kategori = u.id_kategori
        WHERE u.id_usulan = ?", 'i', [$id]);
    $b = $b[0] ?? [];
    return [
        'total'          => isset($b['total_nilai']) ? (float)$b['total_nilai'] : null,
        'jumlah_penilai' => (int)($b['jumlah_penilai'] ?? 0),
        'peringkat_desa' => isset($b['peringkat_desa']) ? (int)$b['peringkat_desa'] : null,
        'kategori'       => !empty($b['nama']) ? ['nama' => $b['nama'], 'warna' => $b['warna']] : null,
    ];
}

/**
 * Urutan prioritas usulan di dalam satu desa (tahun, kecamatan, desa yang sama).
 * Urutan ditetapkan oleh operator kecamatan saat menginput, bukan dari hasil penilaian.
 * Urutan 1 sampai JUMLAH_PRIORITAS_DESA adalah usulan prioritas yang dinilai desa lain;
 * selebihnya menjadi cadangan dan tidak ikut dinilai maupun ditampilkan ke publik.
 */
function rapikan_urutan_prioritas(mysqli $k, int $tahun, int $id_kecamatan, string $desa): void
{
    $daftar = jalankan($k, "SELECT id_usulan FROM tb_usulan
        WHERE tahun = ? AND id_kecamatan = ? AND desa = ? AND status <> 'Ditolak'
        ORDER BY (peringkat_desa IS NULL), peringkat_desa, id_usulan", 'iis', [$tahun, $id_kecamatan, $desa]);
    $no = 0;
    foreach ($daftar as $d) {
        $no++;
        jalankan($k, "UPDATE tb_usulan SET peringkat_desa = ? WHERE id_usulan = ?", 'ii', [$no, (int)$d['id_usulan']]);
    }
    jalankan($k, "UPDATE tb_usulan SET peringkat_desa = NULL
        WHERE tahun = ? AND id_kecamatan = ? AND desa = ? AND status = 'Ditolak'",
        'iis', [$tahun, $id_kecamatan, $desa]);
}

/** Merapikan urutan pada desa tempat sebuah usulan berada */
function rapikan_urutan_usulan(mysqli $k, int $id_usulan): void
{
    $b = jalankan($k, "SELECT tahun, id_kecamatan, desa FROM tb_usulan WHERE id_usulan = ?", 'i', [$id_usulan]);
    if ($b) {
        rapikan_urutan_prioritas($k, (int)$b[0]['tahun'], (int)$b[0]['id_kecamatan'], $b[0]['desa']);
    }
}

/** Jumlah usulan prioritas yang sudah terisi untuk satu desa */
function jumlah_prioritas_desa(mysqli $k, int $tahun, int $id_kecamatan, string $desa): int
{
    return (int)jalankan($k, "SELECT COUNT(*) AS n FROM tb_usulan
        WHERE tahun = ? AND id_kecamatan = ? AND desa = ? AND status <> 'Ditolak'",
        'iis', [$tahun, $id_kecamatan, $desa])[0]['n'];
}

/**
 * Menggeser satu usulan ke atas atau ke bawah dalam urutan prioritas desanya.
 * Mengembalikan true bila urutan benar-benar berubah.
 */
function geser_urutan_prioritas(mysqli $k, int $id_usulan, string $arah): bool
{
    $b = jalankan($k, "SELECT tahun, id_kecamatan, desa, peringkat_desa FROM tb_usulan WHERE id_usulan = ?", 'i', [$id_usulan]);
    if (!$b || $b[0]['peringkat_desa'] === null) {
        return false;
    }
    $u = $b[0];
    $tujuan = (int)$u['peringkat_desa'] + ($arah === 'naik' ? -1 : 1);
    if ($tujuan < 1) {
        return false;
    }
    $tetangga = jalankan($k, "SELECT id_usulan FROM tb_usulan
        WHERE tahun = ? AND id_kecamatan = ? AND desa = ? AND status <> 'Ditolak' AND peringkat_desa = ?",
        'iisi', [(int)$u['tahun'], (int)$u['id_kecamatan'], $u['desa'], $tujuan]);
    if (!$tetangga) {
        return false;
    }
    jalankan($k, "UPDATE tb_usulan SET peringkat_desa = ? WHERE id_usulan = ?", 'ii', [$tujuan, $id_usulan]);
    jalankan($k, "UPDATE tb_usulan SET peringkat_desa = ? WHERE id_usulan = ?",
        'ii', [(int)$u['peringkat_desa'], (int)$tetangga[0]['id_usulan']]);
    return true;
}

/** Syarat SQL usulan prioritas desa (5 usulan yang dinilai) */
function syarat_prioritas(string $alias = 'u'): string
{
    return $alias . '.peringkat_desa IS NOT NULL AND ' . $alias . '.peringkat_desa <= ' . (int)JUMLAH_PRIORITAS_DESA;
}

/** Lencana peringkat usulan di desanya */
function lencana_prioritas($peringkat_desa): string
{
    if ($peringkat_desa === null) {
        return '';
    }
    $p = (int)$peringkat_desa;
    if ($p <= JUMLAH_PRIORITAS_DESA) {
        return '<span class="inline-block text-[11px] font-semibold px-1.5 py-0.5 rounded" style="background:#EDEBE4;color:#211F1C">Prioritas ' . $p . '</span>';
    }
    return '<span class="inline-block text-[11px] px-1.5 py-0.5 rounded bg-amber-50 text-amber-800">Cadangan (urutan ' . $p . ')</span>';
}

/** Kamus usulan aktif, dikelompokkan untuk pilihan formulir */
function ambil_kamus(mysqli $k, bool $hanya_aktif = true): array
{
    return jalankan($k, "
        SELECT km.*, s.nama AS sektor, o.nama AS opd
        FROM tb_kamus_usulan km
        JOIN tb_sektor s ON s.id_sektor = km.id_sektor
        JOIN tb_opd o    ON o.id_opd = km.id_opd
        " . ($hanya_aktif ? 'WHERE km.aktif = 1' : '') . "
        ORDER BY s.nama, km.nama_usulan, km.permasalahan");
}

/**
 * Simpan penilaian satu desa terhadap satu usulan.
 * Satu desa hanya punya satu penilaian per usulan; menyimpan ulang berarti memperbarui.
 * Dijalankan dalam transaksi agar aman saat banyak desa menilai bersamaan.
 */
function simpan_penilaian_desa(mysqli $k, int $id_usulan, int $id_desa, int $id_user, array $skor_masukan, string $catatan): array
{
    $kriteria = ambil_kriteria($k);
    $hitung   = hitung_total_nilai($kriteria, $skor_masukan);

    $wajib = function ($hasil) use ($k) {
        if ($hasil === false) {
            throw new RuntimeException(mysqli_error($k) ?: 'Kueri gagal');
        }
        return $hasil;
    };

    mysqli_begin_transaction($k);
    try {
        $stmt = $wajib(mysqli_prepare($k, "INSERT INTO tb_penilaian
                (id_usulan, id_desa_penilai, id_user, total_nilai, catatan)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE id_user = VALUES(id_user), total_nilai = VALUES(total_nilai),
                                    catatan = VALUES(catatan), diubah_pada = NOW()"));
        mysqli_stmt_bind_param($stmt, 'iiids', $id_usulan, $id_desa, $id_user, $hitung['total'], $catatan);
        $wajib(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $b = jalankan($k, "SELECT id_penilaian FROM tb_penilaian WHERE id_usulan = ? AND id_desa_penilai = ?",
            'ii', [$id_usulan, $id_desa]);
        if (!$b) {
            throw new RuntimeException('Penilaian tidak tersimpan');
        }
        $id_p = (int)$b[0]['id_penilaian'];

        $wajib(mysqli_query($k, "DELETE FROM tb_penilaian_skor WHERE id_penilaian = $id_p"));
        $stmt = $wajib(mysqli_prepare($k, "INSERT INTO tb_penilaian_skor
            (id_penilaian, id_kriteria, skor, bobot, skala_maks, nilai) VALUES (?,?,?,?,?,?)"));
        foreach ($hitung['rincian'] as $r) {
            mysqli_stmt_bind_param($stmt, 'iiidid',
                $id_p, $r['id_kriteria'], $r['skor'], $r['bobot'], $r['skala_maks'], $r['nilai']);
            $wajib(mysqli_stmt_execute($stmt));
        }
        mysqli_stmt_close($stmt);

        $akhir = perbarui_nilai_akhir($k, $id_usulan);
        $wajib(mysqli_commit($k));
    } catch (Throwable $t) {
        mysqli_rollback($k);
        throw $t;
    }

    return ['nilai_desa' => $hitung['total']] + $akhir;
}

/**
 * Hitung ulang seluruh usulan. Dipakai setelah bobot atau ambang kategori diubah.
 * Nilai awal dan setiap penilaian desa dihitung ulang dengan bobot terbaru.
 */
function hitung_ulang_semua(mysqli $k): int
{
    $kriteria = ambil_kriteria($k);

    /* Penilaian antar-desa */
    $per_penilaian = [];
    $q = mysqli_query($k, "SELECT id_penilaian, id_kriteria, skor FROM tb_penilaian_skor");
    while ($q && $b = mysqli_fetch_assoc($q)) {
        $per_penilaian[(int)$b['id_penilaian']][(int)$b['id_kriteria']] = (int)$b['skor'];
    }
    $stmt_p = mysqli_prepare($k, "UPDATE tb_penilaian SET total_nilai = ? WHERE id_penilaian = ?");
    $stmt_s = mysqli_prepare($k, "UPDATE tb_penilaian_skor SET bobot = ?, skala_maks = ?, nilai = ?
                                  WHERE id_penilaian = ? AND id_kriteria = ?");
    foreach ($per_penilaian as $id_p => $skor) {
        $h = hitung_total_nilai($kriteria, $skor);
        mysqli_stmt_bind_param($stmt_p, 'di', $h['total'], $id_p);
        mysqli_stmt_execute($stmt_p);
        foreach ($h['rincian'] as $r) {
            mysqli_stmt_bind_param($stmt_s, 'didii', $r['bobot'], $r['skala_maks'], $r['nilai'], $id_p, $r['id_kriteria']);
            mysqli_stmt_execute($stmt_s);
        }
    }
    mysqli_stmt_close($stmt_p);
    mysqli_stmt_close($stmt_s);

    /* Nilai awal, lalu nilai akhir setiap usulan */
    $jumlah = 0;
    $daftar = [];
    $q = mysqli_query($k, "SELECT id_usulan FROM tb_usulan");
    while ($b = mysqli_fetch_assoc($q)) {
        $daftar[] = (int)$b['id_usulan'];
    }
    foreach ($daftar as $id) {
        $skor = [];
        $qs = mysqli_query($k, "SELECT id_kriteria, skor FROM tb_usulan_skor WHERE id_usulan = $id");
        while ($bs = mysqli_fetch_assoc($qs)) {
            $skor[(int)$bs['id_kriteria']] = (int)$bs['skor'];
        }
        if ($skor) {
            simpan_skor($k, $id, $skor);
        } else {
            perbarui_nilai_akhir($k, $id);
        }
        $jumlah++;
    }
    return $jumlah;
}

/**
 * Jumlah desa yang semestinya menilai satu usulan:
 * semua desa aktif di kecamatan itu, kecuali desa pengusul.
 */
function target_penilai(mysqli $k, int $id_kecamatan, string $desa_pengusul): int
{
    $b = jalankan($k, "SELECT COUNT(*) AS n FROM tb_desa
        WHERE id_kecamatan = ? AND aktif = 1 AND nama <> ?", 'is', [$id_kecamatan, $desa_pengusul]);
    return (int)($b[0]['n'] ?? 0);
}

/**
 * Keterangan waktu singkat, misalnya "2 menit lalu".
 */
function waktu_lalu(string $waktu): string
{
    $detik = time() - strtotime($waktu);
    if ($detik < 45)    { return 'baru saja'; }
    if ($detik < 3600)  { return max(1, (int)round($detik / 60)) . ' menit lalu'; }
    if ($detik < 86400) { return (int)floor($detik / 3600) . ' jam lalu'; }
    if ($detik < 172800) { return 'kemarin, ' . date('H:i', strtotime($waktu)); }
    return date('d/m/Y H:i', strtotime($waktu));
}

/* ------------------------------------------------------------------ */
/* Kode usulan dan log                                                 */
/* ------------------------------------------------------------------ */

function buat_kode_usulan(mysqli $k, int $tahun, string $nama_kecamatan): string
{
    $slug = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nama_kecamatan));
    $slug = substr($slug, 0, 12);
    $awalan = "USL-$tahun-$slug-";

    $stmt = mysqli_prepare($k, "SELECT kode_usulan FROM tb_usulan
        WHERE kode_usulan LIKE CONCAT(?, '%') ORDER BY kode_usulan DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $awalan);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $terakhir = mysqli_fetch_assoc($res)['kode_usulan'] ?? null;
    mysqli_stmt_close($stmt);

    $urut = 1;
    if ($terakhir) {
        $urut = (int)substr($terakhir, -4) + 1;
    }
    return $awalan . str_pad((string)$urut, 4, '0', STR_PAD_LEFT);
}

function catat_log(mysqli $k, string $aksi, string $objek = '', string $keterangan = '')
{
    $id_user  = $_SESSION['id_user'] ?? null;
    $username = $_SESSION['username'] ?? null;
    $ip       = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = mysqli_prepare($k, "INSERT INTO tb_log (id_user, username, aksi, objek, keterangan, ip)
        VALUES (?,?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'isssss', $id_user, $username, $aksi, $objek, $keterangan, $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/* ------------------------------------------------------------------ */
/* Kueri usulan dengan penyaring                                       */
/* ------------------------------------------------------------------ */

/**
 * Membangun potongan WHERE beserta parameter dari penyaring GET.
 */
function bangun_filter(array $g): array
{
    $syarat = ['1=1'];
    $tipe   = '';
    $nilai  = [];

    if (!empty($g['tahun'])) {
        $syarat[] = 'u.tahun = ?';
        $tipe .= 'i';
        $nilai[] = (int)$g['tahun'];
    }
    /* Akun kecamatan selalu dikunci pada kecamatannya sendiri */
    if (kec_user() > 0) {
        $g['kecamatan'] = kec_user();
    }
    if (!empty($g['kecamatan'])) {
        $syarat[] = 'u.id_kecamatan = ?';
        $tipe .= 'i';
        $nilai[] = (int)$g['kecamatan'];
    }
    if (!empty($g['desa'])) {
        $syarat[] = 'u.desa = ?';
        $tipe .= 's';
        $nilai[] = (string)$g['desa'];
    }
    if (!empty($g['sektor'])) {
        $syarat[] = 'u.id_sektor = ?';
        $tipe .= 'i';
        $nilai[] = (int)$g['sektor'];
    }
    if (!empty($g['opd'])) {
        $syarat[] = 'u.id_opd = ?';
        $tipe .= 'i';
        $nilai[] = (int)$g['opd'];
    }
    if (!empty($g['kategori'])) {
        $syarat[] = 'u.id_kategori = ?';
        $tipe .= 'i';
        $nilai[] = (int)$g['kategori'];
    }
    if (!empty($g['status'])) {
        $syarat[] = 'u.status = ?';
        $tipe .= 's';
        $nilai[] = $g['status'];
    }
    if (($g['prioritas'] ?? '') === 'ya') {
        $syarat[] = syarat_prioritas('u');
    } elseif (($g['prioritas'] ?? '') === 'bukan') {
        $syarat[] = '(u.peringkat_desa IS NULL OR u.peringkat_desa > ' . (int)JUMLAH_PRIORITAS_DESA . ')';
    }
    if (($g['penilaian'] ?? '') === 'belum') {
        $syarat[] = 'u.total_nilai IS NULL';
    } elseif (($g['penilaian'] ?? '') === 'sudah') {
        $syarat[] = 'u.total_nilai IS NOT NULL';
    }
    if (!empty($g['cari'])) {
        $syarat[] = '(u.nama_usulan LIKE ? OR u.desa LIKE ? OR u.kode_usulan LIKE ?)';
        $tipe .= 'sss';
        $kata = '%' . $g['cari'] . '%';
        $nilai[] = $kata;
        $nilai[] = $kata;
        $nilai[] = $kata;
    }

    return ['where' => implode(' AND ', $syarat), 'tipe' => $tipe, 'nilai' => $nilai];
}

function jalankan(mysqli $k, string $sql, string $tipe = '', array $nilai = [])
{
    $stmt = mysqli_prepare($k, $sql);
    if (!$stmt) {
        die('Kueri gagal: ' . e(mysqli_error($k)));
    }
    if ($tipe !== '') {
        mysqli_stmt_bind_param($stmt, $tipe, ...$nilai);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $baris = [];
    if ($res) {
        while ($b = mysqli_fetch_assoc($res)) {
            $baris[] = $b;
        }
    }
    mysqli_stmt_close($stmt);
    return $baris;
}

const SQL_USULAN_DASAR = "
    SELECT u.*, kec.nama AS kecamatan, s.nama AS sektor, s.warna AS warna_sektor,
           o.nama AS opd, sm.nama AS sumber, kat.nama AS kategori, kat.warna AS warna_kategori
    FROM tb_usulan u
    JOIN tb_kecamatan kec ON kec.id_kecamatan = u.id_kecamatan
    JOIN tb_sektor s      ON s.id_sektor      = u.id_sektor
    JOIN tb_opd o         ON o.id_opd         = u.id_opd
    JOIN tb_sumber_usulan sm ON sm.id_sumber  = u.id_sumber
    LEFT JOIN tb_kategori kat ON kat.id_kategori = u.id_kategori
";

/* ------------------------------------------------------------------ */
/* Evaluasi program GERBANG (9 Program Unggulan RPJMD 2025-2029)        */
/* ------------------------------------------------------------------ */

/** Perangkat daerah penugasan akun yang sedang masuk (0 bila bukan akun OPD) */
function opd_user(): int
{
    return (int)($_SESSION['id_opd'] ?? 0);
}

function nama_opd_user(): string
{
    return (string)($_SESSION['nama_opd'] ?? '');
}

/**
 * Realisasi indikator diambil dari triwulan terakhir yang sudah diisi.
 * Potongan SQL ini dipakai bersama agar perhitungan capaian selalu sama.
 */
function sql_realisasi_terakhir(): string
{
    return "(SELECT c1.realisasi FROM tb_gerbang_capaian c1
             WHERE c1.id_indikator = i.id_indikator ORDER BY c1.triwulan DESC LIMIT 1)";
}

/**
 * Ringkasan capaian setiap program GERBANG pada satu tahun.
 * Capaian indikator = realisasi dibagi target (maksimal 100 persen),
 * capaian gerbang = rata-rata tertimbang seluruh indikatornya.
 */
function ambil_gerbang(mysqli $k, int $tahun, int $id_opd = 0): array
{
    $syarat_opd = $id_opd > 0 ? ' AND i.id_opd = ' . (int)$id_opd : '';
    $r = sql_realisasi_terakhir();
    return jalankan($k, "
        SELECT g.*,
               COUNT(i.id_indikator) AS jml_indikator,
               COALESCE(SUM($r IS NOT NULL), 0) AS indikator_terisi,
               ROUND(SUM(LEAST(100, COALESCE($r, 0) / NULLIF(i.target, 0) * 100) * i.bobot)
                     / NULLIF(SUM(i.bobot), 0), 2) AS capaian,
               (SELECT COUNT(*) FROM tb_gerbang_opd go WHERE go.id_gerbang = g.id_gerbang) AS jml_opd,
               (SELECT COUNT(*) FROM tb_usulan u WHERE u.id_gerbang = g.id_gerbang AND u.tahun = ?
                  AND u.status <> 'Ditolak' AND " . syarat_prioritas('u') . ") AS jml_usulan,
               (SELECT MAX(c.diubah_pada) FROM tb_gerbang_capaian c
                 JOIN tb_gerbang_indikator ix ON ix.id_indikator = c.id_indikator
                WHERE ix.id_gerbang = g.id_gerbang AND ix.tahun = ?) AS terakhir
        FROM tb_gerbang g
        LEFT JOIN tb_gerbang_indikator i
               ON i.id_gerbang = g.id_gerbang AND i.tahun = ? AND i.aktif = 1" . $syarat_opd . "
        WHERE g.aktif = 1
        GROUP BY g.id_gerbang
        ORDER BY g.urut, g.kode", 'iii', [$tahun, $tahun, $tahun]);
}

/** Indikator satu program GERBANG beserta realisasi terakhirnya */
function ambil_indikator_gerbang(mysqli $k, int $id_gerbang, int $tahun, int $id_opd = 0): array
{
    $syarat_opd = $id_opd > 0 ? ' AND i.id_opd = ' . (int)$id_opd : '';
    $r = sql_realisasi_terakhir();
    return jalankan($k, "
        SELECT i.*, o.nama AS opd,
               $r AS realisasi,
               (SELECT c2.triwulan FROM tb_gerbang_capaian c2
                 WHERE c2.id_indikator = i.id_indikator ORDER BY c2.triwulan DESC LIMIT 1) AS triwulan,
               (SELECT c3.diubah_pada FROM tb_gerbang_capaian c3
                 WHERE c3.id_indikator = i.id_indikator ORDER BY c3.triwulan DESC LIMIT 1) AS diubah_pada,
               (SELECT c4.catatan FROM tb_gerbang_capaian c4
                 WHERE c4.id_indikator = i.id_indikator ORDER BY c4.triwulan DESC LIMIT 1) AS catatan
        FROM tb_gerbang_indikator i
        LEFT JOIN tb_opd o ON o.id_opd = i.id_opd
        WHERE i.id_gerbang = ? AND i.tahun = ? AND i.aktif = 1" . $syarat_opd . "
        ORDER BY i.id_indikator", 'ii', [$id_gerbang, $tahun]);
}

/** Capaian satu indikator dalam persen */
function capaian_indikator(array $i): ?float
{
    if ($i['realisasi'] === null || (float)$i['target'] <= 0) {
        return null;
    }
    return min(100, (float)$i['realisasi'] / (float)$i['target'] * 100);
}

/** Warna status capaian, memakai palet netral dengan aksen seperlunya */
function warna_capaian(?float $persen): string
{
    if ($persen === null) { return '#A9A49A'; }
    if ($persen >= 90)    { return '#5F7059'; }
    if ($persen >= 60)    { return '#8A7A4E'; }
    return '#9C6B5A';
}

/** Perangkat daerah pelaksana sebuah program GERBANG */
function opd_gerbang(mysqli $k, int $id_gerbang): array
{
    return jalankan($k, "SELECT o.id_opd, o.nama, go.peran
        FROM tb_gerbang_opd go JOIN tb_opd o ON o.id_opd = go.id_opd
        WHERE go.id_gerbang = ? ORDER BY go.peran, o.nama", 'i', [$id_gerbang]);
}
