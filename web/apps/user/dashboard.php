<?php
// ~/Documents/ELIOT/web/apps/user/dashboard.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Pastikan sudah login
requireLogin();

// Hanya member yang bisa akses (admin/staff akan di-redirect)
if ($_SESSION['role'] !== 'member') {
    // Redirect ke dashboard sesuai role
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':
            header('Location: ../admin/dashboard.php');
            break;
        case 'staff':
            header('Location: ../staff/dashboard.php');
            break;
        default:
            header('Location: ../user/dashboard.php');
            break;
    }
    exit();
}

// Set default status jika tidak ada
if (!isset($_SESSION['status'])) {
    $_SESSION['status'] = 'aktif'; // Default value
}

// ========================================
// QUERY STATISTIK USER/MEMBER
// ========================================

$user_id = $_SESSION['user_id'];
$stats = [];

// 1. Buku yang sedang dipinjam
$query_borrowed = "SELECT COUNT(*) as count FROM ts_peminjaman WHERE user_id = ? AND status = 'dipinjam' AND is_deleted = 0";
$stmt_borrowed = mysqli_prepare($conn, $query_borrowed);

if ($stmt_borrowed) {
    mysqli_stmt_bind_param($stmt_borrowed, "i", $user_id);
    mysqli_stmt_execute($stmt_borrowed);
    $result_borrowed = mysqli_stmt_get_result($stmt_borrowed);
    
    if ($result_borrowed) {
        $borrowed_data = mysqli_fetch_assoc($result_borrowed);
        $stats['borrowed_books'] = $borrowed_data['count'] ?? 0;
    } else {
        $stats['borrowed_books'] = 0;
    }
} else {
    $stats['borrowed_books'] = 0;
}

// 2. Total riwayat peminjaman
$query_history = "SELECT COUNT(*) as count FROM ts_peminjaman WHERE user_id = ? AND is_deleted = 0";
$stmt_history = mysqli_prepare($conn, $query_history);

if ($stmt_history) {
    mysqli_stmt_bind_param($stmt_history, "i", $user_id);
    mysqli_stmt_execute($stmt_history);
    $result_history = mysqli_stmt_get_result($stmt_history);
    
    if ($result_history) {
        $history_data = mysqli_fetch_assoc($result_history);
        $stats['history_books'] = $history_data['count'] ?? 0;
    } else {
        $stats['history_books'] = 0;
    }
} else {
    $stats['history_books'] = 0;
}

// 3. Denda yang belum dibayar
$query_denda = "SELECT COUNT(*) as count FROM ts_denda WHERE user_id = ? AND status_pembayaran = 'belum_dibayar' AND is_deleted = 0";
$stmt_denda = mysqli_prepare($conn, $query_denda);

if ($stmt_denda) {
    mysqli_stmt_bind_param($stmt_denda, "i", $user_id);
    mysqli_stmt_execute($stmt_denda);
    $result_denda = mysqli_stmt_get_result($stmt_denda);
    
    if ($result_denda) {
        $denda_data = mysqli_fetch_assoc($result_denda);
        $stats['unpaid_fines'] = $denda_data['count'] ?? 0;
    } else {
        $stats['unpaid_fines'] = 0;
    }
} else {
    $stats['unpaid_fines'] = 0;
}

// 4. Buku favorit/reservasi (jika ada tabel ts_reservasi)
$stats['favorite_books'] = 0; // Default
$query_favorite = "SELECT COUNT(*) as count FROM ts_reservasi WHERE user_id = ? AND is_deleted = 0";
$stmt_favorite = mysqli_prepare($conn, $query_favorite);

