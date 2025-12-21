<?php
/**
 * API: Get Next Book Code (Support Multi-Kode)
 * Path: web/apps/includes/api/get_next_code.php
 * 
 * UC-4: Auto-Generate Kode Eksemplar Unik
 * Support ?prefix=GAD&count=10 → return array ['GAD-001', 'GAD-002', ..., 'GAD-010']
 * 
 * PERBAIKAN: Fix regex preg_replace (hilangkan 'g' invalid), tambah logging & error handling
 */

require_once '../../../includes/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Suppress warnings jika ada, tapi kita fix root cause
// ini@error_reporting(E_ALL & ~E_WARNING); // Opsional, tapi lebih baik fix kode

try {
    $prefix = $_GET['prefix'] ?? '';
    $book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
    $count = isset($_GET['count']) ? max(1, intval($_GET['count'])) : 1; // minimal 1

    if (empty($prefix)) {
        throw new Exception("Prefix tidak boleh kosong");
    }

    // Sanitasi prefix - FIX: Hilangkan 'g' dari regex (PHP tidak perlu global modifier)
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $prefix)); // Fixed!
    $prefix = substr($prefix, 0, 10);

    if (empty($prefix)) {
        throw new Exception("Prefix tidak valid setelah sanitasi");
    }

    // Query kode terakhir
    $sql = "SELECT kode_eksemplar 
            FROM rt_book_uid 
            WHERE kode_eksemplar LIKE '{$prefix}-%' 
                AND is_deleted = 0";
    if ($book_id > 0) {
        $sql .= " AND book_id = {$book_id}";
    }
    $sql .= " ORDER BY kode_eksemplar DESC LIMIT 1";

    error_log("[GET_NEXT_CODE] Executing query: $sql");

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query error: " . $conn->error . " | SQL: $sql");
    }

    $next_number = 1;
    if ($row = $result->fetch_assoc()) {
        if (preg_match('/-(\d+)$/', $row['kode_eksemplar'], $matches)) {
            $next_number = intval($matches[1]) + 1;
        }
    }

    // Generate array kode
    $next_codes = [];
    for ($i = 0; $i < $count; $i++) {
        $next_codes[] = sprintf("%s-%03d", $prefix, $next_number + $i);
    }

    echo json_encode([
        'success' => true,
        'prefix' => $prefix,
        'next_codes' => $next_codes,
        'count' => count($next_codes),
        'message' => 'Kode eksemplar berhasil digenerate'
    ], JSON_PRETTY_PRINT);

    error_log("[NEXT CODES SUCCESS] Prefix: {$prefix}, Count: {$count}, Codes: " . implode(', ', $next_codes));

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => [
            'prefix_raw' => $_GET['prefix'] ?? '',
            'error_line' => $e->getLine()
        ]
    ], JSON_PRETTY_PRINT);
    
    error_log("[GET NEXT CODE ERROR] " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
}

$conn->close();
exit;
?>