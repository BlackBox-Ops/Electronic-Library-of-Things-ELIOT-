<?php
/**
 * EditInventoryController.php
 * Path: web/apps/controllers/EditInventoryController.php
 * 
 * Workflow:
 * - Update detail buku di tabel books
 * - Handle publisher baru jika dipilih "new"
 * - Handle pengarang baru jika dipilih "new" (dan relasi rt_book_author)
 * - Update kondisi setiap eksemplar di rt_book_uid
 * - Soft delete eksemplar jika ada di delete_eksemplar[]
 * - Hitung ulang jumlah_eksemplar dan eksemplar_tersedia di tabel books
 * - Handle upload cover image baru (jika ada)
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$book_id = intval($_POST['book_id'] ?? 0);
if ($book_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID buku tidak valid']);
    exit;
}

$conn->begin_transaction();

try {
    // ========================================
    // 1. PREPARE DATA BUKU
    // ========================================
    $judul_buku     = $conn->real_escape_string($_POST['judul_buku'] ?? '');
    $isbn           = $conn->real_escape_string($_POST['isbn'] ?? '');
    $tahun_terbit   = !empty($_POST['tahun_terbit']) ? intval($_POST['tahun_terbit']) : null;
    $jumlah_halaman = !empty($_POST['jumlah_halaman']) ? intval($_POST['jumlah_halaman']) : null;
    $lokasi_rak     = $conn->real_escape_string($_POST['lokasi_rak'] ?? '');
    $deskripsi      = $conn->real_escape_string($_POST['deskripsi'] ?? '');

    if (empty($judul_buku)) {
        throw new Exception("Judul buku wajib diisi");
    }

    // ========================================
    // 2. HANDLE PUBLISHER BARU
    // ========================================
    $publisher_id = null;
    if (!empty($_POST['publisher_id']) && $_POST['publisher_id'] !== 'new') {
        $publisher_id = intval($_POST['publisher_id']);
    } elseif ($_POST['publisher_id'] === 'new') {
        $new_pub_name = $conn->real_escape_string($_POST['new_publisher_name'] ?? '');
        if (empty($new_pub_name)) {
            throw new Exception("Nama publisher baru wajib diisi");
        }

        // Cek apakah sudah ada
        $checkPub = $conn->query("SELECT id FROM publishers WHERE nama_penerbit = '$new_pub_name' AND is_deleted = 0");
        if ($checkPub->num_rows > 0) {
            $publisher_id = $checkPub->fetch_assoc()['id'];
        } else {
            $pub_alamat = $conn->real_escape_string($_POST['publisher_alamat'] ?? '');
            $pub_telepon = $conn->real_escape_string($_POST['publisher_no_telepon'] ?? '');
            $pub_email = $conn->real_escape_string($_POST['publisher_email'] ?? '');

            $insertPub = "INSERT INTO publishers (nama_penerbit, alamat, no_telepon, email) 
                        VALUES ('$new_pub_name', '$pub_alamat', '$pub_telepon', '$pub_email')";
            if (!$conn->query($insertPub)) {
                throw new Exception("Gagal simpan publisher baru: " . $conn->error);
            }
            $publisher_id = $conn->insert_id;
        }
    }

    // ========================================
    // 3. HANDLE PENGARANG BARU & RELASI (WITH PERAN SUPPORT)
    // ========================================
    $author_id = null;
    $peran_author = $conn->real_escape_string($_POST['peran_author'] ?? 'penulis_utama');
    
    // Validasi peran
    $valid_peran = ['penulis_utama', 'co_author', 'editor', 'translator'];
    if (!in_array($peran_author, $valid_peran)) {
        $peran_author = 'penulis_utama'; // Default fallback
    }

    if (!empty($_POST['author_id']) && $_POST['author_id'] !== 'new') {
        $author_id = intval($_POST['author_id']);
    } elseif ($_POST['author_id'] === 'new') {
        $new_auth_name = $conn->real_escape_string(trim($_POST['new_author_name'] ?? ''));
        if (empty($new_auth_name)) {
            throw new Exception("Nama pengarang baru wajib diisi");
        }

        // Cek apakah sudah ada
        $checkAuth = $conn->query("SELECT id FROM authors WHERE nama_pengarang = '$new_auth_name' AND is_deleted = 0");
        if ($checkAuth->num_rows > 0) {
            $author_id = $checkAuth->fetch_assoc()['id'];
        } else {
            $auth_biografi = $conn->real_escape_string($_POST['author_biografi'] ?? '');
            if (mb_strlen($auth_biografi) > 200) {
                throw new Exception("Biografi pengarang maksimal 200 karakter");
            }

            $insertAuth = "INSERT INTO authors (nama_pengarang, biografi) 
                        VALUES ('$new_auth_name', '$auth_biografi')";
            if (!$conn->query($insertAuth)) {
                throw new Exception("Gagal simpan pengarang baru: " . $conn->error);
            }
            $author_id = $conn->insert_id;
        }
    }

    // Update relasi pengarang dengan PERAN
    if ($author_id) {
        // 1. Soft-delete semua relasi lama untuk buku ini
        $conn->query("UPDATE rt_book_author SET is_deleted = 1, updated_at = NOW() WHERE book_id = $book_id");

        // 2. Cek apakah relasi ini sudah pernah ada (meski di-soft-delete)
        $checkRel = $conn->query("SELECT id, is_deleted, peran FROM rt_book_author 
                                WHERE book_id = $book_id AND author_id = $author_id 
                                LIMIT 1");

        if ($checkRel->num_rows > 0) {
            $rel = $checkRel->fetch_assoc();
            // Update: Aktifkan kembali + update peran
            $updateRel = "UPDATE rt_book_author 
                            SET is_deleted = 0, peran = '$peran_author', updated_at = NOW() 
                            WHERE id = {$rel['id']}";
            if (!$conn->query($updateRel)) {
                throw new Exception("Gagal update relasi pengarang: " . $conn->error);
            }
            error_log("[AUTHOR RELATION UPDATED] Book: $book_id, Author: $author_id, Peran: $peran_author");
        } else {
            // Insert baru dengan peran
            $insertRel = "INSERT INTO rt_book_author (book_id, author_id, peran) 
                        VALUES ($book_id, $author_id, '$peran_author')";
            if (!$conn->query($insertRel)) {
                throw new Exception("Gagal insert relasi pengarang: " . $conn->error);
            }
            error_log("[AUTHOR RELATION CREATED] Book: $book_id, Author: $author_id, Peran: $peran_author");
        }
    } else {
        // Jika tidak ada author dipilih, soft-delete semua relasi
        $conn->query("UPDATE rt_book_author SET is_deleted = 1, updated_at = NOW() WHERE book_id = $book_id");
        error_log("[AUTHOR RELATION CLEARED] Book: $book_id - No author selected");
    }

    // ========================================
    // 4. HANDLE COVER IMAGE
    // ========================================
    $cover_image = null;
    $old_cover = $conn->query("SELECT cover_image FROM books WHERE id = $book_id")->fetch_assoc()['cover_image'];

    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['cover_image'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($file_ext, $allowed)) {
            throw new Exception("Format cover tidak valid");
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception("Ukuran cover maksimal 2MB");
        }

        $new_name = 'cover_' . $book_id . '_' . uniqid() . '.' . $file_ext;
        $upload_path = '../../uploads/covers/' . $new_name;
        if (!is_dir('../../uploads/covers/')) {
            mkdir('../../uploads/covers/', 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $cover_image = 'uploads/covers/' . $new_name;
            // Hapus cover lama
            if ($old_cover && file_exists('../../' . $old_cover)) {
                unlink('../../' . $old_cover);
            }
        } else {
            throw new Exception("Gagal upload cover");
        }
    } else {
        $cover_image = $old_cover; // Tetap pakai yang lama
    }

    // ========================================
    // 5. UPDATE TABEL BOOKS (DITAMBAH KETERANGAN)
    // ========================================
    $keterangan = $conn->real_escape_string($_POST['keterangan'] ?? '');

    $updateBook = "UPDATE books SET 
                    judul_buku = '$judul_buku',
                    isbn = '$isbn',
                    publisher_id = " . ($publisher_id ? $publisher_id : "NULL") . ",
                    tahun_terbit = " . ($tahun_terbit ? $tahun_terbit : "NULL") . ",
                    jumlah_halaman = " . ($jumlah_halaman ? $jumlah_halaman : "NULL") . ",
                    lokasi_rak = '$lokasi_rak',
                    deskripsi = '$deskripsi',
                    keterangan = " . ($keterangan ? "'$keterangan'" : "NULL") . ",
                    cover_image = " . ($cover_image ? "'$cover_image'" : "NULL") . ",
                    updated_at = NOW()
                WHERE id = $book_id";

    if (!$conn->query($updateBook)) {
        throw new Exception("Gagal update detail buku: " . $conn->error);
    }

    // ========================================
    // 6. UPDATE KONDISI EKSEMPLAR
    // ========================================
    $eksemplar_data = $_POST['eksemplar'] ?? [];
    $valid_kondisi = ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'];

    foreach ($eksemplar_data as $item) {
        $eks_id = intval($item['id'] ?? 0);
        $kondisi = $conn->real_escape_string($item['kondisi'] ?? 'baik');

        if ($eks_id <= 0 || !in_array($kondisi, $valid_kondisi)) {
            continue;
        }

        $updateEks = "UPDATE rt_book_uid SET kondisi = '$kondisi', updated_at = NOW() WHERE id = $eks_id AND book_id = $book_id";
        if (!$conn->query($updateEks)) {
            throw new Exception("Gagal update kondisi eksemplar ID $eks_id");
        }
    }

    // ========================================
    // 7. HAPUS EKSEMPLAR (SOFT DELETE)
    // ========================================
    $delete_eksemplar = $_POST['delete_eksemplar'] ?? [];
    foreach ($delete_eksemplar as $del_id) {
        $del_id = intval($del_id);
        if ($del_id > 0) {
            $softDelete = "UPDATE rt_book_uid SET is_deleted = 1, updated_at = NOW() WHERE id = $del_id AND book_id = $book_id";
            if (!$conn->query($softDelete)) {
                throw new Exception("Gagal hapus eksemplar ID $del_id");
            }
        }
    }

    // ========================================
    // 8. HITUNG ULANG JUMLAH EKSEMPLAR & TERSEDIA
    // ========================================
    $countQuery = $conn->query("SELECT COUNT(*) as total, 
                                SUM(CASE WHEN kondisi NOT IN ('rusak_berat', 'hilang') THEN 1 ELSE 0 END) as tersedia 
                                FROM rt_book_uid 
                                WHERE book_id = $book_id AND is_deleted = 0");
    $count = $countQuery->fetch_assoc();

    $updateCount = "UPDATE books SET 
                    jumlah_eksemplar = {$count['total']},
                    eksemplar_tersedia = {$count['tersedia']},
                    updated_at = NOW()
                    WHERE id = $book_id";
    if (!$conn->query($updateCount)) {
        throw new Exception("Gagal update jumlah eksemplar");
    }

    // ========================================
    // 9. COMMIT & SUCCESS
    // ========================================
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Data buku dan eksemplar berhasil diupdate!',
        'redirect' => 'inventory.php'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Gagal update: ' . $e->getMessage()
    ]);
}

$conn->close();
exit;
?>