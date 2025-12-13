<!-- Tambahkan menu aktivasi user -->
<li class="nav-item">
    <a class="nav-link" href="admin/activate_users.php">
        <i class="fas fa-user-check"></i> Aktivasi User
        <?php
        // Hitung user yang suspended
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE status = 'suspended' AND is_deleted = 0");
        $stmt->execute();
        $result = $stmt->get_result();
        $suspended_count = $result->fetch_row()[0];
        $stmt->close();
        
        if ($suspended_count > 0): ?>
        <span class="badge bg-warning text-dark ms-2"><?= $suspended_count ?></span>
        <?php endif; ?>
    </a>
</li>