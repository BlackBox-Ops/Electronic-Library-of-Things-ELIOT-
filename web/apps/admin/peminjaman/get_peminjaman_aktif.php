<?php
/**
 * API: Get Peminjaman Aktif (Monitoring)
 * Path: web/apps/includes/api/peminjaman/get_peminjaman_aktif.php
 * 
 * Features:
 * - Get all active loans today
 * - Support filtering by status
 * - Use view vw_peminjaman_aktif
 * - Include pagination
 * 
 * Input (GET params):
 * - status: 'all' | 'dipinjam' | 'telat' (default: 'all')
 * - date: 'today' | 'YYYY-MM-DD' (default: 'today')
 * - limit: int (default: 50)
 * - offset: int (default: 0)
 * 
 * Output: {
 *   "success": true/false,
 *   "data": [ ... ],
 *   "pagination": { total, limit, offset, has_more }
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

// Get query parameters
$status = $_GET['status'] ?? 'all';
$date = $_GET['date'] ?? 'today';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Validate limit
if ($limit > 100) $limit = 100;
if ($limit < 1) $limit = 50;

// Response structure
$response = [
    'success' => false,
    'data' => [],
    'pagination' => [
        'total' => 0,
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => false
    ]
];

try {
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    $types = '';

    // Filter by status
    if ($status !== 'all') {
        if (in_array($status, ['dipinjam', 'telat'])) {
            $whereConditions[] = "p.status = ?";
            $params[] = $status;
            $types .= 's';
        }
    }

    // Filter by date
    if ($date === 'today') {
        $whereConditions[] = "DATE(p.tanggal_pinjam) = CURDATE()";
    } else {
        // Validate date format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $whereConditions[] = "DATE(p.tanggal_pinjam) = ?";
            $params[] = $date;
            $types .= 's';
        }
    }

    $whereClause = !empty($whereConditions) ? 'AND ' . implode(' AND ', $whereConditions) : '';

    // Count total records
    $sqlCount = "
        SELECT COUNT(*) as total
        FROM ts_peminjaman p
        WHERE p.status IN ('dipinjam', 'telat')
          AND p.is_deleted = 0
          {$whereClause}
    ";

    if (!empty($params)) {
        $stmtCount = $conn->prepare($sqlCount);
        if (!empty($types)) {
            $stmtCount->bind_param($types, ...$params);
        }
        $stmtCount->execute();
        $countResult = $stmtCount->get_result()->fetch_assoc();
    } else {
        $countResult = $conn->query($sqlCount)->fetch_assoc();
    }

    $total = $countResult['total'] ?? 0;

    // Get paginated data using view
    $sql = "
        SELECT 
            id,
            kode_peminjaman,
            tanggal_pinjam,
            due_date,
            hari_tersisa,
            status_waktu,
            nama_peminjam,
            email_peminjam,
            no_identitas,
            judul_buku,
            isbn,
            kode_eksemplar,
            nama_staff,
            status,
            catatan
        FROM vw_peminjaman_aktif
        WHERE 1=1
          {$whereClause}
        ORDER BY tanggal_pinjam DESC
        LIMIT ? OFFSET ?
    ";

    $stmtData = $conn->prepare($sql);
    
    // Add limit and offset to params
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmtData->bind_param($types, ...$params);
    $stmtData->execute();
    $result = $stmtData->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Format dates
        $row['tanggal_pinjam_formatted'] = date('d M Y H:i', strtotime($row['tanggal_pinjam']));
        $row['due_date_formatted'] = date('d M Y', strtotime($row['due_date']));
        
        // Add status badge info
        $row['status_badge'] = [
            'dipinjam' => 'primary',
            'telat' => 'danger'
        ][$row['status']] ?? 'secondary';

        $row['waktu_badge'] = [
            'telat' => 'danger',
            'tepat waktu' => 'success'
        ][$row['status_waktu']] ?? 'warning';

        // Add urgency level
        if ($row['hari_tersisa'] <= 0) {
            $row['urgency'] = 'high';
        } elseif ($row['hari_tersisa'] <= 3) {
            $row['urgency'] = 'medium';
        } else {
            $row['urgency'] = 'low';
        }

        $data[] = $row;
    }

    // Build response
    $response['success'] = true;
    $response['data'] = $data;
    $response['pagination'] = [
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => ($offset + $limit) < $total,
        'current_count' => count($data)
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log('Get Peminjaman Aktif Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan server',
        'data' => []
    ]);
}
?>