<?php
/**
 * API: Validate Book for Peminjaman
 * Path: web/apps/includes/api/peminjaman/validate_book.php
 * 
 * Features:
 * - Validate UID buku dari RFID
 * - Check stok & kondisi eksemplar
 * - Check if already borrowed
 * - Return complete book info + preview due date
 * 
 * Input: { 
 *   "uid": "RFID_UID",
 *   "member_id": int (for context)
 * }
 * 
 * Output: {
 *   "success": true/false,
 *   "validation": { "valid": true/false, "errors": [] },
 *   "data": { book_info + eksemplar_info }
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
$memberId = $input['member_id'] ?? null;

// Validation response structure
$response = [
    'success' => false,
    'validation' => [
        'valid' => false,
        'errors' => []
    ],
    'data' => null
];

try {
    // Input validation
    if (empty($uid)) {
        $response['validation']['errors'][] = 'UID tidak boleh kosong';
        echo json_encode($response);
        exit;
    }

    if (empty($memberId)) {
        $response['validation']['errors'][] = 'Member ID tidak ditemukan. Silakan scan ulang kartu member.';
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

    // Step 2: Check if UID is book type
    if ($uidResult['jenis'] !== 'book') {
        $response['validation']['errors'][] = 'UID ini bukan tag buku (tipe: ' . $uidResult['jenis'] . ')';
        echo json_encode($response);
        exit;
    }

    // Step 3: Check if UID is labeled
    if ($uidResult['is_labeled'] !== 'yes') {
        $response['validation']['errors'][] = 'Tag buku belum dilabel atau belum diaktifkan';
        echo json_encode($response);
        exit;
    }

    $uidBufferId = $uidResult['id'];

    // Step 4: Get Book Eksemplar from rt_book_uid
    $stmtBookUID = $conn->prepare("
        SELECT 
            rbu.book_id,
            rbu.kode_eksemplar,
            rbu.kondisi,
            rbu.tanggal_registrasi
        FROM rt_book_uid rbu
        WHERE rbu.uid_buffer_id = ? 
          AND rbu.is_deleted = 0
    ");
    $stmtBookUID->bind_param('i', $uidBufferId);
    $stmtBookUID->execute();
    $eksemplarResult = $stmtBookUID->get_result()->fetch_assoc();

    if (!$eksemplarResult) {
        $response['validation']['errors'][] = 'Tag buku belum didaftarkan ke buku manapun';
        echo json_encode($response);
        exit;
    }

    $bookId = $eksemplarResult['book_id'];
    $kodeEksemplar = $eksemplarResult['kode_eksemplar'];
    $kondisi = $eksemplarResult['kondisi'];

    // Step 5: Validate Kondisi Eksemplar
    if ($kondisi === 'hilang') {
        $response['validation']['errors'][] = "Eksemplar ini tercatat hilang. Hubungi admin.";
        echo json_encode($response);
        exit;
    }

    if ($kondisi === 'rusak_berat') {
        $response['validation']['errors'][] = "Eksemplar ini rusak berat dan tidak dapat dipinjam.";
        echo json_encode($response);
        exit;
    }

    // Step 6: Check if eksemplar is currently borrowed
    $stmtBorrowed = $conn->prepare("
        SELECT 
            p.id,
            p.kode_peminjaman,
            p.tanggal_pinjam,
            p.due_date,
            u.nama as peminjam
        FROM ts_peminjaman p
        JOIN users u ON p.user_id = u.id
        WHERE p.uid_buffer_id = ? 
          AND p.status IN ('dipinjam', 'telat')
          AND p.is_deleted = 0
    ");
    $stmtBorrowed->bind_param('i', $uidBufferId);
    $stmtBorrowed->execute();
    $borrowedResult = $stmtBorrowed->get_result()->fetch_assoc();

    if ($borrowedResult) {
        $response['validation']['errors'][] = "Eksemplar ini sedang dipinjam oleh: {$borrowedResult['peminjam']}";
        $response['validation']['errors'][] = "Kode: {$borrowedResult['kode_peminjaman']}";
        $response['validation']['errors'][] = "Jatuh tempo: " . date('d M Y', strtotime($borrowedResult['due_date']));
        echo json_encode($response);
        exit;
    }

    // Step 7: Get Book Details with Stock
    $stmtBook = $conn->prepare("
        SELECT 
            b.id,
            b.judul_buku,
            b.isbn,
            b.publisher_id,
            b.jumlah_halaman,
            b.kategori,
            b.jumlah_eksemplar,
            b.eksemplar_tersedia,
            b.tahun_terbit,
            b.lokasi_rak,
            b.deskripsi,
            b.cover_image,
            p.nama_penerbit
        FROM books b
        LEFT JOIN publishers p ON b.publisher_id = p.id
        WHERE b.id = ? 
          AND b.is_deleted = 0
    ");
    $stmtBook->bind_param('i', $bookId);
    $stmtBook->execute();
    $book = $stmtBook->get_result()->fetch_assoc();

    if (!$book) {
        $response['validation']['errors'][] = 'Data buku tidak ditemukan';
        echo json_encode($response);
        exit;
    }

    // Step 8: Check Global Stock
    if ($book['eksemplar_tersedia'] <= 0) {
        $response['validation']['errors'][] = "Buku '{$book['judul_buku']}' tidak tersedia (stok habis)";
        echo json_encode($response);
        exit;
    }

    // Step 9: Get Authors
    $stmtAuthors = $conn->prepare("
        SELECT a.nama_pengarang, rba.peran
        FROM rt_book_author rba
        JOIN authors a ON rba.author_id = a.id
        WHERE rba.book_id = ? 
          AND rba.is_deleted = 0
        ORDER BY 
            CASE rba.peran
                WHEN 'penulis_utama' THEN 1
                WHEN 'co_author' THEN 2
                WHEN 'editor' THEN 3
                ELSE 4
            END
    ");
    $stmtAuthors->bind_param('i', $bookId);
    $stmtAuthors->execute();
    $authorsResult = $stmtAuthors->get_result();
    
    $authors = [];
    $pengarangUtama = null;
    while ($author = $authorsResult->fetch_assoc()) {
        $authors[] = $author;
        if ($author['peran'] === 'penulis_utama' && !$pengarangUtama) {
            $pengarangUtama = $author['nama_pengarang'];
        }
    }

    // Step 10: Get Categories (if any)
    $stmtCategories = $conn->prepare("
        SELECT c.nama_kategori
        FROM rt_book_category rbc
        JOIN categories c ON rbc.category_id = c.id
        WHERE rbc.book_id = ? 
          AND rbc.is_deleted = 0
    ");
    $stmtCategories->bind_param('i', $bookId);
    $stmtCategories->execute();
    $categoriesResult = $stmtCategories->get_result();
    
    $categories = [];
    while ($cat = $categoriesResult->fetch_assoc()) {
        $categories[] = $cat['nama_kategori'];
    }

    // Step 11: Get System Settings for Due Date Preview
    $stmtSettings = $conn->prepare("
        SELECT setting_value 
        FROM system_settings 
        WHERE setting_key = 'durasi_peminjaman_default'
    ");
    $stmtSettings->execute();
    $settingResult = $stmtSettings->get_result()->fetch_assoc();
    $durasiHari = $settingResult['setting_value'] ?? 7;

    $dueDatePreview = date('d M Y', strtotime("+{$durasiHari} days"));

    // Step 12: SUCCESS - Build Response
    $response['success'] = true;
    $response['validation']['valid'] = true;
    $response['data'] = [
        // Book Master Data
        'id' => (int)$book['id'],
        'judul_buku' => $book['judul_buku'],
        'isbn' => $book['isbn'],
        'pengarang' => $pengarangUtama ?? 'Anonim',
        'pengarang_lengkap' => $authors,
        'penerbit' => $book['nama_penerbit'],
        'tahun_terbit' => $book['tahun_terbit'],
        'jumlah_halaman' => (int)$book['jumlah_halaman'],
        'kategori' => $book['kategori'],
        'categories' => $categories,
        'lokasi_rak' => $book['lokasi_rak'],
        'deskripsi' => $book['deskripsi'],
        'cover_image' => $book['cover_image'],
        
        // Stock Info
        'jumlah_eksemplar' => (int)$book['jumlah_eksemplar'],
        'eksemplar_tersedia' => (int)$book['eksemplar_tersedia'],
        
        // Eksemplar Spesifik
        'uid' => $uid,
        'uid_buffer_id' => (int)$uidBufferId,
        'kode_eksemplar' => $kodeEksemplar,
        'kondisi' => $kondisi,
        'kondisi_label' => str_replace('_', ' ', ucfirst($kondisi)),
        'tanggal_registrasi' => $eksemplarResult['tanggal_registrasi'],
        
        // Preview Info
        'durasi_hari' => (int)$durasiHari,
        'due_date_preview' => $dueDatePreview,
        'tanggal_pinjam_preview' => date('d M Y')
    ];
    
    $response['message'] = 'Buku valid dan tersedia untuk dipinjam';

    // Add warnings if kondisi not perfect
    $warnings = [];
    if ($kondisi === 'rusak_ringan') {
        $warnings[] = "Eksemplar ini dalam kondisi rusak ringan. Periksa sebelum dipinjamkan.";
    }
    if (!empty($warnings)) {
        $response['warnings'] = $warnings;
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log('Validate Book Error: ' . $e->getMessage());
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