<?php
// Path: /web/apps/admin/set_permissions.php
require_once '../../includes/config.php'; 

// Proteksi: Cek Admin
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

// Ambil parameter role dari URL (admin/staff/member)
$roleToEdit = isset($_GET['role']) ? $_GET['role'] : '';

// Validasi role (pastikan sesuai ENUM di database)
$allowedRoles = ['admin', 'staff', 'member'];
if (!in_array($roleToEdit, $allowedRoles)) {
    die("Role tidak valid.");
}

$pageTitle = 'Atur Izin: ' . ucfirst($roleToEdit);
include_once '../includes/header.php'; 

// Query untuk mengambil semua halaman dan status izin untuk role ini
// Kita gunakan LEFT JOIN agar semua halaman di tabel 'pages' tetap muncul
$sql = "SELECT p.id as page_id, p.page_name, p.category, p.icon, 
               rp.can_access, rp.access_level 
        FROM pages p
        LEFT JOIN role_permissions rp ON p.id = rp.page_id AND rp.role = '$roleToEdit'
        ORDER BY p.category, p.page_name";

$result = $conn->query($sql);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-capitalize">Hak Akses: <?= $roleToEdit ?></h4>
            <p class="text-muted small">Tentukan halaman mana saja yang bisa dibuka dan level operasinya.</p>
        </div>
        <a href="roles.php" class="btn btn-outline-secondary btn-sm px-3 shadow-sm">
            <i class="fas fa-chevron-left me-1"></i> Kembali
        </a>
    </div>

    <form action="save_permissions.php" method="POST">
        <input type="hidden" name="role" value="<?= $roleToEdit ?>">
        
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3" style="width: 30%;">Halaman / Fitur</th>
                            <th>Kategori</th>
                            <th class="text-center">Akses</th>
                            <th class="text-center">Level Izin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="icon-box me-3 text-primary">
                                        <i class="<?= $row['icon'] ?>"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?= $row['page_name'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= $row['category'] ?></span>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input permission-switch" type="checkbox" 
                                           name="access[<?= $row['page_id'] ?>]" 
                                           <?= ($row['can_access'] == 1) ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm access-level-group" role="group">
                                    <input type="radio" class="btn-check" name="level[<?= $row['page_id'] ?>]" 
                                           id="read_<?= $row['page_id'] ?>" value="read"
                                           <?= ($row['access_level'] == 'read' || empty($row['access_level'])) ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-info" for="read_<?= $row['page_id'] ?>">Read</label>

                                    <input type="radio" class="btn-check" name="level[<?= $row['page_id'] ?>]" 
                                           id="write_<?= $row['page_id'] ?>" value="write"
                                           <?= ($row['access_level'] == 'write') ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-warning" for="write_<?= $row['page_id'] ?>">Write</label>

                                    <input type="radio" class="btn-check" name="level[<?= $row['page_id'] ?>]" 
                                           id="full_<?= $row['page_id'] ?>" value="full"
                                           <?= ($row['access_level'] == 'full') ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-danger" for="full_<?= $row['page_id'] ?>">Full</label>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white py-3 text-end">
                <button type="submit" class="btn btn-primary px-4 shadow-sm">
                    <i class="fas fa-save me-1"></i> Simpan Hak Akses
                </button>
            </div>
        </div>
    </form>
</div>

<style>
.icon-box {
    width: 35px;
    height: 35px;
    background: #f8f9fa;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
/* Menandai baris yang aksesnya dimatikan agar lebih jelas */
tr.disabled-row { opacity: 0.5; background-color: #fcfcfc; }
</style>

<script>
// Script sederhana untuk visual: Disable radio button jika switch OFF
document.querySelectorAll('.permission-switch').forEach(sw => {
    sw.addEventListener('change', function() {
        const row = this.closest('tr');
        const radios = row.querySelectorAll('input[type="radio"]');
        if(!this.checked) {
            row.classList.add('disabled-row');
            radios.forEach(r => r.disabled = true);
        } else {
            row.classList.remove('disabled-row');
            radios.forEach(r => r.disabled = false);
        }
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>