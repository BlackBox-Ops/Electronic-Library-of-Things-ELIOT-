<?php
/**
 * Inventory Controller - Enhanced & Fixed Version
 * Path: web/apps/controllers/InventoryController.php
 * 
 * HANDLES:
 * - UC-1: Publisher dengan 4 fields lengkap
 * - UC-2: Author dengan biografi (max 200 karakter)
 * - UC-4: Auto-generate kode eksemplar unik
 * - UC-5: Input kondisi buku per eksemplar
 * - UC-6: Multi-author dengan role (conditional)
 * - Fixed: Truncated code diperbaiki lengkap
 */

require_once '../../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Gunakan POST.'
    ]);
    exit;
}

// ========================================
// 1. CAPTURE & VALIDATE BASIC DATA
// ========================================
$judul         = $conn->real_escape_string($_POST['judul'] ?? '');
$identifier    = $conn->real_escape_string($_POST['identifier'] ?? ''); 
$kategori      = $conn->real_escape_string($_POST['kategori'] ?? 'buku');
$lokasi        = $conn->real_escape_string($_POST['lokasi'] ?? '');
$jumlah_eksemplar = intval($_POST['jumlah_eksemplar'] ?? 1);
$keterangan = $conn->real_escape_string($_POST['keterangan'] ?? '');

if (empty($judul) || empty($identifier)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Judul dan Identifier (ISBN/ISSN) wajib diisi!'
    ]);
    exit;
}

// ========================================
// 2. ADDITIONAL FIELDS
// ========================================
$publisher_id   = !empty($_POST['publisher_id']) && $_POST['publisher_id'] !== 'new' ? intval($_POST['publisher_id']) : null;
$author_id      = !empty($_POST['author_id']) && $_POST['author_id'] !== 'new' ? intval($_POST['author_id']) : null;
$peran_author   = $conn->real_escape_string($_POST['peran_author'] ?? 'penulis_utama');
$tahun_terbit   = !empty($_POST['tahun_terbit']) ? intval($_POST['tahun_terbit']) : null;
$jumlah_halaman = !empty($_POST['jumlah_halaman']) ? intval($_POST['jumlah_halaman']) : 0;
$deskripsi      = $conn->real_escape_string($_POST['deskripsi'] ?? '');

// ========================================
// 3. UC-2: AUTHOR BARU (Biografi Max 200 Karakter)
// ========================================
$new_author_name = $conn->real_escape_string($_POST['new_author_name'] ?? '');
$author_biografi = $conn->real_escape_string($_POST['author_biografi'] ?? '');

if (!empty($author_biografi) && mb_strlen($author_biografi) > 200) {
    echo json_encode([
        'success' => false, 
        'message' => "Biografi penulis maksimal 200 karakter! Saat ini: " . mb_strlen($author_biografi)
    ]);
    exit;
}

// ========================================
// 4. UC-1: PUBLISHER BARU (4 Fields)
// ========================================
$new_pub_name = $conn->real_escape_string($_POST['new_pub_name'] ?? '');
$pub_alamat   = $conn->real_escape_string($_POST['pub_alamat'] ?? '');
$pub_telepon  = $conn->real_escape_string($_POST['pub_no_telepon'] ?? '');
$pub_email    = $conn->real_escape_string($_POST['pub_email'] ?? '');

if (!empty($pub_email) && !filter_var($pub_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Format email penerbit tidak valid!'
    ]);
    exit;
}

// ========================================
// 5. COVER IMAGE UPLOAD
// ========================================
$cover_image = null;
if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['cover_image'];
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_name = $file['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($file_ext, $allowed_ext)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Format file tidak valid! Gunakan: JPG, PNG, GIF, WEBP'
        ]);
        exit;
    }

    if ($file_size > 2 * 1024 * 1024) {
        echo json_encode([
            'success' => false, 
            'message' => 'Ukuran file terlalu besar! Maksimal 2MB.'
        ]);
        exit;
    }

    $new_filename = 'book_' . uniqid() . '.' . $file_ext;
    $upload_dir = '../../uploads/covers/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $upload_path = $upload_dir . $new_filename;
    if (move_uploaded_file($file_tmp, $upload_path)) {
        $cover_image = 'uploads/covers/' . $new_filename;
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal upload cover image!'
        ]);
        exit;
    }
}

