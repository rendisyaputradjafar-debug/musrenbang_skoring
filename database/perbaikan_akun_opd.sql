-- =====================================================================
-- PERBAIKAN: AKUN PERANGKAT DAERAH (USER OPD) TIDAK BISA DIPILIH
-- Berkas ini membuat akun untuk SETIAP perangkat daerah yang aktif.
-- Aman dijalankan berulang kali. Impor di phpMyAdmin pada db_musrenbang.
-- =====================================================================
USE `db_musrenbang`;

-- 1. Pastikan peran dan kolom OPD tersedia
ALTER TABLE `tb_user`
  MODIFY `role` ENUM('admin','verifikator','operator','desa','opd') NOT NULL DEFAULT 'operator';
ALTER TABLE `tb_user` ADD COLUMN IF NOT EXISTS `id_opd` INT DEFAULT NULL;

-- 2. Buat akun untuk perangkat daerah yang belum punya. Sandi awal: opd123
INSERT IGNORE INTO `tb_user` (`username`,`password`,`nama_lengkap`,`jabatan`,`role`,`id_opd`)
SELECT CONCAT('opd_', LOWER(REPLACE(REPLACE(REPLACE(o.`nama`,' ','_'),'.',''),'-','_'))),
       '$2y$10$G37e8a9vnuaWunXKDCRDIeOf9vtXHG7NEd0DIrLVjmZdocytErVdK',
       o.`nama`, 'Operator Perangkat Daerah', 'opd', o.`id_opd`
FROM `tb_opd` o
WHERE o.`aktif` = 1
  AND NOT EXISTS (SELECT 1 FROM `tb_user` u WHERE u.id_opd = o.id_opd);

-- 3. Pemeriksaan hasil: jumlah OPD, akun OPD, dan pemetaan GERBANG
SELECT
  (SELECT COUNT(*) FROM `tb_opd` WHERE `aktif` = 1)                 AS perangkat_daerah_aktif,
  (SELECT COUNT(*) FROM `tb_user` WHERE `role` = 'opd' AND `aktif` = 1) AS akun_opd_aktif,
  (SELECT COUNT(*) FROM `tb_gerbang`)                                AS program_gerbang,
  (SELECT COUNT(*) FROM `tb_gerbang_opd`)                            AS pemetaan_gerbang_opd,
  (SELECT COUNT(*) FROM `tb_gerbang_indikator`)                      AS indikator;

-- 4. Daftar akun yang bisa dipakai login (pilihan "User OPD")
SELECT u.`username`, o.`nama` AS perangkat_daerah
FROM `tb_user` u JOIN `tb_opd` o ON o.`id_opd` = u.`id_opd`
WHERE u.`role` = 'opd' ORDER BY o.`nama`;
