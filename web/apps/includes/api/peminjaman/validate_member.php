<?php
/**
 * API: Validate Member for Peminjaman
 * Path: web/apps/includes/api/peminjaman/validate_member.php
 * 
 * Perbaikan:
 * - Tambah validasi rt_book_uid untuk cek UID assignment
 * - Improve error messages
 * - Add more detailed validation
 * 
 * Input: { "uid": "RFID_UID" }
 * Output: {
 *   "success": true/false,
 *   "validation": { "valid": true/false, "errors": [] },
 *   "data": { member_info },
 *   "slots_available": int
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
$uid = $input['uid'] ?? null;

// Validation response structure
$response = [
    'success' => false,
    'validation' => [
        'valid' => false,
        'errors' => []
    ],
    'data' => null,
    'slots_available' => 0
];

try {
    // Input validation
    if (empty($uid)) {
        $response['validation']['errors'][] = 'UID tidak boleh kosong';
        echo json_encode($response);
        exit;
    }

    // Step 1: Get UID Buffer
    $stmtUID = $conn->prepare("
        SELECT id, jenis, is_labeled
        FROM uid_buffer 
        WHERE uid = ? AND is_deleted = 0
    ");
    $stmtUID->bind_param('s', $uid);
    $stmtUID->execute();
    $uidResult = $stmtUID->get_result()->fetch_assoc();

    if (!$uidResult) {
        $response['validation']['errors'][] = 'UID tidak terdaftar di sistem';
        echo json_encode($response);
        exit;
    }

    // Step 2: Check if UID is user type
    if ($uidResult['jenis'] !== 'user') {
        $response['validation']['errors'][] = 'UID ini bukan kartu member (tipe: ' . $uidResult['jenis'] . ')';
        echo json_encode($response);
        exit;
    }

    // Step 3: Check if UID is labeled
    if ($uidResult['is_labeled'] !== 'yes') {
        $response['validation']['errors'][] = 'Kartu member belum diaktifkan';
        echo json_encode($response);
        exit;
    }

    $uidBufferId = $uidResult['id'];

    // Step 4: Get User from rt_user_uid
    $stmtUserUID = $conn->prepare("
        SELECT user_id, tanggal_aktif, tanggal_nonaktif
        FROM rt_user_uid 
        WHERE uid_buffer_id = ? 
          AND status = 'aktif' 
          AND is_deleted = 0
          AND (tanggal_nonaktif IS NULL OR tanggal_nonaktif > CURDATE())
    ");
    $stmtUserUID->bind_param('i', $uidBufferId);
    $stmtUserUID->execute();
    $userUIDResult = $stmtUserUID->get_result()->fetch_assoc();

    if (!$userUIDResult) {
        $response['validation']['errors'][] = 'Kartu member tidak aktif atau sudah expired';
        echo json_encode($response);
        exit;
    }

    $userId = $userUIDResult['user_id'];

    // Step 5: Get User Details
    $stmtUser = $conn->prepare("
        SELECT 
            id, nama, email, no_identitas, no_telepon, 
            role, status, max_peminjaman, foto_profil
        FROM users
        WHERE id = ? AND is_deleted = 0
    ");
    $stmtUser->bind_param('i', $userId);
    $stmtUser->execute();
    $user = $stmtUser->get_result()->fetch_assoc();

    if (!$user) {
        $response['validation']['errors'][] = 'Data member tidak ditemukan';
        echo json_encode($response);
        exit;
    }

    // Step 6: Validate User Status
    if ($user['status'] !== 'aktif') {
        $statusLabel = [
            'nonaktif' => 'tidak aktif',
            'suspended' => 'suspended',
            'pending' => 'pending aktivasi'
        ][$user['status']] ?? $user['status'];
        
        $response['validation']['errors'][] = "Member {$statusLabel}. Hubungi admin untuk aktivasi.";
        echo json_encode($response);
        exit;
    }

    // Step 7: Check Active Loans
    $stmtActiveLoan = $conn->prepare("
        SELECT COUNT(*) as jumlah_aktif
        FROM ts_peminjaman
        WHERE user_id = ? 
          AND status IN ('dipinjam', 'telat')
          AND is_deleted = 0
    ");
    $stmtActiveLoan->bind_param('i', $userId);
    $stmtActiveLoan->execute();
    $activeLoan = $stmtActiveLoan->get_result()->fetch_assoc();
    $jumlahPinjamAktif = $activeLoan['jumlah_aktif'] ?? 0;

    // Step 8: Check Unpaid Fines
    $stmtDenda = $conn->prepare("
        SELECT COALESCE(SUM(jumlah_denda), 0) as total_denda
        FROM ts_denda
        WHERE user_id = ? 
          AND status_pembayaran = 'belum_dibayar'
          AND is_deleted = 0
    ");
    $stmtDenda->bind_param('i', $userId);
    $stmtDenda->execute();
    $dendaResult = $stmtDenda->get_result()->fetch_assoc();
    $totalDenda = $dendaResult['total_denda'] ?? 0;

    // Step 9: Get System Settings
    $stmtSettings = $conn->prepare("
        SELECT setting_value 
        FROM system_settings 
        WHERE setting_key = 'max_denda_block'
    ");
    $stmtSettings->execute();
    $settingResult = $stmtSettings->get_result()->fetch_assoc();
    $maxDendaBlock = $settingResult['setting_value'] ?? 50000;

    // Step 10: Validate Denda
    if ($totalDenda >= $maxDendaBlock) {
        $response['validation']['errors'][] = 'Member diblokir: Denda mencapai Rp ' . number_format($totalDenda, 0, ',', '.');
        $response['validation']['errors'][] = 'Silakan bayar denda terlebih dahulu di bagian administrasi.';
        echo json_encode($response);
        exit;
    }

    // Step 11: Calculate Available Slots
    $slotsAvailable = $user['max_peminjaman'] - $jumlahPinjamAktif;

    if ($slotsAvailable <= 0) {
        $response['validation']['errors'][] = 'Member sudah mencapai batas maksimal peminjaman (' . $user['max_peminjaman'] . ' buku).';
        $response['validation']['errors'][] = 'Kembalikan buku terlebih dahulu untuk meminjam buku baru.';
        echo json_encode($response);
        exit;
    }

    // Step 12: SUCCESS - Build Response
    $response['success'] = true;
    $response['validation']['valid'] = true;
    $response['data'] = [
        'id' => (int)$user['id'],
        'nama' => $user['nama'],
        'email' => $user['email'],
        'no_identitas' => $user['no_identitas'],
        'no_telepon' => $user['no_telepon'],
        'role' => $user['role'],
        'status' => $user['status'],
        'max_peminjaman' => (int)$user['max_peminjaman'],
        'jumlah_pinjam_aktif' => (int)$jumlahPinjamAktif,
        'total_denda' => (float)$totalDenda,
        'foto_profil' => $user['foto_profil'],
        'uid_buffer_id' => (int)$uidBufferId,
        'tanggal_aktif_kartu' => $userUIDResult['tanggal_aktif']
    ];
    $response['slots_available'] = (int)$slotsAvailable;
    $response['message'] = 'Member valid dan dapat melakukan peminjaman';

    // Add warnings if any
    $warnings = [];
    if ($totalDenda > 0) {
        $warnings[] = "Member memiliki denda Rp " . number_format($totalDenda, 0, ',', '.');
    }
    if ($jumlahPinjamAktif > 0) {
        $warnings[] = "Member sedang meminjam {$jumlahPinjamAktif} buku.";
    }
    if (!empty($warnings)) {
        $response['warnings'] = $warnings;
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log('Validate Member Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan server',
        'validation' => [
            'valid' => false,
            'errors' => ['Internal server error. Silakan coba lagi.']
        ]
    ]);
}
?>