// ========================================
// 6. RFID UNITS DATA
// ========================================
$rfid_units = json_decode($_POST['rfid_units'] ?? '[]', true);

if (!is_array($rfid_units) || count($rfid_units) != $jumlah_eksemplar) {
    echo json_encode([
        'success' => false, 
        'message' => "Jumlah RFID units harus sesuai stok ($jumlah_eksemplar)!"
    ]);
    exit;
}

// ========================================
// 7. BEGIN TRANSACTION
// ========================================
$conn->begin_transaction();

try {
    // ========================================
    // 8. UC-1: HANDLE PUBLISHER BARU
    // ========================================
    if (!$publisher_id && !empty($new_pub_name)) {
        $checkPub = $conn->query("SELECT id FROM publishers WHERE nama_penerbit = '$new_pub_name' AND is_deleted = 0 LIMIT 1");
        
        if ($checkPub->num_rows > 0) {
            $publisher_id = $checkPub->fetch_assoc()['id'];
            error_log("[PUBLISHER] Sudah ada: ID $publisher_id");
        } else {
            $sqlPub = "INSERT INTO publishers 
                        (nama_penerbit, alamat, no_telepon, email) 
                        VALUES 
                        ('$new_pub_name', '$pub_alamat', '$pub_telepon', '$pub_email')";
            
            if (!$conn->query($sqlPub)) {
                throw new Exception("Gagal simpan penerbit baru: " . $conn->error);
            }
            $publisher_id = $conn->insert_id;
            error_log("[PUBLISHER CREATED] ID: $publisher_id, Nama: $new_pub_name");
        }
    }

    // ========================================
    // 9. UC-2: HANDLE AUTHOR BARU
    // ========================================
    if (!$author_id && !empty($new_author_name)) {
        $checkAuth = $conn->query("SELECT id FROM authors WHERE nama_pengarang = '$new_author_name' AND is_deleted = 0 LIMIT 1");
        
        if ($checkAuth->num_rows > 0) {
            $author_id = $checkAuth->fetch_assoc()['id'];
            error_log("[AUTHOR] Sudah ada: ID $author_id");
        } else {
            $sqlAuth = "INSERT INTO authors 
                        (nama_pengarang, biografi) 
                        VALUES 
                        ('$new_author_name', '$author_biografi')";
            
            if (!$conn->query($sqlAuth)) {
                throw new Exception("Gagal simpan penulis baru: " . $conn->error);
            }
            $author_id = $conn->insert_id;
            error_log("[AUTHOR CREATED] ID: $author_id, Nama: $new_author_name");
        }
    }

    // ========================================
    // 10. INSERT BOOK UTAMA
    // ========================================
    $keterangan_sql = $keterangan ? "'$keterangan'" : "NULL";

    $sqlBook = "INSERT INTO books 
                (judul_buku, isbn, kategori, lokasi_rak, publisher_id, tahun_terbit, jumlah_halaman, deskripsi, cover_image, jumlah_eksemplar, keterangan) 
                VALUES 
                ('$judul', '$identifier', '$kategori', '$lokasi', " . ($publisher_id ?? 'NULL') . ", " . ($tahun_terbit ?? 'NULL') . ", $jumlah_halaman, '$deskripsi', " . ($cover_image ? "'$cover_image'" : 'NULL') . ", $jumlah_eksemplar, $keterangan_sql)";
                
    if (!$conn->query($sqlBook)) {
        throw new Exception("Gagal simpan buku: " . $conn->error);
    }

    $bookId = $conn->insert_id;
    error_log("[BOOK CREATED] ID: $bookId, Judul: $judul");

    // ========================================
    // 11. UC-6: RELASI PENULIS
    // ========================================
    if ($author_id) {
        $checkRel = $conn->query("SELECT id FROM rt_book_author WHERE book_id = $bookId AND author_id = $author_id AND is_deleted = 0");
        if ($checkRel->num_rows == 0) {
            $sqlRel = "INSERT INTO rt_book_author (book_id, author_id, peran) VALUES ($bookId, $author_id, '$peran_author')";
            if (!$conn->query($sqlRel)) {
                throw new Exception("Gagal hubungkan penulis: " . $conn->error);
            }
            error_log("[BOOK-AUTHOR LINKED] Book: $bookId, Author: $author_id, Peran: $peran_author");
        }
    }

    // ========================================
    // 12. UC-4 & UC-5: REGISTER RFID UNITS
    // ========================================
    $tgl_reg = date('Y-m-d');
    $successCount = 0;
    $kondisi_summary = [];

    foreach ($rfid_units as $unit) {
        $uid_buffer_id = intval($unit['uid_buffer_id'] ?? 0);
        $kode_eksemplar = $conn->real_escape_string($unit['kode_eksemplar'] ?? '');
        $kondisi = $conn->real_escape_string($unit['kondisi'] ?? 'baik');

        $valid_kondisi = ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'];
        $kondisi = in_array($kondisi, $valid_kondisi) ? $kondisi : 'baik';
        $kondisi_summary[$kondisi] = ($kondisi_summary[$kondisi] ?? 0) + 1;

        if ($uid_buffer_id <= 0 || empty($kode_eksemplar)) {
            continue;
        }

        // Cek timestamp < 5 menit
        $tsCheck = $conn->query("SELECT timestamp FROM uid_buffer WHERE id = $uid_buffer_id");
        if ($tsCheck && $row = $tsCheck->fetch_assoc()) {
            if (time() - strtotime($row['timestamp']) > 300) {
                error_log("[EXPIRED UID] Skipped: Buffer ID $uid_buffer_id");
                continue;
            }
        }

        // Cek duplikat
        $dupCheck = $conn->query("SELECT id FROM rt_book_uid WHERE uid_buffer_id = $uid_buffer_id AND is_deleted = 0");
        if ($dupCheck->num_rows > 0) {
            error_log("[DUPLICATE UID] Skipped: Buffer ID $uid_buffer_id");
            continue;
        }

        // Insert unit
        $sqlUnit = "INSERT INTO rt_book_uid 
                    (book_id, uid_buffer_id, kode_eksemplar, kondisi, tanggal_registrasi) 
                    VALUES ($bookId, $uid_buffer_id, '$kode_eksemplar', '$kondisi', '$tgl_reg')";

        if (!$conn->query($sqlUnit)) {
            throw new Exception("Gagal simpan unit RFID: " . $conn->error);
        }

        // Update buffer
        $updateBuf = "UPDATE uid_buffer 
                    SET jenis = 'book', is_labeled = 'yes', updated_at = NOW() 
                    WHERE id = $uid_buffer_id";
        if (!$conn->query($updateBuf)) {
            throw new Exception("Gagal update status UID: " . $conn->error);
        }

        $successCount++;
        error_log("[RFID REGISTERED] Kode: $kode_eksemplar, UID Buffer: $uid_buffer_id, Kondisi: $kondisi");
    }

    if ($successCount != $jumlah_eksemplar) {
        throw new Exception("Jumlah unit berhasil ($successCount) tidak sesuai stok ($jumlah_eksemplar). Mungkin ada UID expired/duplikat.");
    }

    // ========================================
    // 13. COMMIT
    // ========================================
    $conn->commit();

    // ========================================
    // 14. SUCCESS RESPONSE
    // ========================================
    $kondisi_text = [];
    foreach ($kondisi_summary as $k => $c) {
        $label = ucfirst(str_replace('_', ' ', $k));
        $kondisi_text[] = "$label: $c";
    }

    echo json_encode([
        'success' => true,
        'message' => "Registrasi berhasil!<br>$successCount unit RFID terdaftar<br>Kondisi: " . implode(', ', $kondisi_text),
        'book_id' => $bookId,
        'units_registered' => $successCount
    ]);

    error_log("[REGISTRATION SUCCESS] Book ID: $bookId, Units: $successCount");

} catch (Exception $e) {
    $conn->rollback();

    if ($cover_image && file_exists('../../' . $cover_image)) {
        unlink('../../' . $cover_image);
    }

    echo json_encode([
        'success' => false,
        'message' => 'Gagal registrasi: ' . $e->getMessage()
    ]);

    error_log("[REGISTRATION FAILED] " . $e->getMessage());
}

$conn->close();
exit;
?>