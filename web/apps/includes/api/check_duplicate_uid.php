<?php
    /**
     * check_duplicate_uid.php
     * Path: web/apps/includes/api/check_duplicate_uid.php
     * 
     * API untuk mengecek apakah UID sudah terdaftar di buku tertentu
     * Digunakan untuk validasi sebelum menambah eksemplar baru
     */

    require_once '../../../includes/config.php';

    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');

    // Get parameters
    $uid = isset($_GET['uid']) ? trim($_GET['uid']) : '';
    $book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;

    // Validation
    if (empty($uid)) {
        echo json_encode([
            'success' => false,
            'message' => 'UID parameter required',
            'isDuplicate' => false
        ]);
        exit;
    }

    try {
        // Check if UID exists in uid_buffer
        $stmtBuffer = $conn->prepare("SELECT id FROM uid_buffer WHERE uid = ? LIMIT 1");
        $stmtBuffer->bind_param("s", $uid);
        $stmtBuffer->execute();
        $bufferResult = $stmtBuffer->get_result();
        
        if ($bufferResult->num_rows === 0) {
            // UID tidak ada di buffer
            echo json_encode([
                'success' => true,
                'message' => 'UID not found in buffer',
                'isDuplicate' => false
            ]);
            exit;
        }
        
        $bufferData = $bufferResult->fetch_assoc();
        $uid_buffer_id = $bufferData['id'];
        
        // Check if already used in rt_book_uid
        $stmtCheck = $conn->prepare("
            SELECT rbu.id, rbu.kode_eksemplar, b.judul_buku 
            FROM rt_book_uid rbu
            JOIN books b ON rbu.book_id = b.id
            WHERE rbu.uid_buffer_id = ? 
            AND rbu.is_deleted = 0
            LIMIT 1
        ");
        $stmtCheck->bind_param("i", $uid_buffer_id);
        $stmtCheck->execute();
        $checkResult = $stmtCheck->get_result();
        
        if ($checkResult->num_rows > 0) {
            // UID sudah digunakan
            $usedData = $checkResult->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'message' => 'UID already registered',
                'isDuplicate' => true,
                'usedIn' => [
                    'book_title' => $usedData['judul_buku'],
                    'kode_eksemplar' => $usedData['kode_eksemplar']
                ]
            ]);
        } else {
            // UID belum digunakan (aman)
            echo json_encode([
                'success' => true,
                'message' => 'UID available',
                'isDuplicate' => false
            ]);
        }
        
        $stmtBuffer->close();
        $stmtCheck->close();
        
    } catch (Exception $e) {
        error_log("[CHECK DUPLICATE ERROR] " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'isDuplicate' => false
        ]);
    }

    $conn->close();
    exit;
?>