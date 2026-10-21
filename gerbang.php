<?php
/**
 * Evaluasi 9 Program Unggulan (GERBANG) RPJMD 2025-2029.
 * Perangkat daerah mengisi realisasi indikatornya, capaian terhitung otomatis dan
 * tampil secara langsung di sini maupun di halaman publik.
 */
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();

$halaman_aktif    = 'gerbang';
$judul_halaman    = 'Evaluasi GERBANG';
$halaman_langsung = true;

$tahun = (int)($_GET['tahun'] ?? (int)date('Y'));
$id_g  = (int)($_GET['id'] ?? 0);
$saya  = opd_user();                      /* akun perangkat daerah */
$kelola = boleh('admin');                 /* admin boleh menata indikator */

$daftar_tahun = array_map('intval', array_column(
    jalankan($koneksi, "SELECT DISTINCT tahun FROM tb_gerbang_indikator ORDER BY tahun DESC"), 'tahun'));
foreach ([(int)date('Y'), APP_TAHUN_DEFAULT] as $t) {
    if (!in_array($t, $daftar_tahun, true)) { $daftar_tahun[] = $t; }
}
rsort($daftar_tahun);

$gerbang = ambil_gerbang($koneksi, $tahun);
$terpilih = null;
foreach ($gerbang as $g) {
    if ((int)$g['id_gerbang'] === $id_g) { $terpilih = $g; }
}

$rata_kabupaten = null;
$nilai_semua = array_filter(array_column($gerbang, 'capaian'), function ($v) { return $v !== null; });
if ($nilai_semua) { $rata_kabupaten = array_sum($nilai_semua) / count($nilai_semua); }

