<?php
// ~/Documents/ELIOT/web/apps/includes/sidebar.php

// Definisikan Menu Navigasi berdasarkan Role
$menu = [
    'admin' => [
        ['icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard', 'link' => 'dashboard.php'],
        ['header' => 'Manajemen Aset'],
        ['icon' => 'fas fa-boxes', 'title' => 'Inventaris Aset', 'link' => 'inventory.php'],
        ['icon' => 'fas fa-tags', 'title' => 'Kategori & Item', 'link' => 'categories.php'],
        ['icon' => 'fas fa-qrcode', 'title' => 'Cetak Label RFID', 'link' => 'printLabels.php'],
        ['header' => 'Manajemen Pengguna'],
        ['icon' => 'fas fa-users', 'title' => 'Pengguna Sistem', 'link' => 'users.php'],
        ['icon' => 'fas fa-user-shield', 'title' => 'Akses & Role', 'link' => 'roles.php'],
        ['header' => 'Log & Laporan'],
        ['icon' => 'fas fa-file-alt', 'title' => 'Laporan Aset', 'link' => 'reports.php'],
        ['icon' => 'fas fa-history', 'title' => 'Log Transaksi', 'link' => 'transactionsLog.php'],
    ],
    'staff' => [
        ['icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard', 'link' => 'dashboard.php'],
        ['header' => 'Operasional'],
        ['icon' => 'fas fa-boxes', 'title' => 'Cek Stok Inventaris', 'link' => 'inventory.php'],
        ['icon' => 'fas fa-exchange-alt', 'title' => 'Keluar/Masuk Barang', 'link' => 'transactionProcess.php'],
        ['icon' => 'fas fa-truck-loading', 'title' => 'Penerimaan Aset Baru', 'link' => 'receiving.php'],
        ['header' => 'Laporan Singkat'],
        ['icon' => 'fas fa-file-alt', 'title' => 'Riwayat Transaksi', 'link' => 'transactionsLog.php'],
    ],
    'member' => [
        ['icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard Saya', 'link' => 'dashboard.php'],
        ['header' => 'Pencarian'],
        ['icon' => 'fas fa-search', 'title' => 'Cari Aset', 'link' => 'searchAssets.php'],
        ['header' => 'Pinjaman'],
        ['icon' => 'fas fa-shopping-basket', 'title' => 'Aset Yang Dipinjam', 'link' => 'myBorrowed.php'],
        ['icon' => 'fas fa-undo', 'title' => 'Permintaan Pengembalian', 'link' => 'returnRequest.php'],
    ]
];

// Tentukan menu yang akan digunakan dan halaman aktif
$activeMenu = $menu[$userRole] ?? $menu['member']; // Default ke member
$currentPage = basename($_SERVER['PHP_SELF']);

?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?= $baseUrl ?>/apps/dashboard.php">
            <img src="<?= $baseUrl ?>/apps/assets/img/rfid.png" alt="ELIOT Logo" class="sidebar-logo">
            <span class="sidebar-title">ELIOT</span>
        </a>
    </div>
    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <?php foreach ($activeMenu as $item): ?>
                <?php if (isset($item['header'])): ?>
                    <li class="nav-header"><?= $item['header'] ?></li>
                <?php else: 
                    $isActive = $currentPage === $item['link'];
                    $fullLink = $baseUrl . '/apps/' . $item['link'];
                ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= $fullLink ?>">
                            <i class="<?= $item['icon'] ?> me-2"></i>
                            <?= $item['title'] ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="<?= $baseUrl ?>/apps/profile.php" class="nav-link">
            <i class="fas fa-user-circle me-2"></i> 
            Profil Saya
        </a>
        <a href="<?= $baseUrl ?>/logout.php" class="btn btn-logout w-100 mt-2">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</aside>