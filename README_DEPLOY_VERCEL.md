# SIMUSREN Banggai — paket awal deployment Vercel

Paket ini menambahkan konfigurasi runtime PHP komunitas untuk Vercel dan membuat koneksi database membaca environment variables. Kode aplikasi asli tetap berada di root.

## Penting sebelum dipakai publik
- Aplikasi belum siap berfungsi penuh sampai database MySQL online dibuat dan seluruh skema/data yang diperlukan diimpor.
- Runtime PHP yang dipakai adalah runtime komunitas `vercel-php@0.9.0`, bukan runtime resmi bawaan Vercel. Uji semua halaman dan proses terlebih dahulu.
- Vercel Functions bersifat serverless; jangan mengandalkan file lokal untuk menyimpan unggahan permanen.
- Pengelolaan sesi login berbasis file perlu diuji. Untuk aplikasi multi-pengguna/produksi, gunakan session store bersama dan pertimbangkan hosting PHP tradisional jika sesi tidak stabil.
- Jangan pernah memasukkan password database ke kode atau commit. Atur `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, dan jika diperlukan `DB_PORT` pada Vercel Project Settings → Environment Variables.

## GitHub + Vercel
1. Ekstrak ZIP ini.
2. Buat repository GitHub bernama `musrenbang_skoring` (Private disarankan).
3. Unggah isi folder `musrenbang_skoring` ke root repository, termasuk `vercel.json` dan folder `api`.
4. Di Vercel, Add New → Project → Import repository tersebut.
5. Framework Preset: Other. Jangan isi Build Command khusus kecuali Vercel meminta.
6. Tambahkan environment variables database setelah database online tersedia.
7. Deploy, lalu periksa Deployment Logs dan uji `/`, `/login.php`, `/assets/aplikasi.css`, login, input, penilaian, impor/ekspor, dan logout.

## Database
1. Buat database MySQL online kosong.
2. Impor `database/db_musrenbang.sql` terlebih dahulu melalui phpMyAdmin atau alat database penyedia. File SQL ini berisi perintah DROP/CREATE tabel; jangan impor ke database produksi yang berisi data tanpa backup.
3. Periksa skrip pembaruan lain di `database/` dan terapkan hanya yang memang diperlukan oleh versi skema yang digunakan. Jangan menjalankan semua file pembaruan secara membabi buta.
4. Masukkan host, nama database, user, password, dan port dari penyedia ke environment variables Vercel.

## Catatan router
Router `api/index.php` mengirim permintaan PHP ke file PHP yang sesuai dan menolak akses langsung ke direktori konfigurasi/internal. Uji rute aplikasi secara menyeluruh sebelum membagikan URL kepada pengguna.
