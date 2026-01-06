<?php
/**
 * Halaman Peminjaman Buku - Step 3: Form Peminjaman
 * Path: web/apps/admin/peminjaman/peminjaman.php
 * 
 * Features:
 * - Detail buku lengkap dengan cover
 * - Rating & review buku
 * - Form peminjaman dengan konfirmasi
 * 
 * @author ELIOT System
 * @version 1.0.1 - Fixed controller path
 * @date 2026-01-06
 */

// ============================================
// INITIALIZATION & SECURITY
// ============================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../../includes/config.php';
require_once '../../controllers/PeminjamanController.php'; // ✅ FIXED PATH

// Security check
if (!isset($_SESSION['userRole']) || !in_array($_SESSION['userRole'], ['admin', 'staff'])) {
    header("Location: /eliot/web/404.php");
    exit;
}

// ============================================
// GET PARAMETERS
// ============================================
$uidBufferId = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($uidBufferId === 0 || $userId === 0) {
    $_SESSION['error_message'] = 'Parameter tidak valid';
    header("Location: index.php");
    exit;
}

// ============================================
// INITIALIZE CONTROLLER
// ============================================
$controller = new PeminjamanController($conn);

// Get book details
$bookResult = $controller->getBookDetails($uidBufferId);
if (!$bookResult['success']) {
    $_SESSION['error_message'] = $bookResult['message'];
    header("Location: index.php");
    exit;
}
$book = $bookResult['data'];

// Get ratings
$ratingsResult = $controller->getBookRatings($book['book_id']);
$ratings = $ratingsResult['success'] ? $ratingsResult['data'] : [
    'statistics' => [
        'average_rating' => 0,
        'total_reviews' => 0,
        'count_5_star' => 0,
        'count_4_star' => 0,
        'count_3_star' => 0,
        'count_2_star' => 0,
        'count_1_star' => 0
    ],
    'reviews' => []
];

// Validate member
$memberResult = $controller->validateMemberForBorrow($userId);
if (!$memberResult['success']) {
    $_SESSION['error_message'] = $memberResult['message'];
    header("Location: index.php");
    exit;
}
$member = $memberResult['data'];

// Get system settings
$settingsResult = $controller->getSystemSettings();
$settings = $settingsResult['data'];

