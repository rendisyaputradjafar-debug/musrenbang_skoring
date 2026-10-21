<?php
/**
 * Capaian 9 Program Unggulan (GERBANG) untuk publik.
 * Data berasal dari realisasi indikator yang diisi perangkat daerah dan
 * disandingkan dengan usulan prioritas desa yang mendukung program tersebut.
 */
require_once __DIR__ . '/includes/fungsi_publik.php';

$tahun_evaluasi = (int)($_GET['tahun'] ?? date('Y'));
$tahun_usulan   = (int)($_GET['tahun_usulan'] ?? APP_TAHUN_DEFAULT);

$daftar_tahun = array_map('intval', array_column(
    jalankan($koneksi, "SELECT DISTINCT tahun FROM tb_gerbang_indikator ORDER BY tahun DESC"), 'tahun'));
if (!$daftar_tahun) { $daftar_tahun = [(int)date('Y')]; }
if (!in_array($tahun_evaluasi, $daftar_tahun, true)) { $tahun_evaluasi = $daftar_tahun[0]; }

$gerbang = ambil_gerbang($koneksi, $tahun_evaluasi);
$nilai = array_filter(array_column($gerbang, 'capaian'), function ($v) { return $v !== null; });
$rata = $nilai ? array_sum($nilai) / count($nilai) : null;
$total_indikator = array_sum(array_column($gerbang, 'jml_indikator'));
$total_terisi    = array_sum(array_column($gerbang, 'indikator_terisi'));

$pilih = (int)($_GET['g'] ?? 0);
$rincian = [];
if ($pilih > 0) {
    $rincian = ambil_indikator_gerbang($koneksi, $pilih, $tahun_evaluasi);
}

$judul_publik = 'Program GERBANG';
$menu_publik_aktif = 'program';
$jeda_publik = 10000;
require __DIR__ . '/includes/publik_atas.php';
?>

<section class="kepala-hal">
  <div class="wadah pt-10 pb-24" id="isi-utama">
    <p class="text-sm font-medium" style="color:#A9A49A">RPJMD Kabupaten Banggai 2025-2029</p>
    <h1 class="text-3xl md:text-4xl mt-2">9 Program Unggulan GERBANG</h1>
    <p class="mt-3 max-w-3xl text-lg leading-relaxed" style="color:#D7D3CA">
      GERBANG adalah Gerakan Banggai untuk mewujudkan kabupaten yang Terdepan, Inovatif, Maju dan Sejahtera.
      Halaman ini menampilkan capaian setiap program beserta perangkat daerah pelaksananya.
    </p>
    <form method="get" class="flex flex-wrap items-center gap-3 mt-5">
      <label class="text-sm" for="t_eval" style="color:#A9A49A">Tahun evaluasi</label>
      <select id="t_eval" name="tahun" class="isian" style="width:auto" onchange="this.form.submit()">
        <?php foreach ($daftar_tahun as $t): ?>
          <option value="<?= $t ?>" <?= $t === $tahun_evaluasi ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
      <span data-langsung-status class="ml-auto"></span>
    </form>
  </div>
</section>

<main class="wadah -mt-16 pb-16 relative z-10">
  <div data-langsung id="langsung-program">

    <div class="papan-atas mb-8">
      <div class="kartu p-5 flex items-center gap-5">
        <div class="cincin" style="--p:<?= $rata ?? 0 ?>;--warna:<?= warna_capaian($rata) ?>;width:92px;height:92px">
          <span style="width:74px;height:74px;font-size:1.05rem"><?= $rata !== null ? angka($rata, 0) . '%' : '-' ?></span>
        </div>
        <div>
          <p class="text-sm" style="color:var(--redup)">Rata-rata capaian</p>
          <p class="text-2xl font-extrabold">9 program unggulan</p>
          <p class="text-sm" style="color:var(--redup)">tahun <?= $tahun_evaluasi ?></p>
        </div>
      </div>
      <div class="kartu p-5">
        <p class="text-sm" style="color:var(--redup)">Indikator terisi</p>
        <p class="text-3xl font-extrabold"><?= (int)$total_terisi ?><span class="text-base font-medium" style="color:var(--redup)"> / <?= (int)$total_indikator ?></span></p>
        <p class="text-sm mt-1" style="color:var(--redup)">dilaporkan perangkat daerah</p>
      </div>
      <div class="kartu p-5">
        <p class="text-sm" style="color:var(--redup)">Usulan desa terkait</p>
        <p class="text-3xl font-extrabold"><?= number_format(array_sum(array_column($gerbang, 'jml_usulan')), 0, ',', '.') ?></p>
        <p class="text-sm mt-1" style="color:var(--redup)">usulan prioritas <?= $tahun_usulan ?> yang mendukung GERBANG</p>
      </div>
    </div>

    <ul class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <?php foreach ($gerbang as $g):
          $cap = $g['capaian'] !== null ? (float)$g['capaian'] : null; ?>
        <li class="kartu p-5">
          <button type="button" class="w-full text-left" data-gerbang
                  data-kode="<?= e($g['kode']) ?>" data-nama="<?= e($g['nama']) ?>"
                  data-ringkas="<?= e($g['ringkasan']) ?>" data-capaian="<?= $cap !== null ? angka($cap, 1) . '%' : 'belum ada data' ?>"
                  data-persen="<?= $cap ?? 0 ?>" data-id="<?= (int)$g['id_gerbang'] ?>"
                  data-indikator="<?= (int)$g['indikator_terisi'] ?> dari <?= (int)$g['jml_indikator'] ?>"
                  data-opd="<?= (int)$g['jml_opd'] ?>" data-usulan="<?= (int)$g['jml_usulan'] ?>">
            <span class="flex items-start justify-between gap-3">
              <span class="min-w-0">
                <span class="text-xs font-semibold" style="color:<?= e($g['warna']) ?>"><?= e($g['kode']) ?></span>
                <b class="block text-base leading-snug"><?= e($g['nama']) ?></b>
              </span>
              <span class="cincin shrink-0" style="--p:<?= $cap ?? 0 ?>;--warna:<?= warna_capaian($cap) ?>;width:62px;height:62px">
                <span style="width:50px;height:50px;font-size:.78rem"><?= $cap !== null ? angka($cap, 0) . '%' : '-' ?></span>
              </span>
            </span>
            <span class="block text-sm mt-2 leading-snug" style="color:var(--redup)"><?= e($g['ringkasan']) ?></span>
            <span class="block text-xs mt-3" style="color:var(--redup)">
              <?= (int)$g['indikator_terisi'] ?>/<?= (int)$g['jml_indikator'] ?> indikator terisi,
              <?= (int)$g['jml_opd'] ?> perangkat daerah, <?= (int)$g['jml_usulan'] ?> usulan desa
            </span>
          </button>
        </li>
      <?php endforeach; ?>
      <?php if (!$gerbang): ?>
        <li class="kartu p-8 text-center md:col-span-2 xl:col-span-3" style="color:var(--redup)">Data program GERBANG belum tersedia.</li>
      <?php endif; ?>
    </ul>
  </div>
