<?php
/**
 * Papan pantau: ringkasan penilaian antar-desa yang berubah secara langsung.
 * Cocok ditayangkan di layar saat musyawarah.
 */
require_once __DIR__ . '/includes/fungsi_publik.php';

$daftar_tahun = daftar_tahun_publik($koneksi);
$tahun = (int)($_GET['tahun'] ?? APP_TAHUN_DEFAULT);
$kec   = (int)($_GET['kec'] ?? 0);

$ref_kecamatan = ambil_daftar($koneksi, 'tb_kecamatan', 'id_kecamatan');
$nama_kec = '';
foreach ($ref_kecamatan as $r) {
    if ((int)$r['id_kecamatan'] === $kec) { $nama_kec = $r['nama']; }
}
if ($nama_kec === '') { $kec = 0; }

$saring_kec = $kec > 0 ? ' AND u.id_kecamatan = ' . $kec : '';

/* Ringkasan kabupaten atau kecamatan terpilih */
$ringkas = jalankan($koneksi, "
    SELECT COUNT(*) AS usulan,
           COALESCE(SUM(u.jumlah_penilai), 0) AS nilai_masuk,
           COALESCE(SUM(" . SQL_TARGET_PENILAI . "), 0) AS target,
           COALESCE(SUM(u.total_nilai IS NOT NULL), 0) AS sudah_bernilai,
           COUNT(DISTINCT CONCAT(u.id_kecamatan, '|', u.desa)) AS desa
    FROM tb_usulan u WHERE " . SYARAT_PUBLIK . $saring_kec, 'i', [$tahun])[0];

$persen_total = (int)$ringkas['target'] > 0
    ? min(100, round((int)$ringkas['nilai_masuk'] / (int)$ringkas['target'] * 100)) : 0;

$penilaian_hari_ini = (int)jalankan($koneksi, "
    SELECT COUNT(*) AS n FROM tb_penilaian p JOIN tb_usulan u ON u.id_usulan = p.id_usulan
    WHERE " . SYARAT_PUBLIK . $saring_kec . " AND DATE(p.diubah_pada) = CURDATE()", 'i', [$tahun])[0]['n'];

/* Progres setiap kecamatan */
$per_kec = jalankan($koneksi, "
    SELECT k.id_kecamatan, k.nama,
           (SELECT COUNT(*) FROM tb_desa d WHERE d.id_kecamatan = k.id_kecamatan AND d.aktif = 1) AS jml_desa,
           COUNT(u.id_usulan) AS usulan,
           COALESCE(SUM(u.jumlah_penilai), 0) AS nilai_masuk,
           COALESCE(SUM(" . SQL_TARGET_PENILAI . "), 0) AS target,
           AVG(u.total_nilai) AS rata
    FROM tb_kecamatan k
    LEFT JOIN tb_usulan u ON u.id_kecamatan = k.id_kecamatan AND " . SYARAT_PUBLIK . "
    WHERE k.aktif = 1
    GROUP BY k.id_kecamatan, k.nama
    ORDER BY k.nama", 'i', [$tahun]);

/* Nilai tertinggi dan penilaian terbaru */
$teratas = jalankan($koneksi, SQL_USULAN_DASAR . "
    WHERE " . SYARAT_PUBLIK . $saring_kec . " AND u.total_nilai IS NOT NULL
    ORDER BY u.total_nilai DESC, u.estimasi_anggaran ASC LIMIT 8", 'i', [$tahun]);

$terbaru = jalankan($koneksi, "
    SELECT p.diubah_pada, (p.diubah_pada > p.dibuat_pada) AS diperbarui,
           u.id_usulan, u.nama_usulan, u.desa, u.total_nilai, u.jumlah_penilai, u.peringkat_desa,
           kec.nama AS kecamatan, " . SQL_TARGET_PENILAI . " AS target
    FROM tb_penilaian p
    JOIN tb_usulan u      ON u.id_usulan = p.id_usulan
    JOIN tb_kecamatan kec ON kec.id_kecamatan = u.id_kecamatan
    WHERE " . SYARAT_PUBLIK . $saring_kec . "
    ORDER BY p.diubah_pada DESC, p.id_penilaian DESC LIMIT 10", 'i', [$tahun]);

/* Desa yang sedang dinilai: ada penilaian masuk dalam 60 menit terakhir,
   atau penilaiannya belum lengkap sehingga masih berjalan. */
$desa_dinilai = jalankan($koneksi, "
    SELECT u.id_kecamatan, kec.nama AS kecamatan, u.desa,
           COUNT(*) AS usulan,
           COALESCE(SUM(u.jumlah_penilai), 0) AS masuk,
           COALESCE(SUM(" . SQL_TARGET_PENILAI . "), 0) AS target,
           AVG(u.total_nilai) AS rata,
           (SELECT MAX(p.diubah_pada) FROM tb_penilaian p JOIN tb_usulan ux ON ux.id_usulan = p.id_usulan
             WHERE ux.id_kecamatan = u.id_kecamatan AND ux.desa = u.desa AND ux.tahun = u.tahun) AS terakhir
    FROM tb_usulan u JOIN tb_kecamatan kec ON kec.id_kecamatan = u.id_kecamatan
    WHERE " . SYARAT_PUBLIK . $saring_kec . "
    GROUP BY u.id_kecamatan, kec.nama, u.desa, u.tahun
    HAVING masuk > 0 AND masuk < target
    ORDER BY terakhir DESC
    LIMIT 12", 'i', [$tahun]);

/* Rincian usulan untuk pop up setiap desa */
$rincian_desa = [];
if ($desa_dinilai) {
    $pasangan = [];
    $tipe_r = 'i';
    $nilai_r = [$tahun];
    foreach ($desa_dinilai as $d) {
        $pasangan[] = '(u.id_kecamatan = ? AND u.desa = ?)';
        $tipe_r .= 'is';
        $nilai_r[] = (int)$d['id_kecamatan'];
        $nilai_r[] = $d['desa'];
    }
    foreach (jalankan($koneksi, "
        SELECT u.id_usulan, u.id_kecamatan, u.desa, u.nama_usulan, u.peringkat_desa, u.total_nilai,
               u.jumlah_penilai, " . SQL_TARGET_PENILAI . " AS target
        FROM tb_usulan u
        WHERE " . SYARAT_PUBLIK . " AND (" . implode(' OR ', $pasangan) . ")
        ORDER BY u.desa, u.peringkat_desa", $tipe_r, $nilai_r) as $r) {
        $rincian_desa[$r['id_kecamatan'] . '|' . $r['desa']][] = $r;
    }
}

$judul_publik = 'Papan pantau';
$menu_publik_aktif = 'papan';
$jeda_publik = 5000;
require __DIR__ . '/includes/publik_atas.php';
?>

<section class="kepala-hal">
  <div class="wadah pt-8 pb-24" id="isi-utama">
    <form method="get" class="flex flex-wrap items-center gap-3 mb-5">
      <select name="kec" class="isian" style="width:auto" onchange="this.form.submit()" aria-label="Kecamatan">
        <option value="0">Seluruh kabupaten</option>
        <?php foreach ($ref_kecamatan as $r): ?>
          <option value="<?= $r['id_kecamatan'] ?>" <?= $kec === (int)$r['id_kecamatan'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="tahun" class="isian" style="width:auto" onchange="this.form.submit()" aria-label="Tahun">
        <?php foreach ($daftar_tahun as $t): ?>
          <option value="<?= $t ?>" <?= $t === $tahun ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="tombol">Tampilkan</button></noscript>
      <span data-langsung-status class="ml-auto"></span>
    </form>
    <h1 class="text-3xl md:text-4xl">Papan pantau penilaian<?= $kec ? ', Kecamatan ' . e($nama_kec) : '' ?></h1>
    <p class="mt-2 text-lg" style="color:#D7D3CA">
      Setiap desa menilai <?= JUMLAH_PRIORITAS_DESA ?> usulan prioritas milik desa lain di kecamatannya.
      Angka di halaman ini bergerak sendiri saat nilai baru masuk.
    </p>
  </div>
</section>

<main class="wadah -mt-16 pb-16 relative z-10">
  <div data-langsung id="langsung-papan">

    <div class="papan-atas mb-6">
      <div class="kartu p-5 flex items-center gap-5">
        <div class="cincin" style="--p:<?= $persen_total ?>;--warna:#44403B;width:92px;height:92px">
          <span style="width:74px;height:74px;font-size:1.1rem"><?= $persen_total ?>%</span>
        </div>
        <div>
          <p class="text-sm" style="color:var(--redup)">Penilaian masuk</p>
          <p class="text-2xl font-extrabold"><?= number_format((int)$ringkas['nilai_masuk'], 0, ',', '.') ?>
            <span class="text-base font-medium" style="color:var(--redup)">dari <?= number_format((int)$ringkas['target'], 0, ',', '.') ?></span></p>
        </div>
      </div>
      <div class="kartu p-5">
        <p class="text-sm" style="color:var(--redup)">Usulan prioritas</p>
        <p class="text-3xl font-extrabold"><?= number_format((int)$ringkas['usulan'], 0, ',', '.') ?></p>
        <p class="text-sm mt-1" style="color:var(--redup)">dari <?= number_format((int)$ringkas['desa'], 0, ',', '.') ?> desa</p>
      </div>
      <div class="kartu p-5">
        <p class="text-sm" style="color:var(--redup)">Sudah bernilai</p>
        <p class="text-3xl font-extrabold"><?= number_format((int)$ringkas['sudah_bernilai'], 0, ',', '.') ?></p>
        <p class="text-sm mt-1" style="color:var(--redup)">usulan punya nilai rata-rata</p>
      </div>
      <div class="kartu p-5">
        <p class="text-sm" style="color:var(--redup)">Nilai masuk hari ini</p>
        <p class="text-3xl font-extrabold" style="color:#44403B"><?= number_format($penilaian_hari_ini, 0, ',', '.') ?></p>
        <p class="text-sm mt-1" style="color:var(--redup)">sejak pukul 00.00</p>
      </div>
    </div>

    <section class="mb-8">
      <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
        <div>
          <h2 class="judul-bagian text-xl flex items-center gap-2"><span class="langsung-titik" aria-hidden="true"></span>Desa sedang dinilai</h2>
          <p class="text-sm" style="color:var(--redup)">Klik salah satu desa untuk melihat usulan dan nilainya saat ini.</p>
        </div>
        <span class="text-sm" style="color:var(--redup)"><?= count($desa_dinilai) ?> desa dalam proses penilaian</span>
      </div>
      <?php if ($desa_dinilai): ?>
        <ul class="desa-nilai" id="daftarDesaDinilai">
          <?php foreach ($desa_dinilai as $d):
              $kunci  = $d['id_kecamatan'] . '|' . $d['desa'];
              $target = max(1, (int)$d['target']);
              $persen = min(100, round((int)$d['masuk'] / $target * 100));
              $baru   = $d['terakhir'] && (time() - strtotime($d['terakhir'])) < 900; ?>
            <li>
              <button type="button" class="<?= $baru ? 'aktif-sekarang' : '' ?>" data-desa
                      data-kunci="<?= e($kunci) ?>"
                      data-nama="<?= e($d['desa']) ?>" data-kecamatan="<?= e($d['kecamatan']) ?>"
                      data-terakhir="<?= e($d['terakhir'] ? waktu_lalu($d['terakhir']) : 'belum ada') ?>"
                      data-cap="<?= e($d['terakhir'] ?? '') ?>"
                      data-persen="<?= $persen ?>" data-masuk="<?= (int)$d['masuk'] ?>" data-target="<?= (int)$d['target'] ?>">
                <span class="flex items-start justify-between gap-2">
                  <b><?= $baru ? '<span class="denyut-kecil" aria-hidden="true"></span>' : '' ?><?= e($d['desa']) ?></b>
                  <span class="text-xs font-semibold" style="color:var(--laut)"><?= $persen ?>%</span>
                </span>
                <span class="block text-xs" style="color:var(--redup)">Kec. <?= e($d['kecamatan']) ?></span>
                <span class="jalur-progres" style="margin-top:.5rem"><i style="width:<?= $persen ?>%"></i></span>
                <span class="block text-xs mt-1.5" style="color:var(--redup)">
                  <?= (int)$d['masuk'] ?> dari <?= (int)$d['target'] ?> penilaian, terakhir <?= e($d['terakhir'] ? waktu_lalu($d['terakhir']) : 'belum ada') ?>
                </span>
              </button>
            </li>
          <?php endforeach; ?>
        </ul>
        <script type="application/json" id="dataDesaDinilai"><?= json_encode($rincian_desa, JSON_UNESCAPED_UNICODE) ?></script>
      <?php else: ?>
        <p class="kartu p-6 text-center" style="color:var(--redup)">Belum ada desa yang sedang dinilai saat ini.</p>
      <?php endif; ?>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
      <section class="xl:col-span-2">
        <h2 class="judul-bagian text-xl mb-3"><?= $kec ? 'Progres kecamatan lain' : 'Progres setiap kecamatan' ?></h2>
        <ul class="papan-kec">
          <?php foreach ($per_kec as $r):
              $target = (int)$r['target'];
              $persen = $target > 0 ? min(100, round((int)$r['nilai_masuk'] / $target * 100)) : 0;
              $sorot  = $kec && $kec === (int)$r['id_kecamatan']; ?>
            <li class="kartu p-4 <?= $sorot ? 'sorot' : '' ?>">
              <a href="papan.php?tahun=<?= $tahun ?>&amp;kec=<?= (int)$r['id_kecamatan'] ?>">
                <div class="flex items-baseline justify-between gap-2">
                  <b><?= e($r['nama']) ?></b>
                  <span class="text-sm font-bold" style="color:<?= $persen >= 100 ? '#5F7059' : 'var(--laut)' ?>"><?= $persen ?>%</span>
                </div>
                <div class="jalur-progres mt-2"><i style="width:<?= $persen ?>%"></i></div>
                <p class="text-xs mt-2" style="color:var(--redup)">
                  <?= (int)$r['usulan'] ?> usulan, <?= (int)$r['jml_desa'] ?> desa
                  <?= $r['rata'] !== null ? ', rata-rata ' . angka($r['rata']) : '' ?>
                </p>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>

        <h2 class="judul-bagian text-xl mt-8 mb-3">Nilai tertinggi saat ini</h2>
        <?php if ($teratas): ?>
          <ol class="daftar-sederhana kartu">
            <?php foreach ($teratas as $i => $u): ?>
              <li>
                <span class="rank <?= $i < 3 ? 'r' . ($i + 1) : '' ?>"><?= $i + 1 ?></span>
                <span class="min-w-0 flex-1">
                  <a href="lihat_usulan.php?id=<?= (int)$u['id_usulan'] ?>" class="font-semibold hover:underline"><?= e($u['nama_usulan']) ?></a>
                  <span class="block text-sm" style="color:var(--redup)"><?= e($u['desa']) ?>, Kec. <?= e($u['kecamatan']) ?></span>
                </span>
                <span class="w-32 shrink-0"><?= sel_nilai($u) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p class="kartu p-6 text-center" style="color:var(--redup)">Belum ada usulan yang dinilai.</p>
        <?php endif; ?>
      </section>

      <section>
        <h2 class="judul-bagian text-xl mb-3 flex items-center gap-2"><span class="langsung-titik" aria-hidden="true"></span>Masuk terbaru</h2>
        <ol class="kartu divide-y" style="border-color:var(--garis)">
          <?php foreach ($terbaru as $t):
              $baru = (time() - strtotime($t['diubah_pada'])) < 120;
              $target = max(1, (int)$t['target']);
              $persen = min(100, round((int)$t['jumlah_penilai'] / $target * 100)); ?>
            <li class="p-4 <?= $baru ? 'baris-baru' : '' ?>">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <a href="lihat_usulan.php?id=<?= (int)$t['id_usulan'] ?>" class="font-semibold hover:underline">
                    <?= $baru ? '<span class="label-baru">BARU</span>' : '' ?><?= e($t['nama_usulan']) ?>
                  </a>
                  <p class="text-xs mt-0.5" style="color:var(--redup)">
                    Prioritas <?= (int)$t['peringkat_desa'] ?> <?= e($t['desa']) ?>, Kec. <?= e($t['kecamatan']) ?>
                  </p>
                </div>
                <div class="text-right shrink-0">
                  <b class="text-lg"><?= $t['total_nilai'] !== null ? angka($t['total_nilai'], 1) : '-' ?></b>
                  <p class="text-[11px]" style="color:var(--redup)"><?= e(waktu_lalu($t['diubah_pada'])) ?></p>
                </div>
              </div>
              <div class="flex items-center gap-2 mt-2">
                <span class="jalur-progres flex-1"><i style="width:<?= $persen ?>%"></i></span>
                <small class="text-[11px]" style="color:var(--redup)"><?= (int)$t['jumlah_penilai'] ?>/<?= (int)$t['target'] ?> desa</small>
              </div>
            </li>
          <?php endforeach; ?>
          <?php if (!$terbaru): ?>
            <li class="p-6 text-center" style="color:var(--redup)">Belum ada penilaian masuk.</li>
          <?php endif; ?>
        </ol>
      </section>
    </div>
  </div>
</main>

<div id="lapisDesa" class="lapis-modal" hidden></div>
<div id="modalDesa" class="modal" role="dialog" aria-modal="true" aria-labelledby="judulDesa" hidden>
  <div class="modal-kepala">
    <div>
      <h2 id="judulDesa">Desa</h2>
      <p id="subDesa">Kecamatan</p>
    </div>
    <button type="button" class="tombol-tutup" data-tutup-desa aria-label="Tutup">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>
  <div class="modal-isi">
    <div class="flex items-center gap-3 mb-4">
      <div class="jalur-progres flex-1"><i id="barDesa" style="width:0%"></i></div>
      <span class="text-sm font-semibold" id="persenDesa">0%</span>
    </div>
    <p class="text-sm mb-3" style="color:var(--redup)" id="infoDesa"></p>
    <ol class="daftar-sederhana" id="isiDesa"></ol>
  </div>
  <div class="modal-kaki">
    <span class="text-xs" style="color:var(--redup)">Diperbarui otomatis</span>
    <button type="button" class="tombol-2" data-tutup-desa>Tutup</button>
  </div>
</div>

<div class="rak-pesan" id="rakPesan" aria-live="polite"></div>

<script>
(function () {
  const lapis = document.getElementById('lapisDesa');
  const modal = document.getElementById('modalDesa');
  let kunciAktif = null;
  let pemicu = null;

  function dataDesa() {
    const el = document.getElementById('dataDesaDinilai');
    try { return el ? JSON.parse(el.textContent) : {}; } catch (e) { return {}; }
  }

  function gambarModal(tombol) {
    const kunci = tombol.dataset.kunci;
    kunciAktif = kunci;
    document.getElementById('judulDesa').textContent = tombol.dataset.nama;
    document.getElementById('subDesa').textContent = 'Kecamatan ' + tombol.dataset.kecamatan;
    document.getElementById('barDesa').style.width = tombol.dataset.persen + '%';
    document.getElementById('persenDesa').textContent = tombol.dataset.persen + '%';
    document.getElementById('infoDesa').textContent =
      tombol.dataset.masuk + ' dari ' + tombol.dataset.target + ' penilaian sudah masuk. Penilaian terakhir ' + tombol.dataset.terakhir + '.';
    const isi = document.getElementById('isiDesa');
    isi.innerHTML = '';
    (dataDesa()[kunci] || []).forEach(function (u) {
      const li = document.createElement('li');
      const nilai = u.total_nilai === null ? '<span class="status-tunggu">Belum dinilai</span>'
        : '<b>' + Number(u.total_nilai).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</b>';
      li.innerHTML = '<span class="rank">' + u.peringkat_desa + '</span>'
        + '<span class="min-w-0 flex-1"><a class="font-semibold hover:underline" href="lihat_usulan.php?id=' + u.id_usulan + '">' + u.nama_usulan + '</a>'
        + '<span class="block text-xs" style="color:var(--redup)">' + u.jumlah_penilai + ' dari ' + u.target + ' desa menilai</span></span>'
        + '<span class="text-right">' + nilai + '</span>';
      isi.appendChild(li);
    });
    modal.hidden = false; lapis.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function tutup() {
    modal.hidden = true; lapis.hidden = true; kunciAktif = null;
    document.body.style.overflow = '';
    if (pemicu && document.body.contains(pemicu)) { pemicu.focus(); }
  }

  document.addEventListener('click', function (e) {
    const t = e.target.closest('[data-desa]');
    if (t) { pemicu = t; gambarModal(t); return; }
    if (e.target.closest('[data-tutup-desa]') || e.target === lapis) { tutup(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { tutup(); } });

  /* Pemberitahuan saat ada penilaian baru masuk */
  function cap() {
    return Array.prototype.slice.call(document.querySelectorAll('[data-desa]'))
      .map(function (b) { return b.dataset.kunci + '@' + b.dataset.cap; });
  }
  let sebelum = cap();

  function beritahu(judul, isi) {
    const rak = document.getElementById('rakPesan');
    const p = document.createElement('div');
    p.className = 'pesan-muncul';
    const titik = document.createElement('span');
    titik.className = 'titik-hidup';
    const teks = document.createElement('span');
    const b = document.createElement('b');
    b.textContent = judul;
    const kecil = document.createElement('span');
    kecil.textContent = isi;
    teks.appendChild(b); teks.appendChild(kecil);
    p.appendChild(titik); p.appendChild(teks);
    rak.appendChild(p);
    setTimeout(function () { p.remove(); }, 7000);
  }

  document.addEventListener('langsung:diperbarui', function () {
    const kini = cap();
    const baru = kini.filter(function (x) { return sebelum.indexOf(x) === -1; });
    sebelum = kini;
    baru.slice(0, 3).forEach(function (x) {
      const kunci = x.split('@')[0];
      const tombol = document.querySelector('[data-kunci="' + kunci.replace(/"/g, '') + '"]');
      if (tombol) {
        beritahu('Penilaian baru masuk', tombol.dataset.nama + ', Kec. ' + tombol.dataset.kecamatan
          + ' (' + tombol.dataset.masuk + '/' + tombol.dataset.target + ')');
      }
    });
    /* Pop up yang sedang terbuka ikut diperbarui */
    if (kunciAktif) {
      const tombol = document.querySelector('[data-kunci="' + kunciAktif.replace(/"/g, '') + '"]');
      if (tombol) { gambarModal(tombol); } else { tutup(); }
    }
  });
})();
</script>

<?php require __DIR__ . '/includes/publik_bawah.php'; ?>
