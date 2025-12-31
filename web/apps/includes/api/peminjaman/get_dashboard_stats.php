<?php
/**
 * API: Get Dashboard Statistics
 * Path: web/apps/includes/api/peminjaman/get_dashboard_stats.php
 * 
 * Features:
 * - Get today's loans count
 * - Get will overdue count (3 days)
 * - Get overdue count
 * - Get members with unpaid fines
 * 
 * Output: {
 *   "success": true/false,
 *   "data": {
 *     "total_today": int,
 *     "will_overdue": int,
 *     "overdue_now": int,
 *     "member_with_fines": int
 *   }
 * }
 */

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../../config.php';

// Only accept GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Response structure
$response = [
    'success' => false,
    'data' => [
        'total_today' => 0,
        'will_overdue' => 0,
        'overdue_now' => 0,
        'member_with_fines' => 0
    ]
];

try {
    // Get all statistics in one query
    $sql = "
        SELECT 
            (SELECT COUNT(*) 
             FROM ts_peminjaman 
             WHERE DATE(tanggal_pinjam) = CURDATE() 
               AND is_deleted = 0
            ) as total_today,
            
            (SELECT COUNT(*) 
             FROM vw_peminjaman_aktif 
             WHERE hari_tersisa <= 3 
               AND hari_tersisa > 0
            ) as will_overdue,
            
            (SELECT COUNT(*) 
             FROM vw_peminjaman_aktif 
             WHERE status_waktu = 'telat'
            ) as overdue_now,
            
            (SELECT COUNT(DISTINCT user_id) 
             FROM ts_denda 
             WHERE status_pembayaran = 'belum_dibayar' 
               AND is_deleted = 0
            ) as member_with_fines
    ";

    $result = $conn->query($sql);
    
    if ($result) {
        $stats = $result->fetch_assoc();
        
        $response['success'] = true;
        $response['data'] = [
            'total_today' => (int)($stats['total_today'] ?? 0),
            'will_overdue' => (int)($stats['will_overdue'] ?? 0),
            'overdue_now' => (int)($stats['overdue_now'] ?? 0),
            'member_with_fines' => (int)($stats['member_with_fines'] ?? 0)
        ];

        // Add additional info
        $response['timestamp'] = date('Y-m-d H:i:s');
        $response['server_time'] = date('H:i:s');
    } else {
        throw new Exception('Failed to fetch statistics');
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log('Get Dashboard Stats Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan server',
        'data' => [
            'total_today' => 0,
            'will_overdue' => 0,
            'overdue_now' => 0,
            'member_with_fines' => 0
        ]
    ]);
}
?>