CREATE DATABASE IF NOT EXISTS perpustakaan_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE perpustakaan_db;

-- ========================================
-- DATA MASTER TABLES
-- ========================================

-- Table: UID_BUFFER
-- Purpose: Central repository untuk semua RFID/Barcode identifiers
CREATE TABLE uid_buffer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(100) NOT NULL UNIQUE COMMENT 'RFID/Barcode unique identifier',
    jenis ENUM('user', 'book') NOT NULL COMMENT 'Tipe UID: untuk user atau buku',
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Last scan timestamp',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    INDEX idx_uid (uid),
    INDEX idx_jenis (jenis),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Master UID/RFID/Barcode buffer';

-- Table: CATEGORIES
-- Purpose: Master kategori buku (many-to-many dengan books)
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL UNIQUE,
    deskripsi TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    INDEX idx_nama_kategori (nama_kategori),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Master kategori buku';

-- Table: PUBLISHERS
-- Purpose: Master data penerbit
CREATE TABLE publishers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_penerbit VARCHAR(200) NOT NULL UNIQUE,
    alamat TEXT,
    no_telepon VARCHAR(20),
    email VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    INDEX idx_nama_penerbit (nama_penerbit),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Master penerbit';

-- Table: AUTHORS
-- Purpose: Master data pengarang/penulis
CREATE TABLE authors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_pengarang VARCHAR(200) NOT NULL UNIQUE,
    biografi TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    INDEX idx_nama_pengarang (nama_pengarang),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Master pengarang';

-- Purpose: Master data buku/koleksi perpustakaan
CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul_buku VARCHAR(255) NOT NULL,
    isbn VARCHAR(20) UNIQUE COMMENT 'International Standard Book Number',
    publisher_id INT,
    jumlah_halaman INT,
    kategori ENUM('buku', 'jurnal', 'prosiding', 'skripsi', 'laporan_pkl') DEFAULT 'buku',
    jumlah_eksemplar INT DEFAULT 1 COMMENT 'Total eksemplar yang dimiliki',
    eksemplar_tersedia INT DEFAULT 1 COMMENT 'Jumlah eksemplar yang available',
    tahun_terbit YEAR,
    lokasi_rak VARCHAR(50) COMMENT 'Kode rak: A1, B2, etc',
    deskripsi TEXT,
    cover_image VARCHAR(255) COMMENT 'Path/URL to cover image',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (publisher_id) REFERENCES publishers(id) ON DELETE SET NULL,
    
    INDEX idx_judul_buku (judul_buku),
    INDEX idx_isbn (isbn),
    INDEX idx_kategori (kategori),
    INDEX idx_tahun_terbit (tahun_terbit),
    INDEX idx_eksemplar_tersedia (eksemplar_tersedia),
    INDEX idx_is_deleted (is_deleted),
    
    CONSTRAINT chk_eksemplar_valid CHECK (eksemplar_tersedia <= jumlah_eksemplar),
    CONSTRAINT chk_eksemplar_positive CHECK (jumlah_eksemplar >= 0 AND eksemplar_tersedia >= 0)
) ENGINE=InnoDB COMMENT='Master buku dan koleksi';

-- Table: USERS
-- Purpose: Master data pengguna sistem
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(200) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    no_identitas VARCHAR(50) COMMENT 'NIP/NIM/NIK',
    no_telepon VARCHAR(20),
    alamat TEXT,
    role ENUM('admin', 'staff', 'member') DEFAULT 'member',
    status ENUM('aktif', 'nonaktif', 'suspended') DEFAULT 'aktif',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Bcrypt/Argon2 hash',
    max_peminjaman INT DEFAULT 3 COMMENT 'Max buku yang bisa dipinjam bersamaan',
    foto_profil VARCHAR(255) COMMENT 'Path/URL to profile photo',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_no_identitas (no_identitas),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Master users (admin, staff, member)'

-- ========================================
-- RELATIONAL TABLES (RT_)
-- ========================================

