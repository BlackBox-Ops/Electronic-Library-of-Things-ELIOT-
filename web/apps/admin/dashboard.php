<?php
// ~/Documents/ELIOT/web/apps/admin/dashboard.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();

// Hanya admin yang bisa akses
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../user/dashboard.php');
    exit();
}

// Query untuk mendapatkan user yang perlu perhatian (pending, suspended)
$query = "SELECT * FROM users WHERE (status = 'pending' OR status = 'suspended') AND is_deleted = 0 ORDER BY 
          CASE status 
            WHEN 'pending' THEN 1 
            WHEN 'suspended' THEN 2 
            ELSE 3 
          END, created_at DESC";
$inactive_users = mysqli_query($conn, $query);

// Query untuk statistik - PERBAIKAN: 'aktif' bukan 'active'
$stats = [
    'total_users' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE is_deleted = 0"))['count'],
    'active_users' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE status = 'aktif' AND is_deleted = 0"))['count'],
    'pending_users' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE status = 'pending' AND is_deleted = 0"))['count'],
    'suspended_users' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE status = 'suspended' AND is_deleted = 0"))['count'],
    'total_books' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM books"))['count'],
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ELIOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/assets/css/style.css">
    <style>
        .stat-card {
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }
        .badge-suspended {
            background-color: #dc3545;
            color: #fff;
        }
        .badge-active {
            background-color: #28a745;
            color: #fff;
        }
        .badge-aktif {
            background-color: #28a745;
            color: #fff;
        }
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #628141;">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-crown me-2"></i> Admin Dashboard
            </a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-4">
                    <i class="fas fa-user me-1"></i> <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin') ?>
                </span>
                <a href="../../registrasi.php" class="btn btn-light me-2">
                    <i class="fas fa-user-plus me-1"></i> Buat User
                </a>
                <a href="../logout.php" class="btn btn-outline-light">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="card shadow mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-bars me-2"></i> Menu Admin</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="dashboard.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a href="../../registrasi.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-plus me-2"></i> Buat User Baru
                        </a>
                        <a href="activate_user.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-check me-2"></i> Kelola User
                            <?php if (($stats['pending_users'] + $stats['suspended_users']) > 0): ?>
                                <span class="badge bg-danger float-end"><?= $stats['pending_users'] + $stats['suspended_users'] ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-book me-2"></i> Kelola Buku
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-bar me-2"></i> Laporan
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-cog me-2"></i> Pengaturan
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <!-- Statistik Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card shadow border-primary">
                            <div class="card-body text-center">
                                <h1 class="text-primary"><?= $stats['total_users'] ?></h1>
                                <p class="text-muted">Total User</p>
                                <i class="fas fa-users fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card shadow border-success">
                            <div class="card-body text-center">
                                <h1 class="text-success"><?= $stats['active_users'] ?></h1>
                                <p class="text-muted">User Aktif</p>
                                <i class="fas fa-user-check fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card shadow border-warning">
                            <div class="card-body text-center">
                                <h1 class="text-warning"><?= $stats['pending_users'] ?></h1>
                                <p class="text-muted">Menunggu</p>
                                <i class="fas fa-user-clock fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card shadow border-danger">
                            <div class="card-body text-center">
                                <h1 class="text-danger"><?= $stats['suspended_users'] ?></h1>
                                <p class="text-muted">Ditangguhkan</p>
                                <i class="fas fa-user-slash fa-2x text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User yang Perlu Perhatian Section -->
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-user-cog me-2"></i> User yang Perlu Perhatian
                            <?php if (($stats['pending_users'] + $stats['suspended_users']) > 0): ?>
                                <span class="badge bg-danger"><?= $stats['pending_users'] + $stats['suspended_users'] ?> user</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($inactive_users) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Nama</th>
                                            <th>Email</th>
                                            <th>No. Identitas</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Tanggal Daftar</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; while($user = mysqli_fetch_assoc($inactive_users)): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td>
                                                <?= htmlspecialchars($user['nama']) ?>
                                                <?php if (!empty($user['no_identitas'])): ?>
                                                    <br><small class="text-muted">ID: <?= htmlspecialchars($user['no_identitas']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= !empty($user['no_identitas']) ? htmlspecialchars($user['no_identitas']) : '-' ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= ucfirst($user['role']) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($user['status'] == 'pending'): ?>
                                                    <span class="badge badge-pending">Pending</span>
                                                <?php elseif ($user['status'] == 'suspended'): ?>
                                                    <span class="badge badge-suspended">Suspended</span>
                                                <?php elseif ($user['status'] == 'aktif'): ?>
                                                    <span class="badge badge-aktif">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge badge-active"><?= ucfirst($user['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if ($user['status'] == 'pending'): ?>
                                                        <form action="activate_user.php" method="POST" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <button type="submit" name="activate" class="btn btn-sm btn-success">
                                                                <i class="fas fa-check me-1"></i> Aktifkan
                                                            </button>
                                                        </form>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="confirmReject(<?= $user['id'] ?>)">
                                                            <i class="fas fa-times me-1"></i> Tolak
                                                        </button>
                                                    <?php elseif ($user['status'] == 'suspended'): ?>
                                                        <form action="activate_user.php" method="POST" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <button type="submit" name="activate" class="btn btn-sm btn-success">
                                                                <i class="fas fa-play me-1"></i> Aktifkan
                                                            </button>
                                                        </form>
                                                        <form action="activate_user.php" method="POST" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                                    onclick="return confirm('Hapus user <?= htmlspecialchars(addslashes($user['nama'])) ?> secara permanen?')">
                                                                <i class="fas fa-trash me-1"></i> Hapus
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-check fa-4x text-success mb-3"></i>
                                <h5>Tidak ada user yang perlu perhatian</h5>
                                <p class="text-muted">Semua user sudah aktif</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card shadow mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i> Aksi Cepat</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="../../registrasi.php" class="btn btn-success w-100">
                                    <i class="fas fa-user-plus fa-2x mb-2"></i><br>
                                    Buat User Baru
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="activate_user.php" class="btn btn-warning w-100">
                                    <i class="fas fa-user-check fa-2x mb-2"></i><br>
                                    Kelola User
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="#" class="btn btn-primary w-100">
                                    <i class="fas fa-book fa-2x mb-2"></i><br>
                                    Tambah Buku
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="#" class="btn btn-secondary w-100">
                                    <i class="fas fa-chart-bar fa-2x mb-2"></i><br>
                                    Lihat Laporan
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript untuk konfirmasi -->
    <script>
        function confirmReject(userId) {
            if (confirm('Apakah Anda yakin ingin menolak user ini?')) {
                window.location.href = 'activate_user.php?reject=' + userId;
            }
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>