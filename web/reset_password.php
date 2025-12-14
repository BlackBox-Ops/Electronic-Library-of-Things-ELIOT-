<?php
// ~/Documents/ELIOT/web/reset_password.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    redirect('apps/dashboard.php');
}

// Enable error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error_message = '';
$success_message = '';
$token = $_GET['token'] ?? '';

// Debug token
error_log("=== DEBUG RESET PASSWORD ===");
error_log("Token dari URL: " . $token);

// Validasi token
if (empty($token)) {
    $error_message = 'Token reset password tidak valid!';
    error_log("Error: Token kosong");
} else {
    // CEK 1: Validasi format token (harus hex 64 karakter)
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        $error_message = 'Format token tidak valid!';
        error_log("Error: Format token tidak valid. Panjang: " . strlen($token));
    } else {
        // CEK 2: Cek token di database dengan query yang lebih baik
        $query = "SELECT 
                    pr.*, 
                    u.email,
                    u.role,
                    u.status,
                    u.is_deleted
                  FROM password_resets pr 
                  LEFT JOIN users u ON pr.user_id = u.id 
                  WHERE pr.token = ?";
        
        error_log("Query: " . $query);
        error_log("Parameter token: " . $token);
        
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            $error_message = 'Database error: ' . mysqli_error($conn);
            error_log("Error prepare statement: " . mysqli_error($conn));
        } else {
            mysqli_stmt_bind_param($stmt, "s", $token);
            
            if (!mysqli_stmt_execute($stmt)) {
                $error_message = 'Database error: ' . mysqli_stmt_error($stmt);
                error_log("Error execute statement: " . mysqli_stmt_error($stmt));
            } else {
                $result = mysqli_stmt_get_result($stmt);
                $num_rows = mysqli_num_rows($result);
                
                error_log("Jumlah baris ditemukan: " . $num_rows);
                
                if ($num_rows > 0) {
                    $reset_data = mysqli_fetch_assoc($result);
                    
                    // Debug data
                    error_log("Data ditemukan: " . print_r($reset_data, true));
                    
                    // CEK 3: Validasi user
                    if (!$reset_data['email']) {
                        $error_message = 'User tidak ditemukan!';
                        error_log("Error: User tidak ditemukan");
                    } elseif ($reset_data['is_deleted'] == 1) {
                        $error_message = 'Akun tidak aktif!';
                        error_log("Error: User is_deleted = 1");
                    } elseif ($reset_data['status'] !== 'aktif') {
                        $error_message = 'Akun tidak aktif!';
                        error_log("Error: User status = " . $reset_data['status']);
                    } 
                    // CEK 4: Validasi role (hanya staff dan member)
                    elseif (!in_array($reset_data['role'], ['staff', 'member'])) {
                        $error_message = 'Reset password hanya untuk staff dan member!';
                        error_log("Error: User role = " . $reset_data['role']);
                    }
                    // CEK 5: Token sudah digunakan
                    elseif ($reset_data['used_at'] !== null) {
                        $error_message = 'Token sudah digunakan!';
                        error_log("Error: Token sudah used_at = " . $reset_data['used_at']);
                    }
                    // CEK 6: Token kadaluarsa
                    else {
                        $expires_timestamp = strtotime($reset_data['expires_at']);
                        $now_timestamp = time();
                        
                        error_log("expires_at: " . $reset_data['expires_at'] . " (" . $expires_timestamp . ")");
                        error_log("Sekarang: " . date('Y-m-d H:i:s') . " (" . $now_timestamp . ")");
                        error_log("Sisa waktu: " . ($expires_timestamp - $now_timestamp) . " detik");
                        
                        if ($expires_timestamp <= $now_timestamp) {
                            $error_message = 'Token sudah kadaluarsa!';
                            error_log("Error: Token expired. expires_at <= now");
                        } else {
                            // Semua validasi berhasil
                            error_log("Token VALID!");
                        }
                    }
                } else {
                    $error_message = 'Token tidak ditemukan di database!';
                    error_log("Error: Token tidak ditemukan di database");
                    
                    // Debug: Tampilkan semua token yang ada
                    $debug_query = "SELECT token, expires_at, used_at FROM password_resets LIMIT 5";
                    $debug_result = mysqli_query($conn, $debug_query);
                    error_log("5 token pertama di database:");
                    while ($row = mysqli_fetch_assoc($debug_result)) {
                        error_log("Token: " . substr($row['token'], 0, 20) . "...");
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Proses reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message) && isset($reset_data)) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    error_log("=== PROCESS RESET PASSWORD ===");
    error_log("Password: " . (empty($new_password) ? 'kosong' : 'ada'));
    error_log("Confirm: " . (empty($confirm_password) ? 'kosong' : 'ada'));
    
    if (empty($new_password) || empty($confirm_password)) {
        $error_message = 'Password baru dan konfirmasi harus diisi!';
        error_log("Error: Password kosong");
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Password dan konfirmasi tidak cocok!';
        error_log("Error: Password tidak cocok");
    } elseif (strlen($new_password) < 6) {
        $error_message = 'Password minimal 6 karakter!';
        error_log("Error: Password < 6 karakter");
    } else {
        // Update password user
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        
        if (!$update_stmt) {
            $error_message = 'Database error: ' . mysqli_error($conn);
            error_log("Error prepare update: " . mysqli_error($conn));
        } else {
            mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $reset_data['user_id']);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                $error_message = 'Gagal mereset password. Error: ' . mysqli_stmt_error($update_stmt);
                error_log("Error execute update: " . mysqli_stmt_error($update_stmt));
            } else {
                // Tandai token sudah digunakan
                $mark_used = "UPDATE password_resets SET used_at = NOW() WHERE token = ?";
                $mark_stmt = mysqli_prepare($conn, $mark_used);
                
                if (!$mark_stmt) {
                    error_log("Error prepare mark_used: " . mysqli_error($conn));
                } else {
                    mysqli_stmt_bind_param($mark_stmt, "s", $token);
                    mysqli_stmt_execute($mark_stmt);
                    mysqli_stmt_close($mark_stmt);
                }
                
                $success_message = 'Password berhasil direset! Silakan login dengan password baru.';
                error_log("Success: Password direset untuk user ID " . $reset_data['user_id']);
                
                // Clear $reset_data setelah sukses
                unset($reset_data);
            }
            mysqli_stmt_close($update_stmt);
        }
    }
}

