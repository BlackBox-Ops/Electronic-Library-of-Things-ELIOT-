<?php
// ~/Documents/ELIOT/web/includes/functions.php

function redirect($url) {
    header("Location: " . SITE_URL . "/" . ltrim($url, '/'));
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
        alert("Silakan login terlebih dahulu!");
        redirect('login.php');
    }
} 

// FUNGSI INI YANG HILANG — TAMBAHKAN!
function requireRole($allowed_roles = []) {
    requireLogin(); // Pastikan sudah login dulu

    if (!in_array($_SESSION['role'], $allowed_roles)) {
        alert("Akses ditolak! Anda tidak memiliki izin untuk mengakses halaman ini.");
        redirect('apps/dashboard/index.php'); // atau 'index.php'
    }
}