<?php
// ~/Documents/ELIOT/web/login.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('apps/dashboard.php');
}

$auth = new Auth($conn);
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = 'Email dan password wajib diisi!';
    } else {
        $result = $auth->login($email, $password);
        if ($result === true) {
            redirect('apps/dashboard.php');
        } else {
            $error_message = $result;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ELIOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="public/assets/css/login.css">
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
                        <p class="mb-4 small opacity-75">Sistem manajemen perpustakaan berbasis RFID</p>

                        <!-- Tombol Registrasi Minimalis -->
                        <a href="registrasi.php" class="btn btn-registrasi fw-bold">
                            Registrasi Akun
                        </a>
                    </div>
                </div>

                <!-- Panel Kanan - Form Login -->
                <div class="col-md-6 bg-white p-5 d-flex flex-column justify-content-center position-relative">
                    <button class="btn btn-link theme-toggle-btn position-absolute top-0 end-0 m-3" id="themeToggle">
                        <i class="fas fa-moon fs-5"></i>
                    </button>

                    <div class="form-content">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-dark">Selamat Datang</h2>
                            <p class="text-secondary small">Masuk ke akun Anda</p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger d-flex align-items-center mb-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <div><?= htmlspecialchars($error_message) ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                                    <input type="email" name="email" class="form-control bg-light border-start-0 ps-0" placeholder="Masukkan email" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                                    <input type="password" name="password" class="form-control bg-light border-start-0 border-end-0 ps-0" id="password" placeholder="Masukkan password" required>
                                    <button class="btn btn-light border border-start-0 text-muted" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                
                                <!-- TAMBAHAN: Link Lupa Password -->
                                <div class="mt-2 text-end">
                                    <a href="forgot_password.php" class="text-decoration-none small text-muted">
                                        Lupa password?
                                    </a>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-custom-green text-white py-2 rounded-3 fw-bold btn-login">
                                    Login Sekarang
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4 d-md-none">
                            <p class="small text-secondary">Kembali ke <a href="index.php" class="text-decoration-none fw-bold" style="color: var(--color-green-primary);">Katalog Buku</a></p>
                        </div>
                        <div class="text-center mt-4">
                            <p class="mb-2">
                                <a href="registrasi.php" class="text-decoration-none fw-bold">
                                    <i class="fas fa-user-plus me-1"></i> Belum punya akun? Daftar Member
                                </a>
                            </p>
                            <p class="small text-muted">
                                Daftar sebagai member untuk meminjam buku di ELIOT
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
        
        // Toggle Password Visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

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