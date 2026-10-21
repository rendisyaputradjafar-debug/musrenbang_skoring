<?php
/**
 * Tambah atau ubah usulan.
 * Kecamatan cukup memilih lokasi, memilih usulan dari kamus, lalu mengisi jumlah dan biaya.
 */
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();

/* Usulan diinput oleh kecamatan; akun desa cukup menilai */
if (peran() === 'desa') {
    pesan_set('peringatan', 'Usulan diinput oleh operator kecamatan. Akun desa cukup menilai usulan di halaman ini.');
    header('Location: penilaian.php');
    exit;
}

$id   = (int)($_GET['id'] ?? 0);
$ubah = $id > 0;
$halaman_aktif = 'input';
$judul_halaman = $ubah ? 'Ubah usulan' : 'Tambah usulan';

$data = [
    'kode_usulan' => '', 'tahun' => APP_TAHUN_DEFAULT, 'id_kecamatan' => '', 'desa' => '',
    'id_sumber' => '', 'id_kamus' => 0, 'nama_usulan' => '', 'uraian_masalah' => '',
    'keterangan_lokasi' => '', 'volume' => '', 'satuan' => '', 'estimasi_anggaran' => '',
    'status' => 'Draft', 'catatan_verifikasi' => '', 'jumlah_penilai' => 0, 'total_nilai' => null,
];
$skor_tersimpan = [];

if (!$ubah && !empty($_SESSION['form_terakhir'])) {
    $data = array_merge($data, $_SESSION['form_terakhir']);
}
/* Dibuka dari menu Prioritas desa: desa dan tahun sudah ditentukan */
if (!$ubah && !empty($_GET['desa'])) {
    $data['desa'] = trim((string)$_GET['desa']);
}
if (!$ubah && !empty($_GET['tahun'])) {
    $data['tahun'] = (int)$_GET['tahun'];
}

if ($ubah) {
    $baris = jalankan($koneksi, "SELECT * FROM tb_usulan WHERE id_usulan = ?", 'i', [$id]);
    if (!$baris) {
        pesan_set('galat', 'Usulan tidak ditemukan.');
        header('Location: usulan.php');
        exit;
    }
    $data = $baris[0];
    if (!boleh_kecamatan((int)$data['id_kecamatan'])) {
        pesan_set('galat', 'Usulan ini berada di luar kecamatan akun Anda.');
        header('Location: usulan.php');
        exit;
    }
    foreach (jalankan($koneksi, "SELECT id_kriteria, skor FROM tb_usulan_skor WHERE id_usulan = ?", 'i', [$id]) as $s) {
        $skor_tersimpan[(int)$s['id_kriteria']] = (int)$s['skor'];
    }
}
if (kec_user() > 0) {
    $data['id_kecamatan'] = kec_user();
}

$verifikator   = boleh('admin', 'verifikator');
$kecamatan_ref = ambil_kecamatan_pilihan($koneksi);
$sumber_ref    = ambil_daftar($koneksi, 'tb_sumber_usulan', 'id_sumber');
$peta_desa     = ambil_peta_desa($koneksi);
$kriteria      = ambil_kriteria($koneksi);
$panduan       = jalankan($koneksi, "SELECT skor, keterangan FROM tb_panduan_skor ORDER BY skor");
$label_skor    = array_column($panduan, 'keterangan', 'skor');

/* Kamus: entri aktif, ditambah entri lama yang sedang dipakai usulan ini */
$kamus = ambil_kamus($koneksi);
if ($ubah && (int)$data['id_kamus'] > 0 && !in_array((int)$data['id_kamus'], array_map('intval', array_column($kamus, 'id_kamus')), true)) {
    $lama_km = jalankan($koneksi, "SELECT km.*, s.nama AS sektor, o.nama AS opd FROM tb_kamus_usulan km
        JOIN tb_sektor s ON s.id_sektor = km.id_sektor JOIN tb_opd o ON o.id_opd = km.id_opd WHERE km.id_kamus = ?",
        'i', [(int)$data['id_kamus']]);
    $kamus = array_merge($lama_km, $kamus);
}
$kamus_per_sektor = [];
foreach ($kamus as $k) { $kamus_per_sektor[$k['sektor']][] = $k; }

