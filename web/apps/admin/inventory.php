<?php
/**
 * Inventory Management Page - Enhanced Version
 * Path: web/apps/admin/inventory.php
 * 
 * FEATURES:
 * - UC-1: Publisher form dengan 4 fields lengkap
 * - UC-2: Author form dengan biografi textarea (max 200 char + counter)
 * - UC-3: RFID scan dengan alert hijau/merah
 * - UC-4: Auto-generate kode eksemplar
 * - UC-5: Kondisi buku dropdown per unit
 * - UC-6: Multi-author toggle dengan conditional role
 * 
 * FIXED: Preview link opens in same window (no target="_blank")
 */

require_once '../../includes/config.php'; 

// Security: Check admin role
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
    include_once '../../404.php';
    exit;
}

// Global error handler
set_exception_handler(function($e) {
    error_log($e->getMessage());
    header("Location: /ELIOT/web/500.php");
    exit;
});

// Statistics Query
$statsQuery = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM books WHERE is_deleted = 0) as total_judul,
        (SELECT COUNT(*) FROM rt_book_uid WHERE is_deleted = 0) as total_unit_rfid,
        (SELECT SUM(jumlah_eksemplar) FROM books WHERE is_deleted = 0) as total_stok_seharusnya,
        (SELECT COUNT(*) FROM uid_buffer WHERE is_labeled = 'no' AND is_deleted = 0 AND jenis = 'pending') as uid_belum_dipakai
");
$stats = $statsQuery->fetch_assoc();

// Main Query
$sql = "SELECT b.*, 
        p.nama_penerbit,
        GROUP_CONCAT(DISTINCT a.nama_pengarang SEPARATOR ', ') as daftar_pengarang,
        (SELECT COUNT(*) FROM rt_book_uid rbu WHERE rbu.book_id = b.id AND rbu.is_deleted = 0) as unit_tertag
        FROM books b
        LEFT JOIN publishers p ON b.publisher_id = p.id
        LEFT JOIN rt_book_author rba ON b.id = rba.book_id AND rba.is_deleted = 0
        LEFT JOIN authors a ON rba.author_id = a.id AND a.is_deleted = 0
        WHERE b.is_deleted = 0
        GROUP BY b.id
        ORDER BY b.created_at DESC";

$result = $conn->query($sql);
include_once '../includes/header.php'; 
?>

