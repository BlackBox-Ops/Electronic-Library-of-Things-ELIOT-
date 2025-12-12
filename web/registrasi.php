<?php
// ~/Documents/ELIOT/web/registrasi.php

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

requireRole(['admin']); // Hanya admin akses halaman ini

ob_start();

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $no_identitas = trim($_POST['no_identitas'] ?? '');
    $no_telepon = trim($_POST['no_telepon'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = trim($_POST['role'] ?? 'member'); // Default member, admin pilih

    if (empty($nama) || empty($email) || empty($no_identitas) || empty($no_telepon) || empty($password) || empty($confirm_password) || empty($role)) {
        $error_message = 'Semua kolom wajib diisi.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Konfirmasi password tidak cocok.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Email tidak valid.';
    } else {
        // Cek duplikat email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $error_message = 'Email sudah terdaftar.';
        } else {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Proses foto (opsional)
            $foto_path = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $max_size = 2 * 1024 * 1024;
                if ($_FILES['foto']['size'] > $max_size) {
                    $error_message = 'Ukuran foto maksimal 2MB.';
                } else {
                    $target_dir = 'public/assets/img/profiles/';
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }
                    $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                    $file_name = uniqid('profile_') . '.' . $file_ext;
                    $target_file = $target_dir . $file_name;
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                        $foto_path = $target_file;
                    } else {
                        $error_message = 'Gagal upload foto.';
                    }
                }
            }

            if (empty($error_message)) {
                // Insert ke users
                $stmt = $conn->prepare("INSERT INTO users (nama, email, no_identitas, no_telepon, password_hash, role, status, created_at, updated_at, is_deleted) VALUES (?, ?, ?, ?, ?, ?, 'aktif', NOW(), NOW(), 0)");
                $stmt->bind_param("ssssss", $nama, $email, $no_identitas, $no_telepon, $password_hash, $role);
                if ($stmt->execute()) {
                    $success_message = 'User baru berhasil dibuat!';
                    // Opsional: Insert RT_USER_UID jika ada UID
                    // Misal: $uid = trim($_POST['uid'] ?? '');
                    // Jika ada, insert ke RT_USER_UID
                } else {
                    $error_message = 'Gagal menyimpan data.';
                }
            }
        }
    }
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi User Baru - ELIOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="public/assets/css/style.css"> <!-- CSS global -->
</head>
<body>
    <div class="main-wrapper">
        <div class="container login-card-container">
            <div class="row g-0 card-shadow overflow-hidden rounded-4">
                <!-- Panel Kiri -->
                <div class="col-md-6 d-none d-md-flex align-items-center justify-content-center left-panel position-relative">
                    <div class="overlay-content text-center text-white p-5">
                        <h1 class="display-4 fw-bold mb-3">ELIOT</h1>
                        <p class="lead mb-4">Electronic Library of Things</p>
                        <p class="mb-4 small opacity-75">Buat akun user baru</p>
                    </div>
                </div>

                <!-- Panel Kanan - Form Registrasi -->
                <div class="col-md-6 bg-white p-5 d-flex flex-column justify-content-center position-relative">
                    <button class="btn btn-link theme-toggle-btn position-absolute top-0 end-0 m-3" id="themeToggle">
                        <i class="fas fa-moon fs-5"></i>
                    </button>

                    <div class="form-content">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-dark">Registrasi User Baru</h2>
                            <p class="text-secondary small">Hanya untuk admin</p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger d-flex align-items-center mb-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <div><?= htmlspecialchars($error_message) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success d-flex align-items-center mb-4">
                                <i class="fas fa-check-circle me-2"></i>
                                <div><?= htmlspecialchars($success_message) ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Nama Lengkap</label>
                                <input type="text" name="nama" class="form-control bg-light" placeholder="Masukkan nama" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Email</label>
                                <input type="email" name="email" class="form-control bg-light" placeholder="Masukkan email" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">No Identitas (NIM/NIP)</label>
                                <input type="text" name="no_identitas" class="form-control bg-light" placeholder="Masukkan no identitas" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">No Telepon</label>
                                <input type="tel" name="no_telepon" class="form-control bg-light" placeholder="Masukkan no telepon" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Password</label>
                                <div class="input-group">
                                    <input type="password" name="password" class="form-control bg-light border-end-0" id="password" placeholder="Masukkan password" required>
                                    <button class="btn btn-light border border-start-0 text-muted" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Konfirmasi Password</label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" class="form-control bg-light border-end-0" id="confirm_password" placeholder="Ulangi password" required>
                                    <button class="btn btn-light border border-start-0 text-muted" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Role</label>
                                <select name="role" class="form-control bg-light" required>
                                    <option value="member">Member</option>
                                    <option value="staff">Staff</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Foto (Opsional, Max 2MB)</label>
                                <input type="file" name="foto" class="form-control bg-light" accept="image/*">
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-custom-green text-white py-2 rounded-3 fw-bold btn-login">
                                    Buat Akun
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/assets/js/registrasi.js"></script>
</body>
</html>