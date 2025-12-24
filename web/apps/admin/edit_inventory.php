<?php
/**
 * Edit Inventory Page - ENHANCED VERSION
 * Path: web/apps/admin/edit_inventory.php
 * 
 * NEW FEATURES:
 * ✅ Tombol "Tambah Eksemplar Baru" dengan scan RFID
 * ✅ Fixed delete eksemplar functionality
 * ✅ Integration dengan AddEksemplarController.php
 * ✅ All original features preserved
 */

require_once '../../includes/config.php';

// Security: Check admin role
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
    include_once '../../404.php';
    exit;
}

// Get book ID
$book_id = intval($_GET['id'] ?? 0);

if ($book_id <= 0) {
    header("Location: inventory.php");
    exit;
}

// Fetch book data
$bookQuery = $conn->query("SELECT b.*, p.nama_penerbit, p.id as pub_id
                           FROM books b
                           LEFT JOIN publishers p ON b.publisher_id = p.id
                           WHERE b.id = $book_id AND b.is_deleted = 0
                           LIMIT 1");

if (!$bookQuery || $bookQuery->num_rows === 0) {
    header("Location: inventory.php");
    exit;
}

$bookData = $bookQuery->fetch_assoc();

// Fetch authors for this book
$authorQuery = $conn->query("SELECT a.id, a.nama_pengarang, a.biografi, rba.peran
                             FROM rt_book_author rba
                             JOIN authors a ON rba.author_id = a.id
                             WHERE rba.book_id = $book_id 
                             AND rba.is_deleted = 0
                             AND a.is_deleted = 0
                             LIMIT 1");

$currentAuthor = $authorQuery->fetch_assoc();

// Fetch eksemplar (book units)
$eksemplarQuery = $conn->query("SELECT rbu.*, ub.uid
                                FROM rt_book_uid rbu
                                JOIN uid_buffer ub ON rbu.uid_buffer_id = ub.id
                                WHERE rbu.book_id = $book_id
                                AND rbu.is_deleted = 0
                                ORDER BY rbu.tanggal_registrasi DESC");

include_once '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/inventory.css">

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-green-primary">
                <i class="fas fa-edit me-2"></i>Edit Aset Inventori
            </h4>
            <p class="text-secondary small mb-0">Edit detail buku dan unit RFID</p>
        </div>
        <a href="inventory.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <!-- Main Form -->
    <form id="formEditInventory" enctype="multipart/form-data">
        <input type="hidden" name="book_id" value="<?= $book_id ?>">

        <!-- TABS NAVIGATION -->
        <ul class="nav nav-tabs nav-fill border-bottom-0 bg-light-tab mb-4 shadow-sm rounded-top" style="overflow: hidden;">
            <li class="nav-item">
                <button class="nav-link active py-3 fw-bold" id="asset-tab-btn" data-bs-toggle="tab" data-bs-target="#asset-panel" type="button">
                    <i class="fas fa-info-circle me-2"></i>1. Info Aset
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 fw-bold" id="author-tab-btn" data-bs-toggle="tab" data-bs-target="#author-panel" type="button">
                    <i class="fas fa-user-edit me-2"></i>2. Penulis/Penerbit
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 fw-bold" id="eksemplar-tab-btn" data-bs-toggle="tab" data-bs-target="#eksemplar-panel" type="button">
                    <i class="fas fa-tags me-2"></i>3. Kelola Eksemplar
                </button>
            </li>
        </ul>

        <div class="tab-content bg-white shadow-sm rounded-bottom p-0">
            <!-- ============================================ -->
            <!-- TAB 1: INFO ASET -->
            <!-- ============================================ -->
            <div class="tab-pane fade show active p-4" id="asset-panel">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Judul Buku <span class="text-danger">*</span></label>
                        <input type="text" name="judul_buku" class="form-control" required 
                               value="<?= htmlspecialchars($bookData['judul_buku']) ?>" 
                               placeholder="Masukkan judul buku">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">ISBN <span class="text-danger">*</span></label>
                        <input type="text" name="isbn" class="form-control" required 
                               value="<?= htmlspecialchars($bookData['isbn']) ?>" 
                               placeholder="978-xxx-xxx">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Kategori</label>
                        <select name="kategori" class="form-select">
                            <option value="buku" <?= $bookData['kategori'] == 'buku' ? 'selected' : '' ?>>Buku</option>
                            <option value="jurnal" <?= $bookData['kategori'] == 'jurnal' ? 'selected' : '' ?>>Jurnal</option>
                            <option value="prosiding" <?= $bookData['kategori'] == 'prosiding' ? 'selected' : '' ?>>Prosiding</option>
                            <option value="skripsi" <?= $bookData['kategori'] == 'skripsi' ? 'selected' : '' ?>>Skripsi</option>
                            <option value="laporan_pkl" <?= $bookData['kategori'] == 'laporan_pkl' ? 'selected' : '' ?>>Laporan PKL</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Tahun Terbit</label>
                        <input type="number" name="tahun_terbit" class="form-control" 
                               value="<?= $bookData['tahun_terbit'] ?>" 
                               min="1900" max="2099" placeholder="2024">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Jumlah Halaman</label>
                        <input type="number" name="jumlah_halaman" class="form-control" 
                               value="<?= $bookData['jumlah_halaman'] ?>" 
                               min="1" placeholder="350">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Lokasi Rak</label>
                        <input type="text" name="lokasi_rak" class="form-control" 
                               value="<?= htmlspecialchars($bookData['lokasi_rak']) ?>" 
                               placeholder="Contoh: A1, B2, C3">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Keterangan</label>
                        <input type="text" name="keterangan" class="form-control" 
                               value="<?= htmlspecialchars($bookData['keterangan']) ?>" 
                               placeholder="Catatan tambahan">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-bold">
                            <i class="fas fa-image me-2 text-primary"></i>Cover Image
                        </label>
                        
                        <?php if ($bookData['cover_image']): ?>
                        <div class="mb-2">
                            <img src="../../<?= htmlspecialchars($bookData['cover_image']) ?>" 
                                 alt="Current Cover" 
                                 style="max-width: 150px; max-height: 200px; border-radius: 8px; border: 2px solid #dee2e6;">
                            <p class="text-muted small mt-1 mb-0">Cover saat ini</p>
                        </div>
                        <?php endif; ?>
                        
                        <input type="file" name="cover_image" id="input_cover" class="form-control" 
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="text-muted">Max: 2MB | Format: JPG, PNG, GIF, WEBP | Kosongkan jika tidak ingin mengubah</small>
                        
                        <div id="cover-preview" class="mt-3 d-none text-center">
                            <img id="cover-preview-img" src="" alt="Preview" 
                                 style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 2px solid #dee2e6; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-bold">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="4" 
                                  placeholder="Deskripsi singkat tentang buku..."><?= htmlspecialchars($bookData['deskripsi']) ?></textarea>
                    </div>
                    
                    <div class="col-12 text-end mt-3">
                        <button type="button" class="btn btn-primary px-4" onclick="switchToTab('author-tab-btn')">
                            Lanjut <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- TAB 2: PENULIS & PENERBIT -->
            <!-- ============================================ -->
            <div class="tab-pane fade p-4" id="author-panel">
                <!-- Hidden action inputs -->
                <input type="hidden" name="author_action" id="author_action" value="existing">
                <input type="hidden" name="publisher_action" id="publisher_action" value="existing">
                
                <div class="row g-4">
                    <!-- LEFT COLUMN: AUTHOR SECTION -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-primary-subtle border-0">
                                <h6 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-user-edit me-2"></i>Data Penulis
                                </h6>
                            </div>
                            <div class="card-body">
                                <!-- Author Dropdown -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Pilih Penulis</label>
                                    <select name="author_id" id="author_select" class="form-select">
                                        <option value="">-- Pilih Penulis --</option>
                                        <option value="new">+ Tambah Penulis Baru</option>
                                        <?php
                                        $authorsQuery = $conn->query("SELECT id, nama_pengarang FROM authors WHERE is_deleted = 0 ORDER BY nama_pengarang ASC");
                                        
                                        while ($author = $authorsQuery->fetch_assoc()) {
                                            $selected = ($currentAuthor && $currentAuthor['id'] == $author['id']) ? 'selected' : '';
                                            echo "<option value='{$author['id']}' $selected>" . htmlspecialchars($author['nama_pengarang']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- NEW AUTHOR FIELDS -->
                                <div id="new_author_fields" class="d-none">
                                    <div class="alert alert-info border-0 shadow-sm mb-3">
                                        <i class="fas fa-plus-circle me-2"></i>
                                        <strong>Mode Tambah Penulis Baru</strong>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">
                                            Nama Lengkap Penulis <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="new_author_name" id="new_author_name" class="form-control" 
                                               placeholder="Contoh: Prof. Dr. Ahmad Dahlan">
                                    </div>
                                </div>
                                
                                <!-- EXISTING AUTHOR FIELDS -->
                                <div id="existing_author_fields" class="<?= $currentAuthor ? '' : 'd-none' ?>">
                                    <div class="alert alert-success border-0 shadow-sm mb-3">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Penulis Terpilih: <?= $currentAuthor ? htmlspecialchars($currentAuthor['nama_pengarang']) : '' ?></strong>
                                    </div>
                                    
                                    <div id="author_loading" class="text-center py-3 d-none">
                                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                        <span class="text-muted">Memuat data penulis...</span>
                                    </div>
                                </div>
                                
                                <!-- BIOGRAFI FIELD -->
                                <div class="mb-0">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-book-reader me-2 text-info"></i>Biografi Penulis
                                    </label>
                                    <textarea name="author_biografi" id="author_biografi" class="form-control" rows="5" maxlength="200"
                                              placeholder="Tulis atau edit biografi penulis (maks. 200 karakter)"
                                              <?= !$currentAuthor ? 'disabled' : '' ?>><?= $currentAuthor ? htmlspecialchars($currentAuthor['biografi'] ?? '') : '' ?></textarea>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <small class="text-muted">
                                            <i class="fas fa-lightbulb me-1"></i>Opsional - dapat dikosongkan
                                        </small>
                                        <small class="fw-bold" id="char_count_author">
                                            <?= $currentAuthor ? mb_strlen($currentAuthor['biografi'] ?? '') : 0 ?>/200
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- RIGHT COLUMN: PUBLISHER SECTION -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-success-subtle border-0">
                                <h6 class="mb-0 fw-bold text-success">
                                    <i class="fas fa-building me-2"></i>Data Penerbit
                                </h6>
                            </div>
                            <div class="card-body">
                                <!-- Publisher Dropdown -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Pilih Penerbit</label>
                                    <select name="publisher_id" id="publisher_select" class="form-select">
                                        <option value="">-- Pilih Penerbit --</option>
                                        <option value="new">+ Tambah Penerbit Baru</option>
                                        <?php
                                        $publishersQuery = $conn->query("SELECT id, nama_penerbit FROM publishers WHERE is_deleted = 0 ORDER BY nama_penerbit ASC");
                                        $currentPublisher = $bookData['publisher_id'] ?? null;
                                        
                                        while ($publisher = $publishersQuery->fetch_assoc()) {
                                            $selected = ($currentPublisher == $publisher['id']) ? 'selected' : '';
                                            echo "<option value='{$publisher['id']}' $selected>" . htmlspecialchars($publisher['nama_penerbit']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- NEW PUBLISHER FIELDS -->
                                <div id="new_publisher_fields" class="d-none">
                                    <div class="alert alert-info border-0 shadow-sm mb-3">
                                        <i class="fas fa-plus-circle me-2"></i>
                                        <strong>Mode Tambah Penerbit Baru</strong>
                                    </div>
                                    
                                    <div class="mb-0">
                                        <label class="form-label fw-bold">
                                            Nama Penerbit <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="new_publisher_name" id="new_publisher_name" class="form-control" 
                                               placeholder="Contoh: PT. Gramedia Pustaka Utama">
                                    </div>
                                </div>
                                
                                <!-- EXISTING PUBLISHER FIELDS -->
                                <div id="existing_publisher_fields" class="<?= $currentPublisher ? '' : 'd-none' ?>">
                                    <div class="alert alert-success border-0 shadow-sm mb-3">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Detail Penerbit Saat Ini</strong>
                                    </div>
                                    
                                    <div id="publisher_loading" class="text-center py-3 d-none">
                                        <div class="spinner-border spinner-border-sm text-success me-2"></div>
                                        <span class="text-muted">Memuat data penerbit...</span>
                                    </div>
                                    
                                    <?php
                                    $pubDetails = null;
                                    if ($currentPublisher) {
                                        $pubQuery = $conn->query("SELECT * FROM publishers WHERE id = $currentPublisher LIMIT 1");
                                        $pubDetails = $pubQuery->fetch_assoc();
                                    }
                                    ?>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-map-marker-alt me-2 text-danger"></i>Alamat Kantor
                                        </label>
                                        <textarea name="publisher_alamat" id="publisher_alamat" class="form-control" rows="2"
                                                  placeholder="<?= $pubDetails ? htmlspecialchars($pubDetails['alamat'] ?: 'Tidak ada alamat') : 'Tambahkan alamat penerbit' ?>"><?= $pubDetails ? htmlspecialchars($pubDetails['alamat'] ?? '') : '' ?></textarea>
                                    </div>
                                    
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label fw-bold">
                                                <i class="fas fa-phone me-2 text-primary"></i>Telepon
                                            </label>
                                            <input type="tel" name="publisher_telepon" id="publisher_telepon" class="form-control" 
                                                   value="<?= $pubDetails ? htmlspecialchars($pubDetails['no_telepon'] ?? '') : '' ?>"
                                                   placeholder="021-xxxxx">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label fw-bold">
                                                <i class="fas fa-envelope me-2 text-warning"></i>Email
                                            </label>
                                            <input type="email" name="publisher_email" id="publisher_email" class="form-control" 
                                                   value="<?= $pubDetails ? htmlspecialchars($pubDetails['email'] ?? '') : '' ?>"
                                                   placeholder="info@penerbit.com">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation Buttons -->
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-outline-secondary px-4" onclick="switchToTab('asset-tab-btn')">
                        <i class="fas fa-arrow-left me-2"></i>Kembali ke Info Aset
                    </button>
                    <button type="button" class="btn btn-primary px-4" onclick="switchToTab('eksemplar-tab-btn')">
                        Lanjut ke Eksemplar <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- TAB 3: KELOLA EKSEMPLAR (ENHANCED) -->
            <!-- ============================================ -->
            <div class="tab-pane fade p-4" id="eksemplar-panel">
                <div class="alert alert-info border-0 shadow-sm mb-4">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-info-circle fa-2x me-3 mt-1"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Kelola Unit RFID</h6>
                            <p class="mb-0">Edit kondisi, hapus unit yang sudah ada, atau tambahkan unit RFID baru dengan scan.</p>
                        </div>
                    </div>
                </div>

                <!-- NEW: Tombol Tambah Eksemplar -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 fw-bold">
                        <i class="fas fa-tags me-2 text-primary"></i>Daftar Unit Eksemplar
                    </h6>
                    <button type="button" class="btn btn-success shadow-sm" onclick="openAddEksemplarModal()">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Eksemplar Baru
                    </button>
                </div>

                <?php if ($eksemplarQuery && $eksemplarQuery->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">
                                    <input type="checkbox" id="checkAll" class="form-check-input">
                                </th>
                                <th width="20%">Kode Eksemplar</th>
                                <th width="25%">UID RFID</th>
                                <th width="20%">Kondisi</th>
                                <th width="15%">Tgl Registrasi</th>
                                <th width="15%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="eksemplar-container">
                            <?php 
                            $index = 0;
                            while ($eks = $eksemplarQuery->fetch_assoc()): 
                            ?>
                            <tr id="row-eks-<?= $eks['id'] ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input check-item" value="<?= $eks['id'] ?>">
                                </td>
                                <td>
                                    <strong class="text-primary"><?= htmlspecialchars($eks['kode_eksemplar']) ?></strong>
                                </td>
                                <td>
                                    <code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($eks['uid']) ?></code>
                                </td>
                                <td>
                                    <select name="eksemplar[<?= $index ?>][kondisi]" class="form-select form-select-sm">
                                        <option value="baik" <?= $eks['kondisi'] == 'baik' ? 'selected' : '' ?>>✓ Baik</option>
                                        <option value="rusak_ringan" <?= $eks['kondisi'] == 'rusak_ringan' ? 'selected' : '' ?>>⚠ Rusak Ringan</option>
                                        <option value="rusak_berat" <?= $eks['kondisi'] == 'rusak_berat' ? 'selected' : '' ?>>✗ Rusak Berat</option>
                                        <option value="hilang" <?= $eks['kondisi'] == 'hilang' ? 'selected' : '' ?>>? Hilang</option>
                                    </select>
                                    <input type="hidden" name="eksemplar[<?= $index ?>][id]" value="<?= $eks['id'] ?>">
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('d M Y', strtotime($eks['tanggal_registrasi'])) ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteEksemplar(<?= $eks['id'] ?>)" title="Hapus Unit">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php 
                            $index++;
                            endwhile; 
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <button type="button" class="btn btn-danger" onclick="deleteSelected()">
                        <i class="fas fa-trash-alt me-2"></i>Hapus yang Dipilih
                    </button>
                    <span class="text-muted">
                        Total: <strong><?= $eksemplarQuery->num_rows ?></strong> unit
                    </span>
                </div>

                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-4x mb-3 d-block opacity-50"></i>
                    <p class="mb-0 fw-bold">Belum ada unit RFID terdaftar</p>
                    <small>Klik tombol "Tambah Eksemplar Baru" untuk memulai</small>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-outline-secondary px-4" onclick="switchToTab('author-tab-btn')">
                        <i class="fas fa-arrow-left me-2"></i>Kembali
                    </button>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="inventory.php" class="btn btn-light border px-4">
                <i class="fas fa-times me-2"></i>Batal
            </a>
            <button type="submit" id="btnSimpanEdit" class="btn btn-green px-4 shadow-sm">
                <i class="fas fa-save me-2"></i>Simpan Perubahan
            </button>
        </div>
    </form>
</div>

<!-- Hidden field for deleted eksemplar -->
<input type="hidden" id="deleted_eksemplar" name="delete_eksemplar" value="[]">

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/edit_inventory.js"></script>

<?php include_once '../includes/footer.php'; ?>