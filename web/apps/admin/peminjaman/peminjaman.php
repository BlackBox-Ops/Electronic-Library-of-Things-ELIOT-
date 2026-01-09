<?php
/**
 * Halaman Peminjaman Buku - Step 3: Form Peminjaman
 * FINAL WORKING VERSION - NO MORE BUGS!
 * 
 * @author ELIOT System
 * @version 2.0.0 - FINAL FIX
 * @date 2026-01-07
 */

ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../../includes/config.php';
require_once '../../controllers/PeminjamanController.php';

// Security check
if (!isset($_SESSION['userRole']) || !in_array($_SESSION['userRole'], ['admin', 'staff'])) {
    header("Location: /eliot/web/404.php");
    exit;
}

// Get parameters
$uidBufferId = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($uidBufferId === 0 || $userId === 0) {
    $_SESSION['error_message'] = 'Parameter tidak valid';
    header("Location: index.php");
    exit;
}

// ✅ GET STAFF ID - TRY ALL POSSIBLE SESSION KEYS
$staffId = 0;

// Try common session keys
$possibleKeys = ['userId', 'user_id', 'id'];
foreach ($possibleKeys as $key) {
    if (isset($_SESSION[$key]) && $_SESSION[$key] > 0) {
        $staffId = (int)$_SESSION[$key];
        break;
    }
}

// If still not found, try database lookup
if ($staffId === 0) {
    $email = $_SESSION['userEmail'] ?? $_SESSION['email'] ?? null;
    $role = $_SESSION['userRole'] ?? $_SESSION['role'] ?? null;
    
    if ($email && $role) {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = ? LIMIT 1");
            $stmt->bind_param('ss', $email, $role);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $staffId = (int)$row['id'];
            }
            $stmt->close();
        } catch (Exception $e) {
            // Silent fail
        }
    }
}

// If STILL 0, use a default or redirect
if ($staffId === 0) {
    $_SESSION['error_message'] = 'Session tidak valid. Silakan login ulang.';
    header("Location: ../../../login.php");
    exit;
}

// Initialize controller
$controller = new PeminjamanController($conn);

// Get book details
$bookResult = $controller->getBookDetails($uidBufferId);
if (!$bookResult['success']) {
    $_SESSION['error_message'] = $bookResult['message'] ?? 'Buku tidak ditemukan';
    header("Location: index.php");
    exit;
}
$book = $bookResult['data'];

// Get ratings
$ratingsResult = $controller->getBookRatings($book['book_id'] ?? 0);
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
    $_SESSION['error_message'] = $memberResult['message'] ?? 'Member tidak valid';
    header("Location: index.php");
    exit;
}
$member = $memberResult['data'];

// Get settings
$settingsResult = $controller->getSystemSettings();
$settings = $settingsResult['data'] ?? [
    'durasi_peminjaman_default' => 7,
    'denda_per_hari' => 2000
];

