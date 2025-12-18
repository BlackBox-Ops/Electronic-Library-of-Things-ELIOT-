<?php
    // Path: web/apps/admin/inventory.php
    require_once '../../includes/config.php'; 

    if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
        header("Location: ../dashboard.php");
        exit;
    }

    // Logika statistik ringkas
    $statsQuery = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM books WHERE is_deleted = 0) as total_judul,
            (SELECT COUNT(*) FROM rt_book_uid WHERE is_deleted = 0) as total_unit_rfid,
            (SELECT SUM(jumlah_eksemplar) FROM books WHERE is_deleted = 0) as total_stok_seharusnya
    ");
    $stats = $statsQuery->fetch_assoc();

    // Query Utama
    $sql = "SELECT b.*, p.nama_penerbit,
            GROUP_CONCAT(DISTINCT a.nama_pengarang SEPARATOR ', ') as daftar_pengarang,
            (SELECT COUNT(*) FROM rt_book_uid rbu WHERE rbu.book_id = b.id AND rbu.is_deleted = 0) as unit_tertag
            FROM books b
            LEFT JOIN publishers p ON b.publisher_id = p.id
            LEFT JOIN rt_book_author rba ON b.id = rba.book_id
            LEFT JOIN authors a ON rba.author_id = a.id
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
            <h4 class="fw-bold mb-0 text-green-primary">Asset & Inventory Control</h4>
            <p class="text-secondary small">Registrasi aset dengan sistem scan RFID terpadu.</p>
        </div>
        <button class="btn btn-green shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahAset">
            <i class="fas fa-plus-circle me-2"></i>Registrasi Aset Baru
        </button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card p-3 border-0 shadow-sm">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-primary-subtle text-primary me-3 p-3 rounded"><i class="fas fa-book"></i></div>
                    <div>
                        <small class="text-muted d-block fw-bold">TOTAL JUDUL</small>
                        <h4 class="mb-0 fw-bold"><?= $stats['total_judul'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card p-3 border-0 shadow-sm">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-success-subtle text-success me-3 p-3 rounded"><i class="fas fa-tags"></i></div>
                    <div>
                        <small class="text-muted d-block fw-bold">RFID TERPASANG</small>
                        <h4 class="mb-0 fw-bold"><?= $stats['total_unit_rfid'] ?> / <?= $stats['total_stok_seharusnya'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Informasi Aset</th>
                        <th>Kategori</th>
                        <th>Stok (Tag/Total)</th>
                        <th class="text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr class="main-row" onclick="toggleRow('row-<?= $row['id'] ?>')">
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-chevron-right me-3 rotate-icon" id="icon-row-<?= $row['id'] ?>"></i>
                                <div>
                                    <div class="fw-bold text-primary-custom"><?= htmlspecialchars($row['judul_buku']) ?></div>
                                    <small class="text-muted"><?= $row['isbn'] ?> | <?= $row['daftar_pengarang'] ?: 'Anonim' ?></small>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge-category"><?= ucfirst($row['kategori']) ?></span></td>
                        <td><span class="fw-bold"><?= $row['unit_tertag'] ?> / <?= $row['jumlah_eksemplar'] ?></span></td>
                        <td class="text-center pe-4">
                            <button class="btn btn-mini btn-light border" onclick="event.stopPropagation()"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                    <tr id="row-<?= $row['id'] ?>" class="detail-row d-none">
                        <td colspan="4" class="p-0 border-0 bg-light-custom">
                            <div class="detail-bg-container shadow-sm">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Kode Unit</th>
                                            <th>RFID UID</th>
                                            <th>Kondisi</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $uSql = "SELECT r.*, u.uid FROM rt_book_uid r JOIN uid_buffer u ON r.uid_buffer_id = u.id WHERE r.book_id = {$row['id']}";
                                        $uRes = $conn->query($uSql);
                                        while($unit = $uRes->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $unit['kode_eksemplar'] ?></td>
                                            <td><code><?= $unit['uid'] ?></code></td>
                                            <td><?= ucfirst($unit['kondisi']) ?></td>
                                            <td><span class="badge bg-success">Tersedia</span></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahAset" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold small"><i class="fas fa-layer-group me-2"></i>Registrasi Aset Baru</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="formRegistrasiAset">
                <ul class="nav nav-tabs nav-fill px-3 border-bottom-0 bg-light" id="sapTab">
                    <li class="nav-item">
                        <button class="nav-link active py-2 small" id="asset-tab-btn" data-bs-toggle="tab" data-bs-target="#asset-panel" type="button">1. Info Aset</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-2 small" id="auth-tab-btn" data-bs-toggle="tab" data-bs-target="#auth-panel" type="button">2. Penulis/Penerbit</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link py-2 small" id="rfid-tab-btn" data-bs-toggle="tab" data-bs-target="#rfid-panel" type="button">3. Scan RFID</button>
                    </li>
                </ul>

                <div class="tab-content p-4">
                    <div class="tab-pane fade show active" id="asset-panel">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small">Judul Aset</label>
                                <input type="text" name="judul" id="input_judul" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">ISBN / Serial Number</label>
                                <input type="text" name="identifier" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Kategori</label>
                                <select name="kategori" class="form-select form-select-sm">
                                    <option value="buku">Buku</option>
                                    <option value="hardware">Hardware</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Jumlah Stok</label>
                                <input type="number" name="jumlah_eksemplar" id="input_stok" class="form-control form-control-sm" value="1" min="1">
                            </div>
                            <div class="col-12 text-end">
                                <button type="button" class="btn btn-primary btn-sm px-4" onclick="switchTab('auth-tab-btn')">Lanjut <i class="fas fa-chevron-right ms-1"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="auth-panel">
                        <div class="row g-3">
                            <div class="col-md-6 border-end">
                                <label class="form-label small fw-bold">Penulis</label>
                                <select name="author_id" id="author_select" class="form-select form-select-sm mb-2">
                                    <option value="new">+ Tambah Penulis Baru</option>
                                    <?php 
                                    $as = $conn->query("SELECT id, nama_pengarang FROM authors");
                                    while($a = $as->fetch_assoc()) echo "<option value='{$a['id']}'>{$a['nama_pengarang']}</option>";
                                    ?>
                                </select>
                                <div id="new_author_fields">
                                    <input type="text" name="new_author_name" class="form-control form-control-sm mb-2" placeholder="Nama Lengkap">
                                    <textarea name="author_biografi" class="form-control form-control-sm" rows="2" placeholder="Biografi singkat..."></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Penerbit</label>
                                <select name="publisher_id" id="publisher_select" class="form-select form-select-sm mb-2">
                                    <option value="new">+ Tambah Penerbit Baru</option>
                                    <?php 
                                    $ps = $conn->query("SELECT id, nama_penerbit FROM publishers");
                                    while($p = $ps->fetch_assoc()) echo "<option value='{$p['id']}'>{$p['nama_penerbit']}</option>";
                                    ?>
                                </select>
                                <div id="new_pub_fields">
                                    <input type="text" name="new_pub_name" class="form-control form-control-sm mb-2" placeholder="Nama Penerbit">
                                    <input type="text" name="pub_alamat" class="form-control form-control-sm" placeholder="Alamat Kantor">
                                </div>
                            </div>
                            <div class="col-12 d-flex justify-content-between mt-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="switchTab('asset-tab-btn')">Kembali</button>
                                <button type="button" class="btn btn-primary btn-sm px-4" onclick="switchTab('rfid-tab-btn')">Lanjut ke Scan</button>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="rfid-panel">
                        <div id="unit-rfid-container" style="max-height: 280px; overflow-y: auto;" class="pe-2">
                            </div>
                    </div>
                </div>

                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" id="btnSimpan" class="btn btn-sm btn-green px-4 shadow-sm" disabled>Simpan Aset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/inventory.js"></script>
<?php include_once '../includes/footer.php'; ?>