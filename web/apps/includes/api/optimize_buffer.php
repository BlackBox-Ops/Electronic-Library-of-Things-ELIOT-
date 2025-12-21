<?php
/**
 * optimize_buffer.php - API untuk memanggil Q-Learning middleware
 * Path: web/apps/includes/api/optimize_buffer.php
 * 
 * Fungsi:
 * - Ambil queue_length dari Redis atau parameter GET
 * - Call FastAPI di http://localhost:8000/predict
 * - Simpan hasil ke Redis (optimal_buffer_size)
 * - Return response JSON lengkap + error handling kuat
 */

require_once '../../../includes/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Ambil parameter
$queue_length_param = isset($_GET['queue_length']) ? intval($_GET['queue_length']) : null;
$current_buffer_param = isset($_GET['current_buffer']) ? intval($_GET['current_buffer']) : 100;

// Inisialisasi Redis
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    error_log("[OPTIMIZE BUFFER] Redis connected successfully");
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal koneksi ke Redis: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    error_log("[OPTIMIZE BUFFER ERROR] Redis connection failed: " . $e->getMessage());
    exit;
}

// Ambil queue length dari Redis jika tidak di-pass manual
if ($queue_length_param === null) {
    try {
        $queue_length = $redis->llen('uid_pending_queue'); // Ganti key jika berbeda
        error_log("[OPTIMIZE BUFFER] Queue length dari Redis: $queue_length");
    } catch (Exception $e) {
        $queue_length = 0;
        error_log("[OPTIMIZE BUFFER] Gagal baca queue length: " . $e->getMessage());
    }
} else {
    $queue_length = $queue_length_param;
}

// Validasi
if ($queue_length < 0) $queue_length = 0;
if ($current_buffer_param < 20) $current_buffer_param = 20;
if ($current_buffer_param > 200) $current_buffer_param = 200;

// URL FastAPI
$url = "http://localhost:8000/predict?queue_length={$queue_length}&current_buffer={$current_buffer_param}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Timeout lebih panjang
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'ELIOT-OptimizeBuffer/1.0');

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    $error_msg = "Gagal call Q-Learning middleware: " . $curl_error;
    echo json_encode([
        'success' => false,
        'message' => $error_msg,
        'debug' => [
            'url' => $url,
            'curl_error' => $curl_error
        ]
    ]);
    error_log("[OPTIMIZE BUFFER ERROR] " . $error_msg);
    exit;
}

if ($http_code !== 200) {
    $error_msg = "Middleware return HTTP $http_code";
    echo json_encode([
        'success' => false,
        'message' => $error_msg,
        'debug' => [
            'http_code' => $http_code,
            'response' => $response
        ]
    ]);
    error_log("[OPTIMIZE BUFFER ERROR] $error_msg | Response: $response");
    exit;
}

$prediction = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'Response dari middleware bukan JSON valid',
        'raw_response' => $response
    ]);
    error_log("[OPTIMIZE BUFFER ERROR] Invalid JSON: " . json_last_error_msg());
    exit;
}

if (isset($prediction['new_buffer_size']) && is_int($prediction['new_buffer_size'])) {
    try {
        $redis->set('optimal_buffer_size', $prediction['new_buffer_size']);
        error_log("[OPTIMIZE BUFFER SUCCESS] New buffer size: {$prediction['new_buffer_size']} (Queue: {$prediction['queue_length']}, Action: {$prediction['action']})");
    } catch (Exception $e) {
        error_log("[OPTIMIZE BUFFER] Gagal simpan ke Redis: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'data' => $prediction,
        'message' => 'Buffer size berhasil dioptimalkan oleh Q-Learning agent',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Prediction gagal: new_buffer_size tidak ditemukan',
        'raw_response' => $prediction
    ]);
    error_log("[OPTIMIZE BUFFER ERROR] Invalid prediction format: " . json_encode($prediction));
}

$redis->close();
exit;
?>