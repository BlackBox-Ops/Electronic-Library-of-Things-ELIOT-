<?php
// includes/sidebar.php

// Menu berdasarkan role (contoh admin)
$menu = [
    'admin' => [
        ['icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard', 'link' => 'dashboard.php'],
        ['header' => 'Manajemen Aset'],
        ['icon' => 'fas fa-boxes', 'title' => 'Inventaris', 'link' => 'inventory.php'],
        ['icon' => 'fas fa-tags', 'title' => 'Kategori', 'link' => 'categories.php'],
        ['icon' => 'fas fa-qrcode', 'title' => 'Cetak RFID', 'link' => 'printLabels.php'],
        ['header' => 'Pengguna'],
        ['icon' => 'fas fa-users', 'title' => 'Daftar Pengguna', 'link' => 'users.php'],
        ['icon' => 'fas fa-user-shield', 'title' => 'Roles', 'link' => 'roles.php'],
        ['header' => 'Laporan'],
        ['icon' => 'fas fa-file-alt', 'title' => 'Laporan Aset', 'link' => 'reports.php'],
        ['icon' => 'fas fa-history', 'title' => 'Log Aktivitas', 'link' => 'transactionsLog.php'],
    ]
];

$activeMenu = $menu[$userRole] ?? $menu['admin'];
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
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === $item['link']) ? 'active' : '' ?>" href="<?= $baseUrl ?>/apps/<?= $item['link'] ?>">
                            <i class="<?= $item['icon'] ?>"></i>
                            <?= $item['title'] ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="sidebar-footer">
        <a href="#" class="nav-link">
            <i class="fas fa-user-circle"></i> Profil Saya
        </a>
        <a href="#" class="btn btn-logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>