-- Table: RT_BOOK_AUTHOR
-- Purpose: Junction table untuk many-to-many books dan authors
CREATE TABLE rt_book_author (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    author_id INT NOT NULL,
    peran ENUM('penulis_utama', 'co_author', 'editor', 'translator') DEFAULT 'penulis_utama',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE,
    
    UNIQUE KEY uk_book_author (book_id, author_id),
    INDEX idx_book_id (book_id),
    INDEX idx_author_id (author_id),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Relasi many-to-many books dan authors';

-- Table: RT_USER_UID
-- Purpose: Relasi user dengan kartu RFID/Barcode mereka
CREATE TABLE rt_user_uid (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    uid_buffer_id INT NOT NULL,
    tanggal_aktif DATE NOT NULL,
    tanggal_nonaktif DATE COMMENT 'NULL = masih aktif',
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (uid_buffer_id) REFERENCES uid_buffer(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_uid_buffer_id (uid_buffer_id),
    INDEX idx_status (status),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Relasi user dengan UID card mereka';

-- Table: RT_BOOK_UID
-- Purpose: Relasi setiap eksemplar fisik buku dengan UID
CREATE TABLE rt_book_uid (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    uid_buffer_id INT NOT NULL,
    kode_eksemplar VARCHAR(50) NOT NULL UNIQUE COMMENT 'Contoh: BOOK001-001',
    kondisi ENUM('baik', 'rusak_ringan', 'rusak_berat', 'hilang') DEFAULT 'baik',
    tanggal_registrasi DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (uid_buffer_id) REFERENCES uid_buffer(id) ON DELETE CASCADE,
    
    INDEX idx_book_id (book_id),
    INDEX idx_uid_buffer_id (uid_buffer_id),
    INDEX idx_kode_eksemplar (kode_eksemplar),
    INDEX idx_kondisi (kondisi),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Relasi eksemplar buku dengan UID';

-- ========================================
-- TRANSACTION TABLES (TS_) - DIPERBAIKI UNTUK MySQL
-- ========================================

-- Table: TS_PEMINJAMAN
CREATE TABLE ts_peminjaman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_peminjaman VARCHAR(50) NOT NULL UNIQUE COMMENT 'Format: PJM-YYYYMMDD-XXXX',
    user_id INT NOT NULL COMMENT 'Peminjam',
    book_id INT NOT NULL COMMENT 'Buku yang dipinjam (master)',
    uid_buffer_id INT NOT NULL COMMENT 'UID eksemplar spesifik yang dipinjam',
    staff_id INT NOT NULL COMMENT 'Staff yang memproses',
    tanggal_pinjam DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_date DATETIME NOT NULL COMMENT 'Calculated: tanggal_pinjam + durasi',
    status ENUM('dipinjam', 'dikembalikan', 'telat', 'hilang') DEFAULT 'dipinjam',
    catatan TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT,
    FOREIGN KEY (uid_buffer_id) REFERENCES uid_buffer(id) ON DELETE RESTRICT,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_kode_peminjaman (kode_peminjaman),
    INDEX idx_user_id (user_id),
    INDEX idx_book_id (book_id),
    INDEX idx_uid_buffer_id (uid_buffer_id),
    INDEX idx_status (status),
    INDEX idx_tanggal_pinjam (tanggal_pinjam),
    INDEX idx_due_date (due_date),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Transaksi peminjaman';

-- Table: TS_PENGEMBALIAN
CREATE TABLE ts_pengembalian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_pengembalian VARCHAR(50) NOT NULL UNIQUE COMMENT 'Format: KMB-YYYYMMDD-XXXX',
    peminjaman_id INT NOT NULL,
    uid_buffer_id INT NOT NULL COMMENT 'UID untuk verifikasi',
    staff_id INT NOT NULL COMMENT 'Staff yang memproses',
    tanggal_kembali DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    hari_telat INT DEFAULT 0 COMMENT 'Calculated field',
    denda DECIMAL(10,2) DEFAULT 0.00,
    kondisi_buku ENUM('baik', 'rusak_ringan', 'rusak_berat', 'hilang') DEFAULT 'baik',
    catatan TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0,

    FOREIGN KEY (peminjaman_id) REFERENCES ts_peminjaman(id) ON DELETE RESTRICT,
    FOREIGN KEY (uid_buffer_id) REFERENCES uid_buffer(id) ON DELETE RESTRICT,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_kode_pengembalian (kode_pengembalian),
    INDEX idx_peminjaman_id (peminjaman_id),
    INDEX idx_tanggal_kembali (tanggal_kembali),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Transaksi pengembalian';

-- Table: TS_RESERVASI
CREATE TABLE ts_reservasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_reservasi VARCHAR(50) NOT NULL UNIQUE COMMENT 'Format: RSV-YYYYMMDD-XXXX',
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    tanggal_reservasi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tanggal_expired DATETIME NOT NULL COMMENT 'Deadline untuk ambil buku',
    status ENUM('menunggu', 'diambil', 'expired', 'dibatalkan') DEFAULT 'menunggu',
    catatan TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,

    INDEX idx_kode_reservasi (kode_reservasi),
    INDEX idx_user_id (user_id),
    INDEX idx_book_id (book_id),
    INDEX idx_status (status),
    INDEX idx_tanggal_reservasi (tanggal_reservasi),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Transaksi reservasi buku';

-- Table: TS_DENDA (sebelumnya ts_denda)
CREATE TABLE ts_denda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_denda VARCHAR(50) NOT NULL UNIQUE COMMENT 'Format: DND-YYYYMMDD-XXXX',
    pengembalian_id INT DEFAULT NULL COMMENT 'Nullable jika denda bukan dari pengembalian',
    user_id INT NOT NULL,
    jumlah_denda DECIMAL(10,2) NOT NULL,
    jenis_denda ENUM('keterlambatan', 'kerusakan', 'kehilangan') NOT NULL,
    status_pembayaran ENUM('belum_dibayar', 'dibayar', 'dibebaskan') DEFAULT 'belum_dibayar',
    tanggal_bayar DATETIME DEFAULT NULL,
    staff_id INT DEFAULT NULL COMMENT 'Staff yang memproses pembayaran',
    catatan TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0,

    FOREIGN KEY (pengembalian_id) REFERENCES ts_pengembalian(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_kode_denda (kode_denda),
    INDEX idx_user_id (user_id),
    INDEX idx_status_pembayaran (status_pembayaran),
    INDEX idx_jenis_denda (jenis_denda),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Tracking denda dan pembayaran';

-- Table: TS_RIWAYAT_BUKU
CREATE TABLE ts_riwayat_buku (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    uid_buffer_id INT DEFAULT NULL COMMENT 'UID eksemplar spesifik',
    aksi ENUM('peminjaman', 'pengembalian', 'perbaikan', 'penghapusan', 'registrasi') NOT NULL,
    user_id INT NOT NULL COMMENT 'User yang melakukan aksi',
    keterangan TEXT,
    tanggal_aksi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0,

    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (uid_buffer_id) REFERENCES uid_buffer(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_book_id (book_id),
    INDEX idx_aksi (aksi),
    INDEX idx_tanggal_aksi (tanggal_aksi),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB COMMENT='Audit trail buku';

-- Table: TS_LOG_AKTIVITAS
CREATE TABLE ts_log_aktivitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    modul ENUM('peminjaman', 'pengembalian', 'reservasi', 'denda', 'master_buku', 'master_user', 'sistem') NOT NULL,
    aksi ENUM('create', 'read', 'update', 'delete', 'login', 'logout') NOT NULL,
    deskripsi TEXT,
    data_sebelum JSON,
    data_sesudah JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_user_id (user_id),
    INDEX idx_modul (modul),
    INDEX idx_aksi (aksi),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='System audit log';

-- ========================================
-- TRIGGERS & VIEWS - DIPERBAIKI UNTUK MySQL (XAMPP)
-- ========================================

DELIMITER $$

-- Trigger: Kurangi eksemplar_tersedia saat peminjaman baru (status dipinjam)
CREATE TRIGGER trg_after_peminjaman_insert 
AFTER INSERT ON ts_peminjaman
FOR EACH ROW
BEGIN
    IF NEW.status = 'dipinjam' AND NEW.is_deleted = 0 THEN
        UPDATE books 
        SET eksemplar_tersedia = eksemplar_tersedia - 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.book_id 
          AND eksemplar_tersedia > 0;  -- Safety agar tidak minus
    END IF;
END$$

-- Trigger: Tambah eksemplar_tersedia + update status saat pengembalian
CREATE TRIGGER trg_after_pengembalian_insert 
AFTER INSERT ON ts_pengembalian
FOR EACH ROW
BEGIN
    -- Update status peminjaman menjadi 'dikembalikan' (hanya kalau belum)
    UPDATE ts_peminjaman 
    SET status = 'dikembalikan',
        updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.peminjaman_id 
      AND status != 'dikembalikan'
      AND is_deleted = 0;

    -- Tambah stok eksemplar tersedia
    UPDATE books b
    INNER JOIN ts_peminjaman p ON b.id = p.book_id
    SET b.eksemplar_tersedia = b.eksemplar_tersedia + 1,
        b.updated_at = CURRENT_TIMESTAMP
    WHERE p.id = NEW.peminjaman_id
      AND p.is_deleted = 0;

    -- Hitung hari telat & denda otomatis (bisa override nanti)
    UPDATE ts_pengembalian 
    SET hari_telat = GREATEST(0, DATEDIFF(tanggal_kembali, (
                SELECT due_date FROM ts_peminjaman WHERE id = NEW.peminjaman_id
            ))),
        denda = GREATEST(0, DATEDIFF(tanggal_kembali, (
                SELECT due_date FROM ts_peminjaman WHERE id = NEW.peminjaman_id
            ))) * 1000  -- Contoh: Rp1000/hari, ubah sesuai kebijakan
    WHERE id = NEW.id;
END$$

-- Trigger: Auto-generate kode_peminjaman (PJM-YYYYMMDD-XXXX)
CREATE TRIGGER trg_before_peminjaman_insert 
BEFORE INSERT ON ts_peminjaman
FOR EACH ROW
BEGIN
    DECLARE next_num INT DEFAULT 1;
    DECLARE today_str VARCHAR(8);
    
    SET today_str = DATE_FORMAT(CURRENT_DATE, '%Y%m%d');
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(kode_peminjaman, 14, 4) AS UNSIGNED)), 0) + 1
    INTO next_num
    FROM ts_peminjaman
    WHERE kode_peminjaman LIKE CONCAT('PJM-', today_str, '-%');
    
    SET NEW.kode_peminjaman = CONCAT('PJM-', today_str, '-', LPAD(next_num, 4, '0'));
END$$

-- Trigger: Auto-generate kode_pengembalian (KMB-YYYYMMDD-XXXX) + hitung hari_telat
CREATE TRIGGER trg_before_pengembalian_insert 
BEFORE INSERT ON ts_pengembalian
FOR EACH ROW
BEGIN
    DECLARE next_num INT DEFAULT 1;
    DECLARE today_str VARCHAR(8);
    DECLARE due_date_val DATE;
    
    SET today_str = DATE_FORMAT(CURRENT_DATE, '%Y%m%d');
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(kode_pengembalian, 14, 4) AS UNSIGNED)), 0) + 1
    INTO next_num
    FROM ts_pengembalian
    WHERE kode_pengembalian LIKE CONCAT('KMB-', today_str, '-%');
    
    SET NEW.kode_pengembalian = CONCAT('KMB-', today_str, '-', LPAD(next_num, 4, '0'));
    
    -- Ambil due_date dari peminjaman
    SELECT due_date INTO due_date_val
    FROM ts_peminjaman
    WHERE id = NEW.peminjaman_id;
    
    -- Hitung hari telat (jika due_date tidak null)
    IF due_date_val IS NOT NULL THEN
        SET NEW.hari_telat = GREATEST(0, DATEDIFF(NEW.tanggal_kembali, due_date_val));
    ELSE
        SET NEW.hari_telat = 0;
    END IF;
END$$

DELIMITER ;

-- ========================================
-- VIEW: Peminjaman aktif dengan info lengkap
-- ========================================

CREATE OR REPLACE VIEW vw_peminjaman_aktif AS
SELECT 
    p.id,
    p.kode_peminjaman,
    p.tanggal_pinjam,
    p.due_date,
    GREATEST(0, DATEDIFF(p.due_date, CURRENT_DATE)) AS hari_tersisa,
    IF(DATEDIFF(CURRENT_DATE, p.due_date) > 0, 'telat', 'tepat waktu') AS status_waktu,
    u.nama AS nama_peminjam,
    u.email AS email_peminjam,
    u.no_identitas,
    b.judul_buku,
    b.isbn,
    rt.kode_eksemplar,
    s.nama AS nama_staff,
    p.status,
    p.catatan
FROM ts_peminjaman p
INNER JOIN users u ON p.user_id = u.id AND u.is_deleted = 0
INNER JOIN books b ON p.book_id = b.id AND b.is_deleted = 0
INNER JOIN rt_book_uid rt ON p.uid_buffer_id = rt.uid_buffer_id AND rt.is_deleted = 0
INNER JOIN users s ON p.staff_id = s.id AND s.is_deleted = 0
WHERE p.status IN ('dipinjam', 'telat')
  AND p.is_deleted = 0;

-- View: Denda yang belum dibayar
CREATE OR REPLACE VIEW vw_denda_belum_dibayar AS
SELECT 
    d.id,
    d.kode_denda,
    u.nama AS nama_user,
    u.email,
    u.no_identitas,
    d.jumlah_denda,
    d.jenis_denda,
    d.catatan,
    d.created_at AS tanggal_denda
FROM ts_denda d
INNER JOIN users u ON d.user_id = u.id
WHERE d.status_pembayaran = 'belum_dibayar'
  AND d.is_deleted = FALSE
ORDER BY d.created_at DESC;

-- View: Buku dengan info lengkap
CREATE OR REPLACE VIEW vw_books_lengkap AS
SELECT 
    b.id,
    b.judul_buku,
    b.isbn,
    p.nama_penerbit,
    GROUP_CONCAT(DISTINCT a.nama_pengarang SEPARATOR ', ') AS pengarang,
    GROUP_CONCAT(DISTINCT c.nama_kategori SEPARATOR ', ') AS kategori,
    b.tahun_terbit,
    b.jumlah_eksemplar,
    b.eksemplar_tersedia,
    b.lokasi_rak,
    b.deskripsi,
    b.created_at
FROM books b
LEFT JOIN publishers p ON b.publisher_id = p.id
LEFT JOIN rt_book_author rba ON b.id = rba.book_id
LEFT JOIN authors a ON rba.author_id = a.id
LEFT JOIN rt_book_category rbc ON b.id = rbc.book_id
LEFT JOIN categories c ON rbc.category_id = c.id
WHERE b.is_deleted = FALSE
GROUP BY b.id;


-- ========================================
-- STORED PROCEDURES
-- ========================================

-- Procedure: Process peminjaman
DELIMITER $$
CREATE PROCEDURE sp_process_peminjaman(
    IN p_user_id INT,
    IN p_book_id INT,
    IN p_uid_buffer_id INT,
    IN p_staff_id INT,
    IN p_durasi_hari INT,
    OUT p_result VARCHAR(500)
)
BEGIN
    DECLARE v_eksemplar_tersedia INT;
    DECLARE v_user_status VARCHAR(20);
    DECLARE v_current_loans INT;
    DECLARE v_max_peminjaman INT;
    DECLARE v_due_date DATETIME;
    
    -- Check eksemplar tersedia
    SELECT eksemplar_tersedia INTO v_eksemplar_tersedia
    FROM books WHERE id = p_book_id AND is_deleted = FALSE;
    
    IF v_eksemplar_tersedia <= 0 THEN
        SET p_result = 'ERROR: Tidak ada eksemplar tersedia';
        ROLLBACK;
    ELSE
        -- Check user status
        SELECT status, max_peminjaman INTO v_user_status, v_max_peminjaman
        FROM users WHERE id = p_user_id;
        
        IF v_user_status != 'aktif' THEN
            SET p_result = 'ERROR: User tidak aktif atau suspended';
            ROLLBACK;
        ELSE
            -- Check current loans
            SELECT COUNT(*) INTO v_current_loans
            FROM ts_peminjaman
            WHERE user_id = p_user_id 
              AND status = 'dipinjam' 
              AND is_deleted = FALSE;
            
            IF v_current_loans >= v_max_peminjaman THEN
                SET p_result = 'ERROR: User sudah mencapai batas maksimal peminjaman';
                ROLLBACK;
            ELSE
                -- Calculate due date
                SET v_due_date = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL p_durasi_hari DAY);
                
                -- Insert peminjaman
                INSERT INTO ts_peminjaman (
                    user_id, book_id, uid_buffer_id, staff_id, 
                    tanggal_pinjam, due_date, status
                ) VALUES (
                    p_user_id, p_book_id, p_uid_buffer_id, p_staff_id,
                    CURRENT_TIMESTAMP, v_due_date, 'dipinjam'
                );
                
                SET p_result = CONCAT('SUCCESS: Peminjaman berhasil. Kode: ', 
                                     (SELECT kode_peminjaman FROM ts_peminjaman WHERE id = LAST_INSERT_ID()));
            END IF;
        END IF;
    END IF;
END$$
DELIMITER ;
