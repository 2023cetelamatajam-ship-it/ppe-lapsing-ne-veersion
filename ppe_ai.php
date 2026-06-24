<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'health') {
    echo json_encode([
        'status' => 'ok',
        'service' => 'PPE Depreciation Calculator',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action !== 'chat') {
    http_response_code(404);
    echo json_encode(['error' => 'Unsupported action.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_login();

if (!user_is_accounting_staff()) {
    http_response_code(403);
    echo json_encode(['error' => 'Only Accounting Staff may access this depreciation chatbot.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST requests are accepted.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode((string) file_get_contents('php://input'), true);
$question = trim((string) ($body['question'] ?? ''));
$branch = trim((string) ($body['branch'] ?? $_GET['branch'] ?? ''));
$brand = trim((string) ($body['brand'] ?? $_GET['brand'] ?? ''));

if ($question === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide a depreciation question.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    $assets = hydrate_assets_with_metrics(fetch_assets($pdo));
    $branches = collect_branch_values($assets);
    $brands = collect_brand_values($assets);

    $detectedBranch = detect_filter_value($question, $branches) ?? $branch;
    $detectedBrand = detect_filter_value($question, $brands) ?? $brand;
    $filteredAssets = filter_assets_by_branch_and_brand($assets, $detectedBranch, $detectedBrand);
    $metrics = build_dashboard_metrics($filteredAssets);

    $answer = build_depreciation_answer($question, $filteredAssets, $metrics, $detectedBranch, $detectedBrand);

    echo json_encode(['answer' => $answer], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to process the depreciation request.'], JSON_UNESCAPED_UNICODE);
}

function detect_filter_value(string $question, array $values): ?string
{
    $lowerQuestion = mb_strtolower($question, 'UTF-8');

    foreach ($values as $value) {
        if ($value === '') {
            continue;
        }

        $lowerValue = mb_strtolower($value, 'UTF-8');
        if (mb_strpos($lowerQuestion, $lowerValue) !== false) {
            return $value;
        }
    }

    return null;
}

function build_depreciation_answer(string $question, array $assets, array $metrics, string $branch, string $brand): string
{
    $contextParts = [];
    if ($branch !== '') {
        $contextParts[] = 'branch ' . $branch;
    }
    if ($brand !== '') {
        $contextParts[] = 'company ' . $brand;
    }
    $context = $contextParts !== [] ? 'Based only on PPE system data for ' . implode(' and ', $contextParts) . ', ' : 'Based only on PPE system data, ';

    if (count($assets) === 0) {
        return $context . 'there are no matching assets in the depreciation dataset.';
    }

    $questionLower = mb_strtolower($question, 'UTF-8');
    $parts = [];
    $interval = detect_depreciation_interval($questionLower);

    if ($interval !== null) {
        $intervalTotal = compute_total_interval_depreciation($assets, $interval);
        $parts[] = sprintf('Total %s depreciation is %s for %d assets.', $interval, money($intervalTotal), count($assets));

        if (str_contains($questionLower, 'per asset') || str_contains($questionLower, 'each asset')) {
            $average = count($assets) > 0 ? $intervalTotal / count($assets) : 0.0;
            $parts[] = 'Average per asset is ' . money($average) . '.';
        }
    }

    if (str_contains($questionLower, 'accumulated') || str_contains($questionLower, 'accumulation')) {
        $parts[] = 'Accumulated depreciation is ' . money($metrics['total_accumulated']) . '.';
    }

    if (str_contains($questionLower, 'net') || str_contains($questionLower, 'carrying')) {
        $parts[] = 'Net carrying value is ' . money($metrics['total_carrying']) . '.';
    }

    if (($interval === null && (str_contains($questionLower, 'annual') || str_contains($questionLower, 'yearly') || str_contains($questionLower, 'per year'))) || str_contains($questionLower, 'annual depreciation')) {
        $annual = compute_total_annual_depreciation($assets);
        $parts[] = 'Annual depreciation is ' . money($annual) . '.';
    }

    if (str_contains($questionLower, 'cost') || str_contains($questionLower, 'value')) {
        $parts[] = 'Total cost is ' . money(array_sum(array_map(fn($asset) => asset_total_cost($asset), $assets))) . '.';
    }

    if (str_contains($questionLower, 'fully depreciated') || str_contains($questionLower, 'fully depreciate')) {
        $parts[] = 'There are ' . $metrics['fully_depreciated_count'] . ' fully depreciated assets.';
    }

    if ($parts === []) {
        $annual = compute_total_annual_depreciation($assets);
        $parts[] = 'The selected assets have ' . count($assets) . ' records with ' . money($metrics['total_cost']) . ' total cost, ' . money($metrics['total_accumulated']) . ' accumulated depreciation, and ' . money($metrics['total_carrying']) . ' net carrying value.';
        $parts[] = 'Total annual depreciation is ' . money($annual) . '.';
    }

    return $context . implode(' ', $parts);
}

function detect_depreciation_interval(string $question): ?string
{
    if (str_contains($question, 'daily')) {
        return 'daily';
    }
    if (str_contains($question, 'weekly')) {
        return 'weekly';
    }
    if (str_contains($question, 'monthly')) {
        return 'monthly';
    }
    if (str_contains($question, 'quarterly') || str_contains($question, 'quarter')) {
        return 'quarterly';
    }
    if (str_contains($question, 'yearly') || str_contains($question, 'annual') || str_contains($question, 'per year')) {
        return 'yearly';
    }
    return null;
}

function compute_total_interval_depreciation(array $assets, string $interval): float
{
    $total = 0.0;
    foreach ($assets as $asset) {
        $intervals = depreciation_intervals_from_asset($asset);
        if (isset($intervals[$interval])) {
            $total += (float) $intervals[$interval];
        }
    }
    return round($total, 2);
}

function compute_total_annual_depreciation(array $assets): float
{
    $total = 0.0;
    foreach ($assets as $asset) {
        $total += (float) ($asset['annual_depreciation'] ?? 0);
    }
    return round($total, 2);
}
