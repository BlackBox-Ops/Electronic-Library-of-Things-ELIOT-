<?php
/**
 * Halaman Biodata Peminjaman - Step 2 (Fixed UI Version)
 * 
 * @author ELIOT System - Revised & Fixed
 * @version 2.1.0
 * @date 2026-01-02
 */

// ============================================
// INITIALIZATION & SECURITY
// ============================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../../includes/config.php';

// Security check - hanya admin dan staff
if (!isset($_SESSION['userRole']) || !in_array($_SESSION['userRole'], ['admin', 'staff'])) {
    header("Location: /eliot/web/404.php");
    exit;
}

// ============================================
// GET USER ID FROM QUERY PARAMETER
// ============================================
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($userId === 0) {
    $_SESSION['error_message'] = 'User ID tidak valid';
    header("Location: index.php");
    exit;
}

// ============================================
// FETCH MEMBER DATA
// ============================================
try {
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.nama,
            u.email,
            u.no_identitas,
            u.no_telepon,
            u.alamat,
            u.role,
            u.status,
            u.max_peminjaman,
            u.foto_profil,
            
            -- Hitung jumlah buku yang sedang dipinjam
            (SELECT COUNT(*) 
             FROM ts_peminjaman p 
             WHERE p.user_id = u.id 
               AND p.status = 'dipinjam' 
               AND p.is_deleted = 0
            ) as total_pinjam_aktif,
            
            -- Hitung total denda
            (SELECT COALESCE(SUM(d.jumlah_denda), 0)
             FROM ts_denda d
             WHERE d.user_id = u.id
               AND d.status_pembayaran = 'belum_dibayar'
               AND d.is_deleted = 0
            ) as total_denda
            
        FROM users u
        WHERE u.id = ?
          AND u.role = 'member'
          AND u.is_deleted = 0
        LIMIT 1
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = 'Member tidak ditemukan';
        header("Location: index.php");
        exit;
    }
    
    $member = $result->fetch_assoc();
    $stmt->close();
    
    // Hitung kuota tersisa
    $kuotaTersisa = $member['max_peminjaman'] - $member['total_pinjam_aktif'];
    
} catch (Exception $e) {
    error_log('[Biodata] Error fetching member: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan saat mengambil data member';
    header("Location: index.php");
    exit;
}

// ============================================
// FETCH PEMINJAMAN AKTIF
// ============================================
try {
    $peminjamanStmt = $conn->prepare("
        SELECT 
            p.kode_peminjaman,
            p.tanggal_pinjam,
            p.due_date,
            b.judul_buku,
            rb.kode_eksemplar,
            GREATEST(0, DATEDIFF(p.due_date, CURDATE())) as hari_tersisa,
            IF(DATEDIFF(CURDATE(), p.due_date) > 0, 'telat', 'tepat waktu') as status_waktu
        FROM ts_peminjaman p
        INNER JOIN books b ON p.book_id = b.id
        INNER JOIN rt_book_uid rb ON p.uid_buffer_id = rb.uid_buffer_id
        WHERE p.user_id = ?
          AND p.status = 'dipinjam'
          AND p.is_deleted = 0
        ORDER BY p.tanggal_pinjam DESC
    ");
    
    $peminjamanStmt->bind_param('i', $userId);
    $peminjamanStmt->execute();
    $peminjamanResult = $peminjamanStmt->get_result();
    
    $peminjamanAktif = [];
    while ($row = $peminjamanResult->fetch_assoc()) {
        $peminjamanAktif[] = $row;
    }
    $peminjamanStmt->close();
    
} catch (Exception $e) {
    error_log('[Biodata] Error fetching peminjaman: ' . $e->getMessage());
    $peminjamanAktif = [];
}

// ============================================
// DETERMINE KATEGORI MEMBER BASED ON ROLE
// ============================================
// Karena semua user di tabel hanya punya role 'member'
// Kita tetap tampilkan sebagai "Member" dengan badge info
$kategoriMember = 'Member';
$kategoriBadge = 'info';
$kategoriIcon = 'fa-user';

// Jika di masa depan ada field khusus untuk mahasiswa/dosen, bisa gunakan ini:
// if ($member['kategori_member'] == 'mahasiswa') { ... }
// if ($member['kategori_member'] == 'dosen') { ... }

// ============================================
// PARSE ALAMAT (OPTIONAL)
// ============================================
$alamatParsed = [
    'full' => $member['alamat'] ?? '-',
    'kampung' => '-',
    'kelurahan' => '-',
    'kecamatan' => '-',
    'kota' => '-'
];

if ($member['alamat']) {
    // Coba parse alamat jika ada pattern "Kel", "Kec", "Kota"
    $alamat = $member['alamat'];
    
    // Extract Kelurahan
    if (preg_match('/Kel\.?\s+([^,]+)/i', $alamat, $matches)) {
        $alamatParsed['kelurahan'] = trim($matches[1]);
    }
    
    // Extract Kecamatan
    if (preg_match('/Kec\.?\s+([^,]+)/i', $alamat, $matches)) {
        $alamatParsed['kecamatan'] = trim($matches[1]);
    }
    
    // Extract Kota
    if (preg_match('/Kota\s+([^,]+)/i', $alamat, $matches)) {
        $alamatParsed['kota'] = trim($matches[1]);
    }
    
    // Extract Kampung/Lingkungan (bagian pertama sebelum "Kel")
    if (preg_match('/^([^,]+?)(?=\s*,?\s*Kel)/i', $alamat, $matches)) {
        $alamatParsed['kampung'] = trim($matches[1]);
    }
}

include_once '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/biodata_peminjaman.css">

<div class="container-fluid py-4 biodata-container">
    <!-- Page Title -->
    <div class="text-center mb-4">
        <h3 class="fw-bold text-gradient">
            <i class="fas fa-user-check me-2"></i>Biodata Member
        </h3>
        <p class="text-muted small">Verifikasi data sebelum melanjutkan peminjaman buku</p>
    </div>

    <!-- Profile Header Section -->
    <div class="profile-header-card mb-4">
        <div class="row align-items-center g-4">
            <!-- Avatar Section -->
            <div class="col-lg-2 col-md-3 text-center">
                <div class="avatar-wrapper">
                    <?php if ($member['foto_profil'] && file_exists($member['foto_profil'])): ?>
                        <img src="<?= htmlspecialchars($member['foto_profil']) ?>" alt="Foto Profil">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="badge-role badge bg-<?= $kategoriBadge ?> mt-2">
                    <i class="fas <?= $kategoriIcon ?> me-1"></i><?= $kategoriMember ?>
                </span>
            </div>
            
            <!-- Info Section -->
            <div class="col-lg-10 col-md-9">
                <div class="member-header">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                        <div>
                            <h4 class="member-name mb-1"><?= htmlspecialchars($member['nama']) ?></h4>
                            <p class="member-id mb-0"><?= htmlspecialchars($member['no_identitas']) ?></p>
                        </div>
                        <span class="status-badge status-<?= strtolower($member['status']) ?>">
                            <i class="fas fa-circle pulse-icon"></i><?= ucfirst($member['status']) ?>
                        </span>
                    </div>
                </div>
                
                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <div class="info-box">
                            <i class="fas fa-envelope info-icon"></i>
                            <div class="info-content">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?= htmlspecialchars($member['email']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box">
                            <i class="fas fa-phone info-icon"></i>
                            <div class="info-content">
                                <span class="info-label">Telepon</span>
                                <span class="info-value"><?= $member['no_telepon'] ?: '-' ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($alamatParsed['kampung'] !== '-' || $alamatParsed['kelurahan'] !== '-'): ?>
                    <!-- Alamat Parsed -->
                    <div class="col-12">
                        <div class="info-box">
                            <i class="fas fa-map-marker-alt info-icon"></i>
                            <div class="info-content">
                                <span class="info-label">Alamat Lengkap</span>
                                <div class="alamat-parsed">
                                    <?php if ($alamatParsed['kampung'] !== '-'): ?>
                                    <span class="alamat-item"><i class="fas fa-home me-1"></i><?= $alamatParsed['kampung'] ?></span>
                                    <?php endif; ?>
                                    <?php if ($alamatParsed['kelurahan'] !== '-'): ?>
                                    <span class="alamat-item"><i class="fas fa-map-pin me-1"></i>Kel. <?= $alamatParsed['kelurahan'] ?></span>
                                    <?php endif; ?>
                                    <?php if ($alamatParsed['kecamatan'] !== '-'): ?>
                                    <span class="alamat-item"><i class="fas fa-map-signs me-1"></i>Kec. <?= $alamatParsed['kecamatan'] ?></span>
                                    <?php endif; ?>
                                    <?php if ($alamatParsed['kota'] !== '-'): ?>
                                    <span class="alamat-item"><i class="fas fa-city me-1"></i><?= $alamatParsed['kota'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Alamat Biasa -->
                    <div class="col-12">
                        <div class="info-box">
                            <i class="fas fa-map-marker-alt info-icon"></i>
                            <div class="info-content">
                                <span class="info-label">Alamat</span>
                                <span class="info-value"><?= $alamatParsed['full'] ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card stat-primary">
                <div class="stat-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $kuotaTersisa ?> / <?= $member['max_peminjaman'] ?></div>
                    <div class="stat-label">Kuota Tersisa</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-success">
                <div class="stat-icon">
                    <i class="fas fa-book-reader"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $member['total_pinjam_aktif'] ?></div>
                    <div class="stat-label">Buku Dipinjam</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card <?= $member['total_denda'] > 0 ? 'stat-danger' : 'stat-success' ?>">
                <div class="stat-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">Rp <?= number_format($member['total_denda'], 0, ',', '.') ?></div>
                    <div class="stat-label">Total Denda</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Denda Warning -->
    <?php if ($member['total_denda'] > 0): ?>
    <div class="alert alert-warning-custom mb-4">
        <div class="alert-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="alert-content">
            <strong>Perhatian!</strong> Member memiliki denda <strong>Rp <?= number_format($member['total_denda'], 0, ',', '.') ?></strong>.
            <?php if ($member['total_denda'] >= 50000): ?>
            <br><span class="text-danger fw-bold">Tidak dapat meminjam hingga denda dibayar.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Peminjaman Aktif -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-light border-bottom">
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-list-ul me-2 text-primary"></i>Buku Sedang Dipinjam 
                <span class="badge bg-primary rounded-pill"><?= count($peminjamanAktif) ?></span>
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($peminjamanAktif)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
                    <p class="text-muted mb-0">Tidak ada buku yang sedang dipinjam</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="text-nowrap"><i class="fas fa-hashtag me-1 text-primary"></i>Kode</th>
                                <th class="text-nowrap"><i class="fas fa-book me-1 text-success"></i>Judul Buku</th>
                                <th class="text-nowrap"><i class="fas fa-barcode me-1 text-info"></i>Eksemplar</th>
                                <th class="text-nowrap"><i class="fas fa-calendar me-1 text-warning"></i>Tgl Pinjam</th>
                                <th class="text-nowrap"><i class="fas fa-clock me-1 text-danger"></i>Jatuh Tempo</th>
                                <th class="text-center"><i class="fas fa-hourglass-half me-1 text-secondary"></i>Sisa</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($peminjamanAktif as $item): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($item['kode_peminjaman']) ?></td>
                                <td><?= htmlspecialchars($item['judul_buku']) ?></td>
                                <td><code><?= htmlspecialchars($item['kode_eksemplar']) ?></code></td>
                                <td><?= date('d/m/Y', strtotime($item['tanggal_pinjam'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($item['due_date'])) ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $item['hari_tersisa'] <= 3 ? 'bg-danger' : 'bg-success' ?> rounded-pill">
                                        <?= $item['hari_tersisa'] ?> hari
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $item['status_waktu'] === 'telat' ? 'danger' : 'success' ?>">
                                        <?= $item['status_waktu'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="text-center action-section">
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <button type="button" class="btn-modern btn-confirm" onclick="confirmMember()"
                <?= $member['status'] !== 'aktif' || $kuotaTersisa <= 0 || $member['total_denda'] >= 50000 ? 'disabled' : '' ?>>
                <i class="fas fa-check-circle me-2"></i>Konfirmasi & Lanjutkan
            </button>
            <a href="index.php" class="btn-modern btn-cancel">
                <i class="fas fa-times-circle me-2"></i>Batal & Kembali
            </a>
        </div>

        <?php if ($member['status'] !== 'aktif' || $kuotaTersisa <= 0 || $member['total_denda'] >= 50000): ?>
        <div class="alert alert-danger-custom mt-4">
            <div class="alert-icon">
                <i class="fas fa-ban"></i>
            </div>
            <div class="alert-content">
                <strong>Member tidak dapat meminjam!</strong>
                <ul class="mb-0 mt-2 ps-3">
                    <?php if ($member['status'] !== 'aktif'): ?><li>Status: <?= ucfirst($member['status']) ?></li><?php endif; ?>
                    <?php if ($kuotaTersisa <= 0): ?><li>Kuota peminjaman habis</li><?php endif; ?>
                    <?php if ($member['total_denda'] >= 50000): ?><li>Denda ≥ Rp 50.000 harus dibayar terlebih dahulu</li><?php endif; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/dark-mode-utils.js"></script>

<script>
    const MEMBER_DATA = {
        id: <?= json_encode($member['id']) ?>,
        nama: <?= json_encode($member['nama']) ?>,
        no_identitas: <?= json_encode($member['no_identitas']) ?>,
        kuota_tersisa: <?= json_encode($kuotaTersisa) ?>,
        total_denda: <?= json_encode((float)$member['total_denda']) ?>
    };

    function confirmMember() {
        if (MEMBER_DATA.kuota_tersisa <= 0) {
            showError('Kuota Habis', 'Member sudah mencapai batas maksimal peminjaman.');
            return;
        }
        
        if (MEMBER_DATA.total_denda >= 50000) {
            showError('Denda Terlalu Besar', 'Member harus membayar denda terlebih dahulu.');
            return;
        }
        
        const config = window.DarkModeUtils.getSwalConfig({
            title: 'Konfirmasi Data Member',
            html: `
                <div class="text-start px-3">
                    <p class="mb-2"><strong>Nama:</strong> ${MEMBER_DATA.nama}</p>
                    <p class="mb-2"><strong>No Identitas:</strong> ${MEMBER_DATA.no_identitas}</p>
                    <p class="mb-2"><strong>Kuota Tersisa:</strong> ${MEMBER_DATA.kuota_tersisa} buku</p>
                    <p class="mb-0"><strong>Total Denda:</strong> Rp ${MEMBER_DATA.total_denda.toLocaleString('id-ID')}</p>
                </div>
                <hr class="my-3">
                <p class="text-muted small mb-0 px-3">Lanjutkan ke proses scan buku?</p>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check me-2"></i>Ya, Lanjutkan',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-primary me-2',
                cancelButton: 'btn btn-secondary'
            }
        });

        Swal.fire(config).then((result) => {
            if (result.isConfirmed) {
                showSuccess('Fitur Scan Buku', 'Halaman scan buku akan segera dibuat pada fase berikutnya.');
            }
        });
    }

    function showError(title, message) {
        if (window.DarkModeUtils) {
            window.DarkModeUtils.showError(title, message);
        } else {
            Swal.fire({ title: title, text: message, icon: 'error' });
        }
    }

    function showSuccess(title, message) {
        if (window.DarkModeUtils) {
            window.DarkModeUtils.showToast('info', message, 5000);
        } else {
            Swal.fire({ title: title, text: message, icon: 'info', timer: 5000 });
        }
    }
</script>

<?php
include_once '../../includes/footer.php';
ob_end_flush();
?>