if ($stmt_favorite) {
    mysqli_stmt_bind_param($stmt_favorite, "i", $user_id);
    mysqli_stmt_execute($stmt_favorite);
    $result_favorite = mysqli_stmt_get_result($stmt_favorite);
    
    if ($result_favorite) {
        $favorite_data = mysqli_fetch_assoc($result_favorite);
        $stats['favorite_books'] = $favorite_data['count'] ?? 0;
    }
}
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - ELIOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/assets/css/dashboard.css">
    <style>
        .stat-card {
            transition: transform 0.3s ease;
            border-radius: 10px;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .menu-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .menu-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .member-gradient {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
    </style>
</head>
<body class="bg-light">
    
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm member-gradient">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-book me-2"></i> ELIOT - Member Area
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <span class="navbar-text text-white me-3">
                            <i class="fas fa-user-circle me-1"></i>
                            <strong><?= htmlspecialchars($_SESSION['nama'] ?? 'Member') ?></strong>
                            <small class="d-block">Status: 
                                <?php 
                                $status = $_SESSION['status'] ?? 'aktif';
                                if ($status == 'aktif'): ?>
                                    <span class="text-success">Aktif</span>
                                <?php elseif ($status == 'pending'): ?>
                                    <span class="text-warning">Menunggu</span>
                                <?php elseif ($status == 'suspended'): ?>
                                    <span class="text-danger">Ditangguhkan</span>
                                <?php else: ?>
                                    <span class="text-secondary"><?= ucfirst($status) ?></span>
                                <?php endif; ?>
                            </small>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a href="/eliot/logout.php" class="btn btn-outline-light btn-sm">
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
                <div class="card border-0 shadow-sm member-gradient text-white">
                    <div class="card-body py-4">
                        <div class="d-flex align-items-center">
                            <div class="me-4">
                                <i class="fas fa-user-circle fa-3x opacity-75"></i>
                            </div>
                            <div>
                                <h3 class="mb-1">Selamat Datang, <?= htmlspecialchars($_SESSION['nama']) ?>!</h3>
                                <p class="mb-0 opacity-75">
                                    <i class="fas fa-book-reader me-1"></i> Anggota Perpustakaan ELIOT
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistik Cards -->
        <div class="row mb-4">
            <!-- Buku Dipinjam -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100 stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted text-uppercase fw-bold small mb-1">Sedang Dipinjam</div>
                                <div class="h3 mb-0 fw-bold text-primary"><?= number_format($stats['borrowed_books']) ?></div>
                            </div>
                            <div>
                                <i class="fas fa-book-open fa-2x text-primary opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-primary bg-opacity-10 border-0 small text-primary">
                        <i class="fas fa-clock me-1"></i> Buku sedang Anda pinjam
                    </div>
                </div>
            </div>

            <!-- Riwayat Peminjaman -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100 stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted text-uppercase fw-bold small mb-1">Total Pinjam</div>
                                <div class="h3 mb-0 fw-bold text-success"><?= number_format($stats['history_books']) ?></div>
                            </div>
                            <div>
                                <i class="fas fa-history fa-2x text-success opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-success bg-opacity-10 border-0 small text-success">
                        <i class="fas fa-list-alt me-1"></i> Total riwayat peminjaman
                    </div>
                </div>
            </div>

            <!-- Denda -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100 stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted text-uppercase fw-bold small mb-1">Denda</div>
                                <div class="h3 mb-0 fw-bold text-warning"><?= number_format($stats['unpaid_fines']) ?></div>
                            </div>
                            <div>
                                <i class="fas fa-money-bill-wave fa-2x text-warning opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-warning bg-opacity-10 border-0 small text-warning">
                        <i class="fas fa-exclamation-circle me-1"></i> Denda yang perlu dibayar
                    </div>
                </div>
            </div>

            <!-- Buku Favorit -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100 stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted text-uppercase fw-bold small mb-1">Favorit</div>
                                <div class="h3 mb-0 fw-bold text-info"><?= number_format($stats['favorite_books']) ?></div>
                            </div>
                            <div>
                                <i class="fas fa-heart fa-2x text-info opacity-25"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-info bg-opacity-10 border-0 small text-info">
                        <i class="fas fa-bookmark me-1"></i> Buku yang disimpan
                    </div>
                </div>
            </div>
        </div>

        <!-- Informasi Akun -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-user-circle text-primary me-2"></i> Informasi Akun
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="40%"><strong>Nama Lengkap</strong></td>
                                    <td><?= htmlspecialchars($_SESSION['nama']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email</strong></td>
                                    <td><?= htmlspecialchars($_SESSION['email']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status Akun</strong></td>
                                    <td>
                                        <?php 
                                        $status = $_SESSION['status'] ?? 'aktif';
                                        if ($status == 'aktif'): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php elseif ($status == 'pending'): ?>
                                            <span class="badge bg-warning">Menunggu</span>
                                        <?php elseif ($status == 'suspended'): ?>
                                            <span class="badge bg-danger">Ditangguhkan</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= ucfirst($status) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Role</strong></td>
                                    <td>
                                        <span class="badge bg-primary"><?= ucfirst($_SESSION['role']) ?></span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-info-circle text-info me-2"></i> Informasi Perpustakaan
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Tips:</strong> Anda dapat meminjam maksimal <strong>3 buku</strong> secara bersamaan.
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Perhatian:</strong> Masa pinjam buku adalah <strong>7 hari</strong>. Keterlambatan dikenakan denda.
                        </div>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Syarat:</strong> Pastikan tidak ada denda tertunggak untuk melakukan peminjaman baru.
                        </div>
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
                            
                            <!-- Katalog Buku -->
                            <div class="col-xl-3 col-md-6">
                                <a href="../../index.php" class="text-decoration-none">
                                    <div class="card border-primary h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-book fa-3x text-primary mb-3"></i>
                                            <h6 class="fw-bold text-dark">Katalog Buku</h6>
                                            <p class="small text-muted mb-0">Lihat koleksi buku</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Riwayat Peminjaman -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-success h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-history fa-3x text-success mb-3"></i>
                                            <h6 class="fw-bold text-dark">Riwayat</h6>
                                            <p class="small text-muted mb-0">Lihat riwayat pinjam</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Edit Profil -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-info h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-user-edit fa-3x text-info mb-3"></i>
                                            <h6 class="fw-bold text-dark">Edit Profil</h6>
                                            <p class="small text-muted mb-0">Ubah data pribadi</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Ganti Password -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-warning h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-key fa-3x text-warning mb-3"></i>
                                            <h6 class="fw-bold text-dark">Ganti Password</h6>
                                            <p class="small text-muted mb-0">Ubah kata sandi</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Buku Favorit -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-danger h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-heart fa-3x text-danger mb-3"></i>
                                            <h6 class="fw-bold text-dark">Favorit</h6>
                                            <p class="small text-muted mb-0">Buku favorit Anda</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Notifikasi -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-secondary h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-bell fa-3x text-secondary mb-3"></i>
                                            <h6 class="fw-bold text-dark">Notifikasi</h6>
                                            <p class="small text-muted mb-0">Pemberitahuan</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Bantuan -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-dark h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-question-circle fa-3x text-dark mb-3"></i>
                                            <h6 class="fw-bold text-dark">Bantuan</h6>
                                            <p class="small text-muted mb-0">Pusat bantuan</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- Kontak Admin -->
                            <div class="col-xl-3 col-md-6">
                                <a href="#" class="text-decoration-none">
                                    <div class="card border-primary h-100 menu-card">
                                        <div class="card-body text-center py-4">
                                            <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                                            <h6 class="fw-bold text-dark">Kontak</h6>
                                            <p class="small text-muted mb-0">Hubungi admin</p>
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
                <p class="mb-0">Hak akses: Member Perpustakaan</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>