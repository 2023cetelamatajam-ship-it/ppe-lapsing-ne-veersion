<?php
declare(strict_types=1);

function asset_department_label(array $asset): string
{
    $department = trim((string) ($asset['department_name'] ?? ''));

    return $department === '' ? 'Unassigned' : $department;
}

function asset_location_label(?string $location): string
{
    $value = trim((string) $location);

    return $value === '' ? 'Not specified' : $value;
}

function normalize_transfer_payload(array $input): array
{
    return [
        'asset_id' => (int) ($input['asset_id'] ?? 0),
        'transfer_date' => trim((string) ($input['transfer_date'] ?? date('Y-m-d'))),
        'to_department_id' => ($input['to_department_id'] ?? '') !== '' ? (int) $input['to_department_id'] : null,
        'to_location' => trim((string) ($input['to_location'] ?? '')),
        'notes' => trim((string) ($input['notes'] ?? '')),
    ];
}

function validate_transfer_payload(array $payload, array $asset, array $departments): array
{
    $errors = [];

    if (($payload['asset_id'] ?? 0) <= 0) {
        $errors[] = 'Please choose an asset to transfer.';
    }

    $transferDate = (string) ($payload['transfer_date'] ?? '');
    $transferTimestamp = strtotime($transferDate);

    if ($transferDate === '' || $transferTimestamp === false) {
        $errors[] = 'A valid transfer date is required.';
    }

    $allowedDepartmentIds = array_map(
        static fn (array $department): int => (int) $department['department_id'],
        $departments
    );

    if (($payload['to_department_id'] ?? null) !== null
        && !in_array((int) $payload['to_department_id'], $allowedDepartmentIds, true)
    ) {
        $errors[] = 'Please choose a valid destination department.';
    }

    $locationLength = function_exists('mb_strlen')
        ? mb_strlen((string) ($payload['to_location'] ?? ''))
        : strlen((string) ($payload['to_location'] ?? ''));

    if ($locationLength > 150) {
        $errors[] = 'Destination location must not exceed 150 characters.';
    }

    $acquisitionTimestamp = strtotime((string) ($asset['acquisition_date'] ?? ''));
    if ($acquisitionTimestamp !== false && $transferTimestamp !== false && $transferTimestamp < $acquisitionTimestamp) {
        $errors[] = 'Transfer date cannot be earlier than the acquisition date.';
    }

    $currentDepartmentId = ($asset['department_id'] ?? null) !== null ? (int) $asset['department_id'] : null;
    $currentLocation = trim((string) ($asset['location'] ?? ''));
    $nextDepartmentId = ($payload['to_department_id'] ?? null) ?? $currentDepartmentId;
    $nextLocation = trim((string) ($payload['to_location'] ?? '')) !== ''
        ? trim((string) $payload['to_location'])
        : $currentLocation;

    if ($nextDepartmentId === $currentDepartmentId && $nextLocation === $currentLocation) {
        $errors[] = 'Change the department or location before saving this transfer.';
    }

    return $errors;
}

function save_asset_transfer(PDO $pdo, int $assetId, array $payload, ?int $transferredByUserId = null): int
{
    $asset = fetch_asset_by_id($pdo, $assetId);

    if (!$asset) {
        throw new InvalidArgumentException('Asset record not found.');
    }

    $fromDepartmentId = ($asset['department_id'] ?? null) !== null ? (int) $asset['department_id'] : null;
    $toDepartmentId = ($payload['to_department_id'] ?? null) ?? $fromDepartmentId;
    $fromLocation = trim((string) ($asset['location'] ?? ''));
    $toLocation = trim((string) ($payload['to_location'] ?? '')) !== ''
        ? trim((string) $payload['to_location'])
        : $fromLocation;

    $insert = $pdo->prepare(
        'INSERT INTO asset_transfers (
            asset_id, from_department_id, to_department_id, from_location, to_location,
            transfer_date, notes, transferred_by_user_id
        ) VALUES (
            :asset_id, :from_department_id, :to_department_id, :from_location, :to_location,
            :transfer_date, :notes, :transferred_by_user_id
        )'
    );

    $updateAsset = $pdo->prepare(
        'UPDATE assets
         SET department_id = :department_id, location = :location
         WHERE asset_id = :asset_id'
    );

    $pdo->beginTransaction();

    try {
        $insert->execute([
            'asset_id' => $assetId,
            'from_department_id' => $fromDepartmentId,
            'to_department_id' => $toDepartmentId,
            'from_location' => $fromLocation === '' ? null : $fromLocation,
            'to_location' => $toLocation === '' ? null : $toLocation,
            'transfer_date' => (string) $payload['transfer_date'],
            'notes' => (string) ($payload['notes'] ?? ''),
            'transferred_by_user_id' => $transferredByUserId,
        ]);

        $updateAsset->execute([
            'department_id' => $toDepartmentId,
            'location' => $toLocation === '' ? null : $toLocation,
            'asset_id' => $assetId,
        ]);

        $transferId = (int) $pdo->lastInsertId();
        $pdo->commit();

        return $transferId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function fetch_asset_transfers(PDO $pdo, ?int $assetId = null, ?int $limit = null): array
{
    $sql = 'SELECT t.*, a.asset_code, a.asset_name,
                   fd.department_name AS from_department_name,
                   td.department_name AS to_department_name,
                   u.full_name AS transferred_by_name
            FROM asset_transfers t
            INNER JOIN assets a ON a.asset_id = t.asset_id
            LEFT JOIN departments fd ON fd.department_id = t.from_department_id
            LEFT JOIN departments td ON td.department_id = t.to_department_id
            LEFT JOIN users u ON u.user_id = t.transferred_by_user_id';
    $params = [];

    if ($assetId !== null && $assetId > 0) {
        $sql .= ' WHERE t.asset_id = :asset_id';
        $params['asset_id'] = $assetId;
    }

    $sql .= ' ORDER BY t.transfer_date DESC, t.transfer_id DESC';

    if ($limit !== null && $limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll() ?: [];
}

function build_asset_alert_labels(array $asset): array
{
    $labels = [];

    if (isset($asset['remaining_years']) && (int) $asset['remaining_years'] <= 1 && empty($asset['is_fully_depreciated'])) {
        $labels[] = 'Near End of Life';
    }

    if (!empty($asset['is_fully_depreciated']) && ($asset['status'] ?? '') === 'Active') {
        $labels[] = 'Fully Depreciated but Active';
    }

    if (!empty($asset['anomaly_count'])) {
        $labels[] = 'Data Quality';
    }

    return $labels;
}

function build_asset_alert_messages(array $asset): array
{
    $messages = [];

    if (isset($asset['remaining_years']) && (int) $asset['remaining_years'] <= 1 && empty($asset['is_fully_depreciated'])) {
        $messages[] = (int) $asset['remaining_years'] === 0
            ? 'No useful life remains based on the current schedule.'
            : 'Only ' . (int) $asset['remaining_years'] . ' year(s) of useful life remain.';
    }

    if (!empty($asset['is_fully_depreciated']) && ($asset['status'] ?? '') === 'Active') {
        $messages[] = 'Asset has already reached its salvage value but is still marked as active.';
    }

    foreach (($asset['anomalies'] ?? []) as $anomaly) {
        $messages[] = (string) $anomaly;
    }

    return array_values(array_unique($messages));
}
