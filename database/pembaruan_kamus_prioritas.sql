-- =====================================================================
-- PEMBARUAN: KAMUS USULAN DAN 5 PRIORITAS PER DESA
-- Untuk basis data yang SUDAH terpasang (XAMPP/MariaDB 10.2 ke atas).
-- Data usulan tidak hilang. Aman dijalankan lebih dari sekali.
-- =====================================================================
USE `db_musrenbang`;

-- 1. Tabel kamus usulan
CREATE TABLE IF NOT EXISTS `tb_kamus_usulan` (
  `id_kamus`     INT AUTO_INCREMENT PRIMARY KEY,
  `kode`         VARCHAR(20)  NOT NULL,
  `nama_usulan`  VARCHAR(200) NOT NULL,
  `permasalahan` VARCHAR(255) NOT NULL,
  `id_sektor`    INT NOT NULL,
  `id_opd`       INT NOT NULL,
  `satuan`       VARCHAR(30)  NOT NULL,
  `aktif`        TINYINT(1) NOT NULL DEFAULT 1,
  `dibuat_pada`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_kamus_kode` (`kode`),
  UNIQUE KEY `uq_kamus_isi` (`nama_usulan`,`permasalahan`),
  CONSTRAINT `fk_kamus_sektor` FOREIGN KEY (`id_sektor`) REFERENCES `tb_sektor`(`id_sektor`),
  CONSTRAINT `fk_kamus_opd` FOREIGN KEY (`id_opd`) REFERENCES `tb_opd`(`id_opd`)
) ENGINE=InnoDB;

-- 2. Kolom baru pada usulan
ALTER TABLE `tb_usulan`
  ADD COLUMN IF NOT EXISTS `id_kamus` INT DEFAULT NULL AFTER `id_opd`,
  ADD COLUMN IF NOT EXISTS `keterangan_lokasi` VARCHAR(255) DEFAULT NULL AFTER `uraian_masalah`,
  ADD COLUMN IF NOT EXISTS `peringkat_desa` INT DEFAULT NULL AFTER `jumlah_penilai`;
ALTER TABLE `tb_usulan`
  ADD INDEX IF NOT EXISTS `idx_prioritas` (`tahun`,`id_kecamatan`,`desa`,`peringkat_desa`);

-- 3. Isi kamus awal
-- Kamus usulan awal (contoh umum). Sesuaikan dengan kamus resmi melalui menu Master > Kamus usulan.
DROP TEMPORARY TABLE IF EXISTS `tmp_kamus`;
CREATE TEMPORARY TABLE `tmp_kamus` (kode VARCHAR(20), nama VARCHAR(200), masalah VARCHAR(255), sektor VARCHAR(100), opd VARCHAR(150), satuan VARCHAR(30));
INSERT INTO `tmp_kamus` VALUES
('INF-01','Pembangunan Jalan','Tidak ada jalan penghubung (buntu/rusak)','Infrastruktur','Dinas PUPR','Meter'),
('INF-02','Peningkatan Jalan Lingkungan (Rabat Beton)','Jalan lingkungan rusak dan becek saat hujan','Infrastruktur','Dinas Perkimtan','Meter'),
('INF-03','Pembangunan Jembatan','Warga sulit menyeberang sungai','Infrastruktur','Dinas PUPR','Unit'),
('INF-04','Pembangunan Drainase','Air menggenang saat hujan','Infrastruktur','Dinas PUPR','Meter'),
('INF-05','Rehabilitasi Jaringan Irigasi','Saluran irigasi rusak sehingga sawah tidak teraliri','Infrastruktur','Dinas PUPR','Meter'),
('INF-06','Pembangunan Sarana Air Bersih','Warga kesulitan mendapatkan air bersih','Infrastruktur','Dinas Perkimtan','Unit'),
('INF-07','Pemasangan Lampu Jalan','Jalan gelap dan rawan pada malam hari','Infrastruktur','Dinas Perhubungan','Titik'),
('INF-08','Rehabilitasi Rumah Tidak Layak Huni','Masih ada rumah warga yang tidak layak huni','Infrastruktur','Dinas Perkimtan','Unit'),
('PDK-01','Rehabilitasi Ruang Kelas','Ruang kelas rusak','Pendidikan','Dinas Pendidikan','Ruang'),
('PDK-02','Pengadaan Meja dan Kursi Sekolah','Meja dan kursi sekolah kurang atau rusak','Pendidikan','Dinas Pendidikan','Paket'),
('PDK-03','Pembangunan Gedung PAUD','Belum ada gedung PAUD','Pendidikan','Dinas Pendidikan','Unit'),
('KES-01','Pembangunan Posyandu','Kegiatan posyandu belum memiliki tempat','Kesehatan','Dinas Kesehatan','Unit'),
('KES-02','Rehabilitasi Pustu/Poskesdes','Bangunan layanan kesehatan desa rusak','Kesehatan','Dinas Kesehatan','Unit'),
('KES-03','Pembangunan Jamban Sehat','Masih ada warga yang belum memiliki jamban','Kesehatan','Dinas Kesehatan','Unit'),
('EKO-01','Bantuan Alat dan Mesin Pertanian','Petani kekurangan alat pengolah lahan','Ekonomi','Dinas Pertanian','Unit'),
('EKO-02','Bantuan Bibit dan Pupuk','Hasil panen petani masih rendah','Ekonomi','Dinas Pertanian','Paket'),
('EKO-03','Bantuan Alat Tangkap Nelayan','Alat tangkap nelayan terbatas atau rusak','Ekonomi','Dinas Perikanan','Paket'),
('EKO-04','Pembangunan Pasar Desa','Belum ada tempat jual beli yang layak','Ekonomi','Dinas Perdagangan','Unit'),
('EKO-05','Pelatihan Usaha Mikro','Keterampilan usaha warga masih rendah','Ekonomi','Dinas Perindag','Paket'),
('LH-01','Pengadaan Sarana Persampahan','Sampah belum terkelola dengan baik','Lingkungan Hidup','DLH','Unit'),
('LH-02','Pembangunan Talud Penahan','Rawan longsor atau abrasi','Lingkungan Hidup','BPBD','Meter'),
('SOS-01','Bantuan Sosial Lansia dan Disabilitas','Warga rentan belum mendapat bantuan','Sosial','Dinas Sosial','Paket'),
('PEM-01','Rehabilitasi Kantor/Balai Desa','Kantor atau balai desa rusak','Pemerintahan','Dinas PMD','Unit'),
('PEM-02','Pelatihan Aparatur Desa','Kemampuan aparatur desa masih perlu ditingkatkan','Pemerintahan','Dinas PMD','Paket'),
('WIL-01','Pemasangan Jaringan Internet Desa','Desa belum terjangkau internet','Kewilayahan','Diskominfo','Titik');
INSERT IGNORE INTO `tb_kamus_usulan` (`kode`,`nama_usulan`,`permasalahan`,`id_sektor`,`id_opd`,`satuan`)
SELECT t.kode, t.nama, t.masalah, s.id_sektor, o.id_opd, t.satuan
FROM `tmp_kamus` t
JOIN `tb_sektor` s ON s.nama = t.sektor
JOIN `tb_opd` o    ON o.nama = t.opd;
DROP TEMPORARY TABLE `tmp_kamus`;

-- 4. Kaitan data lama dan peringkat desa
-- Kaitkan usulan lama ke kamus bila nama dan permasalahannya sama persis
UPDATE `tb_usulan` u
JOIN `tb_kamus_usulan` k ON k.nama_usulan = u.nama_usulan AND k.permasalahan = u.uraian_masalah
SET u.id_kamus = k.id_kamus
WHERE u.id_kamus IS NULL;

-- Peringkat usulan di setiap desa (1 sampai 5 = usulan prioritas desa)
UPDATE `tb_usulan` SET `peringkat_desa` = NULL WHERE `status` = 'Ditolak';
UPDATE `tb_usulan` u
JOIN (SELECT id_usulan,
             ROW_NUMBER() OVER (PARTITION BY tahun, id_kecamatan, desa
                                ORDER BY (total_nilai IS NULL), total_nilai DESC, estimasi_anggaran ASC, id_usulan ASC) AS urut
      FROM `tb_usulan` WHERE `status` <> 'Ditolak') r ON r.id_usulan = u.id_usulan
SET u.peringkat_desa = r.urut;
