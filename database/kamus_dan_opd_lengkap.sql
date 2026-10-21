-- =====================================================================
-- PELENGKAP: SELURUH PERANGKAT DAERAH DAN KAMUS USULAN LENGKAP
-- Menambah 36 perangkat daerah beserta akunnya, dan 139 entri kamus usulan
-- yang sudah dikaitkan ke sektor, OPD, dan program GERBANG.
-- Aman dijalankan berulang kali. Entri yang sudah ada tidak diduplikasi.
-- =====================================================================
USE `db_musrenbang`;

-- 1. Perangkat daerah
DROP TEMPORARY TABLE IF EXISTS `tmp_opd`;
CREATE TEMPORARY TABLE `tmp_opd` (nama VARCHAR(150));
INSERT INTO `tmp_opd` VALUES
('Sekretariat Daerah'),('Sekretariat DPRD'),('Inspektorat'),('Bappeda'),('BPKAD'),('Bappenda'),('BKPSDM'),('BRIDA'),('Dinas Pendidikan'),('Dinas Kesehatan'),('RSUD Luwuk'),('Dinas PUPR'),('Dinas Perkimtan'),('Satpol PP dan Damkar'),('Dinas Sosial'),('Dinas Tenaga Kerja'),('P2KBP3A'),('Dinas Ketahanan Pangan'),('DLH'),('Dinas Dukcapil'),('Dinas PMD'),('Dinas Perhubungan'),('Diskominfo'),('Dinas Koperasi dan UMKM'),('Dinas PTSP'),('Dinas Pemuda dan Olahraga'),('Dinas Perpustakaan dan Kearsipan'),('Dinas Perikanan'),('Dinas Pariwisata'),('Dinas TPHP'),('Dinas Peternakan'),('Dinas Perdagangan'),('Dinas Perindag'),('Badan Kesbangpol'),('BPBD'),('Dinas lainnya');
INSERT INTO `tb_opd` (`nama`) SELECT t.nama FROM `tmp_opd` t
WHERE NOT EXISTS (SELECT 1 FROM `tb_opd` o WHERE o.nama = t.nama);
DROP TEMPORARY TABLE `tmp_opd`;

-- Semua perangkat daerah diaktifkan agar dapat dipilih
UPDATE `tb_opd` SET `aktif` = 1 WHERE `aktif` = 0;

-- 2. Akun untuk setiap perangkat daerah. Sandi awal: opd123
INSERT IGNORE INTO `tb_user` (`username`,`password`,`nama_lengkap`,`jabatan`,`role`,`id_opd`)
SELECT CONCAT('opd_', LOWER(REPLACE(REPLACE(REPLACE(o.`nama`,' ','_'),'.',''),'-','_'))),
       '$2y$10$G37e8a9vnuaWunXKDCRDIeOf9vtXHG7NEd0DIrLVjmZdocytErVdK',
       o.`nama`, 'Operator Perangkat Daerah', 'opd', o.`id_opd`
FROM `tb_opd` o
WHERE o.`aktif` = 1 AND NOT EXISTS (SELECT 1 FROM `tb_user` u WHERE u.id_opd = o.id_opd);

-- 3. Kamus usulan
DROP TEMPORARY TABLE IF EXISTS `tmp_kamus`;
CREATE TEMPORARY TABLE `tmp_kamus` (kode VARCHAR(20), nama VARCHAR(200), masalah VARCHAR(255),
                                    sektor VARCHAR(100), opd VARCHAR(150), satuan VARCHAR(30), gerbang VARCHAR(10));
