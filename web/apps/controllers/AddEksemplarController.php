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
    // Fetch book info to determine prefix
    $bkStmt = $conn->prepare("SELECT COALESCE(NULLIF(kode_buku,''), judul) AS ref FROM books WHERE id = ? LIMIT 1");
    $bkStmt->bind_param("i", $book_id);
    $bkStmt->execute();
    $bkRes = $bkStmt->get_result();
    if ($bkRes->num_rows === 0) {
        throw new Exception("Buku tidak ditemukan");
    }
    $bk = $bkRes->fetch_assoc();
    $bkStmt->close();

    // derive prefix: prefer kode_buku (clean alnum uppercase), else first 3 alnum of judul
    $rawRef = strtoupper($bk['ref']);
    // keep only A-Z0-9 and take up to 6 chars (configurable)
    preg_match_all('/[A-Z0-9]/', $rawRef, $m);
    $prefix = implode('', $m[0]);
    $prefix = $prefix ? substr($prefix, 0, 6) : 'EKS';
    // ensure prefix has at least 2-3 chars
    if (strlen($prefix) < 2) $prefix = str_pad($prefix, 3, 'X');

    // compute current max numeric for this prefix
    $safePrefix = $conn->real_escape_string($prefix);
    $resMax = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(kode_eksemplar, LENGTH('{$safePrefix}-')+1) AS UNSIGNED)), 0) AS maxnum 
                             FROM rt_book_uid WHERE kode_eksemplar LIKE '{$safePrefix}-%'");
    $rowMax = $resMax->fetch_assoc();
    $nextNum = intval($rowMax['maxnum']);

    $tgl_reg = date('Y-m-d');

    foreach ($new_eksemplar as $eks) {
        $uid_buffer_id = intval($eks['uid_buffer_id']);
        // allow client-provided kode (editable) but sanitize
        $kodeCandidateUser = trim($eks['kode_eksemplar'] ?? '');
        $kondisi = $conn->real_escape_string($eks['kondisi'] ?? 'baik');

        // verify uid_buffer existence & not already used
        $chkStmt = $conn->prepare("SELECT id FROM uid_buffer WHERE id = ? LIMIT 1");
        $chkStmt->bind_param("i", $uid_buffer_id);
        $chkStmt->execute();
        $chkRes = $chkStmt->get_result();
        if ($chkRes->num_rows === 0) {
            throw new Exception("UID buffer id {$uid_buffer_id} tidak ditemukan");
        }
        $chkStmt->close();

        $dupStmt = $conn->prepare("SELECT id FROM rt_book_uid WHERE uid_buffer_id = ? AND is_deleted = 0 LIMIT 1");
        $dupStmt->bind_param("i", $uid_buffer_id);
        $dupStmt->execute();
        $dupRes = $dupStmt->get_result();
        if ($dupRes->num_rows > 0) {
            throw new Exception("UID buffer id {$uid_buffer_id} sudah terdaftar pada eksemplar lain");
        }
        $dupStmt->close();

        $attempts = 0;
        $inserted = false;
        $kodeToTry = $kodeCandidateUser !== '' ? $conn->real_escape_string($kodeCandidateUser) : '';

        while (!$inserted && $attempts < 50) {
            $attempts++;
            if (empty($kodeToTry)) {
                $nextNum++;
                $kodeToTry = $safePrefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            }

            $insertEks = "INSERT INTO rt_book_uid (book_id, uid_buffer_id, kode_eksemplar, kondisi, tanggal_registrasi) 
                            VALUES ($book_id, $uid_buffer_id, '$kodeToTry', '$kondisi', '$tgl_reg')";

            if ($conn->query($insertEks)) {
                $inserted = true;
            } else {
                $errNo = $conn->errno;
                if ($errNo === 1062) {
                    // duplicate kode -> if user supplied kode, fail fast; else generate next
                    if ($kodeCandidateUser !== '') {
                        throw new Exception("Kode eksemplar '{$kodeCandidateUser}' sudah ada. Silakan ubah kode.");
                    }
                    // reset kodeToTry to force generation in next iteration
                    $kodeToTry = '';
                    continue;
                } else {
                    throw new Exception("Gagal tambah eksemplar: " . $conn->error);
                }
            }
        }

        if (!$inserted) {
            throw new Exception("Gagal mendapatkan kode unik untuk eksemplar setelah beberapa percobaan");
        }

        // Update uid_buffer jadi 'book'
        $updateBuffer = "UPDATE uid_buffer SET jenis = 'book', is_labeled = 'yes', updated_at = NOW() WHERE id = $uid_buffer_id";
        if (!$conn->query($updateBuffer)) {
            throw new Exception("Gagal update buffer UID: " . $conn->error);
        }
    }

    // recalc counts
    $countStmt = $conn->prepare("SELECT COUNT(*) as total, 
                                    SUM(CASE WHEN kondisi NOT IN ('rusak_berat', 'hilang') THEN 1 ELSE 0 END) as tersedia 
                                    FROM rt_book_uid WHERE book_id = ? AND is_deleted = 0");
    $countStmt->bind_param("i", $book_id);
    $countStmt->execute();
    $count = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();

    $updateCount = "UPDATE books SET jumlah_eksemplar = {$count['total']}, eksemplar_tersedia = {$count['tersedia']}, updated_at = NOW() WHERE id = $book_id";
    if (!$conn->query($updateCount)) {
        throw new Exception("Gagal update jumlah eksemplar: " . $conn->error);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Eksemplar baru berhasil ditambahkan']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("[AddEksemplar ERROR] " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Gagal tambah eksemplar: ' . $e->getMessage()]);
}

$conn->close();
exit;
?>