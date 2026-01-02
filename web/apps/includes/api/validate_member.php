<?php
/**
 * API: Validate Member
 * Path: web/apps/includes/api/validate_member.php
 * 
 * @author ELIOT System
 * @version 1.1.0 - FINAL
 * @date 2026-01-02
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Configuration file not found',
        'debug' => ['config_path' => $configPath, 'exists' => false]
    ]);
    exit;
}

require_once $configPath;
debugLog('Config loaded');

// ============================================
// HELPER FUNCTIONS
// ============================================
function sendResponse($success, $data = null, $message = '', $errors = [], $httpCode = 200) {
    http_response_code($httpCode);
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if (!empty($errors)) {
        $response['validation'] = ['errors' => $errors];
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    debugLog('Response sent: ' . $message);
    exit;
}

// ============================================
// CHECK DATABASE
// ============================================
if (!isset($conn) || !$conn) {
    debugLog('ERROR: No database connection');
    sendResponse(false, null, 'Database connection failed', ['Cannot connect to database'], 500);
}

debugLog('Database connected');

// ============================================
// VALIDATE METHOD
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, 'Method not allowed', ['Only POST allowed'], 405);
}

// ============================================
// GET INPUT
// ============================================
$rawInput = file_get_contents('php://input');
debugLog('Raw input: ' . substr($rawInput, 0, 100));

if (empty($rawInput)) {
    sendResponse(false, null, 'Empty request body', ['No data received'], 400);
}

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    debugLog('JSON error: ' . json_last_error_msg());
    sendResponse(false, null, 'Invalid JSON', ['JSON decode failed: ' . json_last_error_msg()], 400);
}

$noIdentitas = isset($input['no_identitas']) ? trim(strip_tags($input['no_identitas'])) : '';
debugLog('no_identitas: ' . $noIdentitas);

// ============================================
// VALIDATE INPUT
// ============================================
if (empty($noIdentitas)) {
    sendResponse(false, null, 'Validation failed', ['No identitas tidak boleh kosong'], 400);
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
          AND u.role IN ('member', 'admin', 'staff') 
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
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendResponse(false, null, 'Member tidak ditemukan', 
            ['No identitas tidak terdaftar atau sudah dihapus'], 404);
    }
    
    $member = $result->fetch_assoc();
    $stmt->close();
    
    debugLog('Member found: ' . $member['nama']);
    
    // ============================================
    // BUSINESS VALIDATION
    // ============================================
    $errors = [];
    $kuotaTersisa = $member['max_peminjaman'] - $member['total_pinjam_aktif'];
    
    // 1. Status
    if ($member['status'] !== 'aktif') {
        $statusMap = [
            'nonaktif' => 'User tidak aktif',
            'suspended' => 'User di-suspend',
            'pending' => 'User pending approval'
        ];
        $errors[] = ($statusMap[$member['status']] ?? 'Status invalid') . '. Hubungi admin.';
    }
    
    // 2. Kuota (skip check untuk admin/staff - mereka unlimited)
    if ($member['role'] === 'member' && $kuotaTersisa <= 0) {
        $errors[] = "Kuota peminjaman habis ({$member['total_pinjam_aktif']}/{$member['max_peminjaman']}). Kembalikan buku dulu.";
    }
    
    // 3. Denda (skip untuk admin/staff)
    if ($member['role'] === 'member') {
        $maxDendaResult = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_denda_block'");
        $maxDenda = 50000;
        if ($maxDendaResult && $row = $maxDendaResult->fetch_assoc()) {
            $maxDenda = (int)$row['setting_value'];
        }
        
        if ($member['total_denda'] >= $maxDenda) {
            $errors[] = "Denda Rp " . number_format($member['total_denda'], 0, ',', '.') . 
                        ". Bayar dulu (max Rp " . number_format($maxDenda, 0, ',', '.') . ").";
        }
    }
    
    if (!empty($errors)) {
        sendResponse(false, null, 'Member tidak memenuhi syarat', $errors, 400);
    }
    
    // ============================================
    // SUCCESS
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
    
    sendResponse(true, $memberData, 'Member valid', [], 200);
    
} catch (Exception $e) {
    debugLog('EXCEPTION: ' . $e->getMessage());
    sendResponse(false, null, 'Server error', ['Internal error: ' . $e->getMessage()], 500);
}

if (isset($conn)) $conn->close();
?>