/* Usulan yang sudah ada per desa pada tahun ini, agar pilihan yang sama tidak diinput dua kali */
$sudah_ada = [];
$tipe_ada = 'i'; $nilai_ada = [(int)$data['tahun']];
$syarat_ada = "tahun = ? AND status <> 'Ditolak' AND id_kamus IS NOT NULL";
if (kec_user() > 0) { $syarat_ada .= ' AND id_kecamatan = ?'; $tipe_ada .= 'i'; $nilai_ada[] = kec_user(); }
foreach (jalankan($koneksi, "SELECT id_usulan, id_kecamatan, desa, id_kamus FROM tb_usulan WHERE $syarat_ada", $tipe_ada, $nilai_ada) as $r) {
    if ((int)$r['id_usulan'] === $id) { continue; }
    $sudah_ada[$r['id_kecamatan'] . '|' . $r['desa']][] = (int)$r['id_kamus'];
}

/* Rekap jumlah usulan per desa (akun kecamatan) */
$rekap_desa = [];
if (kec_user() > 0) {
    $rekap_desa = jalankan($koneksi, "
        SELECT d.nama,
               (SELECT COUNT(*) FROM tb_usulan u WHERE u.id_kecamatan = d.id_kecamatan AND u.desa = d.nama
                  AND u.tahun = ? AND u.status <> 'Ditolak') AS jumlah
        FROM tb_desa d WHERE d.id_kecamatan = ? AND d.aktif = 1 ORDER BY d.nama",
        'ii', [(int)$data['tahun'], kec_user()]);
}

require_once __DIR__ . '/includes/header.php';
$langkah = 1;
?>