INSERT INTO `tmp_kamus` VALUES
('INF-101','Pembangunan Jalan Baru','Belum ada jalan menuju permukiman atau lahan warga','Infrastruktur','Dinas PUPR','Meter','G8'),
('INF-102','Peningkatan Jalan Kabupaten','Jalan kabupaten rusak dan berlubang','Infrastruktur','Dinas PUPR','Meter','G8'),
('INF-103','Pengaspalan Jalan Desa','Jalan desa masih tanah dan sulit dilalui','Infrastruktur','Dinas PUPR','Meter','G8'),
('INF-104','Rabat Beton Jalan Lingkungan','Jalan lingkungan becek saat hujan','Infrastruktur','Dinas Perkimtan','Meter','G8'),
('INF-105','Pembangunan Jalan Usaha Tani','Hasil pertanian sulit diangkut dari lahan','Infrastruktur','Dinas TPHP','Meter','G6'),
('INF-106','Pembangunan Jalan Setapak','Akses antar rumah warga belum memadai','Infrastruktur','Dinas Perkimtan','Meter','G8'),
('INF-107','Pembangunan Jembatan','Warga sulit menyeberang sungai','Infrastruktur','Dinas PUPR','Unit','G8'),
('INF-108','Rehabilitasi Jembatan','Jembatan rusak dan membahayakan pengguna','Infrastruktur','Dinas PUPR','Unit','G8'),
('INF-109','Pembangunan Gorong-Gorong','Air tidak mengalir dan merusak badan jalan','Infrastruktur','Dinas PUPR','Unit','G8'),
('INF-110','Pembangunan Drainase','Air menggenang saat hujan','Infrastruktur','Dinas PUPR','Meter','G8'),
('INF-111','Normalisasi Saluran Drainase','Saluran tersumbat sampah dan sedimen','Infrastruktur','Dinas PUPR','Meter','G8'),
('INF-112','Pembangunan Talud Jalan','Badan jalan rawan longsor','Infrastruktur','Dinas PUPR','Meter','G8'),
('INF-113','Pembangunan Tembok Penahan Tanah','Tebing di permukiman rawan longsor','Infrastruktur','Dinas PUPR','Meter','G8'),
('INF-114','Pembangunan Tanggul Sungai','Permukiman rawan banjir luapan sungai','Infrastruktur','Dinas PUPR','Meter','G8'),
('INF-115','Pembangunan Turap Pantai','Abrasi mengancam permukiman pesisir','Infrastruktur','Dinas PUPR','Meter','G8'),
('INF-116','Pembangunan Tambatan Perahu','Nelayan belum punya tempat sandar perahu','Infrastruktur','Dinas Perikanan','Unit','G6'),
('INF-117','Pembangunan Dermaga Desa','Akses transportasi laut antar desa terbatas','Infrastruktur','Dinas Perhubungan','Unit','G8'),
('INF-118','Pengadaan Rambu dan Marka Jalan','Jalan rawan kecelakaan karena minim rambu','Infrastruktur','Dinas Perhubungan','Unit','G8'),
('INF-119','Pembangunan Halte Angkutan','Penumpang menunggu angkutan tanpa tempat berteduh','Infrastruktur','Dinas Perhubungan','Unit','G8'),
('INF-120','Pembangunan Sarana Air Bersih Perpipaan','Warga kesulitan mendapatkan air bersih','Infrastruktur','Dinas Perkimtan','Unit','G8'),
('INF-121','Pembangunan Sumur Bor','Sumber air warga kering saat kemarau','Infrastruktur','Dinas Perkimtan','Unit','G8'),
('INF-122','Pembangunan Bak Penampung Air','Air bersih tidak tersedia sepanjang hari','Infrastruktur','Dinas Perkimtan','Unit','G8'),
('INF-123','Perbaikan Jaringan Pipa Air Bersih','Jaringan pipa bocor dan tidak mengalir','Infrastruktur','Dinas Perkimtan','Meter','G8'),
('INF-124','Pembangunan MCK Komunal','Sanitasi warga belum layak','Infrastruktur','Dinas Perkimtan','Unit','G8'),
('KES-101','Pembangunan Jamban Sehat Keluarga','Masih ada warga buang air besar sembarangan','Kesehatan','Dinas Kesehatan','Unit','G2'),
('LH-101','Pembangunan IPAL Komunal','Limbah rumah tangga dibuang tanpa pengolahan','Lingkungan Hidup','DLH','Unit','G8'),
('INF-125','Rehabilitasi Rumah Tidak Layak Huni','Masih ada rumah warga tidak layak huni','Infrastruktur','Dinas Perkimtan','Unit','G8'),
('INF-126','Penataan Kawasan Permukiman Kumuh','Permukiman padat dan tidak tertata','Infrastruktur','Dinas Perkimtan','Hektar','G8'),
('INF-127','Pembangunan Jalan Lingkungan Permukiman','Akses dalam kawasan permukiman rusak','Infrastruktur','Dinas Perkimtan','Meter','G8'),
('INF-128','Pemasangan Lampu Penerangan Jalan Umum','Jalan gelap dan rawan pada malam hari','Infrastruktur','Dinas Perhubungan','Titik','G7'),
('INF-129','Pemasangan Lampu Tenaga Surya','Lokasi belum terjangkau jaringan listrik','Infrastruktur','Dinas Perkimtan','Titik','G7'),
('INF-130','Perluasan Jaringan Listrik Desa','Masih ada rumah tangga belum teraliri listrik','Infrastruktur','Dinas Perkimtan','Unit','G7'),
('WIL-101','Pembangunan Tower Penguat Sinyal','Desa masih blank spot sinyal telekomunikasi','Kewilayahan','Diskominfo','Unit','G7'),
('WIL-102','Pengadaan Internet Desa','Pelayanan desa terhambat karena tanpa internet','Kewilayahan','Diskominfo','Titik','G7'),
('WIL-103','Pelatihan Literasi Digital Masyarakat','Kemampuan digital warga masih rendah','Kewilayahan','Diskominfo','Paket','G7'),
('PEM-101','Pengadaan Komputer Pelayanan Desa','Pelayanan administrasi desa masih manual','Pemerintahan','Dinas PMD','Unit','G7'),
('PEM-102','Pengembangan Sistem Informasi Desa','Data desa belum terkelola dengan baik','Pemerintahan','Dinas PMD','Paket','G7'),
('PDK-101','Rehabilitasi Ruang Kelas','Ruang kelas rusak dan tidak layak','Pendidikan','Dinas Pendidikan','Ruang','G1'),
('PDK-102','Pembangunan Ruang Kelas Baru','Jumlah ruang kelas tidak mencukupi','Pendidikan','Dinas Pendidikan','Ruang','G1'),
('PDK-103','Pembangunan Gedung PAUD','Belum ada gedung PAUD di desa','Pendidikan','Dinas Pendidikan','Unit','G1'),
('PDK-104','Pembangunan Perpustakaan Sekolah','Sekolah belum memiliki perpustakaan','Pendidikan','Dinas Perpustakaan dan Kearsipan','Unit','G1'),
('PDK-105','Pengadaan Meja dan Kursi Sekolah','Meja dan kursi sekolah kurang atau rusak','Pendidikan','Dinas Pendidikan','Paket','G1'),
('PDK-106','Pengadaan Buku dan Alat Peraga','Bahan ajar dan alat peraga terbatas','Pendidikan','Dinas Pendidikan','Paket','G1'),
('PDK-107','Pembangunan Pagar Sekolah','Keamanan lingkungan sekolah kurang terjaga','Pendidikan','Dinas Pendidikan','Meter','G1'),
('PDK-108','Pembangunan Toilet Sekolah','Sanitasi sekolah belum memadai','Pendidikan','Dinas Pendidikan','Unit','G1'),
('PDK-109','Pengadaan Sarana Pembelajaran Digital','Pembelajaran berbasis digital belum berjalan','Pendidikan','Dinas Pendidikan','Paket','G1'),
('PDK-110','Beasiswa Siswa dan Mahasiswa Kurang Mampu','Anak dari keluarga miskin terancam putus sekolah','Pendidikan','Dinas Pendidikan','Orang','G1'),
('PDK-111','Program Kembali ke Sekolah bagi Anak Putus Sekolah','Masih ada anak putus sekolah di desa','Pendidikan','Dinas Pendidikan','Orang','G1'),
('PDK-112','Pembangunan Perpustakaan Desa','Minat baca warga belum terfasilitasi','Pendidikan','Dinas Perpustakaan dan Kearsipan','Unit','G1'),
('PDK-113','Pelatihan Guru PAUD dan TK','Kompetensi tenaga pendidik perlu ditingkatkan','Pendidikan','Dinas Pendidikan','Orang','G1'),
('PDK-114','Pengadaan Seragam dan Perlengkapan Sekolah','Siswa kurang mampu tidak memiliki perlengkapan sekolah','Pendidikan','Dinas Sosial','Paket','G1'),
('KES-102','Pembangunan Posyandu','Kegiatan posyandu belum memiliki tempat','Kesehatan','Dinas Kesehatan','Unit','G2'),
('KES-103','Rehabilitasi Posyandu','Bangunan posyandu rusak','Kesehatan','Dinas Kesehatan','Unit','G2'),
('KES-104','Pembangunan Poskesdes','Desa belum memiliki pos kesehatan','Kesehatan','Dinas Kesehatan','Unit','G2'),
('KES-105','Rehabilitasi Puskesmas Pembantu','Bangunan pustu rusak','Kesehatan','Dinas Kesehatan','Unit','G2'),
('KES-106','Pengadaan Alat Kesehatan Puskesmas','Peralatan kesehatan terbatas','Kesehatan','Dinas Kesehatan','Paket','G2'),
('KES-107','Pengadaan Ambulans Desa','Rujukan pasien gawat darurat terlambat','Kesehatan','Dinas Kesehatan','Unit','G2'),
('KES-108','Pemberian Makanan Tambahan Balita','Masih ada balita gizi kurang dan stunting','Kesehatan','Dinas Kesehatan','Paket','G2'),
('KES-109','Pemberian Makanan Tambahan Ibu Hamil','Ibu hamil berisiko kurang energi kronis','Kesehatan','Dinas Kesehatan','Paket','G2'),
('KES-110','Penyuluhan Kesehatan dan Pola Hidup Sehat','Kesadaran hidup sehat warga masih rendah','Kesehatan','Dinas Kesehatan','Paket','G2'),
('KES-111','Pelatihan Kader Posyandu','Kapasitas kader posyandu perlu ditingkatkan','Kesehatan','Dinas Kesehatan','Orang','G2'),
('KES-112','Pengadaan Alat Posyandu dan Antropometri','Alat ukur tumbuh kembang anak belum lengkap','Kesehatan','Dinas Kesehatan','Paket','G2'),
('KES-113','Fasilitasi Jaminan Kesehatan Warga Miskin','Warga miskin belum terdaftar BPJS Kesehatan','Kesehatan','Dinas Kesehatan','Orang','G2'),
('KES-114','Pengadaan Sarana Air Minum Layak Rumah Tangga','Air minum warga belum memenuhi syarat kesehatan','Kesehatan','Dinas Kesehatan','Unit','G2'),
('KES-115','Pencegahan dan Pengendalian Penyakit Menular','Kasus penyakit menular masih ditemukan','Kesehatan','Dinas Kesehatan','Paket','G2'),
('KES-116','Pembangunan Sarana Olahraga Desa','Warga tidak memiliki tempat berolahraga','Kesehatan','Dinas Pemuda dan Olahraga','Unit','G2'),
('SOS-101','Pengadaan Peralatan Olahraga','Kegiatan olahraga pemuda terhambat alat','Sosial','Dinas Pemuda dan Olahraga','Paket','G2'),
('INF-131','Rehabilitasi Jaringan Irigasi','Saluran irigasi rusak sehingga sawah tidak teraliri','Infrastruktur','Dinas PUPR','Meter','G6'),
('INF-132','Pembangunan Embung Pertanian','Petani kekurangan air saat kemarau','Infrastruktur','Dinas TPHP','Unit','G6'),
('INF-133','Pembangunan Sumur Tani','Lahan pertanian tidak memiliki sumber air','Infrastruktur','Dinas TPHP','Unit','G6'),
('EKO-101','Bantuan Alat dan Mesin Pertanian','Petani kekurangan alat pengolah lahan','Ekonomi','Dinas TPHP','Unit','G6'),
('EKO-102','Bantuan Bibit Tanaman Pangan','Hasil panen petani masih rendah','Ekonomi','Dinas TPHP','Paket','G6'),
('EKO-103','Bantuan Pupuk dan Obat Tanaman','Harga pupuk memberatkan petani','Ekonomi','Dinas TPHP','Paket','G6'),
('EKO-104','Bantuan Bibit Kelapa Unggul','Produktivitas kebun kelapa menurun','Ekonomi','Dinas TPHP','Paket','G6'),
('EKO-105','Pengembangan Tanaman Hortikultura','Kebutuhan sayur dan buah masih dipasok dari luar','Ekonomi','Dinas TPHP','Paket','G6'),
('EKO-106','Pengadaan Alat Pengolah Hasil Pertanian','Hasil panen dijual tanpa pengolahan','Ekonomi','Dinas TPHP','Unit','G6'),
('EKO-107','Pembangunan Lumbung Pangan Desa','Cadangan pangan desa belum tersedia','Ekonomi','Dinas Ketahanan Pangan','Unit','G6'),
('EKO-108','Gerakan Pemanfaatan Pekarangan','Pekarangan warga belum dimanfaatkan produktif','Ekonomi','Dinas Ketahanan Pangan','Paket','G6'),
('EKO-109','Bantuan Ternak Sapi atau Kambing','Warga belum memiliki usaha peternakan','Ekonomi','Dinas Peternakan','Ekor','G6'),
('EKO-110','Bantuan Ternak Unggas','Pendapatan tambahan keluarga terbatas','Ekonomi','Dinas Peternakan','Ekor','G6'),
('EKO-111','Pembangunan Kandang Kelompok Ternak','Ternak belum dikandangkan dengan layak','Ekonomi','Dinas Peternakan','Unit','G6'),
('EKO-112','Pelayanan Kesehatan Hewan dan Vaksinasi','Penyakit ternak mengancam populasi','Ekonomi','Dinas Peternakan','Paket','G6'),
('EKO-113','Bantuan Alat Tangkap Nelayan','Alat tangkap nelayan terbatas atau rusak','Ekonomi','Dinas Perikanan','Paket','G6'),
('EKO-114','Bantuan Perahu dan Mesin Nelayan','Armada tangkap nelayan tidak layak','Ekonomi','Dinas Perikanan','Unit','G6'),
('EKO-115','Pengembangan Budidaya Ikan Air Tawar','Potensi budidaya ikan belum dimanfaatkan','Ekonomi','Dinas Perikanan','Paket','G6'),
('EKO-116','Pengembangan Budidaya Rumput Laut','Pendapatan pembudidaya masih rendah','Ekonomi','Dinas Perikanan','Paket','G6'),
('EKO-117','Pengadaan Cold Storage dan Pabrik Es','Hasil tangkapan cepat rusak','Ekonomi','Dinas Perikanan','Unit','G6'),
('EKO-118','Pelatihan Pengolahan Hasil Perikanan','Nilai tambah hasil perikanan masih rendah','Ekonomi','Dinas Perikanan','Paket','G6'),
('EKO-119','Penyuluhan dan Pendampingan Petani','Petani belum menerapkan teknologi budidaya','Ekonomi','Dinas TPHP','Paket','G6'),
('EKO-120','Pelatihan Keterampilan Kerja','Pencari kerja belum memiliki keterampilan','Ekonomi','Dinas Tenaga Kerja','Orang','G3'),
('EKO-121','Pelatihan Menjahit dan Tata Busana','Perempuan desa belum punya keterampilan usaha','Ekonomi','Dinas Tenaga Kerja','Orang','G3'),
('EKO-122','Pelatihan Montir dan Las','Pemuda desa menganggur','Ekonomi','Dinas Tenaga Kerja','Orang','G3'),
('EKO-123','Pelatihan Pengolahan Makanan','Produk olahan rumah tangga belum berkembang','Ekonomi','Dinas Koperasi dan UMKM','Orang','G3'),
('EKO-124','Bantuan Modal Usaha Mikro','Pelaku usaha kecil kekurangan modal','Ekonomi','Dinas Koperasi dan UMKM','Paket','G3'),
('EKO-125','Bantuan Peralatan Usaha Mikro','Peralatan usaha warga tidak memadai','Ekonomi','Dinas Koperasi dan UMKM','Paket','G3'),
('EKO-126','Fasilitasi Perizinan dan Sertifikasi Produk','Produk UMKM belum memiliki izin edar','Ekonomi','Dinas PTSP','Paket','G3'),
('EKO-127','Pelatihan Pemasaran Digital UMKM','Produk desa sulit menjangkau pembeli','Ekonomi','Dinas Koperasi dan UMKM','Orang','G3'),
('EKO-128','Pembangunan Pasar Desa','Belum ada tempat jual beli yang layak','Ekonomi','Dinas Perdagangan','Unit','G3'),
('EKO-129','Rehabilitasi Pasar Desa','Bangunan pasar rusak dan tidak tertata','Ekonomi','Dinas Perdagangan','Unit','G3'),
('EKO-130','Pengembangan Industri Kreatif Desa','Potensi kerajinan lokal belum berkembang','Ekonomi','Dinas Perindag','Paket','G3'),
('SOS-102','Bantuan Sosial bagi Lansia dan Disabilitas','Warga rentan belum mendapat bantuan','Sosial','Dinas Sosial','Orang','G3'),
('SOS-103','Pendataan dan Verifikasi Warga Miskin','Data penerima bantuan belum akurat','Sosial','Dinas Sosial','Paket','G3'),
('SOS-104','Fasilitasi BPJS Ketenagakerjaan Pekerja Rentan','Pekerja informal belum terlindungi jaminan sosial','Sosial','Dinas Tenaga Kerja','Orang','G3'),
('SOS-105','Pemberdayaan Perempuan Kepala Keluarga','Perempuan kepala keluarga berpenghasilan rendah','Sosial','P2KBP3A','Orang','G3'),
('SOS-106','Pelatihan Kewirausahaan Pemuda','Pemuda desa belum memiliki usaha mandiri','Sosial','Dinas Pemuda dan Olahraga','Orang','G3'),
('SOS-107','Sosialisasi Pencegahan Kekerasan Perempuan dan Anak','Kasus kekerasan masih terjadi di masyarakat','Sosial','P2KBP3A','Paket','G3'),
('SOS-108','Pengembangan Desa Layak Anak','Pemenuhan hak anak belum optimal','Sosial','P2KBP3A','Paket','G3'),
('LH-102','Pengadaan Sarana Persampahan','Sampah belum terkelola dengan baik','Lingkungan Hidup','DLH','Unit','G8'),
('LH-103','Pembangunan Tempat Pembuangan Sampah Sementara','Warga membuang sampah sembarangan','Lingkungan Hidup','DLH','Unit','G8'),
('LH-104','Pembangunan Bank Sampah Desa','Sampah bernilai ekonomi belum dipilah','Lingkungan Hidup','DLH','Unit','G8'),
('LH-105','Pengadaan Motor Sampah','Pengangkutan sampah belum tersedia','Lingkungan Hidup','DLH','Unit','G8'),
('LH-106','Penghijauan dan Penanaman Pohon','Lahan kritis dan rawan longsor meluas','Lingkungan Hidup','DLH','Paket','G8'),
('LH-107','Penanaman Mangrove Pesisir','Pantai mengalami abrasi','Lingkungan Hidup','DLH','Hektar','G8'),
('LH-108','Pengadaan Peralatan Kebencanaan Desa','Desa belum siap menghadapi bencana','Lingkungan Hidup','BPBD','Paket','G8'),
('LH-109','Pelatihan Desa Tangguh Bencana','Warga belum memahami mitigasi bencana','Lingkungan Hidup','BPBD','Paket','G8'),
('LH-110','Pengadaan Alat Pemadam Kebakaran Desa','Penanganan kebakaran terlambat','Lingkungan Hidup','Satpol PP dan Damkar','Paket','G8'),
('LH-111','Normalisasi Sungai','Pendangkalan sungai menyebabkan banjir','Lingkungan Hidup','Dinas PUPR','Meter','G8'),
('EKO-131','Pengembangan Desa Wisata','Potensi wisata desa belum dikelola','Ekonomi','Dinas Pariwisata','Paket','G4'),
('EKO-132','Pembangunan Sarana Objek Wisata','Objek wisata belum memiliki fasilitas dasar','Ekonomi','Dinas Pariwisata','Unit','G4'),
('EKO-133','Pelatihan Kelompok Sadar Wisata','Pengelolaan wisata belum profesional','Ekonomi','Dinas Pariwisata','Orang','G4'),
('SOS-109','Pelestarian Seni dan Budaya Daerah','Kesenian tradisional mulai ditinggalkan','Sosial','Dinas Pendidikan','Paket','G4'),
('SOS-110','Pengadaan Alat Kesenian Tradisional','Sanggar seni kekurangan peralatan','Sosial','Dinas Pendidikan','Paket','G4'),
('SOS-111','Rehabilitasi Rumah Ibadah','Bangunan rumah ibadah rusak','Sosial','Sekretariat Daerah','Unit','G4'),
('SOS-112','Pembangunan Sanggar Budaya Desa','Kegiatan budaya belum punya tempat','Sosial','Dinas Pendidikan','Unit','G4'),
('SOS-113','Pembinaan Wawasan Kebangsaan dan Moderasi Beragama','Potensi konflik sosial perlu dicegah','Sosial','Badan Kesbangpol','Paket','G4'),
('PEM-103','Pembangunan Kantor Desa','Kantor desa belum tersedia atau tidak layak','Pemerintahan','Dinas PMD','Unit','G5'),
('PEM-104','Rehabilitasi Kantor Desa','Bangunan kantor desa rusak','Pemerintahan','Dinas PMD','Unit','G5'),
('PEM-105','Pembangunan Balai Pertemuan Desa','Musyawarah warga belum punya tempat','Pemerintahan','Dinas PMD','Unit','G5'),
('PEM-106','Pengadaan Meubelair Kantor Desa','Perabot kantor desa tidak memadai','Pemerintahan','Dinas PMD','Paket','G5'),
('EKO-134','Penguatan BUMDes','BUMDes belum berjalan optimal','Ekonomi','Dinas PMD','Paket','G5'),
('EKO-135','Pelatihan Pengurus BUMDes','Pengelola BUMDes belum terlatih','Ekonomi','Dinas PMD','Orang','G5'),
('EKO-136','Fasilitasi Pembentukan Koperasi Desa','Usaha bersama warga belum berbadan hukum','Ekonomi','Dinas Koperasi dan UMKM','Paket','G5'),
('PEM-107','Pelatihan Aparatur Desa','Kemampuan aparatur desa perlu ditingkatkan','Pemerintahan','Dinas PMD','Orang','G5'),
('PEM-108','Penyusunan Dokumen Perencanaan Desa','Perencanaan desa belum tertib','Pemerintahan','Dinas PMD','Paket','G9'),
('PEM-109','Pelayanan Administrasi Kependudukan Keliling','Warga jauh dari tempat pelayanan dokumen','Pemerintahan','Dinas Dukcapil','Paket','G9'),
('PEM-110','Pembangunan Pos Keamanan Lingkungan','Keamanan lingkungan belum terjaga','Pemerintahan','Satpol PP dan Damkar','Unit','G9'),
('PEM-111','Pembangunan Pagar dan Penataan Kantor Kecamatan','Sarana pelayanan kecamatan belum memadai','Pemerintahan','Sekretariat Daerah','Paket','G9'),
('PEM-112','Pengadaan Sarana Pelayanan Publik Kecamatan','Pelayanan di kecamatan belum optimal','Pemerintahan','Sekretariat Daerah','Paket','G9'),
('PEM-113','Penataan Arsip dan Dokumen Desa','Arsip desa belum tertata','Pemerintahan','Dinas Perpustakaan dan Kearsipan','Paket','G9');

