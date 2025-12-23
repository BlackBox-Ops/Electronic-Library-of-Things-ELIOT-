<?php
/**
 * Edit Inventory Page - Enhanced Version with 2 Tab System
 * Path: web/apps/admin/edit_inventory.php
 * 
 * NEW FEATURES:
 * - Tab 1: Info Umum (Judul, ISBN, Kategori, dll)
 * - Tab 2: Info Detail (Publisher + Author lengkap)
 * - Switch untuk Keterangan field
 * - Multi-Author support (coming next)
 */

require_once '../../includes/config.php';

// Security: Check admin role
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
    include_once '../../404.php';
    exit;
}

// Ambil book_id
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($book_id <= 0) {
    header("Location: inventory.php");
    exit;
}

// Fetch data buku + publisher
$bookStmt = $conn->prepare("SELECT b.*, p.nama_penerbit, p.alamat as pub_alamat, p.no_telepon as pub_telepon, p.email as pub_email
                            FROM books b 
                            LEFT JOIN publishers p ON b.publisher_id = p.id 
                            WHERE b.id = ? AND b.is_deleted = 0");
$bookStmt->bind_param("i", $book_id);
$bookStmt->execute();
$bookResult = $bookStmt->get_result();
$book = $bookResult->fetch_assoc();

if (!$book) {
    header("Location: inventory.php");
    exit;
}

// Fetch pengarang yang terkait dengan buku ini
$currentAuthors = [];
$authorStmt = $conn->prepare("SELECT a.id, a.nama_pengarang, a.biografi
                              FROM authors a
                              JOIN rt_book_author rba ON a.id = rba.author_id
                              WHERE rba.book_id = ? AND rba.is_deleted = 0");
$authorStmt->bind_param("i", $book_id);
$authorStmt->execute();
$authorResult = $authorStmt->get_result();
$currentAuthorData = null;
while ($auth = $authorResult->fetch_assoc()) {
    $currentAuthors[] = $auth['id'];
    if (!$currentAuthorData) {
        $currentAuthorData = $auth; // Ambil author pertama untuk default
    }
}

// Fetch eksemplar
$eksemplarStmt = $conn->prepare("SELECT rbu.id, rbu.kode_eksemplar, rbu.kondisi, ub.uid 
                                 FROM rt_book_uid rbu
                                 LEFT JOIN uid_buffer ub ON rbu.uid_buffer_id = ub.id
                                 WHERE rbu.book_id = ? AND rbu.is_deleted = 0
                                 ORDER BY rbu.kode_eksemplar");
$eksemplarStmt->bind_param("i", $book_id);
$eksemplarStmt->execute();
$eksemplarResult = $eksemplarStmt->get_result();

include_once '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/inventory.css">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-green-primary">
                <i class="fas fa-edit me-2"></i>Edit Buku: <?= htmlspecialchars($book['judul_buku']) ?>
            </h4>
            <p class="text-secondary small mb-0">Update detail buku dan kondisi eksemplar berdasarkan tag RFID.</p>
        </div>
        <a href="inventory.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <!-- Form Edit dengan 2 Tab -->
    <form id="formEditBuku" method="POST" action="../controllers/EditInventoryController.php" enctype="multipart/form-data">
        <input type="hidden" name="book_id" value="<?= $book_id ?>">

        <!-- Card dengan Tab Navigation -->
        <div class="card border-0 shadow-sm card-adaptive mb-4">
            <!-- Tab Navigation -->
            <ul class="nav nav-tabs nav-fill border-bottom-0 bg-light-tab" id="editBookTab" style="padding: 0.75rem 1.5rem 0;">
                <li class="nav-item">
                    <button class="nav-link active py-3 fw-bold" id="info-umum-tab" data-bs-toggle="tab" data-bs-target="#info-umum-panel" type="button">
                        <i class="fas fa-info-circle me-2"></i>1. Info Umum
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 fw-bold" id="info-detail-tab" data-bs-toggle="tab" data-bs-target="#info-detail-panel" type="button">
                        <i class="fas fa-user-edit me-2"></i>2. Info Detail
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content card-body p-4">
                
                <!-- TAB 1: INFO UMUM -->
                <div class="tab-pane fade show active" id="info-umum-panel">
                    <h5 class="mb-4 text-primary-custom">Informasi Umum Buku</h5>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Judul Buku <span class="text-danger">*</span></label>
                            <input type="text" name="judul_buku" class="form-control" value="<?= htmlspecialchars($book['judul_buku']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ISBN / Serial</label>
                            <input type="text" name="isbn" class="form-control" value="<?= htmlspecialchars($book['isbn']) ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Kategori</label>
                            <select name="kategori" class="form-select">
                                <option value="buku" <?= $book['kategori'] == 'buku' ? 'selected' : '' ?>>Buku</option>
                                <option value="jurnal" <?= $book['kategori'] == 'jurnal' ? 'selected' : '' ?>>Jurnal</option>
                                <option value="prosiding" <?= $book['kategori'] == 'prosiding' ? 'selected' : '' ?>>Prosiding</option>
                                <option value="skripsi" <?= $book['kategori'] == 'skripsi' ? 'selected' : '' ?>>Skripsi</option>
                                <option value="laporan_pkl" <?= $book['kategori'] == 'laporan_pkl' ? 'selected' : '' ?>>Laporan PKL</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tahun Terbit</label>
                            <input type="number" name="tahun_terbit" class="form-control" value="<?= $book['tahun_terbit'] ?>" min="1900" max="2099">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Jumlah Halaman</label>
                            <input type="number" name="jumlah_halaman" class="form-control" value="<?= $book['jumlah_halaman'] ?>" min="1">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Lokasi Rak</label>
                            <input type="text" name="lokasi_rak" class="form-control" value="<?= htmlspecialchars($book['lokasi_rak']) ?>" placeholder="Contoh: A1, B2, C3">
                        </div>
                        
                        <!-- SWITCH KETERANGAN (Copy dari inventory.php) -->
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
                                        <input class="form-check-input" type="checkbox" id="switchKeterangan" 
                                               <?= !empty($book['keterangan']) ? 'checked' : '' ?>
                                               style="width: 3rem; height: 1.5rem; cursor: pointer;">
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
                                        value="<?= htmlspecialchars($book['keterangan'] ?? '') ?>"
                                        <?= empty($book['keterangan']) ? 'disabled' : '' ?>
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
                            <?php if ($book['cover_image']): ?>
                                <div class="mt-3 text-center">
                                    <img src="../../<?= htmlspecialchars($book['cover_image']) ?>" alt="Cover" class="img-thumbnail" style="max-height: 200px; border-radius: 8px;">
                                    <p class="small text-muted mt-2">Cover saat ini (akan diganti jika upload baru)</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-bold">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="4" placeholder="Deskripsi singkat tentang buku ini..."><?= htmlspecialchars($book['deskripsi'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="col-12 text-end mt-4 mb-3">
                            <button type="button" class="btn btn-primary px-4" onclick="switchToTab('info-detail-tab')">
                                Lanjut ke Info Detail <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: INFO DETAIL (PUBLISHER + AUTHOR) -->
                <div class="tab-pane fade" id="info-detail-panel">
                    <h5 class="mb-4 text-primary-custom">Informasi Detail: Penerbit & Penulis</h5>
                    <div class="row g-4">
                        
                        <!-- PUBLISHER SECTION -->
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 bg-light-custom">
                                <label class="form-label fw-bold text-success mb-3">
                                    <i class="fas fa-building me-2"></i>Data Penerbit
                                </label>
                                <select name="publisher_id" id="publisher_select" class="form-select mb-3">
                                    <option value="">-- Pilih Penerbit --</option>
                                    <option value="new">+ Tambah Penerbit Baru</option>
                                    <?php
                                    $pubQuery = $conn->query("SELECT id, nama_penerbit FROM publishers WHERE is_deleted = 0 ORDER BY nama_penerbit");
                                    while ($pub = $pubQuery->fetch_assoc()) {
                                        $selected = ($pub['id'] == $book['publisher_id']) ? 'selected' : '';
                                        echo "<option value='{$pub['id']}' $selected>" . htmlspecialchars($pub['nama_penerbit']) . "</option>";
                                    }
                                    ?>
                                </select>
                                
                                <!-- Publisher Detail (Show when existing selected) -->
                                <div id="publisher_detail" class="p-3 border rounded bg-white shadow-sm <?= empty($book['publisher_id']) ? 'd-none' : '' ?>">
                                    <small class="text-muted d-block mb-2 fw-bold">Detail Penerbit Saat Ini:</small>
                                    <div class="mb-2">
                                        <i class="fas fa-map-marker-alt text-success me-2"></i>
                                        <small><?= htmlspecialchars($book['pub_alamat'] ?: 'Tidak ada alamat') ?></small>
                                    </div>
                                    <div class="mb-2">
                                        <i class="fas fa-phone text-success me-2"></i>
                                        <small><?= htmlspecialchars($book['pub_telepon'] ?: 'Tidak ada telepon') ?></small>
                                    </div>
                                    <div>
                                        <i class="fas fa-envelope text-success me-2"></i>
                                        <small><?= htmlspecialchars($book['pub_email'] ?: 'Tidak ada email') ?></small>
                                    </div>
                                </div>
                                
                                <!-- New Publisher Fields -->
                                <div id="new_publisher_fields" class="d-none p-3 border rounded bg-white shadow-sm">
                                    <div class="mb-3">
                                        <label class="fw-bold text-dark">Nama Penerbit <span class="text-danger">*</span></label>
                                        <input type="text" name="new_publisher_name" class="form-control" placeholder="PT. Gramedia Pustaka Utama">
                                    </div>
                                    <div class="mb-3">
                                        <label class="fw-bold text-dark">Alamat Kantor</label>
                                        <textarea name="publisher_alamat" class="form-control" rows="2" placeholder="Jl. Raya No. 123, Jakarta"></textarea>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="fw-bold text-dark">No. Telepon</label>
                                            <input type="tel" name="publisher_no_telepon" class="form-control" placeholder="021-xxxxx">
                                        </div>
                                        <div class="col-6">
                                            <label class="fw-bold text-dark">Email</label>
                                            <input type="email" name="publisher_email" class="form-control" placeholder="info@penerbit.com">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- AUTHOR SECTION -->
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 bg-light-custom">
                                <!-- Multi-Author Switch -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <label class="form-label fw-bold text-primary mb-0">
                                        <i class="fas fa-pen-nib me-2"></i>Data Penulis
                                    </label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="switchMultiAuthor" name="is_multi_author" style="width: 3rem; height: 1.5rem; cursor: pointer;">
                                        <label class="form-check-label small fw-bold ms-2" for="switchMultiAuthor" style="cursor: pointer;">
                                            Multi Penulis?
                                        </label>
                                    </div>
                                </div>

                                <select name="author_id" id="author_select" class="form-select mb-3">
                                    <option value="">-- Pilih Penulis --</option>
                                    <option value="new">+ Tambah Penulis Baru</option>
                                    <?php
                                    $authQuery = $conn->query("SELECT id, nama_pengarang FROM authors WHERE is_deleted = 0 ORDER BY nama_pengarang");
                                    while ($auth = $authQuery->fetch_assoc()) {
                                        $selected = in_array($auth['id'], $currentAuthors) ? 'selected' : '';
                                        echo "<option value='{$auth['id']}' $selected>" . htmlspecialchars($auth['nama_pengarang']) . "</option>";
                                    }
                                    ?>
                                </select>
                                
                                <!-- Author Detail (Show when existing selected) -->
                                <div id="author_detail" class="p-3 border rounded bg-white shadow-sm <?= empty($currentAuthorData) ? 'd-none' : '' ?>">
                                    <small class="text-muted d-block mb-2 fw-bold">Biografi Penulis:</small>
                                    <p class="small mb-0" id="author_bio_text">
                                        <?= htmlspecialchars($currentAuthorData['biografi'] ?? 'Tidak ada biografi') ?>
                                    </p>
                                </div>
                                
                                <!-- New Author Fields -->
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

                                <!-- PERAN FIELD (Conditional - Show if Multi-Author ON) -->
                                <div id="peran_field" class="d-none mt-3">
                                    <label class="form-label fw-bold text-warning">
                                        <i class="fas fa-user-tag me-2"></i>Peran Penulis
                                    </label>
                                    <select name="peran_author" id="peran_select" class="form-select">
                                        <option value="penulis_utama">Penulis Utama</option>
                                        <option value="co_author">Co-Author</option>
                                        <option value="editor">Editor</option>
                                        <option value="translator">Translator</option>
                                    </select>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Aktif karena mode multi-penulis diaktifkan
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4 mb-3">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchToTab('info-umum-tab')">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </button>
                        <span class="text-muted small">
                            <i class="fas fa-info-circle me-2"></i>Klik "Update Aset" di bawah untuk menyimpan
                        </span>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- Daftar Eksemplar (Di luar tab, tetap terlihat) -->
        <div class="card border-0 shadow-sm card-adaptive mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">
                        <i class="fas fa-tags me-2 text-warning"></i>
                        Eksemplar RFID (<?= $eksemplarResult->num_rows ?> unit)
                    </h5>
                    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahEksemplarBaru">
                        <i class="fas fa-plus me-2"></i>Tambah Eksemplar Baru
                    </button>
                </div>

                <?php if ($eksemplarResult->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode Eksemplar</th>
                                    <th>UID RFID</th>
                                    <th>Kondisi Saat Ini</th>
                                    <th>Ubah Kondisi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="eksemplarTableBody">
                                <?php $index = 0; while ($eks = $eksemplarResult->fetch_assoc()): ?>
                                    <tr data-eks-id="<?= $eks['id'] ?>">
                                        <td><strong><?= htmlspecialchars($eks['kode_eksemplar']) ?></strong></td>
                                        <td><code><?= htmlspecialchars($eks['uid']) ?></code></td>
                                        <td>
                                            <span class="badge bg-<?= $eks['kondisi'] == 'baik' ? 'success' : ($eks['kondisi'] == 'rusak_ringan' ? 'warning text-dark' : 'danger') ?>">
                                                <?= ucwords(str_replace('_', ' ', $eks['kondisi'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <select name="eksemplar[<?= $index ?>][kondisi]" class="form-select form-select-sm">
                                                <option value="baik" <?= $eks['kondisi'] == 'baik' ? 'selected' : '' ?>>Baik</option>
                                                <option value="rusak_ringan" <?= $eks['kondisi'] == 'rusak_ringan' ? 'selected' : '' ?>>Rusak Ringan</option>
                                                <option value="rusak_berat" <?= $eks['kondisi'] == 'rusak_berat' ? 'selected' : '' ?>>Rusak Berat</option>
                                                <option value="hilang" <?= $eks['kondisi'] == 'hilang' ? 'selected' : '' ?>>Hilang</option>
                                            </select>
                                            <input type="hidden" name="eksemplar[<?= $index ?>][id]" value="<?= $eks['id'] ?>">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger btn-delete-eksemplar" data-id="<?= $eks['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php $index++; endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                        <p class="mb-0">Belum ada eksemplar RFID terdaftar</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Submit Button (Dynamic) -->
        <div class="mt-4 text-end">
            <div id="button-container">
                <!-- Default: Update Button -->
                <button type="submit" id="btnUpdate" class="btn btn-green px-5 shadow-sm">
                    <i class="fas fa-save me-2"></i>Update Aset
                </button>
                
                <!-- Hidden: Delete Book Button -->
                <button type="button" id="btnDeleteBook" class="btn btn-danger px-5 shadow-sm d-none" onclick="confirmDeleteBook()">
                    <i class="fas fa-trash-alt me-2"></i>Hapus Buku Ini
                </button>
            </div>
            
            <!-- Info Badge -->
            <div class="mt-3">
                <span class="badge bg-info text-dark" id="badge-eksemplar-status">
                    <i class="fas fa-info-circle me-1"></i>
                    <span id="remaining-count"><?= $eksemplarResult->num_rows ?></span> eksemplar tersisa
                </span>
            </div>
        </div>
    </form>
</div>

<!-- Modal Tambah Eksemplar (Sama seperti sebelumnya) -->
<div class="modal fade" id="modalTambahEksemplarBaru" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah Eksemplar Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Scan RFID fisik baru untuk menambah eksemplar ke buku ini. Kode eksemplar akan digenerate otomatis.
                </div>
                <div class="text-center my-4">
                    <button type="button" id="btnScanEksemplarBaru" class="btn btn-primary btn-lg px-5 shadow" onclick="triggerScanEksemplarBaru()">
                        <i class="fas fa-qrcode me-2"></i>SCAN RFID BARU
                    </button>
                </div>
                <div id="containerScanBaru" class="border rounded p-3 bg-light-custom" style="min-height: 200px;">
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-barcode fa-4x mb-3 opacity-50"></i>
                        <p class="fw-bold mb-0">Belum ada RFID di-scan</p>
                    </div>
                </div>
                <input type="hidden" id="inputNewEksemplar" value="[]">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Batal
                </button>
                <button type="button" id="btnSimpanEksemplarBaru" class="btn btn-green px-4 shadow-sm" disabled>
                    <i class="fas fa-save me-2"></i>Simpan Eksemplar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/edit_inventory.js"></script>

<?php include_once '../includes/footer.php'; ?>