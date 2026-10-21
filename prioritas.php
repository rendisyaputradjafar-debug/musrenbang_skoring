<?php
/**
 * Prioritas desa: menata 5 usulan prioritas setiap desa.
 * Hanya usulan pada urutan 1 sampai JUMLAH_PRIORITAS_DESA yang dinilai desa lain
 * dan ditampilkan ke publik.
 */
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();
wajib_peran('admin', 'verifikator', 'operator');

$halaman_aktif    = 'prioritas';
$judul_halaman    = 'Prioritas desa';
$halaman_langsung = true;

$tahun = (int)($_REQUEST['tahun'] ?? APP_TAHUN_DEFAULT);
$kec   = kec_user() > 0 ? kec_user() : (int)($_REQUEST['kec'] ?? 0);
$desa  = trim((string)($_REQUEST['desa'] ?? ''));

$ref_kecamatan = ambil_kecamatan_pilihan($koneksi);
$nama_kec = $kec > 0
    ? (jalankan($koneksi, "SELECT nama FROM tb_kecamatan WHERE id_kecamatan = ?", 'i', [$kec])[0]['nama'] ?? '')
    : '';
if ($nama_kec === '') { $kec = 0; $desa = ''; }

$kembali = 'prioritas.php?' . http_build_query(array_filter([
    'tahun' => $tahun, 'kec' => $kec ?: null, 'desa' => $desa ?: null,
]));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksa_csrf();
    $id = (int)($_POST['id_usulan'] ?? 0);
    $b = jalankan($koneksi, "SELECT * FROM tb_usulan WHERE id_usulan = ?", 'i', [$id]);
    if (!$b || !boleh_kecamatan((int)$b[0]['id_kecamatan'])) {
        pesan_set('galat', 'Usulan tidak ditemukan atau di luar kecamatan Anda.');
        header('Location: ' . $kembali);
        exit;
    }
    $u = $b[0];
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'naik' || $aksi === 'turun') {
        if (geser_urutan_prioritas($koneksi, $id, $aksi)) {
            catat_log($koneksi, 'ubah urutan prioritas', 'tb_usulan#' . $id, $aksi . ' di ' . $u['desa']);
            pesan_set('sukses', 'Urutan "' . $u['nama_usulan'] . '" dipindahkan ke ' . $aksi . '.');
        } else {
            pesan_set('peringatan', 'Urutan tidak dapat dipindahkan lagi.');
        }
    }

    if ($aksi === 'keluarkan') {
        /* Dikeluarkan dari daftar prioritas: diletakkan di urutan paling bawah sebagai cadangan */
        $jumlah = jumlah_prioritas_desa($koneksi, (int)$u['tahun'], (int)$u['id_kecamatan'], $u['desa']);
        jalankan($koneksi, "UPDATE tb_usulan SET peringkat_desa = ? WHERE id_usulan = ?", 'ii', [$jumlah + 1, $id]);
        rapikan_urutan_prioritas($koneksi, (int)$u['tahun'], (int)$u['id_kecamatan'], $u['desa']);
        catat_log($koneksi, 'keluarkan dari prioritas', 'tb_usulan#' . $id, $u['nama_usulan']);
        pesan_set('sukses', '"' . $u['nama_usulan'] . '" dipindahkan ke daftar cadangan dan tidak lagi dinilai.');
    }

    if ($aksi === 'masukkan') {
        /* Dijadikan prioritas: hanya bila slot masih tersedia */
        $terisi = (int)jalankan($koneksi, "SELECT COUNT(*) AS n FROM tb_usulan
            WHERE tahun = ? AND id_kecamatan = ? AND desa = ? AND status <> 'Ditolak'
              AND peringkat_desa <= ?", 'iisi',
            [(int)$u['tahun'], (int)$u['id_kecamatan'], $u['desa'], JUMLAH_PRIORITAS_DESA])[0]['n'];
        if ($terisi >= JUMLAH_PRIORITAS_DESA) {
            pesan_set('peringatan', 'Slot prioritas ' . $u['desa'] . ' sudah penuh. Keluarkan salah satu lebih dulu.');
        } else {
            jalankan($koneksi, "UPDATE tb_usulan SET peringkat_desa = ? WHERE id_usulan = ?", 'ii', [$terisi + 1, $id]);
            rapikan_urutan_prioritas($koneksi, (int)$u['tahun'], (int)$u['id_kecamatan'], $u['desa']);
            catat_log($koneksi, 'masukkan ke prioritas', 'tb_usulan#' . $id, $u['nama_usulan']);
            pesan_set('sukses', '"' . $u['nama_usulan'] . '" masuk daftar prioritas dan mulai dinilai desa lain.');
        }
    }

    header('Location: ' . $kembali);
    exit;
}