</main>

<div id="lapisGerbang" class="lapis-modal" hidden></div>
<div id="modalGerbang" class="modal" role="dialog" aria-modal="true" aria-labelledby="judulGerbang" hidden>
  <div class="modal-kepala">
    <div>
      <h2 id="judulGerbang">Program</h2>
      <p id="kodeGerbang"></p>
    </div>
    <button type="button" class="tombol-tutup" data-tutup-gerbang aria-label="Tutup">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>
  <div class="modal-isi">
    <p id="ringkasGerbang" class="mb-4"></p>
    <div class="flex items-center gap-3 mb-4">
      <div class="jalur-progres flex-1"><i id="barGerbang" style="width:0%"></i></div>
      <span class="font-bold" id="capaianGerbang"></span>
    </div>
    <dl class="grid grid-cols-3 gap-3 text-center">
      <div><dt class="text-xs" style="color:var(--redup)">Indikator</dt><dd class="font-bold" id="indikatorGerbang"></dd></div>
      <div><dt class="text-xs" style="color:var(--redup)">Perangkat daerah</dt><dd class="font-bold" id="opdGerbang"></dd></div>
      <div><dt class="text-xs" style="color:var(--redup)">Usulan desa</dt><dd class="font-bold" id="usulanGerbang"></dd></div>
    </dl>
  </div>
  <div class="modal-kaki">
    <a id="tautanGerbang" class="text-sm font-semibold" style="color:var(--laut)" href="lihat.php">Lihat usulan terkait</a>
    <button type="button" class="tombol-2" data-tutup-gerbang>Tutup</button>
  </div>
</div>

<script>
(function () {
  const lapis = document.getElementById('lapisGerbang');
  const modal = document.getElementById('modalGerbang');
  let pemicu = null;

  function isi(t) {
    pemicu = t;
    document.getElementById('judulGerbang').textContent = t.dataset.nama;
    document.getElementById('kodeGerbang').textContent = 'Kode ' + t.dataset.kode + ', capaian ' + t.dataset.capaian;
    document.getElementById('ringkasGerbang').textContent = t.dataset.ringkas;
    document.getElementById('barGerbang').style.width = t.dataset.persen + '%';
    document.getElementById('capaianGerbang').textContent = t.dataset.capaian;
    document.getElementById('indikatorGerbang').textContent = t.dataset.indikator;
    document.getElementById('opdGerbang').textContent = t.dataset.opd;
    document.getElementById('usulanGerbang').textContent = t.dataset.usulan;
    document.getElementById('tautanGerbang').href = 'lihat.php?gerbang=' + t.dataset.id;
    modal.hidden = false; lapis.hidden = false;
    document.body.style.overflow = 'hidden';
  }
  function tutup() {
    modal.hidden = true; lapis.hidden = true;
    document.body.style.overflow = '';
    if (pemicu && document.body.contains(pemicu)) { pemicu.focus(); }
  }
  document.addEventListener('click', function (e) {
    const t = e.target.closest('[data-gerbang]');
    if (t) { isi(t); return; }
    if (e.target.closest('[data-tutup-gerbang]') || e.target === lapis) { tutup(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { tutup(); } });
})();
</script>

<?php require __DIR__ . '/includes/publik_bawah.php'; ?>
