<?php
/**
 * Penilaian antar-desa.
 * - Akun desa   : daftar usulan desa lain di kecamatannya untuk dinilai.
 * - Akun lain   : pemantauan progres penilaian per kecamatan dan per desa.
 * Usulan terbuka untuk dinilai selama berstatus Draft.
 */
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();
if (peran() === 'opd') {
    header('Location: gerbang.php');
    exit;
}

$halaman_aktif    = 'penilaian';
$judul_halaman    = peran() === 'desa' ? 'Nilai usulan desa tetangga' : 'Pantau penilaian';
$halaman_langsung = true;

$tahun = (int)($_GET['tahun'] ?? APP_TAHUN_DEFAULT);
$daftar_tahun = array_map('intval', array_column(
    jalankan($koneksi, "SELECT DISTINCT tahun FROM tb_usulan ORDER BY tahun DESC"), 'tahun'));
if (!in_array($tahun, $daftar_tahun, true)) { $daftar_tahun[] = $tahun; rsort($daftar_tahun); }

function batang_progres(int $sudah, int $target): string
{
    $persen = $target > 0 ? min(100, round($sudah / $target * 100)) : 0;
    $warna  = $target > 0 && $sudah >= $target ? '#6B7D65' : '#44403B';
    return '<div class="flex items-center gap-2" style="min-width:140px">'
         . '<div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full" style="width:'
         . $persen . '%;background:' . $warna . '"></div></div>'
         . '<span class="text-xs text-slate-600 whitespace-nowrap">' . $sudah . '/' . $target . '</span></div>';
}

$mode_desa = peran() === 'desa' && desa_user() > 0;

