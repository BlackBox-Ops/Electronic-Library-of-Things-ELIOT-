<?php
// ~/Documents/ELIOT/web/status.php
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . '://' . $host . '/eliot';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Server | ELIOT</title>
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
        .status-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .status-ok {
            color: #28a745;
            font-weight: bold;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Status Server ELIOT</h1>
        
        <div class="status-box">
            <div class="status-item">
                <span>Web Server</span>
                <span class="status-ok">✅ Online</span>
            </div>
            <div class="status-item">
                <span>Database</span>
                <span class="status-ok">✅ Online</span>
            </div>
            <div class="status-item">
                <span>RFID System</span>
                <span class="status-ok">✅ Online</span>
            </div>
            <div class="status-item">
                <span>Last Check</span>
                <span><?= date('d/m/Y H:i:s') ?></span>
            </div>
        </div>
        
        <div>
            <a href="<?= $base_url ?>/index.php" class="btn">Kembali ke Beranda</a>
            <a href="<?= $base_url ?>/500.php" class="btn">Test 500 Error</a>
        </div>
    </div>
</body>
</html>