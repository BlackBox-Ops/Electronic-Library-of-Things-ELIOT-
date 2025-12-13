<?php
// ~/Documents/ELIOT/web/apps/admin/activate_user.php

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();

// Hanya admin yang bisa akses
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../user/dashboard.php');
    exit();
}

// Proses aktivasi user - PERBAIKAN: 'aktif' bukan 'active'
if (isset($_POST['activate']) && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    
    $query = "UPDATE users SET status = 'aktif', updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "User berhasil diaktifkan!";
    } else {
        // Debug: tampilkan error MySQL
        $_SESSION['error'] = "Gagal mengaktifkan user! Error: " . mysqli_error($conn);
    }
    
    header('Location: dashboard.php');
    exit();
}

// Proses penolakan user
if (isset($_GET['reject'])) {
    $user_id = intval($_GET['reject']);
    
    // Soft delete untuk user pending
    $query = "UPDATE users SET is_deleted = 1, updated_at = NOW() WHERE id = ? AND status = 'pending'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "User berhasil ditolak!";
    } else {
        $_SESSION['error'] = "Gagal menolak user! Error: " . mysqli_error($conn);
    }
    
    header('Location: dashboard.php');
    exit();
}

// Proses hapus user suspended
if (isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    
    // Soft delete untuk user suspended
    $query = "UPDATE users SET is_deleted = 1, updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "User berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus user! Error: " . mysqli_error($conn);
    }
    
    header('Location: dashboard.php');
    exit();
}

// Proses suspend user
if (isset($_POST['suspend']) && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');
    
    $query = "UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "User berhasil ditangguhkan!";
    } else {
        $_SESSION['error'] = "Gagal menangguhkan user! Error: " . mysqli_error($conn);
    }
    
    header('Location: manage_users.php');
    exit();
}

// Ambil data user yang perlu perhatian (pending, suspended)
$query = "SELECT * FROM users WHERE (status = 'pending' OR status = 'suspended') AND is_deleted = 0 ORDER BY 
          CASE status 
            WHEN 'pending' THEN 1 
            WHEN 'suspended' THEN 2 
            ELSE 3 
          END, created_at DESC";
$inactive_users = mysqli_query($conn, $query);
$total_inactive = mysqli_num_rows($inactive_users);

