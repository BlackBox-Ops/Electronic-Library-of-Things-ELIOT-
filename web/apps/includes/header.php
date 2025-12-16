<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Placeholder testing
if (!isset($_SESSION['userName'])) {
    $_SESSION['userName'] = 'Dev Tester';
    $_SESSION['userRole'] = 'admin';
}

$userName = $_SESSION['userName'];
$userRole = $_SESSION['userRole'] ?? 'member';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host . '/eliot';

$pageTitle = $pageTitle ?? 'Dashboard V3';
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | ELIOT</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/apps/assets/css/dashboard.css">
</head>
<body>
    <div class="wrapper" id="wrapper">
        <?php include_once 'sidebar.php'; ?>

        <div class="main-content" id="mainContent">
            <nav class="eliot-topbar">
                <div class="container-fluid">
                    <!-- Bagian kiri navbar tetap sama -->
                    <div class="navbar-left-group">
                        <button class="btn btn-link sidebar-toggle-btn" id="sidebarToggle">
                            <i class="fas fa-bars fs-4"></i>
                        </button>
                        <div class="d-flex align-items-center">
                            <img src="<?= $baseUrl ?>/apps/assets/img/rfid.png" alt="ELIOT Logo" class="topbar-logo me-2">
                            <span class="eliot-brand-text">ELIOT</span>
                        </div>
                        <div class="search-bar ms-3">
                            <input type="text" class="form-control" placeholder="Cari aset, pengguna...">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>

                    <!-- BAGIAN KANAN NAVBAR – Jarak pas, ikon berwarna -->
                    <div class="action-buttons-right">

                        <!-- Manajemen Akun (hanya admin) -->
                        <?php if ($userRole === 'admin'): ?>
                            <a href="<?= $baseUrl ?>/apps/users.php" 
                            class="btn btn-link p-0" 
                            id="managementAkunBtn" 
                            title="Manajemen Akun & Role">
                                <i class="fas fa-users-cog"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Notifikasi -->
                        <button class="btn btn-link p-0" id="notifBtn" title="Notifikasi">
                            <i class="fas fa-bell"></i>
                        </button>

                        <!-- Fullscreen -->
                        <button class="btn btn-link p-0" id="fullscreenToggle" title="Layar Penuh">
                            <i class="fas fa-expand"></i>
                        </button>

                        <!-- Theme Toggle -->
                        <button class="btn btn-link p-0 theme-toggle-btn" id="themeToggle" title="Ganti Tema">
                            <i class="fas fa-moon eliot-theme-icon"></i>
                        </button>

                        <!-- User Dropdown -->
                        <div class="dropdown ms-1">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" 
                                data-bs-toggle="dropdown" id="userDropdown">
                                <i class="fas fa-user-circle fs-3 me-2"></i>
                                <span class="d-none d-md-inline fw-medium"><?= $userName ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header">Role: <?= ucfirst($userRole) ?></h6></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profil</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Pengaturan</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <main class="content-wrapper">