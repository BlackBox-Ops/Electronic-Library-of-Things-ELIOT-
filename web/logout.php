<?php
// ~/Documents/ELIOT/web/logout.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$auth = new Auth($conn);
$auth->logout();