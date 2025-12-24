<?php
/**
 * EditInventoryController.php - FIXED VERSION
 * Path: web/apps/controllers/EditInventoryController.php
 * 
 * FIXES:
 * - Bug #1: Proper handling of delete_eksemplar array
 * - Bug #2: Enhanced logging for debugging
 * - Bug #3: Proper author relation update (no data loss)
 * - Bug #4: Update existing publisher/author data
 * - Bug #5: Handle biografi updates
 * - Bug #6: Fixed JSON parsing for delete_eksemplar
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

// Enhanced DEBUG logging
error_log('========================================');
error_log('[EDIT INVENTORY] REQUEST START');
error_log('[POST DATA] ' . json_encode($_POST));
error_log('[FILES] ' . json_encode($_FILES));
error_log('========================================');

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

    error_log("[BOOK DATA] Judul: $judul_buku, ISBN: $isbn");

    // ========================================
    // 2. HANDLE PUBLISHER (FIXED)
    // ========================================
    $publisher_id = null;
    $publisher_action = $_POST['publisher_action'] ?? 'existing';
    
    error_log("[PUBLISHER] Action: $publisher_action");
    
    if ($publisher_action === 'new') {
        $new_pub_name = trim($_POST['new_publisher_name'] ?? '');
        
        if (empty($new_pub_name)) {
            throw new Exception("Nama penerbit baru wajib diisi");
        }
        
        $safe_name = $conn->real_escape_string($new_pub_name);
        
        $checkPub = $conn->query("SELECT id FROM publishers WHERE nama_penerbit = '$safe_name' AND is_deleted = 0 LIMIT 1");
        
        if ($checkPub && $checkPub->num_rows > 0) {
            $publisher_id = $checkPub->fetch_assoc()['id'];
            error_log("[PUBLISHER] Already exists, using ID: $publisher_id");
        } else {
            $insertPub = "INSERT INTO publishers (nama_penerbit) VALUES ('$safe_name')";
            
            if (!$conn->query($insertPub)) {
                throw new Exception("Gagal simpan penerbit baru: " . $conn->error);
            }
            
            $publisher_id = $conn->insert_id;
            error_log("[PUBLISHER] Created new: ID $publisher_id, Nama: $safe_name");
        }
        
    } elseif ($publisher_action === 'existing') {
        $publisher_id = intval($_POST['publisher_id'] ?? 0);
        
        if ($publisher_id > 0) {
            $update_pub_alamat = trim($_POST['publisher_alamat'] ?? '');
            $update_pub_telepon = trim($_POST['publisher_telepon'] ?? '');
            $update_pub_email = trim($_POST['publisher_email'] ?? '');
            
            if (!empty($update_pub_alamat) || !empty($update_pub_telepon) || !empty($update_pub_email)) {
                $safe_alamat = $conn->real_escape_string($update_pub_alamat);
                $safe_telepon = $conn->real_escape_string($update_pub_telepon);
                $safe_email = $conn->real_escape_string($update_pub_email);
                
                $updatePubQuery = "UPDATE publishers SET ";
                $updateFields = [];
                
                if (!empty($update_pub_alamat)) {
                    $updateFields[] = "alamat = '$safe_alamat'";
                }
                if (!empty($update_pub_telepon)) {
                    $updateFields[] = "no_telepon = '$safe_telepon'";
                }
                if (!empty($update_pub_email)) {
                    $updateFields[] = "email = '$safe_email'";
                }
                
                $updatePubQuery .= implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = $publisher_id";
                
                if (!$conn->query($updatePubQuery)) {
                    error_log("[PUBLISHER] Update failed: " . $conn->error);
                } else {
                    error_log("[PUBLISHER] Updated details for ID: $publisher_id");
                }
            }
            
            error_log("[PUBLISHER] Using existing ID: $publisher_id");
        }
    }

    // ========================================
    // 3. HANDLE AUTHOR (FIXED)
    // ========================================
    $author_id = null;
    $author_action = $_POST['author_action'] ?? 'existing';
    
    error_log("[AUTHOR] Action: $author_action");
    
    if ($author_action === 'new') {
        $new_author_name = trim($_POST['new_author_name'] ?? '');
        $author_biografi = $conn->real_escape_string($_POST['author_biografi'] ?? '');
        
        if (empty($new_author_name)) {
            throw new Exception("Nama penulis baru wajib diisi");
        }
        
        if (!empty($author_biografi) && mb_strlen($author_biografi) > 200) {
            throw new Exception("Biografi maksimal 200 karakter! Saat ini: " . mb_strlen($author_biografi));
        }
        
        $safe_author = $conn->real_escape_string($new_author_name);
        
        $checkAuth = $conn->query("SELECT id FROM authors WHERE nama_pengarang = '$safe_author' AND is_deleted = 0 LIMIT 1");
        
        if ($checkAuth && $checkAuth->num_rows > 0) {
            $author_id = $checkAuth->fetch_assoc()['id'];
            error_log("[AUTHOR] Already exists, using ID: $author_id");
        } else {
            $sqlAuth = "INSERT INTO authors (nama_pengarang, biografi) VALUES ('$safe_author', '$author_biografi')";
            
            if (!$conn->query($sqlAuth)) {
                throw new Exception("Gagal simpan penulis baru: " . $conn->error);
            }
            
            $author_id = $conn->insert_id;
            error_log("[AUTHOR] Created new: ID $author_id, Nama: $safe_author");
        }
        
    } elseif ($author_action === 'existing') {
        $author_id = intval($_POST['author_id'] ?? 0);
        
        if ($author_id > 0) {
            $update_biografi = trim($_POST['author_biografi'] ?? '');
            
            if (!empty($update_biografi)) {
                if (mb_strlen($update_biografi) > 200) {
                    throw new Exception("Biografi maksimal 200 karakter! Saat ini: " . mb_strlen($update_biografi));
                }
                
                $safe_bio = $conn->real_escape_string($update_biografi);
                $updateAuthQuery = "UPDATE authors SET biografi = '$safe_bio', updated_at = NOW() WHERE id = $author_id";
                
                if (!$conn->query($updateAuthQuery)) {
                    error_log("[AUTHOR] Biografi update failed: " . $conn->error);
                } else {
                    error_log("[AUTHOR] Updated biografi for ID: $author_id");
                }
            }
            
            error_log("[AUTHOR] Using existing ID: $author_id");
        }
    }

    // ========================================
    // 4. UPDATE RELASI AUTHOR (FIXED)
    // ========================================
    if ($author_id) {
        $peran_author = $conn->real_escape_string($_POST['peran_author'] ?? 'penulis_utama');
        $valid_peran = ['penulis_utama', 'co_author', 'editor', 'translator'];
        
        if (!in_array($peran_author, $valid_peran)) {
            $peran_author = 'penulis_utama';
        }
        
        $checkRel = $conn->query("SELECT id, is_deleted, peran FROM rt_book_author 
                                  WHERE book_id = $book_id AND author_id = $author_id 
                                  LIMIT 1");
        
        if ($checkRel && $checkRel->num_rows > 0) {
            $rel = $checkRel->fetch_assoc();
            $updateRel = "UPDATE rt_book_author 
                          SET is_deleted = 0, peran = '$peran_author', updated_at = NOW() 
                          WHERE id = {$rel['id']}";
            
            if (!$conn->query($updateRel)) {
                throw new Exception("Gagal update relasi penulis: " . $conn->error);
            }
            
            error_log("[AUTHOR RELATION] Updated: Book $book_id - Author $author_id - Peran: $peran_author");
        } else {
            $insertRel = "INSERT INTO rt_book_author (book_id, author_id, peran) 
                          VALUES ($book_id, $author_id, '$peran_author')";
            
            if (!$conn->query($insertRel)) {
                throw new Exception("Gagal insert relasi penulis: " . $conn->error);
            }
            
            error_log("[AUTHOR RELATION] Created: Book $book_id - Author $author_id - Peran: $peran_author");
        }
    }

    // ========================================
    // 5. HANDLE COVER IMAGE
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
            
            if ($old_cover && file_exists('../../' . $old_cover)) {
                unlink('../../' . $old_cover);
            }
            
            error_log("[COVER] Uploaded new: $cover_image");
        } else {
            throw new Exception("Gagal upload cover");
        }
    } else {
        $cover_image = $old_cover;
    }

    // ========================================
    // 6. UPDATE TABEL BOOKS
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

    error_log("[BOOK] Updated ID: $book_id");

    // ========================================
    // 7. UPDATE KONDISI EKSEMPLAR
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
    // 8. HAPUS EKSEMPLAR (SOFT DELETE) - FIXED
    // ========================================
    $delete_eksemplar_raw = $_POST['delete_eksemplar'] ?? '[]';
    
    // Parse JSON if it's a string
    if (is_string($delete_eksemplar_raw)) {
        $delete_eksemplar = json_decode($delete_eksemplar_raw, true);
    } else {
        $delete_eksemplar = $delete_eksemplar_raw;
    }
    
    error_log("[DELETE EKSEMPLAR] Raw data: " . $delete_eksemplar_raw);
    error_log("[DELETE EKSEMPLAR] Parsed: " . json_encode($delete_eksemplar));
    
    if (is_array($delete_eksemplar) && !empty($delete_eksemplar)) {
        foreach ($delete_eksemplar as $del_id) {
            $del_id = intval($del_id);
            
            if ($del_id > 0) {
                // First, get UID buffer ID
                $getBuffer = $conn->query("SELECT uid_buffer_id FROM rt_book_uid WHERE id = $del_id AND book_id = $book_id LIMIT 1");
                
                if ($getBuffer && $getBuffer->num_rows > 0) {
                    $bufferData = $getBuffer->fetch_assoc();
                    $uid_buffer_id = $bufferData['uid_buffer_id'];
                    
                    // Soft delete eksemplar
                    $softDelete = "UPDATE rt_book_uid SET is_deleted = 1, updated_at = NOW() WHERE id = $del_id AND book_id = $book_id";
                    
                    if (!$conn->query($softDelete)) {
                        throw new Exception("Gagal hapus eksemplar ID $del_id: " . $conn->error);
                    }
                    
                    // Mark UID buffer as deleted (don't change jenis)
                    $updateBuffer = "UPDATE uid_buffer SET is_deleted = 1, updated_at = NOW() WHERE id = $uid_buffer_id";
                    
                    if (!$conn->query($updateBuffer)) {
                        error_log("[WARNING] Failed to update UID buffer $uid_buffer_id: " . $conn->error);
                    }
                    
                    error_log("[EKSEMPLAR] Soft deleted ID: $del_id (UID Buffer: $uid_buffer_id)");
                }
            }
        }
    } else {
        error_log("[DELETE EKSEMPLAR] No items to delete or invalid format");
    }

    // ========================================
    // 9. HITUNG ULANG JUMLAH EKSEMPLAR & TERSEDIA
    // ========================================
    $countQuery = $conn->query("SELECT COUNT(*) as total, 
                                COALESCE(SUM(CASE WHEN kondisi NOT IN ('rusak_berat', 'hilang') THEN 1 ELSE 0 END),0) as tersedia 
                                FROM rt_book_uid 
                                WHERE book_id = $book_id AND is_deleted = 0");
    
    $count = $countQuery ? $countQuery->fetch_assoc() : null;
    $total = intval($count['total'] ?? 0);
    $tersedia = intval($count['tersedia'] ?? 0);

    $updateCount = "UPDATE books SET 
                    jumlah_eksemplar = {$total},
                    eksemplar_tersedia = {$tersedia},
                    updated_at = NOW()
                    WHERE id = $book_id";
    
    if (!$conn->query($updateCount)) {
        throw new Exception("Gagal update jumlah eksemplar");
    }

    error_log("[EKSEMPLAR] Updated count - Total: $total, Tersedia: $tersedia");

    // ========================================
    // 10. COMMIT & SUCCESS
    // ========================================
    $conn->commit();

    error_log('[EDIT INVENTORY] SUCCESS - Book ID: ' . $book_id);
    error_log('========================================');

    echo json_encode([
        'success' => true,
        'message' => 'Data buku dan eksemplar berhasil diupdate!',
        'redirect' => 'inventory.php'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    error_log('[EDIT INVENTORY] ERROR: ' . $e->getMessage());
    error_log('========================================');
    
    echo json_encode([
        'success' => false,
        'message' => 'Gagal update: ' . $e->getMessage()
    ]);
}

$conn->close();
exit;
?>