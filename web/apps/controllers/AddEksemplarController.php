<?php
/**
 * AddEksemplarController.php - FIXED VERSION
 * Path: web/apps/controllers/AddEksemplarController.php
 * 
 * FIX: Changed 'judul' to 'judul_buku' to match database schema
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../../includes/config.php';

ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

function debugLog($message, $data = null) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    error_log($logMessage);
}

debugLog("=== REQUEST START ===");

try {
    $rawInput = file_get_contents('php://input');
    debugLog("Raw Input", substr($rawInput, 0, 500));
    
    if (empty($rawInput)) {
        throw new Exception('Empty request body');
    }
    
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    $book_id = intval($data['book_id'] ?? 0);
    $new_eksemplar = $data['new_eksemplar'] ?? [];
    
    debugLog("Book ID", $book_id);
    debugLog("Eksemplar Count", count($new_eksemplar));
    
    if ($book_id <= 0) {
        throw new Exception('Book ID tidak valid: ' . $book_id);
    }
    
    if (empty($new_eksemplar)) {
        throw new Exception('Tidak ada eksemplar untuk ditambahkan');
    }
    
    if (!is_array($new_eksemplar)) {
        throw new Exception('Format eksemplar tidak valid');
    }
    
    if (!isset($conn) || $conn->connect_errno) {
        throw new Exception('Database connection failed: ' . ($conn->connect_error ?? 'unknown'));
    }
    
    $conn->begin_transaction();
    debugLog("Transaction started");
    
    // FIX: Changed 'judul' to 'judul_buku'
    $bkStmt = $conn->prepare("SELECT id, judul_buku FROM books WHERE id = ? LIMIT 1");
    
    if (!$bkStmt) {
        throw new Exception('Prepare statement failed: ' . $conn->error);
    }
    
    $bkStmt->bind_param("i", $book_id);
    $bkStmt->execute();
    $bkRes = $bkStmt->get_result();
    
    if ($bkRes->num_rows === 0) {
        $bkStmt->close();
        throw new Exception("Buku dengan ID {$book_id} tidak ditemukan");
    }
    
    $bk = $bkRes->fetch_assoc();
    debugLog("Book found", $bk);
    $bkStmt->close();
    
    // Use kode_buku if available, else judul_buku
    $ref = !empty($bk['kode_buku']) ? $bk['kode_buku'] : $bk['judul_buku'];
    debugLog("Reference for prefix", $ref);
    
    // Generate prefix
    $rawRef = strtoupper($ref);
    preg_match_all('/[A-Z0-9]/', $rawRef, $matches);
    $prefix = implode('', $matches[0]);
    $prefix = $prefix ? substr($prefix, 0, 6) : 'EKS';
    
    if (strlen($prefix) < 2) {
        $prefix = str_pad($prefix, 3, 'X');
    }
    
    debugLog("Generated prefix", $prefix);
    
    // Get max number
    $safePrefix = $conn->real_escape_string($prefix);
    $maxQuery = "SELECT COALESCE(MAX(CAST(SUBSTRING(kode_eksemplar, LENGTH('{$safePrefix}-')+1) AS UNSIGNED)), 0) AS maxnum 
                 FROM rt_book_uid 
                 WHERE kode_eksemplar LIKE '{$safePrefix}-%' 
                 AND is_deleted = 0";
    
    debugLog("Max query", $maxQuery);
    
    $resMax = $conn->query($maxQuery);
    if (!$resMax) {
        throw new Exception('Failed to get max number: ' . $conn->error);
    }
    
    $rowMax = $resMax->fetch_assoc();
    $nextNum = intval($rowMax['maxnum']);
    debugLog("Starting from number", $nextNum);
    
    $tgl_reg = date('Y-m-d');
    $successCount = 0;
    $failedUIDs = [];
    
    // Process each eksemplar
    foreach ($new_eksemplar as $index => $eks) {
        debugLog("Processing eksemplar index {$index}", $eks);
        
        $uid_buffer_id = intval($eks['uid_buffer_id'] ?? 0);
        $kondisi = $conn->real_escape_string($eks['kondisi'] ?? 'baik');
        
        if ($uid_buffer_id <= 0) {
            debugLog("SKIP: Invalid UID buffer ID", $uid_buffer_id);
            $failedUIDs[] = [
                'index' => $index,
                'reason' => 'Invalid UID buffer ID: ' . $uid_buffer_id
            ];
            continue;
        }
        
        // Check uid_buffer exists
        $chkStmt = $conn->prepare("SELECT id, uid, jenis FROM uid_buffer WHERE id = ? LIMIT 1");
        if (!$chkStmt) {
            throw new Exception('Prepare check failed: ' . $conn->error);
        }
        
        $chkStmt->bind_param("i", $uid_buffer_id);
        $chkStmt->execute();
        $chkRes = $chkStmt->get_result();
        
        if ($chkRes->num_rows === 0) {
            debugLog("UID BUFFER NOT FOUND", $uid_buffer_id);
            $chkStmt->close();
            $failedUIDs[] = [
                'index' => $index,
                'uid_buffer_id' => $uid_buffer_id,
                'reason' => 'UID tidak ditemukan di buffer'
            ];
            continue;
        }
        
        $uidData = $chkRes->fetch_assoc();
        debugLog("UID Buffer found", $uidData);
        $chkStmt->close();
        
        // Check duplicate
        $dupStmt = $conn->prepare("SELECT id, kode_eksemplar, book_id FROM rt_book_uid WHERE uid_buffer_id = ? AND is_deleted = 0 LIMIT 1");
        if (!$dupStmt) {
            throw new Exception('Prepare duplicate check failed: ' . $conn->error);
        }
        
        $dupStmt->bind_param("i", $uid_buffer_id);
        $dupStmt->execute();
        $dupRes = $dupStmt->get_result();
        
        if ($dupRes->num_rows > 0) {
            $dupData = $dupRes->fetch_assoc();
            debugLog("DUPLICATE FOUND", $dupData);
            $dupStmt->close();
            $failedUIDs[] = [
                'index' => $index,
                'uid_buffer_id' => $uid_buffer_id,
                'uid' => $uidData['uid'],
                'reason' => 'UID sudah terdaftar pada ' . $dupData['kode_eksemplar']
            ];
            continue;
        }
        $dupStmt->close();
        
        // Insert new eksemplar
        $attempts = 0;
        $inserted = false;
        
        while (!$inserted && $attempts < 50) {
            $attempts++;
            $nextNum++;
            $kodeEksemplar = $safePrefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            
            debugLog("Attempt {$attempts}: Trying kode", $kodeEksemplar);
            
            $insertStmt = $conn->prepare("INSERT INTO rt_book_uid (book_id, uid_buffer_id, kode_eksemplar, kondisi, tanggal_registrasi) VALUES (?, ?, ?, ?, ?)");
            
            if (!$insertStmt) {
                throw new Exception('Prepare insert failed: ' . $conn->error);
            }
            
            $insertStmt->bind_param("iisss", $book_id, $uid_buffer_id, $kodeEksemplar, $kondisi, $tgl_reg);
            
            if ($insertStmt->execute()) {
                $inserted = true;
                $newId = $insertStmt->insert_id;
                $successCount++;
                debugLog("INSERT SUCCESS", [
                    'id' => $newId,
                    'kode' => $kodeEksemplar,
                    'uid_buffer_id' => $uid_buffer_id
                ]);
                
                // Update uid_buffer
                $updateBuffer = $conn->prepare("UPDATE uid_buffer SET jenis = 'book', is_labeled = 'yes', updated_at = NOW() WHERE id = ?");
                if ($updateBuffer) {
                    $updateBuffer->bind_param("i", $uid_buffer_id);
                    if ($updateBuffer->execute()) {
                        debugLog("Buffer updated successfully", $uid_buffer_id);
                    } else {
                        debugLog("Buffer update warning", $conn->error);
                    }
                    $updateBuffer->close();
                }
            } else {
                if ($conn->errno === 1062) {
                    debugLog("Duplicate kode, retrying", $kodeEksemplar);
                    $insertStmt->close();
                    continue;
                } else {
                    debugLog("INSERT ERROR", [
                        'errno' => $conn->errno,
                        'error' => $conn->error
                    ]);
                    $insertStmt->close();
                    throw new Exception('Insert failed: ' . $conn->error);
                }
            }
            
            $insertStmt->close();
        }
        
        if (!$inserted) {
            debugLog("FAILED after max attempts", $attempts);
            $failedUIDs[] = [
                'index' => $index,
                'uid_buffer_id' => $uid_buffer_id,
                'reason' => 'Gagal generate kode unik setelah ' . $attempts . ' percobaan'
            ];
        }
    }
    
    debugLog("Insert loop completed", [
        'success' => $successCount,
        'failed' => count($failedUIDs)
    ]);
    
    // Update book counts
    debugLog("Updating book counts");
    $countStmt = $conn->prepare("SELECT COUNT(*) as total, 
                                    SUM(CASE WHEN kondisi NOT IN ('rusak_berat', 'hilang') THEN 1 ELSE 0 END) as tersedia 
                                 FROM rt_book_uid 
                                 WHERE book_id = ? AND is_deleted = 0");
    
    if (!$countStmt) {
        throw new Exception('Prepare count failed: ' . $conn->error);
    }
    
    $countStmt->bind_param("i", $book_id);
    $countStmt->execute();
    $count = $countStmt->get_result()->fetch_assoc();
    debugLog("Count result", $count);
    $countStmt->close();
    
    $updateBook = $conn->prepare("UPDATE books SET jumlah_eksemplar = ?, eksemplar_tersedia = ?, updated_at = NOW() WHERE id = ?");
    if (!$updateBook) {
        throw new Exception('Prepare update book failed: ' . $conn->error);
    }
    
    $updateBook->bind_param("iii", $count['total'], $count['tersedia'], $book_id);
    if (!$updateBook->execute()) {
        throw new Exception('Update book failed: ' . $conn->error);
    }
    debugLog("Book updated successfully");
    $updateBook->close();
    
    // Commit
    $conn->commit();
    debugLog("Transaction committed successfully");
    
    // Success response
    $response = [
        'success' => true,
        'message' => "{$successCount} eksemplar berhasil ditambahkan",
        'data' => [
            'success_count' => $successCount,
            'failed_count' => count($failedUIDs),
            'total_requested' => count($new_eksemplar)
        ]
    ];
    
    if (!empty($failedUIDs)) {
        $response['warnings'] = $failedUIDs;
        $response['message'] .= ", " . count($failedUIDs) . " gagal";
    }
    
    debugLog("SUCCESS RESPONSE", $response);
    
    ob_clean();
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
        debugLog("Transaction rolled back");
    }
    
    $errorMsg = $e->getMessage();
    
    debugLog("EXCEPTION CAUGHT", [
        'message' => $errorMsg,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    ob_clean();
    
    $errorResponse = [
        'success' => false,
        'message' => 'Gagal tambah eksemplar: ' . $errorMsg,
        'error' => $errorMsg,
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ];
    
    debugLog("ERROR RESPONSE", $errorResponse);
    
    echo json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

if (isset($conn)) {
    $conn->close();
}

debugLog("=== REQUEST END ===\n");

ob_end_flush();
exit;
?>