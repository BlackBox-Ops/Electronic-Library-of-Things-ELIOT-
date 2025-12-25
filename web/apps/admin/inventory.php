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

        <div class="col-md-4 text-end">
            <div class="d-inline-flex gap-2">
                <!-- Preference buttons removed (kept layout container for alignment) -->
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
                                <a href="preview.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-mini btn-outline-primary border" title="Preview">
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
                                            <th>Kode Unit</th>
                                            <th>RFID UID</th>
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

<!-- PAGINATION CONTROLS -->
<div class="d-flex justify-content-end mt-3">
    <nav aria-label="Inventory pagination">
        <ul class="pagination mb-0" id="inventoryPagination"></ul>
    </nav>
</div>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Custom JS -->
<script src="../assets/js/inventory.js"></script>

<!-- client-side filter (external) -->
<script src="../assets/js/filter_inventory.js"></script>

<!-- RESET UID SCRIPT -->
<script>
    document.addEventListener('DOMContentLoaded', function(){
        const btn = document.getElementById('btnResetUID');
        const statsEl = document.getElementById('stats_uid_available');
        if (!btn) return;

        btn.addEventListener('click', function(e){
            e.preventDefault();

            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            Swal.fire({
                title: 'Reset expired UID?',
                text: 'UID yang "expired" (pending > 5 menit) akan di-reset timestamp-nya. Lanjutkan?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Reset',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#dc3545',
                customClass: isDark ? { popup: 'swal-dark' } : {}
            }).then((result) => {
                if (!result.isConfirmed) return;

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Resetting...';

                fetch('../includes/api/reset_expired_uid.php?limit=50', {
                    method: 'GET',
                    cache: 'no-store'
                })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-undo"></i>';

                    if (data && data.success) {
                        // update stats if provided
                        if (statsEl && typeof data.summary !== 'undefined') {
                            // optimistic: subtract reset_count from displayed available if makes sense
                            // if API returns reset_count, decrement; otherwise attempt to use debug value
                            const resetCount = parseInt(data.reset_count || 0);
                            const current = parseInt(statsEl.textContent.replace(/,/g,'')) || 0;
                            statsEl.textContent = Intl.NumberFormat().format(Math.max(0, current - resetCount));
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Selesai',
                            text: data.message || 'UID expired berhasil di-reset',
                            confirmButtonColor: '#41644A',
                            customClass: isDark ? { popup: 'swal-dark' } : {}
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: data.message || 'Reset UID gagal',
                            confirmButtonColor: '#dc3545',
                            customClass: isDark ? { popup: 'swal-dark' } : {}
                        });
                    }
                })
                .catch(err => {
                    console.error('[RESET UID ERROR]', err);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-undo"></i>';
                    const isDarkInner = document.documentElement.getAttribute('data-theme') === 'dark';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi masalah saat menghubungi server',
                        confirmButtonColor: '#dc3545',
                        customClass: isDarkInner ? { popup: 'swal-dark' } : {}
                    });
                });
            });
        });
    });
</script>

<!-- PAGINATION SCRIPT (5 rows per page) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rowsPerPage = 5;
    const mainRows = Array.from(document.querySelectorAll('tr.main-row'));
    const paginationEl = document.getElementById('inventoryPagination');
    if (!paginationEl) return;

    const totalItems = mainRows.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / rowsPerPage));

    function showPage(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        mainRows.forEach((tr, idx) => {
            const detail = tr.nextElementSibling && tr.nextElementSibling.classList.contains('detail-row')
                ? tr.nextElementSibling
                : null;

            if (idx >= start && idx < end) {
                tr.classList.remove('d-none');
                // restore detail visibility only if previously expanded
                if (detail) {
                    if (detail.dataset.expanded === '1') detail.classList.remove('d-none'); else detail.classList.add('d-none');
                }
            } else {
                tr.classList.add('d-none');
                if (detail) detail.classList.add('d-none');
            }
        });

        // update active page button
        Array.from(paginationEl.querySelectorAll('li.page-item')).forEach(li => li.classList.remove('active'));
        const activeBtn = paginationEl.querySelector(`li[data-page="${page}"]`);
        if (activeBtn) activeBtn.classList.add('active');
    }

    function buildPagination() {
        paginationEl.innerHTML = '';

        // Prev
        const prevLi = document.createElement('li');
        prevLi.className = 'page-item';
        prevLi.innerHTML = `<a class="page-link" href="#" aria-label="Previous">&laquo;</a>`;
        prevLi.addEventListener('click', (e) => { e.preventDefault(); const cur = getCurrentPage(); if (cur > 1) goToPage(cur - 1); });
        paginationEl.appendChild(prevLi);

        // Pages
        for (let p = 1; p <= totalPages; p++) {
            const li = document.createElement('li');
            li.className = 'page-item';
            li.dataset.page = p;
            li.innerHTML = `<a class="page-link" href="#">${p}</a>`;
            li.addEventListener('click', function (e) {
                e.preventDefault();
                goToPage(p);
            });
            paginationEl.appendChild(li);
        }

        // Next
        const nextLi = document.createElement('li');
        nextLi.className = 'page-item';
        nextLi.innerHTML = `<a class="page-link" href="#" aria-label="Next">&raquo;</a>`;
        nextLi.addEventListener('click', (e) => { e.preventDefault(); const cur = getCurrentPage(); if (cur < totalPages) goToPage(cur + 1); });
        paginationEl.appendChild(nextLi);
    }

    function getCurrentPage() {
        const active = paginationEl.querySelector('li.page-item.active');
        return active ? parseInt(active.dataset.page) : 1;
    }

    function goToPage(p) {
        if (p < 1) p = 1;
        if (p > totalPages) p = totalPages;
        showPage(p);
    }

    // Expose toggleRow to keep expand/collapse behavior and mark detail state
    window.toggleRow = function(rowId) {
        const detail = document.getElementById(rowId);
        const icon = document.getElementById('icon-' + rowId.split('-').pop()) || null;
        if (!detail) return;
        const isHidden = detail.classList.contains('d-none');
        if (isHidden) {
            detail.classList.remove('d-none');
            detail.dataset.expanded = '1';
            if (icon) icon.classList.add('rotated');
        } else {
            detail.classList.add('d-none');
            detail.dataset.expanded = '0';
            if (icon) icon.classList.remove('rotated');
        }
    };

    // Initialize
    buildPagination();
    goToPage(1);
});
</script>

<?php include_once '../includes/footer.php'; ?>