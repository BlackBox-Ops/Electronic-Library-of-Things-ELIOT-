<?php
/**
 * API: Get Peminjaman Aktif
 * Path: web/apps/includes/api/get_peminjaman_aktif.php
 * 
 * Fungsi:
 * - Return data peminjaman aktif untuk monitoring table
 * - Support filter by status
 * - Support filter by date
 * - Include kategori member (mahasiswa/dosen/umum)
 * 
 * @author ELIOT System
 * @version 1.0.0
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
error_reporting(0);
ini_set('display_errors', 0);

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
// VALIDATE REQUEST METHOD
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, 'Method not allowed', 405);
}

// ============================================
// GET QUERY PARAMETERS
// ============================================
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$date = isset($_GET['date']) ? $_GET['date'] : 'today';

// ============================================
// BUILD QUERY
// ============================================
try {
    $sql = "
        SELECT 
            p.id,
            p.kode_peminjaman,
            p.tanggal_pinjam,
            p.due_date,
            p.status,
            
            -- Member info
            u.id as user_id,
            u.nama as nama_peminjam,
            u.no_identitas,
            u.role as kategori_member,
            
            -- Book info
            b.judul_buku,
            rb.kode_eksemplar,
            
            -- Staff info
            s.nama as nama_staff,
            
            -- Calculated fields
            GREATEST(0, DATEDIFF(p.due_date, CURDATE())) as hari_tersisa,
            IF(DATEDIFF(CURDATE(), p.due_date) > 0, 'telat', 'tepat waktu') as status_waktu
            
        FROM ts_peminjaman p
        INNER JOIN users u ON p.user_id = u.id AND u.is_deleted = 0
        INNER JOIN books b ON p.book_id = b.id AND b.is_deleted = 0
        INNER JOIN rt_book_uid rb ON p.uid_buffer_id = rb.uid_buffer_id AND rb.is_deleted = 0
        INNER JOIN users s ON p.staff_id = s.id AND s.is_deleted = 0
        WHERE p.is_deleted = 0
    ";
    
    // Filter by status
    if ($status !== 'all') {
        if ($status === 'dipinjam') {
            $sql .= " AND p.status = 'dipinjam' AND DATEDIFF(p.due_date, CURDATE()) >= 0";
        } elseif ($status === 'telat') {
            $sql .= " AND p.status = 'dipinjam' AND DATEDIFF(CURDATE(), p.due_date) > 0";
        }
    } else {
        $sql .= " AND p.status IN ('dipinjam', 'telat')";
    }
    
    // Filter by date
    if ($date === 'today') {
        $sql .= " AND DATE(p.tanggal_pinjam) = CURDATE()";
    }
    
    $sql .= " ORDER BY p.tanggal_pinjam DESC, p.created_at DESC";
    
    // Execute query
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    // ============================================
    // FORMAT RESPONSE
    // ============================================
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        // Tentukan urgency level
        $hariTersisa = (int)$row['hari_tersisa'];
        $urgency = 'low';
        $waktuBadge = 'success';
        
        if ($row['status_waktu'] === 'telat') {
            $urgency = 'high';
            $waktuBadge = 'danger';
        } elseif ($hariTersisa <= 1) {
            $urgency = 'high';
            $waktuBadge = 'danger';
        } elseif ($hariTersisa <= 3) {
            $urgency = 'medium';
            $waktuBadge = 'warning';
        }
        
        // Format dates
        $tanggalPinjam = date('d/m/Y H:i', strtotime($row['tanggal_pinjam']));
        $dueDate = date('d/m/Y', strtotime($row['due_date']));
        
        // Mapping kategori member
        $kategoriMember = 'Umum';
        if (stripos($row['no_identitas'], '113') === 0) {
            $kategoriMember = 'Mahasiswa';
        } elseif (stripos($row['no_identitas'], '111') === 0 || 
                  stripos($row['no_identitas'], '112') === 0) {
            $kategoriMember = 'Dosen';
        }
        
        $data[] = [
            'id' => (int)$row['id'],
            'kode_peminjaman' => $row['kode_peminjaman'],
            'tanggal_pinjam' => $row['tanggal_pinjam'],
            'tanggal_pinjam_formatted' => $tanggalPinjam,
            'due_date' => $row['due_date'],
            'due_date_formatted' => $dueDate,
            'status' => $row['status'],
            
            // Member
            'user_id' => (int)$row['user_id'],
            'nama_peminjam' => $row['nama_peminjam'],
            'no_identitas' => $row['no_identitas'],
            'kategori_member' => $kategoriMember,
            
            // Book
            'judul_buku' => $row['judul_buku'],
            'kode_eksemplar' => $row['kode_eksemplar'],
            
            // Staff
            'nama_staff' => $row['nama_staff'],
            
            // Status
            'hari_tersisa' => $hariTersisa,
            'status_waktu' => $row['status_waktu'],
            'urgency' => $urgency,
            'waktu_badge' => $waktuBadge
        ];
    }
    
    sendResponse(true, $data, 'Data fetched successfully', 200);
    
} catch (Exception $e) {
    error_log('[API Peminjaman Aktif] Error: ' . $e->getMessage());
    sendResponse(false, null, 'Internal server error', 500);
}

// ============================================
// CLOSE CONNECTION
// ============================================
if (isset($conn)) {
    $conn->close();
}
?>