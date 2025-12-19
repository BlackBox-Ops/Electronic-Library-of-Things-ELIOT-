<?php
/**
 * API: Get Next Book Code
 * Path: web/apps/includes/api/get_next_code.php
 * 
 * UC-4: Auto-Generate Kode Eksemplar Unik
 * Endpoint ini mendukung generate kode otomatis seperti:
 * ROB-001, ROB-002, ..., ROB-999
 * 
 * BUSINESS LOGIC:
 * 1. Terima prefix kode (misal: "ROB")
 * 2. Query kode terakhir dengan prefix yang sama
 * 3. Increment nomor urut
 * 4. Return next code dengan format PREFIX-XXX (3 digit)
 * 5. Support multi-kode dengan ?count=X (return array codes)
 */

require_once '../../../includes/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Get parameters
    $prefix = $_GET['prefix'] ?? '';
    $book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
    $count = isset($_GET['count']) ? intval($_GET['count']) : 1; // Default 1, untuk multi
    if ($count < 1) $count = 1;
    
    // Validasi prefix
    if (empty($prefix)) {
        throw new Exception("Prefix tidak boleh kosong");
    }
    
    // Sanitize prefix: hanya huruf dan angka, max 10 karakter
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $prefix));
    $prefix = substr($prefix, 0, 10);
    
    if (empty($prefix)) {
        throw new Exception("Prefix tidak valid setelah sanitasi");
    }
    
    // UC-4: Query kode terakhir dengan prefix yang sama
    $sql = "SELECT kode_eksemplar 
            FROM rt_book_uid 
            WHERE kode_eksemplar LIKE '{$prefix}-%' 
            AND is_deleted = 0";
    
    // Jika ada book_id, filter berdasarkan book_id
    if ($book_id > 0) {
        $sql .= " AND book_id = {$book_id}";
    }
    
    $sql .= " ORDER BY kode_eksemplar DESC LIMIT 1";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query error: " . $conn->error);
    }
    
    // UC-4: Calculate starting number
    $next_number = 1; // Default start
    
    if ($row = $result->fetch_assoc()) {
        $last_code = $row['kode_eksemplar'];
        
        // Extract number from last code (format: PREFIX-XXX)
        if (preg_match('/-(\d+)$/', $last_code, $matches)) {
            $last_number = intval($matches[1]);
            $next_number = $last_number + 1;
        }
    }
    
    // UC-4: Generate array of next codes
    $next_codes = [];
    for ($i = 0; $i < $count; $i++) {
        $current_number = $next_number + $i;
        $next_codes[] = sprintf("%s-%03d", $prefix, $current_number);
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'prefix' => $prefix,
        'next_codes' => $next_codes,
        'count' => count($next_codes),
        'message' => count($next_codes) > 1 ? 'Multiple next codes generated successfully' : 'Next code generated successfully'
    ], JSON_PRETTY_PRINT);
    
    error_log("[NEXT CODES GENERATED] Prefix: {$prefix}, Count: {$count}");
    
} catch (Exception $e) {
    // Error response
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'error_type' => 'exception'
    ], JSON_PRETTY_PRINT);
    
    error_log("[GET NEXT CODE ERROR] " . $e->getMessage());
}

$conn->close();
exit;