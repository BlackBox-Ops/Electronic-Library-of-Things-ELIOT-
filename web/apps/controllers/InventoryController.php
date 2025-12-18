<?php
/**
 * Inventory Controller - Enhanced Version
 * Path: web/apps/controllers/InventoryController.php
 * 
 * HANDLES:
 * - UC-1: Publisher dengan 4 fields (nama, alamat, telepon, email)
 * - UC-2: Author dengan biografi (max 200 karakter)
 * - UC-4: Auto-generate kode eksemplar unik (ROB-001, ROB-002, dst)
 * - UC-5: Input kondisi buku per eksemplar
 * - UC-6: Multi-author dengan role (conditional)
 */

require_once '../../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ========================================
    // 1. CAPTURE & VALIDATE BASIC DATA
    // ========================================
    $judul         = $conn->real_escape_string($_POST['judul'] ?? '');
    $identifier    = $conn->real_escape_string($_POST['identifier'] ?? ''); 
    $kategori      = $conn->real_escape_string($_POST['kategori'] ?? 'buku');
    $lokasi        = $conn->real_escape_string($_POST['lokasi'] ?? '');
    $jumlah_eksemplar = intval($_POST['jumlah_eksemplar'] ?? 1);
    
    // Validasi input dasar
    if (empty($judul) || empty($identifier)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Judul dan ISBN wajib diisi!'
        ]);
        exit;
    }
    
    // ========================================
    // 2. CAPTURE ADDITIONAL FIELDS
    // ========================================
    $publisher_id   = !empty($_POST['publisher_id']) && $_POST['publisher_id'] !== 'new' 
                      ? intval($_POST['publisher_id']) : null;
    $author_id      = !empty($_POST['author_id']) && $_POST['author_id'] !== 'new' 
                      ? intval($_POST['author_id']) : null;
    $peran_author   = $conn->real_escape_string($_POST['peran_author'] ?? 'penulis_utama');
    $tahun_terbit   = !empty($_POST['tahun_terbit']) ? intval($_POST['tahun_terbit']) : null;
    $jumlah_halaman = !empty($_POST['jumlah_halaman']) ? intval($_POST['jumlah_halaman']) : 0;
    $deskripsi      = $conn->real_escape_string($_POST['deskripsi'] ?? '');
    
    // ========================================
    // 3. UC-2: AUTHOR DATA (Biografi Max 200 Karakter)
    // ========================================
    $new_author_name = $conn->real_escape_string($_POST['new_author_name'] ?? '');
    $author_biografi = $conn->real_escape_string($_POST['author_biografi'] ?? '');
    
    // Validasi biografi maksimal 200 KARAKTER (bukan kata)
    if (!empty($author_biografi)) {
        $char_count = mb_strlen($author_biografi);
        if ($char_count > 200) {
            echo json_encode([
                'success' => false, 
                'message' => "Biografi penulis maksimal 200 karakter! Saat ini: {$char_count} karakter."
            ]);
            exit;
        }
    }
    
    // ========================================
    // 4. UC-1: PUBLISHER DATA (4 Fields Lengkap)
    // ========================================
    $new_pub_name = $conn->real_escape_string($_POST['new_pub_name'] ?? '');
    $pub_alamat   = $conn->real_escape_string($_POST['pub_alamat'] ?? '');
    $pub_telepon  = $conn->real_escape_string($_POST['pub_telepon'] ?? '');
    $pub_email    = $conn->real_escape_string($_POST['pub_email'] ?? '');
    
    // Validasi email penerbit
    if (!empty($pub_email) && !filter_var($pub_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Format email penerbit tidak valid!'
        ]);
        exit;
    }
    
    // ========================================
    // 5. HANDLE COVER IMAGE UPLOAD
    // ========================================
    $cover_image = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['cover_image'];
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $file_name = $file['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validasi ekstensi file
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_ext, $allowed_ext)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Format file tidak valid! Gunakan: JPG, PNG, GIF, WEBP'
            ]);
            exit;
        }
        
        // Validasi ukuran file (max 2MB)
        if ($file_size > 2 * 1024 * 1024) {
            echo json_encode([
                'success' => false, 
                'message' => 'Ukuran file terlalu besar! Maksimal 2MB.'
            ]);
            exit;
        }
        
        // Generate nama file unik
        $new_filename = 'book_' . uniqid() . '.' . $file_ext;
        $upload_dir = '../../uploads/covers/';
        
        // Buat direktori jika belum ada
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $upload_path = $upload_dir . $new_filename;
        
        // Upload file
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
    // 6. UC-4 & UC-5: RFID UNITS DATA (Kode Unik + Kondisi)
    // ========================================
    $rfid_units = json_decode($_POST['rfid_units'] ?? '[]', true);
    
    if (empty($rfid_units) || !is_array($rfid_units)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Minimal harus scan 1 RFID!'
        ]);
        exit;
    }

    // ========================================
    // 7. BEGIN TRANSACTION
    // ========================================
    $conn->begin_transaction();

    try {
        
        // ========================================
        // 8. UC-1: KELOLA PENERBIT (Publisher Management)
        // ========================================
        if (!$publisher_id && !empty($new_pub_name)) {
            // Cek apakah penerbit sudah ada
            $checkPub = $conn->query("
                SELECT id 
                FROM publishers 
                WHERE nama_penerbit = '$new_pub_name' 
                AND is_deleted = 0 
                LIMIT 1
            ");
            
            if ($checkPub->num_rows > 0) {
                // Publisher sudah ada, gunakan yang existing
                $publisher_id = $checkPub->fetch_assoc()['id'];
            } else {
                // Insert publisher baru dengan 4 fields lengkap
                $sqlPub = "INSERT INTO publishers 
                          (nama_penerbit, alamat, no_telepon, email) 
                          VALUES 
                          ('$new_pub_name', '$pub_alamat', '$pub_telepon', '$pub_email')";
                
                if (!$conn->query($sqlPub)) {
                    throw new Exception("Gagal simpan penerbit: " . $conn->error);
                }
                
                $publisher_id = $conn->insert_id;
                error_log("[PUBLISHER CREATED] ID: {$publisher_id}, Name: {$new_pub_name}");
            }
        }

        // ========================================
        // 9. UC-2: KELOLA PENULIS (Author dengan Biografi)
        // ========================================
        if (!$author_id && !empty($new_author_name)) {
            // Cek apakah penulis sudah ada
            $checkAuth = $conn->query("
                SELECT id 
                FROM authors 
                WHERE nama_pengarang = '$new_author_name' 
                AND is_deleted = 0 
                LIMIT 1
            ");
            
            if ($checkAuth->num_rows > 0) {
                // Author sudah ada, gunakan yang existing
                $author_id = $checkAuth->fetch_assoc()['id'];
            } else {
                // Insert author baru dengan biografi (max 200 karakter)
                $sqlAuth = "INSERT INTO authors 
                           (nama_pengarang, biografi) 
                           VALUES 
                           ('$new_author_name', '$author_biografi')";
                
                if (!$conn->query($sqlAuth)) {
                    throw new Exception("Gagal simpan penulis: " . $conn->error);
                }
                
                $author_id = $conn->insert_id;
                error_log("[AUTHOR CREATED] ID: {$author_id}, Name: {$new_author_name}");
            }
        }

        // ========================================
        // 10. KELOLA MASTER BUKU (Books Table)
        // ========================================
        $checkBook = $conn->query("
            SELECT id 
            FROM books 
            WHERE isbn = '$identifier' 
            AND is_deleted = 0 
            LIMIT 1
        ");
        
        if ($checkBook->num_rows > 0) {
            // Buku sudah ada, update stok
            $bookId = $checkBook->fetch_assoc()['id'];
            $addCount = count($rfid_units);
            
            $updateBook = "UPDATE books 
                          SET jumlah_eksemplar = jumlah_eksemplar + $addCount, 
                              eksemplar_tersedia = eksemplar_tersedia + $addCount,
                              updated_at = NOW()";
            
            // Update cover jika ada upload baru
            if ($cover_image) {
                $updateBook .= ", cover_image = '$cover_image'";
            }
            
            $updateBook .= " WHERE id = $bookId";
            
            if (!$conn->query($updateBook)) {
                throw new Exception("Gagal update stok buku: " . $conn->error);
            }
            
            error_log("[BOOK UPDATED] ID: {$bookId}, Added: {$addCount} units");
            
        } else {
            // Buku baru - Insert dengan semua field
            $pubIdSql = $publisher_id ? $publisher_id : 'NULL';
            $tahunSql = $tahun_terbit ? $tahun_terbit : 'NULL';
            $coverSql = $cover_image ? "'$cover_image'" : 'NULL';
            
            $sqlInsertBook = "INSERT INTO books 
                (judul_buku, isbn, kategori, lokasi_rak, publisher_id, 
                 tahun_terbit, jumlah_halaman, deskripsi, 
                 jumlah_eksemplar, eksemplar_tersedia, cover_image) 
                VALUES 
                ('$judul', '$identifier', '$kategori', '$lokasi', $pubIdSql, 
                 $tahunSql, $jumlah_halaman, '$deskripsi', 
                 $jumlah_eksemplar, $jumlah_eksemplar, $coverSql)";
            
            if (!$conn->query($sqlInsertBook)) {
                throw new Exception("Gagal simpan buku: " . $conn->error);
            }
            
            $bookId = $conn->insert_id;
            error_log("[BOOK CREATED] ID: {$bookId}, Title: {$judul}");
        }

        // ========================================
        // 11. UC-6: KELOLA RELASI PENULIS (rt_book_author)
        // ========================================
        if ($author_id) {
            $checkRelAuthor = $conn->query("
                SELECT id 
                FROM rt_book_author 
                WHERE book_id = $bookId 
                  AND author_id = $author_id 
                  AND is_deleted = 0
            ");
            
            if ($checkRelAuthor->num_rows == 0) {
                // Insert relasi author dengan peran
                $sqlRelAuth = "INSERT INTO rt_book_author 
                              (book_id, author_id, peran) 
                              VALUES 
                              ($bookId, $author_id, '$peran_author')";
                
                if (!$conn->query($sqlRelAuth)) {
                    throw new Exception("Gagal hubungkan penulis: " . $conn->error);
                }
                
                error_log("[BOOK-AUTHOR LINKED] Book: {$bookId}, Author: {$author_id}, Role: {$peran_author}");
            }
        }

        // ========================================
        // 12. UC-4 & UC-5: KELOLA UNIT RFID (rt_book_uid)
        // Auto-generate Kode Eksemplar + Kondisi
        // ========================================
        $tgl_reg = date('Y-m-d');
        $successCount = 0;
        $kondisi_summary = [];
        
        foreach ($rfid_units as $index => $unit) {
            $uid_buffer_id = intval($unit['uid_buffer_id'] ?? 0);
            $kode_eksemplar = $conn->real_escape_string($unit['kode_eksemplar'] ?? '');
            $kondisi = $conn->real_escape_string($unit['kondisi'] ?? 'baik');
            
            // Validasi kondisi enum
            $valid_kondisi = ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'];
            if (!in_array($kondisi, $valid_kondisi)) {
                $kondisi = 'baik'; // default
            }
            
            // Track kondisi untuk summary
            $kondisi_summary[$kondisi] = ($kondisi_summary[$kondisi] ?? 0) + 1;
            
            if ($uid_buffer_id <= 0 || empty($kode_eksemplar)) {
                continue; // Skip invalid data
            }
            
            // Cek apakah UID sudah terdaftar
            $checkUID = $conn->query("
                SELECT id 
                FROM rt_book_uid 
                WHERE uid_buffer_id = $uid_buffer_id 
                  AND is_deleted = 0 
                LIMIT 1
            ");
            
            if ($checkUID->num_rows > 0) {
                // Skip jika sudah terdaftar (mencegah duplikasi)
                error_log("[SKIP DUPLICATE UID] Buffer ID: {$uid_buffer_id}");
                continue;
            }
            
            // UC-4: Insert unit baru dengan kode eksemplar unik dan kondisi
            $sqlUnit = "INSERT INTO rt_book_uid 
                       (book_id, uid_buffer_id, kode_eksemplar, kondisi, tanggal_registrasi) 
                       VALUES 
                       ($bookId, $uid_buffer_id, '$kode_eksemplar', '$kondisi', '$tgl_reg')";
            
            if (!$conn->query($sqlUnit)) {
                throw new Exception("Gagal simpan unit RFID: " . $conn->error);
            }
            
            // Update UID Buffer: set as labeled untuk buku
            $updateUID = "UPDATE uid_buffer 
                         SET jenis = 'book', 
                             is_labeled = 'yes',
                             updated_at = NOW()
                         WHERE id = $uid_buffer_id";
            
            if (!$conn->query($updateUID)) {
                throw new Exception("Gagal update status UID: " . $conn->error);
            }
            
            $successCount++;
            error_log("[RFID UNIT REGISTERED] Code: {$kode_eksemplar}, UID Buffer: {$uid_buffer_id}, Kondisi: {$kondisi}");
        }

        // Validasi: minimal 1 unit berhasil disimpan
        if ($successCount == 0) {
            throw new Exception("Tidak ada unit RFID yang berhasil disimpan!");
        }

        // ========================================
        // 13. COMMIT TRANSACTION
        // ========================================
        $conn->commit();
        
        // ========================================
        // 14. GENERATE SUCCESS MESSAGE
        // ========================================
        $kondisi_text = [];
        foreach ($kondisi_summary as $k => $count) {
            $kondisi_label = ucfirst(str_replace('_', ' ', $k));
            $kondisi_text[] = "{$kondisi_label}: {$count}";
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "Registrasi berhasil!<br>{$successCount} unit RFID terdaftar<br>Kondisi: " . implode(', ', $kondisi_text),
            'book_id' => $bookId,
            'units_registered' => $successCount
        ]);
        
        error_log("[REGISTRATION SUCCESS] Book ID: {$bookId}, Units: {$successCount}");

    } catch (Exception $e) {
        // ========================================
        // 15. ROLLBACK ON ERROR
        // ========================================
        $conn->rollback();
        
        // Hapus file upload jika ada error
        if ($cover_image && file_exists('../../' . $cover_image)) {
            unlink('../../' . $cover_image);
        }
        
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal: ' . $e->getMessage()
        ]);
        
        error_log("[REGISTRATION FAILED] Error: " . $e->getMessage());
    }
    
    $conn->close();
    
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method'
    ]);
}

exit;