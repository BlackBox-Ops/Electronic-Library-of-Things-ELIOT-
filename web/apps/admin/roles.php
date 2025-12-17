<?php
// Path: /web/apps/admin/roles.php

// Config ada di web/includes/config.php
// Dari web/apps/admin/ naik 2 tingkat: admin -> apps -> web, lalu masuk includes
require_once '../../includes/config.php'; 

// Proteksi: Jika bukan admin, tendang ke dashboard
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$pageTitle = 'Manajemen Role';
// Header ada di web/apps/includes/header.php
// Dari web/apps/admin/ naik 1 tingkat ke apps, lalu masuk includes
include_once '../includes/header.php'; 

// Optimasi Query: Ambil kolom yang dibutuhkan saja
$sqlRoles = "SELECT role, COUNT(id) as total FROM users WHERE is_deleted = FALSE GROUP BY role";
$resultRoles = $conn->query($sqlRoles);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">Hak Akses Sistem</h4>
            <p class="text-muted small">Kelola izin halaman berdasarkan role pengguna.</p>
        </div>
        <a href="<?= $baseUrl ?>/apps/dashboard.php" class="btn btn-primary btn-sm px-3 shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">Role</th>
                            <th>Total User</th>
                            <th class="text-center pe-4">Konfigurasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultRoles && $resultRoles->num_rows > 0): ?>
                            <?php while($row = $resultRoles->fetch_assoc()): ?>
                            <tr class="align-middle">
                                <td class="ps-4">
                                    <?php 
                                        $role = $row['role'];
                                        $cls = ($role == 'admin') ? 'badge-admin' : (($role == 'staff') ? 'badge-staff' : 'badge-member');
                                    ?>
                                    <span class="badge <?= $cls ?> text-uppercase"><?= $role ?></span>
                                </td>
                                <td><strong><?= $row['total'] ?></strong> Pengguna</td>
                                <td class="text-center pe-4">
                                    <a href="set_permissions.php?role=<?= $row['role'] ?>" class="btn btn-outline-primary btn-sm py-0" style="font-size: 0.75rem;">
                                        <i class="fas fa-cog me-1"></i> Atur Izin
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                        <tr class="empty-state">
                            <td colspan="3">
                                <div class="empty-state-icon">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <div class="empty-state-text">Tidak ada data role</div>
                                <div class="empty-state-subtext">Belum ada role untuk ditampilkan</div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* Warna cerah agar kontras di Dark Mode */
.badge-admin { background-color: #ff4d4d; color: white; }
.badge-staff { background-color: #00d2d3; color: black; font-weight: 600; }
.badge-member { background-color: #feca57; color: black; font-weight: 600; }

[data-theme="dark"] .badge-staff { background-color: #1dd1a1; }
[data-theme="dark"] .badge-member { background-color: #ff9f43; }

.badge { padding: 0.5em 0.8em; font-size: 0.7rem; border-radius: 4px; }
</style>

<?php include_once '../includes/footer.php'; ?>