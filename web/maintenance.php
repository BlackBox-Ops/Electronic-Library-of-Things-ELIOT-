<?php
// ~/Documents/ELIOT/web/maintenance.php
require_once __DIR__ . '/includes/config.php';

http_response_code(503);
header('Retry-After: 3600');

function getMaintenanceSettings($conn) {
    $query = "SELECT setting_key, setting_value, setting_type FROM system_settings WHERE setting_key LIKE 'maintenance_%'";
    $result = $conn->query($query);
    if (!$result) die('Query error: ' . $conn->error);

    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $key = str_replace('maintenance_', '', $row['setting_key']);
        $value = $row['setting_value'];

        switch ($row['setting_type']) {
            case 'boolean':
                $value = ($value === 'true' || $value === '1');
                break;
            case 'json':
                $value = json_decode($value, true) ?: [];
                break;
        }
        $settings[$key] = $value;
    }
    return $settings;
}

$settings = getMaintenanceSettings($conn);

$requested_uri = $_SERVER['REQUEST_URI'] ?? '/';
$page_path = parse_url($requested_uri, PHP_URL_PATH) ?? '/';
$page_name = basename($page_path);
if (empty($page_name) || $page_name === '/' || $page_name === 'web') $page_name = 'index';
$page_name = preg_replace('/\.(php|html|htm)$/', '', $page_name);

$page_config = $settings['page_config'] ?? [];
$page_info = $page_config[$page_name] ?? $page_config['default'] ?? [
    'title' => 'Halaman Sedang dalam Perawatan',
    'message' => 'Fitur yang Anda coba akses sedang diperbarui.',
    'progress' => 65,
    'eta' => 'Beberapa jam lagi',
    'icon' => 'fa-tools',
];

$base_url = SITE_URL;

$now = new DateTime();
$estimated_end = $settings['estimated_end'] ?? date('Y-m-d H:i:s', strtotime('+2 hours'));
$end_time = new DateTime($estimated_end);
$interval = $now->diff($end_time);

$is_complete = $now >= $end_time;
$hours_remaining = $interval->h + ($interval->days * 24);
$time_display = $is_complete ? '00:00:00' : sprintf('%02d:%02d:%02d', $hours_remaining, $interval->i, $interval->s);
?>

