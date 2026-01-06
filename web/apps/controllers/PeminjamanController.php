<?php
/**
 * Peminjaman Controller
 * Path: web/apps/controllers/PeminjamanController.php
 * 
 * Handles peminjaman workflow:
 * 1. Validate member (from session)
 * 2. Get book details + rating
 * 3. Process peminjaman via API
 * 
 * @author ELIOT System
 * @version 1.0.0
 * @date 2026-01-06
 */

class PeminjamanController {
    private $conn;
    private $baseUrl;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->baseUrl = '/eliot/web/apps';
    }
    
    /**
     * Get book details lengkap untuk halaman peminjaman
     */
    public function getBookDetails($uidBufferId) {
        try {
            $sql = "
                SELECT 
                    -- Book Info
                    b.id AS book_id,
                    b.judul_buku,
                    b.isbn,
                    b.jumlah_halaman,
                    b.kategori,
                    b.tahun_terbit,
                    b.lokasi_rak,
                    b.deskripsi,
                    b.cover_image,
                    b.keterangan,
                    b.jumlah_eksemplar,
                    b.eksemplar_tersedia,
                    
                    -- Eksemplar Info
                    rt.id AS rt_id,
                    rt.kode_eksemplar,
                    rt.kondisi,
                    DATE_FORMAT(rt.tanggal_registrasi, '%d/%m/%Y') AS tanggal_registrasi_formatted,
                    
                    -- Publisher Info
                    p.id AS publisher_id,
                    p.nama_penerbit,
                    p.alamat AS publisher_alamat,
                    p.no_telepon AS publisher_telepon,
                    
                    -- Authors (GROUP_CONCAT)
                    GROUP_CONCAT(
                        DISTINCT CONCAT(
                            a.nama_pengarang, '|', rba.peran
                        ) SEPARATOR ';;'
                    ) AS authors_data,
                    
                    -- Check if sudah dipinjam
                    (SELECT COUNT(*) FROM ts_peminjaman tp 
                     WHERE tp.uid_buffer_id = rt.uid_buffer_id 
                     AND tp.status = 'dipinjam' 
                     AND tp.is_deleted = 0) AS is_borrowed
                    
                FROM rt_book_uid rt
                INNER JOIN books b ON rt.book_id = b.id AND b.is_deleted = 0
                LEFT JOIN publishers p ON b.publisher_id = p.id AND p.is_deleted = 0
                LEFT JOIN rt_book_author rba ON b.id = rba.book_id AND rba.is_deleted = 0
                LEFT JOIN authors a ON rba.author_id = a.id AND a.is_deleted = 0
                
                WHERE rt.uid_buffer_id = ?
                  AND rt.is_deleted = 0
                
                GROUP BY b.id, rt.id, p.id
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('i', $uidBufferId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return [
                    'success' => false,
                    'message' => 'Buku tidak ditemukan untuk UID ini'
                ];
            }
            
            $book = $result->fetch_assoc();
            $stmt->close();
            
            // Process authors
            $authors = [];
            if (!empty($book['authors_data'])) {
                $authorsRaw = explode(';;', $book['authors_data']);
                foreach ($authorsRaw as $authorRaw) {
                    $parts = explode('|', $authorRaw);
                    if (count($parts) === 2) {
                        $authors[] = [
                            'nama' => $parts[0],
                            'peran' => $parts[1]
                        ];
                    }
                }
            }
            
            $book['authors'] = $authors;
            unset($book['authors_data']);
            
            return [
                'success' => true,
                'data' => $book
            ];
            
        } catch (Exception $e) {
            error_log('[PeminjamanController] Error getting book: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get book ratings and statistics
     */
    public function getBookRatings($bookId) {
        try {
            // Get average and count
            $sql = "
                SELECT 
                    COALESCE(ROUND(AVG(rating), 2), 0.0) AS average_rating,
                    COUNT(*) AS total_reviews,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS count_5_star,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS count_4_star,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS count_3_star,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS count_2_star,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS count_1_star
                FROM book_ratings
                WHERE book_id = ? AND is_deleted = 0
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('i', $bookId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            $stmt->close();
            
            // Get recent reviews (limit 5)
            $sql = "
                SELECT 
                    br.rating,
                    br.review,
                    DATE_FORMAT(br.created_at, '%d/%m/%Y') AS review_date,
                    u.nama AS reviewer_name
                FROM book_ratings br
                INNER JOIN users u ON br.user_id = u.id
                WHERE br.book_id = ? AND br.is_deleted = 0
                ORDER BY br.created_at DESC
                LIMIT 5
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('i', $bookId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $reviews = [];
            while ($row = $result->fetch_assoc()) {
                $reviews[] = $row;
            }
            $stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'reviews' => $reviews
                ]
            ];
            
        } catch (Exception $e) {
            error_log('[PeminjamanController] Error getting ratings: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate member dapat meminjam atau tidak
     */
    public function validateMemberForBorrow($userId) {
        try {
            $sql = "
                SELECT 
                    u.id,
                    u.nama,
                    u.status,
                    u.max_peminjaman,
                    (SELECT COUNT(*) 
                     FROM ts_peminjaman p 
                     WHERE p.user_id = u.id 
                     AND p.status = 'dipinjam' 
                     AND p.is_deleted = 0) AS total_pinjam_aktif,
                    (SELECT COALESCE(SUM(d.jumlah_denda), 0)
                     FROM ts_denda d
                     WHERE d.user_id = u.id
                     AND d.status_pembayaran = 'belum_dibayar'
                     AND d.is_deleted = 0) AS total_denda
                FROM users u
                WHERE u.id = ? AND u.is_deleted = 0
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return [
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ];
            }
            
            $member = $result->fetch_assoc();
            $stmt->close();
            
            $errors = [];
            $kuotaTersisa = $member['max_peminjaman'] - $member['total_pinjam_aktif'];
            
            // Validasi
            if ($member['status'] !== 'aktif') {
                $errors[] = 'Status member: ' . $member['status'];
            }
            
            if ($kuotaTersisa <= 0) {
                $errors[] = 'Kuota peminjaman habis';
            }
            
            // Get max denda from settings
            $maxDenda = 50000;
            $settingQuery = $this->conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_denda_block'");
            if ($settingQuery && $row = $settingQuery->fetch_assoc()) {
                $maxDenda = (int)$row['setting_value'];
            }
            
            if ($member['total_denda'] >= $maxDenda) {
                $errors[] = 'Total denda >= Rp ' . number_format($maxDenda, 0, ',', '.');
            }
            
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'message' => 'Member tidak dapat meminjam',
                    'errors' => $errors
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'id' => $member['id'],
                    'nama' => $member['nama'],
                    'kuota_tersisa' => $kuotaTersisa,
                    'total_denda' => $member['total_denda']
                ]
            ];
            
        } catch (Exception $e) {
            error_log('[PeminjamanController] Error validating member: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get system settings
     */
    public function getSystemSettings() {
        try {
            $sql = "
                SELECT setting_key, setting_value 
                FROM system_settings 
                WHERE setting_key IN ('durasi_peminjaman_default', 'denda_per_hari', 'max_denda_block')
            ";
            
            $result = $this->conn->query($sql);
            $settings = [];
            
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            // Defaults
            if (!isset($settings['durasi_peminjaman_default'])) {
                $settings['durasi_peminjaman_default'] = 7;
            }
            if (!isset($settings['denda_per_hari'])) {
                $settings['denda_per_hari'] = 2000;
            }
            if (!isset($settings['max_denda_block'])) {
                $settings['max_denda_block'] = 50000;
            }
            
            return [
                'success' => true,
                'data' => $settings
            ];
            
        } catch (Exception $e) {
            error_log('[PeminjamanController] Error getting settings: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ];
        }
    }
}
?>