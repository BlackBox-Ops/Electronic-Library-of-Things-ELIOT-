<?php
// ~/Documents/ELIOT/web/registrasi.php - VERSI SIMPLE

// ERROR REPORTING
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Variabel untuk status
$error_message = '';
$success_message = '';

// ========================================
// CEK: APAKAH SUDAH ADA ADMIN?
// ========================================
$stmt_check_admin = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_deleted = 0");
$stmt_check_admin->execute();
$result_check_admin = $stmt_check_admin->get_result();
$row_admin = $result_check_admin->fetch_row();
$admin_exists = $row_admin[0] > 0;
$stmt_check_admin->close();

// ========================================
// LOGIC ACCESS CONTROL
// ========================================
if ($admin_exists) {
    requireRole(['admin']);
    $allowed_roles = ['member', 'staff', 'admin'];
    $default_role = 'member';
    $is_first_setup = false;
} else {
    $allowed_roles = ['admin'];
    $default_role = 'admin';
    $is_first_setup = true;
}

// ========================================
// PROSES FORM SUBMISSION - VERSI SIMPLE
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
            // SOLUSI UPLOAD FOTO YANG SANGAT SEDERHANA
            // ============================================================
            $foto_path = null;

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                // Validasi sederhana
                $max_size = 2 * 1024 * 1024; // 2MB
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $file_extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                
                if ($_FILES['foto']['size'] > $max_size) {
                    $error_message = 'Ukuran foto maksimal 2MB.';
                } elseif (!in_array($file_extension, $allowed_extensions)) {
                    $error_message = 'Format foto harus JPG, PNG, atau GIF.';
                } else {
                    // **PERBAIKAN PATH DI SINI**
                    // Gunakan path absolut ke folder upload
                    $upload_dir = '/home/user/Documents/ELIOT/web/public/assets/img/profiles/';
                    
                    // Debug: tampilkan path
                    error_log("Upload dir: $upload_dir");
                    
                    // Pastikan folder ada
                    if (!file_exists($upload_dir)) {
                        if (!mkdir($upload_dir, 0775, true)) {
                            $error_message = 'Gagal membuat folder upload: ' . $upload_dir;
                            $foto_path = null;
                        }
                    }
                    
                    // Periksa apakah folder writable
                    if (file_exists($upload_dir) && is_writable($upload_dir)) {
                        $filename = 'profile_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $target_file = $upload_dir . $filename;
                        
                        // Debug info
                        error_log("Target file: $target_file");
                        error_log("Temp file: " . $_FILES['foto']['tmp_name']);
                        error_log("Is writable: " . (is_writable($upload_dir) ? 'yes' : 'no'));
                        
                        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                            // **PATH RELATIF untuk database**
                            $foto_path = 'public/assets/img/profiles/' . $filename;
                            error_log("Foto berhasil diupload ke: $foto_path");
                        } else {
                            $upload_error = error_get_last();
                            $error_message = 'Upload foto gagal: ' . ($upload_error['message'] ?? 'Unknown error');
                            error_log("Upload error: " . print_r($upload_error, true));
                            $foto_path = null;
                        }
                    } else {
                        $error_message = 'Folder upload tidak bisa ditulis: ' . $upload_dir;
                        error_log("Folder not writable: $upload_dir");
                        $foto_path = null;
                    }
                }
                
                // Jika ada error upload foto, reset agar bisa lanjut tanpa foto
                if (!empty($error_message) && strpos($error_message, 'foto') !== false) {
                    // Reset error message untuk upload foto
                    $error_message = '';
                    $foto_path = null;
                }
            }
                        
            // INSERT USER (jika tidak ada error validasi utama)
            if (empty($error_message)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Tentukan query berdasarkan ada/tidaknya foto
                if ($foto_path) {
                    $stmt_insert = $conn->prepare("INSERT INTO users 
                        (nama, email, no_identitas, no_telepon, alamat, password_hash, role, status, foto_path, created_at, updated_at, is_deleted)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'aktif', ?, NOW(), NOW(), 0)");
                    
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("ssssssss", $nama, $email, $no_identitas, $no_telepon, $alamat, $password_hash, $role, $foto_path);
                    } else {
                        $error_message = 'Error preparing statement: ' . $conn->error;
                    }
                } else {
                    $stmt_insert = $conn->prepare("INSERT INTO users 
                        (nama, email, no_identitas, no_telepon, alamat, password_hash, role, status, created_at, updated_at, is_deleted)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'aktif', NOW(), NOW(), 0)");
                    
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("sssssss", $nama, $email, $no_identitas, $no_telepon, $alamat, $password_hash, $role);
                    } else {
                        $error_message = 'Error preparing statement: ' . $conn->error;
                    }
                }
                
                // Jika statement berhasil dibuat
                if (empty($error_message) && $stmt_insert) {
                    if ($stmt_insert->execute()) {
                        $success_message = "User '$nama' berhasil dibuat dengan role '$role'!";
                        
                        // Redirect berdasarkan kondisi
                        if ($is_first_setup) {
                            // Admin pertama → redirect ke login
                            echo "<script>
                                setTimeout(function(){ 
                                    alert('Admin utama berhasil dibuat! Silakan login.');
                                    window.location.href = 'login.php'; 
                                }, 2000);
                            </script>";
                        } else {
                            // Admin membuat user baru → redirect ke dashboard
                            echo "<script>
                                setTimeout(function(){ 
                                    window.location.href = 'apps/dashboard.php'; 
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
    <title>Registrasi User - ELIOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="public/assets/css/style.css">
    <link rel="stylesheet" href="public/assets/css/registrasi.css">
    <style>
        /* Styling untuk alert */
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
        
        /* Tema dark untuk alert close */
        [data-theme="dark"] .alert-dismissible .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="container login-card-container">
            <div class="row g-0 card-shadow overflow-hidden rounded-4">
                
                <!-- Panel Kiri -->
                <div class="col-md-6 d-none d-md-flex align-items-center justify-content-center left-panel position-relative">
                    <div class="overlay-content text-center text-white p-5">
                        <h1 class="display-4 fw-bold mb-3">ELIOT</h1>
                        <p class="lead mb-4">Electronic Library of Things</p>
                        
                        <?php if ($is_first_setup): ?>
                            <div class="alert alert-warning bg-white text-dark border-0 shadow-sm">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Setup Awal</strong><br>
                                <small>Buat akun Admin pertama</small>
                            </div>
                        <?php else: ?>
                            <p class="mb-4 small opacity-90">Panel Admin - Buat User Baru</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Panel Kanan - Form -->
                <div class="col-md-6 bg-white p-5 d-flex flex-column justify-content-center position-relative">
                    
                    <button class="btn btn-link theme-toggle-btn position-absolute top-0 end-0 m-3 text-decoration-none" id="themeToggle" type="button">
                        <i class="fas fa-moon fs-5"></i>
                    </button>

                    <div class="form-content">
                        <div class="text-center mb-3">
                            <?php if ($is_first_setup): ?>
                                <h2 class="fw-bold text-dark mb-1">Setup Admin Utama</h2>
                                <p class="text-secondary small mb-0">Langkah pertama menggunakan ELIOT</p>
                            <?php else: ?>
                                <h2 class="fw-bold text-dark mb-1">Buat User Baru</h2>
                                <p class="text-secondary small mb-0">Panel Administrator</p>
                            <?php endif; ?>
                        </div>

                        <!-- ALERT SUCCESS -->
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-check-circle me-2 mt-1 fs-5"></i>
                                    <div class="flex-grow-1">
                                        <?= $success_message ?>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- ALERT ERROR -->
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
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

                            <!-- ROLE SELECTION -->
                            <?php if (!$is_first_setup && count($allowed_roles) > 1): ?>
                            <div class="mb-2">
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
                                <input type="hidden" name="role" value="<?= $default_role ?>">
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
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Foto Profil <span class="text-muted">(Opsional)</span></label>
                                <input type="file" name="foto" class="form-control form-control-sm" accept="image/*">
                                <small class="text-muted d-block mt-1">
                                    Max 2MB - JPG, PNG, GIF (Untuk demo, bisa skip)
                                </small>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-custom-green text-white fw-bold" id="submitBtn">
                                    <i class="fas fa-user-plus me-2"></i>
                                    <span id="btnText">
                                        <?= $is_first_setup ? 'Buat Admin & Mulai' : 'Buat User Baru' ?>
                                    </span>
                                </button>
                            </div>
                        </form>

                        <!-- Link Kembali -->
                        <div class="text-center mt-3">
                            <?php if ($is_first_setup): ?>
                                <a href="login.php" class="text-decoration-none small text-muted">
                                    <i class="fas fa-arrow-left me-1"></i> Sudah punya akun? Login
                                </a>
                            <?php else: ?>
                                <a href="apps/dashboard.php" class="text-decoration-none small text-muted">
                                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
                                </a>
                            <?php endif; ?>
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