$daftar_tahun = array_map('intval', array_column(
    jalankan($koneksi, "SELECT DISTINCT tahun FROM tb_usulan ORDER BY tahun DESC"), 'tahun'));
if (!in_array($tahun, $daftar_tahun, true)) { $daftar_tahun[] = $tahun; rsort($daftar_tahun); }

/* Data desa pada kecamatan terpilih */
$desa_kec = [];
$jumlah_desa_kec = 0;
if ($kec > 0) {
    $desa_kec = jalankan($koneksi, "
        SELECT d.nama,
               (SELECT COUNT(*) FROM tb_usulan u WHERE u.id_kecamatan = d.id_kecamatan AND u.desa = d.nama
                  AND u.tahun = ? AND u.status <> 'Ditolak' AND u.peringkat_desa <= ?) AS terisi,
               (SELECT COUNT(*) FROM tb_usulan u WHERE u.id_kecamatan = d.id_kecamatan AND u.desa = d.nama
                  AND u.tahun = ? AND u.status <> 'Ditolak' AND u.peringkat_desa > ?) AS cadangan,
               (SELECT COALESCE(SUM(u.jumlah_penilai), 0) FROM tb_usulan u
                 WHERE u.id_kecamatan = d.id_kecamatan AND u.desa = d.nama AND u.tahun = ?
                   AND u.status <> 'Ditolak' AND u.peringkat_desa <= ?) AS nilai_masuk
        FROM tb_desa d WHERE d.id_kecamatan = ? AND d.aktif = 1 ORDER BY d.nama",
        'iiiiiii', [$tahun, JUMLAH_PRIORITAS_DESA, $tahun, JUMLAH_PRIORITAS_DESA, $tahun, JUMLAH_PRIORITAS_DESA, $kec]);
    $jumlah_desa_kec = count($desa_kec);
}
$target_penilai = max(0, $jumlah_desa_kec - 1);   /* 10 desa -> dinilai 9 desa lain */

