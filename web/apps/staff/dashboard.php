<?php
// ~/Documents/ELIOT/web/apps/staff/dashboard.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Pastikan sudah login
requireLogin();

// Hanya staff dan admin yang bisa akses
requireRole(['staff', 'admin']);

// ========================================
// QUERY STATISTIK STAFF
// ========================================

// Total buku (tidak dihapus)
$query_total_books = "SELECT COUNT(*) as count FROM books WHERE is_deleted = 0";
$result_books = mysqli_query($conn, $query_total_books);
$total_books = mysqli_fetch_assoc($result_books)['count'] ?? 0;

// Buku yang sedang dipinjam
$query_borrowed = "SELECT COUNT(*) as count FROM ts_peminjaman WHERE status = 'dipinjam' AND is_deleted = 0";
$result_borrowed = mysqli_query($conn, $query_borrowed);
$borrowed_books = mysqli_fetch_assoc($result_borrowed)['count'] ?? 0;

// Peminjaman yang telat
$query_telat = "SELECT COUNT(*) as count FROM ts_peminjaman WHERE status = 'telat' AND is_deleted = 0";
$result_telat = mysqli_query($conn, $query_telat);
$telat_books = mysqli_fetch_assoc($result_telat)['count'] ?? 0;

// Denda yang belum dibayar
$query_denda = "SELECT COUNT(*) as count FROM ts_denda WHERE status_pembayaran = 'belum_dibayar' AND is_deleted = 0";
$result_denda = mysqli_query($conn, $query_denda);
$unpaid_fines = mysqli_fetch_assoc($result_denda)['count'] ?? 0;

$stats = [
    'total_books' => $total_books,
    'borrowed_books' => $borrowed_books,
    'telat_books' => $telat_books,
    'unpaid_fines' => $unpaid_fines
];
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - ELIOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/assets/css/dashboard.css">
</head>
<body class="bg-light">
    
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-book-reader me-2"></i> ELIOT - Staff Panel
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <span class="navbar-text text-white me-3">
                            <i class="fas fa-user-tie me-1"></i>
                            <strong><?= htmlspecialchars($_SESSION['nama'] ?? 'Staff') ?></strong>
                            <small class="d-block">Role: <?= ucfirst($_SESSION['role']) ?></small>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a href="<?= SITE_URL ?>/logout.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid px-4 py-5">
        
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white py-4">
                        <div class="d-flex align-items-center">
                            <div class="me-4">
                                <i class="fas fa-user-tie fa-3x opacity-75"></i>
                            </div>
                            <div>
                                <h3 class="mb-1">Selamat Datang, <?= htmlspecialchars($_SESSION['nama']) ?>!</h3>
                                <p class="mb-0 opacity-75">
                                    <i class="fas fa-briefcase me-1"></i> Staff Perpustakaan ELIOT
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistik Cards -->
        <div class="row mb-4">
            <!-- Total Buku -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted text-uppercase fw-bold small mb-1">Total Buku</div>
                                <div class="h3 mb-0 fw-bold text-primary"><?= number_format($stats['total_books']) ?></div>
                            </div>
                            <div>
                                <i class="fas fa-book fa-2x text-primary opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-primary bg-opacity-10 border-0 small text-primary">
                        <i class="fas fa-info-circle me-1"></i> Koleksi perpustakaan
                    </div>
                </div>
            </div>

            <!-- Buku Dipinjam -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted text-uppercase fw-bold small mb-1">Sedang Dipinjam</div>
                                <div class="h3 mb-0 fw-bold text-warning"><?= number_format($stats['borrowed_books']) ?></div>
                            </div>
                            <div>
                                <i class="fas fa-book-reader fa-2x text-warning opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-warning bg-opacity-10 border-0 small text-warning">
                        <i class="fas fa-clock me-1"></i> Peminjaman aktif
                    </div>
                </div>
            </div>

            <!-- Buku Telat -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted text-uppercase fw-bold small mb-1">Keterlambatan</div>
                                <div class="h3 mb-0 fw-bold text-danger"><?= number_format($stats['telat_books']) ?></div>
                            </div>
                            <div>
                                <i class="fas fa-exclamation-triangle fa-2x text-danger opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-danger bg-opacity-10 border-0 small text-danger">
                        <i class="fas fa-calendar-times me-1"></i> Perlu tindak lanjut
                    </div>
                </div>
            </div>

            <!-- Denda Belum Dibayar -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted text-uppercase fw-bold small mb-1">Denda Pending</div>
                                <div class="h3 mb-0 fw-bold text-success"><?= number_format($stats['unpaid_fines']) ?></div>
                            </div>
                            <div>
                                <i class="fas fa-money-bill-wave fa-2x text-success opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-success bg-opacity-10 border-0 small text-success">
                        <i class="fas fa-coins me-1"></i> Menunggu pembayaran
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Aksi Cepat -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-bolt text-warning me-2"></i> Menu Aksi Cepat
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            
                            <!-- Peminjaman Baru -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-primary h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-handshake fa-3x text-primary mb-3"></i>
                                            <h6 class="fw-bold text-dark">Peminjaman Baru</h6>
                                            <p class="small text-muted mb-0">Proses peminjaman buku</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Pengembalian -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-success h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-undo fa-3x text-success mb-3"></i>
                                            <h6 class="fw-bold text-dark">Pengembalian</h6>
                                            <p class="small text-muted mb-0">Proses pengembalian buku</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Cari Buku -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-info h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-search fa-3x text-info mb-3"></i>
                                            <h6 class="fw-bold text-dark">Cari Buku</h6>
                                            <p class="small text-muted mb-0">Pencarian koleksi buku</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Riwayat Transaksi -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-warning h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-history fa-3x text-warning mb-3"></i>
                                            <h6 class="fw-bold text-dark">Riwayat</h6>
                                            <p class="small text-muted mb-0">Lihat riwayat transaksi</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Kelola Denda -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-danger h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-money-bill fa-3x text-danger mb-3"></i>
                                            <h6 class="fw-bold text-dark">Kelola Denda</h6>
                                            <p class="small text-muted mb-0">Manajemen denda</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Laporan -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-secondary h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-chart-line fa-3x text-secondary mb-3"></i>
                                            <h6 class="fw-bold text-dark">Laporan</h6>
                                            <p class="small text-muted mb-0">Lihat laporan statistik</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Reservasi -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-dark h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-bookmark fa-3x text-dark mb-3"></i>
                                            <h6 class="fw-bold text-dark">Reservasi</h6>
                                            <p class="small text-muted mb-0">Kelola reservasi buku</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Scan RFID -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-primary h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-qrcode fa-3x text-primary mb-3"></i>
                                            <h6 class="fw-bold text-dark">Scan RFID</h6>
                                            <p class="small text-muted mb-0">Scan kartu/buku RFID</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="bg-light border-top mt-5 py-3">
        <div class="container-fluid px-4">
            <div class="text-center text-muted small">
                <p class="mb-0">&copy; 2024 ELIOT - Electronic Library of Things. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
    .menu-card {
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .menu-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .bg-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    </style>
</body>
</html>