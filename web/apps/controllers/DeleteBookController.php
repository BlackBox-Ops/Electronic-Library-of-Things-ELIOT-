<?php
/**
 * DeleteBookController.php
 * Path: web/apps/controllers/DeleteBookController.php
 * 
 * FEATURES:
 * - Soft delete book (is_deleted = 1)
 * - Soft delete all eksemplar
 * - Set all UID to 'invalid' status
 * - Logging for audit trail
 * - Called when user delete all eksemplar (remaining = 0)
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

// Only accept POST with JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$book_id = intval($input['book_id'] ?? 0);
$action = $input['action'] ?? '';

if ($book_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID buku tidak valid'
    ]);
    exit;
}

// Verify book exists
$checkBook = $conn->query("SELECT judul_buku FROM books WHERE id = $book_id AND is_deleted = 0");
if ($checkBook->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Buku tidak ditemukan atau sudah dihapus'
    ]);
    exit;
}

$bookData = $checkBook->fetch_assoc();

$conn->begin_transaction();

try {
        // ========================================
        // 1. GET ALL UID BUFFER IDS FROM EKSEMPLAR
        // ========================================
        $uidQuery = $conn->query("SELECT uid_buffer_id FROM rt_book_uid WHERE book_id = $book_id AND is_deleted = 0");
        $uidBufferIds = [];
        while ($row = $uidQuery->fetch_assoc()) {
            $uidBufferIds[] = $row['uid_buffer_id'];
        }
        
        $deletedEksemplarCount = count($uidBufferIds);
        
        // ========================================
        // 2. SOFT DELETE BOOK AND RESET COUNTERS
        // ========================================
        // Set is_deleted plus reset jumlah_eksemplar and eksemplar_tersedia ke 0 untuk konsistensi
        $deleteBook = "UPDATE books 
                        SET is_deleted = 1, 
                            jumlah_eksemplar = 0, 
                            eksemplar_tersedia = 0, 
                            updated_at = NOW() 
                        WHERE id = $book_id";
        if (!$conn->query($deleteBook)) {
            throw new Exception("Gagal soft delete buku: " . $conn->error);
        }
        
        error_log("[DELETE BOOK] Book ID: $book_id | Judul: {$bookData['judul_buku']}");
        
        // ========================================
        // 3. SOFT DELETE ALL EKSEMPLAR
        // ========================================
        $deleteEksemplar = "UPDATE rt_book_uid SET is_deleted = 1, updated_at = NOW() WHERE book_id = $book_id";
        if (!$conn->query($deleteEksemplar)) {
            throw new Exception("Gagal soft delete eksemplar: " . $conn->error);
        }
        
        error_log("[DELETE BOOK] Deleted {$deletedEksemplarCount} eksemplar");
        
        // ========================================
        // 4. MARK UID BUFFER ENTRIES AS DELETED (DO NOT CHANGE ENUM)
        // ========================================
        $invalidatedCount = 0;
        if (!empty($uidBufferIds)) {
            $uidIdsStr = implode(',', array_map('intval', $uidBufferIds));
            
            // Mark UID records as soft-deleted instead of changing enum/unknown columns
            $invalidateUID = "UPDATE uid_buffer 
                                SET is_deleted = 1,
                                    updated_at = NOW() 
                                WHERE id IN ($uidIdsStr)";
            
            if (!$conn->query($invalidateUID)) {
                throw new Exception("Gagal invalidate UID: " . $conn->error);
            }
            
            $invalidatedCount = $conn->affected_rows;
            error_log("[DELETE BOOK] Invalidated {$invalidatedCount} UID(s)");
        }
        
        // ========================================
        // 5. SOFT DELETE BOOK-AUTHOR RELATIONS
        // ========================================
        $deleteRelations = "UPDATE rt_book_author SET is_deleted = 1, updated_at = NOW() WHERE book_id = $book_id";
        $conn->query($deleteRelations); // Non-critical, so no throw
        
        // ========================================
        // 6. COMMIT TRANSACTION
        // ========================================
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Buku '{$bookData['judul_buku']}' berhasil dihapus (soft delete)",
            'deleted_eksemplar' => $deletedEksemplarCount,
            'invalidated_uid' => $invalidatedCount,
            'book_id' => $book_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        error_log("[DELETE BOOK SUCCESS] Book ID: $book_id | Eksemplar: $deletedEksemplarCount | UID: $invalidatedCount");
        
    } catch (Exception $e) {
        $conn->rollback();
        
        echo json_encode([
            'success' => false,
            'message' => 'Gagal hapus buku: ' . $e->getMessage(),
            'error_detail' => $e->getLine()
        ]);
        
        error_log("[DELETE BOOK FAILED] Book ID: $book_id | Error: " . $e->getMessage());
    }

$conn->close();
exit;
?>