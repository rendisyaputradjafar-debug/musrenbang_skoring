-- =====================================================================
-- PEMBARUAN: PENILAIAN ANTAR-DESA (MULTI USER)
-- Untuk basis data yang SUDAH terpasang (XAMPP/MariaDB). Data usulan tidak hilang.
-- Aman dijalankan lebih dari sekali.
--
-- Syarat: tabel tb_desa sudah berisi data (impor data_kecamatan_desa.sql lebih dulu).
-- =====================================================================
USE `db_musrenbang`;

-- 1. Peran baru "desa" dan kaitan akun ke desa
ALTER TABLE `tb_user`
  MODIFY `role` ENUM('admin','verifikator','operator','desa') NOT NULL DEFAULT 'operator';
ALTER TABLE `tb_user`
  ADD COLUMN IF NOT EXISTS `id_desa` INT DEFAULT NULL AFTER `id_kecamatan`;

-- 2. Nilai awal dan jumlah desa penilai pada usulan
ALTER TABLE `tb_usulan`
  ADD COLUMN IF NOT EXISTS `nilai_awal` DECIMAL(6,2) DEFAULT NULL AFTER `estimasi_anggaran`,
  ADD COLUMN IF NOT EXISTS `jumlah_penilai` INT NOT NULL DEFAULT 0 AFTER `nilai_awal`;

-- Nilai lama (dari form/impor) menjadi nilai awal. Hanya untuk usulan yang belum dinilai desa.
UPDATE `tb_usulan` SET `nilai_awal` = `total_nilai`
WHERE `nilai_awal` IS NULL AND `jumlah_penilai` = 0 AND `total_nilai` IS NOT NULL;

-- 3. Tabel penilaian antar-desa
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

-- 4. Akun untuk setiap desa/kelurahan
--    Nama pengguna: <kecamatan>.<desa>, contoh pagimana.pisou. Sandi awal: desa123
--    Di halaman login cukup pilih kecamatan dan desa, lalu isi sandi.
INSERT IGNORE INTO `tb_user` (`username`,`password`,`nama_lengkap`,`jabatan`,`role`,`id_kecamatan`,`id_desa`)
SELECT CONCAT(LOWER(REPLACE(REPLACE(REPLACE(REPLACE(k.`nama`,' ','_'),'-','_'),'''',''),'.','')), '.', LOWER(REPLACE(REPLACE(REPLACE(REPLACE(d.`nama`,' ','_'),'-','_'),'''',''),'.',''))),
       '$2y$10$1mkAEtDsTsuWENv3bHSAxO4KO1RbV3caz.PncCpVth013qS.cthN.',
       CONCAT(d.`jenis`, ' ', d.`nama`, ', Kec. ', k.`nama`),
       CONCAT('Operator ', d.`jenis`), 'desa', d.`id_kecamatan`, d.`id_desa`
FROM `tb_desa` d
JOIN `tb_kecamatan` k ON k.`id_kecamatan` = d.`id_kecamatan`
WHERE d.`aktif` = 1
  AND NOT EXISTS (SELECT 1 FROM `tb_user` u WHERE u.`id_desa` = d.`id_desa`);
