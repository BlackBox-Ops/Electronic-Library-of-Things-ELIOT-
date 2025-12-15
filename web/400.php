<?php
// ~/Documents/ELIOT/web/400.php

// Set HTTP 400: Wajib agar browser tahu ini adalah error Bad Request
http_response_code(400);

// Log error (optional)
error_log("[ELIOT 400] Bad Request: " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . " - " . date('Y-m-d H:i:s'));

// Base URL (Harap sesuaikan $base_url jika struktur folder Anda berbeda)
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
    <title>400 - Permintaan Buruk | ELIOT</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <link rel="stylesheet" href="<?= $base_url ?>/public/assets/css/400.css">
</head>
<body>
    <div class="error-container">
        <div class="error-card">
        
            <button class="btn btn-link theme-toggle-btn position-absolute top-0 end-0 m-3" id="themeToggle">
                <i class="fas fa-moon fs-5"></i>
            </button>

            <div class="error-left">
                <div class="error-image">
                    <span class="error-code fall-object">
                        400
                    </span>
                </div>
            </div>
            
            <div class="error-right">
                <div class="error-content">
                    <h1 class="mb-3">400 Bad Request</h1>
                    <p class="lead text-secondary mb-4">
                        Permintaan yang Anda kirimkan tidak dapat diproses oleh server karena format atau data yang tidak valid.
                    </p>
                    <p class="text-secondary small">
                        Silakan periksa kembali data yang Anda kirimkan (misalnya, form yang tidak lengkap, format file yang salah, atau data yang mengandung karakter terlarang).
                    </p>

                    <div class="action-buttons mt-4">
                        <div class="row g-3">
                            <div class="col-12 col-sm-auto">
                                <a href="javascript:void(0)" onclick="goBackSafely()" class="btn btn-primary-custom w-100">
                                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Halaman Sebelumnya
                                </a>
                            </div>
                            <div class="col-12 col-sm-auto">
                                <a href="<?= $base_url ?>/login.php" class="btn btn-secondary-custom w-100">
                                    <i class="fas fa-home me-2"></i> Ke Halaman Utama
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="error-footer mt-5">
                    <div class="logo-back-link">
                        <img src="<?= $base_url ?>/public/assets/img/rfid.png" alt="ELIOT" class="rfid-logo">
                        <span class="text-muted small">ELIOT - Integrated Asset Management</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const html = document.documentElement;
        
        function getSystemTheme() {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        function updateThemeIcon(theme) {
            const themeToggle = document.getElementById('themeToggle');
            if (!themeToggle) return;
            const icon = themeToggle.querySelector('i');
            if (!icon) return;
            
            if (theme === 'dark') {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
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

        // Fungsi kembali aman (sama seperti 404/500)
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