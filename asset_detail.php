<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

header('Content-Type: application/json; charset=utf-8');

$assetId = isset($_GET['asset_id']) ? (int) $_GET['asset_id'] : 0;
if ($assetId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid asset id.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    $asset = fetch_asset_by_id($pdo, $assetId);

    if (!$asset) {
        http_response_code(404);
        echo json_encode(['error' => 'Asset not found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $metrics = get_asset_metrics($asset);
    $rows = fetch_depreciation_rows($pdo, $assetId);

    // Build schedule rows with net_value using helper
    $schedule = [];
    foreach ($rows as $row) {
        $schedule[] = [
            'depreciation_year' => (int) $row['depreciation_year'],
            'depreciation_expense' => (float) $row['depreciation_expense'],
            'accumulated_depreciation' => (float) $row['accumulated_depreciation'],
            'ending_value' => (float) $row['ending_value'],
            'net_value' => (float) schedule_display_net_value($asset, $row),
        ];
    }

    echo json_encode([
        'asset' => $asset,
        'metrics' => [
            'annual_depreciation' => (float) $metrics['annual_depreciation'],
            'accumulated_depreciation' => (float) $metrics['accumulated_depreciation'],
            'carrying_amount' => (float) $metrics['carrying_amount'],
        ],
        'schedule' => $schedule,
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load asset details.'], JSON_UNESCAPED_UNICODE);
    exit;
}
