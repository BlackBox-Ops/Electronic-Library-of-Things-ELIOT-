<?php
// apps/admin/activate_users.php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Hanya admin yang bisa akses
requireRole(['admin']);

// Proses aktivasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'] ?? 'activate';
    
    if ($action === 'activate') {
        $stmt = $conn->prepare("UPDATE users SET status = 'aktif', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'User berhasil diaktifkan!'];
        } else {
            $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Gagal mengaktifkan user: ' . $stmt->error];
        }
        $stmt->close();
        
    } elseif ($action === 'deactivate') {
        $stmt = $conn->prepare("UPDATE users SET status = 'nonaktif', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['alert'] = ['type' => 'success', 'message' => 'User berhasil dinonaktifkan!'];
        } else {
            $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Gagal menonaktifkan user: ' . $stmt->error];
        }
        $stmt->close();
        
    } elseif ($action === 'suspend') {
        $stmt = $conn->prepare("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['alert'] = ['type' => 'warning', 'message' => 'User berhasil disuspend!'];
        } else {
            $_SESSION['alert'] = ['type' => 'danger', 'message' => 'Gagal mensuspend user: ' . $stmt->error];
        }
        $stmt->close();
    }
    
    header('Location: activate_users.php');
    exit;
}

// Ambil data user yang suspended
$stmt = $conn->prepare("SELECT id, nama, email, role, status, created_at FROM users 
                       WHERE status IN ('suspended', 'nonaktif') 
                       AND is_deleted = 0 
                       ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktivasi User - ELIOT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h3 mb-0">Aktivasi User</h2>
                    <a href="../dashboard.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
                
                <?php showAlert(); ?>
                
                <?php if (empty($users)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Tidak ada user yang menunggu aktivasi.
                </div>
                <?php else: ?>
                
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">User Menunggu Aktivasi</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Tanggal Daftar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $index => $user): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($user['nama']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($user['status'] === 'suspended') {
                                                echo '<span class="badge bg-warning text-dark">Menunggu Aktivasi</span>';
                                            } elseif ($user['status'] === 'nonaktif') {
                                                echo '<span class="badge bg-danger">Nonaktif</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">' . $user['status'] . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <?php if ($user['status'] === 'suspended'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn btn-success btn-sm" 
                                                        onclick="return confirm('Aktifkan user ini?')">
                                                    <i class="fas fa-check"></i> Aktifkan
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['status'] === 'aktif'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="action" value="suspend">
                                                <button type="submit" class="btn btn-warning btn-sm" 
                                                        onclick="return confirm('Suspend user ini?')">
                                                    <i class="fas fa-pause"></i> Suspend
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <button type="submit" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Nonaktifkan user ini?')">
                                                    <i class="fas fa-ban"></i> Nonaktifkan
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>