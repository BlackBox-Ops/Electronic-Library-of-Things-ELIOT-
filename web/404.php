<?php
// ~/Documents/ELIOT/web/404.php

// Set HTTP 404
http_response_code(404);

// Log error (optional)
error_log("[ELIOT 404] " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . " - " . date('Y-m-d H:i:s'));

// Base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . '://' . $host . '/eliot';
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan | ELIOT</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Custom 404 CSS -->
    <link rel="stylesheet" href="<?= $base_url ?>/public/assets/css/404.css">
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            
            <!-- Left Panel: SVG Illustration -->
            <div class="error-left">
                <div class="error-illustration">
                    <svg class="error-image" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">
                        <!-- Background circle -->
                        <circle cx="200" cy="150" r="120" fill="currentColor" opacity="0.1"/>
                        
                        <!-- Book stack -->
                        <rect x="150" y="100" width="100" height="15" fill="#628141" rx="2"/>
                        <rect x="145" y="120" width="110" height="15" fill="#506834" rx="2"/>
                        <rect x="155" y="140" width="90" height="15" fill="#8BA76C" rx="2"/>
                        
                        <!-- Magnifying glass -->
                        <circle cx="240" cy="90" r="25" fill="none" stroke="#628141" stroke-width="4"/>
                        <line x1="257" y1="107" x2="280" y2="130" stroke="#628141" stroke-width="4" stroke-linecap="round"/>
                        
                        <!-- Question mark -->
                        <text x="200" y="220" text-anchor="middle" fill="#628141" font-size="60" font-weight="bold">?</text>
                        
                        <!-- Decorative elements -->
                        <circle cx="120" cy="80" r="8" fill="#628141" opacity="0.3"/>
                        <circle cx="280" cy="190" r="12" fill="#8BA76C" opacity="0.3"/>
                        <circle cx="150" cy="220" r="6" fill="#506834" opacity="0.3"/>
                    </svg>
                    
                    <div class="illustration-text">
                        <h3>Oops! Halaman Hilang</h3>
                        <p>Sepertinya kita tersesat di perpustakaan digital...</p>
                    </div>
                </div>
            </div>
            
            <!-- Right Panel: Content -->
            <div class="error-right">
                
                <!-- Theme Toggle -->
                <button class="theme-toggle-btn" id="themeToggle" type="button" aria-label="Toggle dark mode">
                    <i class="fas fa-moon"></i>
                </button>
                
                <div class="error-content">
                    
                    <!-- Back to ELIOT Link -->
                    <div class="text-end mb-4">
                        <a href="<?= $base_url ?>/index.php" class="back-link">
                        <span class="logo-container">
                            <img src="public/assets/img/rfid.png" alt="RFID Logo" class="rfid-logo">
                        </span>
                        <span class="link-text">
                            <span class="small">kembali ke</span>
                            <span class="eliot-brand"> ELIOT</span>
                        </span>
                    </div>
                    
                    <!-- 404 Code -->
                    <div class="error-code mb-3">
                        <h1>404</h1>
                    </div>
                    
                    <!-- Title & Description -->
                    <h2>Halaman Tidak Ditemukan</h2>
                    <p>
                        Maaf, halaman yang Anda cari tidak dapat ditemukan. 
                        Mungkin telah dipindahkan, dihapus, atau belum tersedia.
                    </p>
                    
                    <!-- Action Buttons -->
                    <div class="d-grid gap-3">
                        <a href="<?= $base_url ?>/login.php" class="btn-primary-custom">
                            <i class="fas fa-sign-in-alt me-2"></i>Kembali ke Login
                        </a>
                        
                        <div class="row g-2">
                            <!-- Hanya tombol Kembali saja -->
                            <div class="col-12">
                                <button onclick="goBackSafely()" class="btn-secondary-custom w-100" id="backButton">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali ke page
                                </button>
                            </div>
                        </div>
                        
                        <!-- Additional Links -->
                        <div class="text-center mt-3">
                            <a href="mailto:admin@eliot.co.id" class="text-decoration-none small">
                                <i class="fas fa-envelope me-1"></i> Laporkan
                            </a>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="error-footer mt-4">
                        <p class="small mb-1">
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom 404 JS -->
    <script src="<?= $base_url ?>/public/assets/js/404.js"></script>
</body>
</html>