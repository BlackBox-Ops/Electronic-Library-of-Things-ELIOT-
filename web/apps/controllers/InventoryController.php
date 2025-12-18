<?php
// web/apps/controllers/InventoryController.php
require_once '../../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tangkap data input
    $judul         = $conn->real_escape_string($_POST['judul']);
    $identifier    = $conn->real_escape_string($_POST['identifier']); 
    $kategori      = $conn->real_escape_string($_POST['kategori']);
    $lokasi        = $conn->real_escape_string($_POST['lokasi']);
    $kode_unit     = $conn->real_escape_string($_POST['kode_eksemplar']);
    $uid_buffer_id = intval($_POST['uid_id']); 
    
    // Field Tambahan
    $publisher_id   = !empty($_POST['publisher_id']) ? intval($_POST['publisher_id']) : "NULL";
    $author_id      = !empty($_POST['author_id']) ? intval($_POST['author_id']) : null;
    $tahun_terbit   = !empty($_POST['tahun_terbit']) ? intval($_POST['tahun_terbit']) : "NULL";
    $jumlah_halaman = !empty($_POST['jumlah_halaman']) ? intval($_POST['jumlah_halaman']) : 0;
    $deskripsi      = $conn->real_escape_string($_POST['deskripsi']);
    $tgl_reg       = date('Y-m-d'); 

    $conn->begin_transaction();

    try {
        // 1. Kelola Master Buku
        $checkBook = $conn->query("SELECT id FROM books WHERE isbn = '$identifier' LIMIT 1");
        
        if ($checkBook->num_rows > 0) {
            $bookId = $checkBook->fetch_assoc()['id'];
            $conn->query("UPDATE books SET jumlah_eksemplar = jumlah_eksemplar + 1, eksemplar_tersedia = eksemplar_tersedia + 1 WHERE id = '$bookId'");
        } else {
            $sqlInsertBook = "INSERT INTO books (judul_buku, isbn, kategori, lokasi_rak, publisher_id, tahun_terbit, jumlah_halaman, deskripsi) 
                              VALUES ('$judul', '$identifier', '$kategori', '$lokasi', $publisher_id, $tahun_terbit, $jumlah_halaman, '$deskripsi')";
            if (!$conn->query($sqlInsertBook)) throw new Exception("Gagal simpan buku: " . $conn->error);
            $bookId = $conn->insert_id;
        }

        // 2. Kelola Penulis (rt_book_author)
        if ($author_id) {
            $checkRelAuthor = $conn->query("SELECT id FROM rt_book_author WHERE book_id = $bookId AND author_id = $author_id");
            if ($checkRelAuthor->num_rows == 0) {
                $conn->query("INSERT INTO rt_book_author (book_id, author_id, peran) VALUES ($bookId, $author_id, 'penulis_utama')");
            }
        }

        // 3. Kelola Detail Unit (rt_book_uid)
        $sqlUnit = "INSERT INTO rt_book_uid (book_id, uid_buffer_id, kode_eksemplar, kondisi, tanggal_registrasi) 
                    VALUES ('$bookId', '$uid_buffer_id', '$kode_unit', 'baik', '$tgl_reg')";
        if (!$conn->query($sqlUnit)) throw new Exception("Gagal simpan unit: " . $conn->error);

        // 4. Update UID Buffer
        $conn->query("UPDATE uid_buffer SET jenis = 'book' WHERE id = '$uid_buffer_id'");

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Registrasi Berhasil!']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
    }
}