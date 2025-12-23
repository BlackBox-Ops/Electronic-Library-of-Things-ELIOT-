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
        <button class="btn btn-green shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahAset">
            <i class="fas fa-plus-circle me-2"></i>Registrasi Aset Baru
        </button>
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
                    <div>
                        <small class="text-muted d-block fw-bold">UID TERSEDIA</small>
                        <h4 class="mb-0 fw-bold"><?= number_format($stats['uid_belum_dipakai']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter dan Search Controls -->
    <div class="row mb-4">
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
            <label for="searchAuthor" class="fw-bold mb-2">Cari Berdasarkan Author</label>
            <input type="text" id="searchAuthor" class="form-control" placeholder="Masukkan nama author...">
        </div>
        <div class="col-md-3">
            <label for="searchPublisher" class="fw-bold mb-2">Cari Berdasarkan Publisher</label>
            <input type="text" id="searchPublisher" class="form-control" placeholder="Masukkan nama publisher...">
        </div>
        <div class="col-md-3">
            <label class="fw-bold mb-2 d-block">Reset UID Expired</label>
            <button type="button" id="btnResetUID" class="btn btn-warning w-100 shadow-sm" onclick="resetExpiredUID()">
                <i class="fas fa-undo-alt me-2"></i>Reset UID Buffer
            </button>
            <small class="text-muted d-block mt-1">Reset UID pending > 5 menit</small>
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
                            <a href="edit_inventory.php?id=<?= $row['id'] ?>" class="btn btn-mini btn-light border" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
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
                                            <th>Kode Unit</th>
                                            <th>RFID UID</th>
                                            <th>Kondisi</th>
                                            <th>Tgl Registrasi</th>
                                            <th>Status</th>
                                            <th>Keterangan</th>
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
                                                        'rusak_ringan' => 'bg-warning text-dark',
                                                        'rusak_berat' => 'bg-danger',
                                                        'hilang' => 'bg-dark'
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
                                            <td><?= htmlspecialchars($unit['keterangan'] ?? '-') ?></td>
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
</div>

<!-- MODAL REGISTRASI ASET BARU -->
<div class="modal fade" id="modalTambahAset" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg modal-adaptive">
            <div class="modal-header border-bottom bg-light-tab">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-layer-group me-2 text-success"></i>Registrasi Aset Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="formRegistrasiAset" enctype="multipart/form-data">
                <!-- TABS NAVIGATION -->
                <ul class="nav nav-tabs nav-fill border-bottom-0 bg-light-tab sticky-top" id="sapTab" style="top: 0; z-index: 10;">
                    <li class="nav-item">
                        <button class="nav-link active py-3 fw-bold" id="asset-tab-btn" data-bs-toggle="tab" data-bs-target="#asset-panel" type="button">
                            <i class="fas fa-info-circle me-2"></i>1. Info Aset
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-3 fw-bold" id="auth-tab-btn" data-bs-toggle="tab" data-bs-target="#auth-panel" type="button">
                            <i class="fas fa-user-edit me-2"></i>2. Penulis/Penerbit
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-3 fw-bold" id="rfid-tab-btn" data-bs-toggle="tab" data-bs-target="#rfid-panel" type="button">
                            <i class="fas fa-qrcode me-2"></i>3. Scan RFID
                        </button>
                    </li>
                </ul>

                <div class="tab-content modal-body modal-body-adaptive" style="max-height: 60vh; overflow-y: auto;">
                    <!-- TAB 1: INFO ASET -->
                    <div class="tab-pane fade show active p-4" id="asset-panel">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Judul Aset <span class="text-danger">*</span></label>
                                <input type="text" name="judul" id="input_judul" class="form-control" required placeholder="Masukkan judul buku/aset">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">ISBN / Serial <span class="text-danger">*</span></label>
                                <input type="text" name="identifier" class="form-control" required placeholder="978-xxx-xxx">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Keterangan (Opsional)</label>
                                <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Edisi 2024, Mahasiswa John Doe">
                                <small class="text-muted">Gunakan jika judul buku sama dengan yang sudah ada</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Kategori</label>
                                <select name="kategori" class="form-select">
                                    <option value="buku">Buku</option>
                                    <option value="jurnal">Jurnal</option>
                                    <option value="prosiding">Prosiding</option>
                                    <option value="skripsi">Skripsi</option>
                                    <option value="laporan_pkl">Laporan PKL</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Tahun Terbit</label>
                                <input type="number" name="tahun_terbit" class="form-control" min="1900" max="2099" placeholder="2024">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Jumlah Halaman</label>
                                <input type="number" name="jumlah_halaman" class="form-control" min="1" placeholder="350">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Lokasi Rak</label>
                                <input type="text" name="lokasi" class="form-control" placeholder="Contoh: A1, B2, C3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Jumlah Stok <span class="text-danger">*</span></label>
                                <input type="number" name="jumlah_eksemplar" id="input_stok" class="form-control" value="1" min="1" required>
                                <small class="text-muted">Jumlah fisik buku yang akan di-scan</small>
                            </div>
                            
                            <!-- SWITCH ENABLE KETERANGAN -->
                            <div class="col-12">
                                <div class="border rounded p-3 bg-light-custom">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <label class="form-label fw-bold text-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i>Keterangan Tambahan
                                            </label>
                                            <small class="d-block text-muted mt-1">
                                                Aktifkan jika buku dengan judul sama namun beda edisi/cetakan/penulis
                                            </small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="switchKeterangan" style="width: 3rem; height: 1.5rem; cursor: pointer;">
                                            <label class="form-check-label ms-2 fw-bold text-muted" for="switchKeterangan" style="cursor: pointer;">
                                                Aktifkan
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div id="keterangan_field_wrapper">
                                        <label class="form-label fw-bold text-dark">Detail Keterangan</label>
                                        <input 
                                            type="text" 
                                            name="keterangan" 
                                            id="input_keterangan" 
                                            class="form-control" 
                                            placeholder="Contoh: Edisi 2024, Revisi oleh Dr. Ahmad" 
                                            disabled
                                        >
                                        <small class="text-muted d-block mt-1">
                                            <i class="fas fa-lightbulb me-1"></i>
                                            <strong>Contoh:</strong> "Edisi ke-5 (2024)", "Revisi oleh Prof. John Doe", "Cetakan Mahasiswa"
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-image me-2 text-primary"></i>Cover Image (Opsional)
                                </label>
                                <input type="file" name="cover_image" id="input_cover" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                                <small class="text-muted">Max: 2MB | Format: JPG, PNG, GIF, WEBP</small>
                                <div id="cover-preview" class="mt-3 d-none text-center">
                                    <img id="cover-preview-img" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 2px solid #dee2e6; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Deskripsi</label>
                                <textarea name="deskripsi" class="form-control" rows="3" placeholder="Deskripsi singkat tentang buku ini..."></textarea>
                            </div>
                            <div class="col-12 text-end mt-4 mb-3">
                                <button type="button" class="btn btn-primary px-4" onclick="switchTab('auth-tab-btn')">
                                    Lanjut <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: UC-1 & UC-2 & UC-6 - PENULIS/PENERBIT -->
                    <div class="tab-pane fade p-4" id="auth-panel">
                        <div class="row g-4">
                            <!-- UC-2 & UC-6: AUTHOR SECTION -->
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100 bg-light-custom">
                                    <!-- UC-6: Multi-Author Toggle -->
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <label class="form-label fw-bold text-primary mb-0">
                                            <i class="fas fa-pen-nib me-2"></i>Data Penulis
                                        </label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="switchMultiAuthor" name="is_multi_author">
                                            <label class="form-check-label small fw-bold" for="switchMultiAuthor">Multi Penulis?</label>
                                        </div>
                                    </div>

                                    <select name="author_id" id="author_select" class="form-select mb-3">
                                        <option value="">-- Pilih Penulis --</option>
                                        <option value="new">Tambah Penulis Baru</option>
                                        <?php 
                                        $as = $conn->query("SELECT id, nama_pengarang FROM authors WHERE is_deleted = 0 ORDER BY nama_pengarang");
                                        while($a = $as->fetch_assoc()) {
                                            echo "<option value='{$a['id']}'>" . htmlspecialchars($a['nama_pengarang']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                    
                                    <!-- UC-2: New Author Fields dengan Biografi -->
                                    <div id="new_author_fields" class="d-none p-3 border rounded bg-white shadow-sm">
                                        <div class="mb-3">
                                            <label class="fw-bold text-dark">Nama Lengkap Pengarang</label>
                                            <input type="text" name="new_author_name" class="form-control" placeholder="Contoh: Prof. Dr. Eliot Anderson">
                                        </div>
                                        <div class="mb-0">
                                            <label class="fw-bold text-dark">Biografi Singkat</label>
                                            <textarea name="author_biografi" id="author_biografi_new" class="form-control" rows="4" maxlength="200" placeholder="Tulis biografi penulis maksimal 200 karakter..."></textarea>
                                            <div class="text-end mt-1">
                                                <small class="text-muted" id="char-count-new">0/200</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- UC-6: Role Field (Conditional) -->
                                    <div id="peran_field" class="d-none mt-3">
                                        <label class="form-label fw-bold text-warning">
                                            <i class="fas fa-user-tag me-2"></i>Peran Penulis
                                        </label>
                                        <select name="peran_author" class="form-select">
                                            <option value="penulis_utama">Penulis Utama</option>
                                            <option value="co_author">Co-Author</option>
                                            <option value="editor">Editor</option>
                                            <option value="translator">Translator</option>
                                        </select>
                                        <small class="text-muted">Aktif karena mode multi-penulis diaktifkan</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- UC-1: PUBLISHER SECTION (4 Fields) -->
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100 bg-light-custom">
                                    <label class="form-label fw-bold text-success mb-3">
                                        <i class="fas fa-building me-2"></i>Data Penerbit
                                    </label>
                                    <select name="publisher_id" id="publisher_select" class="form-select mb-3">
                                        <option value="">-- Pilih Penerbit --</option>
                                        <option value="new">Tambah Penerbit Baru</option>
                                        <?php 
                                        $ps = $conn->query("SELECT id, nama_penerbit FROM publishers WHERE is_deleted = 0 ORDER BY nama_penerbit");
                                        while($p = $ps->fetch_assoc()) {
                                            echo "<option value='{$p['id']}'>" . htmlspecialchars($p['nama_penerbit']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                    
                                    <!-- UC-1: New Publisher Fields (4 Fields Lengkap) -->
                                    <div id="new_pub_fields" class="d-none p-3 border rounded bg-white shadow-sm">
                                        <div class="mb-3">
                                            <label class="fw-bold text-dark">Nama Penerbit <span class="text-danger">*</span></label>
                                            <input type="text" name="new_pub_name" class="form-control" placeholder="PT. Gramedia Pustaka Utama">
                                        </div>
                                        <div class="mb-3">
                                            <label class="fw-bold text-dark">Alamat Kantor</label>
                                            <textarea name="pub_alamat" class="form-control" rows="2" placeholder="Jl. Raya No. 123, Jakarta"></textarea>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="fw-bold text-dark">No. Telepon</label>
                                                <input type="tel" name="pub_telepon" class="form-control" placeholder="021-xxxxx">
                                            </div>
                                            <div class="col-6">
                                                <label class="fw-bold text-dark">Email</label>
                                                <input type="email" name="pub_email" class="form-control" placeholder="info@penerbit.com">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="switchTab('asset-tab-btn')">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </button>
                            <button type="button" class="btn btn-primary px-4" onclick="switchTab('rfid-tab-btn')">
                                Lanjut ke Scan <i class="fas fa-qrcode ms-2"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- TAB 3: UC-3, UC-4, UC-5 - SCAN RFID -->
                    <div class="tab-pane fade p-4" id="rfid-panel">
                        <div class="alert alert-info border-0 shadow-sm mb-4">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle fa-2x me-3 mt-1"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Panduan Scan RFID</h6>
                                    <p class="mb-0">Scan RFID untuk setiap unit fisik buku. Jumlah scan harus sesuai dengan jumlah stok yang diinput di Tab 1.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mb-4">
                            <button type="button" id="btnScanTrigger" class="btn btn-primary btn-lg px-5 shadow" onclick="triggerScan()">
                                <i class="fas fa-qrcode me-2"></i>SCAN RFID BARU
                            </button>
                        </div>
                        
                        <!-- UC-4 & UC-5: Container untuk hasil scan -->
                        <div id="unit-rfid-container" style="max-height: 400px; overflow-y: auto;" class="border rounded p-3 bg-light-custom">
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-barcode fa-4x mb-3 d-block opacity-50"></i>
                                <p class="mb-0 fw-bold">Belum ada RFID yang di-scan</p>
                                <small>Klik tombol di atas untuk mulai scan</small>
                            </div>
                        </div>
                        
                        <!-- Hidden input untuk JSON data -->
                        <input type="hidden" name="rfid_units" id="input_rfid_units" value="[]">
                        
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="switchTab('auth-tab-btn')">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </button>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-info fs-6 px-3 py-2" id="badge-scan-count">0 Unit</span>
                                <button type="button" class="btn btn-warning" onclick="clearAllScans()">
                                    <i class="fas fa-redo me-2"></i>Reset Semua
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="padding: 1.25rem 1.5rem; border-top: none;">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Batal
                    </button>
                    <button type="submit" id="btnSimpan" class="btn btn-green px-4 shadow-sm" disabled>
                        <i class="fas fa-save me-2"></i>Simpan Aset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Custom JS -->
<script src="../assets/js/inventory.js"></script>

<?php include_once '../includes/footer.php'; ?>