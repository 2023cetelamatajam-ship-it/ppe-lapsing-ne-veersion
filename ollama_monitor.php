<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

$branch = trim((string) ($_GET['branch'] ?? ''));
$brand = trim((string) ($_GET['brand'] ?? ''));
$interval = trim((string) ($_GET['interval'] ?? 'monthly'));
$refresh = isset($_GET['refresh']) && (string) $_GET['refresh'] === '1';

$cacheDir = APP_ROOT . '/storage';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$cacheKey = md5($branch . '|' . $brand . '|' . $interval);
$cacheFile = $cacheDir . '/ollama_monitor_' . $cacheKey . '.json';
$ttl = defined('OLLAMA_CACHE_TTL') ? OLLAMA_CACHE_TTL : 60;

// Helper to compute fresh response and write cache
$computeAndCache = function () use ($pdo, $branch, $brand, $interval, $cacheFile) {
    $allAssets = fetch_assets($pdo);
    $filtered = filter_assets_by_branch_and_brand($allAssets, $branch !== '' ? $branch : null, $brand !== '' ? $brand : null);
    $filtered = hydrate_assets_with_metrics($filtered);

    $aggregate = aggregate_depreciation_totals($filtered, $interval);

    $perAsset = [];
    foreach ($filtered as $asset) {
        $intervals = depreciation_intervals_from_asset($asset);
        $perAsset[] = [
            'asset_id' => (int) ($asset['asset_id'] ?? 0),
            'asset_code' => $asset['asset_code'] ?? '',
            'asset_name' => $asset['asset_name'] ?? '',
            'branch' => get_asset_branch_name($asset),
            'company' => get_asset_brand_tag($asset),
            'intervals' => $intervals,
        ];
    }

    $ollamaSummary = '';
    // Prepare a concise prompt for Ollama if enabled
    $cfg = get_ollama_config();
    if (!empty($cfg['enabled'])) {
        $promptParts = [];
        $promptParts[] = sprintf('You are given depreciation monitoring data for %d assets.', count($filtered));
        $promptParts[] = sprintf('The total %s depreciation for the selected scope is %s PHP.', $interval, number_format($aggregate['total_depreciation'], 2));
        $promptParts[] = 'List any obvious risks or recommendations in one short paragraph.';
        $prompt = implode("\n", $promptParts);
        $ollamaSummary = call_ollama($prompt);
    }

    $response = [
        'count' => count($filtered),
        'interval' => $aggregate['interval'],
        'total_depreciation' => $aggregate['total_depreciation'],
        'assets' => $perAsset,
        'ollama_summary' => $ollamaSummary,
        'generated_at' => time(),
    ];

    @file_put_contents($cacheFile, json_encode($response), LOCK_EX);

    return $response;
};

// If explicit refresh requested, compute synchronously and return
if ($refresh) {
    $out = $computeAndCache();
    echo json_encode($out);
    exit;
}

// If cache exists and is fresh, return cached
if (is_file($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    $cached = json_decode(file_get_contents($cacheFile), true) ?: null;

    if ($age <= $ttl && $cached !== null) {
        echo json_encode($cached);
        exit;
    }

    // Cache is stale: trigger a background refresh (best-effort, non-blocking small timeout)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $refreshUrl = $protocol . '://' . $host . $path . '/ollama_monitor.php?refresh=1&branch=' . urlencode($branch) . '&brand=' . urlencode($brand) . '&interval=' . urlencode($interval);

    // fire-and-forget via curl with short timeout
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $refreshUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 700);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_exec($ch);
    curl_close($ch);

    // Return stale cached data while refresh runs
    if ($cached !== null) {
        echo json_encode($cached);
        exit;
    }
}

// No cache present: compute now
$out = $computeAndCache();

echo json_encode($out);
