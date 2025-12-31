<?php
/**
 * API: Print Receipt (Struk Peminjaman)
 * Path: web/apps/includes/api/peminjaman/print_receipt.php
 * 
 * Input: ?batch=BATCH-YYYYMMDD-XXXX
 * Output: HTML page ready to print
 */

error_reporting(0);
ini_set('display_errors', 0);

require_once '../../../config.php';

// Get batch code from URL
$kodeBatch = $_GET['batch'] ?? null;

if (empty($kodeBatch)) {
    die('Kode batch tidak ditemukan');
}

// Get peminjaman data
$sql = "
    SELECT 
        p.kode_peminjaman,
        p.tanggal_pinjam,
        p.due_date,
        u.nama as member_nama,
        u.no_identitas,
        u.email,
        b.judul_buku,
        b.isbn,
        rbu.kode_eksemplar,
        rbu.kondisi,
        s.nama as staff_nama
    FROM ts_peminjaman p
    JOIN users u ON p.user_id = u.id
    JOIN books b ON p.book_id = b.id
    JOIN rt_book_uid rbu ON p.uid_buffer_id = rbu.uid_buffer_id
    JOIN users s ON p.staff_id = s.id
    WHERE p.catatan LIKE ?
      AND p.is_deleted = 0
    ORDER BY p.kode_peminjaman
";

$searchBatch = "%Batch: {$kodeBatch}%";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $searchBatch);
$stmt->execute();
$result = $stmt->get_result();

$peminjaman = [];
$member = null;
$staff = null;
$tanggalPinjam = null;
$dueDate = null;

while ($row = $result->fetch_assoc()) {
    if (!$member) {
        $member = [
            'nama' => $row['member_nama'],
            'no_identitas' => $row['no_identitas'],
            'email' => $row['email']
        ];
        $staff = $row['staff_nama'];
        $tanggalPinjam = $row['tanggal_pinjam'];
        $dueDate = $row['due_date'];
    }
    
    $peminjaman[] = [
        'kode' => $row['kode_peminjaman'],
        'judul' => $row['judul_buku'],
        'isbn' => $row['isbn'],
        'eksemplar' => $row['kode_eksemplar'],
        'kondisi' => $row['kondisi']
    ];
}

if (empty($peminjaman)) {
    die('Data peminjaman tidak ditemukan');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Peminjaman - <?= htmlspecialchars($kodeBatch) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .receipt {
            border: 2px solid #000;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 10px;
            margin: 2px 0;
        }
        .section {
            margin: 15px 0;
        }
        .section-title {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 8px;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }
        .info-label {
            font-weight: bold;
            width: 140px;
        }
        .info-value {
            flex: 1;
        }
        .books-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .books-table th,
        .books-table td {
            border: 1px solid #000;
            padding: 8px 5px;
            text-align: left;
        }
        .books-table th {
            background: #000;
            color: #fff;
            font-weight: bold;
            font-size: 11px;
        }
        .books-table td {
            font-size: 11px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px dashed #000;
            text-align: center;
        }
        .footer p {
            margin: 5px 0;
            font-size: 10px;
        }
        .signature {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        .signature-box {
            text-align: center;
            width: 45%;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 60px;
            padding-top: 5px;
        }
        .important {
            background: #f0f0f0;
            padding: 10px;
            border: 1px solid #000;
            margin: 15px 0;
            font-weight: bold;
            text-align: center;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        .print-button:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        🖨️ Cetak Struk
    </button>

    <div class="receipt">
        <div class="header">
            <h1>PERPUSTAKAAN ELIOT</h1>
            <p>Sistem Peminjaman RFID</p>
            <p>Jl. Contoh No. 123, Jakarta | Telp: (021) 1234-5678</p>
        </div>

        <div class="section">
            <div class="section-title">Informasi Transaksi</div>
            <div class="info-row">
                <span class="info-label">Kode Batch:</span>
                <span class="info-value"><?= htmlspecialchars($kodeBatch) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Tanggal Pinjam:</span>
                <span class="info-value"><?= date('d F Y H:i', strtotime($tanggalPinjam)) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Tanggal Kembali:</span>
                <span class="info-value"><?= date('d F Y', strtotime($dueDate)) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Durasi:</span>
                <span class="info-value">7 Hari</span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Data Peminjam</div>
            <div class="info-row">
                <span class="info-label">Nama:</span>
                <span class="info-value"><?= htmlspecialchars($member['nama']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">NIM/NIP:</span>
                <span class="info-value"><?= htmlspecialchars($member['no_identitas']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value"><?= htmlspecialchars($member['email']) ?></span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Daftar Buku (<?= count($peminjaman) ?> Buku)</div>
            <table class="books-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Kode Peminjaman</th>
                        <th>Judul Buku</th>
                        <th>Eksemplar</th>
                        <th style="width: 80px;">Kondisi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($peminjaman as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($item['kode']) ?></td>
                        <td><?= htmlspecialchars($item['judul']) ?></td>
                        <td><?= htmlspecialchars($item['eksemplar']) ?></td>
                        <td><?= ucfirst(str_replace('_', ' ', $item['kondisi'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="important">
            ⚠️ PENTING: Kembalikan buku sebelum <?= date('d F Y', strtotime($dueDate)) ?>
            <br>Denda keterlambatan: Rp 2.000 per hari per buku
        </div>

        <div class="section">
            <div class="section-title">Catatan</div>
            <p>✓ Periksa kondisi buku sebelum dibawa pulang</p>
            <p>✓ Jaga kebersihan dan jangan merusak buku</p>
            <p>✓ Hubungi perpustakaan jika ada kerusakan/kehilangan</p>
            <p>✓ Simpan struk ini sebagai bukti peminjaman</p>
        </div>

        <div class="signature">
            <div class="signature-box">
                <p>Peminjam</p>
                <div class="signature-line">
                    <?= htmlspecialchars($member['nama']) ?>
                </div>
            </div>
            <div class="signature-box">
                <p>Petugas</p>
                <div class="signature-line">
                    <?= htmlspecialchars($staff) ?>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Terima kasih telah menggunakan layanan perpustakaan</p>
            <p>Dicetak pada: <?= date('d F Y H:i:s') ?></p>
            <p style="margin-top: 10px;">═══════════════════════════════</p>
            <p style="font-size: 9px;">Dokumen ini adalah bukti sah peminjaman buku</p>
        </div>
    </div>

    <script>
        // Auto print on load (optional)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>