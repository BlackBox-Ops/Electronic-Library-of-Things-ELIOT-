<?php
/**
 * AddEksemplarController.php
 * Path: web/apps/controllers/AddEksemplarController.php
 * 
 * Tambah eksemplar baru ke buku existing via scan RFID
 * Generate kode eksemplar lanjut, insert rt_book_uid, update jumlah di books
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$book_id = intval($data['book_id'] ?? 0);
$new_eksemplar = $data['new_eksemplar'] ?? [];

if ($book_id <= 0 || empty($new_eksemplar)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit;
}

$conn->begin_transaction();

try {
    $tgl_reg = date('Y-m-d');

    foreach ($new_eksemplar as $eks) {
        $uid_buffer_id = intval($eks['uid_buffer_id']);
        $kode_eksemplar = $conn->real_escape_string($eks['kode_eksemplar']);
        $kondisi = $conn->real_escape_string($eks['kondisi'] ?? 'baik');

        // Insert rt_book_uid
        $insertEks = "INSERT INTO rt_book_uid (book_id, uid_buffer_id, kode_eksemplar, kondisi, tanggal_registrasi) 
                        VALUES ($book_id, $uid_buffer_id, '$kode_eksemplar', '$kondisi', '$tgl_reg')";
        if (!$conn->query($insertEks)) {
            throw new Exception("Gagal tambah eksemplar: " . $conn->error);
        }

        // Update uid_buffer jadi 'book'
        $updateBuffer = "UPDATE uid_buffer SET jenis = 'book', is_labeled = 'yes', updated_at = NOW() WHERE id = $uid_buffer_id";
        if (!$conn->query($updateBuffer)) {
            throw new Exception("Gagal update buffer UID: " . $conn->error);
        }
    }

    // Update jumlah_eksemplar & tersedia di books
    $countStmt = $conn->prepare("SELECT COUNT(*) as total, 
                                    SUM(CASE WHEN kondisi NOT IN ('rusak_berat', 'hilang') THEN 1 ELSE 0 END) as tersedia 
                                    FROM rt_book_uid WHERE book_id = ? AND is_deleted = 0");
    $countStmt->bind_param("i", $book_id);
    $countStmt->execute();
    $count = $countStmt->get_result()->fetch_assoc();

    $updateCount = "UPDATE books SET jumlah_eksemplar = {$count['total']}, eksemplar_tersedia = {$count['tersedia']}, updated_at = NOW() WHERE id = $book_id";
    if (!$conn->query($updateCount)) {
        throw new Exception("Gagal update jumlah eksemplar: " . $conn->error);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Eksemplar baru berhasil ditambahkan']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Gagal tambah eksemplar: ' . $e->getMessage()]);
}

$conn->close();
exit;
?>