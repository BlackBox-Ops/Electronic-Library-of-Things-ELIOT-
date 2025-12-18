<?php
class LogModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Mencatat Audit Log Sistem
    public function logActivity($userId, $modul, $aksi, $deskripsi, $dataSesudah = null) {
        $dataJson = $dataSesudah ? json_encode($dataSesudah) : null;
        $ip = $_SERVER['REMOTE_ADDR'];
        $ua = $_SERVER['HTTP_USER_AGENT'];

        $stmt = $this->conn->prepare("INSERT INTO ts_log_aktivitas (user_id, modul, aksi, deskripsi, data_sesudah, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $userId, $modul, $aksi, $deskripsi, $dataJson, $ip, $ua);
        return $stmt->execute();
    }

    // Mencatat Riwayat Fisik Buku/Aset
    public function logBookHistory($bookId, $uidId, $aksi, $userId, $keterangan) {
        $stmt = $this->conn->prepare("INSERT INTO ts_riwayat_buku (book_id, uid_buffer_id, aksi, user_id, keterangan) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisis", $bookId, $uidId, $aksi, $userId, $keterangan);
        return $stmt->execute();
    }
}