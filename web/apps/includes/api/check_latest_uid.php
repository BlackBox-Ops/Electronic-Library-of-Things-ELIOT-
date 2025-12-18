<?php
    // Path: web/apps/includes/api/check_latest_uid.php
    // Lokasi Config: web/includes/config.php (Naik 2 level)
    require_once '../../../includes/config.php'; 

    header('Content-Type: application/json');

    // Ambil UID yang masuk dalam 1 menit terakhir
    $sql = "SELECT id, uid FROM uid_buffer 
            WHERE id NOT IN (SELECT uid_buffer_id FROM rt_book_uid) 
            AND is_deleted = 0 
            AND created_at >= NOW() - INTERVAL 1 MINUTE 
            ORDER BY created_at DESC LIMIT 1";

    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'id' => $row['id'], 'uid' => $row['uid']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Scan hardware tidak ditemukan']);
    }
    exit;