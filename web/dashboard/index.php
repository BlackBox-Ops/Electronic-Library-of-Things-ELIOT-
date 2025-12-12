<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();

$role = $_SESSION['role'];

switch ($role) {
    case 'member':
        redirect('dashboard/member/index.php');
        break;
    case 'staff':
    case 'admin':
        redirect('dashboard/admin/index.php');
        break;
    default:
        $auth = new Auth($conn);
        $auth->logout();
}
?>