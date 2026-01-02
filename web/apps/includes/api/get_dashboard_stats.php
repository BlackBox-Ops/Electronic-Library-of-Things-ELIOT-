<?php
/**
 * API: Get Dashboard Statistics
 * Path: web/apps/includes/api/get_dashboard_stats.php
 * 
 * Fungsi:
 * - Return statistics untuk dashboard peminjaman
 * - Total peminjaman hari ini
 * - Peminjaman yang akan jatuh tempo (≤3 hari)
 * - Peminjaman yang telat
 * - Member dengan denda
 * 
 * @author ELIOT System
 * @version 1.0.1 - FIXED
 * @date 2026-01-02
 */

// ============================================
// CORS & HEADERS
// ============================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// INITIALIZATION
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/get_dashboard_stats_error.log');

// FIXED: Path ke config.php
// Dari: web/apps/includes/api/get_dashboard_stats.php
// Ke: web/includes/config.php
// Naik 3 level: ../../../
require_once __DIR__ . '/../../../includes/config.php';

// ============================================
// HELPER FUNCTION
// ============================================
function sendResponse($success, $data = null, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// CHECK DATABASE CONNECTION
// ============================================
if (!isset($conn)) {
    error_log('[API Dashboard Stats] Database connection not available');
    sendResponse(false, null, 'Database connection failed', 500);
}

// ============================================
// VALIDATE REQUEST METHOD
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, 'Method not allowed', 405);
}

// ============================================
// FETCH STATISTICS
// ============================================
try {
    $statsQuery = $conn->query("
        SELECT 
            -- Peminjaman hari ini
            (SELECT COUNT(*) 
             FROM ts_peminjaman 
             WHERE DATE(tanggal_pinjam) = CURDATE() 
               AND is_deleted = 0
            ) as total_today,
            
            -- Akan jatuh tempo (≤3 hari, >0 hari)
            (SELECT COUNT(*) 
             FROM vw_peminjaman_aktif 
             WHERE hari_tersisa <= 3 
               AND hari_tersisa > 0
            ) as will_overdue,
            
            -- Telat pengembalian
            (SELECT COUNT(*) 
             FROM vw_peminjaman_aktif 
             WHERE status_waktu = 'telat'
            ) as overdue_now,
            
            -- Member dengan denda belum dibayar
            (SELECT COUNT(DISTINCT user_id) 
             FROM ts_denda 
             WHERE status_pembayaran = 'belum_dibayar' 
               AND is_deleted = 0
            ) as member_with_fines
    ");
    
    if (!$statsQuery) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $stats = $statsQuery->fetch_assoc();
    
    // Format response
    $data = [
        'total_today' => (int)($stats['total_today'] ?? 0),
        'will_overdue' => (int)($stats['will_overdue'] ?? 0),
        'overdue_now' => (int)($stats['overdue_now'] ?? 0),
        'member_with_fines' => (int)($stats['member_with_fines'] ?? 0),
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    sendResponse(true, $data, 'Statistics fetched successfully', 200);
    
} catch (Exception $e) {
    error_log('[API Dashboard Stats] Error: ' . $e->getMessage());
    sendResponse(false, null, 'Internal server error: ' . $e->getMessage(), 500);
}

// ============================================
// CLOSE CONNECTION
// ============================================
if (isset($conn)) {
    $conn->close();
}
?>