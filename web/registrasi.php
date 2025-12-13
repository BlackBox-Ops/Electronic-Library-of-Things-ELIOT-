<?php
// ~/Documents/ELIOT/web/registrasi.php - VERSI SIMPLE

// ERROR REPORTING
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session untuk cek login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/functions.php';

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
    
    // VALIDASI INPUT
    if (empty($nama) || empty($email) || empty($password)) {
        $error_message = 'Nama, email, dan password wajib diisi!';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Password dan konfirmasi tidak cocok!';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password minimal 6 karakter!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format email tidak valid!';
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
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="container login-card-container">
            <div class="row g-0 card-shadow overflow-hidden rounded-4">
                
                <!-- Panel Kiri - Hanya Logo & Deskripsi -->
                <div class="col-md-6 d-none d-md-flex align-items-center justify-content-center left-panel position-relative">
                    <div class="overlay-content text-center text-white p-5">
                        <h1 class="display-4 fw-bold mb-3">ELIOT</h1>
                        <p class="lead mb-4">Electronic Library of Things</p>
                    </div>
                </div>

                <!-- Panel Kanan - Form -->
                <div class="col-md-6 bg-white p-5 d-flex flex-column justify-content-center position-relative">
                    
                    <button class="btn btn-link theme-toggle-btn position-absolute top-0 end-0 m-3 text-decoration-none" id="themeToggle" type="button">
                        <i class="fas fa-moon fs-5"></i>
                    </button>

                    <div class="form-content">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-dark mb-1"><?= htmlspecialchars($form_title) ?></h2>
                            <p class="text-secondary small mb-0"><?= htmlspecialchars($form_subtitle) ?></p>
                        </div>

                        <!-- ALERT SUCCESS -->
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

                        <!-- ALERT ERROR -->
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

                        <!-- FORM REGISTRASI -->
                        <form method="POST" enctype="multipart/form-data" id="registerForm" onsubmit="return validateForm()">
                            
                            <!-- Nama -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama" class="form-control" placeholder="Nama lengkap" required 
                                       value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
                            </div>

                            <!-- Email -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="email@example.com" required 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- No Identitas -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold">No Identitas (NIM/NIP)</label>
                                <input type="text" name="no_identitas" class="form-control" placeholder="NIM/NIP" 
                                       value="<?= htmlspecialchars($_POST['no_identitas'] ?? '') ?>">
                            </div>

                            <!-- No Telepon -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold">No Telepon</label>
                                <input type="tel" name="no_telepon" class="form-control" placeholder="08123456789" 
                                       value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>">
                            </div>

                            <!-- Alamat -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Alamat</label>
                                <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat lengkap"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                            </div>

                            <!-- ROLE SELECTION (hanya untuk admin) -->
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

                            <!-- Password -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" class="form-control" 
                                           placeholder="Minimal 6 karakter" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Konfirmasi Password -->
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

                            <!-- Foto Profil -->
                            <div class="mb-4">
                                <label class="form-label small fw-bold">Foto Profil <span class="text-muted">(Opsional)</span></label>
                                <input type="file" name="foto" class="form-control form-control-sm" accept="image/*">
                                <small class="text-muted d-block mt-1">
                                    Max 2MB - JPG, PNG, GIF
                                </small>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-custom-green text-white fw-bold" id="submitBtn">
                                    <i class="fas fa-user-plus me-2"></i>
                                    <span id="btnText">
                                        <?= htmlspecialchars($submit_text) ?>
                                    </span>
                                </button>
                            </div>
                        </form>

                        <!-- Link Kembali -->
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

    // Theme toggle
    function setThemeFromStorage() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);
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
    
    document.getElementById('themeToggle')?.addEventListener('click', function() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeIcon(newTheme);
    });

    // Form validation
    function validateForm() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password !== confirmPassword) {
            alert('Password dan konfirmasi password tidak cocok!');
            return false;
        }
        
        if (password.length < 6) {
            alert('Password minimal 6 karakter!');
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