include_once '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/peminjaman_form.css">

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Peminjaman</a></li>
            <li class="breadcrumb-item"><a href="biodata_peminjaman.php?user_id=<?= $userId ?>">Biodata</a></li>
            <li class="breadcrumb-item active">Konfirmasi</li>
        </ol>
    </nav>

    <div class="text-center mb-4">
        <h3 class="fw-bold"><i class="fas fa-book-reader me-2"></i>Konfirmasi Peminjaman</h3>
        <p class="text-muted small">Pastikan data sudah benar sebelum diproses</p>
    </div>

    <div class="row g-4">
        <!-- LEFT: Book Details -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-book me-2"></i>Detail Buku</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <?php if (!empty($book['cover_image']) && file_exists($book['cover_image'])): ?>
                                <img src="<?= htmlspecialchars($book['cover_image']) ?>" 
                                     alt="Cover" class="img-fluid rounded shadow"
                                     style="max-height: 300px;">
                            <?php else: ?>
                                <div style="background:#f0f0f0;padding:50px;border-radius:8px;">
                                    <i class="fas fa-book fa-5x text-muted"></i>
                                    <p class="text-muted mt-2">No Cover</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-8">
                            <h4 class="fw-bold text-primary mb-3">
                                <?= htmlspecialchars($book['judul_buku'] ?? 'N/A') ?>
                            </h4>
                            
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th width="40%"><i class="fas fa-barcode me-1"></i> ISBN</th>
                                    <td><?= htmlspecialchars($book['isbn'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-users me-1"></i> Pengarang</th>
                                    <td>
                                        <?php if (!empty($book['authors'])): ?>
                                            <?php foreach ($book['authors'] as $author): ?>
                                                <span class="badge bg-secondary me-1">
                                                    <?= htmlspecialchars($author['nama'] ?? '-') ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-building me-1"></i> Penerbit</th>
                                    <td><?= htmlspecialchars($book['nama_penerbit'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-calendar me-1"></i> Tahun</th>
                                    <td><?= htmlspecialchars($book['tahun_terbit'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-map-marker-alt me-1"></i> Lokasi Rak</th>
                                    <td><strong class="text-success"><?= htmlspecialchars($book['lokasi_rak'] ?? '-') ?></strong></td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-qrcode me-1"></i> Kode Eksemplar</th>
                                    <td><code><?= htmlspecialchars($book['kode_eksemplar'] ?? '-') ?></code></td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-check-circle me-1"></i> Kondisi</th>
                                    <td>
                                        <span class="badge bg-<?= ($book['kondisi'] ?? '') === 'baik' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($book['kondisi'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- RIGHT: Form -->
        <div class="col-lg-5">
            <div class="card shadow-lg border-0 sticky-top" style="top:20px;">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Form Peminjaman</h5>
                </div>
                <div class="card-body p-4">
                    <!-- Member Info -->
                    <div class="alert alert-info">
                        <h6 class="fw-bold mb-2"><i class="fas fa-user me-2"></i>Peminjam</h6>
                        <p class="mb-1"><strong><?= htmlspecialchars($member['nama'] ?? 'N/A') ?></strong></p>
                        <small>
                            Kuota: <span class="badge bg-primary"><?= $member['kuota_tersisa'] ?? 0 ?> buku</span>
                            Denda: <span class="badge bg-<?= ($member['total_denda'] ?? 0) > 0 ? 'danger' : 'success' ?>">
                                Rp <?= number_format($member['total_denda'] ?? 0, 0, ',', '.') ?>
                            </span>
                        </small>
                    </div>
                    
                    <!-- Settings -->
                    <div class="mb-3">
                        <label class="form-label">Durasi (Hari Kerja)</label>
                        <select class="form-select" id="durasi-peminjaman">
                            <?php for ($i = 3; $i <= 14; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == ($settings['durasi_peminjaman_default'] ?? 7) ? 'selected' : '' ?>>
                                <?= $i ?> hari kerja
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Catatan (Opsional)</label>
                        <textarea class="form-control" id="catatan-peminjaman" rows="3" 
                                  placeholder="Catatan tambahan..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Denda: <strong>Rp <?= number_format($settings['denda_per_hari'] ?? 2000, 0, ',', '.') ?>/hari</strong>
                        </small>
                    </div>
                    
                    <!-- Buttons -->
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success btn-lg" id="btn-process">
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

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/dark-mode-utils.js"></script>

<script>
// ===== PEMINJAMAN FORM - SIMPLE VERSION =====
const DATA = {
    userId: <?= $userId ?>,
    bookId: <?= $book['book_id'] ?? 0 ?>,
    uidBufferId: <?= $uidBufferId ?>,
    staffId: <?= $staffId ?>,
    memberName: <?= json_encode($member['nama'] ?? '') ?>,
    bookTitle: <?= json_encode($book['judul_buku'] ?? '') ?>,
    kodeEksemplar: <?= json_encode($book['kode_eksemplar'] ?? '') ?>
};

console.log('[Peminjaman] Data loaded:', DATA);

// Validate data
if (!DATA.staffId || DATA.staffId === 0) {
    alert('Error: Staff ID tidak ditemukan. Silakan login ulang.');
    window.location.href = '../../../login.php';
}

// Button handler
document.getElementById('btn-process').addEventListener('click', async function() {
    const durasi = parseInt(document.getElementById('durasi-peminjaman').value);
    const catatan = document.getElementById('catatan-peminjaman').value.trim();
    
    // Confirm
    const result = await Swal.fire({
        title: 'Konfirmasi',
        html: `<div class="text-start">
            <p><strong>Member:</strong> ${DATA.memberName}</p>
            <p><strong>Buku:</strong> ${DATA.bookTitle}</p>
            <p><strong>Durasi:</strong> ${durasi} hari kerja</p>
        </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Proses',
        cancelButtonText: 'Batal'
    });
    
    if (!result.isConfirmed) return;
    
    // Show loading
    Swal.fire({
        title: 'Memproses...',
        text: 'Mohon tunggu',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    try {
        const response = await fetch('../../includes/api/scan_peminjaman.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                uid_buffer_id: DATA.uidBufferId,
                book_id: DATA.bookId,
                user_id: DATA.userId,
                staff_id: DATA.staffId,
                durasi_hari: durasi,
                catatan: catatan,
                rfid_uid: ''
            })
        });
        
        const result = await response.json();
        console.log('[Peminjaman] API response:', result);
        
        if (result.success) {
            const p = result.data?.peminjaman || {};
            await Swal.fire({
                title: 'Berhasil!',
                html: `<div class="text-center">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h5>Kode: ${p.kode_peminjaman || 'N/A'}</h5>
                    <p>Jatuh tempo: ${p.due_date_formatted || 'N/A'}</p>
                </div>`,
                icon: 'success'
            });
            window.location.href = 'index.php';
        } else {
            throw new Error(result.message || 'Terjadi kesalahan');
        }
    } catch (error) {
        console.error('[Peminjaman] Error:', error);
        Swal.fire({
            title: 'Gagal',
            text: error.message,
            icon: 'error'
        });
    }
});

console.log('[Peminjaman] Script ready');
</script>

<?php 
include_once '../../includes/footer.php';
ob_end_flush();
?>