<?php
// ~/Documents/ELIOT/web/500.php

// Set HTTP 500
http_response_code(500);

// Log error (optional)
error_log("[ELIOT 500] " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . " - " . date('Y-m-d H:i:s'));

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
    <title>500 - Server Error | ELIOT</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Custom Error CSS (reuse 404.css) -->
    <link rel="stylesheet" href="<?= $base_url ?>/public/assets/css/404.css">
    
    <!-- Additional CSS for 500 page -->
    <style>
        :root {
            --color-error: #dc3545;
            --color-warning: #ffc107;
        }
        
        [data-theme="dark"] {
            --color-error: #f87171;
            --color-warning: #fbbf24;
        }
        
        .error-code h1.error-500 {
            color: var(--color-error);
        }
        
        .error-badge {
            background: rgba(220, 53, 69, 0.1);
            color: var(--color-error);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        [data-theme="dark"] .error-badge {
            background: rgba(248, 113, 113, 0.1);
            border-color: rgba(248, 113, 113, 0.3);
        }
        
        .info-box {
            background: rgba(98, 129, 65, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid var(--color-green-primary);
        }
        
        [data-theme="dark"] .info-box {
            background: rgba(139, 167, 108, 0.1);
        }
        
        .info-box h6 {
            color: var(--color-green-primary);
            font-weight: 600;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .status-item i {
            width: 20px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            
            <!-- Left Panel: Server Error Illustration -->
            <div class="error-left">
                <div class="error-illustration">
                    <svg class="error-image" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">
                        <!-- Background circle with error color -->
                        <circle cx="200" cy="150" r="120" fill="var(--color-error)" opacity="0.1"/>
                        
                        <!-- Server rack -->
                        <rect x="140" y="100" width="120" height="80" fill="var(--color-green-primary)" rx="5"/>
                        
                        <!-- Server lights (error state - red) -->
                        <circle cx="155" cy="115" r="3" fill="var(--color-error)"/>
                        <circle cx="165" cy="115" r="3" fill="var(--color-error)"/>
                        <circle cx="175" cy="115" r="3" fill="var(--color-error)"/>
                        
                        <!-- Server panels -->
                        <rect x="150" y="130" width="100" height="8" fill="currentColor" opacity="0.3" rx="2"/>
                        <rect x="150" y="145" width="100" height="8" fill="currentColor" opacity="0.3" rx="2"/>
                        <rect x="150" y="160" width="100" height="8" fill="currentColor" opacity="0.3" rx="2"/>
                        
                        <!-- Warning triangle -->
                        <path d="M200,80 L230,130 L170,130 Z" fill="var(--color-warning)" opacity="0.9"/>
                        <circle cx="200" cy="105" r="6" fill="white"/>
                        <line x1="200" y1="100" x2="200" y2="110" stroke="var(--color-error)" stroke-width="2"/>
                        
                        <!-- Broken connection lines -->
                        <line x1="100" y1="150" x2="140" y2="150" stroke="var(--color-error)" stroke-width="2" stroke-dasharray="4"/>
                        <line x1="260" y1="150" x2="300" y2="150" stroke="var(--color-error)" stroke-width="2" stroke-dasharray="4"/>
                        
                        <!-- Error text -->
                        <text x="200" y="220" text-anchor="middle" fill="var(--color-error)" font-size="40" font-weight="bold">500</text>
                        
                        <!-- Decorative elements -->
                        <circle cx="100" cy="80" r="8" fill="var(--color-green-primary)" opacity="0.3"/>
                        <circle cx="300" cy="190" r="12" fill="var(--color-green-primary)" opacity="0.3"/>
                        <circle cx="120" cy="220" r="6" fill="var(--color-green-primary)" opacity="0.3"/>
                    </svg>
                    
                    <div class="illustration-text">
                        <h3>Oops! Server Error</h3>
                        <p>Sistem sedang mengalami masalah teknis...</p>
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
                    
                    <!-- Back to ELIOT Link with RFID Logo -->
                    <div class="text-end mb-4">
                        <a href="<?= $base_url ?>/index.php" class="back-link logo-back-link">
                            <svg class="rfid-logo" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                    xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 6H20V18H4V6Z" stroke="currentColor" stroke-width="2" 
                                        stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M8 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M8 14H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <rect x="6" y="8" width="4" height="4" rx="1" fill="currentColor" opacity="0.5"/>
                            </svg>
                            <div>
                                <span class="small">kembali ke</span>
                                <span class="eliot-brand"> ELIOT</span>
                            </div>
                        </a>
                    </div>
                    
                    <!-- Error Badge & Code -->
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="error-badge">
                            <i class="fas fa-server me-1"></i> Server Error
                        </span>
                        <div class="error-code">
                            <h1 class="error-500">500</h1>
                        </div>
                    </div>
                    
                    <!-- Title & Description -->
                    <h2 class="mb-3">Server Mengalami Masalah</h2>
                    <p class="mb-4">
                        Maaf, server sedang mengalami kesalahan internal. 
                        Tim teknis kami telah diberitahu dan sedang memperbaiki masalah ini.
                    </p>
                    
                    <!-- What to Do (User-friendly guidance) -->
                    <div class="info-box mb-4">
                        <h6 class="mb-3"><i class="fas fa-lightbulb me-2"></i>Yang bisa Anda lakukan:</h6>
                        <div class="status-item">
                            <i class="fas fa-redo text-success"></i>
                            <span>Coba refresh halaman setelah beberapa menit</span>
                        </div>
                        <div class="status-item">
                            <i class="fas fa-wifi text-success"></i>
                            <span>Periksa koneksi internet Anda</span>
                        </div>
                        <div class="status-item">
                            <i class="fas fa-history text-success"></i>
                            <span>Coba akses kembali nanti</span>
                        </div>
                        <div class="status-item">
                            <i class="fas fa-envelope text-success"></i>
                            <span>Laporkan jika masalah berlanjut</span>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-grid gap-3">
                        <!-- Primary Action: Back to Home -->
                        <a href="<?= $base_url ?>/index.php" class="btn-primary-custom">
                            <i class="fas fa-home me-2"></i>Kembali ke Beranda
                        </a>
                        
                        <!-- Secondary Actions -->
                        <div class="row g-2">
                            <div class="col-6">
                                <button onclick="window.location.reload()" class="btn-secondary-custom w-100">
                                    <i class="fas fa-redo me-2"></i>Coba Lagi
                                </button>
                            </div>
                            <div class="col-6">
                                <a href="mailto:admin@eliot.co.id?subject=ELIOT%20500%20Error" 
                                    class="btn-secondary-custom w-100 d-block text-decoration-none text-center">
                                    <i class="fas fa-envelope me-2"></i>Laporkan
                                </a>
                            </div>
                        </div>
                        
                        <!-- Status Page Link -->
                        <div class="text-center mt-2">
                            <a href="<?= $base_url ?>/status.php" class="text-decoration-none small">
                                <i class="fas fa-server me-1"></i> Cek Status Server
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
    
    <!-- Custom Error JS (reuse 404.js) -->
    <script src="<?= $base_url ?>/public/assets/js/404.js"></script>
</body>
</html>