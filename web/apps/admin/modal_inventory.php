<?php
/**
 * Full page replacement for modal registration (previously modal inside inventory.php)
 * Path: web/apps/admin/modal_inventory.php
 */

require_once '../../includes/config.php';

// Security: Check admin role
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
    include_once '../../404.php';
    exit;
}

include_once '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/inventory.css">

<div class="container-fluid py-4">
    <div class="card border-0 shadow-sm card-adaptive">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-layer-group me-2 text-success"></i>Registrasi Aset Baru</h5>

            <form id="formRegistrasiAset" enctype="multipart/form-data">
                <!-- TABS NAVIGATION -->
                <ul class="nav nav-tabs nav-fill border-bottom-0 bg-light-tab mb-3" id="sapTab">
                    <li class="nav-item">
                        <button class="nav-link active py-2 fw-bold" id="asset-tab-btn" data-bs-toggle="tab" data-bs-target="#asset-panel" type="button">
                            <i class="fas fa-info-circle me-2"></i>1. Info Aset
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-2 fw-bold" id="auth-tab-btn" data-bs-toggle="tab" data-bs-target="#auth-panel" type="button">
                            <i class="fas fa-user-edit me-2"></i>2. Penulis/Penerbit
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-2 fw-bold" id="rfid-tab-btn" data-bs-toggle="tab" data-bs-target="#rfid-panel" type="button">
                            <i class="fas fa-qrcode me-2"></i>3. Scan RFID
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- TAB 1: INFO ASET (keterangan opsional dihapus) -->
                    <div class="tab-pane fade show active p-3" id="asset-panel">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Judul Aset <span class="text-danger">*</span></label>
                                <input type="text" name="judul" id="input_judul" class="form-control" required placeholder="Masukkan judul buku/aset">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">ISBN / Serial <span class="text-danger">*</span></label>
                                <input type="text" name="identifier" class="form-control" required placeholder="978-xxx-xxx">
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
                                <label class="form-label fw-bold">Jumlah Stok <span class="text-danger">*</span></label>
                                <input type="number" name="jumlah_eksemplar" id="input_stok" class="form-control" value="1" min="1" required>
                                <small class="text-muted">Jumlah fisik buku yang akan di-scan</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold">Deskripsi</label>
                                <textarea name="deskripsi" class="form-control" rows="3" placeholder="Deskripsi singkat tentang buku ini..."></textarea>
                            </div>

                            <div class="col-12 text-end mt-2">
                                <button type="button" class="btn btn-primary px-4" onclick="document.getElementById('auth-tab-btn').click()">
                                    Lanjut <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: AUTHOR / PUBLISHER -->
                    <div class="tab-pane fade p-3" id="auth-panel">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100 bg-light-custom">
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

                                    <!-- existing author detail area (biografi akan di-load via JS like edit_inventory.js) -->
                                    <div id="existing_author_fields" class="p-3 border rounded bg-white shadow-sm d-none">
                                        <h6 class="fw-bold mb-2">Biografi Penulis</h6>
                                        <p id="author_biografi_display" class="small text-muted mb-0">-</p>
                                    </div>

                                    <!-- New Author -->
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

                                </div>
                            </div>

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

                                    <!-- existing publisher details (loaded via JS) -->
                                    <div id="existing_publisher_fields" class="p-3 border rounded bg-white shadow-sm d-none">
                                        <h6 class="fw-bold mb-2">Detail Penerbit</h6>
                                        <p id="publisher_detail_display" class="small text-muted mb-0">-</p>
                                    </div>

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

                        <div class="d-flex justify-content-between mt-3">
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('asset-tab-btn').click()">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </button>
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('rfid-tab-btn').click()">
                                Lanjut ke Scan <i class="fas fa-qrcode ms-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- TAB 3: SCAN RFID -->
                    <div class="tab-pane fade p-3" id="rfid-panel">
                        <div class="alert alert-info border-0 shadow-sm mb-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle fa-2x me-3 mt-1"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Panduan Scan RFID</h6>
                                    <p class="mb-0">Scan RFID untuk setiap unit fisik buku. Jumlah scan harus sesuai dengan jumlah stok yang diinput di Tab 1.</p>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mb-3">
                            <button type="button" id="btnScanTrigger" class="btn btn-primary btn-lg px-5 shadow" onclick="triggerScan()">
                                <i class="fas fa-qrcode me-2"></i>SCAN RFID BARU
                            </button>
                        </div>

                        <div id="unit-rfid-container" class="border rounded p-3 bg-light-custom" style="max-height:400px; overflow-y:auto;">
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-barcode fa-4x mb-3 d-block opacity-50"></i>
                                <p class="mb-0 fw-bold">Belum ada RFID yang di-scan</p>
                                <small>Klik tombol di atas untuk mulai scan</small>
                            </div>
                        </div>

                        <input type="hidden" name="rfid_units" id="input_rfid_units" value="[]">

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('auth-tab-btn').click()">
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

                <div class="mt-4 text-end">
                    <a href="inventory.php" class="btn btn-light border me-2"><i class="fas fa-times me-2"></i>Batal</a>
                    <button type="submit" id="btnSimpan" class="btn btn-green px-4 shadow-sm" disabled>
                        <i class="fas fa-save me-2"></i>Simpan Aset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SweetAlert2 and page scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/inventory.js"></script>

<?php include_once '../includes/footer.php'; ?>