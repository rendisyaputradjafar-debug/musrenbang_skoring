-- =====================================================================
-- DATA RESMI KECAMATAN DAN DESA/KELURAHAN KABUPATEN BANGGAI
-- Sumber: Book1D.pdf  |  24 kecamatan, 291 desa, 46 kelurahan
--
-- Untuk basis data yang SUDAH terpasang. Berkas ini berdiri sendiri:
-- tabel desa dan akun kecamatan ikut dibuat bila belum ada.
-- Data usulan tidak diubah. Aman dijalankan lebih dari sekali.
-- =====================================================================
USE `db_musrenbang`;

-- 0. Tabel desa (dibuat bila belum ada)
CREATE TABLE IF NOT EXISTS `tb_desa` (
  `id_desa`      INT AUTO_INCREMENT PRIMARY KEY,
  `id_kecamatan` INT NOT NULL,
  `nama`         VARCHAR(100) NOT NULL,
  `jenis`        ENUM('Desa','Kelurahan') NOT NULL DEFAULT 'Desa',
  `aktif`        TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `uq_kec_desa` (`id_kecamatan`,`nama`),
  CONSTRAINT `fk_desa_kec` FOREIGN KEY (`id_kecamatan`) REFERENCES `tb_kecamatan`(`id_kecamatan`)
) ENGINE=InnoDB;

-- 1. Kecamatan Toili Jaya belum ada pada data awal
INSERT INTO `tb_kecamatan` (`nama`)
SELECT 'Toili Jaya' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `tb_kecamatan` WHERE `nama` = 'Toili Jaya');

-- 2. Akun operator untuk kecamatan yang belum punya akun (termasuk Toili Jaya)
--    Nama pengguna kec_toili_jaya, sandi awal kecamatan123
INSERT IGNORE INTO `tb_user` (`username`,`password`,`nama_lengkap`,`jabatan`,`role`,`id_kecamatan`)
SELECT CONCAT('kec_', LOWER(REPLACE(k.`nama`,' ','_'))),
       '$2y$10$s6J9Qx2izv5/1ot.GrNcJe1Jn9I9mI1f.q4ZxRt/zSMhexRCwgHLO',
       CONCAT('Operator Kecamatan ', k.`nama`), 'Operator Kecamatan', 'operator', k.`id_kecamatan`
FROM `tb_kecamatan` k
WHERE NOT EXISTS (SELECT 1 FROM `tb_user` u WHERE u.`username` = CONCAT('kec_', LOWER(REPLACE(k.`nama`,' ','_'))));

-- 3. Desa dan kelurahan per kecamatan
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
