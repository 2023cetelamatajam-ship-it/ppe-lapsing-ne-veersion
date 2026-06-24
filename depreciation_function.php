<?php
declare(strict_types=1);

function count_users(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function fetch_users(PDO $pdo): array
{
    $statement = $pdo->query('SELECT user_id, full_name, email, role, created_at FROM users ORDER BY created_at DESC');

    return $statement->fetchAll() ?: [];
}

function fetch_user_by_id(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT user_id, full_name, email, role, created_at
         FROM users
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function update_user_password(PDO $pdo, int $userId, string $password): void
{
    $statement = $pdo->prepare('UPDATE users SET password = :password WHERE user_id = :user_id');
    $statement->execute([
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'user_id' => $userId,
    ]);
}

function fetch_categories(PDO $pdo): array
{
    $statement = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name');

    return $statement->fetchAll() ?: [];
}

function fetch_departments(PDO $pdo): array
{
    $statement = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name');

    return $statement->fetchAll() ?: [];
}

function fetch_asset_lookup(PDO $pdo): array
{
    $statement = $pdo->query('SELECT asset_id, asset_code, asset_name, location FROM assets ORDER BY asset_name, asset_code');

    return $statement->fetchAll() ?: [];
}

function fetch_asset_by_id(PDO $pdo, int $assetId): ?array
{
    $statement = $pdo->prepare(
        'SELECT a.*, c.category_name, d.department_name
         FROM assets a
         LEFT JOIN categories c ON c.category_id = a.category_id
         LEFT JOIN departments d ON d.department_id = a.department_id
         WHERE a.asset_id = :asset_id
         LIMIT 1'
    );
    $statement->execute(['asset_id' => $assetId]);
    $asset = $statement->fetch();

    return $asset ?: null;
}

function fetch_assets(PDO $pdo, array $filters = []): array
{
    $sql = 'SELECT a.*, c.category_name, d.department_name
            FROM assets a
            LEFT JOIN categories c ON c.category_id = a.category_id
            LEFT JOIN departments d ON d.department_id = a.department_id
            WHERE 1=1';
    $params = [];

    if (!empty($filters['q'])) {
        $sql .= ' AND (
            a.asset_code LIKE :query
            OR a.asset_name LIKE :query
            OR COALESCE(a.location, \'\') LIKE :query
            OR COALESCE(c.category_name, \'\') LIKE :query
            OR COALESCE(d.department_name, \'\') LIKE :query
        )';
        $params['query'] = '%' . trim((string) $filters['q']) . '%';
    }

    if (!empty($filters['status'])) {
        $sql .= ' AND a.status = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['category_id'])) {
        $sql .= ' AND a.category_id = :category_id';
        $params['category_id'] = (int) $filters['category_id'];
    }

    if (!empty($filters['department_id'])) {
        $sql .= ' AND a.department_id = :department_id';
        $params['department_id'] = (int) $filters['department_id'];
    }

    $sql .= ' ORDER BY a.acquisition_date DESC, a.asset_name ASC';

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll() ?: [];
}

function asset_total_cost(array $asset): float
{
    return round(
        (float) ($asset['acquisition_cost'] ?? 0) + (float) ($asset['additional_amount'] ?? 0),
        2
    );
}

function normalize_asset_payload(array $input): array
{
    return [
        'asset_code' => trim((string) ($input['asset_code'] ?? '')),
        'asset_name' => trim((string) ($input['asset_name'] ?? '')),
        'category_id' => ($input['category_id'] ?? '') !== '' ? (int) $input['category_id'] : null,
        'department_id' => ($input['department_id'] ?? '') !== '' ? (int) $input['department_id'] : null,
        'acquisition_date' => trim((string) ($input['acquisition_date'] ?? '')),
        'acquisition_cost' => (float) ($input['acquisition_cost'] ?? 0),
        'additional_amount' => (float) ($input['additional_amount'] ?? 0),
        'salvage_value' => (float) ($input['salvage_value'] ?? 0),
        'useful_life' => (int) ($input['useful_life'] ?? 0),
        'depreciation_method' => trim((string) ($input['depreciation_method'] ?? 'Straight-line')) ?: 'Straight-line',
        'location' => trim((string) ($input['location'] ?? '')),
        'status' => trim((string) ($input['status'] ?? 'Active')) ?: 'Active',
        'remarks' => trim((string) ($input['remarks'] ?? '')),
    ];
}

function validate_asset_payload(array $payload): array
{
    $errors = [];

    if ($payload['asset_code'] === '') {
        $errors[] = 'Asset code is required.';
    }

    if ($payload['asset_name'] === '') {
        $errors[] = 'Asset name is required.';
    }

    if ($payload['acquisition_date'] === '' || strtotime($payload['acquisition_date']) === false) {
        $errors[] = 'A valid acquisition date is required.';
    }

    if ($payload['acquisition_cost'] <= 0) {
        $errors[] = 'Acquisition cost must be greater than zero.';
    }

    if ($payload['additional_amount'] < 0) {
        $errors[] = 'Additional amount cannot be negative.';
    }

    if ($payload['salvage_value'] < 0) {
        $errors[] = 'Salvage value cannot be negative.';
    }

    if ($payload['salvage_value'] > ($payload['acquisition_cost'] + $payload['additional_amount'])) {
        $errors[] = 'Salvage value cannot exceed the total of acquisition cost and additional amount.';
    }

    if ($payload['useful_life'] <= 0) {
        $errors[] = 'Useful life must be at least 1 year.';
    }

    if (!in_array($payload['status'], ['Active', 'Disposed', 'Fully Depreciated'], true)) {
        $errors[] = 'Please select a valid status.';
    }

    return $errors;
}

function save_asset(PDO $pdo, array $payload, ?int $assetId = null): int
{
    $query = $assetId === null
        ? 'INSERT INTO assets (
                asset_code, asset_name, category_id, department_id, acquisition_date,
                acquisition_cost, additional_amount, salvage_value, useful_life, depreciation_method,
                location, status, remarks
            ) VALUES (
                :asset_code, :asset_name, :category_id, :department_id, :acquisition_date,
                :acquisition_cost, :additional_amount, :salvage_value, :useful_life, :depreciation_method,
                :location, :status, :remarks
            )'
        : 'UPDATE assets SET
                asset_code = :asset_code,
                asset_name = :asset_name,
                category_id = :category_id,
                department_id = :department_id,
                acquisition_date = :acquisition_date,
                acquisition_cost = :acquisition_cost,
                additional_amount = :additional_amount,
                salvage_value = :salvage_value,
                useful_life = :useful_life,
                depreciation_method = :depreciation_method,
                location = :location,
                status = :status,
                remarks = :remarks
            WHERE asset_id = :asset_id';

    $params = $payload;

    if ($assetId !== null) {
        $params['asset_id'] = $assetId;
    }

    try {
        $statement = $pdo->prepare($query);
        $statement->execute($params);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            throw new InvalidArgumentException('Asset code must be unique.');
        }

        throw $exception;
    }

    $savedAssetId = $assetId ?? (int) $pdo->lastInsertId();
    rebuild_depreciation_schedule($pdo, $savedAssetId);

    return $savedAssetId;
}

function delete_asset(PDO $pdo, int $assetId): void
{
    $statement = $pdo->prepare('DELETE FROM assets WHERE asset_id = :asset_id');
    $statement->execute(['asset_id' => $assetId]);
}

function calculate_annual_depreciation(float $cost, float $salvageValue, int $usefulLife): float
{
    if ($usefulLife <= 0 || $cost <= $salvageValue) {
        return 0.0;
    }

    return round(($cost - $salvageValue) / $usefulLife, 2);
}

function get_asset_depreciation_start_date(array $asset): ?DateTimeImmutable
{
    $acquisitionDate = (string) ($asset['acquisition_date'] ?? '');
    $timestamp = strtotime($acquisitionDate);

    if ($timestamp === false) {
        return null;
    }

    return (new DateTimeImmutable('@' . $timestamp))
        ->setTimezone(new DateTimeZone(date_default_timezone_get()))
        ->modify('first day of next month')
        ->setTime(0, 0, 0);
}

function asset_has_started_depreciation(array $asset, ?DateTimeImmutable $today = null): bool
{
    $today = $today ?? new DateTimeImmutable('now');
    $startDate = get_asset_depreciation_start_date($asset);

    return $startDate !== null && $today >= $startDate;
}

function schedule_display_net_value(array $asset, array $row): float
{
    return round(
        max(
            (float) ($row['ending_value'] ?? 0) - (float) ($asset['salvage_value'] ?? 0),
            0
        ),
        2
    );
}

function generate_depreciation_schedule(array $asset): array
{
    $cost = asset_total_cost($asset);
    $salvageValue = (float) ($asset['salvage_value'] ?? 0);
    $usefulLife = (int) ($asset['useful_life'] ?? 0);
    $acquisitionDate = (string) ($asset['acquisition_date'] ?? '');

    if ($usefulLife <= 0 || strtotime($acquisitionDate) === false) {
        return [];
    }

    $acquisitionYear = (int) date('Y', strtotime($acquisitionDate));
    $depreciationStartYear = $acquisitionYear + 1;
    $depreciableBase = max($cost - $salvageValue, 0);
    $annualDepreciation = calculate_annual_depreciation($cost, $salvageValue, $usefulLife);
    $accumulated = 0.0;
    $schedule = [
        [
            'depreciation_year' => $acquisitionYear,
            'beginning_value' => round($cost, 2),
            'depreciation_expense' => 0.0,
            'accumulated_depreciation' => 0.0,
            'ending_value' => round($cost, 2),
        ],
    ];

    for ($index = 0; $index < $usefulLife; $index++) {
        $year = $depreciationStartYear + $index;
        $beginningValue = round($cost - $accumulated, 2);
        $remainingDepreciableBase = round($depreciableBase - $accumulated, 2);
        $expense = $index === ($usefulLife - 1)
            ? max($remainingDepreciableBase, 0)
            : min($annualDepreciation, max($remainingDepreciableBase, 0));

        $accumulated = round($accumulated + $expense, 2);
        $endingValue = round(max($cost - $accumulated, $salvageValue), 2);

        $schedule[] = [
            'depreciation_year' => $year,
            'beginning_value' => $beginningValue,
            'depreciation_expense' => round($expense, 2),
            'accumulated_depreciation' => $accumulated,
            'ending_value' => $endingValue,
        ];
    }

    return $schedule;
}

function depreciation_schedule_needs_refresh(array $storedRows, array $generatedSchedule): bool
{
    if (count($storedRows) !== count($generatedSchedule)) {
        return true;
    }

    foreach ($generatedSchedule as $index => $row) {
        $storedRow = $storedRows[$index] ?? null;

        if ($storedRow === null) {
            return true;
        }

        if ((int) $storedRow['depreciation_year'] !== (int) $row['depreciation_year']) {
            return true;
        }

        foreach (['beginning_value', 'depreciation_expense', 'accumulated_depreciation', 'ending_value'] as $key) {
            if (round((float) $storedRow[$key], 2) !== round((float) $row[$key], 2)) {
                return true;
            }
        }
    }

    return false;
}

function rebuild_depreciation_schedule(PDO $pdo, int $assetId): void
{
    $asset = fetch_asset_by_id($pdo, $assetId);

    if (!$asset) {
        throw new InvalidArgumentException('Asset record not found.');
    }

    $schedule = generate_depreciation_schedule($asset);
    $insert = $pdo->prepare(
        'INSERT INTO depreciation_schedule (
            asset_id, depreciation_year, beginning_value, depreciation_expense,
            accumulated_depreciation, ending_value
        ) VALUES (
            :asset_id, :depreciation_year, :beginning_value, :depreciation_expense,
            :accumulated_depreciation, :ending_value
        )'
    );

    $pdo->beginTransaction();

    try {
        $delete = $pdo->prepare('DELETE FROM depreciation_schedule WHERE asset_id = :asset_id');
        $delete->execute(['asset_id' => $assetId]);

        foreach ($schedule as $row) {
            $insert->execute([
                'asset_id' => $assetId,
                'depreciation_year' => $row['depreciation_year'],
                'beginning_value' => $row['beginning_value'],
                'depreciation_expense' => $row['depreciation_expense'],
                'accumulated_depreciation' => $row['accumulated_depreciation'],
                'ending_value' => $row['ending_value'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function fetch_depreciation_rows(PDO $pdo, int $assetId): array
{
    $asset = fetch_asset_by_id($pdo, $assetId);

    if (!$asset) {
        return [];
    }

    $statement = $pdo->prepare(
        'SELECT schedule_id, asset_id, depreciation_year, beginning_value, depreciation_expense,
                accumulated_depreciation, ending_value
         FROM depreciation_schedule
         WHERE asset_id = :asset_id
         ORDER BY depreciation_year'
    );
    $statement->execute(['asset_id' => $assetId]);
    $rows = $statement->fetchAll() ?: [];
    $generatedSchedule = generate_depreciation_schedule($asset);

    if ($rows === [] || depreciation_schedule_needs_refresh($rows, $generatedSchedule)) {
        rebuild_depreciation_schedule($pdo, $assetId);
        $statement->execute(['asset_id' => $assetId]);
        $rows = $statement->fetchAll() ?: [];
    }

    return $rows;
}

function get_known_branches(): array
{
    return [
        'Mitsubishi Heights',
        'Mitsubishi Glamang',
        'Mitsubishi Kidapawan',
        'Fuso Gensan',
        'Hyundai Gensan',
    ];
}

function get_known_companies(): array
{
    return [
        'MICEI',
        'NTRprising',
    ];
}

function normalize_asset_branch_name(string $branch): string
{
    $branch = trim(mb_strtolower($branch, 'UTF-8'));
    if ($branch === '') {
        return 'Unassigned';
    }

    if (str_contains($branch, 'glamang')) {
        return 'Mitsubishi Glamang';
    }

    if (str_contains($branch, 'kidapawan')) {
        return 'Mitsubishi Kidapawan';
    }

    if (str_contains($branch, 'highway') || str_contains($branch, 'heights') || str_contains($branch, 'height')) {
        return 'Mitsubishi Heights';
    }

    if (str_contains($branch, 'fuso') && str_contains($branch, 'gensan')) {
        return 'Fuso Gensan';
    }

    if (str_contains($branch, 'hyundai') && str_contains($branch, 'gensan')) {
        return 'Hyundai Gensan';
    }

    if (str_contains($branch, 'gensan')) {
        if (str_contains($branch, 'fuso')) {
            return 'Fuso Gensan';
        }
        if (str_contains($branch, 'hyundai')) {
            return 'Hyundai Gensan';
        }
    }

    if (str_contains($branch, 'mitsubishi')) {
        if (str_contains($branch, 'glamang')) {
            return 'Mitsubishi Glamang';
        }
        if (str_contains($branch, 'kidapawan')) {
            return 'Mitsubishi Kidapawan';
        }
        if (str_contains($branch, 'highway') || str_contains($branch, 'heights') || str_contains($branch, 'height')) {
            return 'Mitsubishi Heights';
        }
    }

    return mb_convert_case(trim($branch), MB_CASE_TITLE, 'UTF-8');
}

function normalize_asset_company_name(string $company, string $branch = ''): string
{
    $company = trim(mb_strtolower($company, 'UTF-8'));

    if ($company !== '') {
        if (str_contains($company, 'micei') || str_contains($company, 'mitsubishi')) {
            return 'MICEI';
        }

        if (str_contains($company, 'ntr') || str_contains($company, 'fuso') || str_contains($company, 'hyundai')) {
            return 'NTRprising';
        }
    }

    if ($branch !== '') {
        if (str_starts_with($branch, 'Mitsubishi')) {
            return 'MICEI';
        }

        if (str_contains($branch, 'Gensan')) {
            return 'NTRprising';
        }
    }

    return 'Unassigned';
}

function get_asset_branch_name(array $asset): string
{
    $branch = trim((string) ($asset['branch_name'] ?? $asset['location'] ?? ''));
    return normalize_asset_branch_name($branch);
}

function get_asset_brand_tag(array $asset): string
{
    $rawBrand = trim((string) ($asset['brand_tag'] ?? $asset['company_name'] ?? $asset['department_name'] ?? ''));
    $branch = get_asset_branch_name($asset);
    return normalize_asset_company_name($rawBrand, $branch);
}

function collect_branch_values(array $assets): array
{
    $branches = [];
    foreach ($assets as $asset) {
        $branches[] = get_asset_branch_name($asset);
    }
    $branches = array_unique($branches);
    sort($branches, SORT_NATURAL | SORT_FLAG_CASE);

    return array_values($branches);
}

function collect_brand_values(array $assets): array
{
    $brands = [];
    foreach ($assets as $asset) {
        $brands[] = get_asset_brand_tag($asset);
    }
    $brands = array_unique($brands);
    sort($brands, SORT_NATURAL | SORT_FLAG_CASE);

    return array_values($brands);
}

function filter_assets_by_branch_and_brand(array $assets, ?string $branch, ?string $brand): array
{
    if ($branch !== null && $branch !== '') {
        $assets = array_filter($assets, fn($asset) => strcasecmp(get_asset_branch_name($asset), $branch) === 0);
    }

    if ($brand !== null && $brand !== '') {
        $assets = array_filter($assets, fn($asset) => strcasecmp(get_asset_brand_tag($asset), $brand) === 0);
    }

    return array_values($assets);
}

function get_asset_metrics(array $asset, ?int $year = null): array
{
    $evaluationYear = $year ?? CURRENT_YEAR;
    $cost = asset_total_cost($asset);
    $salvageValue = (float) ($asset['salvage_value'] ?? 0);
    $usefulLife = max((int) ($asset['useful_life'] ?? 0), 0);
    $annualDepreciation = calculate_annual_depreciation($cost, $salvageValue, $usefulLife);
    $schedule = generate_depreciation_schedule($asset);
    $status = (string) ($asset['status'] ?? 'Active');
    $acquisitionYear = strtotime((string) ($asset['acquisition_date'] ?? '')) !== false
        ? (int) date('Y', strtotime((string) $asset['acquisition_date']))
        : CURRENT_YEAR;
    $depreciationStartYear = $acquisitionYear + 1;

    $selectedRow = null;
    foreach ($schedule as $row) {
        if ((int) $row['depreciation_year'] <= $evaluationYear) {
            $selectedRow = $row;
        }
    }

    if ($selectedRow === null && $schedule !== [] && $evaluationYear >= (int) end($schedule)['depreciation_year']) {
        $selectedRow = end($schedule);
    }

    $elapsedYears = 0;
    if ($evaluationYear >= $depreciationStartYear && $usefulLife > 0) {
        $elapsedYears = min(($evaluationYear - $depreciationStartYear) + 1, $usefulLife);
    }

    $accumulated = $selectedRow['accumulated_depreciation'] ?? 0.0;
    $carryingAmount = $selectedRow['ending_value'] ?? $cost;
    $remainingYears = max($usefulLife - $elapsedYears, 0);
    $lifeUsedRatio = $usefulLife > 0 ? min($elapsedYears / $usefulLife, 1) : 0.0;
    $isFullyDepreciated = $usefulLife > 0 && (
        $carryingAmount <= ($salvageValue + 0.01)
        || $elapsedYears >= $usefulLife
        || $status === 'Fully Depreciated'
    );

    $condition = 'Healthy';
    if ($isFullyDepreciated || $remainingYears === 0) {
        $condition = 'Critical';
    } elseif ($lifeUsedRatio >= 0.8 || $remainingYears <= 1) {
        $condition = 'Monitor';
    }

    $metrics = [
        'annual_depreciation' => $annualDepreciation,
        'accumulated_depreciation' => round((float) $accumulated, 2),
        'carrying_amount' => round((float) $carryingAmount, 2),
        'remaining_years' => $remainingYears,
        'elapsed_years' => $elapsedYears,
        'life_used_ratio' => round($lifeUsedRatio, 4),
        'is_fully_depreciated' => $isFullyDepreciated,
        'condition' => $condition,
        'schedule_rows' => $schedule,
        'schedule_year_start' => $schedule[0]['depreciation_year'] ?? $depreciationStartYear,
        'schedule_year_end' => $schedule !== [] ? end($schedule)['depreciation_year'] : $depreciationStartYear,
    ];

    $metrics['anomalies'] = detect_asset_anomalies($asset, $metrics);
    $metrics['anomaly_count'] = count($metrics['anomalies']);

    return $metrics;
}

function detect_asset_anomalies(array $asset, array $metrics): array
{
    $anomalies = [];
    $cost = asset_total_cost($asset);
    $salvageValue = (float) ($asset['salvage_value'] ?? 0);
    $status = (string) ($asset['status'] ?? 'Active');

    if ((int) ($asset['useful_life'] ?? 0) <= 0) {
        $anomalies[] = 'Useful life is missing or invalid.';
    }

    if ($salvageValue > $cost) {
        $anomalies[] = 'Salvage value is higher than the total asset cost.';
    }

    if ($metrics['carrying_amount'] < -0.01) {
        $anomalies[] = 'Net amount dropped below zero.';
    }

    if ($metrics['is_fully_depreciated'] && $status === 'Active') {
        $anomalies[] = 'Asset is fully depreciated but still marked as active.';
    }

    if ($cost <= 0) {
        $anomalies[] = 'Acquisition cost should be greater than zero.';
    }

    return $anomalies;
}

function hydrate_assets_with_metrics(array $assets, ?int $year = null): array
{
    foreach ($assets as &$asset) {
        $metrics = get_asset_metrics($asset, $year);
        $asset = array_merge($asset, $metrics);
    }
    unset($asset);

    return $assets;
}

function build_dashboard_metrics(array $assets): array
{
    $metrics = [
        'asset_count' => count($assets),
        'total_cost' => 0.0,
        'total_accumulated' => 0.0,
        'total_carrying' => 0.0,
        'active_count' => 0,
        'fully_depreciated_count' => 0,
        'near_end_count' => 0,
        'unusual_count' => 0,
    ];

    foreach ($assets as $asset) {
        $metrics['total_cost'] += asset_total_cost($asset);
        $metrics['total_accumulated'] += (float) ($asset['accumulated_depreciation'] ?? 0);
        $metrics['total_carrying'] += (float) ($asset['carrying_amount'] ?? 0);
        $metrics['active_count'] += (($asset['status'] ?? '') === 'Active') ? 1 : 0;
        $metrics['fully_depreciated_count'] += !empty($asset['is_fully_depreciated']) ? 1 : 0;
        $metrics['near_end_count'] += (isset($asset['remaining_years']) && (int) $asset['remaining_years'] <= 1 && empty($asset['is_fully_depreciated'])) ? 1 : 0;
        $metrics['unusual_count'] += !empty($asset['anomaly_count']) ? 1 : 0;
    }

    foreach (['total_cost', 'total_accumulated', 'total_carrying'] as $key) {
        $metrics[$key] = round((float) $metrics[$key], 2);
    }

    return $metrics;
}

function build_category_summary(array $assets): array
{
    $summary = [];

    foreach ($assets as $asset) {
        $key = (string) ($asset['category_name'] ?? 'Uncategorized');

        if (!isset($summary[$key])) {
            $summary[$key] = [
                'label' => $key,
                'asset_count' => 0,
                'total_cost' => 0.0,
                'total_accumulated' => 0.0,
                'total_carrying' => 0.0,
            ];
        }

        $summary[$key]['asset_count']++;
        $summary[$key]['total_cost'] += asset_total_cost($asset);
        $summary[$key]['total_accumulated'] += (float) ($asset['accumulated_depreciation'] ?? 0);
        $summary[$key]['total_carrying'] += (float) ($asset['carrying_amount'] ?? 0);
    }

    usort($summary, static fn (array $left, array $right): int => $right['total_cost'] <=> $left['total_cost']);

    return $summary;
}

function build_department_summary(array $assets): array
{
    $summary = [];

    foreach ($assets as $asset) {
        $key = (string) ($asset['department_name'] ?? 'Unassigned');

        if (!isset($summary[$key])) {
            $summary[$key] = [
                'label' => $key,
                'asset_count' => 0,
                'total_cost' => 0.0,
                'total_carrying' => 0.0,
            ];
        }

        $summary[$key]['asset_count']++;
        $summary[$key]['total_cost'] += asset_total_cost($asset);
        $summary[$key]['total_carrying'] += (float) ($asset['carrying_amount'] ?? 0);
    }

    usort($summary, static fn (array $left, array $right): int => $right['total_cost'] <=> $left['total_cost']);

    return $summary;
}

function build_asset_alerts(array $assets): array
{
    $alerts = [
        'near_end' => [],
        'fully_depreciated_active' => [],
        'unusual' => [],
    ];

    foreach ($assets as $asset) {
        if (isset($asset['remaining_years']) && (int) $asset['remaining_years'] <= 1 && empty($asset['is_fully_depreciated'])) {
            $alerts['near_end'][] = $asset;
        }

        if (!empty($asset['is_fully_depreciated']) && ($asset['status'] ?? '') === 'Active') {
            $alerts['fully_depreciated_active'][] = $asset;
        }

        if (!empty($asset['anomaly_count'])) {
            $alerts['unusual'][] = $asset;
        }
    }

    usort(
        $alerts['near_end'],
        static fn (array $left, array $right): int => ($left['remaining_years'] <=> $right['remaining_years']) ?: strcmp((string) $left['asset_name'], (string) $right['asset_name'])
    );

    usort(
        $alerts['unusual'],
        static fn (array $left, array $right): int => ($right['anomaly_count'] <=> $left['anomaly_count']) ?: strcmp((string) $left['asset_name'], (string) $right['asset_name'])
    );

    usort(
        $alerts['fully_depreciated_active'],
        static fn (array $left, array $right): int => strcmp((string) $left['asset_name'], (string) $right['asset_name'])
    );

    return $alerts;
}

function build_risk_summary(array $metrics, array $alerts): string
{
    if ($metrics['asset_count'] === 0) {
        return 'No PPE records are in the system yet, so there are no depreciation risks to summarize.';
    }

    $parts = [
        money($metrics['total_cost']) . ' total PPE cost is currently being monitored.',
    ];

    if ($metrics['near_end_count'] > 0) {
        $parts[] = $metrics['near_end_count'] . ' ' . pluralize($metrics['near_end_count'], 'asset is', 'assets are') . ' close to the end of useful life.';
    }

    if ($metrics['fully_depreciated_count'] > 0) {
        $parts[] = $metrics['fully_depreciated_count'] . ' ' . pluralize($metrics['fully_depreciated_count'], 'asset is', 'assets are') . ' already fully depreciated.';
    }

    if (count($alerts['unusual']) > 0) {
        $parts[] = count($alerts['unusual']) . ' ' . pluralize(count($alerts['unusual']), 'record needs', 'records need') . ' closer review for possible data issues.';
    }

    return implode(' ', $parts);
}

/**
 * Return prorated depreciation amounts for common intervals based on annual depreciation.
 * Intervals supported: day, week, month, quarter, year
 */
function depreciation_intervals_from_asset(array $asset): array
{
    $metrics = get_asset_metrics($asset);
    $annual = (float) ($metrics['annual_depreciation'] ?? 0.0);
    $hasStarted = asset_has_started_depreciation($asset);

    // If the asset has not yet entered service, current interval depreciation is zero.
    if (!$hasStarted || !empty($metrics['is_fully_depreciated'])) {
        return [
            'daily' => 0.0,
            'weekly' => 0.0,
            'monthly' => 0.0,
            'quarterly' => 0.0,
            'yearly' => 0.0,
        ];
    }

    // Use simple calendar approximations for active depreciation.
    $perDay = $annual / 365.0;
    $perWeek = $perDay * 7.0;
    $perMonth = $annual / 12.0;
    $perQuarter = $annual / 4.0;

    return [
        'daily' => round($perDay, 2),
        'weekly' => round($perWeek, 2),
        'monthly' => round($perMonth, 2),
        'quarterly' => round($perQuarter, 2),
        'yearly' => round($annual, 2),
    ];
}

/**
 * Aggregate depreciation totals for a collection of assets for a given interval.
 * $interval: daily|weekly|monthly|quarterly|yearly
 */
function aggregate_depreciation_totals(array $assets, string $interval = 'yearly'): array
{
    $interval = mb_strtolower($interval, 'UTF-8');
    $total = 0.0;
    $count = 0;

    foreach ($assets as $asset) {
        $intervals = depreciation_intervals_from_asset($asset);
        if (isset($intervals[$interval])) {
            $total += (float) $intervals[$interval];
        }
        $count++;
    }

    return [
        'interval' => $interval,
        'asset_count' => $count,
        'total_depreciation' => round($total, 2),
    ];
}

function compute_total_interval_depreciation(array $assets, string $interval): float
{
    $total = 0.0;
    $interval = mb_strtolower($interval, 'UTF-8');

    foreach ($assets as $asset) {
        $intervals = depreciation_intervals_from_asset($asset);
        if (isset($intervals[$interval])) {
            $total += (float) $intervals[$interval];
        }
    }

    return round($total, 2);
}

function detect_depreciation_interval(string $question): ?string
{
    $lower = mb_strtolower($question, 'UTF-8');
    if (str_contains($lower, 'daily')) {
        return 'daily';
    }
    if (str_contains($lower, 'weekly')) {
        return 'weekly';
    }
    if (str_contains($lower, 'monthly')) {
        return 'monthly';
    }
    if (str_contains($lower, 'quarterly') || str_contains($lower, 'quarter')) {
        return 'quarterly';
    }
    if (str_contains($lower, 'yearly') || str_contains($lower, 'annual') || str_contains($lower, 'per year')) {
        return 'yearly';
    }

    return null;
}

function format_ollama_endpoint(string $baseUrl, string $endpoint): string
{
    if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
        return $endpoint;
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
}

function call_ollama_with_payload(string $url, array $payload, array $cfg): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) ($cfg['timeout'] ?? 10));
    curl_setopt($ch, CURLOPT_TIMEOUT, (int) ($cfg['timeout'] ?? 10));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $result = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'body' => $result !== false ? (string) $result : '',
        'error' => $result === false ? $error : '',
        'decoded' => json_decode($result !== false ? $result : '', true),
    ];
}

function parse_ollama_response(array $response): ?string
{
    $decoded = $response['decoded'];
    if (is_array($decoded)) {
        if (isset($decoded['text'])) {
            return trim((string) $decoded['text']);
        }

        if (isset($decoded['results'][0]['text'])) {
            return trim((string) $decoded['results'][0]['text']);
        }

        if (isset($decoded['choices'][0]['text'])) {
            return trim((string) $decoded['choices'][0]['text']);
        }

        if (isset($decoded['choices'][0]['message']['content'])) {
            return trim((string) $decoded['choices'][0]['message']['content']);
        }

        if (isset($decoded['output'][0]['content'][0]['text'])) {
            return trim((string) $decoded['output'][0]['content'][0]['text']);
        }
    }

    return null;
}

/**
 * Minimal Ollama HTTP wrapper. Sends a prompt to the configured Ollama server and returns text output.
 */
function call_ollama(string $prompt): string
{
    $cfg = get_ollama_config();

    if (empty($cfg['enabled'])) {
        return 'Ollama integration is disabled in configuration.';
    }

    $endpoints = [
        $cfg['endpoint'] ?? '/api/generate',
        '/api/completions',
        '/v1/completions',
    ];

    foreach ($endpoints as $endpoint) {
        $url = format_ollama_endpoint($cfg['url'], $endpoint);
        $payload = [
            'model' => $cfg['model'],
            'prompt' => $prompt,
        ];

        $result = call_ollama_with_payload($url, $payload, $cfg);
        if ($result['status'] >= 200 && $result['status'] < 300) {
            $text = parse_ollama_response($result);
            return $text !== null ? $text : (string) $result['body'];
        }

        if ($result['status'] === 404 && str_contains($result['body'], 'model')) {
            continue;
        }

        if ($result['status'] === 404 && $endpoint === ($cfg['endpoint'] ?? '/api/generate')) {
            continue;
        }

        if (!empty($result['error'])) {
            return 'Ollama request failed: ' . $result['error'];
        }

        $decodedMessage = $result['decoded']['error'] ?? $result['decoded']['message'] ?? null;
        return sprintf('Ollama returned HTTP %d: %s', $result['status'], $decodedMessage ?? substr((string) $result['body'], 0, 1000));
    }

    return 'Unable to contact Ollama; please verify OLLAMA_URL and OLLAMA_ENDPOINT configuration.';
}