$usulan_desa = [];
if ($kec > 0 && $desa !== '') {
    $usulan_desa = jalankan($koneksi, SQL_USULAN_DASAR . "
        WHERE u.tahun = ? AND u.id_kecamatan = ? AND u.desa = ? AND u.status <> 'Ditolak'
        ORDER BY (u.peringkat_desa IS NULL), u.peringkat_desa", 'iis', [$tahun, $kec, $desa]);
}
$prioritas = array_values(array_filter($usulan_desa, function ($u) {
    return (int)$u['peringkat_desa'] > 0 && (int)$u['peringkat_desa'] <= JUMLAH_PRIORITAS_DESA;
}));
$cadangan = array_values(array_filter($usulan_desa, function ($u) {
    return (int)$u['peringkat_desa'] > JUMLAH_PRIORITAS_DESA;
}));

require_once __DIR__ . '/includes/header.php';

function tombol_urut(int $id, string $aksi, string $label, bool $nonaktif): string
{
    return '<form method="post" class="inline">'
        . '<input type="hidden" name="csrf" value="' . token_csrf() . '">'
        . '<input type="hidden" name="aksi" value="' . $aksi . '">'
        . '<input type="hidden" name="id_usulan" value="' . $id . '">'
        . '<button class="tombol-ikon" title="' . $label . '" aria-label="' . $label . '"' . ($nonaktif ? ' disabled style="opacity:.35"' : '') . '>'
        . ($aksi === 'naik'
            ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"/></svg>'
            : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 5v14M6 13l6 6 6-6"/></svg>')
        . '</button></form>';
}
?>

<form method="get" class="flex flex-wrap items-end gap-3 mb-5 cetak-sembunyi">
  <div>
    <label class="block text-xs text-slate-600 mb-1" for="p_tahun">Tahun</label>
    <select id="p_tahun" name="tahun" class="isian" onchange="this.form.submit()">
      <?php foreach ($daftar_tahun as $t): ?>
        <option value="<?= $t ?>" <?= $t === $tahun ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if (kec_user() === 0): ?>
    <div>
      <label class="block text-xs text-slate-600 mb-1" for="p_kec">Kecamatan</label>
      <select id="p_kec" name="kec" class="isian" style="min-width:200px" onchange="this.form.submit()">
        <option value="0">Pilih kecamatan</option>
        <?php foreach ($ref_kecamatan as $r): ?>
          <option value="<?= $r['id_kecamatan'] ?>" <?= $kec === (int)$r['id_kecamatan'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <?php if ($desa !== ''): ?><input type="hidden" name="desa" value="<?= e($desa) ?>"><?php endif; ?>
  <noscript><button class="tombol">Terapkan</button></noscript>
</form>

<div id="langsung-prioritas" data-langsung>

<?php if ($kec === 0): ?>
  <div class="kartu p-10 text-center">
    <p class="font-semibold">Pilih kecamatan terlebih dahulu.</p>
    <p class="text-sm text-slate-500 mt-1">Setiap desa memiliki <?= JUMLAH_PRIORITAS_DESA ?> usulan prioritas yang akan dinilai desa lain.</p>
  </div>

<?php elseif ($desa === ''): ?>
  <div class="kartu p-5 mb-5">
    <p class="font-bold text-lg">Kecamatan <?= e($nama_kec) ?></p>
    <p class="text-sm text-slate-600 mt-1">
      <?= $jumlah_desa_kec ?> desa/kelurahan. Setiap desa mengisi <?= JUMLAH_PRIORITAS_DESA ?> usulan prioritas,
      lalu dinilai oleh <?= $target_penilai ?> desa lainnya.
    </p>
  </div>

  <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 min-[1700px]:grid-cols-4">
    <?php foreach ($desa_kec as $d):
        $terisi = (int)$d['terisi'];
        $persen = $terisi / JUMLAH_PRIORITAS_DESA * 100;
        $target_nilai = $terisi * $target_penilai;
        $persen_nilai = $target_nilai > 0 ? min(100, round((int)$d['nilai_masuk'] / $target_nilai * 100)) : 0; ?>
      <li class="kartu p-4">
        <a href="?tahun=<?= $tahun ?>&amp;kec=<?= $kec ?>&amp;desa=<?= urlencode($d['nama']) ?>" class="block">
          <div class="flex items-start justify-between gap-2">
            <b class="text-base"><?= e($d['nama']) ?></b>
            <span class="text-xs <?= $terisi >= JUMLAH_PRIORITAS_DESA ? 'text-stone-700' : 'text-amber-700' ?> font-semibold whitespace-nowrap">
              <?= $terisi ?>/<?= JUMLAH_PRIORITAS_DESA ?> terisi
            </span>
          </div>
          <div class="flex gap-1 mt-3" aria-hidden="true">
            <?php for ($i = 1; $i <= JUMLAH_PRIORITAS_DESA; $i++): ?>
              <span class="flex-1 h-2 rounded-full" style="background:<?= $i <= $terisi ? '#44403B' : '#E7E5DF' ?>"></span>
            <?php endfor; ?>
          </div>
          <p class="text-xs text-slate-500 mt-2">
            Penilaian masuk <?= (int)$d['nilai_masuk'] ?> dari <?= $target_nilai ?> (<?= $persen_nilai ?>%)
            <?= (int)$d['cadangan'] ? ', ' . (int)$d['cadangan'] . ' cadangan' : '' ?>
          </p>
        </a>
      </li>
    <?php endforeach; ?>
    <?php if (!$desa_kec): ?>
      <li class="kartu p-8 text-center sm:col-span-2 xl:col-span-3">Data desa kecamatan ini belum diisi di menu Data master.</li>
    <?php endif; ?>
  </ul>

<?php else: ?>
  <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
    <div>
      <a href="?tahun=<?= $tahun ?>&amp;kec=<?= $kec ?>" class="text-sm font-semibold" style="color:#44403B">&larr; Semua desa</a>
      <h2 class="text-2xl font-extrabold mt-1"><?= e($desa) ?></h2>
      <p class="text-sm text-slate-600">Kecamatan <?= e($nama_kec) ?>, tahun <?= $tahun ?>. Dinilai oleh <?= $target_penilai ?> desa lain.</p>
    </div>
    <?php if (count($prioritas) < JUMLAH_PRIORITAS_DESA): ?>
      <a href="usulan_form.php?desa=<?= urlencode($desa) ?>&amp;tahun=<?= $tahun ?>" class="tombol">Tambah usulan prioritas</a>
    <?php endif; ?>
  </div>

  <ol class="space-y-3 mb-8">
    <?php for ($slot = 1; $slot <= JUMLAH_PRIORITAS_DESA; $slot++):
        $u = $prioritas[$slot - 1] ?? null; ?>
      <li class="kartu p-4 <?= $u ? '' : 'border-dashed' ?>">
        <?php if ($u):
            $jp = (int)$u['jumlah_penilai'];
            $persen = $target_penilai > 0 ? min(100, round($jp / $target_penilai * 100)) : 0; ?>
          <div class="flex flex-wrap items-start gap-4">
            <span class="w-10 h-10 rounded-xl grid place-items-center font-extrabold shrink-0" style="background:#EDEBE4;color:#211F1C"><?= $slot ?></span>
            <div class="min-w-0 flex-1">
              <a href="usulan_detail.php?id=<?= (int)$u['id_usulan'] ?>" class="font-semibold text-slate-900 hover:underline"><?= e($u['nama_usulan']) ?></a>
              <p class="text-sm text-slate-600"><?= e($u['uraian_masalah']) ?></p>
              <p class="text-xs text-slate-500 mt-0.5">
                <?= e($u['sektor']) ?>, <?= angka($u['volume'], 0) ?> <?= e($u['satuan']) ?>, <?= rupiah($u['estimasi_anggaran']) ?>
              </p>
              <div class="flex items-center gap-2 mt-2" style="max-width:320px">
                <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                  <div class="h-full rounded-full" style="width:<?= $persen ?>%;background:#44403B"></div>
                </div>
                <span class="text-xs text-slate-600 whitespace-nowrap"><?= $jp ?>/<?= $target_penilai ?> desa menilai</span>
              </div>
            </div>
            <div class="text-right shrink-0">
              <p class="text-2xl font-extrabold"><?= $u['total_nilai'] !== null ? angka($u['total_nilai']) : '-' ?></p>
              <p class="text-xs text-slate-500">nilai saat ini</p>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <?= tombol_urut((int)$u['id_usulan'], 'naik', 'Naikkan urutan', $slot === 1) ?>
              <?= tombol_urut((int)$u['id_usulan'], 'turun', 'Turunkan urutan', $slot >= count($prioritas)) ?>
              <a href="usulan_form.php?id=<?= (int)$u['id_usulan'] ?>" class="tombol-2 text-xs">Ubah</a>
              <form method="post" class="inline" onsubmit="return confirm('Keluarkan usulan ini dari daftar prioritas? Usulan tetap tersimpan sebagai cadangan dan berhenti dinilai.')">
                <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
                <input type="hidden" name="aksi" value="keluarkan">
                <input type="hidden" name="id_usulan" value="<?= (int)$u['id_usulan'] ?>">
                <button class="tombol-2 text-xs text-rose-600">Keluarkan</button>
              </form>
            </div>
          </div>
        <?php else: ?>
          <div class="flex items-center gap-4">
            <span class="w-10 h-10 rounded-xl grid place-items-center font-extrabold shrink-0 bg-slate-100 text-slate-400"><?= $slot ?></span>
            <p class="text-slate-500 flex-1">Slot prioritas <?= $slot ?> masih kosong.</p>
            <a href="usulan_form.php?desa=<?= urlencode($desa) ?>&amp;tahun=<?= $tahun ?>" class="tombol-2">Isi slot ini</a>
          </div>
        <?php endif; ?>
      </li>
    <?php endfor; ?>
  </ol>

  <?php if ($cadangan): ?>
    <h3 class="font-bold mb-2">Cadangan (tidak dinilai)</h3>
    <p class="text-sm text-slate-600 mb-3">Usulan ini tersimpan, tetapi tidak dinilai desa lain dan tidak tampil di halaman publik.</p>
    <ul class="kartu divide-y divide-slate-100">
      <?php foreach ($cadangan as $u): ?>
        <li class="p-4 flex flex-wrap items-center gap-3">
          <div class="min-w-0 flex-1">
            <a href="usulan_detail.php?id=<?= (int)$u['id_usulan'] ?>" class="font-medium hover:underline"><?= e($u['nama_usulan']) ?></a>
            <p class="text-xs text-slate-500"><?= e($u['sektor']) ?>, <?= rupiah($u['estimasi_anggaran']) ?></p>
          </div>
          <?php if (count($prioritas) < JUMLAH_PRIORITAS_DESA): ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
              <input type="hidden" name="aksi" value="masukkan">
              <input type="hidden" name="id_usulan" value="<?= (int)$u['id_usulan'] ?>">
              <button class="tombol-2 text-xs">Jadikan prioritas</button>
            </form>
          <?php else: ?>
            <span class="text-xs text-slate-500">Slot prioritas penuh</span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
