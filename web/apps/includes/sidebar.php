<?php
// includes/sidebar.php

// Menu berdasarkan role
$menu = [
    'admin' => [
        ['icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard', 'link' => 'dashboard.php', 'folder' => ''],
        ['header' => 'Manajemen Aset'],
        ['icon' => 'fas fa-boxes', 'title' => 'Inventaris', 'link' => 'inventory.php', 'folder' => 'admin'],
        ['icon' => 'fas fa-book-reader', 'title' => 'Peminjaman', 'link' => 'peminjaman/', 'folder' => 'admin'], // trailing slash penting
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

// Ambil informasi path saat ini
$currentScript = $_SERVER['PHP_SELF'];                  // contoh: /eliot/web/apps/admin/peminjaman/index.php
$currentPage   = basename($currentScript);              // hasil: index.php
$currentDir    = dirname($currentScript);               // hasil: /eliot/web/apps/admin/peminjaman
$currentFolder = basename($currentDir);                 // hasil: peminjaman
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
                    <li class="nav-header"><?= htmlspecialchars($item['header']) ?></li>
                <?php else: ?>
                    <?php 
                        $folder = $item['folder'] ?? '';
                        $link   = $item['link'];

                        // Logic penentuan active yang lebih akurat
                        $isActive = false;

                        // Case 1: Link file langsung (misal dashboard.php, users.php)
                        if ($currentPage === $link) {
                            $isActive = true;
                        }

                        // Case 2: Link folder dengan trailing slash (misal peminjaman/)
                        // Cek apakah current directory mengandung folder tersebut
                        if (str_ends_with($link, '/') && strpos($currentDir, rtrim($link, '/')) !== false) {
                            $isActive = true;
                        }

                        // Case 3: Backup untuk folder tanpa trailing slash atau index.php
                        $cleanLink = rtrim($link, '/');
                        if ($currentFolder === $cleanLink || $currentPage === $cleanLink . '.php') {
                            $isActive = true;
                        }

                        // Bangun full URL
                        $fullPath = $baseUrl . '/apps/' . ($folder ? $folder . '/' : '') . $link;
                    ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= htmlspecialchars($fullPath) ?>">
                            <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                            <?= htmlspecialchars($item['title']) ?>
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