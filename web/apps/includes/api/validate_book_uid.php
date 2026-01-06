<?php
/**
 * API: Validate Book UID
 * Path: web/apps/includes/api/validate_book_uid.php
 * 
 * Validates RFID UID untuk buku sebelum redirect ke peminjaman.php
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
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
ini_set('error_log', __DIR__ . '/validate_book_uid_error.log');

function debugLog($msg) {
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $msg);
}

debugLog('=== VALIDATE BOOK UID API CALLED ===');

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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, 'Method not allowed', 'METHOD_NOT_ALLOWED');
}

// ============================================
// GET INPUT
// ============================================
$rawInput = file_get_contents('php://input');
debugLog('Raw input: ' . substr($rawInput, 0, 100));

if (empty($rawInput)) {
    sendResponse(false, null, 'Empty request body', 'EMPTY_INPUT');
}

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    debugLog('JSON error: ' . json_last_error_msg());
    sendResponse(false, null, 'Invalid JSON', 'INVALID_JSON');
}

$rfidUid = isset($input['rfid_uid']) ? trim($input['rfid_uid']) : '';
debugLog('rfid_uid: ' . $rfidUid);

// ============================================
// VALIDATE INPUT
// ============================================
if (empty($rfidUid)) {
    sendResponse(false, null, 'RFID UID tidak boleh kosong', 'EMPTY_RFID');
}

// ============================================
// VALIDATE UID IN DATABASE
// ============================================
try {
    $sql = "
        SELECT 
            ub.id AS uid_buffer_id,
            ub.uid,
            ub.jenis,
            ub.is_labeled,
            
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
        
        WHERE ub.uid = ? AND ub.is_deleted = 0
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $rfidUid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        debugLog('ERROR: UID not found in database');
        sendResponse(false, null, 'RFID tidak terdaftar dalam sistem', 'UID_NOT_FOUND');
    }
    
    $data = $result->fetch_assoc();
    $stmt->close();
    
    debugLog('UID found: ' . json_encode($data));
    
    // ============================================
    // VALIDATE UID TYPE
    // ============================================
    if ($data['jenis'] !== 'book') {
        debugLog('ERROR: UID type is not book: ' . $data['jenis']);
        sendResponse(false, null, 
            'RFID ini bukan untuk buku (Type: ' . $data['jenis'] . ')', 
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
        'is_borrowed' => (int)$data['is_borrowed'] > 0
    ];
    
    debugLog('SUCCESS: Book UID validated');
    sendResponse(true, $responseData, 'UID valid dan dapat diproses', 'SUCCESS');
    
} catch (Exception $e) {
    debugLog('EXCEPTION: ' . $e->getMessage());
    sendResponse(false, null, 'Server error: ' . $e->getMessage(), 'SERVER_ERROR');
}

if (isset($conn)) $conn->close();
?>