include_once '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/peminjaman_form.css">

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Peminjaman</a></li>
            <li class="breadcrumb-item"><a href="biodata_peminjaman.php?user_id=<?= $userId ?>">Biodata Member</a></li>
            <li class="breadcrumb-item active">Form Peminjaman</li>
        </ol>
    </nav>

    <!-- Page Title -->
    <div class="text-center mb-4">
        <h3 class="fw-bold text-gradient">
            <i class="fas fa-book-reader me-2"></i>Konfirmasi Peminjaman Buku
        </h3>
        <p class="text-muted small">Pastikan data peminjaman sudah benar sebelum diproses</p>
    </div>

    <div class="row g-4">
        <!-- LEFT COLUMN: Book Details + Rating -->
        <div class="col-lg-7">
            
            <!-- SECTION 1: DETAIL BUKU -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-book me-2"></i>Detail Buku</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <!-- Cover Image -->
                        <div class="col-md-4 text-center mb-3 mb-md-0">
                            <?php if (!empty($book['cover_image']) && file_exists($book['cover_image'])): ?>
                                <img src="<?= htmlspecialchars($book['cover_image']) ?>" 
                                     alt="Cover Buku" 
                                     class="img-fluid rounded shadow"
                                     style="max-height: 300px; object-fit: cover;">
                            <?php else: ?>
                                <div class="book-placeholder">
                                    <i class="fas fa-book fa-5x text-muted"></i>
                                    <p class="text-muted mt-2">No Cover</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Book Info -->
                        <div class="col-md-8">
                            <h4 class="fw-bold text-primary mb-3"><?= htmlspecialchars($book['judul_buku']) ?></h4>
                            
                            <?php if (!empty($book['keterangan'])): ?>
                            <div class="alert alert-info py-2 px-3 mb-3">
                                <small><i class="fas fa-info-circle me-1"></i><?= htmlspecialchars($book['keterangan']) ?></small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="book-info-grid">
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-barcode me-1"></i>ISBN</span>
                                    <span class="info-value"><?= htmlspecialchars($book['isbn']) ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-users me-1"></i>Pengarang</span>
                                    <span class="info-value">
                                        <?php if (!empty($book['authors'])): ?>
                                            <?php foreach ($book['authors'] as $author): ?>
                                                <span class="badge bg-secondary me-1">
                                                    <?= htmlspecialchars($author['nama']) ?>
                                                    <?php if ($author['peran'] !== 'penulis_utama'): ?>
                                                        <small>(<?= ucfirst(str_replace('_', ' ', $author['peran'])) ?>)</small>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-building me-1"></i>Penerbit</span>
                                    <span class="info-value"><?= htmlspecialchars($book['nama_penerbit'] ?? '-') ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-calendar me-1"></i>Tahun Terbit</span>
                                    <span class="info-value"><?= htmlspecialchars($book['tahun_terbit'] ?? '-') ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-file-alt me-1"></i>Jumlah Halaman</span>
                                    <span class="info-value"><?= number_format($book['jumlah_halaman'] ?? 0) ?> hal</span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-tag me-1"></i>Kategori</span>
                                    <span class="info-value">
                                        <span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $book['kategori'])) ?></span>
                                    </span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-map-marker-alt me-1"></i>Lokasi Rak</span>
                                    <span class="info-value fw-bold text-success"><?= htmlspecialchars($book['lokasi_rak'] ?? '-') ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-qrcode me-1"></i>Kode Eksemplar</span>
                                    <span class="info-value"><code><?= htmlspecialchars($book['kode_eksemplar']) ?></code></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-check-circle me-1"></i>Kondisi</span>
                                    <span class="info-value">
                                        <span class="badge bg-<?= $book['kondisi'] === 'baik' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($book['kondisi']) ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (!empty($book['deskripsi'])): ?>
                            <div class="mt-3">
                                <h6 class="fw-bold">Deskripsi:</h6>
                                <p class="text-muted small"><?= nl2br(htmlspecialchars($book['deskripsi'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 2: RATING & REVIEW -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-star me-2"></i>Rating & Review</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <!-- Rating Statistics -->
                        <div class="col-md-5 text-center border-end">
                            <div class="rating-overview">
                                <div class="rating-number display-4 fw-bold text-warning">
                                    <?= number_format($ratings['statistics']['average_rating'], 1) ?>
                                </div>
                                <div class="rating-stars mb-2">
                                    <?php 
                                    $avgRating = $ratings['statistics']['average_rating'];
                                    for ($i = 1; $i <= 5; $i++): 
                                        if ($i <= floor($avgRating)): ?>
                                            <i class="fas fa-star text-warning"></i>
                                        <?php elseif ($i <= ceil($avgRating)): ?>
                                            <i class="fas fa-star-half-alt text-warning"></i>
                                        <?php else: ?>
                                            <i class="far fa-star text-warning"></i>
                                        <?php endif;
                                    endfor; ?>
                                </div>
                                <p class="text-muted mb-0"><?= number_format($ratings['statistics']['total_reviews']) ?> Review</p>
                            </div>
                            
                            <!-- Rating Breakdown -->
                            <div class="rating-breakdown mt-4 text-start">
                                <?php for ($i = 5; $i >= 1; $i--): 
                                    $count = $ratings['statistics']["count_{$i}_star"];
                                    $percentage = $ratings['statistics']['total_reviews'] > 0 
                                        ? ($count / $ratings['statistics']['total_reviews']) * 100 
                                        : 0;
                                ?>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="me-2" style="width: 60px;"><?= $i ?> <i class="fas fa-star text-warning"></i></span>
                                    <div class="progress flex-grow-1" style="height: 8px;">
                                        <div class="progress-bar bg-warning" 
                                             style="width: <?= $percentage ?>%"></div>
                                    </div>
                                    <span class="ms-2 text-muted small" style="width: 40px;"><?= $count ?></span>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Reviews -->
                        <div class="col-md-7">
                            <h6 class="fw-bold mb-3">Review Terbaru</h6>
                            
                            <?php if (empty($ratings['reviews'])): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-comments fa-3x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">Belum ada review</p>
                                </div>
                            <?php else: ?>
                                <div class="reviews-list">
                                    <?php foreach ($ratings['reviews'] as $review): ?>
                                    <div class="review-item mb-3 pb-3 border-bottom">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong><?= htmlspecialchars($review['reviewer_name']) ?></strong>
                                                <div class="review-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <small class="text-muted"><?= $review['review_date'] ?></small>
                                        </div>
                                        <?php if (!empty($review['review'])): ?>
                                        <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($review['review'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- RIGHT COLUMN: Form Peminjaman -->
        <div class="col-lg-5">
            <!-- SECTION 3: FORM PEMINJAMAN -->
            <div class="card shadow-lg border-0 sticky-top" style="top: 20px;">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Form Peminjaman</h5>
                </div>
                <div class="card-body p-4">
                    <!-- Member Info -->
                    <div class="member-info-box mb-4">
                        <h6 class="fw-bold mb-3"><i class="fas fa-user me-2 text-primary"></i>Informasi Peminjam</h6>
                        <div class="info-grid">
                            <div class="info-row">
                                <span class="label">Nama:</span>
                                <span class="value fw-bold"><?= htmlspecialchars($member['nama']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Kuota Tersisa:</span>
                                <span class="value">
                                    <span class="badge bg-primary"><?= $member['kuota_tersisa'] ?> buku</span>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="label">Total Denda:</span>
                                <span class="value">
                                    <span class="badge bg-<?= $member['total_denda'] > 0 ? 'danger' : 'success' ?>">
                                        Rp <?= number_format($member['total_denda'], 0, ',', '.') ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loan Settings -->
                    <div class="loan-settings-box mb-4">
                        <h6 class="fw-bold mb-3"><i class="fas fa-cog me-2 text-warning"></i>Pengaturan Peminjaman</h6>
                        
                        <div class="mb-3">
                            <label class="form-label">Durasi Peminjaman (Hari Kerja)</label>
                            <select class="form-select" id="durasi-peminjaman">
                                <?php for ($i = 3; $i <= 14; $i++): ?>
                                <option value="<?= $i ?>" <?= $i == $settings['durasi_peminjaman_default'] ? 'selected' : '' ?>>
                                    <?= $i ?> hari kerja
                                </option>
                                <?php endfor; ?>
                            </select>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Tanggal jatuh tempo akan dihitung otomatis (tidak termasuk weekend & libur nasional)
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Catatan (Opsional)</label>
                            <textarea class="form-control" 
                                      id="catatan-peminjaman" 
                                      rows="3" 
                                      placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Warning Box -->
                    <div class="alert alert-warning mb-4">
                        <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Perhatian!</h6>
                        <ul class="mb-0 ps-3">
                            <li>Pastikan kondisi buku dalam keadaan <strong>baik</strong></li>
                            <li>Denda keterlambatan: <strong>Rp <?= number_format($settings['denda_per_hari'], 0, ',', '.') ?>/hari</strong></li>
                            <li>Jaga buku dengan baik selama dipinjam</li>
                        </ul>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-grid gap-2">
                        <button type="button" 
                                class="btn btn-success btn-lg" 
                                id="btn-process-peminjaman">
                            <i class="fas fa-check-circle me-2"></i>Proses Peminjaman
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/dark-mode-utils.js"></script>

<script>
    // ============================================
    // PEMINJAMAN FORM - JavaScript
    // ============================================

    const PEMINJAMAN_DATA = {
        userId: <?= json_encode($userId) ?>,
        bookId: <?= json_encode($book['book_id']) ?>,
        uidBufferId: <?= json_encode($uidBufferId) ?>,
        staffId: <?= json_encode($_SESSION['userId']) ?>,
        memberName: <?= json_encode($member['nama']) ?>,
        bookTitle: <?= json_encode($book['judul_buku']) ?>,
        kodeEksemplar: <?= json_encode($book['kode_eksemplar']) ?>
    };

    const API_ENDPOINT = '../../includes/api/scan_peminjaman.php'; // ✅ FIXED PATH (relative)

    // ============================================
    // PROCESS PEMINJAMAN
    // ============================================
    document.getElementById('btn-process-peminjaman').addEventListener('click', async function() {
        const durasi = parseInt(document.getElementById('durasi-peminjaman').value);
        const catatan = document.getElementById('catatan-peminjaman').value.trim();
        
        // Konfirmasi
        const config = window.DarkModeUtils.getSwalConfig({
            title: 'Konfirmasi Peminjaman',
            html: `
                <div class="text-start px-3">
                    <p class="mb-2"><strong>Peminjam:</strong> ${PEMINJAMAN_DATA.memberName}</p>
                    <p class="mb-2"><strong>Buku:</strong> ${PEMINJAMAN_DATA.bookTitle}</p>
                    <p class="mb-2"><strong>Kode Eksemplar:</strong> <code>${PEMINJAMAN_DATA.kodeEksemplar}</code></p>
                    <p class="mb-2"><strong>Durasi:</strong> ${durasi} hari kerja</p>
                    ${catatan ? `<p class="mb-0"><strong>Catatan:</strong> ${catatan}</p>` : ''}
                </div>
                <hr class="my-3">
                <p class="text-center text-muted mb-0">Proses peminjaman buku ini?</p>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check me-2"></i>Ya, Proses',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-success me-2',
                cancelButton: 'btn btn-secondary'
            }
        });
        
        const result = await Swal.fire(config);
        
        if (result.isConfirmed) {
            await processPeminjaman(durasi, catatan);
        }
    });

    async function processPeminjaman(durasi, catatan) {
        // Show loading
        Swal.fire({
            title: 'Memproses...',
            html: 'Mohon tunggu, sedang memproses peminjaman',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        try {
            const response = await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    rfid_uid: '', // Not used, we already have uid_buffer_id
                    user_id: PEMINJAMAN_DATA.userId,
                    staff_id: PEMINJAMAN_DATA.staffId,
                    durasi_hari: durasi,
                    // Custom params for direct processing
                    uid_buffer_id: PEMINJAMAN_DATA.uidBufferId,
                    book_id: PEMINJAMAN_DATA.bookId,
                    catatan: catatan
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                handleSuccess(result.data);
            } else {
                handleError(result.message || 'Terjadi kesalahan');
            }
            
        } catch (error) {
            console.error('Error:', error);
            handleError('Gagal terhubung ke server: ' + error.message);
        }
    }

    function handleSuccess(data) {
        const config = window.DarkModeUtils.getSwalConfig({
            title: 'Peminjaman Berhasil!',
            html: `
                <div class="text-center py-3">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h5 class="mb-3">Kode Peminjaman</h5>
                    <h3 class="text-primary mb-3">${data.peminjaman.kode_peminjaman}</h3>
                    <div class="text-start px-4">
                        <p class="mb-2"><strong>Peminjam:</strong> ${data.peminjam.nama}</p>
                        <p class="mb-2"><strong>Buku:</strong> ${data.buku.judul}</p>
                        <p class="mb-2"><strong>Tanggal Pinjam:</strong> ${data.peminjaman.tanggal_pinjam_formatted}</p>
                        <p class="mb-2"><strong>Jatuh Tempo:</strong> ${data.peminjaman.due_date_formatted}</p>
                        <p class="mb-0"><strong>Durasi:</strong> ${data.peminjaman.durasi_hari_kerja} hari kerja</p>
                    </div>
                </div>
            `,
            icon: 'success',
            confirmButtonText: '<i class="fas fa-home me-2"></i>Kembali ke Dashboard',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-primary'
            }
        });
        
        Swal.fire(config).then(() => {
            window.location.href = 'index.php';
        });
    }

    function handleError(message) {
        const config = window.DarkModeUtils.getSwalConfig({
            title: 'Peminjaman Gagal',
            html: `<p class="mb-0">${message}</p>`,
            icon: 'error',
            confirmButtonText: 'OK',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-secondary'
            }
        });
        
        Swal.fire(config);
    }
</script>

<?php
include_once '../../includes/footer.php';
ob_end_flush();
?>