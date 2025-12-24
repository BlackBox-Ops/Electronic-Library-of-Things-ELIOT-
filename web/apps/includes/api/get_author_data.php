<?php
/**
 * get_author_data.php
 * Path: web/apps/includes/api/get_author_data.php
 * 
 * Purpose: Fetch author data including biografi
 */

require_once '../config.php';

header('Content-Type: application/json');

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Author ID tidak ditemukan'
    ]);
    exit;
}

$author_id = intval($_GET['id']);

// Validate ID
if ($author_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Author ID tidak valid'
    ]);
    exit;
}

// Fetch author data
$query = "SELECT id, nama_pengarang, biografi, created_at 
          FROM authors 
          WHERE id = $author_id AND is_deleted = 0 
          LIMIT 1";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $author = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'author' => [
            'id' => $author['id'],
            'nama_pengarang' => $author['nama_pengarang'],
            'biografi' => $author['biografi'] ?? '',
            'created_at' => $author['created_at']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Data penulis tidak ditemukan'
    ]);
}

$conn->close();
exit;
?>