<?php
// ~/Documents/ELIOT/web/apps/dashboard.php

// Start session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Variabel khusus untuk header.php (Judul Halaman)
$pageTitle = 'Dashboard Utama (Admin Test)'; 

// FIX: Menggunakan __DIR__ untuk Path Absolut
// Memanggil header (cek login dinonaktifkan untuk test)
include __DIR__ . '/includes/header.php'; 
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="h4">Selamat Datang, <?= htmlspecialchars($userName) ?>!</h2>
        <p class="text-muted">Ini adalah Dashboard Anda sebagai <?= ucfirst($userRole) ?>.</p>
        <p class="text-danger small"><b>PENTING:</b> Cek otorisasi dinonaktifkan di header.php untuk pengujian layout.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4 col-md-6">
        <div class="card card-custom-primary shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="fas fa-user-shield fa-3x me-3"></i>
                    <div>
                        <h5 class="card-title mb-0">Role Pengguna</h5>
                        <p class="card-text h3 fw-bold"><?= ucfirst($userRole) ?></p>
                    </div>
                </div>
                <hr>
                <p class="card-text small text-muted">Akses Anda terbatas pada menu di sidebar sesuai peran Anda.</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6">
        <div class="card card-custom-warning shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="fas fa-boxes fa-3x me-3"></i>
                    <div>
                        <h5 class="card-title mb-0">Total Aset Aktif</h5>
                        <p class="card-text h3 fw-bold">1289</p>
                    </div>
                </div>
                <hr>
                <p class="card-text small text-muted">Jumlah aset yang terdaftar dan tersedia di sistem.</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-12">
        <div class="card card-custom-success shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exchange-alt fa-3x me-3"></i>
                    <div>
                        <h5 class="card-title mb-0">Transaksi Bulan Ini</h5>
                        <p class="card-text h3 fw-bold">45</p>
                    </div>
                </div>
                <hr>
                <p class="card-text small text-muted">Total pinjaman dan pengembalian yang tercatat.</p>
            </div>
        </div>
    </div>
</div>

<?php
// Memanggil footer
include __DIR__ . '/includes/footer.php';
?>