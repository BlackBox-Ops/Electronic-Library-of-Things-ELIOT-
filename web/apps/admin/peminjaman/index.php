<?php
/**
 * Halaman Peminjaman Buku - Sistem RFID ELIOT
 * Path: web/apps/admin/peminjaman/index.php
 */

ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../../includes/config.php';

// Security check
if (!isset($_SESSION['userRole']) || !in_array($_SESSION['userRole'], ['admin', 'staff'])) {
    include_once '../../../404.php';
    exit;
}

set_exception_handler(function($e) {
    error_log($e->getMessage());
    header("Location: /eliot/web/500.php");
    exit;
});

// Statistik dashboard (langsung dari PHP, aman)
$statsQuery = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM ts_peminjaman WHERE DATE(tanggal_pinjam) = CURDATE() AND is_deleted = 0) as total_today,
        (SELECT COUNT(*) FROM vw_peminjaman_aktif WHERE hari_tersisa <= 3 AND hari_tersisa > 0) as will_overdue,
        (SELECT COUNT(*) FROM vw_peminjaman_aktif WHERE status_waktu = 'telat') as overdue_now,
        (SELECT COUNT(DISTINCT no_identitas) FROM vw_denda_belum_dibayar) as member_with_fines
");
$stats = $statsQuery->fetch_assoc() ?? [
    'total_today' => 0,
    'will_overdue' => 0,
    'overdue_now' => 0,
    'member_with_fines' => 0
];

