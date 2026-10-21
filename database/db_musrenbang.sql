-- =====================================================================
-- SIMUSREN BANGGAI
-- Sistem Informasi Penjaringan dan Skoring Usulan Musrenbang
-- Bappeda Kabupaten Banggai
-- Basis data: db_musrenbang (MySQL / MariaDB)
-- Impor melalui phpMyAdmin: Import > pilih berkas ini > Go
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `db_musrenbang`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_musrenbang`;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. Pengguna
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tb_user`;
CREATE TABLE `tb_user` (
  `id_user`      INT AUTO_INCREMENT PRIMARY KEY,
  `username`     VARCHAR(50)  NOT NULL UNIQUE,
  `password`     VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `jabatan`      VARCHAR(100) DEFAULT NULL,
  `role`         ENUM('admin','verifikator','operator','desa') NOT NULL DEFAULT 'operator',
  `id_kecamatan` INT DEFAULT NULL,
  `id_desa`      INT DEFAULT NULL,
  `aktif`        TINYINT(1) NOT NULL DEFAULT 1,
  `dibuat_pada`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Kata sandi awal: admin123 / verifikator123 / operator123
INSERT INTO `tb_user` (`username`,`password`,`nama_lengkap`,`jabatan`,`role`,`id_kecamatan`) VALUES
('admin','$2y$10$S/aA3PZTZlEmidQg2JDyFONDpEOFB1dOvoG2m/2pYLKjshxF57k3O','Administrator','Bidang Infrastruktur dan Kewilayahan','admin',NULL),
('verifikator','$2y$10$KrRcnkqyuC4dR6td84hpVetTVOk5tDCnKMWKxeR2wBEfNrIU/FvsO','Verifikator Bappeda','Perencana','verifikator',NULL),
('operator','$2y$10$mOwNyLGstliUej0D.iqNheCQqiTnn7P..Oc8KQIUpMahe7bfQ2Nwe','Operator Kecamatan','Kasi PMD','operator',NULL);

-- ---------------------------------------------------------------------
-- 2. Referensi wilayah dan klasifikasi
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tb_kecamatan`;
CREATE TABLE `tb_kecamatan` (
  `id_kecamatan` INT AUTO_INCREMENT PRIMARY KEY,
  `nama`         VARCHAR(80) NOT NULL,
  `aktif`        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO `tb_kecamatan` (`nama`) VALUES
('Balantak'),('Balantak Selatan'),('Balantak Utara'),('Batui'),('Batui Selatan'),
('Bualemo'),('Bunta'),('Kintom'),('Lamala'),('Lobu'),
('Luwuk'),('Luwuk Selatan'),('Luwuk Timur'),('Luwuk Utara'),('Mantoh'),
('Masama'),('Moilong'),('Nambo'),('Nuhon'),('Pagimana'),
('Simpang Raya'),('Toili'),('Toili Barat'),('Toili Jaya');

DROP TABLE IF EXISTS `tb_desa`;
CREATE TABLE `tb_desa` (
  `id_desa`      INT AUTO_INCREMENT PRIMARY KEY,
  `id_kecamatan` INT NOT NULL,
  `nama`         VARCHAR(100) NOT NULL,
  `jenis`        ENUM('Desa','Kelurahan') NOT NULL DEFAULT 'Desa',
  `aktif`        TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `uq_kec_desa` (`id_kecamatan`,`nama`),
  CONSTRAINT `fk_desa_kec` FOREIGN KEY (`id_kecamatan`) REFERENCES `tb_kecamatan`(`id_kecamatan`)
) ENGINE=InnoDB;

-- Data resmi: 24 kecamatan, 291 desa, 46 kelurahan (sumber: Book1D.pdf)
DROP TEMPORARY TABLE IF EXISTS `tmp_wilayah`;
CREATE TEMPORARY TABLE `tmp_wilayah` (kec VARCHAR(80), desa VARCHAR(100), jenis VARCHAR(10));

-- Batui: 6 desa, 7 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Batui','Honbola','Desa'),
('Batui','Kayowa','Desa'),
('Batui','Nonong','Desa'),
('Batui','Ondo-Ondolu','Desa'),
('Batui','Ondo-Ondolu I','Desa'),
('Batui','Uso','Desa'),
('Batui','Bakung','Kelurahan'),
('Batui','Balantang','Kelurahan'),
('Batui','Batui','Kelurahan'),
('Batui','Bugis','Kelurahan'),
('Batui','Lamo','Kelurahan'),
('Batui','Sisipan','Kelurahan'),
('Batui','Tolando','Kelurahan');
-- Bunta: 18 desa, 4 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Bunta','Balanga','Desa'),
('Bunta','Bohotokong','Desa'),
('Bunta','Demangan Jaya','Desa'),
('Bunta','Dondo Soboli','Desa'),
('Bunta','Hion','Desa'),
('Bunta','Huhak','Desa'),
('Bunta','Kalumbangan','Desa'),
('Bunta','Koili','Desa'),
('Bunta','Laonggo','Desa'),
('Bunta','Longgolian','Desa'),
('Bunta','Lontio','Desa'),
('Bunta','Matabas','Desa'),
('Bunta','Nanga-Nangaon','Desa'),
('Bunta','Polo','Desa'),
('Bunta','Pongian','Desa'),
('Bunta','Toima','Desa'),
('Bunta','Tombongan Ulos','Desa'),
('Bunta','Tuntung','Desa'),
('Bunta','Bunta I','Kelurahan'),
('Bunta','Bunta II','Kelurahan'),
('Bunta','Kalaka','Kelurahan'),
('Bunta','Salabenda','Kelurahan');
-- Kintom: 11 desa, 3 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Kintom','Babang Buyangge','Desa'),
('Kintom','Dimpalon','Desa'),
('Kintom','Dimpalon Baru','Desa'),
('Kintom','Kalolos','Desa'),
('Kintom','Manyula','Desa'),
('Kintom','Padang','Desa'),
('Kintom','Samadoya','Desa'),
('Kintom','Solan','Desa'),
('Kintom','Solan Baru','Desa'),
('Kintom','Tangkiang','Desa'),
('Kintom','Uling','Desa'),
('Kintom','Kintom','Kelurahan'),
('Kintom','Lontio','Kelurahan'),
('Kintom','Mendono','Kelurahan');
-- Luwuk: 2 desa, 8 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Luwuk','Lumpoknyo','Desa'),
('Luwuk','Tontouan','Desa'),
('Luwuk','Baru','Kelurahan'),
('Luwuk','Bungin','Kelurahan'),
('Luwuk','Bungin Timur','Kelurahan'),
('Luwuk','Kaleke','Kelurahan'),
('Luwuk','Karaton','Kelurahan'),
('Luwuk','Luwuk','Kelurahan'),
('Luwuk','Mangkio Baru','Kelurahan'),
('Luwuk','Soho','Kelurahan');
-- Lamala: 12 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Lamala','Bahari Makmur','Desa'),
('Lamala','Baruga','Desa'),
('Lamala','Bonebobakal','Desa'),
('Lamala','Kagitakan','Desa'),
('Lamala','Kotabaru','Desa'),
('Lamala','Kota Raya','Desa'),
('Lamala','Labotan','Desa'),
('Lamala','Lomba','Desa'),
('Lamala','Nipa','Desa'),
('Lamala','Poroan','Desa'),
('Lamala','Sirom','Desa'),
('Lamala','Tinonda','Desa');
-- Balantak: 10 desa, 3 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Balantak','Boloak','Desa'),
('Balantak','Dolom','Desa'),
('Balantak','Kiloma','Desa'),
('Balantak','Luok','Desa'),
('Balantak','Mamping','Desa'),
('Balantak','Padang','Desa'),
('Balantak','Ra''u','Desa'),
('Balantak','Talima A','Desa'),
('Balantak','Talima B','Desa'),
('Balantak','Tanotu','Desa'),
('Balantak','Balantak','Kelurahan'),
('Balantak','Dale-Dale','Kelurahan'),
('Balantak','Talang Batu','Kelurahan');
-- Pagimana: 30 desa, 3 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Pagimana','Ampera','Desa'),
('Pagimana','Asaan','Desa'),
('Pagimana','Bajo Poat','Desa'),
('Pagimana','Balai Gondi','Desa'),
('Pagimana','Baloa Doda','Desa'),
('Pagimana','Bondat','Desa'),
('Pagimana','Bulu','Desa'),
('Pagimana','Bungawon','Desa'),
('Pagimana','Dongkalan','Desa'),
('Pagimana','Gomuo','Desa'),
('Pagimana','Hohudongan','Desa'),
('Pagimana','Huhak','Desa'),
('Pagimana','Jaya Bakti','Desa'),
('Pagimana','Lambangan','Desa'),
('Pagimana','Lamo','Desa'),
('Pagimana','Nain','Desa'),
('Pagimana','Pisou','Desa'),
('Pagimana','Pinapuan','Desa'),
('Pagimana','Poh','Desa'),
('Pagimana','Sammajatem','Desa'),
('Pagimana','Sepa','Desa'),
('Pagimana','Sinampangnyo','Desa'),
('Pagimana','Siuna','Desa'),
('Pagimana','Tampe','Desa'),
('Pagimana','Taloyon','Desa'),
('Pagimana','Tintingan','Desa'),
('Pagimana','Toipan','Desa'),
('Pagimana','Tongkonunuk','Desa'),
('Pagimana','Tombang','Desa'),
('Pagimana','Uwedaka','Desa'),
('Pagimana','Basabungan','Kelurahan'),
('Pagimana','Pagimana','Kelurahan'),
('Pagimana','Pakowa','Kelurahan');
-- Bualemo: 20 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Bualemo','Bima Karya','Desa'),
('Bualemo','Binsil','Desa'),
('Bualemo','Binsil Padang','Desa'),
('Bualemo','Bualemo A','Desa'),
('Bualemo','Bualemo B','Desa'),
('Bualemo','Dwi Karya','Desa'),
('Bualemo','Lembah Tompotika','Desa'),
('Bualemo','Lembah Makmur','Desa'),
('Bualemo','Longkoga Barat','Desa'),
('Bualemo','Longkoga Timur','Desa'),
('Bualemo','Malik','Desa'),
('Bualemo','Malik Makmur','Desa'),
('Bualemo','Mayayap','Desa'),
('Bualemo','Nipa Kalemoan','Desa'),
('Bualemo','Salipi','Desa'),
('Bualemo','Sampaka','Desa'),
('Bualemo','Taima','Desa'),
('Bualemo','Tikupan','Desa'),
('Bualemo','Trans Mayayap','Desa'),
('Bualemo','Toiba','Desa');
-- Toili: 14 desa, 1 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Toili','Benteng','Desa'),
('Toili','Cendana Pura','Desa'),
('Toili','Jaya Kencana','Desa'),
('Toili','Mansahang','Desa'),
('Toili','Rusa Kencana','Desa'),
('Toili','Samalore','Desa'),
('Toili','Sentral Sari','Desa'),
('Toili','Sentral Timur','Desa'),
('Toili','Sidomukti','Desa'),
('Toili','Singkoyo','Desa'),
('Toili','Tanah Abang','Desa'),
('Toili','Tirta Kencana','Desa'),
('Toili','Tirta Sari','Desa'),
('Toili','Ue Mea','Desa'),
('Toili','Cendana','Kelurahan');
-- Masama: 14 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Masama','Cemerlang','Desa'),
('Masama','Duata Karya','Desa'),
('Masama','Eteng','Desa'),
('Masama','Kembang Merta','Desa'),
('Masama','Kospa Duatakarya','Desa'),
('Masama','Minang Andala','Desa'),
('Masama','Padangon','Desa'),
('Masama','Purwo Agung','Desa'),
('Masama','Rangga-Rangga','Desa'),
('Masama','Serese','Desa'),
('Masama','Simpangan','Desa'),
('Masama','Tangeban','Desa'),
('Masama','Taugi','Desa'),
('Masama','Tompotika Makmur','Desa');
-- Luwuk Timur: 13 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Luwuk Timur','Bantayan','Desa'),
('Luwuk Timur','Baya','Desa'),
('Luwuk Timur','Boitan','Desa'),
('Luwuk Timur','Bukit Mulya','Desa'),
('Luwuk Timur','Hunduhon','Desa'),
('Luwuk Timur','Indang Sari','Desa'),
('Luwuk Timur','Kayutanyo','Desa'),
('Luwuk Timur','Lauwon','Desa'),
('Luwuk Timur','Lontos','Desa'),
('Luwuk Timur','Louk','Desa'),
('Luwuk Timur','Molino','Desa'),
('Luwuk Timur','Pohi','Desa'),
('Luwuk Timur','Uwedikan','Desa');
-- Toili Barat: 17 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Toili Barat','Bukit Makarti','Desa'),
('Toili Barat','Bumi Harapan','Desa'),
('Toili Barat','Dongin','Desa'),
('Toili Barat','Gunung Keramat','Desa'),
('Toili Barat','Kami Wangi','Desa'),
('Toili Barat','Karya Makmur','Desa'),
('Toili Barat','Lembah Kramat','Desa'),
('Toili Barat','Makapa','Desa'),
('Toili Barat','Mantawa','Desa'),
('Toili Barat','Mantawa Bonebae','Desa'),
('Toili Barat','Mekar Jaya','Desa'),
('Toili Barat','Mekar Sari','Desa'),
('Toili Barat','Pandan Wangi','Desa'),
('Toili Barat','Pasir Lamba','Desa'),
('Toili Barat','Rata','Desa'),
('Toili Barat','Sindang Sari','Desa'),
('Toili Barat','Uwelolu','Desa');
-- Nuhon: 20 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Nuhon','Balaan','Desa'),
('Nuhon','Bangketa','Desa'),
('Nuhon','Batu Hitam','Desa'),
('Nuhon','Bella','Desa'),
('Nuhon','Binohu','Desa'),
('Nuhon','Bolobungkang','Desa'),
('Nuhon','Damai Makmur','Desa'),
('Nuhon','Jaya Makmur','Desa'),
('Nuhon','Kabua Bua','Desa'),
('Nuhon','Mantan B','Desa'),
('Nuhon','Pakowa Bunta','Desa'),
('Nuhon','Petak','Desa'),
('Nuhon','Pibombo','Desa'),
('Nuhon','Pulo Dalagan','Desa'),
('Nuhon','Obo Balingara','Desa'),
('Nuhon','Saiti','Desa'),
('Nuhon','Sumber Agung','Desa'),
('Nuhon','Tomeang','Desa'),
('Nuhon','Tetesulu','Desa'),
('Nuhon','Tobelombang','Desa');
-- Moilong: 16 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Moilong','Arga Kencana','Desa'),
('Moilong','Argo Mulyo','Desa'),
('Moilong','Bumi Harjo','Desa'),
('Moilong','Karang Anyar','Desa'),
('Moilong','Karya Jaya','Desa'),
('Moilong','Minakarya','Desa'),
('Moilong','Minahaki','Desa'),
('Moilong','Moilong','Desa'),
('Moilong','Mulyoharjo','Desa'),
('Moilong','Saluan','Desa'),
('Moilong','Selamet Raharjo','Desa'),
('Moilong','Sido Makmur','Desa'),
('Moilong','Sidoharjo','Desa'),
('Moilong','Sumber Harjo','Desa'),
('Moilong','Toili','Desa'),
('Moilong','Tou','Desa');
-- Batui Selatan: 10 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Batui Selatan','Bonebalantak','Desa'),
('Batui Selatan','Gori-Gori','Desa'),
('Batui Selatan','Maasing','Desa'),
('Batui Selatan','Maleo Jaya','Desa'),
('Batui Selatan','Masungkang','Desa'),
('Batui Selatan','Ombolu','Desa'),
('Batui Selatan','Paisu Buloli','Desa'),
('Batui Selatan','Sinorang','Desa'),
('Batui Selatan','Sukamaju','Desa'),
('Batui Selatan','Sukamaju Satu','Desa');
-- Lobu: 10 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Lobu','Bahingin','Desa'),
('Lobu','Balean','Desa'),
('Lobu','Bolobungkang','Desa'),
('Lobu','Dolom','Desa'),
('Lobu','Kadodi','Desa'),
('Lobu','Lambuli','Desa'),
('Lobu','Lobu','Desa'),
('Lobu','Niubulan','Desa'),
('Lobu','Uha Uhangon','Desa'),
('Lobu','Uwe Daka-Daka','Desa');
-- Simpang Raya: 12 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Simpang Raya','Beringin Jaya','Desa'),
('Simpang Raya','Doda Bunta','Desa'),
('Simpang Raya','Dowiwi','Desa'),
('Simpang Raya','Dwipa Karya','Desa'),
('Simpang Raya','Gonohop','Desa'),
('Simpang Raya','Koninis','Desa'),
('Simpang Raya','Lokait','Desa'),
('Simpang Raya','Mantan A','Desa'),
('Simpang Raya','Rantau Jaya','Desa'),
('Simpang Raya','Simpang Satu','Desa'),
('Simpang Raya','Simpang Dua','Desa'),
('Simpang Raya','Sumber Mulia','Desa');
-- Balantak Selatan: 11 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Balantak Selatan','Booy','Desa'),
('Balantak Selatan','Dondo','Desa'),
('Balantak Selatan','Giwang','Desa'),
('Balantak Selatan','Gorontalo','Desa'),
('Balantak Selatan','Poyang','Desa'),
('Balantak Selatan','Resarna','Desa'),
('Balantak Selatan','Sepe','Desa'),
('Balantak Selatan','Tenggawas','Desa'),
('Balantak Selatan','Tintingon','Desa'),
('Balantak Selatan','Tombos','Desa'),
('Balantak Selatan','Tongke','Desa');
-- Balantak Utara: 10 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Balantak Utara','Batu Mandi','Desa'),
('Balantak Utara','Batu Simpang','Desa'),
('Balantak Utara','Kampangar','Desa'),
('Balantak Utara','Kuntang','Desa'),
('Balantak Utara','Ondoliang','Desa'),
('Balantak Utara','Pangkalaseang','Desa'),
('Balantak Utara','Pangkalaseang Baru','Desa'),
('Balantak Utara','Pulau Dua','Desa'),
('Balantak Utara','Teku','Desa'),
('Balantak Utara','Toweer','Desa');
-- Luwuk Selatan: 1 desa, 9 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Luwuk Selatan','Bubung','Desa'),
('Luwuk Selatan','Bukit Mambual','Kelurahan'),
('Luwuk Selatan','Hanga-Hanga','Kelurahan'),
('Luwuk Selatan','Hanga-Hanga Permai','Kelurahan'),
('Luwuk Selatan','Jole','Kelurahan'),
('Luwuk Selatan','Kompo','Kelurahan'),
('Luwuk Selatan','Maahas','Kelurahan'),
('Luwuk Selatan','Simpong','Kelurahan'),
('Luwuk Selatan','Tanjung Tuwis','Kelurahan'),
('Luwuk Selatan','Tombang Permai','Kelurahan');
-- Luwuk Utara: 9 desa, 2 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Luwuk Utara','Awu','Desa'),
('Luwuk Utara','Biak','Desa'),
('Luwuk Utara','Boyou','Desa'),
('Luwuk Utara','Bumi Beringin','Desa'),
('Luwuk Utara','Bunga','Desa'),
('Luwuk Utara','Buon Mandiri','Desa'),
('Luwuk Utara','Kamumu','Desa'),
('Luwuk Utara','Lenyek','Desa'),
('Luwuk Utara','Salodik','Desa'),
('Luwuk Utara','Kilongan','Kelurahan'),
('Luwuk Utara','Kilongan Permai','Kelurahan');
-- Mantoh: 10 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Mantoh','Binotik','Desa'),
('Mantoh','Bollo','Desa'),
('Mantoh','Bombongan','Desa'),
('Mantoh','Boras','Desa'),
('Mantoh','Garuga','Desa'),
('Mantoh','Lonas','Desa'),
('Mantoh','Pondan','Desa'),
('Mantoh','Sobol','Desa'),
('Mantoh','Sobol Baru','Desa'),
('Mantoh','Sulubombong','Desa');
-- Nambo: 5 desa, 6 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Nambo','Koyoan','Desa'),
('Nambo','Koyoan Permai','Desa'),
('Nambo','Lumbe','Desa'),
('Nambo','Padungnyo','Desa'),
('Nambo','Sayambongin','Desa'),
('Nambo','Lontio','Kelurahan'),
('Nambo','Lontio Baru','Kelurahan'),
('Nambo','Nambo Bosaa','Kelurahan'),
('Nambo','Nambo Lempek','Kelurahan'),
('Nambo','Nambo Lempek Baru','Kelurahan'),
('Nambo','Nambo Padang','Kelurahan');
-- Toili Jaya: 10 desa, 0 kelurahan
INSERT INTO `tmp_wilayah` VALUES
('Toili Jaya','Tolisu','Desa'),
('Toili Jaya','Marga Kencana','Desa'),
('Toili Jaya','Mekar Kencana','Desa'),
('Toili Jaya','Mulya Sari','Desa'),
('Toili Jaya','Tirta Jaya','Desa'),
('Toili Jaya','Sari Buana','Desa'),
('Toili Jaya','Tohiti Sari','Desa'),
('Toili Jaya','Sindang Baru','Desa'),
('Toili Jaya','Bukit Jaya','Desa'),
('Toili Jaya','Piondo','Desa');

INSERT IGNORE INTO `tb_desa` (`id_kecamatan`,`nama`,`jenis`)
SELECT k.id_kecamatan, t.desa, t.jenis
FROM `tmp_wilayah` t JOIN `tb_kecamatan` k ON k.nama = t.kec;

DROP TEMPORARY TABLE `tmp_wilayah`;

-- Akun operator untuk setiap kecamatan.
-- Nama pengguna: kec_<nama kecamatan>, contoh kec_luwuk_selatan. Sandi awal: kecamatan123
INSERT IGNORE INTO `tb_user` (`username`,`password`,`nama_lengkap`,`jabatan`,`role`,`id_kecamatan`)
SELECT CONCAT('kec_', LOWER(REPLACE(`nama`,' ','_'))),
       '$2y$10$s6J9Qx2izv5/1ot.GrNcJe1Jn9I9mI1f.q4ZxRt/zSMhexRCwgHLO',
       CONCAT('Operator Kecamatan ', `nama`), 'Operator Kecamatan', 'operator', `id_kecamatan`
FROM `tb_kecamatan`;

-- Akun untuk setiap desa/kelurahan. Nama pengguna <kecamatan>.<desa>, sandi awal desa123
INSERT IGNORE INTO `tb_user` (`username`,`password`,`nama_lengkap`,`jabatan`,`role`,`id_kecamatan`,`id_desa`)
SELECT CONCAT(LOWER(REPLACE(REPLACE(REPLACE(REPLACE(k.`nama`,' ','_'),'-','_'),'''',''),'.','')), '.', LOWER(REPLACE(REPLACE(REPLACE(REPLACE(d.`nama`,' ','_'),'-','_'),'''',''),'.',''))),
       '$2y$10$1mkAEtDsTsuWENv3bHSAxO4KO1RbV3caz.PncCpVth013qS.cthN.',
       CONCAT(d.`jenis`, ' ', d.`nama`, ', Kec. ', k.`nama`),
       CONCAT('Operator ', d.`jenis`), 'desa', d.`id_kecamatan`, d.`id_desa`
FROM `tb_desa` d
JOIN `tb_kecamatan` k ON k.`id_kecamatan` = d.`id_kecamatan`
WHERE d.`aktif` = 1
  AND NOT EXISTS (SELECT 1 FROM `tb_user` u WHERE u.`id_desa` = d.`id_desa`);

DROP TABLE IF EXISTS `tb_sektor`;
CREATE TABLE `tb_sektor` (
  `id_sektor` INT AUTO_INCREMENT PRIMARY KEY,
  `nama`      VARCHAR(80) NOT NULL,
  `warna`     VARCHAR(9) NOT NULL DEFAULT '#0F766E',
  `aktif`     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO `tb_sektor` (`nama`,`warna`) VALUES
('Infrastruktur','#0F766E'),
('Lingkungan Hidup','#15803D'),
('Pendidikan','#1D4ED8'),
('Kesehatan','#BE123C'),
('Ekonomi','#B45309'),
('Sosial','#7C3AED'),
('Pemerintahan','#334155'),
('Kewilayahan','#0369A1');

DROP TABLE IF EXISTS `tb_sumber_usulan`;
CREATE TABLE `tb_sumber_usulan` (
  `id_sumber` INT AUTO_INCREMENT PRIMARY KEY,
  `nama`      VARCHAR(80) NOT NULL,
  `aktif`     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO `tb_sumber_usulan` (`nama`) VALUES
('Desa/Kelurahan'),('Musrenbang Kecamatan'),('Pokok Pikiran DPRD'),
('Forum OPD'),('Masyarakat/Komunitas'),('Lainnya');

DROP TABLE IF EXISTS `tb_opd`;
CREATE TABLE `tb_opd` (
  `id_opd` INT AUTO_INCREMENT PRIMARY KEY,
  `nama`   VARCHAR(120) NOT NULL,
  `aktif`  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO `tb_opd` (`nama`) VALUES
('Dinas PUPR'),('Dinas Perkimtan'),('DLH'),('Dinas Pendidikan'),('Dinas Kesehatan'),
('Dinas PMD'),('Dinas Sosial'),('Dinas Perdagangan'),('Dinas Perindag'),('Bappeda'),
('Dinas Perhubungan'),('Diskominfo'),('Dinas Ketahanan Pangan'),('BPBD'),
('Dinas Pertanian'),('Dinas Perikanan'),('Dinas lainnya');

-- ---------------------------------------------------------------------
-- 3. Kriteria penilaian dan bobot
--    nilai = (skor / skala_maksimum) x bobot
--    Total bobot wajib 100.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tb_kriteria`;
CREATE TABLE `tb_kriteria` (
  `id_kriteria`  INT AUTO_INCREMENT PRIMARY KEY,
  `kode`         VARCHAR(10) NOT NULL UNIQUE,
  `nama`         VARCHAR(100) NOT NULL,
  `deskripsi`    TEXT,
  `bobot`        DECIMAL(6,2) NOT NULL,
  `skala_maks`   TINYINT NOT NULL DEFAULT 5,
  `urut`         TINYINT NOT NULL DEFAULT 1,
  `aktif`        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO `tb_kriteria` (`kode`,`nama`,`deskripsi`,`bobot`,`skala_maks`,`urut`) VALUES
('K1','Urgensi','Tingkat kemendesakan penanganan masalah pada kondisi eksisting.',25.00,5,1),
('K2','Dampak/Manfaat','Luas sebaran penerima manfaat dan besaran dampak yang dihasilkan.',20.00,5,2),
('K3','Prioritas Pembangunan','Kesesuaian dengan prioritas RPJMD, RKPD, dan tema pembangunan tahun rencana.',15.00,5,3),
('K4','Kelayakan Teknis/Kewenangan','Kesesuaian kewenangan daerah, tata ruang, dan standar teknis kegiatan.',20.00,5,4),
('K5','Partisipasi','Tingkat keterlibatan dan dukungan masyarakat terhadap usulan.',10.00,5,5),
('K6','Kesiapan','Kesiapan lahan, dokumen perencanaan teknis, dan kesiapan pelaksanaan.',10.00,5,6);

DROP TABLE IF EXISTS `tb_kategori`;
CREATE TABLE `tb_kategori` (
  `id_kategori`  INT AUTO_INCREMENT PRIMARY KEY,
  `nama`         VARCHAR(60) NOT NULL,
  `batas_bawah`  DECIMAL(6,2) NOT NULL,
  `warna`        VARCHAR(9) NOT NULL DEFAULT '#64748B',
  `urut`         TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO `tb_kategori` (`nama`,`batas_bawah`,`warna`,`urut`) VALUES
('Prioritas Sangat Tinggi',90.00,'#065F46',1),
('Prioritas Tinggi',80.00,'#0F766E',2),
('Prioritas Sedang',70.00,'#B45309',3),
('Prioritas Rendah',60.00,'#C2410C',4),
('Tidak Prioritas',0.00,'#9F1239',5);

DROP TABLE IF EXISTS `tb_panduan_skor`;
CREATE TABLE `tb_panduan_skor` (
  `skor`      TINYINT PRIMARY KEY,
  `keterangan` VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

INSERT INTO `tb_panduan_skor` (`skor`,`keterangan`) VALUES
(5,'Sangat tinggi / sangat baik'),
(4,'Tinggi / baik'),
(3,'Sedang'),
(2,'Rendah'),
(1,'Sangat rendah / tidak siap');

-- ---------------------------------------------------------------------
-- 4. Usulan
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tb_usulan`;
CREATE TABLE `tb_usulan` (
  `id_usulan`         INT AUTO_INCREMENT PRIMARY KEY,
  `kode_usulan`       VARCHAR(40) NOT NULL UNIQUE,
  `tahun`             SMALLINT NOT NULL,
  `id_kecamatan`      INT NOT NULL,
  `desa`              VARCHAR(100) NOT NULL,
  `id_sumber`         INT NOT NULL,
  `id_sektor`         INT NOT NULL,
  `id_opd`            INT NOT NULL,
  `id_kamus`          INT DEFAULT NULL,
  `nama_usulan`       VARCHAR(255) NOT NULL,
  `uraian_masalah`    TEXT,
  `keterangan_lokasi` VARCHAR(255) DEFAULT NULL,
  `volume`            DECIMAL(14,2) DEFAULT 0,
  `satuan`            VARCHAR(30) DEFAULT NULL,
  `estimasi_anggaran` DECIMAL(18,2) DEFAULT 0,
  `nilai_awal`        DECIMAL(6,2) DEFAULT NULL,
  `jumlah_penilai`    INT NOT NULL DEFAULT 0,
  `peringkat_desa`    INT DEFAULT NULL,
  `total_nilai`       DECIMAL(6,2) DEFAULT NULL,
  `id_kategori`       INT DEFAULT NULL,
  `status`            ENUM('Draft','Terverifikasi','Ditolak') NOT NULL DEFAULT 'Draft',
  `catatan_verifikasi` TEXT,
  `id_user`           INT DEFAULT NULL,
  `dibuat_pada`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diubah_pada`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_tahun` (`tahun`),
  KEY `idx_kec` (`id_kecamatan`),
  KEY `idx_desa` (`id_kecamatan`,`desa`),
  KEY `idx_prioritas` (`tahun`,`id_kecamatan`,`desa`,`peringkat_desa`),
  KEY `idx_sektor` (`id_sektor`),
  KEY `idx_opd` (`id_opd`),
  KEY `idx_nilai` (`total_nilai`),
  CONSTRAINT `fk_usulan_kec` FOREIGN KEY (`id_kecamatan`) REFERENCES `tb_kecamatan`(`id_kecamatan`),
  CONSTRAINT `fk_usulan_sumber` FOREIGN KEY (`id_sumber`) REFERENCES `tb_sumber_usulan`(`id_sumber`),
  CONSTRAINT `fk_usulan_sektor` FOREIGN KEY (`id_sektor`) REFERENCES `tb_sektor`(`id_sektor`),
  CONSTRAINT `fk_usulan_opd` FOREIGN KEY (`id_opd`) REFERENCES `tb_opd`(`id_opd`)
) ENGINE=InnoDB;

-- Rincian skor per kriteria. Bobot ikut disimpan agar hasil dapat ditelusuri
-- kembali walaupun bobot induk berubah di kemudian hari.
DROP TABLE IF EXISTS `tb_usulan_skor`;
CREATE TABLE `tb_usulan_skor` (
  `id_skor`     INT AUTO_INCREMENT PRIMARY KEY,
  `id_usulan`   INT NOT NULL,
  `id_kriteria` INT NOT NULL,
  `skor`        TINYINT NOT NULL,
  `bobot`       DECIMAL(6,2) NOT NULL,
  `skala_maks`  TINYINT NOT NULL DEFAULT 5,
  `nilai`       DECIMAL(6,2) NOT NULL,
  UNIQUE KEY `uq_usulan_kriteria` (`id_usulan`,`id_kriteria`),
  CONSTRAINT `fk_skor_usulan` FOREIGN KEY (`id_usulan`) REFERENCES `tb_usulan`(`id_usulan`) ON DELETE CASCADE,
  CONSTRAINT `fk_skor_kriteria` FOREIGN KEY (`id_kriteria`) REFERENCES `tb_kriteria`(`id_kriteria`)
) ENGINE=InnoDB;

-- Penilaian antar-desa: setiap desa menilai usulan desa lain di kecamatannya.
-- Nilai akhir usulan = rata-rata seluruh penilaian desa.
DROP TABLE IF EXISTS `tb_penilaian_skor`;
DROP TABLE IF EXISTS `tb_penilaian`;
CREATE TABLE IF NOT EXISTS `tb_penilaian` (
  `id_penilaian`    INT AUTO_INCREMENT PRIMARY KEY,
  `id_usulan`       INT NOT NULL,
  `id_desa_penilai` INT NOT NULL,
  `id_user`         INT DEFAULT NULL,
  `total_nilai`     DECIMAL(6,2) NOT NULL,
  `catatan`         VARCHAR(500) DEFAULT NULL,
  `dibuat_pada`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diubah_pada`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_usulan_desa` (`id_usulan`,`id_desa_penilai`),
  KEY `idx_penilai` (`id_desa_penilai`),
  KEY `idx_waktu` (`diubah_pada`),
  CONSTRAINT `fk_nilai_usulan` FOREIGN KEY (`id_usulan`) REFERENCES `tb_usulan`(`id_usulan`) ON DELETE CASCADE,
  CONSTRAINT `fk_nilai_desa` FOREIGN KEY (`id_desa_penilai`) REFERENCES `tb_desa`(`id_desa`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tb_penilaian_skor` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `id_penilaian` INT NOT NULL,
  `id_kriteria`  INT NOT NULL,
  `skor`         TINYINT NOT NULL,
  `bobot`        DECIMAL(6,2) NOT NULL,
  `skala_maks`   TINYINT NOT NULL DEFAULT 5,
  `nilai`        DECIMAL(6,2) NOT NULL,
  UNIQUE KEY `uq_penilaian_kriteria` (`id_penilaian`,`id_kriteria`),
  CONSTRAINT `fk_nskor_penilaian` FOREIGN KEY (`id_penilaian`) REFERENCES `tb_penilaian`(`id_penilaian`) ON DELETE CASCADE,
  CONSTRAINT `fk_nskor_kriteria` FOREIGN KEY (`id_kriteria`) REFERENCES `tb_kriteria`(`id_kriteria`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. Jejak audit
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tb_log`;
CREATE TABLE `tb_log` (
  `id_log`    INT AUTO_INCREMENT PRIMARY KEY,
  `id_user`   INT DEFAULT NULL,
  `username`  VARCHAR(50) DEFAULT NULL,
  `aksi`      VARCHAR(50) NOT NULL,
  `objek`     VARCHAR(120) DEFAULT NULL,
  `keterangan` TEXT,
  `ip`        VARCHAR(45) DEFAULT NULL,
  `waktu`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_waktu` (`waktu`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. Contoh usulan (sesuai baris contoh pada lembar kerja)
-- ---------------------------------------------------------------------
INSERT INTO `tb_usulan`
(`kode_usulan`,`tahun`,`id_kecamatan`,`desa`,`id_sumber`,`id_sektor`,`id_opd`,
 `nama_usulan`,`uraian_masalah`,`volume`,`satuan`,`estimasi_anggaran`,
 `nilai_awal`,`total_nilai`,`id_kategori`,`status`,`id_user`)
VALUES
('USL-2027-PAGIMANA-0001',2027,
 (SELECT id_kecamatan FROM tb_kecamatan WHERE nama='Pagimana'),
 'Pisou',
 (SELECT id_sumber FROM tb_sumber_usulan WHERE nama='Desa/Kelurahan'),
 (SELECT id_sektor FROM tb_sektor WHERE nama='Infrastruktur'),
 (SELECT id_opd FROM tb_opd WHERE nama='Dinas PUPR'),
 'Pembangunan Jalan','Tidak ada jalan penghubung (buntu/rusak)',
 1000,'Meter',200000000,
 72.00,72.00,(SELECT id_kategori FROM tb_kategori WHERE nama='Prioritas Sedang'),
 'Draft',1);

INSERT INTO `tb_usulan_skor` (`id_usulan`,`id_kriteria`,`skor`,`bobot`,`skala_maks`,`nilai`) VALUES
(1,1,5,25.00,5,25.00),
(1,2,3,20.00,5,12.00),
(1,3,3,15.00,5,9.00),
(1,4,2,20.00,5,8.00),
(1,5,4,10.00,5,8.00),
(1,6,5,10.00,5,10.00);


-- ---------------------------------------------------------------------
-- Kamus usulan dan 5 prioritas per desa
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `tb_kamus_usulan`;
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

-- ---------------------------------------------------------------------
-- Evaluasi program GERBANG (RPJMD 2025-2029)
-- ---------------------------------------------------------------------

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

SET FOREIGN_KEY_CHECKS = 1;
