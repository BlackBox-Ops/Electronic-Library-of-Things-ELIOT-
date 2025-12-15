<?php
// ~/Documents/ELIOT/web/includes/auth.php - KODE LENGKAP

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
    
    public function getUserByEmail($email) {
        $query = "SELECT * FROM users WHERE email = ? AND is_deleted = 0 LIMIT 1";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_assoc($result);
    }
    
    // ===============================================
    // FUNGSI BARU UNTUK VALIDASI KEAMANAN PASSWORD
    // ===============================================

    /**
     * Memvalidasi apakah password memenuhi kriteria keamanan baru.
     * Kriteria: Min 6 karakter, 1 Huruf Kapital, 1 Huruf Kecil, 1 Angka.
     * @param string $password - Plaintext password
     * @return bool
     */
    public function isPasswordSecure(string $password): bool {
        // Panjang minimal 6 karakter
        if (strlen($password) < 6) return false;
        
        // Harus mengandung 1 huruf kapital
        if (!preg_match('/[A-Z]/', $password)) return false;
        
        // Harus mengandung 1 huruf kecil
        if (!preg_match('/[a-z]/', $password)) return false;
        
        // Harus mengandung 1 angka
        if (!preg_match('/[0-9]/', $password)) return false;
        
        return true;
    }

    /**
     * Cek apakah password yang dimasukkan adalah salah satu password bawaan yang dilarang.
     * @param string $password - Plaintext password
     * @return bool
     */
    public function isDefaultPassword(string $password): bool {
        // Daftar password bawaan atau sangat umum yang wajib dihindari
        $default_passwords = [
            'admin1', '', 'admin123', 
            'member1', 'member2', 'user123',
            'password', '123456', 'qwerty', 'admin', 'eliot' 
        ];
        
        return in_array(strtolower($password), $default_passwords);
    }
    // ===============================================
    // FUNGSI PENILAIAN KEKUATAN PASSWORD BARU
    // ===============================================

    /**
     * Menilai kekuatan password berdasarkan kriteria (digunakan oleh backend).
     * @param string $password - Plaintext password
     * @return array [level (int 0-4), strength (string)]
     */
    public function assessPasswordStrength(string $password): array {
        $score = 0;
        $length = strlen($password);

        // Kriteria:
        if ($length >= 8) $score++; // Panjang minimal 8 (lebih baik dari 6)
        if (preg_match('/[A-Z]/', $password)) $score++; // Huruf kapital
        if (preg_match('/[a-z]/', $password)) $score++; // Huruf kecil
        if (preg_match('/[0-9]/', $password)) $score++; // Angka
        if (preg_match('/[^a-zA-Z0-9\s]/', $password)) $score++; // Karakter spesial/simbol

        $strength = '';
        $level = 0;

        if ($length < 6) {
            $strength = "Sangat Lemah";
            $level = 0;
        } elseif ($score < 3) {
            $strength = "Lemah";
            $level = 1;
        } elseif ($score == 3 || $score == 4) {
            $strength = "Sedang";
            $level = 2;
        } elseif ($score == 5) {
            $strength = "Kuat";
            $level = 3;
        } else {
             $strength = "Sangat Kuat"; // Jika score lebih dari 5 (misal panjang > 12)
            $level = 4;
        }
        
        // Pengecualian: Jika tidak memenuhi kriteria minimal (walau score tinggi), set ke lemah
        if (!$this->isPasswordSecure($password)) {
             $strength = "Lemah"; // Tidak ada Kapital/Kecil/Angka
            $level = 1; 
        }

        return ['level' => $level, 'strength' => $strength];
    }
}