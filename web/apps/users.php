<?php
// Lokasi: /home/user/Documents/ELIOT/web/apps/users.php

require_once '../includes/config.php'; 
$pageTitle = 'Manajemen User';
include_once 'includes/header.php'; 

// --- LOGIKA FILTER, SEARCH & PAGINATION ---
$limit = 10; 
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$start = ($page > 1) ? ($page * $limit) - $limit : 0;

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
$search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';

// --- OPTIMASI QUERY (Hanya mengambil kolom yang dibutuhkan) ---
$columns = "id, nama, email, no_identitas, role, status";
$whereClause = "WHERE is_deleted = FALSE";

if ($tab == 'aktif') $whereClause .= " AND status = 'aktif'";
if ($tab == 'suspended') $whereClause .= " AND status = 'suspended'";
if ($tab == 'pending') $whereClause .= " AND status = 'nonaktif'";

if (!empty($search)) {
    $whereClause .= " AND (nama LIKE '%$search%' OR email LIKE '%$search%' OR no_identitas LIKE '%$search%')";
}

// 1. Query Statistik
$sqlStats = "SELECT 
    COUNT(id) as total,
    SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
    SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif
FROM users WHERE is_deleted = FALSE";
$stats = $conn->query($sqlStats)->fetch_assoc();

// 2. Query Data User (Menggunakan $columns untuk performa lebih cepat)
$sqlUsers = "SELECT $columns FROM users $whereClause ORDER BY created_at DESC LIMIT $start, $limit";
$resultUsers = $conn->query($sqlUsers);

// 3. Hitung Total untuk Pagination
$totalData = $conn->query("SELECT COUNT(id) as jml FROM users $whereClause")->fetch_assoc()['jml'];
$pages = ceil($totalData / $limit);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 text-primary fw-bold">Manajemen User</h4>
            <p class="text-muted small mb-0">Daftar hak akses sistem ELIOT</p>
        </div>
        <a href="dashboard.php" class="btn btn-primary btn-sm px-3 shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="row g-2 mb-3">
        <?php 
        $cards = [
            ['Total', $stats['total'], 'primary', 'users'],
            ['Aktif', $stats['aktif'], 'success', 'user-check'],
            ['Suspended', $stats['suspended'], 'danger', 'user-slash'],
            ['Pending', $stats['nonaktif'], 'warning', 'user-clock']
        ];
        foreach ($cards as $c): 
        ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted d-block" style="font-size: 0.75rem;"><?= $c[0] ?></small>
                        <h5 class="mb-0 fw-bold"><?= number_format($c[1] ?? 0) ?></h5>
                    </div>
                    <i class="fas fa-<?= $c[3] ?> text-<?= $c[2] ?> opacity-50"></i>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom py-2">
            <div class="row g-2 align-items-center">
                <div class="col-md-8">
                    <ul class="nav nav-pills nav-pills-sm" id="userTabs" style="font-size: 0.85rem;">
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 <?= $tab == 'all' ? 'active bg-primary' : 'text-primary' ?>" href="?tab=all&q=<?= $search ?>">Semua</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 <?= $tab == 'aktif' ? 'active bg-success' : 'text-success' ?>" href="?tab=aktif&q=<?= $search ?>">Aktif</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 <?= $tab == 'suspended' ? 'active bg-danger' : 'text-danger' ?>" href="?tab=suspended&q=<?= $search ?>">Suspended</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 <?= $tab == 'pending' ? 'active bg-warning text-dark' : 'text-warning' ?>" href="?tab=pending&q=<?= $search ?>">Pending</a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <form method="GET" class="input-group input-group-sm">
                        <input type="hidden" name="tab" value="<?= $tab ?>">
                        <input type="text" name="q" class="form-control" placeholder="Cari..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size: 0.9rem;">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-2">User</th>
                            <th>No. Identitas</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="text-center pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultUsers->num_rows > 0): ?>
                            <?php while($row = $resultUsers->fetch_assoc()): ?>
                            <tr class="align-middle">
                                <td class="ps-3">
                                    <div class="d-flex align-items-center py-1">
                                        <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center me-2" style="width:32px; height:32px;">
                                            <i class="fas fa-user text-primary" style="font-size: 0.75rem;"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-primary mb-0" style="line-height: 1.2;"><?= htmlspecialchars($row['nama']) ?></div>
                                            <small class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($row['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><small class="fw-medium"><?= htmlspecialchars($row['no_identitas']) ?></small></td>
                                <td>
                                    <?php 
                                        $role = $row['role'];
                                        $roleClass = ($role == 'admin') ? 'badge-role-admin' : (($role == 'staff') ? 'badge-role-staff' : 'badge-role-member');
                                    ?>
                                    <span class="badge <?= $roleClass ?>"><?= strtoupper($role) ?></span>
                                </td>
                                <td>
                                    <?php 
                                        $s = $row['status'];
                                        $cls = ($s == 'aktif') ? 'bg-success' : (($s == 'suspended') ? 'bg-danger' : 'bg-warning text-dark');
                                    ?>
                                    <span class="badge <?= $cls ?> shadow-none" style="font-size: 0.7rem;"><?= ucfirst($s) ?></span>
                                </td>
                                <td class="text-center pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-link text-primary p-1" title="Detail"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-link text-warning p-1" title="Ubah Status"><i class="fas fa-user-edit"></i></button>
                                        <button class="btn btn-link text-danger p-1" title="Hapus"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted small">Data tidak ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($pages > 1): ?>
        <div class="card-footer bg-transparent border-top py-2 text-center">
            <nav class="d-inline-block">
                <ul class="pagination pagination-sm mb-0">
                    <?php for($i=1; $i<=$pages; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?tab=<?= $tab ?>&q=<?= $search ?>&p=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* CSS Variabel untuk Role Badge */
    .badge-role-admin { background-color: #ff5f5f; color: #fff; } /* Merah terang */
    .badge-role-staff { background-color: #00d2d3; color: #000; font-weight: 600; } /* Cyan cerah */
    .badge-role-member { background-color: #feca57; color: #000; font-weight: 600; } /* Kuning cerah */

    /* Saat Dark Mode aktif, kita pertahankan warna yang sangat kontras */
    [data-theme="dark"] .badge-role-staff { background-color: #1dd1a1; color: #000; } /* Hijau mint cerah */
    [data-theme="dark"] .badge-role-member { background-color: #ff9f43; color: #000; } /* Orange cerah */

    .badge {
        padding: 0.4em 0.6em;
        font-size: 0.7rem;
        letter-spacing: 0.5px;
    }

    .nav-pills-sm .nav-link { border-radius: 20px; font-weight: 500; }
</style>

<?php include_once 'includes/footer.php'; ?>