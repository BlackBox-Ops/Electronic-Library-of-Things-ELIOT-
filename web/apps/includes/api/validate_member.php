<?php
/**
 * API: Validate Member
 * Path: web/apps/includes/api/validate_member.php
 * 
 * @author ELIOT System
 * @version 1.4.0 - Fixed HTTP Status Code Issue
 * @date 2026-01-04
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
ini_set('error_log', __DIR__ . '/validate_member_error.log');

function debugLog($msg) {
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $msg);
}

debugLog('=== API Called ===');
debugLog('Method: ' . $_SERVER['REQUEST_METHOD']);

// ============================================
// LOAD CONFIG
// ============================================
$configPath = __DIR__ . '/../../../includes/config.php';
debugLog('Config path: ' . $configPath);

if (!file_exists($configPath)) {
    debugLog('ERROR: Config not found at ' . $configPath);
    http_response_code(200); // Always 200, error in JSON
    echo json_encode([
        'success' => false,
        'code' => 'CONFIG_ERROR',
        'message' => 'Configuration file not found',
        'data' => null,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

require_once $configPath;
debugLog('Config loaded');

// ============================================
// HELPER FUNCTIONS
// ============================================
function sendResponse($success, $data = null, $message = '', $errors = [], $code = null) {
    http_response_code(200); // ✅ ALWAYS 200 - Error differentiation by JSON
    
    $response = [
        'success' => $success,
        'code' => $code,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (!empty($errors)) {
        $response['validation'] = ['errors' => $errors];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    debugLog('Response sent: ' . $message . ' (Code: ' . $code . ')');
    exit;
}

// ============================================
// CHECK DATABASE
// ============================================
if (!isset($conn) || !$conn) {
    debugLog('ERROR: No database connection');
    sendResponse(false, null, 'Database connection failed', 
        ['Cannot connect to database'], 'DB_ERROR');
}

debugLog('Database connected');

// ============================================
// VALIDATE METHOD
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, 'Method not allowed', 
        ['Only POST allowed'], 'METHOD_NOT_ALLOWED');
}

// ============================================
// GET INPUT
// ============================================
$rawInput = file_get_contents('php://input');
debugLog('Raw input: ' . substr($rawInput, 0, 100));

if (empty($rawInput)) {
    sendResponse(false, null, 'Empty request body', 
        ['No data received'], 'EMPTY_INPUT');
}

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    debugLog('JSON error: ' . json_last_error_msg());
    sendResponse(false, null, 'Invalid JSON', 
        ['JSON decode failed: ' . json_last_error_msg()], 'INVALID_JSON');
}

$noIdentitas = isset($input['no_identitas']) ? trim(strip_tags($input['no_identitas'])) : '';
debugLog('no_identitas: ' . $noIdentitas);

// ============================================
// VALIDATE INPUT
// ============================================
if (empty($noIdentitas)) {
    sendResponse(false, null, 'Validation failed', 
        ['No identitas tidak boleh kosong'], 'EMPTY_NO_IDENTITAS');
}

// ============================================
// QUERY MEMBER
// ============================================
try {
    $sql = "
        SELECT 
            u.id, u.nama, u.email, u.no_identitas, u.no_telepon, u.alamat,
            u.role, u.status, u.max_peminjaman, u.foto_profil,
            (SELECT COUNT(*) FROM ts_peminjaman p 
             WHERE p.user_id = u.id AND p.status = 'dipinjam' AND p.is_deleted = 0) as total_pinjam_aktif,
            (SELECT COALESCE(SUM(d.jumlah_denda), 0) FROM ts_denda d
             WHERE d.user_id = u.id AND d.status_pembayaran = 'belum_dibayar' AND d.is_deleted = 0) as total_denda
        FROM users u
        WHERE u.no_identitas = ? 
          AND u.is_deleted = 0
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param('s', $noIdentitas);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    debugLog('Query executed, rows: ' . $result->num_rows);
    
    // ============================================
    // CHECK IF USER EXISTS
    // ============================================
    if ($result->num_rows === 0) {
        $stmt->close();
        debugLog('User not found');
        
        // ✅ HTTP 200 dengan error code NOT_REGISTERED
        sendResponse(false, null, 'No identitas belum terdaftar', 
            ['Nomor identitas tidak ditemukan dalam sistem'], 
            'NOT_REGISTERED'
        );
    }
    
    $member = $result->fetch_assoc();
    $stmt->close();
    
    debugLog('Member found: ' . $member['nama'] . ' (Role: ' . $member['role'] . ')');
    
    // ============================================
    // ROLE CHECK: HANYA MEMBER YANG BOLEH PINJAM
    // ============================================
    if ($member['role'] !== 'member') {
        debugLog('ERROR: User is not member (Role: ' . $member['role'] . ')');
        
        // ✅ HTTP 200 dengan error code ADMIN_STAFF_NOT_ALLOWED
        sendResponse(false, null, 'Admin dan Staff tidak dapat meminjam buku', 
            ['Hanya member yang dapat meminjam buku'], 
            'ADMIN_STAFF_NOT_ALLOWED'
        );
    }
    
    // ============================================
    // BUSINESS VALIDATION - HANYA UNTUK MEMBER
    // ============================================
    $errors = [];
    $kuotaTersisa = $member['max_peminjaman'] - $member['total_pinjam_aktif'];
    
    // 1. Status Check
    if ($member['status'] !== 'aktif') {
        $statusMap = [
            'nonaktif' => 'User tidak aktif',
            'suspended' => 'User di-suspend',
            'pending' => 'User pending approval'
        ];
        $errors[] = ($statusMap[$member['status']] ?? 'Status invalid') . '. Hubungi admin.';
    }
    
    // 2. Kuota Check
    if ($kuotaTersisa <= 0) {
        $errors[] = "Kuota peminjaman habis ({$member['total_pinjam_aktif']}/{$member['max_peminjaman']}). Kembalikan buku terlebih dahulu.";
    }
    
    // 3. Denda Check
    $maxDendaResult = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_denda_block'");
    $maxDenda = 50000;
    if ($maxDendaResult && $row = $maxDendaResult->fetch_assoc()) {
        $maxDenda = (int)$row['setting_value'];
    }
    
    if ($member['total_denda'] >= $maxDenda) {
        $errors[] = "Total denda Rp " . number_format($member['total_denda'], 0, ',', '.') . 
                    ". Bayar denda terlebih dahulu (maksimal Rp " . number_format($maxDenda, 0, ',', '.') . ").";
    }
    
    // Jika ada error validasi
    if (!empty($errors)) {
        debugLog('Validation failed: ' . implode(', ', $errors));
        
        // ✅ HTTP 200 dengan error code VALIDATION_FAILED
        sendResponse(false, null, 'Member tidak memenuhi syarat', 
            $errors, 'VALIDATION_FAILED');
    }
    
    // ============================================
    // SUCCESS RESPONSE
    // ============================================
    $memberData = [
        'id' => (int)$member['id'],
        'nama' => $member['nama'],
        'email' => $member['email'],
        'no_identitas' => $member['no_identitas'],
        'no_telepon' => $member['no_telepon'] ?? '-',
        'alamat' => $member['alamat'] ?? '-',
        'role' => $member['role'],
        'status' => $member['status'],
        'max_peminjaman' => (int)$member['max_peminjaman'],
        'foto_profil' => $member['foto_profil'] ?? null,
        'kuota' => [
            'total' => (int)$member['max_peminjaman'],
            'terpakai' => (int)$member['total_pinjam_aktif'],
            'tersisa' => $kuotaTersisa
        ],
        'denda' => [
            'total' => (float)$member['total_denda'],
            'formatted' => 'Rp ' . number_format($member['total_denda'], 0, ',', '.')
        ]
    ];
    
    debugLog('SUCCESS: Member valid and ready to borrow');
    
    // ✅ HTTP 200 dengan success code
    sendResponse(true, $memberData, 'Member valid dan siap meminjam', 
        [], 'SUCCESS');
    
} catch (Exception $e) {
    debugLog('EXCEPTION: ' . $e->getMessage());
    
    // ✅ HTTP 200 dengan error code SERVER_ERROR
    sendResponse(false, null, 'Server error', 
        ['Internal error: ' . $e->getMessage()], 'SERVER_ERROR');
}

if (isset($conn)) $conn->close();
?>