<?php
/**
 * get_publisher_data.php
 * Path: web/apps/includes/api/get_publisher_data.php
 * 
 * Purpose: Fetch publisher data including alamat, telepon, email
 */

require_once '../config.php';

header('Content-Type: application/json');

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Publisher ID tidak ditemukan'
    ]);
    exit;
}

$publisher_id = intval($_GET['id']);

// Validate ID
if ($publisher_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Publisher ID tidak valid'
    ]);
    exit;
}

// Fetch publisher data
$query = "SELECT id, nama_penerbit, alamat, no_telepon, email, created_at 
          FROM publishers 
          WHERE id = $publisher_id AND is_deleted = 0 
          LIMIT 1";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $publisher = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'publisher' => [
            'id' => $publisher['id'],
            'nama_penerbit' => $publisher['nama_penerbit'],
            'alamat' => $publisher['alamat'] ?? '',
            'no_telepon' => $publisher['no_telepon'] ?? '',
            'email' => $publisher['email'] ?? '',
            'created_at' => $publisher['created_at']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Data penerbit tidak ditemukan'
    ]);
}

$conn->close();
exit;
?>