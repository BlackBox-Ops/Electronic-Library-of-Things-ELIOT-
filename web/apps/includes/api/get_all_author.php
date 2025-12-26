<?php
/**
 * get_all_authors.php
 * Path: web/apps/includes/api/get_all_authors.php
 * 
 * Purpose: Fetch all authors for dropdown selection
 */

require_once '../config.php';

header('Content-Type: application/json');

// Fetch all authors
$query = "SELECT id, nama_pengarang, biografi 
          FROM authors 
          WHERE is_deleted = 0 
          ORDER BY nama_pengarang ASC";

$result = $conn->query($query);

if ($result) {
    $authors = [];
    
    while ($row = $result->fetch_assoc()) {
        $authors[] = [
            'id' => $row['id'],
            'nama_pengarang' => $row['nama_pengarang'],
            'biografi' => $row['biografi'] ?? ''
        ];
    }
    
    echo json_encode([
        'success' => true,
        'authors' => $authors,
        'count' => count($authors)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengambil data penulis'
    ]);
}

$conn->close();
exit;
?>