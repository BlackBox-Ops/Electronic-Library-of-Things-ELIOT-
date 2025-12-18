<?php
/**
 * API: Check Latest UID from RFID Scanner
 * Path: web/apps/includes/api/check_latest_uid.php
 * 
 * UC-3: Scan RFID untuk UID Book
 * Endpoint ini dipanggil oleh frontend untuk mengambil UID terbaru yang di-scan
 * 
 * BUSINESS LOGIC:
 * 1. Ambil UID terbaru yang belum dilabel (is_labeled = 'no')
 * 2. Filter hanya yang jenis = 'pending' (baru di-scan dari hardware)
 * 3. Belum terdaftar di rt_book_uid (belum dipakai)
 * 4. Scan dalam 5 menit terakhir (untuk toleransi waktu)
 * 5. Return JSON dengan success true/false
 */

require_once '../../../includes/config.php'; 

// Set header JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    /**
     * QUERY OPTIMIZATION:
     * - Menggunakan LEFT JOIN untuk performance
     * - Index pada is_labeled, jenis, is_deleted sudah ada di schema
     * - LIMIT 1 untuk speed
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
            LIMIT 1";

    $result = $conn->query($sql);

    // UC-3: Success Scenario
    if (!$result) {
        throw new Exception("Database query error: " . $conn->error);
    }

    if ($row = $result->fetch_assoc()) {
        // ✓ UID DITEMUKAN - Return success dengan data lengkap
        echo json_encode([
            'success' => true, 
            'id' => intval($row['id']), 
            'uid' => $row['uid'],
            'jenis' => $row['jenis'],
            'timestamp' => $row['timestamp'],
            'message' => 'UID berhasil ditemukan'
        ], JSON_PRETTY_PRINT);
        
        // Log untuk debugging (optional)
        error_log("[RFID SCAN SUCCESS] UID: {$row['uid']}, Buffer ID: {$row['id']}");
        
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