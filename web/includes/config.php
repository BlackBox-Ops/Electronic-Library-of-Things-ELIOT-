<?php
// ~/Documents/ELIOT/web/includes/config.php

// Tampilkan error selama development (nanti matikan saat production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Mulai session hanya sekali — di sini saja!
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');                    // Kosong default XAMPP
define('DB_NAME', 'perpustakaan_db');         // Sesuai permintaanmu!

define('SITE_URL', 'http://localhost/eliot'); // Base URL project

// Koneksi Database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");