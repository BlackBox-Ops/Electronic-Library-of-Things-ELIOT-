<?php
// ~/Documents/ELIOT/web/includes/auth.php

class Auth {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
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
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['status'] = $user['status']; 

                // Log login (opsional)
                $this->logActivity($user['id'], 'sistem', 'login');

                return true;
            } else {
                return "Password salah.";
            }
        } else {
            return "Email tidak ditemukan.";
        }
    }

    public function logout() {
        if (isLoggedIn()) {
            $this->logActivity($_SESSION['user_id'], 'sistem', 'logout');
        }
        session_destroy();
        redirect('login.php');
    }

    private function logActivity($user_id, $modul, $aksi) {
        $stmt = $this->conn->prepare("INSERT INTO ts_log_aktivitas (user_id, modul, aksi, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bind_param("isss", $user_id, $modul, $aksi, $ip);
        $stmt->execute();
    }
    
    // ========================================
    // GET USER BY EMAIL - TAMBAHKAN INI
    // ========================================
    public function getUserByEmail($email) {
        $query = "SELECT * FROM users WHERE email = ? AND is_deleted = 0 LIMIT 1";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        
        return null;
    }
}