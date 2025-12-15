<?php
// ~/Documents/ELIOT/web/404.php

// Set HTTP 404
http_response_code(404);

// Log error (optional)
error_log("[ELIOT 404] " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . " - " . date('Y-m-d H:i:s'));

// Base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Asumsi path base_url adalah /eliot
$base_url = $protocol . '://' . $host . '/eliot'; 
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan | ELIOT</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <link rel="stylesheet" href="<?= $base_url ?>/public/assets/css/404.css">
</head>
<body>
    <div class="error-container">
        <div class="error-card">
        
            <button class="btn btn-link theme-toggle-btn position-absolute top-0 end-0 m-3" id="themeToggle">
                <i class="fas fa-moon fs-5"></i>
            </button>

            <div class="error-left">
                <div class="error-image">
                    <span class="error-code">404</span>
                    </div>
            </div>
            
            <div class="error-right">
                <div class="error-content">
                    
                    <a href="<?= $base_url ?>/login.php" class="logo-back-link mb-3">
                        <img src="<?= $base_url ?>/public/assets/img/rfid.png" alt="ELIOT Logo" class="rfid-logo">
                        <span class="fs-5 fw-bold text-primary">ELIOT</span>
                    </a>
                    
                    <h1 class="text-primary mt-3">Ups! Halaman Tidak Ditemukan.</h1>
                    
                    <p class="text-secondary error-message">
                        Kami tidak dapat menemukan halaman yang Anda cari. Mungkin Anda salah mengetik alamat, atau halaman tersebut telah dipindahkan.
                    </p>
                    
                    <div class="action-buttons mt-4">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <a href="<?= $base_url ?>/login.php" class="btn-primary-custom w-100">
                                    <i class="fas fa-home me-2"></i> Ke Halaman Utama
                                </a>
                            </div>
                            <div class="col-md-6">
                                <button type="button" onclick="goBackSafely()" class="btn-secondary-custom w-100" id="backButton">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali ke page
                                </button>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="mailto:admin@eliot.co.id" class="text-decoration-none small">
                                <i class="fas fa-envelope me-1"></i> Laporkan
                            </a>
                        </div>
                    </div>
                    
                    <div class="error-footer mt-4">
                        <p class="small mb-1 text-secondary">
                            © 2024 <span class="eliot-brand">ELIOT</span> - Electronic Library of Things
                        </p>
                        <p class="small mb-0 text-muted">
                            Sistem Manajemen Perpustakaan Berbasis RFID
                        </p>
                    </div>
                    
                </div>
            </div>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const html = document.documentElement;

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

        function getSystemTheme() {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        function applyTheme(theme) {
            html.setAttribute('data-theme', theme);
            updateThemeIcon(theme);
        }

        function setThemeOnLoad() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                applyTheme(savedTheme);
            } else {
                applyTheme(getSystemTheme());
            }
        }
        
        // Listener untuk perubahan tema sistem (Media Query)
        const systemThemeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        systemThemeQuery.addEventListener('change', (e) => {
            if (!localStorage.getItem('theme')) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });

        // Theme Toggle dengan localStorage
        document.getElementById('themeToggle').addEventListener('click', function() {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            applyTheme(newTheme);
            localStorage.setItem('theme', newTheme);
        });

        // Set tema saat halaman dimuat
        document.addEventListener('DOMContentLoaded', setThemeOnLoad);

        // Fungsi kembali aman
        function goBackSafely() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                // Fallback jika tidak ada riwayat
                window.location.href = '<?= $base_url ?>/login.php'; 
            }
        }
    </script>
</body>
</html>