INSERT IGNORE INTO `tb_kamus_usulan` (`kode`,`nama_usulan`,`permasalahan`,`id_sektor`,`id_opd`,`satuan`,`id_gerbang`)
SELECT t.kode, t.nama, t.masalah, s.id_sektor, o.id_opd, t.satuan, g.id_gerbang
FROM `tmp_kamus` t
JOIN `tb_sektor` s      ON s.nama = t.sektor
JOIN `tb_opd` o         ON o.nama = t.opd
LEFT JOIN `tb_gerbang` g ON g.kode = t.gerbang
WHERE NOT EXISTS (SELECT 1 FROM `tb_kamus_usulan` k
                  WHERE k.nama_usulan = t.nama AND k.permasalahan = t.masalah);
DROP TEMPORARY TABLE `tmp_kamus`;

-- 4. Usulan mengikuti kaitan GERBANG dari kamusnya
--    (entri kamus lama yang belum terkait dapat diatur di menu Master > Kamus usulan)
UPDATE `tb_usulan` u JOIN `tb_kamus_usulan` k ON k.id_kamus = u.id_kamus
SET u.id_gerbang = k.id_gerbang WHERE u.id_gerbang IS NULL AND k.id_gerbang IS NOT NULL;

-- 5. Pemeriksaan hasil
SELECT (SELECT COUNT(*) FROM `tb_opd` WHERE aktif = 1)                      AS perangkat_daerah,
       (SELECT COUNT(*) FROM `tb_user` WHERE role = 'opd' AND aktif = 1)    AS akun_opd,
       (SELECT COUNT(*) FROM `tb_kamus_usulan` WHERE aktif = 1)             AS entri_kamus,
       (SELECT COUNT(*) FROM `tb_kamus_usulan` WHERE id_gerbang IS NOT NULL) AS kamus_terkait_gerbang;
