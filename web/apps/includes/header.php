<?php
// ~/Documents/ELIOT/web/apps/includes/header.php

// Start session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PENTING: Sesuaikan path agar menunjuk ke folder web/includes
require_once '../includes/config.php'; 
require_once '../includes/auth.php';   

// ========================================
// 1. NONAKTIFKAN VALIDASI UNTUK TEST
// ========================================

// Gunakan data placeholder untuk testing tanpa login
if (!isset($_SESSION['userName'])) {
    $_SESSION['userId'] = 999;
    $_SESSION['userName'] = 'Dev Tester';
    $_SESSION['userRole'] = 'admin'; // Role default untuk test
}

// Ambil data user dari sesi (menggunakan Camel Case)
$userId = $_SESSION['userId'] ?? null;
$userName = $_SESSION['userName'] ?? 'Pengguna Anonim';
$userRole = $_SESSION['userRole'] ?? 'member'; 

// ========================================
// 2. Tentukan Base URL (Menggunakan Camel Case)
// ========================================
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Asumsi path baseUrl adalah /eliot
$baseUrl = $protocol . '://' . $host . '/eliot';

// ========================================
// 3. Tentukan Title Halaman
// ========================================
// Variabel harus didefinisikan di file yang meng-include header ini
$pageTitle = $pageTitle ?? 'Dashboard'; 
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | ELIOT - <?= ucfirst($userRole) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <link rel="stylesheet" href="<?= $baseUrl ?>/apps/assets/css/dashboard.css"> 
</head>
<body>
    <div class="wrapper" id="wrapper">
        <?php 
            // Variabel $baseUrl dan $userRole sudah tersedia di scope ini
            include_once 'sidebar.php'; 
        ?>

        <div class="main-content" id="mainContent">
            <nav class="navbar topbar eliot-topbar shadow-sm">
                <div class="container-fluid"> 
                    <div class="d-flex align-items-center navbar-left-group">
                        <button class="btn btn-link sidebar-toggle-btn" id="sidebarToggle" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle navigation">
                            <i class="fas fa-bars fs-5"></i>
                        </button>
                        <div class="d-flex align-items-center">
                            <img src="<?= $baseUrl ?>/apps/assets/img/rfid.png" alt="ELIOT Logo" class="topbar-logo me-2">
                            <span class="eliot-brand-text d-none d-sm-block">ELIOT</span>
                        </div>
                    </div>
                
                    <div class="d-flex align-items-center action-buttons-right">
                        <button class="btn btn-link me-3 p-0" type="button" title="Pencarian Cepat">
                            <i class="fas fa-search fs-5"></i>
                        </button>

                        <button class="btn btn-link me-3 p-0 d-none d-md-block" type="button" title="Kelola Pengguna" disabled>
                            <i class="fas fa-users-cog fs-5 text-secondary"></i>
                        </button>
                    
                        <button class="btn btn-link me-3 p-0" id="fullscreenToggle" title="Mode Layar Penuh">
                            <i class="fas fa-expand fs-5"></i>
                        </button>
                    
                        <button class="btn btn-link me-3 p-0 theme-toggle-btn" id="themeToggle" title="Toggle Dark/Light Mode">
                            <i class="fas fa-moon fs-5 eliot-theme-icon"></i>
                        </button>

                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle fs-4 me-2" style="color: var(--colorGreenPrimary);"></i> 
                                <span class="d-none d-sm-inline fw-medium"><?= $userName ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><h6 class="dropdown-header">Role: <?= ucfirst($userRole) ?></h6></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= $baseUrl ?>/apps/profile.php"><i class="fas fa-user-circle me-2"></i> Profil</a></li>
                                <li><a class="dropdown-item" href="<?= $baseUrl ?>/apps/settings.php"><i class="fas fa-cog me-2"></i> Pengaturan</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= $baseUrl ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
        
            <main class="content-wrapper">