<?php
/**
 * API: Get Latest RFID UID
 * Path: web/apps/includes/api/get_latest_rfid.php
 * 
 * Mengambil UID terbaru berdasarkan timestamp untuk simulasi scan
 * 
 * @author ELIOT System
 * @version 1.0.0
 * @date 2026-01-06
 */

// ============================================
// CORS & HEADERS
// ============================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// ERROR HANDLING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/get_latest_rfid_error.log');

function debugLog($msg) {
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $msg);
}

debugLog('=== GET LATEST RFID API CALLED ===');

// ============================================
// LOAD CONFIG
// ============================================
$configPath = __DIR__ . '/../../../includes/config.php';
if (!file_exists($configPath)) {
    debugLog('ERROR: Config not found');
    echo json_encode([
        'success' => false,
        'code' => 'CONFIG_ERROR',
        'message' => 'Configuration file not found',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

require_once $configPath;

// ============================================
// HELPER FUNCTION
// ============================================
function sendResponse($success, $data = null, $message = '', $code = null) {
    http_response_code(200);
    echo json_encode([
        'success' => $success,
        'code' => $code,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    debugLog('Response: ' . $message . ' (Code: ' . $code . ')');
    exit;
}

// ============================================
// CHECK DATABASE
// ============================================
if (!isset($conn) || !$conn) {
    debugLog('ERROR: No database connection');
    sendResponse(false, null, 'Database connection failed', 'DB_ERROR');
}

// ============================================
// VALIDATE METHOD
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, 'Method not allowed. Use GET.', 'METHOD_NOT_ALLOWED');
}

// ============================================
// GET LATEST UID FROM DATABASE
// ============================================
try {
    // Ambil UID dengan timestamp terbaru (dalam 10 detik terakhir untuk keamanan)
    $sql = "
        SELECT 
            ub.id AS uid_buffer_id,
            ub.uid,
            ub.jenis,
            ub.is_labeled,
            ub.timestamp,
            TIMESTAMPDIFF(SECOND, ub.timestamp, NOW()) AS seconds_ago,
            
            rt.id AS rt_id,
            rt.book_id,
            rt.kode_eksemplar,
            rt.kondisi,
            
            b.judul_buku,
            b.isbn,
            b.eksemplar_tersedia,
            
            (SELECT COUNT(*) 
             FROM ts_peminjaman tp 
             WHERE tp.uid_buffer_id = ub.id 
             AND tp.status = 'dipinjam' 
             AND tp.is_deleted = 0) AS is_borrowed
            
        FROM uid_buffer ub
        LEFT JOIN rt_book_uid rt ON ub.id = rt.uid_buffer_id AND rt.is_deleted = 0
        LEFT JOIN books b ON rt.book_id = b.id AND b.is_deleted = 0
        
        WHERE ub.is_deleted = 0
          AND ub.jenis = 'book'
          AND TIMESTAMPDIFF(SECOND, ub.timestamp, NOW()) <= 30
        
        ORDER BY ub.timestamp DESC
        LIMIT 1
    ";
    
    debugLog('Executing query to get latest UID...');
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    if ($result->num_rows === 0) {
        debugLog('No recent UID found');
        sendResponse(false, null, 
            'Tidak ada scan RFID buku dalam 30 detik terakhir. Silakan update timestamp di database untuk simulasi.', 
            'NO_RECENT_SCAN'
        );
    }
    
    $data = $result->fetch_assoc();
    
    debugLog('Latest UID found: ' . $data['uid'] . ' (' . $data['seconds_ago'] . ' seconds ago)');
    
    // ============================================
    // VALIDATE UID TYPE
    // ============================================
    if ($data['jenis'] !== 'book') {
        debugLog('ERROR: UID type is not book: ' . $data['jenis']);
        sendResponse(false, null, 
            'RFID terbaru bukan untuk buku (Type: ' . $data['jenis'] . ')', 
            'UID_NOT_BOOK'
        );
    }
    
    // ============================================
    // CHECK IF UID IS LABELED
    // ============================================
    if ($data['is_labeled'] !== 'yes') {
        debugLog('ERROR: UID not labeled yet');
        sendResponse(false, null, 
            'RFID belum dilabeli ke buku. Silakan proses labeling terlebih dahulu.', 
            'UID_NOT_LABELED'
        );
    }
    
    // ============================================
    // CHECK IF BOOK DATA EXISTS
    // ============================================
    if (empty($data['book_id'])) {
        debugLog('ERROR: Book data not found for this UID');
        sendResponse(false, null, 
            'Data buku tidak ditemukan untuk RFID ini', 
            'BOOK_DATA_NOT_FOUND'
        );
    }
    
    // ============================================
    // SUCCESS RESPONSE
    // ============================================
    $responseData = [
        'uid_buffer_id' => (int)$data['uid_buffer_id'],
        'uid' => $data['uid'],
        'book_id' => (int)$data['book_id'],
        'kode_eksemplar' => $data['kode_eksemplar'],
        'kondisi' => $data['kondisi'],
        'judul_buku' => $data['judul_buku'],
        'isbn' => $data['isbn'],
        'eksemplar_tersedia' => (int)$data['eksemplar_tersedia'],
        'is_borrowed' => (int)$data['is_borrowed'] > 0,
        'scan_info' => [
            'timestamp' => $data['timestamp'],
            'seconds_ago' => (int)$data['seconds_ago']
        ]
    ];
    
    debugLog('SUCCESS: Latest book UID retrieved');
    sendResponse(true, $responseData, 'UID terbaru berhasil diambil (' . $data['seconds_ago'] . ' detik yang lalu)', 'SUCCESS');
    
} catch (Exception $e) {
    debugLog('EXCEPTION: ' . $e->getMessage());
    sendResponse(false, null, 'Server error: ' . $e->getMessage(), 'SERVER_ERROR');
}

if (isset($conn)) $conn->close();
?>