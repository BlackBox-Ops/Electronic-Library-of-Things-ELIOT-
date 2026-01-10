<?php
/**
 * Halaman Peminjaman Buku - Step 1: Validasi Peminjam
 * Path: web/apps/admin/peminjaman/index.php
 * 
 * Features:
 * - Input NIM/NIDN/NIK member dengan desain minimalis
 * - Validasi member dan redirect ke biodata_peminjaman.php
 * - Dashboard statistics
 * - Monitoring peminjaman aktif dengan SEARCH & FILTER
 * - Support dark mode & light mode
 * 
 * @author ELIOT System
 * @version 2.3.0 - Added Search & Advanced Filters
 * @date 2026-01-10
 */

// ============================================
// INITIALIZATION & SECURITY
// ============================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../../includes/config.php';

// Security check - hanya admin dan staff yang bisa akses
if (!isset($_SESSION['userRole']) || !in_array($_SESSION['userRole'], ['admin', 'staff'])) {
    header("Location: /eliot/web/404.php");
    exit;
}

// Exception handler untuk error yang tidak tertangani
set_exception_handler(function($e) {
    error_log('[Peminjaman] Uncaught Exception: ' . $e->getMessage());
    header("Location: /eliot/web/500.php");
    exit;
});

// ============================================
// FETCH DASHBOARD STATISTICS
// ============================================
try {
    $statsQuery = $conn->query("
        SELECT 
            (SELECT COUNT(*) 
            FROM ts_peminjaman 
            WHERE DATE(tanggal_pinjam) = CURDATE() 
            AND is_deleted = 0) as total_today,
            
            (SELECT COUNT(*) 
            FROM vw_peminjaman_aktif 
            WHERE hari_tersisa <= 3 
            AND hari_tersisa > 0) as will_overdue,

            (SELECT COUNT(*) 
            FROM vw_peminjaman_aktif 
            WHERE status_waktu = 'telat') as overdue_now,
            
            (SELECT COUNT(DISTINCT user_id) 
            FROM ts_denda 
            WHERE status_pembayaran = 'belum_dibayar' 
            AND is_deleted = 0) as member_with_fines
    ");
    
    $stats = $statsQuery->fetch_assoc() ?? [
        'total_today' => 0,
        'will_overdue' => 0,
        'overdue_now' => 0,
        'member_with_fines' => 0
    ];
} catch (Exception $e) {
    error_log('[Peminjaman] Stats Query Error: ' . $e->getMessage());
    $stats = [
        'total_today' => 0,
        'will_overdue' => 0,
        'overdue_now' => 0,
        'member_with_fines' => 0
    ];
}

// ============================================
// FETCH SYSTEM SETTINGS
// ============================================
try {
    $settingsQuery = $conn->query("
        SELECT setting_key, setting_value 
        FROM system_settings 
        WHERE setting_key IN ('durasi_peminjaman_default', 'denda_per_hari', 'max_denda_block')
    ");
    
    $settings = [];
    while ($row = $settingsQuery->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    error_log('[Peminjaman] Settings Query Error: ' . $e->getMessage());
    $settings = [
        'durasi_peminjaman_default' => 7,
        'denda_per_hari' => 2000,
        'max_denda_block' => 50000
    ];
}

// Include header
include_once '../../includes/header.php';
?>

<!-- ============================================
    CUSTOM STYLES
    ============================================ -->
<link rel="stylesheet" href="../../assets/css/peminjaman.css">

<div class="container-fluid py-4">
    
    <!-- ============================================
        PAGE HEADER
        ============================================ -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-green-primary">
                <i class="fas fa-hand-holding-heart me-2"></i>Peminjaman Buku
            </h4>
            <p class="text-secondary small mb-0">
                Sistem peminjaman dengan teknologi RFID 
            </p>
        </div>
        <div>
            <button class="btn btn-outline-primary btn-sm" onclick="window.location.reload()">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- ============================================
        STATISTICS CARDS
        ============================================ -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Peminjaman Hari Ini -->
        <div class="col-md-6 col-lg-3">
            <div class="stat-card stat-card-primary">
                <div class="stat-icon">
                    <i class="fas fa-book-reader"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Peminjaman Hari Ini</div>
                    <div class="stat-value" id="stat-today"><?= number_format($stats['total_today']) ?></div>
                </div>
            </div>
        </div>

        <!-- Card 2: Akan Jatuh Tempo -->
        <div class="col-md-6 col-lg-3">
            <div class="stat-card stat-card-warning">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Akan Jatuh Tempo</div>
                    <div class="stat-value" id="stat-will-overdue"><?= number_format($stats['will_overdue']) ?></div>
                </div>
            </div>
        </div>

        <!-- Card 3: Telat Pengembalian -->
        <div class="col-md-6 col-lg-3">
            <div class="stat-card stat-card-danger">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Telat Pengembalian</div>
                    <div class="stat-value" id="stat-overdue"><?= number_format($stats['overdue_now']) ?></div>
                </div>
            </div>
        </div>

        <!-- Card 4: Member Berdenda -->
        <div class="col-md-6 col-lg-3">
            <div class="stat-card stat-card-info">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Member Berdenda</div>
                    <div class="stat-value" id="stat-fines"><?= number_format($stats['member_with_fines']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
        MAIN INPUT AREA - TWO STEP DESIGN
        ============================================ -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card card-adaptive border-0 shadow-lg">
                <div class="card-body p-4 p-md-5">
                    
                    <!-- Status Indicator -->
                    <div class="text-center mb-4">
                        <div class="status-indicator idle" id="status-indicator">
                            <span class="status-pulse"></span>
                            <span id="status-text">Siap Menerima Input</span>
                        </div>
                    </div>

                    <!-- Two Step Input -->
                    <div class="row g-4">
                        
                        <!-- STEP 1: Input No Identitas -->
                        <div class="col-lg-6">
                            <div class="step-container">
                                <!-- Step Header -->
                                <div class="step-header mb-3">
                                    <div class="step-number">1</div>
                                    <div class="step-info">
                                        <h6 class="step-title mb-1">Masukkan No Identitas</h6>
                                        <p class="step-desc mb-0">NIM / NIK / NIDN</p>
                                    </div>
                                </div>
                                
                                <!-- Step Input -->
                                <div class="member-input-box">
                                    <div class="input-group input-group-lg">
                                        <!-- Icon -->
                                        <span class="input-group-text">
                                            <i class="fas fa-id-card"></i>
                                        </span>
                                        
                                        <!-- Input Field -->
                                        <input type="text" 
                                                class="form-control form-control-lg" 
                                                id="input-member-uid"
                                                placeholder="Ketik nomor identitas"
                                                autocomplete="off"
                                                autofocus>
                                        
                                        <!-- Validate Button -->
                                        <button class="btn btn-green btn-lg" 
                                                id="btn-validate-member" 
                                                type="button">
                                            <i class="fas fa-check-circle me-2"></i>Validasi
                                        </button>
                                    </div>
                                    
                                    <!-- Helper Text -->
                                    <div class="form-text mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Tekan <strong>Enter</strong> atau klik <strong>Validasi</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 2: Scan RFID Card -->
                        <div class="col-lg-6">
                            <div class="step-container step-disabled" id="step-2-container">
                                <!-- Step Header -->
                                <div class="step-header mb-3">
                                    <div class="step-number">2</div>
                                    <div class="step-info">
                                        <h6 class="step-title mb-1">Scan RFID Card</h6>
                                        <p class="step-desc mb-0">Scan kartu member</p>
                                    </div>
                                </div>
                                
                                <!-- Step Input -->
                                <div class="rfid-input-box">
                                    <div class="input-group input-group-lg">
                                        <!-- Icon -->
                                        <span class="input-group-text">
                                            <i class="fas fa-wifi"></i>
                                        </span>
                                        
                                        <!-- Input Field (Disabled) -->
                                        <input type="text" 
                                                class="form-control form-control-lg" 
                                                id="input-rfid-card"
                                                placeholder="Dekatkan kartu ke scanner"
                                                autocomplete="off"
                                                disabled>
                                        
                                        <!-- Scan Button (Disabled) -->
                                        <button class="btn btn-primary btn-lg" 
                                                id="btn-scan-rfid" 
                                                type="button"
                                                disabled>
                                            <i class="fas fa-qrcode me-2"></i>Scan
                                        </button>
                                    </div>
                                    
                                    <!-- Helper Text -->
                                    <div class="form-text mt-2">
                                        <i class="fas fa-lock me-1"></i>
                                        Step ini akan aktif setelah validasi member berhasil
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
        MONITORING TABLE - PEMINJAMAN AKTIF
        ============================================ -->
    <div class="card card-adaptive border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 p-4 pb-0">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-list me-2"></i>Peminjaman Aktif
                    <span class="badge-count ms-2" id="result-count">0</span>
                </h5>
                <button class="btn btn-sm btn-outline-primary" onclick="refreshMonitoringTable()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <!-- ============================================
                SEARCH & FILTER SECTION (NEW)
                ============================================ -->
            <div class="search-filter-container">
                <div class="row g-3 align-items-end">
                    
                    <!-- Search Box -->
                    <div class="col-lg-5">
                        <label class="form-label mb-2">
                            <i class="fas fa-search me-1"></i>Cari Peminjam
                        </label>
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" 
                                    class="form-control" 
                                    id="search-input"
                                    placeholder="Cari nama peminjam atau NIM/NIK/NIDN..."
                                    autocomplete="off">
                            <button class="clear-search d-none" id="clear-search">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Filter Status -->
                    <div class="col-lg-2 col-md-4">
                        <div class="filter-group">
                            <label class="form-label mb-2">
                                <i class="fas fa-filter me-1"></i>Status
                            </label>
                            <select class="form-select" id="filter-status">
                                <option value="all">Semua Status</option>
                                <option value="dipinjam">Dipinjam</option>
                                <option value="telat">Telat</option>
                            </select>
                        </div>
                    </div>

                    <!-- Filter Tanggal -->
                    <div class="col-lg-3 col-md-4">
                        <div class="filter-group">
                            <label class="form-label mb-2">
                                <i class="fas fa-calendar me-1"></i>Periode
                            </label>
                            <select class="form-select" id="filter-date">
                                <option value="today">Hari Ini</option>
                                <option value="7days">7 Hari Terakhir</option>
                                <option value="30days">30 Hari Terakhir</option>
                                <option value="all">Semua Data</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    <div class="col-lg-2 col-md-4">
                        <button class="btn btn-outline-secondary w-100" id="btn-reset-filter">
                            <i class="fas fa-redo me-1"></i>Reset Filter
                        </button>
                    </div>

                </div>
            </div>
        </div>
        
        <div class="card-body p-4 pt-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle table-monitoring">
                    <thead class="table-light">
                        <tr>
                            <th width="50">No</th>
                            <th>Kode</th>
                            <th>Peminjam</th>
                            <th>UID Buku</th>
                            <th>Judul Buku</th>
                            <th>Staff</th>
                            <th>Status</th>
                            <th width="80">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="monitoring-table-body">
                        <!-- Will be populated by JavaScript -->
                    </tbody>
                </table>
                
                <!-- Loading State -->
                <div id="monitoring-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2 mb-0">Memuat data...</p>
                </div>
                
                <!-- Empty State -->
                <div id="monitoring-empty" class="text-center py-5 text-muted d-none">
                    <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                    <p class="mb-0">Tidak ada data peminjaman</p>
                    <small>Coba ubah filter atau lakukan pencarian lain</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
    JAVASCRIPT DEPENDENCIES
    ============================================ -->
<!-- SweetAlert2 untuk modal/alert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Core JavaScript Files -->
<script src="../../assets/js/dark-mode-utils.js"></script>
<script src="../../assets/js/peminjaman.js"></script>

<!-- ============================================
    SYSTEM CONFIGURATION
    ============================================ -->
<script>
    // Global configuration untuk JavaScript
    const SYSTEM_CONFIG = {
        // Durasi peminjaman default (hari)
        durasiBuku: <?= json_encode((int)($settings['durasi_peminjaman_default'] ?? 7)) ?>,
        
        // Denda per hari (Rupiah)
        dendaPerHari: <?= json_encode((int)($settings['denda_per_hari'] ?? 2000)) ?>,
        
        // Maksimal denda untuk block member (Rupiah)
        maxDendaBlock: <?= json_encode((int)($settings['max_denda_block'] ?? 50000)) ?>,
        
        // Staff info dari session
        staffId: <?= json_encode($_SESSION['userId'] ?? null) ?>,
        staffName: <?= json_encode($_SESSION['userName'] ?? 'Unknown') ?>,
        
        // API base URL
        apiBase: '/eliot/web/apps/includes/api/'
    };
    
    console.log('[Config] System configuration loaded:', SYSTEM_CONFIG);
</script>

<?php
include_once '../../includes/footer.php';
ob_end_flush();
?>