-- =====================================================================
-- PEMBARUAN: EVALUASI PROGRAM GERBANG (9 PROGRAM UNGGULAN RPJMD 2025-2029)
-- Sumber data: RPJMD Kabupaten Banggai 2025-2029, tabel 3.16.
-- Aman dijalankan lebih dari sekali. Data lama tidak hilang.
-- =====================================================================
USE `db_musrenbang`;

-- 1. Peran akun perangkat daerah
ALTER TABLE `tb_user`
  MODIFY `role` ENUM('admin','verifikator','operator','desa','opd') NOT NULL DEFAULT 'operator';
ALTER TABLE `tb_user`
  ADD COLUMN IF NOT EXISTS `id_opd` INT DEFAULT NULL AFTER `id_desa`;

-- 2. Kaitan usulan Musrenbang dengan program GERBANG
ALTER TABLE `tb_kamus_usulan` ADD COLUMN IF NOT EXISTS `id_gerbang` INT DEFAULT NULL AFTER `id_opd`;
ALTER TABLE `tb_usulan` ADD COLUMN IF NOT EXISTS `id_gerbang` INT DEFAULT NULL AFTER `id_kamus`;

-- 3. Tabel evaluasi GERBANG
CREATE TABLE IF NOT EXISTS `tb_gerbang` (
  `id_gerbang` INT AUTO_INCREMENT PRIMARY KEY,
  `kode`       VARCHAR(10) NOT NULL,
  `nama`       VARCHAR(120) NOT NULL,
  `ringkasan`  VARCHAR(400) NOT NULL,
  `warna`      VARCHAR(10) NOT NULL DEFAULT '#44403C',
  `urut`       INT NOT NULL DEFAULT 1,
  `aktif`      TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `uq_gerbang_kode` (`kode`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tb_gerbang_opd` (
  `id_gerbang` INT NOT NULL,
  `id_opd`     INT NOT NULL,
  `peran`      ENUM('Penanggung jawab','Pendukung') NOT NULL DEFAULT 'Pendukung',
  PRIMARY KEY (`id_gerbang`,`id_opd`),
  CONSTRAINT `fk_go_gerbang` FOREIGN KEY (`id_gerbang`) REFERENCES `tb_gerbang`(`id_gerbang`) ON DELETE CASCADE,
  CONSTRAINT `fk_go_opd` FOREIGN KEY (`id_opd`) REFERENCES `tb_opd`(`id_opd`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tb_gerbang_indikator` (
  `id_indikator` INT AUTO_INCREMENT PRIMARY KEY,
  `id_gerbang`   INT NOT NULL,
  `id_opd`       INT DEFAULT NULL,
  `nama`         VARCHAR(255) NOT NULL,
  `satuan`       VARCHAR(30) NOT NULL DEFAULT 'Persen',
  `target`       DECIMAL(14,2) NOT NULL DEFAULT 100,
  `tahun`        INT NOT NULL,
  `bobot`        DECIMAL(6,2) NOT NULL DEFAULT 1,
  `aktif`        TINYINT(1) NOT NULL DEFAULT 1,
  KEY `idx_gerbang_tahun` (`id_gerbang`,`tahun`),
  CONSTRAINT `fk_gi_gerbang` FOREIGN KEY (`id_gerbang`) REFERENCES `tb_gerbang`(`id_gerbang`) ON DELETE CASCADE,
  CONSTRAINT `fk_gi_opd` FOREIGN KEY (`id_opd`) REFERENCES `tb_opd`(`id_opd`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tb_gerbang_capaian` (
  `id_capaian`   INT AUTO_INCREMENT PRIMARY KEY,
  `id_indikator` INT NOT NULL,
  `triwulan`     TINYINT NOT NULL,
  `realisasi`    DECIMAL(14,2) NOT NULL DEFAULT 0,
  `catatan`      VARCHAR(400) DEFAULT NULL,
  `id_user`      INT DEFAULT NULL,
  `diubah_pada`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_capaian` (`id_indikator`,`triwulan`),
  KEY `idx_capaian_waktu` (`diubah_pada`),
  CONSTRAINT `fk_gc_indikator` FOREIGN KEY (`id_indikator`) REFERENCES `tb_gerbang_indikator`(`id_indikator`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- OPD pelaksana GERBANG yang belum ada di daftar
DROP TEMPORARY TABLE IF EXISTS `tmp_opd`;
CREATE TEMPORARY TABLE `tmp_opd` (nama VARCHAR(150));
INSERT INTO `tmp_opd` VALUES ('BKPSDM'),('BPKAD'),('BRIDA'),('Badan Kesbangpol'),('Bappeda'),('Bappenda'),('DLH'),('Dinas Kesehatan'),('Dinas Ketahanan Pangan'),('Dinas Koperasi dan UMKM'),('Dinas PMD'),('Dinas PTSP'),('Dinas PUPR'),('Dinas Pariwisata'),('Dinas Pendidikan'),('Dinas Perdagangan'),('Dinas Perhubungan'),('Dinas Perikanan'),('Dinas Perkimtan'),('Dinas Peternakan'),('Dinas Sosial'),('Dinas TPHP'),('Dinas Tenaga Kerja'),('Diskominfo'),('Inspektorat'),('P2KBP3A'),('RSUD Luwuk'),('Sekretariat Daerah');
INSERT INTO `tb_opd` (`nama`) SELECT t.nama FROM `tmp_opd` t
  WHERE NOT EXISTS (SELECT 1 FROM `tb_opd` o WHERE o.nama = t.nama);
DROP TEMPORARY TABLE `tmp_opd`;

-- 9 Program Unggulan (GERBANG) sesuai RPJMD 2025-2029 tabel 3.16
INSERT IGNORE INTO `tb_gerbang` (`kode`,`nama`,`ringkasan`,`warna`,`urut`) VALUES
('G1','Gerbang Cerdas','Mewujudkan generasi cerdas, berakhlak, berbudi pekerti luhur, serta menguasai IPTEK.','#1D4ED8',1),
('G2','Gerbang Sehat','Mewujudkan derajat kesehatan masyarakat yang tinggi.','#BE123C',2),
('G3','Gerbang Produktif Sejahtera','Tenaga kerja lokal terampil, pengangguran berkurang, ekonomi produktif, investasi meningkat, tanpa miskin ekstrem.','#B45309',3),
('G4','Gerbang Berbudaya','Melestarikan nilai budaya BABASALAN serta mengembangkan pariwisata alam dan budaya.','#7C3AED',4),
('G5','Gerbang Desa','Membangun desa untuk mencapai desa maju dan mandiri.','#047857',5),
('G6','Gerbang Pangan dan Pekarangan','Meningkatkan nilai tambah pertanian, perikanan, kelautan dan peternakan serta pemanfaatan pekarangan.','#15803D',6),
('G7','Gerbang Digital dan Terang','Mempercepat transformasi digital serta akses penerangan listrik rumah tangga dan jalan.','#0369A1',7),
('G8','Gerbang Layak, Bersih dan Lancar','Sarana prasarana dasar layak, lingkungan terjaga, serta jalan dan jembatan memadai.','#0F766E',8),
('G9','Gerbang Amanah','Tata kelola pemerintahan yang bersih, transparan, inovatif dan akuntabel.','#334155',9);

-- Perangkat daerah pelaksana setiap GERBANG
DROP TEMPORARY TABLE IF EXISTS `tmp_go`;
CREATE TEMPORARY TABLE `tmp_go` (kode VARCHAR(10), opd VARCHAR(150), peran VARCHAR(20));
INSERT INTO `tmp_go` VALUES
('G1','Sekretariat Daerah','Penanggung jawab'),
('G1','Dinas Pendidikan','Pendukung'),
('G1','Bappeda','Pendukung'),
('G1','Dinas Sosial','Pendukung'),
('G2','Sekretariat Daerah','Penanggung jawab'),
('G2','Dinas Kesehatan','Pendukung'),
('G2','Bappeda','Pendukung'),
('G2','RSUD Luwuk','Pendukung'),
('G2','Dinas Sosial','Pendukung'),
('G2','P2KBP3A','Pendukung'),
('G3','Sekretariat Daerah','Penanggung jawab'),
('G3','Bappeda','Pendukung'),
('G3','Dinas Koperasi dan UMKM','Pendukung'),
('G3','Dinas Tenaga Kerja','Pendukung'),
('G3','Dinas Sosial','Pendukung'),
('G3','P2KBP3A','Pendukung'),
('G3','Dinas Perdagangan','Pendukung'),
('G3','Dinas PTSP','Pendukung'),
('G4','Dinas Pendidikan','Penanggung jawab'),
('G4','Dinas Pariwisata','Pendukung'),
('G4','Badan Kesbangpol','Pendukung'),
('G4','Dinas PMD','Pendukung'),
('G5','Dinas PMD','Penanggung jawab'),
('G5','Dinas Pariwisata','Pendukung'),
('G6','Dinas TPHP','Penanggung jawab'),
('G6','Dinas Peternakan','Pendukung'),
('G6','Dinas Perikanan','Pendukung'),
('G6','Dinas Ketahanan Pangan','Pendukung'),
('G7','Sekretariat Daerah','Penanggung jawab'),
('G7','Diskominfo','Pendukung'),
('G7','Dinas Koperasi dan UMKM','Pendukung'),
('G7','Dinas Perhubungan','Pendukung'),
('G7','Dinas Perkimtan','Pendukung'),
('G8','Sekretariat Daerah','Penanggung jawab'),
('G8','Dinas PUPR','Pendukung'),
('G8','DLH','Pendukung'),
('G8','Dinas Perkimtan','Pendukung'),
('G9','Sekretariat Daerah','Penanggung jawab'),
('G9','Bappeda','Pendukung'),
('G9','BPKAD','Pendukung'),
('G9','Bappenda','Pendukung'),
('G9','BRIDA','Pendukung'),
('G9','BKPSDM','Pendukung'),
('G9','Inspektorat','Pendukung'),
('G9','Dinas PMD','Pendukung');
INSERT IGNORE INTO `tb_gerbang_opd` (`id_gerbang`,`id_opd`,`peran`)
SELECT g.id_gerbang, o.id_opd, t.peran FROM `tmp_go` t
JOIN `tb_gerbang` g ON g.kode = t.kode JOIN `tb_opd` o ON o.nama = t.opd;
DROP TEMPORARY TABLE `tmp_go`;

-- Kegiatan prioritas RPJMD menjadi indikator awal (satuan persen, target 100)
DROP TEMPORARY TABLE IF EXISTS `tmp_gi`;
CREATE TEMPORARY TABLE `tmp_gi` (kode VARCHAR(10), nama VARCHAR(255));
INSERT INTO `tmp_gi` VALUES
('G1','Beasiswa untuk siswa dan mahasiswa miskin'),
('G1','Beasiswa untuk guru'),
('G1','Hibah pengembangan perguruan tinggi daerah'),
('G1','Dana Operasional Sekolah (DOS) untuk mendukung BOS'),
('G1','Dukungan pembelajaran berbasis digital'),
('G1','Pengembangan perpustakaan digital dan perpustakaan daerah'),
('G1','Pemenuhan sarana prasarana PAUD, SD dan SMP sesuai standar'),
('G1','Gerakan kembali ke sekolah bagi anak putus sekolah (ADE Kembali Sekolah)'),
('G1','Tanpa pungutan di sekolah'),
('G2','Kesehatan gratis melalui cakupan kepesertaan BPJS (UHC Prioritas)'),
('G2','Status BLUD untuk 27 Puskesmas'),
('G2','Rintisan rumah sakit tipe C atau D di 4 wilayah prioritas'),
('G2','Pengembangan sarana prasarana dan kualitas layanan RSUD Luwuk'),
('G2','Dukungan makan bergizi gratis'),
('G2','Peningkatan sarana prasarana fasilitas kesehatan tingkat pertama'),
('G2','Penurunan kematian ibu dan bayi (Ambulance Dering Ibu Hamil)'),
('G2','Penurunan stunting'),
('G2','Pengembangan olahraga masal untuk pola hidup sehat'),
('G2','Pemerataan tenaga kesehatan'),
('G3','Pelatihan vokasional dan pelatihan kerja di LLK Luwuk maupun kemitraan'),
('G3','Pelatihan life skill di PKBM'),
('G3','UMKM naik kelas'),
('G3','Pengembangan industri kreatif'),
('G3','Participating Interest 10 persen melalui BEU'),
('G3','Fasilitasi wirausaha pemuda, santri, wirausaha sosial dan komunitas'),
('G3','Perlindungan sosial melalui modal usaha, perizinan dan pendampingan usaha'),
('G3','Pemenuhan dan pengendalian harga bahan pokok'),
('G3','Pemberdayaan perempuan, pemuda, penyandang disabilitas dan lansia'),
('G3','Perwujudan Kabupaten Layak Anak dan sekolah ramah anak'),
('G3','Kemudahan perizinan, investasi dan berusaha'),
('G3','Inovasi TUMIS IKAN TERI berbasis DTSEN'),
('G3','UGD Kemiskinan'),
('G3','Cakupan BPJS Ketenagakerjaan'),
('G4','Pemajuan dan pelestarian kebudayaan BABASALAN'),
('G4','Pengembangan 4 destinasi pariwisata prioritas (KM 5, Teluk Lalong, Salodik, Pulo Dua)'),
('G4','Pengembangan kampung atau desa wisata'),
('G4','Moderasi beragama dan pengembangan wawasan kebangsaan'),
('G5','Satu BUMDes 500 juta'),
('G5','Dukungan Koperasi Merah Putih'),
('G5','Desa maju dan mandiri'),
('G5','Desa atau kampung wisata dan tematik lainnya'),
('G6','Germas pemanfaatan pekarangan (1 Juta 1 Pekarangan)'),
('G6','Banggai Sejuta Ternak'),
('G6','Pengarusutamaan produk unggulan kelapa'),
('G6','Bantuan alat tangkap dan budidaya ikan'),
('G6','Banggai Smart Farming'),
('G6','Industrialisasi pangan untuk diversifikasi dan daya saing'),
('G7','Bebas gelap: akses rumah tangga ke listrik PLN dan lampu jalan'),
('G7','Bebas blank spot'),
('G7','Kabupaten pintar dan desa pintar'),
('G7','Banggai Command Centre'),
('G7','Penerapan open data dan Satu Data Banggai'),
('G7','Penguatan SDM digital'),
('G7','Peningkatan infrastruktur TIK'),
('G7','Digitalisasi sektor ekonomi (koperasi modern dan UMKM go digital)'),
('G7','Pengembangan platform digital'),
('G8','Banggai tanpa kekumuhan'),
('G8','Rintisan Smart Water Grid Management (PDAM pintar)'),
('G8','Pengelolaan sampah terintegrasi hulu hilir'),
('G8','Peningkatan kemantapan jalan kabupaten'),
('G8','Penataan Luwuk Water Front City, Green and Clean City dan Luwuk Kota Gas'),
('G8','Kawasan Perkantoran Halimun'),
('G8','Penanganan banjir Kota Luwuk'),
('G9','Banggai RB dan SAKIP A'),
('G9','Digitalisasi pelayanan publik'),
('G9','Peningkatan Indeks SPBE'),
('G9','Penguatan Laboratorium Inovasi untuk Banggai Inovatif'),
('G9','Kolaborasi pembangunan dengan pendekatan PENTAHELIX'),
('G9','Perencanaan dan penganggaran untuk APBD sehat'),
('G9','Penerapan sistem merit dan manajemen talenta'),
('G9','Peningkatan fiskal daerah');
INSERT INTO `tb_gerbang_indikator` (`id_gerbang`,`id_opd`,`nama`,`satuan`,`target`,`tahun`)
SELECT g.id_gerbang,
       (SELECT go.id_opd FROM `tb_gerbang_opd` go WHERE go.id_gerbang = g.id_gerbang AND go.peran = 'Penanggung jawab' LIMIT 1),
       t.nama, 'Persen', 100, YEAR(CURDATE())
FROM `tmp_gi` t JOIN `tb_gerbang` g ON g.kode = t.kode
WHERE NOT EXISTS (SELECT 1 FROM `tb_gerbang_indikator` i WHERE i.id_gerbang = g.id_gerbang AND i.nama = t.nama);
DROP TEMPORARY TABLE `tmp_gi`;

-- Akun untuk setiap perangkat daerah. Nama pengguna opd_<nama>, sandi awal opd123
INSERT IGNORE INTO `tb_user` (`username`,`password`,`nama_lengkap`,`jabatan`,`role`,`id_opd`)
SELECT CONCAT('opd_', LOWER(REPLACE(REPLACE(REPLACE(o.`nama`,' ','_'),'.',''),'-','_'))),
       '$2y$10$G37e8a9vnuaWunXKDCRDIeOf9vtXHG7NEd0DIrLVjmZdocytErVdK',
       o.`nama`, 'Operator Perangkat Daerah', 'opd', o.`id_opd`
FROM `tb_opd` o
WHERE o.`aktif` = 1
  AND NOT EXISTS (SELECT 1 FROM `tb_user` u WHERE u.id_opd = o.id_opd);

-- 4. Usulan yang sudah ada mengikuti kaitan GERBANG dari kamusnya
UPDATE `tb_usulan` u JOIN `tb_kamus_usulan` k ON k.id_kamus = u.id_kamus
SET u.id_gerbang = k.id_gerbang WHERE u.id_gerbang IS NULL AND k.id_gerbang IS NOT NULL;
