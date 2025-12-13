<?php
// ~/Documents/ELIOT/web/includes/auth.php

class Auth {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    // Method internal: cek apakah user sudah login
    private function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    // Method internal: redirect sederhana (tidak bergantung functions.php)
    private function redirect($url) {
        header("Location: " . $url);
        exit();
    }

    public function login($email, $password) {
        $stmt = $this->conn->prepare("SELECT id, nama, email, password_hash, role, status FROM users WHERE email = ? AND is_deleted = 0 LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['status'] !== 'aktif') {
                return "Akun Anda tidak aktif atau suspended.";
            }

            if (password_verify($password, $user['password_hash'])) {
                // Login sukses
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Log aktivitas login
                $this->logActivity($user['id'], 'sistem', 'login');

                return true;
            } else {
                return "Password salah.";
            }
        } else {
            return "Email tidak ditemukan.";
        }

        $stmt->close();
    }

    public function logout() {
        // Log logout jika user sedang login
        if ($this->isLoggedIn()) {
            $this->logActivity($_SESSION['user_id'], 'sistem', 'logout');
        }

        // Hapus semua session
        session_destroy();

        // Redirect ke login
        $this->redirect('./login.php');
    }

    private function logActivity($user_id, $modul, $aksi) {
        $stmt = $this->conn->prepare("INSERT INTO ts_log_aktivitas (user_id, modul, aksi, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bind_param("isss", $user_id, $modul, $aksi, $ip);
        $stmt->execute();
        $stmt->close();
    }
}