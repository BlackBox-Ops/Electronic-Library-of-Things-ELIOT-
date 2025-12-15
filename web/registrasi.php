<?php
// ~/Documents/ELIOT/web/registrasi.php - VERSI REVISI DENGAN VALIDASI KEAMANAN DAN STRENGTH METER

// ERROR REPORTING
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session untuk cek login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php'; // WAJIB: Memuat class Auth untuk validasi keamanan

// Variabel untuk status
$error_message = '';
$success_message = '';

// ========================================
// TENTUKAN MODE: PUBLIC atau ADMIN
// ========================================
$mode = 'public'; // default

// Cek apakah ada admin yang sudah login
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $mode = 'admin';
    $is_logged_in_as_admin = true;
} else {
    $is_logged_in_as_admin = false;
}

// ========================================
// CEK: APAKAH SUDAH ADA ADMIN DI DATABASE?
// ========================================
$stmt_check_admin = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_deleted = 0");
$stmt_check_admin->execute();
$result_check_admin = $stmt_check_admin->get_result();
$row_admin = $result_check_admin->fetch_row();
$admin_exists = $row_admin[0] > 0;
$stmt_check_admin->close();

// ========================================
// LOGIC ACCESS CONTROL BERDASARKAN MODE
// ========================================
if ($mode === 'admin') {
    // Mode admin: admin yang sudah login membuat user
    $allowed_roles = ['member', 'staff', 'admin'];
    $default_role = 'member';
    $is_first_setup = false;
    
    // Judul dan teks
    $page_title = "Buat User Baru - Panel Admin";
    $form_title = "Buat User Baru";
    $form_subtitle = "Panel Administrator";
    $submit_text = "Buat User Baru";
    $back_link = "apps/dashboard.php";
    $back_text = "Kembali ke Dashboard";
    
} elseif (!$admin_exists) {
    // Mode first setup: buat admin pertama
    $mode = 'first_setup';
    $allowed_roles = ['admin'];
    $default_role = 'admin';
    $is_first_setup = true;
    
    // Judul dan teks
    $page_title = "Setup Admin Utama - ELIOT";
    $form_title = "Setup Admin Utama";
    $form_subtitle = "Langkah pertama menggunakan ELIOT";
    $submit_text = "Buat Admin & Mulai";
    $back_link = "login.php";
    $back_text = "Sudah punya akun? Login";
    
} else {
    // Mode public: user mendaftar sendiri sebagai member
    $mode = 'public';
    $allowed_roles = ['member']; // Hanya boleh daftar sebagai member
    $default_role = 'member';
    $is_first_setup = false;
    
    // Judul dan teks
    $page_title = "Registrasi Member - ELIOT";
    $form_title = "Daftar Member Baru";
    $form_subtitle = "Electronic Library of Things";
    $submit_text = "Daftar Sekarang";
    $back_link = "login.php";
    $back_text = "Sudah punya akun? Login";
    
    // Redirect jika sudah login
    if (isset($_SESSION['user_id'])) {
        header('Location: apps/dashboard.php');
        exit;
    }
}

