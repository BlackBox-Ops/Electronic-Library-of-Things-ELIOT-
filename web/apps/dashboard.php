<?php
// ~/Documents/ELIOT/web/apps/dashboard.php

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin(); // Pastikan sudah login

$auth = new Auth($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ELIOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/style.css"> <!-- Gunakan CSS global -->
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #628141;">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-book me-2"></i> ELIOT Dashboard
            </a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-4">
                    Halo, <strong><?= htmlspecialchars($_SESSION['nama'] ?? 'User') ?></strong> 
                    (<?= htmlspecialchars(ucfirst($_SESSION['role'])) ?>)
                </span>
                <a href="../registrasi.php" class="btn btn-light me-2 <?= ($_SESSION['role'] !== 'admin') ? 'd-none' : '' ?>">
                    <i class="fas fa-user-plus me-1"></i> Buat User
                </a>
                <a href="../logout.php" class="btn btn-outline-light">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-tachometer-alt fa-5x text-success mb-4"></i>
                        <h2>Selamat Datang di Dashboard ELIOT!</h2>
                        <p class="lead text-muted">Role Anda: <strong><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></strong></p>
                        
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <div class="mt-4">
                                <h4>Menu Admin</h4>
                                <a href="registrasi.php" class="btn btn-success me-2">
                                    <i class="fas fa-user-plus"></i> Buat User Baru
                                </a>
                                <a href="#" class="btn btn-primary me-2">
                                    <i class="fas fa-book"></i> Kelola Buku
                                </a>
                                <a href="#" class="btn btn-info">
                                    <i class="fas fa-chart-bar"></i> Laporan
                                </a>
                            </div>
                        <?php elseif ($_SESSION['role'] === 'staff'): ?>
                            <p>Anda dapat memproses peminjaman dan pengembalian buku.</p>
                        <?php else: ?>
                            <p>Selamat membaca! Kunjungi katalog untuk melihat buku.</p>
                            <a href="../index.php" class="btn btn-primary">Lihat Katalog Buku</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>