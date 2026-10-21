<?php
require_once __DIR__ . '/includes/fungsi.php';
wajib_login();
wajib_peran('admin');

$halaman_aktif = 'bobot';
$judul_halaman = 'Bobot kriteria';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksa_csrf();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'bobot') {
        $bobot_baru = $_POST['bobot'] ?? [];
        $jumlah = 0;
        foreach ($bobot_baru as $b) { $jumlah += (float)$b; }

        if (abs($jumlah - 100) > 0.01) {
            pesan_set('galat', 'Total bobot harus tepat 100. Nilai yang dikirim berjumlah ' . angka($jumlah) . '.');
        } else {
            $stmt = mysqli_prepare($koneksi, "UPDATE tb_kriteria SET bobot = ?, deskripsi = ? WHERE id_kriteria = ?");
            foreach ($bobot_baru as $id_kr => $b) {
                $id_kr = (int)$id_kr;
                $nilai = (float)$b;
                $desk  = trim($_POST['deskripsi'][$id_kr] ?? '');
                mysqli_stmt_bind_param($stmt, 'dsi', $nilai, $desk, $id_kr);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
            $n = hitung_ulang_semua($koneksi);
            catat_log($koneksi, 'ubah bobot', 'tb_kriteria', 'Hitung ulang ' . $n . ' usulan');
            pesan_set('sukses', 'Bobot diperbarui. Nilai ' . $n . ' usulan dihitung ulang.');
        }
    }

    if ($aksi === 'kategori') {
        $stmt = mysqli_prepare($koneksi, "UPDATE tb_kategori SET nama = ?, batas_bawah = ?, warna = ? WHERE id_kategori = ?");
        foreach ($_POST['nama_kategori'] ?? [] as $id_kat => $nama) {
            $id_kat = (int)$id_kat;
            $batas  = (float)($_POST['batas'][$id_kat] ?? 0);
            $warna  = $_POST['warna'][$id_kat] ?? '#64748B';
            $nama   = trim($nama);
            mysqli_stmt_bind_param($stmt, 'sdsi', $nama, $batas, $warna, $id_kat);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        $n = hitung_ulang_semua($koneksi);
        catat_log($koneksi, 'ubah kategori', 'tb_kategori', 'Hitung ulang ' . $n . ' usulan');
        pesan_set('sukses', 'Ambang kategori diperbarui. Nilai ' . $n . ' usulan dihitung ulang.');
    }

    header('Location: bobot.php');
    exit;
}

$kriteria = ambil_kriteria($koneksi);
$kategori = ambil_kategori($koneksi);
$jumlah_bobot = total_bobot($koneksi);

require_once __DIR__ . '/includes/header.php';
?>

<div class="grid gap-5 lg:grid-cols-3">

  <form method="post" class="lg:col-span-2 kartu p-5">
    <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
    <input type="hidden" name="aksi" value="bobot">

    <p class="font-semibold text-sm text-slate-800">Bobot enam kriteria</p>
    <p class="text-xs text-slate-500 mt-1 mb-4">
      Total bobot wajib tepat 100 agar nilai akhir tetap berskala 0 sampai 100.
      Setiap perubahan langsung memicu perhitungan ulang seluruh usulan.
    </p>

    <table class="data w-full mb-4">
      <thead><tr><th>Kode</th><th>Kriteria</th><th style="width:110px">Bobot</th><th>Deskripsi</th></tr></thead>
      <tbody>
      <?php foreach ($kriteria as $kr): ?>
        <tr>
          <td class="text-slate-500"><?= e($kr['kode']) ?></td>
          <td class="font-medium"><?= e($kr['nama']) ?></td>
          <td><input type="number" step="0.01" min="0" max="100" class="isian bobot"
                     name="bobot[<?= $kr['id_kriteria'] ?>]" value="<?= e($kr['bobot']) ?>"></td>
          <td><input class="isian" name="deskripsi[<?= $kr['id_kriteria'] ?>]" value="<?= e($kr['deskripsi']) ?>"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="bg-slate-50">
          <td colspan="2" class="font-semibold">Total bobot</td>
          <td class="font-bold" id="totalBobot"><?= angka($jumlah_bobot) ?></td>
          <td class="text-xs text-slate-500">Harus bernilai 100</td>
        </tr>
      </tfoot>
    </table>

    <button class="tombol">Simpan bobot dan hitung ulang</button>
  </form>

  <form method="post" class="kartu p-5">
    <input type="hidden" name="csrf" value="<?= token_csrf() ?>">
    <input type="hidden" name="aksi" value="kategori">

    <p class="font-semibold text-sm text-slate-800">Ambang kategori prioritas</p>
    <p class="text-xs text-slate-500 mt-1 mb-4">
      Kategori dipilih dari ambang tertinggi yang terpenuhi oleh nilai akhir.
    </p>

    <div class="space-y-3">
    <?php foreach ($kategori as $kat): ?>
      <div class="border border-slate-200 rounded-lg p-3">
        <input class="isian mb-2" name="nama_kategori[<?= $kat['id_kategori'] ?>]" value="<?= e($kat['nama']) ?>">
        <div class="flex gap-2">
          <input type="number" step="0.01" class="isian" name="batas[<?= $kat['id_kategori'] ?>]"
                 value="<?= e($kat['batas_bawah']) ?>" placeholder="Batas bawah">
          <input type="color" class="isian p-1" style="width:56px"
                 name="warna[<?= $kat['id_kategori'] ?>]" value="<?= e($kat['warna']) ?>">
        </div>
      </div>
    <?php endforeach; ?>
    </div>

    <button class="tombol w-full mt-4">Simpan kategori</button>
  </form>
</div>

<div class="kartu p-5 mt-5">
  <p class="font-semibold text-sm text-slate-800 mb-2">Cara nilai dihitung</p>
  <p class="text-sm text-slate-600 leading-relaxed">
    Nilai satu kriteria sama dengan skor dibagi skala maksimum, lalu dikali bobot kriteria tersebut.
    Nilai akhir adalah penjumlahan nilai seluruh kriteria. Contoh dengan bobot berlaku saat ini:
    skor urgensi 5 menghasilkan <?= angka(hitung_nilai_kriteria(5, $kriteria[0]['bobot'] ?? 25)) ?>,
    sedangkan skor urgensi 2 menghasilkan <?= angka(hitung_nilai_kriteria(2, $kriteria[0]['bobot'] ?? 25)) ?>.
    Bobot yang berlaku saat penilaian disimpan ikut dicatat pada tabel rincian skor, sehingga hasil lama tetap dapat ditelusuri.
  </p>
</div>

<script>
function jumlahkanBobot() {
  let total = 0;
  document.querySelectorAll('.bobot').forEach(i => total += Number(i.value || 0));
  const sel = document.getElementById('totalBobot');
  sel.textContent = total.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  sel.style.color = Math.abs(total - 100) > 0.01 ? '#9F1239' : '#065F46';
}
document.querySelectorAll('.bobot').forEach(i => i.addEventListener('input', jumlahkanBobot));
jumlahkanBobot();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