// Hapus data sensitive dari log
error_log("=== END DEBUG ===");
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
        .debug-info {
            background-color: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 0.85rem;
            display: none; /* Sembunyikan di production */
        }
        .debug-toggle {
            position: fixed;
            bottom: 10px;
            right: 10px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="container login-card-container">
            <div class="row g-0 card-shadow overflow-hidden rounded-4">
                <!-- Panel Kiri dengan Background -->
                <div class="col-md-6 d-none d-md-flex align-items-center justify-content-center left-panel position-relative">
                    <div class="overlay-content text-center text-white p-5">
                        <h1 class="display-4 fw-bold mb-3">ELIOT</h1>
                        <p class="lead mb-4">Electronic Library of Things</p>
                        <p class="mb-4 small opacity-75">Buat password baru untuk akun Anda</p>
                        
                        <!-- Debug info di panel kiri -->
                        <?php if (!empty($token) && !empty($error_message) && $error_message !== 'Token reset password tidak valid!'): ?>
                        <div class="debug-info bg-dark bg-opacity-25 mt-4 p-3 rounded">
                            <small>
                                <strong>Debug Info:</strong><br>
                                Token: <?= htmlspecialchars(substr($token, 0, 20)) ?>...<br>
                                Error: <?= htmlspecialchars($error_message) ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Panel Kanan - Form -->
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
                                    <?php if ($error_message === 'Token tidak ditemukan di database!'): ?>
                                    <br><small class="text-muted">Pastikan Anda menggunakan link yang benar dari email.</small>
                                    <?php endif; ?>
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
                            
                            <!-- Debug info tambahan -->
                            <div class="mt-3 small text-muted">
                                <details>
                                    <summary class="cursor-pointer">Info Token</summary>
                                    <div class="mt-2 p-2 bg-light rounded">
                                        Token: <?= htmlspecialchars(substr($token, 0, 20)) ?>...<br>
                                        Berakhir: <?= htmlspecialchars($reset_data['expires_at']) ?><br>
                                        Dibuat: <?= htmlspecialchars($reset_data['created_at']) ?>
                                    </div>
                                </details>
                            </div>
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

    <!-- Debug Toggle Button -->
    <button class="btn btn-sm btn-outline-secondary debug-toggle" onclick="toggleDebug()">
        <i class="fas fa-bug"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk set tema dari localStorage
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

        // Form Validation dengan custom confirm password
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

        // Toggle debug info
        function toggleDebug() {
            const debugElements = document.querySelectorAll('.debug-info');
            debugElements.forEach(el => {
                el.style.display = el.style.display === 'none' ? 'block' : 'none';
            });
        }

        // Set tema saat halaman dimuat
        document.addEventListener('DOMContentLoaded', setThemeFromStorage);
    </script>
</body>
</html>