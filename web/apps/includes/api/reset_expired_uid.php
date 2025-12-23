<?php
/**
 * API: Reset Expired UID Buffer
 * Path: web/apps/includes/api/reset_expired_uid.php
 * 
 * FITUR:
 * - Reset timestamp UID yang pending > 5 menit
 * - Order by timestamp ASC (FIFO - yang paling lama di-reset duluan)
 * - Support limit (opsional via GET parameter)
 * - Logging lengkap untuk audit trail
 * 
 * QUERY LOGIC:
 * UPDATE uid_buffer 
 * SET timestamp = NOW() 
 * WHERE status='pending' AND is_labeled='no' AND jenis='pending'
 * AND timestamp < NOW() - INTERVAL 5 MINUTE
 * ORDER BY timestamp ASC 
 * LIMIT n
 */

require_once '../../../includes/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Optional: Limit jumlah UID yang di-reset
    // Jika tidak ada limit, reset SEMUA expired UID
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : null;
    
    // Step 1: Get expired UID untuk log
    $checkSql = "SELECT id, uid, timestamp, 
                    TIMESTAMPDIFF(MINUTE, timestamp, NOW()) as minutes_old
                    FROM uid_buffer 
                WHERE is_labeled = 'no' 
                    AND is_deleted = 0 
                    AND jenis = 'pending'
                    AND timestamp < NOW() - INTERVAL 5 MINUTE
                ORDER BY timestamp ASC";
    
    if ($limit) {
        $checkSql .= " LIMIT " . intval($limit);
    }
    
    error_log("[RESET_UID] Checking expired UIDs: " . $checkSql);
    
    $checkResult = $conn->query($checkSql);
    
    if (!$checkResult) {
        throw new Exception("Query check error: " . $conn->error);
    }
    
    $expiredUIDs = [];
    while ($row = $checkResult->fetch_assoc()) {
        $expiredUIDs[] = [
            'id' => $row['id'],
            'uid' => $row['uid'],
            'old_timestamp' => $row['timestamp'],
            'minutes_old' => $row['minutes_old']
        ];
    }
    
    $expiredCount = count($expiredUIDs);
    
    if ($expiredCount === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Tidak ada UID expired yang perlu di-reset',
            'reset_count' => 0,
            'debug' => [
                'query_executed' => $checkSql,
                'check_condition' => 'timestamp < NOW() - INTERVAL 5 MINUTE'
            ]
        ], JSON_PRETTY_PRINT);
        
        error_log("[RESET_UID] No expired UIDs found");
        exit;
    }
    
    // Step 2: Extract IDs untuk update
    $uidIds = array_column($expiredUIDs, 'id');
    $uidIdsStr = implode(',', array_map('intval', $uidIds));
    
    // Step 3: Execute UPDATE
    $updateSql = "UPDATE uid_buffer 
                  SET timestamp = NOW(), 
                      updated_at = NOW()
                  WHERE id IN ($uidIdsStr)";
    
    error_log("[RESET_UID] Executing update: " . $updateSql);
    
    if (!$conn->query($updateSql)) {
        throw new Exception("Update failed: " . $conn->error);
    }
    
    $affectedRows = $conn->affected_rows;
    
    // Step 4: Log untuk audit trail
    $logDetails = [];
    foreach ($expiredUIDs as $uid) {
        $logDetails[] = "UID {$uid['uid']} (ID: {$uid['id']}, Expired: {$uid['minutes_old']} menit)";
        error_log("[UID RESET] {$uid['uid']} - Was {$uid['minutes_old']} minutes old");
    }
    
    // Step 5: Success response
    echo json_encode([
        'success' => true,
        'message' => "$affectedRows UID berhasil di-reset ke timestamp NOW()",
        'reset_count' => $affectedRows,
        'details' => $expiredUIDs,
        'summary' => [
            'total_expired_found' => $expiredCount,
            'total_reset' => $affectedRows,
            'oldest_uid_minutes' => $expiredUIDs[0]['minutes_old'] ?? 0,
            'timestamp_now' => date('Y-m-d H:i:s')
        ],
        'debug' => [
            'check_query' => $checkSql,
            'update_query' => $updateSql,
            'limit_applied' => $limit
        ]
    ], JSON_PRETTY_PRINT);
    
    error_log("[RESET_UID SUCCESS] Reset $affectedRows UID(s): " . implode(', ', array_column($expiredUIDs, 'uid')));
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    
    echo json_encode([
        'success' => false,
        'message' => 'Gagal reset UID: ' . $errorMessage,
        'error_type' => 'exception',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
    error_log("[RESET_UID ERROR] " . $errorMessage . " | Trace: " . $e->getTraceAsString());
}

$conn->close();
exit;
?>