if ($terpilih) {
    $indikator = ambil_indikator_gerbang($koneksi, $id_g, $tahun);
    $opd_pelaksana = opd_gerbang($koneksi, $id_g);
    $usulan_terkait = jalankan($koneksi, SQL_USULAN_DASAR . "
        WHERE u.id_gerbang = ? AND u.tahun = ? AND u.status <> 'Ditolak' AND " . syarat_prioritas('u') . "
        ORDER BY (u.total_nilai IS NULL), u.total_nilai DESC LIMIT 8", 'ii', [$id_g, $tahun]);
    $ref_opd = ambil_daftar($koneksi, 'tb_opd', 'id_opd');
}

require_once __DIR__ . '/includes/header.php';
?>

<form method="get" class="flex flex-wrap items-end gap-3 mb-5 cetak-sembunyi">
  <?php if ($id_g): ?><input type="hidden" name="id" value="<?= $id_g ?>"><?php endif; ?>
  <div>
    <label class="block text-xs text-slate-600 mb-1" for="g_tahun">Tahun evaluasi</label>
    <select id="g_tahun" name="tahun" class="isian" onchange="this.form.submit()">
      <?php foreach ($daftar_tahun as $t): ?>
        <option value="<?= $t ?>" <?= $t === $tahun ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($saya): ?>
    <p class="text-sm text-slate-600 pb-2">Anda mengisi realisasi untuk <b><?= e(nama_opd_user()) ?></b>.</p>
  <?php endif; ?>
  <noscript><button class="tombol">Tampilkan</button></noscript>
</form>

<div id="langsung-gerbang" data-langsung>

<?php if (!$terpilih): ?>

  <div class="kartu p-5 mb-5">
    <div class="flex flex-wrap items-center justify-between gap-5">
      <div>
        <p class="font-bold text-lg">Capaian 9 Program Unggulan, tahun <?= $tahun ?></p>
        <p class="text-sm text-slate-600 mt-1">
          GERBANG adalah program unggulan Bupati dan Wakil Bupati dalam RPJMD 2025-2029.
          Capaian dihitung dari realisasi indikator yang diisi perangkat daerah pelaksana.
        </p>
      </div>
      <div class="text-right">
        <p class="text-xs text-slate-500">Rata-rata capaian</p>
        <p class="text-4xl font-extrabold" style="color:<?= warna_capaian($rata_kabupaten) ?>">
          <?= $rata_kabupaten !== null ? angka($rata_kabupaten, 1) . '%' : '-' ?>
        </p>
      </div>
    </div>
  </div>

  <ul class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($gerbang as $g):
        $cap = $g['capaian'] !== null ? (float)$g['capaian'] : null;
        $warna = warna_capaian($cap); ?>
      <li class="kartu p-5">
        <a href="?tahun=<?= $tahun ?>&amp;id=<?= (int)$g['id_gerbang'] ?>" class="block">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <span class="text-xs font-semibold" style="color:<?= e($g['warna']) ?>"><?= e($g['kode']) ?></span>
              <p class="font-bold text-base leading-snug"><?= e($g['nama']) ?></p>
            </div>
            <div class="cincin shrink-0" style="--p:<?= $cap ?? 0 ?>;--warna:<?= $warna ?>;width:64px;height:64px">
              <span style="width:52px;height:52px;font-size:.8rem"><?= $cap !== null ? angka($cap, 0) . '%' : '-' ?></span>
            </div>
          </div>
          <p class="text-sm text-slate-600 mt-2 leading-snug"><?= e($g['ringkasan']) ?></p>
          <dl class="grid grid-cols-3 gap-2 mt-4 text-center">
            <div><dt class="text-[11px] text-slate-500">Indikator</dt><dd class="font-bold"><?= (int)$g['indikator_terisi'] ?>/<?= (int)$g['jml_indikator'] ?></dd></div>
            <div><dt class="text-[11px] text-slate-500">Perangkat daerah</dt><dd class="font-bold"><?= (int)$g['jml_opd'] ?></dd></div>
            <div><dt class="text-[11px] text-slate-500">Usulan desa</dt><dd class="font-bold"><?= (int)$g['jml_usulan'] ?></dd></div>
          </dl>
          <?php if ($g['terakhir']): ?>
            <p class="text-[11px] text-slate-500 mt-3">Pembaruan terakhir <?= e(waktu_lalu($g['terakhir'])) ?></p>
          <?php endif; ?>
        </a>
      </li>
    <?php endforeach; ?>
    <?php if (!$gerbang): ?>
      <li class="kartu p-8 text-center md:col-span-2 xl:col-span-3">
        Data GERBANG belum ada. Impor berkas <b>database/pembaruan_gerbang.sql</b> terlebih dahulu.
      </li>
    <?php endif; ?>
  </ul>

<?php else:
    $cap = $terpilih['capaian'] !== null ? (float)$terpilih['capaian'] : null; ?>

  <a href="?tahun=<?= $tahun ?>" class="text-sm font-semibold" style="color:#44403B">&larr; Semua program GERBANG</a>

  <div class="kartu p-5 mt-3 mb-5">
    <div class="flex flex-wrap items-start justify-between gap-5">
      <div class="min-w-0">
        <span class="text-xs font-semibold" style="color:<?= e($terpilih['warna']) ?>"><?= e($terpilih['kode']) ?></span>
        <h2 class="text-2xl font-extrabold"><?= e($terpilih['nama']) ?></h2>
        <p class="text-sm text-slate-600 mt-1 max-w-2xl"><?= e($terpilih['ringkasan']) ?></p>
        <div class="flex flex-wrap gap-1.5 mt-3">
          <?php foreach ($opd_pelaksana as $o): ?>
            <span class="lencana" style="background:<?= $o['peran'] === 'Penanggung jawab' ? '#211F1C' : '#6B665F' ?>"><?= e($o['nama']) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="text-center">
        <div class="cincin mx-auto" style="--p:<?= $cap ?? 0 ?>;--warna:<?= warna_capaian($cap) ?>;width:116px;height:116px">
          <span style="width:94px;height:94px;font-size:1.4rem"><?= $cap !== null ? angka($cap, 1) . '%' : '-' ?></span>
        </div>
        <p class="text-xs text-slate-500 mt-2">capaian tahun <?= $tahun ?></p>
      </div>
    </div>
  </div>

  <div class="kartu overflow-hidden mb-5">
    <div class="px-4 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
      <p class="font-semibold">Indikator dan realisasi (<?= count($indikator) ?>)</p>
      <p class="text-xs text-slate-500">Realisasi diisi per triwulan secara kumulatif oleh perangkat daerah.</p>
    </div>
    <div class="overflow-x-auto">
      <table class="data w-full">
        <thead><tr>
          <th>Indikator</th><th>Perangkat daerah</th><th class="text-right">Target</th>
          <th class="text-right">Realisasi</th><th style="width:160px">Capaian</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($indikator as $i):
            $ci = capaian_indikator($i);
            $boleh_isi = $kelola || boleh('verifikator') || ($saya > 0 && (int)$i['id_opd'] === $saya); ?>
          <tr>
            <td>
              <span class="font-medium"><?= e($i['nama']) ?></span>
              <?php if ($i['catatan']): ?><div class="text-xs text-slate-500"><?= e($i['catatan']) ?></div><?php endif; ?>
            </td>
            <td class="text-sm"><?= e($i['opd'] ?? 'Belum ditentukan') ?></td>
            <td class="text-right whitespace-nowrap"><?= angka($i['target'], 0) ?> <span class="text-xs text-slate-500"><?= e($i['satuan']) ?></span></td>
            <td class="text-right whitespace-nowrap">
              <?= $i['realisasi'] !== null ? angka($i['realisasi'], 0) : '<span class="text-slate-400">-</span>' ?>
              <?php if ($i['triwulan']): ?><div class="text-xs text-slate-500">TW<?= (int)$i['triwulan'] ?>, <?= e(waktu_lalu($i['diubah_pada'])) ?></div><?php endif; ?>
            </td>
            <td>
              <div class="flex items-center gap-2">
                <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                  <div class="h-full rounded-full" style="width:<?= $ci ?? 0 ?>%;background:<?= warna_capaian($ci) ?>"></div>
                </div>
                <span class="text-xs font-semibold whitespace-nowrap"><?= $ci !== null ? angka($ci, 0) . '%' : '-' ?></span>
              </div>
            </td>
            <td class="text-right">
              <?php if ($boleh_isi): ?>
                <button type="button" class="tombol-2 text-xs" data-isi
                        data-id="<?= (int)$i['id_indikator'] ?>" data-nama="<?= e($i['nama']) ?>"
                        data-target="<?= e($i['target']) ?>" data-satuan="<?= e($i['satuan']) ?>"
                        data-realisasi="<?= e($i['realisasi'] ?? '') ?>" data-triwulan="<?= (int)($i['triwulan'] ?: 1) ?>"
                        data-catatan="<?= e($i['catatan'] ?? '') ?>">Isi realisasi</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$indikator): ?>
          <tr><td colspan="6" class="text-center text-slate-500 py-8">Belum ada indikator untuk tahun <?= $tahun ?>.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($kelola): ?>
  <details class="kartu p-5 mb-5">
    <summary class="font-semibold cursor-pointer">Tambah indikator baru</summary>
    <form method="post" action="proses/gerbang_simpan.php" class="grid gap-3 md:grid-cols-5 mt-4 items-end">
      <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
      <input type="hidden" name="aksi" value="indikator">
      <input type="hidden" name="id_gerbang" value="<?= $id_g ?>">
      <input type="hidden" name="tahun" value="<?= $tahun ?>">
      <div class="md:col-span-2">
        <label class="block text-xs text-slate-600 mb-1" for="i_nama">Nama indikator</label>
        <input id="i_nama" name="nama" required class="isian">
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1" for="i_opd">Perangkat daerah</label>
        <select id="i_opd" name="id_opd" class="isian">
          <option value="">Belum ditentukan</option>
          <?php foreach ($ref_opd as $o): ?>
            <option value="<?= $o['id_opd'] ?>"><?= e($o['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1" for="i_target">Target</label>
        <input id="i_target" name="target" type="number" step="0.01" value="100" class="isian">
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1" for="i_satuan">Satuan</label>
        <input id="i_satuan" name="satuan" value="Persen" class="isian">
      </div>
      <div class="md:col-span-5"><button class="tombol">Tambahkan indikator</button></div>
    </form>
  </details>
  <?php endif; ?>

  <?php if ($usulan_terkait): ?>
  <div class="kartu overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-200">
      <p class="font-semibold">Usulan Musrenbang yang mendukung program ini</p>
      <p class="text-xs text-slate-500">Diambil dari usulan prioritas desa yang kamusnya dikaitkan ke <?= e($terpilih['nama']) ?>.</p>
    </div>
    <table class="data w-full">
      <tbody>
      <?php foreach ($usulan_terkait as $u): ?>
        <tr>
          <td><a href="usulan_detail.php?id=<?= (int)$u['id_usulan'] ?>" class="font-medium" style="color:#44403B"><?= e($u['nama_usulan']) ?></a>
            <div class="text-xs text-slate-500"><?= e($u['desa']) ?>, Kec. <?= e($u['kecamatan']) ?></div></td>
          <td class="text-right font-semibold whitespace-nowrap"><?= $u['total_nilai'] !== null ? angka($u['total_nilai']) : 'belum dinilai' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

<?php endif; ?>
</div>

<!-- Pop up pengisian realisasi -->
<div id="lapisIsi" class="lapis-modal" hidden></div>
<div id="modalIsi" class="modal" role="dialog" aria-modal="true" aria-labelledby="judulModal" hidden>
  <form method="post" action="proses/gerbang_simpan.php">
    <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
    <input type="hidden" name="aksi" value="realisasi">
    <input type="hidden" name="id_indikator" id="m_id">
    <input type="hidden" name="kembali" value="gerbang.php?tahun=<?= $tahun ?>&id=<?= $id_g ?>">
    <div class="modal-kepala">
      <h2 id="judulModal" class="font-bold">Isi realisasi</h2>
      <button type="button" id="tutupModal" class="tombol-ikon" aria-label="Tutup">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </div>
    <div class="modal-isi">
      <p class="font-medium" id="m_nama"></p>
      <p class="text-sm text-slate-500 mb-4" id="m_target"></p>

      <label class="block text-sm font-medium mb-1" for="m_triwulan">Triwulan</label>
      <select id="m_triwulan" name="triwulan" class="isian mb-3">
        <?php for ($t = 1; $t <= 4; $t++): ?><option value="<?= $t ?>">Triwulan <?= $t ?></option><?php endfor; ?>
      </select>

      <label class="block text-sm font-medium mb-1" for="m_realisasi">Realisasi sampai triwulan ini</label>
      <input id="m_realisasi" name="realisasi" type="number" step="0.01" min="0" required class="isian mb-3" inputmode="decimal">

      <label class="block text-sm font-medium mb-1" for="m_catatan">Catatan (tidak wajib)</label>
      <input id="m_catatan" name="catatan" maxlength="400" class="isian">
    </div>
    <div class="modal-kaki">
      <button type="button" class="tombol-2" data-tutup>Batal</button>
      <button class="tombol">Simpan realisasi</button>
    </div>
  </form>
</div>

<script>
(function () {
  const lapis = document.getElementById('lapisIsi');
  const modal = document.getElementById('modalIsi');
  if (!modal) { return; }
  let pemicu = null;

  function buka(t) {
    pemicu = t;
    document.getElementById('m_id').value = t.dataset.id;
    document.getElementById('m_nama').textContent = t.dataset.nama;
    document.getElementById('m_target').textContent = 'Target ' + t.dataset.target + ' ' + t.dataset.satuan;
    document.getElementById('m_realisasi').value = t.dataset.realisasi || '';
    document.getElementById('m_triwulan').value = t.dataset.triwulan || '1';
    document.getElementById('m_catatan').value = t.dataset.catatan || '';
    modal.hidden = false; lapis.hidden = false;
    document.body.style.overflow = 'hidden';
    document.getElementById('m_realisasi').focus();
  }
  function tutup() {
    modal.hidden = true; lapis.hidden = true;
    document.body.style.overflow = '';
    if (pemicu) { pemicu.focus(); }
  }
  document.addEventListener('click', function (e) {
    const t = e.target.closest('[data-isi]');
    if (t) { buka(t); }
    if (e.target.closest('[data-tutup]') || e.target === lapis || e.target.closest('#tutupModal')) { tutup(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { tutup(); } });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
