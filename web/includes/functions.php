<?php
// ~/Documents/ELIOT/web/includes/functions.php

function redirect($url) {
    // Hilangkan leading slash jika ada
    $url = ltrim($url, '/');
    
    // Jika URL sudah lengkap dengan SITE_URL, langsung redirect
    if (strpos($url, 'http') === 0) {
        header("Location: " . $url);
    } else {
        header("Location: " . SITE_URL . "/" . $url);
    }
    exit();
}

function alert($message, $type = 'danger') {
    $_SESSION['alert'] = ['message' => $message, 'type' => $type];
}

function showAlert() {
    if (isset($_SESSION['alert'])) {
        echo '<div class="alert alert-' . htmlspecialchars($_SESSION['alert']['type']) . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($_SESSION['alert']['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['alert']);
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        alert("Silakan login terlebih dahulu!", "warning");
        redirect('login.php');
    }
} 

function requireRole($allowed_roles = []) {
    requireLogin(); // Pastikan sudah login dulu

    if (!in_array($_SESSION['role'], $allowed_roles)) {
        alert("Akses ditolak! Anda tidak memiliki izin untuk mengakses halaman ini.", "danger");
        redirect('apps/dashboard.php'); // Fix: Ganti dari dashboard/index.php
    }
}

// Helper function untuk debugging (hapus saat production)
function dd($data) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    die();
}

// Helper untuk format tanggal Indonesia
function formatTanggal($date, $format = 'd M Y') {
    $bulan = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    
    $timestamp = strtotime($date);
    $d = date('d', $timestamp);
    $m = date('n', $timestamp);
    $y = date('Y', $timestamp);
    
    return $d . ' ' . $bulan[$m] . ' ' . $y;
}

// Helper untuk cek ekstensi file yang diizinkan
function isAllowedImageType($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $file_type = mime_content_type($file);
    return in_array($file_type, $allowed_types);
}