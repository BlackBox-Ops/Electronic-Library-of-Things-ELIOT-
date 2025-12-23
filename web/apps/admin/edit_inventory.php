<?php
/**
 * Edit Inventory Page - Enhanced Version
 * Path: web/apps/admin/edit_inventory.php
 * 
 * Workflow:
 * - Edit detail buku master (judul, ISBN, publisher, pengarang, dll)
 * - Support tambah publisher & pengarang baru langsung dari form edit
 * - Tampilkan dan edit kondisi setiap eksemplar RFID
 * - Hapus eksemplar dengan JS aman (DOM manipulation)
 * - Tambah eksemplar baru via scan RFID langsung dari halaman ini
 */

require_once '../../includes/config.php';

// Security: Check admin role
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
    include_once '../../404.php';
    exit;
}

// Ambil book_id
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($book_id <= 0) {
    header("Location: inventory.php");
    exit;
}

// Fetch data buku + publisher
$bookStmt = $conn->prepare("SELECT b.*, p.nama_penerbit 
                            FROM books b 
                            LEFT JOIN publishers p ON b.publisher_id = p.id 
                            WHERE b.id = ? AND b.is_deleted = 0");
$bookStmt->bind_param("i", $book_id);
$bookStmt->execute();
$bookResult = $bookStmt->get_result();
$book = $bookResult->fetch_assoc();

if (!$book) {
    header("Location: inventory.php");
    exit;
}

// Fetch pengarang yang terkait dengan buku ini
$currentAuthors = [];
$authorStmt = $conn->prepare("SELECT a.id, a.nama_pengarang 
                              FROM authors a
                              JOIN rt_book_author rba ON a.id = rba.author_id
                              WHERE rba.book_id = ? AND rba.is_deleted = 0");
$authorStmt->bind_param("i", $book_id);
$authorStmt->execute();
$authorResult = $authorStmt->get_result();
while ($auth = $authorResult->fetch_assoc()) {
    $currentAuthors[] = $auth['id'];
}

// Fetch eksemplar
$eksemplarStmt = $conn->prepare("SELECT rbu.id, rbu.kode_eksemplar, rbu.kondisi, ub.uid 
                                 FROM rt_book_uid rbu
                                 LEFT JOIN uid_buffer ub ON rbu.uid_buffer_id = ub.id
                                 WHERE rbu.book_id = ? AND rbu.is_deleted = 0
                                 ORDER BY rbu.kode_eksemplar");
$eksemplarStmt->bind_param("i", $book_id);
$eksemplarStmt->execute();
$eksemplarResult = $eksemplarStmt->get_result();

include_once '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/inventory.css">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-green-primary"><i class="fas fa-edit me-2"></i>Edit Buku: <?= htmlspecialchars($book['judul_buku']) ?></h4>
            <p class="text-secondary small mb-0">Update detail buku dan kondisi eksemplar berdasarkan tag RFID.</p>
        </div>
        <a href="inventory.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <!-- Form Edit -->
    <form method="POST" action="../controllers/EditInventoryController.php" enctype="multipart/form-data">
        <input type="hidden" name="book_id" value="<?= $book_id ?>">

        <!-- Detail Buku -->
        <div class="card border-0 shadow-sm card-adaptive mb-4">
            <div class="card-body p-4">
                <h5 class="mb-4">Detail Buku</h5>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Judul Buku</label>
                        <input type="text" name="judul_buku" class="form-control" value="<?= htmlspecialchars($book['judul_buku']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">ISBN</label>
                        <input type="text" name="isbn" class="form-control" value="<?= htmlspecialchars($book['isbn']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Publisher</label>
                        <select name="publisher_id" id="publisher_select" class="form-select">
                            <option value="">-- Pilih Publisher --</option>
                            <option value="new">+ Tambah Publisher Baru</option>
                            <?php
                            $pubQuery = $conn->query("SELECT id, nama_penerbit FROM publishers WHERE is_deleted = 0 ORDER BY nama_penerbit");
                            while ($pub = $pubQuery->fetch_assoc()) {
                                $selected = ($pub['id'] == $book['publisher_id']) ? 'selected' : '';
                                echo "<option value='{$pub['id']}' $selected>" . htmlspecialchars($pub['nama_penerbit']) . "</option>";
                            }
                            ?>
                        </select>
                        <!-- Field Publisher Baru -->
                        <div id="new_publisher_fields" class="d-none mt-3 p-3 border rounded bg-light-custom">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nama Publisher Baru</label>
                                <input type="text" name="new_publisher_name" class="form-control">
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Alamat</label>
                                    <input type="text" name="publisher_alamat" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">No Telepon</label>
                                    <input type="text" name="publisher_no_telepon" class="form-control">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="publisher_email" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Pengarang</label>
                        <select name="author_id" id="author_select" class="form-select">
                            <option value="">-- Pilih Pengarang --</option>
                            <option value="new">+ Tambah Pengarang Baru</option>
                            <?php
                            $authQuery = $conn->query("SELECT id, nama_pengarang FROM authors WHERE is_deleted = 0 ORDER BY nama_pengarang");
                            while ($auth = $authQuery->fetch_assoc()) {
                                $selected = in_array($auth['id'], $currentAuthors) ? 'selected' : '';
                                echo "<option value='{$auth['id']}' $selected>" . htmlspecialchars($auth['nama_pengarang']) . "</option>";
                            }
                            ?>
                        </select>
                        <!-- Field Pengarang Baru -->
                        <div id="new_author_fields" class="d-none mt-3 p-3 border rounded bg-light-custom">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nama Pengarang Baru</label>
                                <input type="text" name="new_author_name" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Biografi (Maks 200 karakter)</label>
                                <textarea name="author_biografi" class="form-control" rows="3" maxlength="200"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tahun Terbit</label>
                        <input type="number" name="tahun_terbit" class="form-control" value="<?= $book['tahun_terbit'] ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Jumlah Halaman</label>
                        <input type="number" name="jumlah_halaman" class="form-control" value="<?= $book['jumlah_halaman'] ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Lokasi Rak</label>
                        <input type="text" name="lokasi_rak" class="form-control" value="<?= htmlspecialchars($book['lokasi_rak']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="4"><?= htmlspecialchars($book['deskripsi'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Cover Image (Opsional)</label>
                        <input type="file" name="cover_image" class="form-control" accept="image/*">
                        <?php if ($book['cover_image']): ?>
                            <div class="mt-2">
                                <img src="../../<?= htmlspecialchars($book['cover_image']) ?>" alt="Cover" class="img-thumbnail" style="max-height: 150px;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daftar Eksemplar -->
        <div class="card border-0 shadow-sm card-adaptive">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">Eksemplar RFID (<?= $eksemplarResult->num_rows ?> unit)</h5>
                    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahEksemplarBaru">
                        <i class="fas fa-plus me-2"></i>Tambah Eksemplar Baru
                    </button>
                </div>

                <?php if ($eksemplarResult->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode Eksemplar</th>
                                    <th>UID RFID</th>
                                    <th>Kondisi Saat Ini</th>
                                    <th>Ubah Kondisi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $index = 0; while ($eks = $eksemplarResult->fetch_assoc()): ?>
                                    <tr data-eks-id="<?= $eks['id'] ?>">
                                        <td><strong><?= htmlspecialchars($eks['kode_eksemplar']) ?></strong></td>
                                        <td><code><?= htmlspecialchars($eks['uid']) ?></code></td>
                                        <td>
                                            <span class="badge bg-<?= $eks['kondisi'] == 'baik' ? 'success' : ($eks['kondisi'] == 'rusak_ringan' ? 'warning' : 'danger') ?>">
                                                <?= ucwords(str_replace('_', ' ', $eks['kondisi'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <select name="eksemplar[<?= $index ?>][kondisi]" class="form-select form-select-sm">
                                                <option value="baik" <?= $eks['kondisi'] == 'baik' ? 'selected' : '' ?>>Baik</option>
                                                <option value="rusak_ringan" <?= $eks['kondisi'] == 'rusak_ringan' ? 'selected' : '' ?>>Rusak Ringan</option>
                                                <option value="rusak_berat" <?= $eks['kondisi'] == 'rusak_berat' ? 'selected' : '' ?>>Rusak Berat</option>
                                                <option value="hilang" <?= $eks['kondisi'] == 'hilang' ? 'selected' : '' ?>>Hilang</option>
                                            </select>
                                            <input type="hidden" name="eksemplar[<?= $index ?>][id]" value="<?= $eks['id'] ?>">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger btn-delete-eksemplar" data-id="<?= $eks['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php $index++; endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Belum ada eksemplar RFID terdaftar.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Submit -->
        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-green px-5 shadow-sm">
                <i class="fas fa-save me-2"></i>Update Aset
            </button>
        </div>
    </form>
</div>

<!-- Modal Tambah Eksemplar Baru -->
<div class="modal fade" id="modalTambahEksemplarBaru" tabindex="-1" aria-labelledby="modalTambahEksemplarLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTambahEksemplarLabel"><i class="fas fa-plus-circle me-2"></i>Tambah Eksemplar Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Scan RFID fisik baru untuk menambah eksemplar ke buku ini. Kode eksemplar akan digenerate otomatis (lanjutan dari yang terakhir).
                </div>

                <div class="text-center my-4">
                    <button type="button" id="btnScanEksemplarBaru" class="btn btn-primary btn-lg px-5 shadow">
                        <i class="fas fa-qrcode me-2"></i>SCAN RFID BARU
                    </button>
                </div>

                <div id="containerScanBaru" class="border rounded p-3 bg-light-custom" style="min-height: 200px;">
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-barcode fa-4x mb-3 opacity-50"></i>
                        <p class="fw-bold mb-0">Belum ada RFID di-scan</p>
                        <small>Klik tombol SCAN untuk memulai</small>
                    </div>
                </div>

                <input type="hidden" id="inputNewEksemplar" name="new_eksemplar_data" value="[]">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Batal
                </button>
                <button type="button" id="btnSimpanEksemplarBaru" class="btn btn-green px-4 shadow-sm" disabled>
                    <i class="fas fa-save me-2"></i>Simpan Eksemplar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- JavaScript untuk Semua Fitur -->
<script>
// Toggle Publisher & Author Baru
document.getElementById('publisher_select').addEventListener('change', function() {
    document.getElementById('new_publisher_fields').classList.toggle('d-none', this.value !== 'new');
});

document.getElementById('author_select').addEventListener('change', function() {
    document.getElementById('new_author_fields').classList.toggle('d-none', this.value !== 'new');
});

// Hapus Eksemplar dengan JS Aman
document.querySelectorAll('.btn-delete-eksemplar').forEach(button => {
    button.addEventListener('click', function() {
        if (confirm('Yakin ingin menghapus eksemplar ini? Data akan hilang permanen.')) {
            const row = this.closest('tr');
            const eksemplarId = this.getAttribute('data-id');

            row.remove();

            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'delete_eksemplar[]';
            hiddenInput.value = eksemplarId;
            document.querySelector('form').appendChild(hiddenInput);
        }
    });
});

// Variabel untuk eksemplar baru
let newEksemplarList = [];
let isScanningNew = false;

function triggerScanEksemplarBaru() {
    if (isScanningNew) return;

    isScanningNew = true;
    const btn = document.getElementById('btnScanEksemplarBaru');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Scanning...';

    fetch('../includes/api/check_latest_uid.php?limit=10&_=' + Date.now())
        .then(res => res.json())
        .then(data => {
            if (data.success && data.uids.length > 0) {
                const prefix = '<?= substr(strtoupper(preg_replace("/[^A-Z0-9]/", "", $book['judul_buku'])), 0, 3) ?>';
                fetch(`../includes/api/get_next_code.php?prefix=${prefix}&count=${data.uids.length}&book_id=<?= $book_id ?>&_=` + Date.now())
                    .then(codeRes => codeRes.json())
                    .then(codeData => {
                        if (codeData.success) {
                            data.uids.forEach((uidItem, i) => {
                                newEksemplarList.push({
                                    uid_buffer_id: uidItem.id,
                                    kode_eksemplar: codeData.next_codes[i],
                                    kondisi: 'baik'
                                });
                            });

                            renderNewEksemplarList();
                            document.getElementById('inputNewEksemplar').value = JSON.stringify(newEksemplarList);
                            document.getElementById('btnSimpanEksemplarBaru').disabled = false;

                            Swal.fire('Berhasil!', `${data.uids.length} RFID baru di-scan`, 'success');
                        } else {
                            Swal.fire('Error', codeData.message, 'error');
                        }
                    });
            } else {
                Swal.fire('Gagal', data.message || 'Tidak ada UID baru ditemukan', 'warning');
            }
        })
        .catch(err => {
            Swal.fire('Error', 'Gagal menghubungi server scan', 'error');
        })
        .finally(() => {
            isScanningNew = false;
            btn.disabled = false;
            btn.innerHTML = original;
        });
}

function renderNewEksemplarList() {
    const container = document.getElementById('containerScanBaru');
    if (newEksemplarList.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-barcode fa-4x mb-3 opacity-50"></i><p class="fw-bold mb-0">Belum ada RFID di-scan</p></div>';
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>No</th><th>Kode</th><th>UID</th><th>Kondisi</th><th>Aksi</th></tr></thead><tbody>';
    newEksemplarList.forEach((item, idx) => {
        html += `<tr>
                    <td>${idx + 1}</td>
                    <td><strong>${item.kode_eksemplar}</strong></td>
                    <td><code>${item.uid_buffer_id}</code></td>
                    <td>
                        <select class="form-select form-select-sm" onchange="newEksemplarList[${idx}].kondisi = this.value">
                            <option value="baik" selected>Baik</option>
                            <option value="rusak_ringan">Rusak Ringan</option>
                            <option value="rusak_berat">Rusak Berat</option>
                            <option value="hilang">Hilang</option>
                        </select>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger" onclick="newEksemplarList.splice(${idx}, 1); renderNewEksemplarList(); updateSimpanButton();">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function updateSimpanButton() {
    document.getElementById('btnSimpanEksemplarBaru').disabled = newEksemplarList.length === 0;
}

// Simpan ke form utama
document.getElementById('btnSimpanEksemplarBaru').addEventListener('click', function() {
    if (newEksemplarList.length === 0) return;

    document.getElementById('inputNewEksemplar').value = JSON.stringify(newEksemplarList);
    bootstrap.Modal.getInstance(document.getElementById('modalTambahEksemplarBaru')).hide();
    Swal.fire('Siap!', `${newEksemplarList.length} eksemplar baru siap disimpan`, 'success');
});

// Submit form utama dengan AJAX + SweetAlert
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: data.message || 'Data berhasil diupdate!',
                confirmButtonColor: '#41644A'
            }).then(() => {
                window.location.href = data.redirect || 'inventory.php';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: data.message || 'Terjadi kesalahan',
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Gagal terhubung ke server',
            confirmButtonColor: '#dc3545'
        });
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>