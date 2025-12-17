<?php
    require_once '../../includes/config.php'; 

    if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
        header("Location: ../dashboard.php");
        exit;
    }

    // Logika Filter
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $category = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';

    $whereClause = "b.is_deleted = 0";
    if ($search) $whereClause .= " AND (b.judul_buku LIKE '%$search%' OR b.isbn LIKE '%$search%')";
    if ($category) $whereClause .= " AND b.kategori = '$category'";

    // 1. STATISTIK REAL-TIME (Optimized)
    $statsQuery = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM books WHERE is_deleted = 0) as total_judul,
            (SELECT COUNT(*) FROM rt_book_uid WHERE is_deleted = 0) as total_unit,
            (SELECT COUNT(*) FROM ts_peminjaman WHERE status = 'dipinjam' AND is_deleted = 0) as dipinjam,
            (SELECT COUNT(*) FROM rt_book_uid WHERE kondisi != 'baik' AND is_deleted = 0) as rusak
    ");
    $stats = $statsQuery->fetch_assoc();

    // 2. QUERY UTAMA DENGAN SUBQUERY UNTUK PERFORMA
    // Kita menghitung ketersediaan per buku secara efisien
    $sql = "SELECT b.*, p.nama_penerbit, 
            GROUP_CONCAT(DISTINCT a.nama_pengarang SEPARATOR ', ') as daftar_pengarang,
            (SELECT COUNT(*) FROM rt_book_uid rbu WHERE rbu.book_id = b.id AND rbu.is_deleted = 0) as total_fisik,
            (SELECT COUNT(*) FROM rt_book_uid rbu 
                LEFT JOIN ts_peminjaman tp ON rbu.uid_buffer_id = tp.uid_buffer_id AND tp.status = 'dipinjam'
                WHERE rbu.book_id = b.id AND tp.id IS NULL AND rbu.is_deleted = 0) as tersedia
            FROM books b
            LEFT JOIN publishers p ON b.publisher_id = p.id
            LEFT JOIN rt_book_author rba ON b.id = rba.book_id
            LEFT JOIN authors a ON rba.author_id = a.id
            WHERE $whereClause
            GROUP BY b.id
            ORDER BY b.created_at DESC";

    $result = $conn->query($sql);

    $pageTitle = 'Inventory Management';
    include_once '../includes/header.php'; 
?>

<link rel="stylesheet" href="../assets/css/inventory.css">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-green-primary">Inventaris & Asset Tracking</h4>
            <p class="text-secondary small mb-0">Monitor setiap unit unik berbasis RFID dan QR Code.</p>
        </div>
        <button class="btn btn-green btn-mini" data-bs-toggle="modal" data-bs-target="#modalTambahAset">
            <i class="fas fa-plus me-1"></i> Registrasi Aset
        </button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card p-3 shadow-sm border-0">
                <small class="text-muted fw-bold">TOTAL JUDUL</small>
                <h3 class="fw-bold mb-0"><?= number_format($stats['total_judul']) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 shadow-sm border-0">
                <small class="text-muted fw-bold">TOTAL UNIT (RFID/QR)</small>
                <h3 class="fw-bold mb-0 text-primary"><?= number_format($stats['total_unit']) ?></h3>
            </div>
        </div>
        </div>

    <div class="card p-3 shadow-sm mb-4 border-0">
        <form method="GET" class="row g-2">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select form-select-sm">
                    <option value="">Semua Kategori</option>
                    <option value="buku" <?= $category == 'buku' ? 'selected' : '' ?>>Buku</option>
                    <option value="laporan_pkl" <?= $category == 'laporan_pkl' ? 'selected' : '' ?>>IT Asset</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-green btn-mini w-100">Filter</button>
                <a href="inventory.php" class="btn btn-light btn-mini border">Reset</a>
            </div>
        </form>
    </div>

    <div class="card shadow-sm border-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Informasi Aset</th>
                        <th>Kategori</th>
                        <th>Ketersediaan (Unit)</th>
                        <th>Lokasi</th>
                        <th class="text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $bookId = $row['id'];
                            $available = $row['tersedia'];
                            $total = $row['total_fisik'];
                            $percent = ($total > 0) ? ($available / $total) * 100 : 0;
                        ?>
                        <tr class="main-row" onclick="toggleRow('row-<?= $bookId ?>')">
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-chevron-right me-3 rotate-icon" id="icon-row-<?= $bookId ?>"></i>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($row['judul_buku']) ?></div>
                                        <small class="text-muted">ISBN: <?= $row['isbn'] ?> | Oleh: <?= $row['daftar_pengarang'] ?: 'Anonim' ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border badge-soft text-capitalize"><?= $row['kategori'] ?></span></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="fw-bold me-2"><?= $available ?>/<?= $total ?></span>
                                    <div class="progress flex-grow-1" style="height: 6px; max-width: 70px;">
                                        <div class="progress-bar <?= ($percent < 30) ? 'bg-danger' : 'bg-success' ?>" style="width: <?= $percent ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td><small class="text-muted"><?= $row['lokasi_rak'] ?: '-' ?></small></td>
                            <td class="text-center pe-4">
                                <button class="btn btn-mini btn-light border text-primary" onclick="event.stopPropagation()"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <tr id="row-<?= $bookId ?>" class="detail-row d-none">
                            <td colspan="5" class="p-0 border-0">
                                <div class="detail-container p-3">
                                    <h6 class="small fw-bold text-success mb-3"><i class="fas fa-tag me-2"></i>Unit Tracking Detail</h6>
                                    <table class="table table-sm table-borderless mb-0 small">
                                        <thead>
                                            <tr class="text-muted border-bottom">
                                                <th>Kode Eksemplar</th>
                                                <th>RFID/QR UID</th>
                                                <th>Kondisi</th>
                                                <th>Status Saat Ini</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // Query unit unik untuk judul ini
                                            $unitSql = "SELECT rbu.*, ub.uid, 
                                                    (SELECT status FROM ts_peminjaman WHERE uid_buffer_id = rbu.uid_buffer_id AND status = 'dipinjam' LIMIT 1) as loan_status
                                                    FROM rt_book_uid rbu 
                                                    JOIN uid_buffer ub ON rbu.uid_buffer_id = ub.id 
                                                    WHERE rbu.book_id = $bookId AND rbu.is_deleted = 0";
                                            $units = $conn->query($unitSql);
                                            while($u = $units->fetch_assoc()):
                                            ?>
                                            <tr>
                                                <td><?= $u['kode_eksemplar'] ?></td>
                                                <td><code><?= $u['uid'] ?></code></td>
                                                <td><?= ($u['kondisi'] == 'baik') ? '<span class="text-success">Baik</span>' : '<span class="text-danger">'.$u['kondisi'].'</span>' ?></td>
                                                <td>
                                                    <?php if($u['loan_status']): ?>
                                                        <span class="badge bg-warning text-dark badge-soft">Dipinjam</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success text-white badge-soft">Tersedia di Rak</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5">Data tidak ditemukan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../assets/js/inventory.js"></script>
<?php include_once '../includes/footer.php'; ?>