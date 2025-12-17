<?php
// includes/sidebar.php

// Menu berdasarkan role
$menu = [
    'admin' => [
        ['icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard', 'link' => 'dashboard.php', 'folder' => ''],
        ['header' => 'Manajemen Aset'],
        ['icon' => 'fas fa-boxes', 'title' => 'Inventaris', 'link' => 'inventory.php', 'folder' => 'admin'],
        ['icon' => 'fas fa-tags', 'title' => 'Kategori', 'link' => 'categories.php', 'folder' => ''],
        ['icon' => 'fas fa-qrcode', 'title' => 'Cetak RFID', 'link' => 'printLabels.php', 'folder' => ''],
        ['header' => 'Pengguna'],
        ['icon' => 'fas fa-users', 'title' => 'Daftar Pengguna', 'link' => 'users.php', 'folder' => ''],
        ['icon' => 'fas fa-user-shield', 'title' => 'Roles', 'link' => 'roles.php', 'folder' => 'admin'],
        ['header' => 'Laporan'],
        ['icon' => 'fas fa-file-alt', 'title' => 'Laporan Aset', 'link' => 'reports.php', 'folder' => ''],
        ['icon' => 'fas fa-history', 'title' => 'Log Aktivitas', 'link' => 'transactionsLog.php', 'folder' => ''],
    ],
    'staff' => [
        ['icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard', 'link' => 'dashboard.php', 'folder' => ''],
        ['header' => 'Operasional'],
        ['icon' => 'fas fa-boxes', 'title' => 'Inventaris', 'link' => 'inventory.php', 'folder' => ''],
    ],
    'member' => [
        ['icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard', 'link' => 'dashboard.php', 'folder' => ''],
        ['header' => 'Pencarian'],
        ['icon' => 'fas fa-search', 'title' => 'Cari Aset', 'link' => 'searchAssets.php', 'folder' => ''],
    ]
];

$activeMenu = $menu[$userRole] ?? $menu['member'];
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?= $baseUrl ?>/apps/dashboard.php">
            <img src="<?= $baseUrl ?>/apps/assets/img/rfid.png" alt="ELIOT" class="sidebar-logo">
            <span class="sidebar-title">ELIOT</span>
        </a>
    </div>

    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <?php foreach ($activeMenu as $item): ?>
                <?php if (isset($item['header'])): ?>
                    <li class="nav-header"><?= $item['header'] ?></li>
                <?php else: ?>
                    <?php 
                        // Tentukan path berdasarkan folder
                        $folder = $item['folder'] ?? '';
                        $fullPath = $baseUrl . '/apps/' . ($folder ? $folder . '/' : '') . $item['link'];
                        $isActive = $currentPage === $item['link'];
                    ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= $fullPath ?>">
                            <i class="<?= $item['icon'] ?>"></i>
                            <?= $item['title'] ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="sidebar-footer">
        <a href="<?= $baseUrl ?>/apps/profile.php" class="nav-link">
            <i class="fas fa-user-circle"></i> Profil Saya
        </a>
        <a href="<?= $baseUrl ?>/logout.php" class="btn btn-logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>