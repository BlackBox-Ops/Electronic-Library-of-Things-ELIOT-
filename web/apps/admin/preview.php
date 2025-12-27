<?php
/**
 * Book Preview Page - Optimized Version
 * Path: web/apps/admin/preview.php
 * 
 * IMPROVEMENTS:
 * ✅ Fixed back button (always visible)
 * ✅ Compact header (100px)
 * ✅ Mini button to inventory.php with TOOLTIP
 * ✅ Pagination Tab 2: 5 items per page
 * ✅ Stay on active tab after pagination reload
 * ✅ Mobile warning for <768px
 */

require_once '../../includes/config.php';

// Security Check
if (!isset($_SESSION['userRole'])) {
    header("Location: /ELIOT/web/login.php");
    exit;
}

// Get book ID
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($book_id <= 0) {
    header("Location: inventory.php");
    exit;
}

// Query 1: Data Buku Lengkap dengan Penulis & Penerbit
$bookQuery = $conn->prepare("
    SELECT 
        b.*,
        p.nama_penerbit, 
        p.alamat as publisher_alamat, 
        p.no_telepon as publisher_phone, 
        p.email as publisher_email
    FROM books b
    LEFT JOIN publishers p ON b.publisher_id = p.id
    WHERE b.id = ? AND b.is_deleted = 0
");
$bookQuery->bind_param("i", $book_id);
$bookQuery->execute();
$book = $bookQuery->get_result()->fetch_assoc();

if (!$book) {
    header("Location: inventory.php");
    exit;
}

// Query 2: Multi-Author dengan Role
$authorsQuery = $conn->prepare("
    SELECT 
        a.nama_pengarang,
        a.biografi,
        rba.peran
    FROM rt_book_author rba
    JOIN authors a ON rba.author_id = a.id
    WHERE rba.book_id = ? AND rba.is_deleted = 0
    ORDER BY 
        CASE rba.peran
            WHEN 'penulis_utama' THEN 1
            WHEN 'co_author' THEN 2
            WHEN 'editor' THEN 3
            WHEN 'translator' THEN 4
        END
");
$authorsQuery->bind_param("i", $book_id);
$authorsQuery->execute();
$authors = $authorsQuery->get_result()->fetch_all(MYSQLI_ASSOC);

// Pagination for Tab 2: Eksemplar (5 items per page)
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Count total eksemplar
$totalQuery = $conn->prepare("
    SELECT COUNT(*) as total
    FROM rt_book_uid rbu
    WHERE rbu.book_id = ? AND rbu.is_deleted = 0
");
$totalQuery->bind_param("i", $book_id);
$totalQuery->execute();
$totalResult = $totalQuery->get_result()->fetch_assoc();
$totalEksemplar = $totalResult['total'];
$totalPages = ceil($totalEksemplar / $limit);

// Query 3: Eksemplar dengan pagination
$eksemplarQuery = $conn->prepare("
    SELECT 
        rbu.id,
        rbu.kode_eksemplar,
        ub.uid as uid_rfid,
        rbu.kondisi,
        rbu.tanggal_registrasi,
        COUNT(DISTINCT tp.id) as jumlah_dipinjam,
        CASE 
            WHEN tp.status = 'dipinjam' THEN 'Sedang Dipinjam'
            ELSE 'Tersedia'
        END as status_saat_ini,
        tp.due_date
    FROM rt_book_uid rbu
    LEFT JOIN uid_buffer ub ON rbu.uid_buffer_id = ub.id
    LEFT JOIN ts_peminjaman tp ON rbu.uid_buffer_id = tp.uid_buffer_id 
        AND tp.status = 'dipinjam'
        AND tp.is_deleted = 0
    WHERE rbu.book_id = ? AND rbu.is_deleted = 0
    GROUP BY rbu.id
    ORDER BY rbu.kode_eksemplar ASC
    LIMIT ? OFFSET ?
");
$eksemplarQuery->bind_param("iii", $book_id, $limit, $offset);
$eksemplarQuery->execute();
$eksemplars = $eksemplarQuery->get_result()->fetch_all(MYSQLI_ASSOC);

// Query 4: Statistik Peminjaman
$statsQuery = $conn->prepare("
    SELECT 
        COUNT(DISTINCT tp.id) as total_peminjaman,
        COUNT(DISTINCT CASE WHEN tp.status = 'dipinjam' THEN tp.id END) as sedang_dipinjam,
        COUNT(DISTINCT CASE WHEN tp.status = 'dikembalikan' THEN tp.id END) as sudah_dikembalikan,
        COALESCE(AVG(tpg.hari_telat), 0) as rata_rata_keterlambatan
    FROM ts_peminjaman tp
    LEFT JOIN ts_pengembalian tpg ON tp.id = tpg.peminjaman_id
    WHERE tp.book_id = ? AND tp.is_deleted = 0
");
$statsQuery->bind_param("i", $book_id);
$statsQuery->execute();
$stats = $statsQuery->get_result()->fetch_assoc();

// Query 5: Rating & Reviews
$ratingQuery = $conn->prepare("
    SELECT 
        COALESCE(ROUND(AVG(rating), 1), 0) as average_rating,
        COUNT(*) as total_reviews,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as count_5_star,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as count_4_star,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as count_3_star,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as count_2_star,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as count_1_star
    FROM book_ratings
    WHERE book_id = ? AND is_deleted = 0
");
$ratingQuery->bind_param("i", $book_id);
$ratingQuery->execute();
$rating = $ratingQuery->get_result()->fetch_assoc();

// Helper: Generate stars HTML
function renderStars($rating, $maxStars = 5) {
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = $maxStars - $fullStars - ($halfStar ? 1 : 0);
    
    $html = '';
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<i class="fas fa-star star filled"></i>';
    }
    if ($halfStar) {
        $html .= '<i class="fas fa-star-half-alt star filled"></i>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<i class="far fa-star star"></i>';
    }
    return $html;
}

// Helper: Role display
function displayRole($role) {
    $roles = [
        'penulis_utama' => 'Penulis Utama',
        'co_author' => 'Co-Author',
        'editor' => 'Editor',
        'translator' => 'Penerjemah'
    ];
    return $roles[$role] ?? $role;
}

include_once '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/preview.css">

<!-- FIXED BACK BUTTON BAR -->
<div class="fixed-back-bar">
    <a href="inventory.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Kembali ke Inventory
    </a>
</div>

<!-- COMPACT HEADER -->
<div class="preview-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-auto">
                <?php if (!empty($book['cover_image'])): ?>
                    <img src="<?= htmlspecialchars($book['cover_image']) ?>" alt="Cover" class="book-cover-img">
                <?php else: ?>
                    <div class="book-cover-placeholder">
                        <i class="fas fa-book"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col">
                <h1 class="book-title"><?= htmlspecialchars($book['judul_buku']) ?></h1>
                
                <div class="rating-display">
                    <div class="stars"><?= renderStars($rating['average_rating']) ?></div>
                    <span class="rating-number"><?= number_format($rating['average_rating'], 1) ?></span>
                    <span class="rating-count">(<?= $rating['total_reviews'] ?> review)</span>
                </div>

                <div class="book-meta">
                    <span><i class="fas fa-barcode"></i> ISBN: <?= htmlspecialchars($book['isbn']) ?></span>
                    <span><i class="fas fa-calendar"></i> <?= $book['tahun_terbit'] ?></span>
                    <span><i class="fas fa-file-alt"></i> <?= $book['jumlah_halaman'] ?> hal</span>
                    <span><i class="fas fa-map-marker-alt"></i> Rak <?= htmlspecialchars($book['lokasi_rak']) ?></span>
                </div>

                <div>
                    <span class="badge-category"><?= ucfirst($book['kategori']) ?></span>
                    <?php if (!empty($book['keterangan'])): ?>
                        <span class="book-keterangan ms-3">
                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($book['keterangan']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mini Button with Tooltip -->
            <div class="col-auto">
                <button 
                    class="inventory-btn" 
                    aria-label="Kembali ke Inventory"
                    data-bs-toggle="tooltip" 
                    data-bs-placement="left" 
                    title="Kembali ke Inventory"
                    onclick="window.location.href='inventory.php'">
                    <i class="fas fa-box"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- STICKY TAB NAVIGATION -->
<div class="nav-tabs-sticky-wrapper">
    <ul class="nav nav-tabs" id="previewTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                <i class="fas fa-info-circle"></i> Overview
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="eksemplar-tab" data-bs-toggle="tab" data-bs-target="#eksemplar" type="button" role="tab">
                <i class="fas fa-tags"></i> Eksemplar & RFID
                <span class="badge"><?= $totalEksemplar ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="statistik-tab" data-bs-toggle="tab" data-bs-target="#statistik" type="button" role="tab">
                <i class="fas fa-chart-bar"></i> Statistik
            </button>
        </li>
    </ul>
</div>

<!-- TAB CONTENT -->
<div class="preview-content">
    <div class="tab-content" id="previewTabContent">

        <!-- TAB 1: OVERVIEW -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel">
            <!-- Deskripsi Buku -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-align-left me-2"></i> Deskripsi Buku
                </div>
                <div class="card-body">
                    <p class="description-text">
                        <?= !empty($book['deskripsi']) 
                            ? nl2br(htmlspecialchars($book['deskripsi'])) 
                            : '<em class="text-muted">Tidak ada deskripsi tersedia.</em>' 
                        ?>
                    </p>
                </div>
            </div>

            <!-- Informasi Penulis -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-edit me-2"></i> Informasi Penulis
                </div>
                <div class="card-body">
                    <?php if (count($authors) > 0): ?>
                        <?php foreach ($authors as $author): ?>
                            <div class="author-inline">
                                <div class="flex-grow-1">
                                    <div class="author-name"><?= htmlspecialchars($author['nama_pengarang']) ?></div>
                                    <div class="author-role"><?= displayRole($author['peran']) ?></div>
                                    <?php if (!empty($author['biografi'])): ?>
                                        <div class="author-bio"><?= nl2br(htmlspecialchars($author['biografi'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-slash"></i>
                            <p>Tidak ada informasi penulis</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Informasi Penerbit -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-building me-2"></i> Informasi Penerbit
                </div>
                <div class="card-body">
                    <?php if (!empty($book['nama_penerbit'])): ?>
                        <div class="publisher-info">
                            <h6><?= htmlspecialchars($book['nama_penerbit']) ?></h6>
                            <?php if (!empty($book['publisher_alamat'])): ?>
                                <p><i class="fas fa-map-marker-alt"></i> <?= nl2br(htmlspecialchars($book['publisher_alamat'])) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($book['publisher_email'])): ?>
                                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($book['publisher_email']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($book['publisher_phone'])): ?>
                                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($book['publisher_phone']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-building"></i>
                            <p>Tidak ada informasi penerbit</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 2: EKSEMPLAR & RFID -->
        <div class="tab-pane fade" id="eksemplar" role="tabpanel">
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">Semua <span class="badge"><?= $totalEksemplar ?></span></button>
                <button class="filter-btn" data-filter="tersedia">Tersedia</button>
                <button class="filter-btn" data-filter="dipinjam">Sedang Dipinjam</button>
                <button class="filter-btn" data-filter="baik">Kondisi Baik</button>
                <button class="filter-btn" data-filter="rusak">Rusak</button>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="eksemplarTable">
                        <thead>
                            <tr>
                                <th>Kode Eksemplar</th>
                                <th>UID RFID</th>
                                <th>Kondisi</th>
                                <th>Status</th>
                                <th>Dipinjam (x)</th>
                                <th>Tgl Registrasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($eksemplars) > 0): ?>
                                <?php foreach ($eksemplars as $eks): ?>
                                    <?php
                                        $kondisiBadge = [
                                            'baik' => 'badge-success',
                                            'rusak_ringan' => 'badge-warning',
                                            'rusak_berat' => 'badge-danger',
                                            'hilang' => 'badge-secondary'
                                        ];
                                        $badgeClass = $kondisiBadge[$eks['kondisi']] ?? 'badge-secondary';
                                        $statusBadge = $eks['status_saat_ini'] == 'Tersedia' ? 'badge-success' : 'badge-warning';
                                        $statusKey = strtolower(str_replace(' ', '_', $eks['status_saat_ini']));
                                    ?>
                                    <tr data-kondisi="<?= $eks['kondisi'] ?>" data-status="<?= $statusKey ?>">
                                        <td><strong><?= htmlspecialchars($eks['kode_eksemplar']) ?></strong></td>
                                        <td><code><?= htmlspecialchars($eks['uid_rfid'] ?? '-') ?></code></td>
                                        <td><span class="badge <?= $badgeClass ?>"><?= ucwords(str_replace('_', ' ', $eks['kondisi'])) ?></span></td>
                                        <td><span class="badge <?= $statusBadge ?>"><?= $eks['status_saat_ini'] ?></span></td>
                                        <td><?= $eks['jumlah_dipinjam'] ?> kali</td>
                                        <td><?= date('d M Y', strtotime($eks['tanggal_registrasi'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p>Belum ada eksemplar terdaftar</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <a href="?id=<?= $book_id ?>&page=<?= max(1, $page - 1) ?>#eksemplar" 
                           class="pagination-btn <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <span class="pagination-info">Halaman <?= $page ?> dari <?= $totalPages ?></span>
                        <a href="?id=<?= $book_id ?>&page=<?= min($totalPages, $page + 1) ?>#eksemplar" 
                           class="pagination-btn <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 3: STATISTIK -->
        <div class="tab-pane fade" id="statistik" role="tabpanel">
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['total_peminjaman'] ?></div>
                        <div class="stat-label">Total Peminjaman</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['sedang_dipinjam'] ?></div>
                        <div class="stat-label">Sedang Dipinjam</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['sudah_dikembalikan'] ?></div>
                        <div class="stat-label">Sudah Dikembalikan</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($stats['rata_rata_keterlambatan'], 1) ?></div>
                        <div class="stat-label">Rata-rata Telat (hari)</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-star me-2"></i> Rating & Review
                </div>
                <div class="card-body">
                    <div class="row align-items-center mb-4">
                        <div class="col-md-4 text-center">
                            <div class="stat-number"><?= number_format($rating['average_rating'], 1) ?></div>
                            <div class="stars mb-2"><?= renderStars($rating['average_rating']) ?></div>
                            <div class="text-muted"><?= $rating['total_reviews'] ?> reviews</div>
                        </div>
                        <div class="col-md-8">
                            <div class="rating-breakdown">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <?php
                                        $count = $rating["count_{$i}_star"];
                                        $percentage = $rating['total_reviews'] > 0 
                                            ? ($count / $rating['total_reviews']) * 100 
                                            : 0;
                                    ?>
                                    <div class="rating-bar-row">
                                        <div class="rating-bar-label"><?= $i ?> <i class="fas fa-star"></i></div>
                                        <div class="rating-bar-container">
                                            <div class="rating-bar-fill" style="width: <?= $percentage ?>%"></div>
                                        </div>
                                        <div class="rating-bar-count"><?= $count ?></div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Mobile Warning -->
<div class="mobile-warning" role="alert">
    <i class="fas fa-exclamation-triangle"></i>
    <p>Silahkan buka di PC/Laptop atau Tab. Web ini tidak dioptimalkan untuk mobile.</p>
</div>

<script>
// Tooltip, Active Tab Persistence, Filter, Theme, Mobile Redirect
document.addEventListener('DOMContentLoaded', function () {
    // Tooltip Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) {
        return new bootstrap.Tooltip(el, { delay: { show: 100, hide: 100 }, trigger: 'hover focus' });
    });

    // Restore active tab after reload (pagination)
    const tabKey = 'previewActiveTab';
    const savedTab = localStorage.getItem(tabKey);
    if (savedTab) {
        const tabBtn = document.querySelector(`#${savedTab}-tab`);
        if (tabBtn) new bootstrap.Tab(tabBtn).show();
    }

    // Save active tab on change
    document.querySelectorAll('#previewTabs .nav-link').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function (e) {
            const id = e.target.id.replace('-tab', '');
            localStorage.setItem(tabKey, id);
        });
    });

    // Theme detection
    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // Client-side filter
    const filterBtns = document.querySelectorAll('.filter-btn');
    const rows = document.querySelectorAll('#eksemplarTable tbody tr');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filter = btn.dataset.filter;
            rows.forEach(row => {
                let show = true;
                if (filter === 'tersedia' && row.dataset.status !== 'tersedia') show = false;
                if (filter === 'dipinjam' && row.dataset.status !== 'sedang_dipinjam') show = false;
                if (filter === 'baik' && row.dataset.kondisi !== 'baik') show = false;
                if (filter === 'rusak' && !row.dataset.kondisi.includes('rusak')) show = false;
                row.style.display = show ? '' : 'none';
            });
        });
    });

    // Mobile redirect
    if (window.innerWidth < 900) {
        document.body.addEventListener('click', () => location.href = 'inventory.php');
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>