<form method="post" action="proses/usulan_simpan.php" class="grid gap-5 xl:grid-cols-3" id="formUsulan">
  <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
  <input type="hidden" name="id_usulan" value="<?= $ubah ? $id : 0 ?>">

  <div class="xl:col-span-2 space-y-5">

    <?php if ($ubah && !(int)$data['id_kamus']): ?>
      <p class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm px-4 py-3">
        Usulan lama ini ("<?= e($data['nama_usulan']) ?>") belum terhubung ke kamus.
        Pilih padanannya di langkah 2 sebelum menyimpan.
      </p>
    <?php endif; ?>

    <!-- Langkah 1: lokasi -->
    <section class="kartu p-5">
      <h2 class="font-bold text-lg"><span class="text-stone-700"><?= $langkah++ ?>.</span> Untuk desa mana?</h2>
      <p class="text-sm text-slate-500 mb-4">Pilih desa yang mengajukan usulan ini.</p>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="block text-sm font-medium mb-1" for="f_kec">Kecamatan</label>
          <?php if (kec_user() > 0): ?>
            <input type="hidden" name="id_kecamatan" id="f_kec" value="<?= kec_user() ?>">
            <input class="isian" value="<?= e($_SESSION['nama_kecamatan'] ?? '') ?>" disabled>
          <?php else: ?>
            <select name="id_kecamatan" id="f_kec" required class="isian">
              <option value="">Pilih kecamatan</option>
              <?php foreach ($kecamatan_ref as $r): ?>
                <option value="<?= $r['id_kecamatan'] ?>" <?= (int)$data['id_kecamatan'] === (int)$r['id_kecamatan'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1" for="f_desa">Desa/kelurahan</label>
          <select name="desa" id="f_desa" required class="isian" data-terpilih="<?= e($data['desa']) ?>"></select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1" for="f_sumber">Sumber usulan</label>
          <select name="id_sumber" id="f_sumber" required class="isian">
            <?php foreach ($sumber_ref as $r): ?>
              <option value="<?= $r['id_sumber'] ?>" <?= (int)$data['id_sumber'] === (int)$r['id_sumber'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1" for="f_tahun">Tahun rencana</label>
          <select name="tahun" id="f_tahun" class="isian">
            <?php foreach (array_unique([(int)$data['tahun'], APP_TAHUN_DEFAULT, APP_TAHUN_DEFAULT + 1]) as $t): ?>
              <option value="<?= $t ?>" <?= (int)$data['tahun'] === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </section>

    <!-- Langkah 2: kamus -->
    <section class="kartu p-5">
      <h2 class="font-bold text-lg"><span class="text-stone-700"><?= $langkah++ ?>.</span> Pilih usulan dari kamus</h2>
      <p class="text-sm text-slate-500 mb-4">Ketik kata kunci, lalu klik salah satu. Nama, permasalahan, sektor, dan satuan terisi otomatis.</p>

      <div class="flex flex-wrap gap-2 mb-3">
        <input type="search" id="cariKamus" class="isian" style="flex:1 1 220px" placeholder="Cari, misalnya: jalan, air, posyandu" aria-label="Cari di kamus">
        <select id="saringSektor" class="isian" style="flex:0 1 200px" aria-label="Saring sektor">
          <option value="">Semua sektor</option>
          <?php foreach (array_keys($kamus_per_sektor) as $ns): ?>
            <option><?= e($ns) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (!$kamus): ?>
        <p class="rounded-xl bg-rose-50 text-rose-800 text-sm px-4 py-3">
          Kamus usulan masih kosong. Hubungi admin Bappeda untuk mengisi menu Kamus usulan.
        </p>
      <?php else: ?>
      <div class="kamus-daftar" role="radiogroup" aria-label="Kamus usulan" id="daftarKamus">
        <?php foreach ($kamus_per_sektor as $ns => $isi): ?>
          <div class="kamus-grup" data-grup="<?= e($ns) ?>"><?= e($ns) ?></div>
          <?php foreach ($isi as $k): ?>
            <label class="kamus-opsi" data-sektor="<?= e($ns) ?>"
                   data-cari="<?= e(mb_strtolower($k['nama_usulan'] . ' ' . $k['permasalahan'] . ' ' . $k['kode'] . ' ' . $k['opd'])) ?>"
                   data-id="<?= (int)$k['id_kamus'] ?>">
              <input type="radio" name="id_kamus" value="<?= (int)$k['id_kamus'] ?>" required
                     data-nama="<?= e($k['nama_usulan']) ?>" data-masalah="<?= e($k['permasalahan']) ?>"
                     data-sektor="<?= e($ns) ?>" data-opd="<?= e($k['opd']) ?>" data-satuan="<?= e($k['satuan']) ?>"
                     <?= (int)$data['id_kamus'] === (int)$k['id_kamus'] ? 'checked' : '' ?>>
              <span class="flex justify-between gap-3">
                <span class="min-w-0">
                  <b class="block text-slate-900"><?= e($k['nama_usulan']) ?></b>
                  <span class="block text-sm text-slate-600"><?= e($k['permasalahan']) ?></span>
                  <span class="block text-xs text-slate-500 mt-0.5"><?= e($k['opd']) ?>, satuan <?= e($k['satuan']) ?></span>
                </span>
                <span class="text-xs text-slate-400 shrink-0 whitespace-nowrap"><?= e($k['kode']) ?></span>
              </span>
              <span class="tanda-ada hidden text-xs font-semibold text-amber-700 mt-1">Sudah diusulkan untuk desa ini</span>
            </label>
          <?php endforeach; ?>
        <?php endforeach; ?>
        <p id="kamusKosong" class="hidden px-4 py-6 text-center text-sm text-slate-500">Tidak ada yang cocok. Coba kata lain.</p>
      </div>
      <?php endif; ?>
    </section>

    <!-- Langkah 3: jumlah dan biaya -->
    <section class="kartu p-5">
      <h2 class="font-bold text-lg"><span class="text-stone-700"><?= $langkah++ ?>.</span> Berapa jumlah dan perkiraan biayanya?</h2>
      <p class="text-sm text-slate-500 mb-4">Isi sesuai kebutuhan di lapangan.</p>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="block text-sm font-medium mb-1" for="f_volume">Jumlah (<span id="labelSatuan"><?= e($data['satuan'] ?: 'satuan') ?></span>)</label>
          <input type="number" step="0.01" min="0.01" name="volume" id="f_volume" required value="<?= e($data['volume']) ?>" class="isian" inputmode="decimal" placeholder="Contoh: 500">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1" for="f_anggaran">Perkiraan biaya (Rp)</label>
          <input type="text" name="estimasi_anggaran" id="f_anggaran" value="<?= $data['estimasi_anggaran'] !== '' ? number_format((float)$data['estimasi_anggaran'], 0, ',', '.') : '' ?>"
                 class="isian" inputmode="numeric" placeholder="Contoh: 200.000.000">
          <p class="text-xs text-slate-500 mt-1" id="bacaAnggaran"></p>
        </div>
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium mb-1" for="f_ket">Keterangan lokasi <span class="font-normal text-slate-500">(tidak wajib)</span></label>
          <input name="keterangan_lokasi" id="f_ket" maxlength="255" value="<?= e($data['keterangan_lokasi'] ?? '') ?>" class="isian" placeholder="Contoh: Dusun II, RT 03, dekat sekolah">
        </div>
      </div>
    </section>

    <?php if ($verifikator): ?>
    <!-- Khusus verifikator -->
    <section class="kartu p-5">
      <h2 class="font-bold text-lg"><span class="text-stone-700"><?= $langkah++ ?>.</span> Verifikasi <span class="text-sm font-normal text-slate-500">(khusus admin dan verifikator)</span></h2>
      <div class="grid gap-4 sm:grid-cols-2 mt-3">
        <div>
          <label class="block text-sm font-medium mb-1" for="f_status">Status</label>
          <select name="status" id="f_status" class="isian">
            <?php foreach (['Draft' => 'Terbuka untuk dinilai', 'Terverifikasi' => 'Terverifikasi', 'Ditolak' => 'Ditolak'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= $data['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1" for="f_catatan">Catatan verifikasi</label>
          <input name="catatan_verifikasi" id="f_catatan" value="<?= e($data['catatan_verifikasi']) ?>" class="isian">
        </div>
      </div>

      <label class="flex items-center gap-2 mt-5 text-sm font-medium cursor-pointer">
        <input type="checkbox" name="isi_nilai_awal" value="1" id="isiNilaiAwal" <?= $skor_tersimpan ? 'checked' : '' ?>>
        Isi nilai awal (dipakai sementara sampai ada desa yang menilai)
      </label>
      <fieldset id="bagianNilaiAwal" class="mt-3 space-y-3" <?= $skor_tersimpan ? '' : 'disabled hidden' ?>>
        <?php foreach ($kriteria as $kr): $terpilih = $skor_tersimpan[$kr['id_kriteria']] ?? 0; ?>
          <div>
            <p class="text-sm font-medium"><?= e($kr['nama']) ?> <span class="text-xs font-normal text-slate-500">bobot <?= angka($kr['bobot'], 0) ?></span></p>
            <div class="pilih-skor mt-1.5">
              <?php for ($s = 1; $s <= (int)$kr['skala_maks']; $s++): ?>
                <label>
                  <input type="radio" class="sr-only" name="skor[<?= (int)$kr['id_kriteria'] ?>]" value="<?= $s ?>" <?= $terpilih === $s ? 'checked' : '' ?>>
                  <span class="kotak"><b><?= $s ?></b><small><?= e(explode(' / ', $label_skor[$s] ?? '')[0]) ?></small></span>
                </label>
              <?php endfor; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </fieldset>
    </section>
    <?php endif; ?>
  </div>

  <aside class="space-y-5">
    <div class="kartu p-5 xl:sticky xl:top-20">
      <p class="font-bold">Ringkasan</p>
      <dl class="mt-3 space-y-2.5 text-sm">
        <div><dt class="text-xs text-slate-500">Desa</dt><dd class="font-semibold" id="rDesa">Belum dipilih</dd></div>
        <div><dt class="text-xs text-slate-500">Usulan</dt><dd class="font-semibold" id="rNama">Belum dipilih</dd></div>
        <div><dt class="text-xs text-slate-500">Permasalahan</dt><dd id="rMasalah">-</dd></div>
        <div class="grid grid-cols-2 gap-2">
          <div><dt class="text-xs text-slate-500">Sektor</dt><dd id="rSektor">-</dd></div>
          <div><dt class="text-xs text-slate-500">OPD</dt><dd id="rOpd">-</dd></div>
        </div>
      </dl>

      <?php if ($ubah): ?>
        <p class="text-xs text-slate-600 bg-slate-50 rounded-lg p-2.5 mt-4">
          Nilai saat ini <b><?= $data['total_nilai'] !== null ? angka($data['total_nilai']) : 'belum ada' ?></b>,
          dinilai <?= (int)$data['jumlah_penilai'] ?> desa.
        </p>
      <?php else: ?>
        <p class="text-xs text-slate-600 bg-slate-50 rounded-lg p-2.5 mt-4" id="infoSlot">
          Setiap desa mengisi <?= JUMLAH_PRIORITAS_DESA ?> usulan prioritas. Setelah disimpan, usulan langsung
          dinilai oleh desa-desa lain di kecamatan ini dan tampil di halaman publik.
        </p>
      <?php endif; ?>

      <?php if ($ubah): ?>
        <button class="tombol w-full mt-4">Simpan perubahan</button>
      <?php else: ?>
        <button name="lagi" value="1" class="tombol w-full mt-4">Simpan, lalu tambah lagi</button>
        <button class="tombol-2 w-full mt-2">Simpan dan selesai</button>
      <?php endif; ?>
      <a href="<?= $ubah ? 'usulan_detail.php?id=' . $id : 'usulan.php' ?>" class="block text-center text-sm text-slate-500 mt-3 hover:underline">Batal</a>
    </div>

    <?php if ($rekap_desa): $belum = count(array_filter($rekap_desa, function ($r) { return (int)$r['jumlah'] < JUMLAH_PRIORITAS_DESA; })); ?>
    <div class="kartu p-5">
      <p class="font-bold">Usulan per desa, <?= (int)$data['tahun'] ?></p>
      <p class="text-xs mt-1 mb-3 <?= $belum ? 'text-amber-700' : 'text-stone-700' ?>">
        <?= $belum ? $belum . ' dari ' . count($rekap_desa) . ' desa belum lengkap ' . JUMLAH_PRIORITAS_DESA . ' prioritas.' : 'Semua desa sudah lengkap.' ?>
      </p>
      <ul class="text-sm max-h-72 overflow-y-auto divide-y divide-slate-100">
        <?php foreach ($rekap_desa as $r): $n = (int)$r['jumlah']; ?>
          <li class="flex items-center justify-between py-1.5 gap-2">
            <span class="<?= $n ? '' : 'text-amber-700 font-medium' ?>"><?= e($r['nama']) ?></span>
            <span class="text-xs <?= $n >= JUMLAH_PRIORITAS_DESA ? 'text-stone-700 font-semibold' : 'text-amber-700' ?>">
              <?= $n ?>/<?= JUMLAH_PRIORITAS_DESA ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </aside>
</form>

<script>
(function () {
  const petaDesa = <?= json_encode($peta_desa, JSON_UNESCAPED_UNICODE) ?>;
  const sudahAda = <?= json_encode($sudah_ada, JSON_UNESCAPED_UNICODE) ?>;
  const kec = document.getElementById('f_kec');
  const desa = document.getElementById('f_desa');
  const awalDesa = desa.dataset.terpilih || '';

  /* Desa mengikuti kecamatan */
  function isiDesa(pertama) {
    const daftar = (petaDesa[kec.value] || []).slice();
    if (pertama && awalDesa && daftar.indexOf(awalDesa) === -1) { daftar.unshift(awalDesa); }
    desa.innerHTML = '';
    const o = document.createElement('option');
    o.value = '';
    o.textContent = !kec.value ? 'Pilih kecamatan dulu' : (daftar.length ? 'Pilih desa' : 'Data desa belum diisi admin');
    desa.appendChild(o);
    daftar.forEach(function (n) {
      const op = document.createElement('option');
      op.value = n; op.textContent = n;
      if (pertama && n === awalDesa) { op.selected = true; }
      desa.appendChild(op);
    });
    desa.disabled = !kec.value || !daftar.length;
    perbarui();
  }
  if (kec.tagName === 'SELECT') { kec.addEventListener('change', function () { isiDesa(false); }); }
  desa.addEventListener('change', perbarui);

  /* Pencarian kamus */
  const cari = document.getElementById('cariKamus');
  const sektor = document.getElementById('saringSektor');
  const opsi = Array.prototype.slice.call(document.querySelectorAll('.kamus-opsi'));
  const grup = Array.prototype.slice.call(document.querySelectorAll('.kamus-grup'));
  function saring() {
    const kata = (cari.value || '').toLowerCase().trim().split(/\s+/).filter(Boolean);
    const s = sektor.value;
    const tampil = {};
    let ada = 0;
    opsi.forEach(function (el) {
      const cocok = (!s || el.dataset.sektor === s) && kata.every(function (k) { return el.dataset.cari.indexOf(k) !== -1; });
      el.hidden = !cocok;
      if (cocok) { tampil[el.dataset.sektor] = true; ada++; }
    });
    grup.forEach(function (g) { g.hidden = !tampil[g.dataset.grup]; });
    const kosong = document.getElementById('kamusKosong');
    if (kosong) { kosong.classList.toggle('hidden', ada > 0); }
  }
  if (cari) {
    cari.addEventListener('input', saring);
    sektor.addEventListener('change', saring);
    cari.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
  }

  /* Ringkasan dan penanda usulan ganda */
  function perbarui() {
    const pilih = document.querySelector('input[name="id_kamus"]:checked');
    const kunci = kec.value + '|' + desa.value;
    const dipakai = sudahAda[kunci] || [];
    opsi.forEach(function (el) {
      const ganda = dipakai.indexOf(Number(el.dataset.id)) !== -1;
      el.querySelector('.tanda-ada').classList.toggle('hidden', !ganda);
      el.querySelector('input').disabled = ganda;
      el.style.opacity = ganda ? '.55' : '';
    });
    document.getElementById('rDesa').textContent = desa.value || 'Belum dipilih';
    if (pilih && !pilih.disabled) {
      document.getElementById('rNama').textContent = pilih.dataset.nama;
      document.getElementById('rMasalah').textContent = pilih.dataset.masalah;
      document.getElementById('rSektor').textContent = pilih.dataset.sektor;
      document.getElementById('rOpd').textContent = pilih.dataset.opd;
      document.getElementById('labelSatuan').textContent = pilih.dataset.satuan;
    } else {
      if (pilih) { pilih.checked = false; }
      document.getElementById('rNama').textContent = 'Belum dipilih';
      ['rMasalah', 'rSektor', 'rOpd'].forEach(function (i) { document.getElementById(i).textContent = '-'; });
    }
  }
  opsi.forEach(function (el) { el.querySelector('input').addEventListener('change', perbarui); });

  /* Perkiraan biaya: titik ribuan otomatis dan cara baca */
  const angg = document.getElementById('f_anggaran');
  const baca = document.getElementById('bacaAnggaran');
  function formatAnggaran() {
    const angka = angg.value.replace(/[^0-9]/g, '');
    angg.value = angka ? Number(angka).toLocaleString('id-ID') : '';
    const n = Number(angka || 0);
    baca.textContent = n >= 1e9 ? 'Sekitar ' + (n / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' miliar rupiah'
                     : n >= 1e6 ? 'Sekitar ' + (n / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' juta rupiah' : '';
  }
  angg.addEventListener('input', formatAnggaran);
  formatAnggaran();

  /* Nilai awal (verifikator) */
  const cek = document.getElementById('isiNilaiAwal');
  if (cek) {
    const bagian = document.getElementById('bagianNilaiAwal');
    cek.addEventListener('change', function () { bagian.disabled = !cek.checked; bagian.hidden = !cek.checked; });
  }

  isiDesa(true);
  const terpilih = document.querySelector('input[name="id_kamus"]:checked');
  if (terpilih) {
    const wadah = document.getElementById('daftarKamus');
    wadah.scrollTop = Math.max(0, terpilih.closest('.kamus-opsi').offsetTop - 40);
  }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
