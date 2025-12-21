<?php
/**
 * API: Check Latest UID from RFID Scanner
 * Path: web/apps/includes/api/check_latest_uid.php
 * 
 * UC-3: Scan RFID untuk UID Book (Supports Multi-UID)
 * 
 * PERBAIKAN:
 * - Logging query lengkap & jumlah row
 * - Debug info lebih detail untuk frontend
 * - Sanitasi limit lebih ketat
 * - Tambah CORS header (opsional)
 * - Response lebih informatif
 * - Suggestion index untuk performa
 */

require_once '../../../includes/config.php'; 

// Tambah CORS jika diperlukan (bisa dihapus jika tidak cross-origin)
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Sanitasi dan validasi limit
    $limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : null; // Max 50 untuk cegah overload
    $limitSql = $limit ? "LIMIT " . intval($limit) : "";

    // Query utama - dengan index suggestion
    // SUGGESTION: Tambah index di MySQL:
    // CREATE INDEX idx_uid_buffer_pending ON uid_buffer(jenis, is_labeled, is_deleted, timestamp);
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

    error_log("[check_latest_uid] Executing query: " . $sql);

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Database query error: " . $conn->error . " | Query: " . $sql);
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

    $found_count = count($uids);

    if ($found_count > 0) {
        // Reset timestamp untuk extend timeout (hanya jika ada data)
        $uidIds = array_column($uids, 'id');
        $uidIdsStr = implode(',', array_map('intval', $uidIds)); // Sanitasi

        if (!empty($uidIdsStr)) {
            $updateSql = "UPDATE uid_buffer SET timestamp = NOW() WHERE id IN ($uidIdsStr)";
            if (!$conn->query($updateSql)) {
                error_log("[check_latest_uid] Failed to reset timestamp: " . $conn->error);
                // Tidak throw error, karena UID sudah terambil, hanya timeout extend gagal
            } else {
                error_log("[check_latest_uid] Timestamp reset untuk " . count($uidIds) . " UID");
            }
        }

        echo json_encode([
            'success' => true, 
            'uids' => $uids,
            'count' => $found_count,
            'message' => $found_count > 1 
                ? "$found_count UID berhasil ditemukan dan siap digunakan" 
                : "1 UID berhasil ditemukan dan siap digunakan",
            'debug' => [
                'query_executed' => $sql,
                'uids_fetched' => $found_count,
                'timestamp_reset' => !empty($uidIdsStr)
            ]
        ], JSON_PRETTY_PRINT);
        
        error_log("[RFID SCAN SUCCESS] Fetched $found_count UIDs: " . implode(', ', array_column($uids, 'uid')));

    } else {
        // Gagal: Tidak ada UID valid
        echo json_encode([
            'success' => false, 
            'message' => 'UID tidak ditemukan atau sudah expired.',
            'debug_info' => [
                'reason' => 'Tidak ada UID pending yang valid dalam 5 menit terakhir',
                'query_executed' => $sql,
                'suggestions' => [
                    'Pastikan RFID tag sudah di-scan pada reader ESP32',
                    'Cek apakah data masuk ke tabel uid_buffer (jenis=pending, is_labeled=no)',
                    'Cronjob invalidate_old_uid.py sudah berjalan? (untuk bersihkan UID expired)',
                    'UID belum pernah digunakan sebelumnya',
                    'Waktu sistem server dan ESP32 sinkron?'
                ],
                'timestamp_now' => date('Y-m-d H:i:s'),
                'time_limit_check' => 'timestamp >= NOW() - INTERVAL 5 MINUTE'
            ]
        ], JSON_PRETTY_PRINT);
        
        error_log("[RFID SCAN FAILED] No valid UID found | Query: " . substr($sql, 0, 200) . "...");
    }
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    echo json_encode([
        'success' => false, 
        'message' => 'System error: Terjadi kesalahan pada server.',
        'error_details' => $errorMessage,
        'error_type' => 'exception',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
    error_log("[RFID API ERROR] " . $errorMessage);
}

$conn->close();
exit;
?>