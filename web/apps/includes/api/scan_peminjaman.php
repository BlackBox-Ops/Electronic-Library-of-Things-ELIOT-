<?php
/**
 * API: Scan RFID untuk Peminjaman Buku (Updated)
 * Path: web/apps/includes/api/scan_peminjaman.php
 * 
 * Updated to support direct uid_buffer_id param
 * 
 * @author ELIOT System
 * @version 1.1.0
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
ini_set('error_log', __DIR__ . '/scan_peminjaman_error.log');

function debugLog($msg) {
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $msg);
}

debugLog('=== SCAN PEMINJAMAN API CALLED ===');

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
debugLog('Raw input: ' . substr($rawInput, 0, 200));

if (empty($rawInput)) {
    sendResponse(false, null, 'Empty request body', 'EMPTY_INPUT');
}

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    debugLog('JSON error: ' . json_last_error_msg());
    sendResponse(false, null, 'Invalid JSON', 'INVALID_JSON');
}

// Extract parameters
$rfidUid = isset($input['rfid_uid']) ? trim($input['rfid_uid']) : '';
$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$staffId = isset($input['staff_id']) ? (int)$input['staff_id'] : 0;
$durasiHari = isset($input['durasi_hari']) ? (int)$input['durasi_hari'] : 7;
$catatan = isset($input['catatan']) ? trim($input['catatan']) : '';

// ✅ NEW: Support direct uid_buffer_id and book_id
$directUidBufferId = isset($input['uid_buffer_id']) ? (int)$input['uid_buffer_id'] : 0;
$directBookId = isset($input['book_id']) ? (int)$input['book_id'] : 0;

debugLog("Input - RFID: $rfidUid, User: $userId, Staff: $staffId, Durasi: $durasiHari, Direct UID: $directUidBufferId");

// ============================================
// VALIDATE INPUT
// ============================================
if ($userId <= 0) {
    sendResponse(false, null, 'User ID tidak valid', 'INVALID_USER_ID');
}

if ($staffId <= 0) {
    sendResponse(false, null, 'Staff ID tidak valid', 'INVALID_STAFF_ID');
}

// ============================================
// DETERMINE UID BUFFER ID
// ============================================
$uidBufferId = 0;

if ($directUidBufferId > 0) {
    // ✅ Direct call from peminjaman.php
    debugLog('Using direct uid_buffer_id: ' . $directUidBufferId);
    $uidBufferId = $directUidBufferId;
    
} elseif (!empty($rfidUid)) {
    // Original RFID scan flow
    debugLog('Looking up RFID: ' . $rfidUid);
    
    try {
        $sql = "SELECT id, uid, jenis, is_labeled 
                FROM uid_buffer 
                WHERE uid = ? AND is_deleted = 0 
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $rfidUid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            debugLog('ERROR: RFID not found');
            sendResponse(false, null, 'RFID tidak terdaftar dalam sistem', 'RFID_NOT_FOUND');
        }
        
        $uidData = $result->fetch_assoc();
        $stmt->close();
        
        debugLog('UID found: ' . json_encode($uidData));
        
        if ($uidData['jenis'] !== 'book') {
            debugLog('ERROR: UID is not a book');
            sendResponse(false, null, 'RFID ini bukan untuk buku', 'RFID_NOT_BOOK');
        }
        
        $uidBufferId = (int)$uidData['id'];
        
    } catch (Exception $e) {
        debugLog('EXCEPTION in UID validation: ' . $e->getMessage());
        sendResponse(false, null, 'Error validasi RFID', 'UID_VALIDATION_ERROR');
    }
    
} else {
    sendResponse(false, null, 'RFID UID atau UID Buffer ID diperlukan', 'MISSING_UID');
}

// ============================================
// GET BOOK INFO LENGKAP
// ============================================
try {
    $sql = "
        SELECT 
            b.id AS book_id,
            b.judul_buku,
            b.isbn,
            b.jumlah_halaman,
            b.kategori,
            b.tahun_terbit,
            b.lokasi_rak,
            b.deskripsi,
            b.cover_image,
            b.keterangan,
            b.jumlah_eksemplar,
            b.eksemplar_tersedia,
            
            rt.id AS rt_id,
            rt.kode_eksemplar,
            rt.kondisi,
            rt.tanggal_registrasi,
            
            p.id AS publisher_id,
            p.nama_penerbit,
            p.alamat AS publisher_alamat,
            p.no_telepon AS publisher_telepon,
            
            GROUP_CONCAT(
                DISTINCT CONCAT(
                    a.nama_pengarang, '|', rba.peran
                ) SEPARATOR ';;'
            ) AS authors_data,
            
            (SELECT COUNT(*) FROM ts_peminjaman tp 
             WHERE tp.uid_buffer_id = rt.uid_buffer_id 
             AND tp.status = 'dipinjam' 
             AND tp.is_deleted = 0) AS is_borrowed
            
        FROM rt_book_uid rt
        INNER JOIN books b ON rt.book_id = b.id AND b.is_deleted = 0
        LEFT JOIN publishers p ON b.publisher_id = p.id AND p.is_deleted = 0
        LEFT JOIN rt_book_author rba ON b.id = rba.book_id AND rba.is_deleted = 0
        LEFT JOIN authors a ON rba.author_id = a.id AND a.is_deleted = 0
        
        WHERE rt.uid_buffer_id = ?
          AND rt.is_deleted = 0
        
        GROUP BY 
            b.id, rt.id, p.id
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $uidBufferId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        debugLog('ERROR: Book not found for this UID');
        sendResponse(false, null, 'Buku tidak ditemukan untuk UID ini', 'BOOK_NOT_FOUND');
    }
    
    $bookData = $result->fetch_assoc();
    $stmt->close();
    
    debugLog('Book found: ' . $bookData['judul_buku']);
    
} catch (Exception $e) {
    debugLog('EXCEPTION in book query: ' . $e->getMessage());
    sendResponse(false, null, 'Error mengambil data buku', 'BOOK_QUERY_ERROR');
}

// ============================================
// VALIDASI BUKU
// ============================================
if ($bookData['kondisi'] !== 'baik') {
    debugLog('ERROR: Book condition is not good: ' . $bookData['kondisi']);
    sendResponse(false, null, 
        'Kondisi buku: ' . strtoupper($bookData['kondisi']) . '. Tidak dapat dipinjam.', 
        'BOOK_CONDITION_BAD'
    );
}

if ($bookData['eksemplar_tersedia'] <= 0) {
    debugLog('ERROR: No available copies');
    sendResponse(false, null, 
        'Semua eksemplar sedang dipinjam. Eksemplar tersedia: 0', 
        'NO_COPIES_AVAILABLE'
    );
}

if ($bookData['is_borrowed'] > 0) {
    debugLog('ERROR: This copy is already borrowed');
    sendResponse(false, null, 
        'Eksemplar ini sedang dipinjam oleh member lain', 
        'COPY_ALREADY_BORROWED'
    );
}

// ============================================
// PROCESS AUTHORS DATA
// ============================================
$authors = [];
if (!empty($bookData['authors_data'])) {
    $authorsRaw = explode(';;', $bookData['authors_data']);
    foreach ($authorsRaw as $authorRaw) {
        $parts = explode('|', $authorRaw);
        if (count($parts) === 2) {
            $authors[] = [
                'nama' => $parts[0],
                'peran' => $parts[1]
            ];
        }
    }
}

// ============================================
// CALL STORED PROCEDURE
// ============================================
try {
    $sql = "CALL sp_process_peminjaman_v2(?, ?, ?, ?, ?, @result, @peminjaman_id)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'iiiii', 
        $userId, 
        $bookData['book_id'], 
        $uidBufferId, 
        $staffId, 
        $durasiHari
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Stored procedure execution failed: ' . $stmt->error);
    }
    
    $stmt->close();
    
    // Get OUT parameters
    $result = $conn->query("SELECT @result AS result, @peminjaman_id AS peminjaman_id");
    $spResult = $result->fetch_assoc();
    
    debugLog('SP Result: ' . $spResult['result']);
    debugLog('Peminjaman ID: ' . $spResult['peminjaman_id']);
    
    if (strpos($spResult['result'], 'SUCCESS') !== 0) {
        $errorMsg = str_replace('ERROR: ', '', $spResult['result']);
        debugLog('SP Error: ' . $errorMsg);
        sendResponse(false, null, $errorMsg, 'SP_ERROR');
    }
    
    $peminjamanId = (int)$spResult['peminjaman_id'];
    
    // ✅ Update catatan if provided
    if (!empty($catatan)) {
        $updateSql = "UPDATE ts_peminjaman SET catatan = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('si', $catatan, $peminjamanId);
        $updateStmt->execute();
        $updateStmt->close();
    }
    
} catch (Exception $e) {
    debugLog('EXCEPTION in SP call: ' . $e->getMessage());
    sendResponse(false, null, 'Error memproses peminjaman: ' . $e->getMessage(), 'SP_EXCEPTION');
}

// ============================================
// GET PEMINJAMAN DETAIL
// ============================================
try {
    $sql = "
        SELECT 
            p.id,
            p.kode_peminjaman,
            p.tanggal_pinjam,
            p.due_date,
            DATE_FORMAT(p.tanggal_pinjam, '%d/%m/%Y %H:%i') AS tanggal_pinjam_formatted,
            DATE_FORMAT(p.due_date, '%d/%m/%Y') AS due_date_formatted,
            p.status,
            p.catatan,
            
            u.nama AS nama_peminjam,
            u.email AS email_peminjam,
            u.no_identitas,
            
            s.nama AS nama_staff
            
        FROM ts_peminjaman p
        INNER JOIN users u ON p.user_id = u.id
        INNER JOIN users s ON p.staff_id = s.id
        WHERE p.id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $peminjamanId);
    $stmt->execute();
    $result = $stmt->get_result();
    $peminjamanDetail = $result->fetch_assoc();
    $stmt->close();
    
} catch (Exception $e) {
    debugLog('EXCEPTION getting peminjaman detail: ' . $e->getMessage());
    $peminjamanDetail = ['id' => $peminjamanId];
}

// ============================================
// BUILD RESPONSE DATA
// ============================================
$responseData = [
    'peminjaman' => [
        'id' => $peminjamanId,
        'kode_peminjaman' => $peminjamanDetail['kode_peminjaman'] ?? 'N/A',
        'tanggal_pinjam' => $peminjamanDetail['tanggal_pinjam'] ?? null,
        'due_date' => $peminjamanDetail['due_date'] ?? null,
        'tanggal_pinjam_formatted' => $peminjamanDetail['tanggal_pinjam_formatted'] ?? '',
        'due_date_formatted' => $peminjamanDetail['due_date_formatted'] ?? '',
        'status' => $peminjamanDetail['status'] ?? 'dipinjam',
        'durasi_hari_kerja' => $durasiHari,
        'catatan' => $peminjamanDetail['catatan'] ?? null
    ],
    
    'buku' => [
        'id' => (int)$bookData['book_id'],
        'judul' => $bookData['judul_buku'],
        'isbn' => $bookData['isbn'],
        'kategori' => $bookData['kategori'],
        'tahun_terbit' => $bookData['tahun_terbit'],
        'jumlah_halaman' => (int)$bookData['jumlah_halaman'],
        'lokasi_rak' => $bookData['lokasi_rak'],
        'deskripsi' => $bookData['deskripsi'],
        'keterangan' => $bookData['keterangan'],
        'cover_image' => $bookData['cover_image'],
        
        'eksemplar' => [
            'kode' => $bookData['kode_eksemplar'],
            'kondisi' => $bookData['kondisi'],
            'tanggal_registrasi' => $bookData['tanggal_registrasi']
        ],
        
        'penerbit' => [
            'id' => (int)$bookData['publisher_id'],
            'nama' => $bookData['nama_penerbit'],
            'alamat' => $bookData['publisher_alamat'],
            'telepon' => $bookData['publisher_telepon']
        ],
        
        'pengarang' => $authors,
        
        'stok' => [
            'total_eksemplar' => (int)$bookData['jumlah_eksemplar'],
            'tersedia_sebelum' => (int)$bookData['eksemplar_tersedia'],
            'tersedia_sekarang' => (int)$bookData['eksemplar_tersedia'] - 1
        ]
    ],
    
    'peminjam' => [
        'nama' => $peminjamanDetail['nama_peminjam'] ?? 'N/A',
        'email' => $peminjamanDetail['email_peminjam'] ?? 'N/A',
        'no_identitas' => $peminjamanDetail['no_identitas'] ?? 'N/A'
    ],
    
    'staff' => [
        'nama' => $peminjamanDetail['nama_staff'] ?? 'N/A'
    ]
];

// ============================================
// SUCCESS RESPONSE
// ============================================
debugLog('SUCCESS: Peminjaman completed');
sendResponse(
    true, 
    $responseData, 
    'Peminjaman berhasil diproses. ' . $spResult['result'], 
    'SUCCESS'
);

if (isset($conn)) $conn->close();
?>