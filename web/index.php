<?php
// ~/Documents/ELIOT/web/index.php
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . '://' . $host . '/eliot';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELIOT - Electronic Library of Things</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        h1 {
            color: #628141;
            margin-bottom: 20px;
        }
        .btn {
            background: #628141;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
            cursor: pointer;
        }
        .btn:hover {
            background: #506834;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏠 ELIOT - Beranda</h1>
        <p>Ini adalah halaman beranda sementara untuk testing.</p>
        
        <div>
            <a href="<?= $base_url ?>/login.php" class="btn">Login</a>
            <a href="<?= $base_url ?>/404.php" class="btn">Test 404</a>
            <a href="<?= $base_url ?>/500.php" class="btn">Test 500</a>
        </div>
        
        <p style="margin-top: 30px; font-size: 12px; color: #888;">
            Electronic Library of Things - Sistem Manajemen Perpustakaan Berbasis RFID
        </p>
    </div>
</body>
</html>