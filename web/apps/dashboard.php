<?php
// ~/apps/dashboard.php
$pageTitle = 'Dashboard V3';
include_once 'includes/header.php'; 
?>

<div class="container-fluid">
    <!-- Header Selamat Datang -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Selamat Datang, <?= $userName ?>!</h2>
            <p class="text-muted mb-0">Dashboard ELIOT - Integrated Asset Management</p>
        </div>
        <button class="btn btn-primary btn-sm px-3 shadow-sm">
            <i class="fas fa-plus me-2"></i>Tambah Aset Baru
        </button>
    </div>

    <!-- Statistik Cards -->
    <div class="row g-4 mb-5">
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-success-subtle p-3 rounded-circle me-3">
                        <i class="fas fa-boxes fa-2x text-success"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted">Total Aset</h6>
                        <h4 class="mb-0">1,234</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-primary-subtle p-3 rounded-circle me-3">
                        <i class="fas fa-users fa-2x text-primary"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted">Pengguna Aktif</h6>
                        <h4 class="mb-0">567</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-warning-subtle p-3 rounded-circle me-3">
                        <i class="fas fa-exchange-alt fa-2x text-warning"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted">Transaksi Hari Ini</h6>
                        <h4 class="mb-0">89</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-danger-subtle p-3 rounded-circle me-3">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted">Aset Hilang</h6>
                        <h4 class="mb-0">12</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Aktivitas Terbaru</h5>
            <a href="#" class="btn btn-sm btn-outline-secondary">Lihat Semua</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Aksi</th>
                            <th>Aset</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data)): ?>
                        <tr class="empty-state">
                            <td colspan="5">
                                <div class="empty-state-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <div class="empty-state-text">Tidak ada data</div>
                                <div class="empty-state-subtext">Belum ada aktivitas untuk ditampilkan</div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <!-- Data rows here -->
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Actions (opsional) -->
    <div class="row mt-5">
        <div class="col-md-4">
            <div class="card shadow-sm text-center p-4">
                <i class="fas fa-qrcode fa-3x text-success mb-3"></i>
                <h5>Cetak Label RFID</h5>
                <p class="text-muted small">Buat label baru untuk aset</p>
                <a href="#" class="btn btn-outline-success btn-sm">Mulai</a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm text-center p-4">
                <i class="fas fa-file-alt fa-3x text-primary mb-3"></i>
                <h5>Laporan Aset</h5>
                <p class="text-muted small">Lihat laporan lengkap</p>
                <a href="#" class="btn btn-outline-primary btn-sm">Lihat Laporan</a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm text-center p-4">
                <i class="fas fa-history fa-3x text-warning mb-3"></i>
                <h5>Riwayat Transaksi</h5>
                <p class="text-muted small">Semua aktivitas sistem</p>
                <a href="#" class="btn btn-outline-warning btn-sm">Lihat Riwayat</a>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>