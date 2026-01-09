<?php
/**
 * Halaman Detail Peminjaman
 * Path: web/apps/admin/peminjaman/detail_peminjaman.php
 * 
 * Features:
 * - Detail informasi peminjaman lengkap
 * - Informasi buku dan peminjam
 * - Timeline peminjaman
 * - Status dan countdown
 * - Support dark mode & light mode
 * 
 * @author ELIOT System
 * @version 1.0.0
 * @date 2026-01-09
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

// Get ID peminjaman dari parameter
$peminjaman_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($peminjaman_id <= 0) {
    header("Location: index.php");
    exit;
}

// ============================================
// FETCH DETAIL PEMINJAMAN
// ============================================
try {
    $query = $conn->prepare("
        SELECT 
            p.id,
            p.kode_peminjaman,
            p.tanggal_pinjam,
            p.due_date,
            p.status,
            p.catatan,
            p.created_at,
            p.updated_at,
            
            -- User Info
            u.id as user_id,
            u.nama as nama_peminjam,
            u.email as email_peminjam,
            u.no_identitas,
            u.no_telepon,
            u.alamat,
            u.role as user_role,
            u.foto_profil,
            
            -- Book Info
            b.id as book_id,
            b.judul_buku,
            b.isbn,
            b.tahun_terbit,
            b.jumlah_halaman,
            b.lokasi_rak,
            b.deskripsi as deskripsi_buku,
            b.cover_image,
            
            -- Publisher & Author
            pub.nama_penerbit,
            GROUP_CONCAT(DISTINCT a.nama_pengarang SEPARATOR ', ') as pengarang,
            
            -- UID & Eksemplar
            uid.uid,
            rbu.kode_eksemplar,
            rbu.kondisi as kondisi_buku,
            
            -- Staff Info
            staff.id as staff_id,
            staff.nama as nama_staff,
            staff.email as email_staff,
            
            -- Calculated fields
            DATEDIFF(p.due_date, CURDATE()) as hari_tersisa,
            CASE 
                WHEN CURDATE() > p.due_date THEN 'telat'
                WHEN DATEDIFF(p.due_date, CURDATE()) <= 3 THEN 'akan_telat'
                ELSE 'normal'
            END as status_waktu,
            
            -- Denda (jika ada)
            d.jumlah_denda,
            d.status_pembayaran as status_denda
            
        FROM ts_peminjaman p
        INNER JOIN users u ON p.user_id = u.id
        INNER JOIN books b ON p.book_id = b.id
        LEFT JOIN publishers pub ON b.publisher_id = pub.id
        LEFT JOIN rt_book_author rba ON b.id = rba.book_id AND rba.is_deleted = 0
        LEFT JOIN authors a ON rba.author_id = a.id
        LEFT JOIN uid_buffer uid ON p.uid_buffer_id = uid.id
        LEFT JOIN rt_book_uid rbu ON p.uid_buffer_id = rbu.uid_buffer_id AND rbu.is_deleted = 0
        INNER JOIN users staff ON p.staff_id = staff.id
        LEFT JOIN ts_denda d ON p.id = d.pengembalian_id AND d.is_deleted = 0
        
        WHERE p.id = ? AND p.is_deleted = 0
        GROUP BY p.id
    ");
    
    $query->bind_param("i", $peminjaman_id);
    $query->execute();
    $result = $query->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: index.php");
        exit;
    }
    
    $data = $result->fetch_assoc();
    
} catch (Exception $e) {
    error_log('[Detail Peminjaman] Query Error: ' . $e->getMessage());
    header("Location: index.php");
    exit;
}

// Helper function untuk status badge
function getStatusBadge($status) {
    $badges = [
        'dipinjam' => '<span class="badge bg-primary"><i class="fas fa-book-reader me-1"></i>Dipinjam</span>',
        'dikembalikan' => '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Dikembalikan</span>',
        'telat' => '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Telat</span>',
        'hilang' => '<span class="badge bg-dark"><i class="fas fa-times-circle me-1"></i>Hilang</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
}

// Helper function untuk status waktu badge
function getStatusWaktuBadge($status_waktu, $hari_tersisa) {
    if ($status_waktu === 'telat') {
        return '<span class="badge bg-danger"><i class="fas fa-clock me-1"></i>Telat ' . abs($hari_tersisa) . ' hari</span>';
    } elseif ($status_waktu === 'akan_telat') {
        return '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation me-1"></i>Jatuh tempo ' . $hari_tersisa . ' hari lagi</span>';
    } else {
        return '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Masih ' . $hari_tersisa . ' hari</span>';
    }
}

// Include header
include_once '../../includes/header.php';
?>

<!-- Custom Styles -->
<link rel="stylesheet" href="../../assets/css/peminjaman.css">
<link rel="stylesheet" href="../../assets/css/detail_peminjaman.css">

<div class="container-fluid py-4">
    
    <!-- ============================================
        BREADCRUMB & BACK BUTTON
        ============================================ -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Peminjaman</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($data['kode_peminjaman']) ?></li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-green-primary">
                <i class="fas fa-info-circle me-2"></i>Detail Peminjaman
            </h4>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <div class="row g-4">
        
        <!-- ============================================
            LEFT COLUMN - PEMINJAMAN INFO
            ============================================ -->
        <div class="col-lg-8">
            
            <!-- Status Card -->
            <div class="card card-adaptive border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="fw-bold mb-2"><?= htmlspecialchars($data['kode_peminjaman']) ?></h5>
                            <div class="d-flex gap-2 flex-wrap">
                                <?= getStatusBadge($data['status']) ?>
                                <?= getStatusWaktuBadge($data['status_waktu'], $data['hari_tersisa']) ?>
                                <?php if ($data['jumlah_denda']): ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-money-bill-wave me-1"></i>
                                        Denda: Rp <?= number_format($data['jumlah_denda'], 0, ',', '.') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <small class="text-muted d-block">Dibuat pada</small>
                            <strong><?= date('d M Y, H:i', strtotime($data['created_at'])) ?></strong>
                        </div>
                    </div>
                    
                    <!-- Timeline -->
                    <div class="timeline-container">
                        <div class="timeline-item">
                            <div class="timeline-icon bg-success">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="timeline-content">
                                <strong>Tanggal Pinjam</strong>
                                <p class="mb-0"><?= date('d F Y, H:i', strtotime($data['tanggal_pinjam'])) ?></p>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-icon bg-warning">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="timeline-content">
                                <strong>Jatuh Tempo</strong>
                                <p class="mb-0"><?= date('d F Y, H:i', strtotime($data['due_date'])) ?></p>
                            </div>
                        </div>
                        
                        <?php if ($data['status'] === 'dikembalikan'): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon bg-success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="timeline-content">
                                <strong>Dikembalikan</strong>
                                <p class="mb-0"><?= date('d F Y, H:i', strtotime($data['updated_at'])) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Catatan -->
                    <?php if ($data['catatan']): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        <strong><i class="fas fa-sticky-note me-2"></i>Catatan:</strong>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($data['catatan'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Book Info Card -->
            <div class="card card-adaptive border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-book me-2"></i>Informasi Buku
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <?php if ($data['cover_image']): ?>
                                <img src="<?= htmlspecialchars($data['cover_image']) ?>" 
                                     alt="Cover" 
                                     class="img-fluid rounded shadow-sm book-cover">
                            <?php else: ?>
                                <div class="book-cover-placeholder">
                                    <i class="fas fa-book fa-3x"></i>
                                    <p class="small mt-2 mb-0">No Cover</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <h5 class="fw-bold mb-3"><?= htmlspecialchars($data['judul_buku']) ?></h5>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <i class="fas fa-user-edit text-green-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Pengarang</small>
                                            <strong><?= htmlspecialchars($data['pengarang'] ?? 'N/A') ?></strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <i class="fas fa-building text-green-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Penerbit</small>
                                            <strong><?= htmlspecialchars($data['nama_penerbit'] ?? 'N/A') ?></strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <i class="fas fa-barcode text-green-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">ISBN</small>
                                            <strong><?= htmlspecialchars($data['isbn']) ?></strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar text-green-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Tahun Terbit</small>
                                            <strong><?= htmlspecialchars($data['tahun_terbit'] ?? 'N/A') ?></strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <i class="fas fa-file-alt text-green-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Jumlah Halaman</small>
                                            <strong><?= number_format($data['jumlah_halaman'] ?? 0) ?> halaman</strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt text-green-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Lokasi Rak</small>
                                            <strong><?= htmlspecialchars($data['lokasi_rak'] ?? 'N/A') ?></strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="detail-item">
                                        <i class="fas fa-wifi text-green-primary me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">UID & Kode Eksemplar</small>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <span class="badge-uid" onclick="copyToClipboard('<?= htmlspecialchars($data['uid']) ?>', this)">
                                                    <?= htmlspecialchars($data['uid']) ?>
                                                    <i class="fas fa-copy copy-icon"></i>
                                                </span>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($data['kode_eksemplar']) ?></span>
                                                <span class="badge bg-<?= $data['kondisi_buku'] === 'baik' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($data['kondisi_buku']) ?>
                                                </span>
                                            </div>
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
            RIGHT COLUMN - USER & STAFF INFO
            ============================================ -->
        <div class="col-lg-4">
            
            <!-- User Info Card -->
            <div class="card card-adaptive border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-user me-2"></i>Peminjam
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <?php if ($data['foto_profil']): ?>
                            <img src="<?= htmlspecialchars($data['foto_profil']) ?>" 
                                 alt="Profile" 
                                 class="rounded-circle profile-img">
                        <?php else: ?>
                            <div class="profile-placeholder">
                                <i class="fas fa-user fa-3x"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-center mb-3">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($data['nama_peminjam']) ?></h6>
                        <span class="badge bg-primary"><?= ucfirst($data['user_role']) ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="detail-item mb-3">
                        <i class="fas fa-id-card text-green-primary me-2"></i>
                        <div>
                            <small class="text-muted d-block">No Identitas</small>
                            <strong><?= htmlspecialchars($data['no_identitas']) ?></strong>
                        </div>
                    </div>
                    
                    <div class="detail-item mb-3">
                        <i class="fas fa-envelope text-green-primary me-2"></i>
                        <div>
                            <small class="text-muted d-block">Email</small>
                            <strong><?= htmlspecialchars($data['email_peminjam']) ?></strong>
                        </div>
                    </div>
                    
                    <div class="detail-item mb-3">
                        <i class="fas fa-phone text-green-primary me-2"></i>
                        <div>
                            <small class="text-muted d-block">No Telepon</small>
                            <strong><?= htmlspecialchars($data['no_telepon'] ?? 'N/A') ?></strong>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <i class="fas fa-map-marker-alt text-green-primary me-2"></i>
                        <div>
                            <small class="text-muted d-block">Alamat</small>
                            <strong><?= htmlspecialchars($data['alamat'] ?? 'N/A') ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Staff Info Card -->
            <div class="card card-adaptive border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-user-tie me-2"></i>Diproses Oleh
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="detail-item mb-3">
                        <i class="fas fa-user-shield text-green-primary me-2"></i>
                        <div>
                            <small class="text-muted d-block">Nama Staff</small>
                            <strong><?= htmlspecialchars($data['nama_staff']) ?></strong>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <i class="fas fa-envelope text-green-primary me-2"></i>
                        <div>
                            <small class="text-muted d-block">Email Staff</small>
                            <strong><?= htmlspecialchars($data['email_staff']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        
    </div>
    
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/dark-mode-utils.js"></script>
<script src="../../assets/js/detail_peminjaman.js"></script>

<?php
include_once '../../includes/footer.php';
ob_end_flush();
?>