// System settings
$settingsQuery = $conn->query("
    SELECT setting_key, setting_value 
    FROM system_settings 
    WHERE setting_key IN ('durasi_peminjaman_default', 'denda_per_hari', 'max_denda_block')
");
$settings = [];
while ($row = $settingsQuery->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

include_once '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/peminjaman.css">

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-green-primary">
                <i class="fas fa-hand-holding-heart me-2"></i>Peminjaman Buku
            </h4>
            <p class="text-secondary small mb-0">
                Sistem peminjaman dengan teknologi RFID untuk pengalaman yang lebih cepat dan akurat.
            </p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="window.location.reload()">
                <i class="fas fa-sync-alt me-2"></i>Refresh Halaman
            </button>
            <button class="btn btn-outline-secondary" onclick="showHelp()">
                <i class="fas fa-question-circle me-2"></i>Bantuan
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="stat-card stat-card-primary">
                <div class="stat-icon"><i class="fas fa-book-reader"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Peminjaman</div>
                    <div class="stat-value" id="stat-today"><?= number_format($stats['total_today']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card stat-card-warning">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Jatuh Tempo</div>
                    <div class="stat-value" id="stat-will-overdue"><?= number_format($stats['will_overdue']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card stat-card-danger">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Telat</div>
                    <div class="stat-value" id="stat-overdue"><?= number_format($stats['overdue_now']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card stat-card-info">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Denda</div>
                    <div class="stat-value" id="stat-fines"><?= number_format($stats['member_with_fines']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Transaction Area -->
    <div class="row g-4 mb-4">
        <!-- Left Panel: RFID Scanning -->
        <div class="col-lg-5">
            <div class="card card-adaptive border-0 shadow-sm">
                <div class="card-body p-4">
                    <!-- Step 1: Member Scanning -->
                    <div id="member-scan-section">
                        <div class="section-header mb-3">
                            <div class="step-badge">1</div>
                            <div>
                                <h5 class="mb-0 fw-bold">Scan Kartu Member</h5>
                                <small class="text-muted">Tempelkan kartu RFID member ke reader</small>
                            </div>
                        </div>

                        <div id="member-scanner" class="rfid-scanner-box" data-state="idle">
                            <div class="scanner-animation">
                                <i class="fas fa-id-card fa-3x"></i>
                                <div class="pulse-ring"></div>
                            </div>
                            <p class="scanner-text mt-3 mb-0">Menunggu scan kartu member...</p>
                            <div class="scanner-status mt-2">
                                <span class="badge bg-secondary">Status: Idle</span>
                            </div>
                        </div>

                        <!-- Tombol Scan Member -->
                        <div class="text-center mt-4">
                            <button id="btn-scan-member" class="btn btn-success btn-sm">
                                <i class="fas fa-id-card me-2"></i>Scan Kartu Member
                            </button>
                        </div>

                        <!-- Member Info (hidden awal) -->
                        <div id="member-info" class="member-info-card d-none mt-4"></div>
                    </div>

                    <hr class="my-5">

                    <!-- Step 2: Book Scanning -->
                    <div id="book-scan-section" class="disabled-section">
                        <div class="section-header mb-3">
                            <div class="step-badge">2</div>
                            <div>
                                <h5 class="mb-0 fw-bold">Scan Buku</h5>
                                <small class="text-muted">Tempelkan buku ke reader (maksimal <span id="max-books">0</span> buku)</small>
                            </div>
                        </div>

                        <div id="book-scanner" class="rfid-scanner-box" data-state="disabled">
                            <div class="scanner-animation">
                                <i class="fas fa-book fa-3x"></i>
                                <div class="pulse-ring"></div>
                            </div>
                            <p class="scanner-text mt-3 mb-0">Scan member terlebih dahulu</p>
                            <div class="scanner-status mt-2">
                                <span class="badge bg-secondary">Status: Disabled</span>
                            </div>
                        </div>

                        <!-- Tombol Scan Buku (disabled awal) -->
                        <div class="text-center mt-4">
                            <button id="btn-scan-buku" class="btn btn-success btn-lg" disabled>
                                <i class="fas fa-book me-2"></i>Scan Buku
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel: Shopping Cart -->
        <div class="col-lg-7">
            <div class="card card-adaptive border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 p-4 pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-shopping-cart me-2"></i>Keranjang Peminjaman
                        </h5>
                        <span class="badge bg-primary fs-6" id="cart-badge">0 buku</span>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div id="cart-empty" class="cart-empty-state">
                        <i class="fas fa-shopping-cart fa-4x mb-3 opacity-25"></i>
                        <p class="text-muted mb-0">Belum ada buku di keranjang</p>
                        <small class="text-muted">Scan buku setelah member terdeteksi</small>
                    </div>

                    <div id="cart-items" class="d-none"></div>

                    <div id="cart-summary" class="d-none mt-4 pt-3 border-top">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-bold">Total Buku:</span>
                            <span class="fw-bold" id="total-books">0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="fw-bold">Tanggal Kembali:</span>
                            <span class="fw-bold text-primary" id="due-date-display">-</span>
                        </div>
                        <div class="d-grid gap-2">
                            <button id="btn-process" class="btn btn-green btn-lg" disabled>
                                <i class="fas fa-check-circle me-2"></i>Proses Peminjaman
                            </button>
                            <button id="btn-reset" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-2"></i>Reset Transaksi
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monitoring Table (sementara dinonaktifkan) -->
    <div class="card card-adaptive border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 p-4 pb-0">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-list me-2"></i>Peminjaman Aktif Hari Ini
                </h5>
            </div>
        </div>
        <div class="card-body p-4 pt-3">
            <div class="text-center py-5 text-muted">
                <i class="fas fa-info-circle fa-3x mb-3 opacity-50"></i>
                <p class="mb-0">Fitur monitoring akan aktif setelah API peminjaman selesai dibuat.</p>
                <small>Saat ini dalam tahap pengembangan</small>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Custom Scripts - Path benar: ../../assets/js/ -->
<script src="../../assets/js/rfid-handler.js"></script>
<script src="../../assets/js/cart-manager.js"></script>
<script src="../../assets/js/peminjaman.js"></script>

<!-- SYSTEM_CONFIG di akhir (aman dari output bocor) -->
<script>
    const SYSTEM_CONFIG = {
        durasiBuku: <?= json_encode($settings['durasi_peminjaman_default'] ?? 7) ?>,
        dendaPerHari: <?= json_encode($settings['denda_per_hari'] ?? 2000) ?>,
        maxDendaBlock: <?= json_encode($settings['max_denda_block'] ?? 50000) ?>,
        staffId: <?= json_encode($_SESSION['userId'] ?? null) ?>,
        staffName: <?= json_encode($_SESSION['userName'] ?? '') ?>
    };
</script>

<?php
include_once '../../includes/footer.php';
ob_end_flush();
?>