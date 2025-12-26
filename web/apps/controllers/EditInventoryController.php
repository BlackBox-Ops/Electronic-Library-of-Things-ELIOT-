<?php
/**
 * EditInventoryController.php - ENHANCED VERSION with Multiple Authors
 * Path: web/apps/controllers/EditInventoryController.php
 * 
 * NEW FEATURES:
 * ✅ Handle multiple authors dengan berbagai role
 * ✅ Publisher fields always visible and updateable
 * ✅ Proper deletion handling untuk authors
 * ✅ All previous features preserved
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

// Enhanced DEBUG logging
error_log('========================================');
error_log('[EDIT INVENTORY] REQUEST START - ENHANCED VERSION');
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
    $kategori       = $conn->real_escape_string($_POST['kategori'] ?? 'buku');
    $tahun_terbit   = !empty($_POST['tahun_terbit']) ? intval($_POST['tahun_terbit']) : null;
    $jumlah_halaman = !empty($_POST['jumlah_halaman']) ? intval($_POST['jumlah_halaman']) : null;
    $lokasi_rak     = $conn->real_escape_string($_POST['lokasi_rak'] ?? '');
    $deskripsi      = $conn->real_escape_string($_POST['deskripsi'] ?? '');
    $keterangan     = $conn->real_escape_string($_POST['keterangan'] ?? '');

    if (empty($judul_buku)) {
        throw new Exception("Judul buku wajib diisi");
    }

    error_log("[BOOK DATA] Judul: $judul_buku, ISBN: $isbn");

    // ========================================
    // 2. HANDLE PUBLISHER (ALWAYS VISIBLE FIELDS)
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
        $pub_alamat = $conn->real_escape_string($_POST['publisher_alamat'] ?? '');
        $pub_telepon = $conn->real_escape_string($_POST['publisher_telepon'] ?? '');
        $pub_email = $conn->real_escape_string($_POST['publisher_email'] ?? '');
        
        // Check if exists
        $checkPub = $conn->query("SELECT id FROM publishers WHERE nama_penerbit = '$safe_name' AND is_deleted = 0 LIMIT 1");
        
        if ($checkPub && $checkPub->num_rows > 0) {
            $publisher_id = $checkPub->fetch_assoc()['id'];
            error_log("[PUBLISHER] Already exists, using ID: $publisher_id");
        } else {
            // Insert new publisher with all fields
            $insertPub = "INSERT INTO publishers (nama_penerbit, alamat, no_telepon, email) 
                          VALUES ('$safe_name', '$pub_alamat', '$pub_telepon', '$pub_email')";
            
            if (!$conn->query($insertPub)) {
                throw new Exception("Gagal simpan penerbit baru: " . $conn->error);
            }
            
            $publisher_id = $conn->insert_id;
            error_log("[PUBLISHER] Created new: ID $publisher_id");
        }
        
    } elseif ($publisher_action === 'existing') {
        $publisher_id = intval($_POST['publisher_id'] ?? 0);
        
        if ($publisher_id > 0) {
            // Always update publisher fields (they're always visible)
            $pub_alamat = $conn->real_escape_string($_POST['publisher_alamat'] ?? '');
            $pub_telepon = $conn->real_escape_string($_POST['publisher_telepon'] ?? '');
            $pub_email = $conn->real_escape_string($_POST['publisher_email'] ?? '');
            
            $updatePubQuery = "UPDATE publishers SET 
                               alamat = '$pub_alamat',
                               no_telepon = '$pub_telepon',
                               email = '$pub_email',
                               updated_at = NOW() 
                               WHERE id = $publisher_id";
            
            if (!$conn->query($updatePubQuery)) {
                error_log("[PUBLISHER] Update failed: " . $conn->error);
            } else {
                error_log("[PUBLISHER] Updated details for ID: $publisher_id");
            }
        }
    }

    // ========================================
    // 3. HANDLE MULTIPLE AUTHORS (ENHANCED)
    // ========================================
    
    // A. Delete removed authors
    $deleted_authors_raw = $_POST['deleted_authors'] ?? '[]';
    $deleted_authors = is_string($deleted_authors_raw) ? json_decode($deleted_authors_raw, true) : $deleted_authors_raw;
    
    error_log("[AUTHORS DELETE] Raw: $deleted_authors_raw");
    
    if (is_array($deleted_authors) && !empty($deleted_authors)) {
        foreach ($deleted_authors as $relation_id) {
            $relation_id = intval($relation_id);
            if ($relation_id > 0) {
                $softDelete = "UPDATE rt_book_author SET is_deleted = 1, updated_at = NOW() 
                               WHERE id = $relation_id AND book_id = $book_id";
                
                if (!$conn->query($softDelete)) {
                    error_log("[AUTHORS DELETE] Failed for relation ID $relation_id: " . $conn->error);
                } else {
                    error_log("[AUTHORS DELETE] Soft deleted relation ID: $relation_id");
                }
            }
        }
    }
    
    // B. Update existing authors
    $existing_authors = $_POST['authors'] ?? [];
    
    error_log("[AUTHORS UPDATE] Count: " . count($existing_authors));
    
    foreach ($existing_authors as $author_data) {
        $relation_id = intval($author_data['relation_id'] ?? 0);
        $author_id = intval($author_data['author_id'] ?? 0);
        $peran = $conn->real_escape_string($author_data['peran'] ?? 'penulis_utama');
        $biografi = $conn->real_escape_string($author_data['biografi'] ?? '');
        
        if ($relation_id > 0 && $author_id > 0) {
            // Update relation
            $updateRel = "UPDATE rt_book_author SET 
                          peran = '$peran',
                          updated_at = NOW()
                          WHERE id = $relation_id AND book_id = $book_id";
            
            if (!$conn->query($updateRel)) {
                error_log("[AUTHORS UPDATE] Failed relation $relation_id: " . $conn->error);
            } else {
                error_log("[AUTHORS UPDATE] Updated relation $relation_id with role: $peran");
            }
            
            // Update author biografi if provided
            if (!empty($biografi)) {
                if (mb_strlen($biografi) > 200) {
                    throw new Exception("Biografi penulis maksimal 200 karakter");
                }
                
                $updateAuth = "UPDATE authors SET biografi = '$biografi', updated_at = NOW() WHERE id = $author_id";
                
                if (!$conn->query($updateAuth)) {
                    error_log("[AUTHORS UPDATE] Failed biografi for author $author_id: " . $conn->error);
                } else {
                    error_log("[AUTHORS UPDATE] Updated biografi for author $author_id");
                }
            }
        }
    }
    
    // C. Add new authors
    $new_authors = $_POST['new_authors'] ?? [];
    
    error_log("[AUTHORS NEW] Count: " . count($new_authors));
    
    foreach ($new_authors as $new_author) {
        $author_id = $new_author['author_id'] ?? '';
        $new_name = trim($new_author['new_name'] ?? '');
        $role = $conn->real_escape_string($new_author['role'] ?? 'penulis_utama');
        $bio = $conn->real_escape_string($new_author['bio'] ?? '');
        
        if (!empty($bio) && mb_strlen($bio) > 200) {
            throw new Exception("Biografi penulis maksimal 200 karakter");
        }
        
        $final_author_id = null;
        
        if ($author_id === 'new') {
            // Create new author
            if (empty($new_name)) {
                throw new Exception("Nama penulis baru wajib diisi");
            }
            
            $safe_name = $conn->real_escape_string($new_name);
            
            // Check if exists
            $checkAuth = $conn->query("SELECT id FROM authors WHERE nama_pengarang = '$safe_name' AND is_deleted = 0 LIMIT 1");
            
            if ($checkAuth && $checkAuth->num_rows > 0) {
                $final_author_id = $checkAuth->fetch_assoc()['id'];
                error_log("[AUTHORS NEW] Author exists, using ID: $final_author_id");
            } else {
                $insertAuth = "INSERT INTO authors (nama_pengarang, biografi) VALUES ('$safe_name', '$bio')";
                
                if (!$conn->query($insertAuth)) {
                    throw new Exception("Gagal simpan penulis baru: " . $conn->error);
                }
                
                $final_author_id = $conn->insert_id;
                error_log("[AUTHORS NEW] Created new author ID: $final_author_id");
            }
        } else {
            // Use existing author
            $final_author_id = intval($author_id);
            
            // Update biografi if provided
            if (!empty($bio) && $final_author_id > 0) {
                $updateBio = "UPDATE authors SET biografi = '$bio', updated_at = NOW() WHERE id = $final_author_id";
                $conn->query($updateBio);
            }
        }
        
        // Create relation
        if ($final_author_id > 0) {
            // Check if relation already exists
            $checkRel = $conn->query("SELECT id FROM rt_book_author 
                                      WHERE book_id = $book_id AND author_id = $final_author_id 
                                      LIMIT 1");
            
            if ($checkRel && $checkRel->num_rows > 0) {
                // Update existing relation
                $rel_id = $checkRel->fetch_assoc()['id'];
                $updateRel = "UPDATE rt_book_author SET is_deleted = 0, peran = '$role', updated_at = NOW() WHERE id = $rel_id";
                $conn->query($updateRel);
                error_log("[AUTHORS NEW] Updated existing relation for author $final_author_id");
            } else {
                // Insert new relation
                $insertRel = "INSERT INTO rt_book_author (book_id, author_id, peran) 
                              VALUES ($book_id, $final_author_id, '$role')";
                
                if (!$conn->query($insertRel)) {
                    throw new Exception("Gagal simpan relasi penulis: " . $conn->error);
                }
                
                error_log("[AUTHORS NEW] Created relation for author $final_author_id with role: $role");
            }
        }
    }
    
    // Validate: must have at least one author
    $countAuthors = $conn->query("SELECT COUNT(*) as total FROM rt_book_author 
                                  WHERE book_id = $book_id AND is_deleted = 0")->fetch_assoc()['total'];
    
    if ($countAuthors == 0) {
        throw new Exception("Minimal harus ada 1 penulis untuk buku ini");
    }
    
    error_log("[AUTHORS] Total authors after update: $countAuthors");

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
    // 5. UPDATE TABEL BOOKS
    // ========================================
    $updateBook = "UPDATE books SET 
                    judul_buku = '$judul_buku',
                    isbn = '$isbn',
                    kategori = '$kategori',
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
    $delete_eksemplar_raw = $_POST['delete_eksemplar'] ?? '[]';
    
    if (is_string($delete_eksemplar_raw)) {
        $delete_eksemplar = json_decode($delete_eksemplar_raw, true);
    } else {
        $delete_eksemplar = $delete_eksemplar_raw;
    }
    
    error_log("[DELETE EKSEMPLAR] Raw: $delete_eksemplar_raw");
    error_log("[DELETE EKSEMPLAR] Parsed: " . json_encode($delete_eksemplar));
    
    if (is_array($delete_eksemplar) && !empty($delete_eksemplar)) {
        foreach ($delete_eksemplar as $del_id) {
            $del_id = intval($del_id);
            
            if ($del_id > 0) {
                $getBuffer = $conn->query("SELECT uid_buffer_id FROM rt_book_uid WHERE id = $del_id AND book_id = $book_id LIMIT 1");
                
                if ($getBuffer && $getBuffer->num_rows > 0) {
                    $bufferData = $getBuffer->fetch_assoc();
                    $uid_buffer_id = $bufferData['uid_buffer_id'];
                    
                    $softDelete = "UPDATE rt_book_uid SET is_deleted = 1, updated_at = NOW() WHERE id = $del_id AND book_id = $book_id";
                    
                    if (!$conn->query($softDelete)) {
                        throw new Exception("Gagal hapus eksemplar ID $del_id: " . $conn->error);
                    }
                    
                    $updateBuffer = "UPDATE uid_buffer SET is_deleted = 1, updated_at = NOW() WHERE id = $uid_buffer_id";
                    $conn->query($updateBuffer);
                    
                    error_log("[EKSEMPLAR] Soft deleted ID: $del_id (UID Buffer: $uid_buffer_id)");
                }
            }
        }
    }

    // ========================================
    // 8. HITUNG ULANG JUMLAH EKSEMPLAR & TERSEDIA
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
    // 9. COMMIT & SUCCESS
    // ========================================
    $conn->commit();

    error_log('[EDIT INVENTORY] SUCCESS - Book ID: ' . $book_id);
    error_log('========================================');

    echo json_encode([
        'success' => true,
        'message' => 'Data buku, penulis, penerbit, dan eksemplar berhasil diupdate!',
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