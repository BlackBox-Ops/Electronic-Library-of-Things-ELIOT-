<?php
// ~/Documents/ELIOT/web/apps/dashboard.php

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Cek session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Jika belum login, redirect ke login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit();
}

// Redirect berdasarkan role
$role = $_SESSION['role'];

switch ($role) {
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    case 'staff':
        header('Location: staff/dashboard.php');
        break;
    case 'member':
    default:
        header('Location: user/dashboard.php');
        break;
}
exit();
?>