if ($mode_desa) {
    /* ------------------------- Mode penilai (akun desa) ------------------------- */
    $id_desa   = desa_user();
    $nama_desa = nama_desa_user();
    $kec       = kec_user();
    $tab       = in_array($_GET['tab'] ?? '', ['belum', 'sudah', 'semua'], true) ? $_GET['tab'] : 'belum';

    $jumlah_desa_kec = (int)jalankan($koneksi,
        "SELECT COUNT(*) AS n FROM tb_desa WHERE id_kecamatan = ? AND aktif = 1", 'i', [$kec])[0]['n'];
    $nama_desa_kec = array_column(jalankan($koneksi,
        "SELECT nama FROM tb_desa WHERE id_kecamatan = ? AND aktif = 1", 'i', [$kec]), 'nama');

    $semua = jalankan($koneksi, "
        SELECT u.id_usulan, u.kode_usulan, u.nama_usulan, u.uraian_masalah, u.desa, u.status,
               u.estimasi_anggaran, u.volume, u.satuan, u.jumlah_penilai, u.total_nilai,
               s.nama AS sektor, s.warna AS warna_sektor,
               p.total_nilai AS nilai_saya, p.diubah_pada AS waktu_saya
        FROM tb_usulan u
        JOIN tb_sektor s ON s.id_sektor = u.id_sektor
        LEFT JOIN tb_penilaian p ON p.id_usulan = u.id_usulan AND p.id_desa_penilai = ?
        WHERE u.tahun = ? AND u.id_kecamatan = ? AND u.desa <> ? AND u.status <> 'Ditolak'
          AND " . syarat_prioritas('u') . "
        ORDER BY (u.status = 'Draft') DESC, (p.id_penilaian IS NULL) DESC, u.dibuat_pada, u.id_usulan",
        'iiis', [$id_desa, $tahun, $kec, $nama_desa]);

    $terbuka = array_values(array_filter($semua, function ($r) { return $r['status'] === 'Draft'; }));
    $belum   = array_values(array_filter($terbuka, function ($r) { return $r['nilai_saya'] === null; }));
    $sudah   = array_values(array_filter($semua, function ($r) { return $r['nilai_saya'] !== null; }));
    $tampil  = $tab === 'belum' ? $belum : ($tab === 'sudah' ? $sudah : $semua);

    $jumlah_terbuka = count($terbuka);
    $sudah_terbuka  = $jumlah_terbuka - count($belum);

    $usulan_sendiri = (int)jalankan($koneksi, "SELECT COUNT(*) AS n FROM tb_usulan u
        WHERE tahun = ? AND id_kecamatan = ? AND desa = ? AND status <> 'Ditolak' AND " . syarat_prioritas('u'),
        'iis', [$tahun, $kec, $nama_desa])[0]['n'];

} else {
    /* ------------------------- Mode pemantauan ------------------------- */
    $kec = kec_user() > 0 ? kec_user() : (int)($_GET['kec'] ?? 0);
    $ref_kecamatan = ambil_kecamatan_pilihan($koneksi);

    if ($kec > 0) {
        $nama_kec = jalankan($koneksi, "SELECT nama FROM tb_kecamatan WHERE id_kecamatan = ?", 'i', [$kec])[0]['nama'] ?? '';

        $per_desa = jalankan($koneksi, "
            SELECT d.id_desa, d.nama, d.jenis,
                   (SELECT COUNT(*) FROM tb_usulan u
                     WHERE u.id_kecamatan = d.id_kecamatan AND u.tahun = ? AND u.status = 'Draft'
                       AND u.desa <> d.nama AND " . syarat_prioritas('u') . ") AS target,
                   (SELECT COUNT(*) FROM tb_penilaian p JOIN tb_usulan u ON u.id_usulan = p.id_usulan
                     WHERE p.id_desa_penilai = d.id_desa AND u.tahun = ? AND u.status = 'Draft' AND " . syarat_prioritas('u') . ") AS sudah,
                   (SELECT MAX(p.diubah_pada) FROM tb_penilaian p JOIN tb_usulan u ON u.id_usulan = p.id_usulan
                     WHERE p.id_desa_penilai = d.id_desa AND u.tahun = ?) AS terakhir,
                   (SELECT COUNT(*) FROM tb_usulan u
                     WHERE u.id_kecamatan = d.id_kecamatan AND u.tahun = ? AND u.desa = d.nama
                       AND u.status <> 'Ditolak') AS usulan_sendiri,
                   (SELECT COUNT(*) FROM tb_user x WHERE x.id_desa = d.id_desa AND x.aktif = 1) AS akun
            FROM tb_desa d
            WHERE d.id_kecamatan = ? AND d.aktif = 1
            ORDER BY d.nama", 'iiiii', [$tahun, $tahun, $tahun, $tahun, $kec]);

        $jumlah_desa_kec = count($per_desa);
        $nama_desa_kec   = array_column($per_desa, 'nama');

        $per_usulan = jalankan($koneksi, SQL_USULAN_DASAR . "
            WHERE u.tahun = ? AND u.id_kecamatan = ? AND u.status <> 'Ditolak' AND " . syarat_prioritas('u') . "
            ORDER BY u.desa, u.peringkat_desa", 'ii', [$tahun, $kec]);

        $total_target = array_sum(array_column($per_desa, 'target'));
        $total_sudah  = array_sum(array_column($per_desa, 'sudah'));
        $desa_selesai = count(array_filter($per_desa, function ($d) {
            return (int)$d['target'] > 0 && (int)$d['sudah'] >= (int)$d['target'];
        }));
        $desa_belum_mulai = count(array_filter($per_desa, function ($d) {
            return (int)$d['target'] > 0 && (int)$d['sudah'] === 0;
        }));
    } else {
        $per_kec = jalankan($koneksi, "
            SELECT k.id_kecamatan, k.nama,
                   (SELECT COUNT(*) FROM tb_desa d WHERE d.id_kecamatan = k.id_kecamatan AND d.aktif = 1) AS jml_desa,
                   (SELECT COUNT(*) FROM tb_usulan u
                     WHERE u.id_kecamatan = k.id_kecamatan AND u.tahun = ? AND u.status = 'Draft' AND " . syarat_prioritas('u') . ") AS terbuka,
                   (SELECT COUNT(*) FROM tb_penilaian p JOIN tb_usulan u ON u.id_usulan = p.id_usulan
                     WHERE u.id_kecamatan = k.id_kecamatan AND u.tahun = ? AND u.status = 'Draft' AND " . syarat_prioritas('u') . ") AS masuk,
                   (SELECT COUNT(*) FROM tb_usulan u JOIN tb_desa d
                        ON d.id_kecamatan = u.id_kecamatan AND d.aktif = 1 AND d.nama <> u.desa
                     WHERE u.id_kecamatan = k.id_kecamatan AND u.tahun = ? AND u.status = 'Draft' AND " . syarat_prioritas('u') . ") AS target,
                   (SELECT COUNT(DISTINCT p.id_desa_penilai) FROM tb_penilaian p JOIN tb_usulan u ON u.id_usulan = p.id_usulan
                     WHERE u.id_kecamatan = k.id_kecamatan AND u.tahun = ?) AS desa_aktif
            FROM tb_kecamatan k WHERE k.aktif = 1 ORDER BY k.nama", 'iiii', [$tahun, $tahun, $tahun, $tahun]);
    }
}

function target_usulan(int $jumlah_desa, array $nama_desa, string $desa_pengusul): int
{
    return max(0, $jumlah_desa - (in_array($desa_pengusul, $nama_desa, true) ? 1 : 0));
}

require_once __DIR__ . '/includes/header.php';
?>

<form method="get" class="flex flex-wrap items-end gap-3 mb-5 cetak-sembunyi">
  <div>
    <label class="block text-xs text-slate-600 mb-1">Tahun rencana</label>
    <select name="tahun" class="isian" onchange="this.form.submit()">
      <?php foreach ($daftar_tahun as $t): ?>
        <option value="<?= $t ?>" <?= $t === $tahun ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($mode_desa): ?>
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <?php elseif (kec_user() === 0): ?>
    <div>
      <label class="block text-xs text-slate-600 mb-1">Kecamatan</label>
      <select name="kec" class="isian" style="min-width:200px" onchange="this.form.submit()">
        <option value="0">Rekap semua kecamatan</option>
        <?php foreach ($ref_kecamatan as $r): ?>
          <option value="<?= $r['id_kecamatan'] ?>" <?= $kec === (int)$r['id_kecamatan'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <noscript><button class="tombol">Terapkan</button></noscript>
</form>

<div id="langsung-penilaian" data-langsung>

<?php if ($mode_desa): ?>

  <div class="kartu p-5 mb-5">
    <div class="flex flex-wrap items-start justify-between gap-6">
      <div class="max-w-xl">
        <p class="text-xs text-slate-500">Penilai</p>
        <p class="text-lg font-bold text-slate-900"><?= e($nama_desa) ?>, Kecamatan <?= e($_SESSION['nama_kecamatan'] ?? '') ?></p>
        <p class="text-sm text-slate-600 mt-1 leading-relaxed">
          Setiap desa punya <?= JUMLAH_PRIORITAS_DESA ?> usulan prioritas yang dicatat Kecamatan <?= e($_SESSION['nama_kecamatan'] ?? '') ?>.
          Tugas Anda menilai usulan prioritas desa lain secara jujur sesuai kondisi lapangan.
          Usulan desa Anda sendiri (<?= $usulan_sendiri ?> dari <?= JUMLAH_PRIORITAS_DESA ?>) dinilai oleh desa lain.
        </p>
      </div>
      <div style="min-width:260px">
        <p class="text-xs text-slate-500 mb-1">Progres penilaian Anda, tahun <?= $tahun ?></p>
        <p class="text-3xl font-bold text-slate-900"><?= $sudah_terbuka ?><span class="text-base font-medium text-slate-500"> dari <?= $jumlah_terbuka ?> usulan</span></p>
        <div class="mt-2"><?= batang_progres($sudah_terbuka, $jumlah_terbuka) ?></div>
        <?php if ($belum): ?>
          <a href="penilaian_form.php?id=<?= (int)$belum[0]['id_usulan'] ?>" class="tombol inline-block mt-3">Nilai usulan berikutnya</a>
        <?php elseif ($jumlah_terbuka > 0): ?>
          <p class="text-sm text-stone-700 font-medium mt-3">Semua usulan terbuka sudah Anda nilai. Terima kasih.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="flex flex-wrap gap-2 mb-3">
    <?php foreach (['belum' => ['Belum saya nilai', count($belum)], 'sudah' => ['Sudah saya nilai', count($sudah)], 'semua' => ['Semua usulan', count($semua)]] as $k => $v): ?>
      <a href="?tahun=<?= $tahun ?>&amp;tab=<?= $k ?>" class="<?= $tab === $k ? 'tombol' : 'tombol-2' ?>"><?= $v[0] ?> (<?= $v[1] ?>)</a>
    <?php endforeach; ?>
  </div>

  <div class="kartu overflow-hidden">
    <div class="overflow-x-auto">
      <table class="data w-full">
        <thead><tr>
          <th>Usulan prioritas</th><th>Desa pengusul</th><th>Sektor</th><th class="text-right">Estimasi anggaran</th>
          <th>Desa sudah menilai</th><th class="text-right">Nilai dari Anda</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($tampil as $r):
            $target = target_usulan($jumlah_desa_kec, $nama_desa_kec, $r['desa']);
            $terbuka_r = $r['status'] === 'Draft'; ?>
          <tr>
            <td>
              <a href="usulan_detail.php?id=<?= (int)$r['id_usulan'] ?>" class="font-medium text-slate-900 hover:underline"><?= e($r['nama_usulan']) ?></a>
              <div class="text-xs text-slate-500"><?= e($r['kode_usulan']) ?>, <?= angka($r['volume'], 0) ?> <?= e($r['satuan']) ?></div>
            </td>
            <td><?= e($r['desa']) ?></td>
            <td><span class="inline-flex items-center gap-1.5 text-sm"><span class="w-2 h-2 rounded-sm" style="background:<?= e($r['warna_sektor']) ?>"></span><?= e($r['sektor']) ?></span></td>
            <td class="text-right whitespace-nowrap"><?= rupiah($r['estimasi_anggaran']) ?></td>
            <td><?= batang_progres((int)$r['jumlah_penilai'], $target) ?></td>
            <td class="text-right">
              <?php if ($r['nilai_saya'] !== null): ?>
                <span class="font-semibold"><?= angka($r['nilai_saya']) ?></span>
                <div class="text-xs text-slate-500"><?= e(waktu_lalu($r['waktu_saya'])) ?></div>
              <?php else: ?>
                <span class="text-slate-400">-</span>
              <?php endif; ?>
            </td>
            <td class="text-right whitespace-nowrap">
              <?php if (!$terbuka_r): ?>
                <span class="text-xs text-slate-500">Ditutup (<?= e($r['status']) ?>)</span>
              <?php elseif ($r['nilai_saya'] === null): ?>
                <a href="penilaian_form.php?id=<?= (int)$r['id_usulan'] ?>" class="tombol">Nilai</a>
              <?php else: ?>
                <a href="penilaian_form.php?id=<?= (int)$r['id_usulan'] ?>" class="tombol-2">Ubah nilai</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$tampil): ?>
          <tr><td colspan="7" class="text-center text-slate-500 py-10">
            <?= $tab === 'belum'
                ? ($jumlah_terbuka ? 'Semua usulan terbuka sudah Anda nilai.' : 'Kecamatan belum menginput usulan desa lain untuk tahun ' . $tahun . '. Halaman ini akan terisi sendiri begitu usulan masuk.')
                : 'Tidak ada usulan pada tab ini.' ?>
          </td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-xs text-slate-500 mt-3">
    Nilai yang Anda berikan tidak ditampilkan kepada desa lain maupun publik. Publik hanya melihat nilai rata-rata
    dan jumlah desa yang sudah menilai. Penilaian dapat diubah selama usulan belum diverifikasi.
  </p>

<?php elseif ($kec > 0): ?>

  <div class="grid gap-4 md:grid-cols-4 mb-5">
    <div class="kartu p-4">
      <p class="text-xs text-slate-500">Penilaian masuk</p>
      <p class="text-2xl font-bold"><?= $total_sudah ?><span class="text-sm font-medium text-slate-500"> / <?= $total_target ?></span></p>
      <div class="mt-2"><?= batang_progres($total_sudah, $total_target) ?></div>
    </div>
    <div class="kartu p-4">
      <p class="text-xs text-slate-500">Desa/kelurahan</p>
      <p class="text-2xl font-bold"><?= $jumlah_desa_kec ?></p>
      <p class="text-xs text-slate-500 mt-1">di Kecamatan <?= e($nama_kec) ?></p>
    </div>
    <div class="kartu p-4">
      <p class="text-xs text-slate-500">Desa selesai menilai</p>
      <p class="text-2xl font-bold text-stone-700"><?= $desa_selesai ?></p>
      <p class="text-xs text-slate-500 mt-1">sudah menilai semua usulan terbuka</p>
    </div>
    <div class="kartu p-4">
      <p class="text-xs text-slate-500">Desa belum mulai</p>
      <p class="text-2xl font-bold <?= $desa_belum_mulai ? 'text-amber-700' : '' ?>"><?= $desa_belum_mulai ?></p>
      <p class="text-xs text-slate-500 mt-1">perlu diingatkan</p>
    </div>
  </div>

  <div class="grid gap-5 xl:grid-cols-5">
    <div class="kartu overflow-hidden xl:col-span-2 h-fit">
      <div class="px-4 py-3 border-b border-slate-200">
        <p class="text-sm font-semibold text-slate-800">Progres setiap desa sebagai penilai</p>
        <p class="text-xs text-slate-500">Setiap desa menilai <?= JUMLAH_PRIORITAS_DESA ?> usulan milik <?= max(0, $jumlah_desa_kec - 1) ?> desa lain.</p>
      </div>
      <div class="overflow-x-auto max-h-[640px] overflow-y-auto">
        <table class="data w-full">
          <thead><tr><th>Desa</th><th>Sudah menilai</th><th>Terakhir</th></tr></thead>
          <tbody>
          <?php foreach ($per_desa as $d): ?>
            <tr>
              <td>
                <span class="font-medium"><?= e($d['nama']) ?></span>
                <?php if (!(int)$d['akun']): ?>
                  <div class="text-xs text-rose-600">Belum punya akun</div>
                <?php else: ?>
                  <div class="text-xs text-slate-500"><?= (int)$d['usulan_sendiri'] ?> usulan sendiri</div>
                <?php endif; ?>
              </td>
              <td><?= batang_progres((int)$d['sudah'], (int)$d['target']) ?></td>
              <td class="text-xs text-slate-500 whitespace-nowrap"><?= $d['terakhir'] ? e(waktu_lalu($d['terakhir'])) : 'Belum pernah' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$per_desa): ?>
            <tr><td colspan="3" class="text-center text-slate-500 py-8">Belum ada data desa untuk kecamatan ini. Isi di menu Master.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="kartu overflow-hidden xl:col-span-3 h-fit">
      <div class="px-4 py-3 border-b border-slate-200">
        <p class="text-sm font-semibold text-slate-800">Progres setiap usulan</p>
        <p class="text-xs text-slate-500">Usulan dengan penilai paling sedikit ditampilkan lebih dulu.</p>
      </div>
      <div class="overflow-x-auto max-h-[640px] overflow-y-auto">
        <table class="data w-full">
          <thead><tr><th>Usulan</th><th>Desa sudah menilai</th><th class="text-right">Nilai rata-rata</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($per_usulan as $u): ?>
            <tr>
              <td>
                <a href="usulan_detail.php?id=<?= (int)$u['id_usulan'] ?>" class="font-medium text-slate-900 hover:underline"><?= e($u['nama_usulan']) ?></a>
                <div class="text-xs text-slate-500"><?= e($u['desa']) ?>, <?= e($u['sektor']) ?></div>
              </td>
              <td><?= batang_progres((int)$u['jumlah_penilai'], target_usulan($jumlah_desa_kec, $nama_desa_kec, $u['desa'])) ?></td>
              <td class="text-right">
                <?php if ($u['total_nilai'] !== null): ?>
                  <span class="font-semibold"><?= angka($u['total_nilai']) ?></span>
                  <div class="text-xs text-slate-500"><?= (int)$u['jumlah_penilai'] > 0 ? e($u['kategori'] ?? '') : 'nilai awal' ?></div>
                <?php else: ?>
                  <span class="text-slate-400">Menunggu</span>
                <?php endif; ?>
              </td>
              <td class="text-xs"><?= $u['status'] === 'Draft' ? 'Terbuka' : e($u['status']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$per_usulan): ?>
            <tr><td colspan="4" class="text-center text-slate-500 py-8">Belum ada usulan pada tahun <?= $tahun ?>.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<?php else: ?>

  <div class="kartu overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-200">
      <p class="text-sm font-semibold text-slate-800">Rekap penilaian antar-desa per kecamatan, tahun <?= $tahun ?></p>
      <p class="text-xs text-slate-500">Target = jumlah usulan terbuka dikali jumlah desa lain di kecamatan tersebut.</p>
    </div>
    <div class="overflow-x-auto">
      <table class="data w-full">
        <thead><tr><th>Kecamatan</th><th class="text-right">Desa</th><th class="text-right">Usulan terbuka</th>
          <th class="text-right">Desa aktif menilai</th><th>Penilaian masuk</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($per_kec as $k): ?>
          <tr>
            <td class="font-medium"><?= e($k['nama']) ?></td>
            <td class="text-right"><?= (int)$k['jml_desa'] ?></td>
            <td class="text-right"><?= (int)$k['terbuka'] ?></td>
            <td class="text-right"><?= (int)$k['desa_aktif'] ?></td>
            <td><?= batang_progres((int)$k['masuk'], (int)$k['target']) ?></td>
            <td class="text-right"><a href="?tahun=<?= $tahun ?>&amp;kec=<?= (int)$k['id_kecamatan'] ?>" class="text-sm" style="color:#44403B">Rincian</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
