<?php
/**
 * API: Check Latest UID from RFID Scanner
 * Path: web/apps/includes/api/check_latest_uid.php
 * 
 * UC-3: Scan RFID untuk UID Book (Now Supports Multi-UID)
 * Endpoint ini dipanggil oleh frontend untuk mengambil UID terbaru yang di-scan
 * 
 * BUSINESS LOGIC:
 * 1. Ambil UID terbaru yang belum dilabel (is_labeled = 'no')
 * 2. Filter hanya yang jenis = 'pending' (baru di-scan dari hardware)
 * 3. Belum terdaftar di rt_book_uid (belum dipakai)
 * 4. Scan dalam 5 menit terakhir (untuk toleransi waktu)
 * 5. Support multi-UID dengan param ?limit=X (default: all)
 * 6. Saat ambil, reset timestamp untuk extend timeout
 * 7. Return JSON dengan success true/false, dan array uids jika multi
 */

require_once '../../../includes/config.php'; 

// Set header JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Get param limit (default null = all)
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;
    $limitSql = $limit ? "LIMIT $limit" : "";

    /**
     * QUERY OPTIMIZATION:
     * - Menggunakan LEFT JOIN untuk performance
     * - Index pada is_labeled, jenis, is_deleted sudah ada di schema
     * - ORDER DESC timestamp untuk ambil terbaru dulu
     */
    $sql = "SELECT 
                ub.id, 
                ub.uid, 
                ub.jenis, 
                ub.timestamp 
            FROM uid_buffer ub
            LEFT JOIN rt_book_uid rbu ON ub.id = rbu.uid_buffer_id AND rbu.is_deleted = 0
            WHERE ub.is_labeled = 'no' 
                AND ub.is_deleted = 0 
                AND ub.jenis = 'pending'
                AND rbu.id IS NULL
                AND ub.timestamp >= NOW() - INTERVAL 5 MINUTE 
            ORDER BY ub.timestamp DESC
            $limitSql";

    $result = $conn->query($sql);

    // UC-3: Success Scenario
    if (!$result) {
        throw new Exception("Database query error: " . $conn->error);
    }

    $uids = [];
    while ($row = $result->fetch_assoc()) {
        $uids[] = [
            'id' => intval($row['id']),
            'uid' => $row['uid'],
            'jenis' => $row['jenis'],
            'timestamp' => $row['timestamp']
        ];
    }

    if (!empty($uids)) {
        // ✓ UID DITEMUKAN - Reset timestamp untuk semua UID yang diambil
        $uidIds = array_column($uids, 'id');
        $uidIdsStr = implode(',', $uidIds);
        $updateSql = "UPDATE uid_buffer SET timestamp = NOW() WHERE id IN ($uidIdsStr)";
        if (!$conn->query($updateSql)) {
            throw new Exception("Failed to reset timestamp: " . $conn->error);
        }

        echo json_encode([
            'success' => true, 
            'uids' => $uids,
            'count' => count($uids),
            'message' => count($uids) > 1 ? 'Multiple UID berhasil ditemukan' : 'UID berhasil ditemukan'
        ], JSON_PRETTY_PRINT);
        
        // Log untuk debugging
        error_log("[RFID SCAN SUCCESS] Fetched " . count($uids) . " UIDs");

    } else {
        // UC-3: Failed Scenario
        // ✗ UID TIDAK DITEMUKAN
        echo json_encode([
            'success' => false, 
            'message' => 'UID tidak ditemukan. Pastikan tag RFID sudah di-scan pada reader.',
            'debug_info' => [
                'reason' => 'No pending UID found in last 5 minutes',
                'check_list' => [
                    'RFID tag sudah di-scan?',
                    'Hardware ESP32 terhubung?',
                    'UID masuk ke uid_buffer dengan jenis=pending?',
                    'UID belum pernah dipakai sebelumnya?'
                ]
            ]
        ], JSON_PRETTY_PRINT);
        
        error_log("[RFID SCAN FAILED] No available UID found");
    }
    
} catch (Exception $e) {
    // UC-3: Alternative Flow - Bug Handling
    // Handle semua error dengan response JSON yang konsisten
    $errorMessage = $e->getMessage();
    
    echo json_encode([
        'success' => false, 
        'message' => 'System error: ' . $errorMessage,
        'error_type' => 'exception',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
    // Log error untuk debugging
    error_log("[RFID API ERROR] " . $errorMessage);
}

$conn->close();
exit;