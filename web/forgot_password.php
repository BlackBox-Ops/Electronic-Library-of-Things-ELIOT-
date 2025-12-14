<?php
// ~/Documents/ELIOT/web/forgot_password.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    redirect('apps/dashboard.php');
}

$message = '';
$message_type = 'info'; // info, success, danger

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Email wajib diisi!';
        $message_type = 'danger';
    } else {
        // Cek apakah email terdaftar
        $auth = new Auth($conn);
        $user = $auth->getUserByEmail($email);
        
        if ($user) {
            // Cek role - hanya staff dan member yang bisa reset password
            if ($user['role'] === 'staff' || $user['role'] === 'member') {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Simpan token ke database
                $query = "INSERT INTO password_resets (user_id, token, expires_at) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE token = ?, expires_at = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "issss", $user['id'], $token, $expires_at, $token, $expires_at);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Untuk prototype, kita langsung redirect ke reset page dengan token
                    // (Di production, seharusnya kirim email dengan link reset)
                    redirect("reset_password.php?token=$token");
                } else {
                    $message = 'Gagal memproses permintaan. Silakan coba lagi.';
                    $message_type = 'danger';
                }
            } else {
                $message = 'Reset password hanya untuk staff dan member. Admin harap hubungi administrator.';
                $message_type = 'danger';
            }
        } else {
            $message = 'Email tidak terdaftar dalam sistem.';
            $message_type = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - ELIOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="public/assets/css/style.css">
    <link rel="stylesheet" href="public/assets/css/forgot_password.css">
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
                        <p class="mb-4 small opacity-75">Reset password Anda dengan mudah</p>
                        
                        <!-- Informasi -->
                        <div class="steps-container mt-4">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-text">Masukkan email</div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-text">Verifikasi identitas</div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-text">Buat password baru</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel Kanan - Form -->
                <div class="col-md-6 bg-white p-5 d-flex flex-column justify-content-center position-relative">
                    <button class="btn btn-link theme-toggle-btn position-absolute top-0 end-0 m-3" id="themeToggle">
                        <i class="fas fa-moon fs-5"></i>
                    </button>

                    <div class="form-content">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-dark mb-2">Lupa Password</h2>
                            <p class="text-secondary small">Reset password akun Anda</p>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type ?> d-flex align-items-center mb-4">
                                <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : ($message_type == 'danger' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
                                <div><?= htmlspecialchars($message) ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Email Terdaftar</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                                    <input type="email" name="email" class="form-control bg-light border-start-0 ps-0" placeholder="user@eliot.co.id" required>
                                </div>
                                <div class="form-text small text-muted mt-1">
                                    Masukkan email yang terdaftar pada akun ELIOT Anda.
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-custom-green text-white py-2 rounded-3 fw-bold">
                                    <i class="fas fa-paper-plane me-2"></i>Kirim Link Reset
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="small text-secondary">
                                <a href="login.php" class="text-decoration-none fw-bold" style="color: var(--color-green-primary);">
                                    <i class="fas fa-arrow-left me-1"></i>Kembali ke Login
                                </a>
                            </p>
                            <p class="small text-muted mt-2">
                                Hanya untuk reset password <strong>staff dan member</strong>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
        
        // Theme Toggle dengan localStorage
        document.getElementById('themeToggle').addEventListener('click', function() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        // Form Validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Set tema saat halaman dimuat
        document.addEventListener('DOMContentLoaded', setThemeFromStorage);
    </script>
</body>
</html>