<!DOCTYPE html>
<html lang="id" data-theme="light"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - <?= htmlspecialchars($page_info['title']) ?> | ELIOT</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <link rel="stylesheet" href="<?= $base_url ?>/public/assets/css/maintenance.css?v=<?= time() ?>">
</head>
<body class="maintenance-body">

    <div class="maintenance-container">
        <div class="maintenance-card">

            <button class="btn btn-link theme-toggle-btn position-absolute top-0 end-0 m-3" id="themeToggle">
                <i class="fas fa-moon fs-5"></i>
            </button>

            <div class="maintenance-illustration">
                <svg width="320" height="320" viewBox="0 0 320 320" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="160" cy="160" r="140" fill="#f8f9fa" opacity="0.6"/>
                    
                    <g class="gear-large">
                        <circle cx="160" cy="160" r="80" fill="none" stroke="#628141" stroke-width="12"/>
                        <path d="M160 70 L160 50 M160 270 L160 290 M70 160 L50 160 M270 160 L290 160 
                                 M110 110 L95 95 M210 210 L225 225 M110 210 L95 225 M210 110 L225 95" 
                              stroke="#628141" stroke-width="10" stroke-linecap="round"/>
                    </g>

                    <g class="gear-small">
                        <circle cx="220" cy="100" r="40" fill="none" stroke="#628141" stroke-width="8"/>
                        <path d="M220 60 L220 45 M220 140 L220 155 M180 100 L165 100 M260 100 L275 100 
                                 M195 75 L185 65 M245 125 L255 135 M195 125 L185 135 M245 75 L255 65" 
                              stroke="#628141" stroke-width="7" stroke-linecap="round"/>
                    </g>

                    <circle cx="160" cy="160" r="110" fill="none" stroke="#e9ecef" stroke-width="12"/>
                    <circle cx="160" cy="160" r="110" fill="none" stroke="#628141" stroke-width="12"
                            stroke-dasharray="691" stroke-dashoffset="<?= 691 * (1 - ($page_info['progress'] / 100)) ?>"
                            stroke-linecap="round" transform="rotate(-90 160 160)" class="progress-ring"/>
                </svg>

                <div class="progress-text">
                    <h3 class="text-primary"><?= htmlspecialchars($page_info['title']) ?></h3>
                    <p class="progress-percent text-secondary"><?= $page_info['progress'] ?>% Selesai</p>
                </div>
            </div>

            <div class="maintenance-content">
                <div class="content-header">
                    <span class="maintenance-badge">
                        <i class="fas fa-tools"></i> MODE PERAWATAN
                    </span>
                </div>

                <h1 class="text-primary"><?= htmlspecialchars($page_info['title']) ?></h1>
                <p class="maintenance-message text-secondary">
                    <?= htmlspecialchars($page_info['message']) ?>
                </p>

                <div class="progress-container mb-4">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width: <?= $page_info['progress'] ?>%"></div>
                    </div>
                    <small class="progress-label text-secondary"><?= $page_info['progress'] ?>% tercapai</small>
                </div>

                <div class="countdown-container">
                    <div class="countdown-title text-secondary">
                        <i class="fas fa-clock"></i> Estimasi waktu tersisa
                    </div>
                    <div class="countdown-timer text-primary" id="countdownDisplay" data-complete="<?= $is_complete ? '1' : '0' ?>">
                        <?= $time_display ?>
                    </div>
                </div>

                <div class="action-buttons">
                    <button class="btn-notify-action btn-mini" id="notifyBtn">
                        <i class="fas fa-bell"></i>
                        Beritahu Saya Saat Selesai
                    </button>

                    <div class="secondary-buttons">
                        <a href="<?= $base_url ?>/index.php" class="btn-secondary-mini btn-mini">
                            <i class="fas fa-home"></i> Kembali ke Home
                        </a>
                        <button onclick="window.location.reload()" class="btn-secondary-mini btn-mini">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="maintenance-footer">
                    <p class="text-secondary">&copy; 2025 <strong>ELIOT</strong> — Sistem sedang diperbarui untuk pengalaman yang lebih baik.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $base_url ?>/public/assets/js/maintenance.js?v=<?= time() ?>"></script>
    <script>
        const html = document.documentElement;

        function updateThemeIcon(theme) {
            const icon = document.getElementById('themeToggle')?.querySelector('i');
            if (icon) {
                if (theme === 'dark') {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                } else {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                }
            }
        }

        /**
         * Mengaplikasikan tema: preferensi user (localStorage) > preferensi sistem
         */
        function applyTheme(theme) {
            html.setAttribute('data-theme', theme);
            updateThemeIcon(theme);
        }

        function getSystemTheme() {
            // Fungsi untuk mendapatkan tema sistem
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        function setThemeOnLoad() {
            const savedTheme = localStorage.getItem('theme');
            
            if (savedTheme) {
                // 1. Jika ada di localStorage (manual override/sync antar page), gunakan itu
                applyTheme(savedTheme);
            } else {
                // 2. Jika tidak ada, gunakan preferensi sistem (Auto-Sync)
                applyTheme(getSystemTheme());
            }
        }
        
        // Listener untuk perubahan tema sistem (Media Query)
        // Ini yang membuat dark mode otomatis tanpa refresh jika tema sistem berubah
        const systemThemeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        systemThemeQuery.addEventListener('change', (e) => {
            // Hanya ganti tema jika user belum menyetel preferensi manual di localStorage
            if (!localStorage.getItem('theme')) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });

        // Theme Toggle dengan localStorage
        document.getElementById('themeToggle').addEventListener('click', function() {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            // Terapkan dan Simpan preferensi manual di localStorage
            applyTheme(newTheme);
            localStorage.setItem('theme', newTheme);
        });

        // Set tema saat halaman dimuat
        document.addEventListener('DOMContentLoaded', setThemeOnLoad);

        // Logic Countdown Timer
        function updateCountdown() {
            const countdownDisplay = document.getElementById('countdownDisplay');
            if (!countdownDisplay || countdownDisplay.getAttribute('data-complete') === '1') return;

            const now = new Date(<?= json_encode($now->format('Y-m-d H:i:s')) ?>).getTime();
            const endTime = new Date(<?= json_encode($estimated_end) ?>).getTime();
            const distance = endTime - Date.now() + (Date.now() - now);

            if (distance < 0) {
                countdownDisplay.innerHTML = '00:00:00';
                countdownDisplay.setAttribute('data-complete', '1');
                clearInterval(countdownInterval);
                return;
            }

            const totalSeconds = Math.floor(distance / 1000);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;

            countdownDisplay.innerHTML = 
                `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }

        updateCountdown();
        const countdownInterval = setInterval(updateCountdown, 1000);
        
        // Kode untuk menangani tombol NotifyBtn agar tidak spam alert
        document.getElementById('notifyBtn').addEventListener('click', function() {
             console.log('Permintaan notifikasi telah diterima.');
             
             // Ganti tombol menjadi "Permintaan Dikirim" dan nonaktifkan
             this.innerHTML = '<i class="fas fa-check"></i> Permintaan Dikirim';
             this.disabled = true;
        });

    </script>
</body>
</html>