<?php
// ~/Documents/ELIOT/web/reset_password.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php'; // Membutuhkan class Auth dengan isPasswordSecure() dan assessPasswordStrength()

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    redirect('apps/dashboard.php');
}

// Enable error reporting untuk debugging (Hapus/Ganti dengan error handling production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error_message = '';
$success_message = '';
$token = $_GET['token'] ?? '';

$auth = new Auth($conn); // Inisialisasi Auth

// Validasi token
if (empty($token)) {
    $error_message = 'Token reset password tidak valid!';
} else {
    // CEK 1: Validasi format token (harus hex 64 karakter)
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        $error_message = 'Format token tidak valid!';
    } else {
        // CEK 2: Cek token di database
        $query = "SELECT 
                    pr.*, 
                    u.email,
                    u.role,
                    u.status,
                    u.is_deleted
                  FROM password_resets pr 
                  LEFT JOIN users u ON pr.user_id = u.id 
                  WHERE pr.token = ?";
        
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            $error_message = 'Database error: ' . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $token);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                $reset_data = mysqli_fetch_assoc($result);
                
                // CEK 3: Validasi user dan status
                if (!$reset_data['email'] || $reset_data['is_deleted'] == 1 || $reset_data['status'] !== 'aktif') {
                    $error_message = 'Akun tidak valid atau tidak aktif!';
                } 
                // CEK 4: Token sudah digunakan
                elseif ($reset_data['used_at'] !== null) {
                    $error_message = 'Token sudah digunakan!';
                }
                // CEK 5: Token kadaluarsa
                else {
                    $expires_timestamp = strtotime($reset_data['expires_at']);
                    $now_timestamp = time();
                    
                    if ($expires_timestamp <= $now_timestamp) {
                        $error_message = 'Token sudah kadaluarsa!';
                    }
                }
            } else {
                $error_message = 'Token tidak ditemukan di database!';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Proses reset password (hanya jika token valid)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message) && isset($reset_data)) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error_message = 'Password baru dan konfirmasi harus diisi!';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Password dan konfirmasi tidak cocok!';
    } elseif (!$auth->isPasswordSecure($new_password)) { // VALIDASI KEAMANAN BACKEND
        $error_message = 'Password harus terdiri dari minimal 6 karakter, mengandung huruf kapital, huruf kecil, dan angka!';
    } else {
        // Update password user
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        
        if (mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $reset_data['user_id']) && mysqli_stmt_execute($update_stmt)) {
            
            // Tandai token sudah digunakan
            $mark_used = "UPDATE password_resets SET used_at = NOW() WHERE token = ?";
            $mark_stmt = mysqli_prepare($conn, $mark_used);
            if (mysqli_stmt_bind_param($mark_stmt, "s", $token)) {
                mysqli_stmt_execute($mark_stmt);
            }
            mysqli_stmt_close($mark_stmt);
            
            $success_message = 'Password berhasil direset! Silakan login dengan password baru.';
            unset($reset_data); // Clear data agar form tidak ditampilkan lagi
            
        } else {
            $error_message = 'Gagal mereset password. Silakan coba lagi.';
        }
        mysqli_stmt_close($update_stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ELIOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="public/assets/css/style.css">
    <link rel="stylesheet" href="public/assets/css/forgot_password.css">
    
    <style>
        .progress {
            border-radius: 4px;
            height: 6px; /* Tinggikan sedikit untuk visibilitas */
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
                        <p class="mb-4 small opacity-75">Buat password baru untuk akun Anda</p>
                    </div>
                </div>

                <div class="col-md-6 bg-white p-5 d-flex flex-column justify-content-center position-relative">
                    <button class="btn btn-link theme-toggle-btn position-absolute top-0 end-0 m-3" id="themeToggle">
                        <i class="fas fa-moon fs-5"></i>
                    </button>

                    <div class="form-content">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-dark mb-2">Reset Password</h2>
                            <p class="text-secondary small">Buat password baru untuk akun Anda</p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger d-flex align-items-center mb-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <div>
                                    <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success d-flex align-items-center mb-4">
                                <i class="fas fa-check-circle me-2"></i>
                                <div><?= htmlspecialchars($success_message) ?></div>
                            </div>
                            <div class="text-center">
                                <a href="login.php" class="btn btn-custom-green text-white py-2 px-4 rounded-3 fw-bold">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login Sekarang
                                </a>
                            </div>
                        <?php elseif (!isset($reset_data) || !empty($error_message)): ?>
                            <div class="text-center">
                                <p class="text-muted mb-3"><?= htmlspecialchars($error_message ?: 'Link reset password tidak valid.') ?></p>
                                <a href="forgot_password.php" class="btn btn-custom-green text-white py-2 px-4 rounded-3 fw-bold mt-2">
                                    <i class="fas fa-redo me-2"></i>Minta Link Baru
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" class="needs-validation" novalidate>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-secondary">Email</label>
                                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($reset_data['email']) ?>" readonly>
                                    <div class="form-text small text-muted mt-1">
                                        Akun yang akan direset password (<?= htmlspecialchars($reset_data['role']) ?>)
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-secondary">Password Baru <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                                        <input type="password" name="password" class="form-control bg-light border-start-0 ps-0" id="password" placeholder="Minimal 6 karakter" required minlength="6">
                                        <button class="btn btn-light border border-start-0 text-muted" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">
                                        Password minimal 6 karakter
                                    </div>
                                    
                                    <div class="mt-2" id="password-strength-container" style="display:none;">
                                        <div class="progress">
                                            <div id="strength-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <span id="strength-text" class="small fw-bold mt-1 d-block"></span>
                                    </div>
                                    </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-secondary">Konfirmasi Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                                        <input type="password" name="confirm_password" class="form-control bg-light border-start-0 ps-0" id="confirm_password" placeholder="Ulangi password" required minlength="6">
                                        <button class="btn btn-light border border-start-0 text-muted" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">
                                        Harus sama dengan password baru
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-custom-green text-white py-2 rounded-3 fw-bold">
                                        <i class="fas fa-save me-2"></i>Reset Password
                                    </button>
                                </div>
                            </form>
                            
                            <?php endif; ?>

                        <div class="text-center mt-4">
                            <a href="login.php" class="text-decoration-none small text-muted">
                                <i class="fas fa-arrow-left me-1"></i>Kembali ke Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- FUNGSI TEMA (TETAP SAMA) ---
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
            const storedTheme = localStorage.getItem('theme');
            const systemTheme = getSystemTheme();
            const theme = storedTheme || systemTheme;
            
            document.documentElement.setAttribute('data-theme', theme);
            updateThemeIcon(theme);
        }
        
        // Theme Toggle
        document.getElementById('themeToggle')?.addEventListener('click', function() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        // Toggle Password Visibility
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            if (passwordInput) {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            }
        });

        document.getElementById('toggleConfirmPassword')?.addEventListener('click', function() {
            const passwordInput = document.getElementById('confirm_password');
            if (passwordInput) {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
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
            
            if (!bar || !text || !container) return; // Exit jika elemen tidak ada
            
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

            // Tampilkan atau sembunyikan container
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

        // Form Validation (Bootstrap Standard & Custom Confirm)
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    // Validasi konfirmasi password
                    const password = document.getElementById('password');
                    const confirmPassword = document.getElementById('confirm_password');
                    
                    if (password && confirmPassword && password.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Password tidak cocok');
                        confirmPassword.classList.add('is-invalid');
                    } else if (confirmPassword) {
                        confirmPassword.setCustomValidity('');
                        confirmPassword.classList.remove('is-invalid');
                    }
                    
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
            
            // Real-time validation untuk confirm password
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password && confirmPassword) {
                // Gunakan fungsi yang sudah ada untuk validasi real-time
                password.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
            
            function validatePasswords() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Password tidak cocok');
                    confirmPassword.classList.add('is-invalid');
                } else {
                    confirmPassword.setCustomValidity('');
                    confirmPassword.classList.remove('is-invalid');
                }
            }
        })();

        // Set tema saat halaman dimuat
        document.addEventListener('DOMContentLoaded', setThemeFromStorage);
    </script>
</body>
</html>