<?php
/**
 * API: Process Peminjaman (Batch)
 * Path: web/apps/includes/api/peminjaman/process_peminjaman.php
 * 
 * Perbaikan:
 * - Add UID validation before insert
 * - Add row locking on stock update
 * - Add rt_book_uid validation
 * - Better error handling
 * - More detailed logging
 * 
 * Input: {
 *   "member_id": int,
 *   "books": [
 *     { "book_id": int, "uid_buffer_id": int, "kode_eksemplar": string }
 *   ],
 *   "durasi_hari": int
 * }
 * 
 * Output: {
 *   "success": true/false,
 *   "message": string,
 *   "data": {
 *     "kode_batch": string,
 *     "total_books": int,
 *     "details": []
 *   }
 * }
 */

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../../config.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$memberId = $input['member_id'] ?? null;
$books = $input['books'] ?? [];
$durasiHari = $input['durasi_hari'] ?? 7;

// Get staff ID from session
session_start();
$staffId = $_SESSION['userId'] ?? null;

// Response structure
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // ========== INPUT VALIDATION ==========
    
    if (empty($memberId)) {
        $response['message'] = 'Member ID tidak boleh kosong';
        echo json_encode($response);
        exit;
    }

    if (empty($books) || !is_array($books)) {
        $response['message'] = 'Data buku tidak valid';
        echo json_encode($response);
        exit;
    }

    if (count($books) === 0) {
        $response['message'] = 'Tidak ada buku yang dipilih';
        echo json_encode($response);
        exit;
    }

    if (count($books) > 3) {
        $response['message'] = 'Maksimal 3 buku per transaksi';
        echo json_encode($response);
        exit;
    }

    if (empty($staffId)) {
        $response['message'] = 'Staff ID tidak ditemukan. Silakan login ulang';
        echo json_encode($response);
        exit;
    }

    // ========== PRE-VALIDATION: Check Member Status ==========
    
    $stmtMember = $conn->prepare("
        SELECT id, nama, status, max_peminjaman
        FROM users
        WHERE id = ? AND is_deleted = 0
    ");
    $stmtMember->bind_param('i', $memberId);
    $stmtMember->execute();
    $member = $stmtMember->get_result()->fetch_assoc();

    if (!$member) {
        $response['message'] = 'Member tidak ditemukan';
        echo json_encode($response);
        exit;
    }

    if ($member['status'] !== 'aktif') {
        $response['message'] = 'Member tidak aktif. Status: ' . $member['status'];
        echo json_encode($response);
        exit;
    }

    // Check current active loans
    $stmtActiveLoan = $conn->prepare("
        SELECT COUNT(*) as jumlah
        FROM ts_peminjaman
        WHERE user_id = ? 
          AND status IN ('dipinjam', 'telat')
          AND is_deleted = 0
    ");
    $stmtActiveLoan->bind_param('i', $memberId);
    $stmtActiveLoan->execute();
    $activeLoan = $stmtActiveLoan->get_result()->fetch_assoc();
    $currentLoans = $activeLoan['jumlah'] ?? 0;

    $slotsAvailable = $member['max_peminjaman'] - $currentLoans;
    
    if (count($books) > $slotsAvailable) {
        $response['message'] = "Member hanya memiliki {$slotsAvailable} slot tersedia. Tidak dapat meminjam " . count($books) . " buku.";
        echo json_encode($response);
        exit;
    }

    // ========== PRE-VALIDATION: Validate All UIDs ==========
    
    foreach ($books as $index => $book) {
        $bookId = $book['book_id'] ?? null;
        $uidBufferId = $book['uid_buffer_id'] ?? null;
        $kodeEksemplar = $book['kode_eksemplar'] ?? null;

        if (!$bookId || !$uidBufferId || !$kodeEksemplar) {
            throw new Exception("Data buku #{$index} tidak lengkap");
        }

        // Validate UID
        $stmtUID = $conn->prepare("
            SELECT ub.jenis, rbu.book_id, rbu.kondisi
            FROM uid_buffer ub
            JOIN rt_book_uid rbu ON ub.id = rbu.uid_buffer_id
            WHERE ub.id = ? 
              AND ub.is_deleted = 0
              AND rbu.is_deleted = 0
        ");
        $stmtUID->bind_param('i', $uidBufferId);
        $stmtUID->execute();
        $uidData = $stmtUID->get_result()->fetch_assoc();

        if (!$uidData) {
            throw new Exception("UID buffer ID {$uidBufferId} tidak valid atau tidak terdaftar");
        }

        if ($uidData['jenis'] !== 'book') {
            throw new Exception("UID buffer ID {$uidBufferId} bukan tipe buku");
        }

        if ($uidData['book_id'] != $bookId) {
            throw new Exception("UID tidak sesuai dengan buku yang dipilih (expected: {$bookId}, got: {$uidData['book_id']})");
        }

        if (in_array($uidData['kondisi'], ['hilang', 'rusak_berat'])) {
            throw new Exception("Eksemplar {$kodeEksemplar} dalam kondisi {$uidData['kondisi']} dan tidak dapat dipinjam");
        }

        // Check if already borrowed
        $stmtBorrowed = $conn->prepare("
            SELECT kode_peminjaman
            FROM ts_peminjaman
            WHERE uid_buffer_id = ?
              AND status IN ('dipinjam', 'telat')
              AND is_deleted = 0
        ");
        $stmtBorrowed->bind_param('i', $uidBufferId);
        $stmtBorrowed->execute();
        $borrowed = $stmtBorrowed->get_result()->fetch_assoc();

        if ($borrowed) {
            throw new Exception("Eksemplar {$kodeEksemplar} sedang dipinjam (kode: {$borrowed['kode_peminjaman']})");
        }
    }

    // ========== START TRANSACTION ==========
    
    $conn->begin_transaction();

    // Generate Batch Code: BATCH-YYYYMMDD-XXXX
    $datePart = date('Ymd');
    $stmtCount = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM ts_peminjaman 
        WHERE DATE(tanggal_pinjam) = CURDATE()
          AND is_deleted = 0
    ");
    $stmtCount->execute();
    $countResult = $stmtCount->get_result()->fetch_assoc();
    $sequence = str_pad($countResult['total'] + 1, 4, '0', STR_PAD_LEFT);
    $kodeBatch = "BATCH-{$datePart}-{$sequence}";

    // Calculate dates
    $tanggalPinjam = date('Y-m-d H:i:s');
    $dueDate = date('Y-m-d H:i:s', strtotime("+{$durasiHari} days"));

    // ========== PROCESS EACH BOOK ==========
    
    $details = [];
    $itemSequence = 1;

    foreach ($books as $book) {
        $bookId = $book['book_id'];
        $uidBufferId = $book['uid_buffer_id'];
        $kodeEksemplar = $book['kode_eksemplar'];

        // Generate Kode Peminjaman: BATCH-YYYYMMDD-XXXX-01
        $kodePeminjaman = "{$kodeBatch}-" . str_pad($itemSequence, 2, '0', STR_PAD_LEFT);

        // Insert to ts_peminjaman
        $stmtInsert = $conn->prepare("
            INSERT INTO ts_peminjaman (
                kode_peminjaman,
                user_id,
                book_id,
                uid_buffer_id,
                staff_id,
                tanggal_pinjam,
                due_date,
                status,
                catatan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'dipinjam', ?)
        ");

        $catatan = "Batch: {$kodeBatch}";
        $stmtInsert->bind_param(
            'siiissss',
            $kodePeminjaman,
            $memberId,
            $bookId,
            $uidBufferId,
            $staffId,
            $tanggalPinjam,
            $dueDate,
            $catatan
        );

        if (!$stmtInsert->execute()) {
            throw new Exception("Gagal menyimpan peminjaman untuk {$kodeEksemplar}: " . $stmtInsert->error);
        }

        // Update eksemplar_tersedia with ROW LOCK
        $stmtUpdate = $conn->prepare("
            UPDATE books 
            SET eksemplar_tersedia = eksemplar_tersedia - 1,
                updated_at = NOW()
            WHERE id = ? 
              AND eksemplar_tersedia > 0
              AND is_deleted = 0
        ");
        $stmtUpdate->bind_param('i', $bookId);
        
        if (!$stmtUpdate->execute()) {
            throw new Exception("Gagal update stok buku {$kodeEksemplar}: " . $stmtUpdate->error);
        }

        if ($stmtUpdate->affected_rows === 0) {
            throw new Exception("Stok buku {$kodeEksemplar} tidak mencukupi atau buku tidak ditemukan");
        }

        // Get book title for response
        $stmtBook = $conn->prepare("SELECT judul_buku FROM books WHERE id = ?");
        $stmtBook->bind_param('i', $bookId);
        $stmtBook->execute();
        $bookData = $stmtBook->get_result()->fetch_assoc();

        $details[] = [
            'kode_peminjaman' => $kodePeminjaman,
            'buku' => $bookData['judul_buku'] ?? 'Unknown',
            'kode' => $kodeEksemplar,
            'book_id' => $bookId,
            'uid_buffer_id' => $uidBufferId
        ];

        $itemSequence++;
    }

    // ========== LOG AKTIVITAS ==========
    
    $logDesc = "Peminjaman batch {$kodeBatch} oleh {$member['nama']} - " . count($books) . " buku";
    $stmtLog = $conn->prepare("
        INSERT INTO ts_log_aktivitas (
            user_id, modul, aksi, deskripsi, ip_address, user_agent
        ) VALUES (?, 'peminjaman', 'create', ?, ?, ?)
    ");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
    $stmtLog->bind_param('isss', $staffId, $logDesc, $ipAddress, $userAgent);
    
    if (!$stmtLog->execute()) {
        error_log("Warning: Failed to log activity for {$kodeBatch}");
        // Don't throw error, continue transaction
    }

    // ========== COMMIT TRANSACTION ==========
    
    $conn->commit();

    // ========== SUCCESS RESPONSE ==========
    
    $response['success'] = true;
    $response['message'] = 'Peminjaman berhasil diproses';
    $response['data'] = [
        'kode_batch' => $kodeBatch,
        'total_books' => count($books),
        'member_nama' => $member['nama'],
        'member_nim' => $member['no_identitas'] ?? '-',
        'tanggal_pinjam' => date('d M Y H:i', strtotime($tanggalPinjam)),
        'due_date' => date('d M Y', strtotime($dueDate)),
        'durasi_hari' => (int)$durasiHari,
        'details' => $details,
        'staff_id' => $staffId
    ];

    echo json_encode($response);

} catch (Exception $e) {
    // ========== ROLLBACK ON ERROR ==========
    
    if (isset($conn)) {
        $conn->rollback();
    }
    
    error_log('Process Peminjaman Error: ' . $e->getMessage());
    error_log('Input data: ' . json_encode($input));
    
    http_response_code(500);
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    echo json_encode($response);
}
?>