<link rel="stylesheet" href="../assets/css/inventory.css">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-green-primary">📦 Asset & Inventory Control</h4>
            <p class="text-secondary small mb-0">Registrasi aset dengan sistem scan RFID terpadu.</p>
        </div>
        <a href="modal_inventory.php" class="btn btn-green shadow-sm">
            <i class="fas fa-plus-circle me-2"></i>Registrasi Aset Baru
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card p-3 border-0 shadow-sm card-adaptive">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-primary-subtle text-primary me-3 p-3 rounded">
                        <i class="fas fa-book fa-lg"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block fw-bold">TOTAL JUDUL</small>
                        <h4 class="mb-0 fw-bold"><?= number_format($stats['total_judul']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card p-3 border-0 shadow-sm card-adaptive">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-success-subtle text-success me-3 p-3 rounded">
                        <i class="fas fa-tags fa-lg"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block fw-bold">RFID TERPASANG</small>
                        <h4 class="mb-0 fw-bold"><?= $stats['total_unit_rfid'] ?> / <?= $stats['total_stok_seharusnya'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card p-3 border-0 shadow-sm card-adaptive">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-warning-subtle text-warning me-3 p-3 rounded">
                        <i class="fas fa-qrcode fa-lg"></i>
                    </div>
                    <div class="flex-grow-1">
                        <small class="text-muted d-block fw-bold">UID TERSEDIA</small>
                        <h4 class="mb-0 fw-bold">
                            <span id="stats_uid_available"><?= number_format($stats['uid_belum_dipakai']) ?></span>
                        </h4>
                    </div>

                    <!-- Reset button placed here: compact, contextually next to UID stat -->
                    <div class="ms-3 text-end">
                        <button id="btnResetUID" class="btn btn-sm btn-outline-danger" title="Reset expired UID">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter dan Search Controls -->
    <div id="filtersBlock" class="row mb-4">
        <div class="col-md-3">
            <label for="filterKategori" class="fw-bold mb-2">Filter Kategori</label>
            <select id="filterKategori" class="form-select">
                <option value="">Semua Kategori</option>
                <?php
                // Mengambil kategori unik dari database
                $kategoriQuery = $conn->query("SELECT DISTINCT kategori FROM books WHERE is_deleted = 0");
                while ($kat = $kategoriQuery->fetch_assoc()) {
                    echo "<option value='{$kat['kategori']}'>{$kat['kategori']}</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-3">
            <label for="searchTitle" class="fw-bold mb-2">Cari Berdasarkan Judul</label>
            <input type="text" id="searchTitle" class="form-control" placeholder="Masukkan judul buku...">
        </div>

        <div class="col-md-3">
            <label for="searchAuthor" class="fw-bold mb-2">Cari Berdasarkan Author</label>
            <input type="text" id="searchAuthor" class="form-control" placeholder="Masukkan nama author...">
        </div>

        <div class="col-md-3">
            <label for="searchPublisher" class="fw-bold mb-2">Cari Berdasarkan Publisher</label>
            <input type="text" id="searchPublisher" class="form-control" placeholder="Masukkan nama publisher...">
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-8">
            <div class="d-flex gap-2">
                <button id="btnApplyFilters" class="btn btn-primary shadow-sm">
                    <i class="fas fa-filter me-2"></i>Terapkan Filter
                </button>
                <button id="btnClearFilters" class="btn btn-outline-secondary">
                    <i class="fas fa-eraser me-2"></i>Hapus Filter
                </button>
            </div>
        </div>
    </div>

    <!-- Main Table -->
    <div class="card border-0 shadow-sm overflow-hidden card-adaptive">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Informasi Aset</th>
                        <th>Penerbit</th>
                        <th>Kategori</th>
                        <th>Stok (Tag/Total)</th>
                        <th class="text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                    <tr class="main-row" onclick="toggleRow('row-<?= $row['id'] ?>')">
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-chevron-right me-3 rotate-icon" id="icon-row-<?= $row['id'] ?>"></i>
                                <div>
                                    <div class="fw-bold text-primary-custom"><?= htmlspecialchars($row['judul_buku']) ?></div>
                                    <small class="text-muted">
                                        ISBN: <?= htmlspecialchars($row['isbn']) ?> | 
                                        <?= htmlspecialchars($row['daftar_pengarang'] ?: 'Anonim') ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <small><?= htmlspecialchars($row['nama_penerbit'] ?: '-') ?></small>
                        </td>
                        <td><span class="badge-category"><?= ucfirst($row['kategori']) ?></span></td>
                        <td>
                            <span class="fw-bold text-success"><?= $row['unit_tertag'] ?></span> / 
                            <span class="text-muted"><?= $row['jumlah_eksemplar'] ?></span>
                        </td>
                        <td class="text-center pe-4">
                            <div class="d-inline-flex gap-2">
                                <a href="edit_inventory.php?id=<?= $row['id'] ?>" class="btn btn-mini btn-light border" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <!-- FIXED: Removed target="_blank" - opens in same window -->
                                <a href="preview.php?id=<?= $row['id'] ?>" class="btn btn-mini btn-outline-primary border" title="Preview">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <!-- DETAIL ROW -->
                    <tr id="row-<?= $row['id'] ?>" class="detail-row d-none">
                        <td colspan="5" class="p-0 border-0 bg-light-custom">
                            <div class="detail-bg-container shadow-sm">
                                <h6 class="text-uppercase fw-bold mb-3 text-muted">
                                    <i class="fas fa-list me-2"></i>Detail Unit RFID
                                </h6>
                                <table class="table table-sm mb-0 table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Kode Unit  Buku</th>
                                            <th>Kode RFID Buku</th>
                                            <th>Kondisi</th>
                                            <th>Tgl Registrasi</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $uSql = "SELECT rbu.*, ub.uid, ub.jenis 
                                                    FROM rt_book_uid rbu
                                                    JOIN uid_buffer ub ON rbu.uid_buffer_id = ub.id
                                                    WHERE rbu.book_id = {$row['id']} 
                                                    AND rbu.is_deleted = 0
                                                    ORDER BY rbu.tanggal_registrasi DESC";
                                            $uRes = $conn->query($uSql);
                                            
                                            if ($uRes && $uRes->num_rows > 0):
                                                while($unit = $uRes->fetch_assoc()): 
                                                    $badgeKondisi = [
                                                        'baik' => 'bg-success',
                                                        'rusak_ringan' => 'bg-dark',
                                                        'rusak_berat' => 'bg-secondary',
                                                        'hilang' => 'bg-danger'
                                                    ];
                                                    $badgeClass = $badgeKondisi[$unit['kondisi']] ?? 'bg-secondary';
                                            ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($unit['kode_eksemplar']) ?></strong></td>
                                            <td><code><?= htmlspecialchars($unit['uid']) ?></code></td>
                                            <td>
                                                <span class="badge <?= $badgeClass ?>">
                                                    <?= ucwords(str_replace('_', ' ', $unit['kondisi'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('d M Y', strtotime($unit['tanggal_registrasi'])) ?>
                                                </small>
                                            </td>
                                            <td><span class="badge bg-success">Tersedia</span></td>
                                        </tr>
                                        <?php 
                                            endwhile;
                                        else: 
                                        ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">
                                                <i class="fas fa-inbox me-2"></i>Belum ada unit RFID terdaftar
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 d-block opacity-50"></i>
                            <p class="mb-0">Belum ada data aset terdaftar</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PAGINATION CONTROLS -->
    <div class="d-flex justify-content-end mt-3">
        <nav aria-label="Inventory pagination">
            <ul class="pagination mb-0" id="inventoryPagination"></ul>
        </nav>
    </div>
</div>


<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Custom JS -->
<script src="../assets/js/inventory.js"></script>

<!-- client-side filter (external) -->
<script src="../assets/js/filter_inventory.js"></script>

<?php include_once '../includes/footer.php'; ?>