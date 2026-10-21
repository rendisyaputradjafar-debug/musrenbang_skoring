-- =====================================================================
-- PEMBARUAN basis data yang SUDAH terpasang (tanpa menghapus data usulan)
-- Impor berkas ini melalui phpMyAdmin pada basis data db_musrenbang.
-- Aman dijalankan lebih dari sekali.
-- =====================================================================
USE `db_musrenbang`;

CREATE TABLE IF NOT EXISTS `tb_desa` (
  `id_desa`      INT AUTO_INCREMENT PRIMARY KEY,
  `id_kecamatan` INT NOT NULL,
  `nama`         VARCHAR(100) NOT NULL,
  `jenis`        ENUM('Desa','Kelurahan') NOT NULL DEFAULT 'Desa',
  `aktif`        TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `uq_kec_desa` (`id_kecamatan`,`nama`),
  CONSTRAINT `fk_desa_kec` FOREIGN KEY (`id_kecamatan`) REFERENCES `tb_kecamatan`(`id_kecamatan`)
) ENGINE=InnoDB;

-- Akun operator untuk setiap kecamatan.
-- Nama pengguna: kec_<nama kecamatan>, contoh kec_luwuk_selatan. Sandi awal: kecamatan123
INSERT IGNORE INTO `tb_user` (`username`,`password`,`nama_lengkap`,`jabatan`,`role`,`id_kecamatan`)
SELECT CONCAT('kec_', LOWER(REPLACE(`nama`,' ','_'))),
       '$2y$10$s6J9Qx2izv5/1ot.GrNcJe1Jn9I9mI1f.q4ZxRt/zSMhexRCwgHLO',
       CONCAT('Operator Kecamatan ', `nama`), 'Operator Kecamatan', 'operator', `id_kecamatan`
FROM `tb_kecamatan`;
