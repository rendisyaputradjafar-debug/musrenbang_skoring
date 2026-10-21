<?php
// ==========================================
// KONEKSI DATABASE PDO (db_musrenbang)
// ==========================================
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'db_musrenbang';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$port = getenv('DB_PORT') ?: '3306';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Skeleton Query untuk mengambil data kecamatan dinamis dari database (hilangkan comment jika tabel sudah siap)
    // $stmt_kec = $pdo->query("SELECT id_kecamatan, nama_kecamatan FROM tabel_kecamatan ORDER BY nama_kecamatan ASC");
    // $kecamatan_list = $stmt_kec->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    // Tangani error koneksi secara senyap atau tampilkan alert
    // die("Koneksi Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Musrenbang Skoring</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome untuk Ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* CSS Variabel untuk Tema Modern */
        :root {
            --primary-color: #0ea5e9;
            --secondary-color: #3b82f6;
            --dark-color: #0f172a;
            --bg-gradient: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            color: #334155;
        }

        /* Navigasi Modern */
        .navbar {
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.03);
            padding: 15px 0;
        }

        .navbar-brand {
            font-weight: 800;
            color: var(--dark-color) !important;
            letter-spacing: 0.5px;
        }

        .navbar-brand span {
            color: var(--primary-color);
        }

        .nav-link {
            font-weight: 500;
            margin: 0 10px;
            position: relative;
            transition: color 0.3s;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary-color) !important;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary-color);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        /* Tombol Login Gradient */
        .btn-login {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white !important;
            border: none;
            border-radius: 50px;
            padding: 10px 28px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.4);
        }

        /* Hero Section */
        .hero-section {
            padding: 80px 0 60px;
            text-align: center;
        }

        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(to right, var(--dark-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        /* Filter Panel (Glassmorphism Effect) */
        .filter-panel {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            margin-top: 30px;
        }

        .form-select, .form-control {
            border-radius: 12px;
            padding: 14px 20px;
            border: 1px solid #cbd5e1;
            background-color: rgba(255,255,255,0.9);
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(14, 165, 233, 0.15);
        }

        .btn-filter {
            background: var(--dark-color);
            color: white;
            border-radius: 12px;
            padding: 14px;
            width: 100%;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-filter:hover {
            background: var(--primary-color);
            color: white;
            transform: scale(1.02);
        }

        /* Data Card Design */
        .skoring-card {
            background: #fff;
            border: none;
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            margin-bottom: 25px;
        }
        
        .skoring-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-chart-line me-2 text-primary"></i>
                E-Musrenbang<span>Skoring</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="fas fa-home me-1"></i> Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#view-filter"><i class="fas fa-layer-group me-1"></i> View Usulan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-chart-pie me-1"></i> Analisis & Statistik</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-book me-1"></i> Panduan Indikator</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="login.php" class="btn btn-login"><i class="fas fa-sign-in-alt me-2"></i>Login Admin</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero & Filter Section -->
    <div class="container hero-section">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h1 class="hero-title">Sistem Digitalisasi Skoring<br>Perencanaan Pembangunan</h1>
                <p class="lead text-secondary mb-5">Transparansi, Akuntabilitas, dan Percepatan Penilaian Usulan Musrenbang Terintegrasi</p>
                
                <!-- Box Panel Filter View -->
                <div class="filter-panel" id="view-filter">
                    <div class="d-flex align-items-center mb-4">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                            <i class="fas fa-search"></i>
                        </div>
                        <h4 class="mb-0 fw-bold text-dark">Filter View Data Musrenbang</h4>
                    </div>

                    <form action="" method="GET" class="row g-3">
                        <!-- Filter Kecamatan -->
                        <div class="col-md-5">
                            <label class="form-label text-muted fw-semibold small ps-1">Pilih Kecamatan</label>
                            <select name="kecamatan" id="kecamatan" class="form-select" onchange="loadDesa()">
                                <option value="" selected disabled>-- Menampilkan Semua Kecamatan --</option>
                                <!-- Placeholder Statis (Nanti di-loop dari PHP $kecamatan_list) -->
                                <option value="Luwuk">Luwuk</option>
                                <option value="Luwuk Selatan">Luwuk Selatan</option>
                                <option value="Luwuk Utara">Luwuk Utara</option>
                                <option value="Batui">Batui</option>
                                <option value="Kintom">Kintom</option>
                            </select>
                        </div>
                        
                        <!-- Filter Desa (Bergantung pada Kecamatan) -->
                        <div class="col-md-5">
                            <label class="form-label text-muted fw-semibold small ps-1">Pilih Desa/Kelurahan</label>
                            <select name="desa" id="desa" class="form-select">
                                <option value="" selected disabled>-- Pilih Kecamatan Terlebih Dahulu --</option>
                            </select>
                        </div>

                        <!-- Tombol Cari -->
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-filter shadow-sm">
                                Tampilkan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Container Hasil View Data (Contoh Layout Modern) -->
    <div class="container mb-5 pb-5">
        <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-list text-primary me-2"></i>Preview Hasil Usulan Prioritas</h5>
        
        <div class="row">
            <!-- Contoh Item 1 -->
            <div class="col-lg-4 col-md-6">
                <div class="skoring-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">Infrastruktur</span>
                        <h5 class="fw-bold text-success mb-0"><i class="fas fa-star text-warning me-1"></i> 95 Poin</h5>
                    </div>
                    <h5 class="fw-bold text-dark mt-3">Peningkatan Jalan Lingkar</h5>
                    <p class="text-secondary small mb-3">
                        <i class="fas fa-map-marker-alt me-2 text-danger"></i>Kec. Luwuk Selatan, Kel. Maahas
                    </p>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: 95%" aria-valuenow="95" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>

            <!-- Contoh Item 2 -->
            <div class="col-lg-4 col-md-6">
                <div class="skoring-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill">Ekonomi</span>
                        <h5 class="fw-bold text-success mb-0"><i class="fas fa-star text-warning me-1"></i> 88 Poin</h5>
                    </div>
                    <h5 class="fw-bold text-dark mt-3">Bantuan Alat Tangkap Nelayan</h5>
                    <p class="text-secondary small mb-3">
                        <i class="fas fa-map-marker-alt me-2 text-danger"></i>Kec. Batui, Kel. Sisipan
                    </p>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-info" role="progressbar" style="width: 88%" aria-valuenow="88" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
            
            <!-- Tempat melooping data dari PHP Database nantinya -->
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script Vanilla JS untuk Filter Dependen (Kecamatan -> Desa) -->
    <script>
        function loadDesa() {
            const kecamatanSelect = document.getElementById('kecamatan');
            const desaSelect = document.getElementById('desa');
            const selectedKec = kecamatanSelect.value;

            // Reset menu desa
            desaSelect.innerHTML = '<option value="" selected disabled>-- Pilih Desa/Kelurahan --</option>';

            // Simulasi Data Desa Berdasarkan Kecamatan 
            // (Untuk penerapan nyata, data ini bisa di-fetch menggunakan AJAX/Fetch API ke file PHP lain untuk query db_musrenbang)
            const dataDesa = {
                "Luwuk": ["Bungin", "Baru", "Soho", "Karaton", "Luwuk"],
                "Luwuk Selatan": ["Simpong", "Tanjung Tupe", "Maahas", "Komo", "Jontoge"],
                "Luwuk Utara": ["Kilongan", "Bunga", "Biak", "Boyou"],
                "Batui": ["Batui", "Balantang", "Sisipan", "Tolando", "Bakti Rohani"],
                "Kintom": ["Kintom", "Uwedikan", "Solan", "Samadoya"]
            };

            // Masukkan option baru
            if (dataDesa[selectedKec]) {
                dataDesa[selectedKec].forEach(function(desa) {
                    const option = document.createElement('option');
                    option.value = desa;
                    option.textContent = desa;
                    desaSelect.appendChild(option);
                });
            }
        }
    </script>
</body>
</html>