// ========================================
// PROSES FORM SUBMISSION
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_identitas = trim($_POST['no_identitas'] ?? '');
    $no_telepon = trim($_POST['no_telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $requested_role = trim($_POST['role'] ?? $default_role);
    $role = in_array($requested_role, $allowed_roles) ? $requested_role : $default_role;
    
    // INISIALISASI AUTH UNTUK VALIDASI
    $auth = new Auth($conn);
    
    // VALIDASI INPUT
    if (empty($nama) || empty($email) || empty($password)) {
        $error_message = 'Nama, email, dan password wajib diisi!';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Password dan konfirmasi tidak cocok!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format email tidak valid!';
    } elseif (!$auth->isPasswordSecure($password)) { // <--- VALIDASI KEAMANAN BARU
        $error_message = 'Password harus minimal 6 karakter dan mengandung huruf kapital, huruf kecil, dan angka!';
    } else {
        // CEK EMAIL DUPLIKAT
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND is_deleted = 0");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = 'Email sudah terdaftar!';
            $stmt->close();
        } else {
            $stmt->close();
            
            // ============================================================
            // PROSES UPLOAD FOTO (OPSIONAL)
            // ============================================================
            $foto_path = null;
            
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                // Validasi sederhana
                $max_size = 2 * 1024 * 1024; // 2MB
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $file_extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                
                if ($_FILES['foto']['size'] > $max_size) {
                    // Error size, tapi lanjut tanpa foto
                    error_log("File too large: " . $_FILES['foto']['size']);
                } elseif (!in_array($file_extension, $allowed_extensions)) {
                    // Error format, tapi lanjut tanpa foto
                    error_log("Invalid file extension: " . $file_extension);
                } else {
                    // PATH KE FOLDER YANG SUDAH ADA
                    $upload_dir = '/home/user/Documents/ELIOT/web/public/assets/img/profiles/';
                    
                    // HANYA upload jika folder ada dan writable
                    if (file_exists($upload_dir) && is_writable($upload_dir)) {
                        // Generate nama file unik
                        $filename = 'profile_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $target_file = $upload_dir . $filename;
                        
                        // Pindahkan file
                        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                            $foto_path = 'public/assets/img/profiles/' . $filename;
                            
                            // Set permission file
                            chmod($target_file, 0644);
                        }
                    }
                }
            }
            
            // ============================================================
            // TENTUKAN STATUS USER
            // ============================================================
            if ($mode === 'first_setup') {
                // Admin pertama langsung aktif
                $status = 'aktif';
            } else {
                // User baru: suspended (tunggu verifikasi admin)
                $status = 'suspended';
            }
            
            // Pastikan status valid sesuai ENUM
            $allowed_statuses = ['aktif', 'nonaktif', 'suspended'];
            if (!in_array($status, $allowed_statuses)) {
                $status = 'suspended';
            }
            
            // ============================================================
            // INSERT USER KE DATABASE
            // ============================================================
            if (empty($error_message)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Tentukan query berdasarkan ada/tidaknya foto
                if ($foto_path) {
                    $stmt_insert = $conn->prepare("INSERT INTO users 
                        (nama, email, no_identitas, no_telepon, alamat, password_hash, role, status, foto_path, created_at, updated_at, is_deleted)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)");
                    
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("sssssssss", 
                            $nama, $email, $no_identitas, $no_telepon, 
                            $alamat, $password_hash, $role, $status, $foto_path
                        );
                    } else {
                        $error_message = 'Error preparing statement: ' . $conn->error;
                    }
                } else {
                    $stmt_insert = $conn->prepare("INSERT INTO users 
                        (nama, email, no_identitas, no_telepon, alamat, password_hash, role, status, created_at, updated_at, is_deleted)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)");
                    
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("ssssssss", 
                            $nama, $email, $no_identitas, $no_telepon, 
                            $alamat, $password_hash, $role, $status
                        );
                    } else {
                        $error_message = 'Error preparing statement: ' . $conn->error;
                    }
                }
                
                // Jika statement berhasil dibuat
                if (empty($error_message) && $stmt_insert) {
                    if ($stmt_insert->execute()) {
                        
                        if ($mode === 'first_setup') {
                            $success_message = "Admin utama '$nama' berhasil dibuat!";
                            echo "<script>
                                setTimeout(function(){ 
                                    alert('Admin utama berhasil dibuat! Silakan login.');
                                    window.location.href = 'login.php'; 
                                }, 2000);
                            </script>";
                            
                        } elseif ($mode === 'admin') {
                            $success_message = "User '$nama' berhasil dibuat!";
                            echo "<script>
                                setTimeout(function(){ 
                                    window.location.href = 'apps/dashboard.php'; 
                                }, 2000);
                            </script>";
                            
                        } else {
                            // Mode public
                            $success_message = "Pendaftaran berhasil! Akun Anda sedang menunggu verifikasi admin.";
                            echo "<script>
                                setTimeout(function(){ 
                                    alert('Pendaftaran berhasil! Silakan tunggu verifikasi admin.');
                                    window.location.href = 'login.php'; 
                                }, 2000);
                            </script>";
                        }
                        
                    } else {
                        $error_message = 'Gagal menyimpan data: ' . $stmt_insert->error;
                    }
                    
                    $stmt_insert->close();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="public/assets/css/style.css">
    <link rel="stylesheet" href="public/assets/css/registrasi.css">
    <style>
        .alert-dismissible {
            padding-right: 3.5rem;
            position: relative;
        }
        
        .alert-dismissible .btn-close {
            position: absolute;
            top: 50%;
            right: 1rem;
            transform: translateY(-50%);
            padding: 0.75rem;
        }
        
        [data-theme="dark"] .alert-dismissible .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        /* START New CSS for Strength Meter */
        .progress {
            border-radius: 4px;
            height: 6px; 
            background-color: var(--input-border);
        }
        .progress-bar {
            transition: width 0.3s ease-in-out;
        }
        /* Penyesuaian warna teks agar kontras */
        [data-theme="dark"] .text-danger { color: #f8d7da !important; }
        [data-theme="dark"] .text-warning { color: #ffc107 !important; }
        [data-theme="dark"] .text-info { color: #6cbfff !important; }
        [data-theme="dark"] .text-success { color: #d1e7dd !important; }
        /* END New CSS */
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="container login-card-container">
            <div class="row g-0 card-shadow overflow-hidden rounded-4">
                
                <div class="col-md-6 d-none d-md-flex align-items-center justify-content-center left-panel position-relative">
                    <div class="overlay-content text-center text-white p-5">
                        <h1 class="display-4 fw-bold mb-3">ELIOT</h1>
                        <p class="lead mb-4">Electronic Library of Things</p>
                    </div>
                </div>

                <div class="col-md-6 bg-white p-5 d-flex flex-column justify-content-center position-relative">
                    
                    <button class="btn btn-link theme-toggle-btn position-absolute top-0 end-0 m-3 text-decoration-none" id="themeToggle" type="button">
                        <i class="fas fa-moon fs-5"></i>
                    </button>

                    <div class="form-content">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-dark mb-1"><?= htmlspecialchars($form_title) ?></h2>
                            <p class="text-secondary small mb-0"><?= htmlspecialchars($form_subtitle) ?></p>
                        </div>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-check-circle me-2 mt-1 fs-5"></i>
                                    <div class="flex-grow-1">
                                        <?= htmlspecialchars($success_message) ?>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-exclamation-triangle me-2 mt-1 fs-5"></i>
                                    <div class="flex-grow-1">
                                        <?= htmlspecialchars($error_message) ?>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info small py-2 mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Password minimal 6 karakter, harus mengandung huruf <b>Kapital</b>, <b>Kecil</b>, dan <b>Angka</b>.
                        </div>

                        <form method="POST" enctype="multipart/form-data" id="registerForm" onsubmit="return validateForm()">
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama" class="form-control" placeholder="Nama lengkap" required 
                                       value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="email@example.com" required 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">No Identitas (NIM/NIP)</label>
                                <input type="text" name="no_identitas" class="form-control" placeholder="NIM/NIP" 
                                       value="<?= htmlspecialchars($_POST['no_identitas'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">No Telepon</label>
                                <input type="tel" name="no_telepon" class="form-control" placeholder="08123456789" 
                                       value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Alamat</label>
                                <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat lengkap"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                            </div>

                            <?php if ($mode === 'admin' && count($allowed_roles) > 1): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Role <span class="text-danger">*</span></label>
                                <select name="role" class="form-select" required>
                                    <?php foreach ($allowed_roles as $role_option): ?>
                                        <option value="<?= $role_option ?>" <?= ($role_option === $default_role) ? 'selected' : '' ?>>
                                            <?= ucfirst($role_option) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                                <input type="hidden" name="role" value="<?= htmlspecialchars($default_role) ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" class="form-control" 
                                           placeholder="Minimal 6 karakter" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="mt-2" id="password-strength-container" style="display:none;">
                                    <div class="progress">
                                        <div id="strength-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <span id="strength-text" class="small fw-bold mt-1 d-block"></span>
                                </div>
                                </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Konfirmasi Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="confirm_password" 
                                           class="form-control" placeholder="Ulangi password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold">Foto Profil <span class="text-muted">(Opsional)</span></label>
                                <input type="file" name="foto" class="form-control form-control-sm" accept="image/*">
                                <small class="text-muted d-block mt-1">
                                    Max 2MB - JPG, PNG, GIF
                                </small>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-custom-green text-white fw-bold" id="submitBtn">
                                    <i class="fas fa-user-plus me-2"></i>
                                    <span id="btnText">
                                        <?= htmlspecialchars($submit_text) ?>
                                    </span>
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <a href="<?= htmlspecialchars($back_link) ?>" class="text-decoration-none small text-muted">
                                <i class="fas fa-arrow-left me-1"></i> <?= htmlspecialchars($back_text) ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Toggle password visibility
    document.getElementById('togglePassword')?.addEventListener('click', function() {
        const input = document.getElementById('password');
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });

    document.getElementById('toggleConfirmPassword')?.addEventListener('click', function() {
        const input = document.getElementById('confirm_password');
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });

    // Theme toggle dan Sinkronisasi (Dark Mode)
    function getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function updateThemeIcon(theme) {
        const icon = document.getElementById('themeToggle')?.querySelector('i');
        if (icon) {
            if (theme === 'dark') {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        }
    }
    
    function setThemeFromStorage() {
        const savedTheme = localStorage.getItem('theme');
        const initialTheme = savedTheme || getSystemTheme();
        document.documentElement.setAttribute('data-theme', initialTheme);
        updateThemeIcon(initialTheme);
    }
    
    document.getElementById('themeToggle')?.addEventListener('click', function() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeIcon(newTheme);
    });

    const systemThemeQuery = window.matchMedia('(prefers-color-scheme: dark)');
    systemThemeQuery.addEventListener('change', (e) => {
        if (!localStorage.getItem('theme')) {
            setThemeFromStorage();
        }
    });
    
    // ==============================================
    // FUNGSI PENILAIAN KEKUATAN PASSWORD (CLIENT SIDE)
    // ==============================================
    function checkPasswordStrength(password) {
        let score = 0;
        const length = password.length;

        if (length < 6) return { level: 0, strength: "Terlalu Pendek", width: 0 };
        
        // Kriteria penilaian
        if (length >= 8) score++; 
        if (password.match(/[A-Z]/)) score++; 
        if (password.match(/[a-z]/)) score++; 
        if (password.match(/[0-9]/)) score++; 
        if (password.match(/[^a-zA-Z0-9\s]/)) score++; // Simbol

        let level, strength, width;

        if (score < 3) {
            strength = "Lemah";
            level = 1;
        } else if (score == 3) {
            strength = "Sedang";
            level = 2;
        } else if (score == 4) {
            strength = "Kuat";
            level = 3;
        } else if (score == 5) {
            strength = "Sangat Kuat";
            level = 4;
        } else {
             strength = "Sangat Lemah";
             level = 0;
        }

        // Atur lebar bar
        switch (level) {
            case 1: width = 25; break;
            case 2: width = 50; break;
            case 3: width = 75; break;
            case 4: width = 100; break;
            default: width = 0;
        }
        
        // Override untuk kriteria wajib (min 6, Kapital, Kecil, Angka)
        if (length >= 6 && (!password.match(/[A-Z]/) || !password.match(/[a-z]/) || !password.match(/[0-9]/))) {
            strength = "Lemah (Wajib Kapital, Kecil, Angka)";
            level = 1;
            width = 25;
        }
        
        return { level, strength, width };
    }

    function updatePasswordStrengthUI(strengthData) {
        const bar = document.getElementById('strength-bar');
        const text = document.getElementById('strength-text');
        const container = document.getElementById('password-strength-container');
        const width = strengthData.width;
        
        if (!bar || !text || !container) return;
        
        bar.style.width = width + '%';
        bar.setAttribute('aria-valuenow', width);
        text.textContent = 'Kekuatan: ' + strengthData.strength;
        
        // Reset class
        bar.classList.remove('bg-danger', 'bg-warning', 'bg-info', 'bg-success');
        text.classList.remove('text-danger', 'text-warning', 'text-info', 'text-success');
        
        // Set class berdasarkan level
        switch (strengthData.level) {
            case 1: 
                bar.classList.add('bg-danger'); 
                text.classList.add('text-danger');
                break;
            case 2: 
                bar.classList.add('bg-warning'); 
                text.classList.add('text-warning');
                break;
            case 3: 
                bar.classList.add('bg-info'); 
                text.classList.add('text-info');
                break;
            case 4: 
                bar.classList.add('bg-success'); 
                text.classList.add('text-success');
                break;
            default: 
                // Level 0 (Terlalu pendek/kosong)
                bar.classList.add('bg-danger');
                text.classList.add('text-danger');
        }

        if (width === 0) {
            container.style.display = 'none';
        } else {
            container.style.display = 'block';
        }
    }

    // Event Listener untuk input password
    document.getElementById('password')?.addEventListener('input', function() {
        const password = this.value;
        const strengthData = checkPasswordStrength(password);
        updatePasswordStrengthUI(strengthData);
    });


    // Form validation
    function validateForm() {
        const passwordInput = document.getElementById('password');
        const password = passwordInput.value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        // Cek Kesamaan Password
        if (password !== confirmPassword) {
            alert('Password dan konfirmasi password tidak cocok!');
            return false;
        }
        
        // Cek Keamanan Password (Harus sinkron dengan PHP: min 6, Kapital, Kecil, Angka)
        if (password.length < 6 || !password.match(/[A-Z]/) || !password.match(/[a-z]/) || !password.match(/[0-9]/)) {
            alert('Password harus minimal 6 karakter dan mengandung huruf kapital, huruf kecil, dan angka!');
            passwordInput.focus();
            return false;
        }
        
        // Loading state
        const btn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
        
        return true;
    }

    // Set tema saat load
    document.addEventListener('DOMContentLoaded', setThemeFromStorage);
    </script>
</body>
</html>