// Ambil semua user untuk management
$all_users_query = "SELECT * FROM users WHERE is_deleted = 0 ORDER BY status, role, nama";
$all_users = mysqli_query($conn, $all_users_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Admin ELIOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/assets/css/style.css">
    <style>
        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }
        .badge-suspended {
            background-color: #dc3545;
            color: #fff;
        }
        .badge-aktif {
            background-color: #28a745;
            color: #fff;
        }
        .tab-content {
            border: 1px solid #dee2e6;
            border-top: none;
            padding: 20px;
            border-radius: 0 0 5px 5px;
        }
        .nav-tabs .nav-link.active {
            background-color: #fff;
            border-bottom-color: #fff;
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
                <a href="dashboard.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
                <a href="../logout.php" class="btn btn-outline-light">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-user-cog me-2"></i> Kelola User
                </h4>
            </div>
            
            <!-- Tabs -->
            <ul class="nav nav-tabs" id="userTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                        Menunggu Aktivasi
                        <?php if ($total_inactive > 0): ?>
                            <span class="badge bg-danger"><?= $total_inactive ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                        Semua User
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="userTabContent">
                <!-- Tab 1: User Pending/Suspended -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                    <?php if ($total_inactive > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Nama Lengkap</th>
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
                                            <span class="badge bg-info"><?= ucfirst($user['role']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($user['status'] == 'pending'): ?>
                                                <span class="badge badge-pending">Pending</span>
                                            <?php elseif ($user['status'] == 'suspended'): ?>
                                                <span class="badge badge-suspended">Suspended</span>
                                            <?php elseif ($user['status'] == 'aktif'): ?>
                                                <span class="badge badge-aktif">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-info"><?= ucfirst($user['status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <?php if ($user['status'] == 'pending'): ?>
                                                    <form action="" method="POST" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" name="activate" 
                                                                class="btn btn-success"
                                                                onclick="return confirm('Aktifkan user <?= htmlspecialchars(addslashes($user['nama'])) ?>?')">
                                                            <i class="fas fa-check me-1"></i> Aktifkan
                                                        </button>
                                                    </form>
                                                    <a href="?reject=<?= $user['id'] ?>" 
                                                       class="btn btn-danger"
                                                       onclick="return confirm('Tolak user <?= htmlspecialchars(addslashes($user['nama'])) ?>?')">
                                                        <i class="fas fa-times me-1"></i> Tolak
                                                    </a>
                                                <?php elseif ($user['status'] == 'suspended'): ?>
                                                    <form action="" method="POST" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" name="activate" 
                                                                class="btn btn-success"
                                                                onclick="return confirm('Aktifkan user <?= htmlspecialchars(addslashes($user['nama'])) ?>?')">
                                                            <i class="fas fa-play me-1"></i> Aktifkan
                                                        </button>
                                                    </form>
                                                    <form action="" method="POST" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" 
                                                                class="btn btn-danger"
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
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Informasi:</strong> 
                            <ul class="mb-0">
                                <li>User dengan status <span class="badge badge-pending">Pending</span> adalah user baru yang perlu persetujuan</li>
                                <li>User dengan status <span class="badge badge-suspended">Suspended</span> adalah user yang ditangguhkan</li>
                                <li>User yang ditolak/dihapus akan di-<em>soft delete</em> (tidak benar-benar dihapus dari database)</li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-check fa-5x text-success mb-4"></i>
                            <h3>Tidak ada user yang perlu perhatian</h3>
                            <p class="text-muted">Semua user sudah aktif</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tab 2: Semua User -->
                <div class="tab-pane fade" id="all" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Nama Lengkap</th>
                                    <th>Email</th>
                                    <th>No. Identitas</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Terdaftar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1; 
                                mysqli_data_seek($all_users, 0); // Reset pointer
                                while($user = mysqli_fetch_assoc($all_users)): 
                                ?>
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
                                        <?php if ($user['status'] == 'aktif'): ?>
                                            <span class="badge badge-aktif">Aktif</span>
                                        <?php elseif ($user['status'] == 'pending'): ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php elseif ($user['status'] == 'suspended'): ?>
                                            <span class="badge badge-suspended">Suspended</span>
                                        <?php else: ?>
                                            <span class="badge bg-info"><?= ucfirst($user['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($user['status'] == 'aktif'): ?>
                                                <button type="button" class="btn btn-warning" 
                                                        onclick="suspendUser(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['nama'])) ?>')">
                                                    <i class="fas fa-pause me-1"></i> Suspend
                                                </button>
                                            <?php elseif ($user['status'] == 'pending'): ?>
                                                <form action="" method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="activate" class="btn btn-success">
                                                        <i class="fas fa-check me-1"></i> Aktifkan
                                                    </button>
                                                </form>
                                            <?php elseif ($user['status'] == 'suspended'): ?>
                                                <form action="" method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="activate" class="btn btn-success">
                                                        <i class="fas fa-play me-1"></i> Aktifkan
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="user_detail.php?id=<?= $user['id'] ?>" class="btn btn-info">
                                                <i class="fas fa-eye me-1"></i> Detail
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal untuk suspend user -->
        <div class="modal fade" id="suspendModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Suspend User</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p id="suspendUserName"></p>
                            <input type="hidden" name="user_id" id="suspendUserId">
                            <div class="mb-3">
                                <label for="reason" class="form-label">Alasan Penangguhan</label>
                                <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="suspend" class="btn btn-warning">Suspend User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function suspendUser(userId, userName) {
            document.getElementById('suspendUserId').value = userId;
            document.getElementById('suspendUserName').innerHTML = 
                'Anda akan menangguhkan user: <strong>' + userName + '</strong>. Masukkan alasan:';
            
            var modal = new bootstrap.Modal(document.getElementById('suspendModal'));
            modal.show();
        }
        
        // Aktifkan tab pertama jika ada user pending
        <?php if ($total_inactive > 0): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var tab = new bootstrap.Tab(document.querySelector('#pending-tab'));
            tab.show();
        });
        <?php endif; ?>
    </script>
</body>
</html>