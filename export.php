<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$type = trim((string) request_value('type', 'assets'));
$timestamp = date('Ymd-His');

switch ($type) {
    case 'assets':
        $assets = hydrate_assets_with_metrics(fetch_assets($pdo));
        $rows = [];

        foreach ($assets as $asset) {
            $rows[] = [
                $asset['asset_code'],
                $asset['asset_name'],
                (string) ($asset['category_name'] ?? 'Uncategorized'),
                asset_department_label($asset),
                (string) $asset['acquisition_date'],
                (string) $asset['status'],
                (string) $asset['condition'],
                number_format((float) $asset['acquisition_cost'], 2, '.', ''),
                number_format((float) ($asset['additional_amount'] ?? 0), 2, '.', ''),
                number_format(asset_total_cost($asset), 2, '.', ''),
                number_format((float) $asset['salvage_value'], 2, '.', ''),
                (string) $asset['useful_life'],
                number_format((float) $asset['annual_depreciation'], 2, '.', ''),
                number_format((float) $asset['accumulated_depreciation'], 2, '.', ''),
                number_format((float) $asset['carrying_amount'], 2, '.', ''),
                (string) $asset['remaining_years'],
                asset_location_label((string) ($asset['location'] ?? '')),
                (string) ($asset['remarks'] ?? ''),
            ];
        }

        download_csv(
            'ppe-assets-' . $timestamp . '.csv',
            [
                'Asset Code',
                'Asset Name',
                'Category',
                'Department',
                'Acquisition Date',
                'Status',
                'Condition',
                'Acquisition Cost',
                'Additional Amount',
                'Total Cost',
                'Salvage Value',
                'Useful Life',
                'Annual Depreciation',
                'Accumulated Depreciation',
                'Net Amount',
                'Remaining Years',
                'Location',
                'Remarks',
            ],
            $rows
        );

    case 'alerts':
        $assets = hydrate_assets_with_metrics(fetch_assets($pdo));
        $rows = [];

        foreach ($assets as $asset) {
            $labels = build_asset_alert_labels($asset);

            if ($labels === []) {
                continue;
            }

            $rows[] = [
                $asset['asset_code'],
                $asset['asset_name'],
                (string) ($asset['category_name'] ?? 'Uncategorized'),
                asset_department_label($asset),
                (string) $asset['status'],
                (string) $asset['condition'],
                (string) $asset['remaining_years'],
                number_format((float) $asset['carrying_amount'], 2, '.', ''),
                implode('; ', $labels),
                implode('; ', build_asset_alert_messages($asset)),
            ];
        }

        download_csv(
            'ppe-alerts-' . $timestamp . '.csv',
            [
                'Asset Code',
                'Asset Name',
                'Category',
                'Department',
                'Status',
                'Condition',
                'Remaining Years',
                'Net Amount',
                'Alert Types',
                'Review Notes',
            ],
            $rows
        );

    case 'transfers':
        $transfers = fetch_asset_transfers($pdo);
        $rows = [];

        foreach ($transfers as $transfer) {
            $rows[] = [
                (string) $transfer['transfer_date'],
                $transfer['asset_code'],
                $transfer['asset_name'],
                trim((string) ($transfer['from_department_name'] ?? '')) ?: 'Unassigned',
                asset_location_label((string) ($transfer['from_location'] ?? '')),
                trim((string) ($transfer['to_department_name'] ?? '')) ?: 'Unassigned',
                asset_location_label((string) ($transfer['to_location'] ?? '')),
                (string) ($transfer['transferred_by_name'] ?? 'System'),
                (string) ($transfer['notes'] ?? ''),
            ];
        }

        download_csv(
            'ppe-transfers-' . $timestamp . '.csv',
            [
                'Transfer Date',
                'Asset Code',
                'Asset Name',
                'From Department',
                'From Location',
                'To Department',
                'To Location',
                'Recorded By',
                'Notes',
            ],
            $rows
        );

    case 'schedule':
        $assetId = (int) request_value('asset_id', 0);
        $asset = fetch_asset_by_id($pdo, $assetId);

        if (!$asset) {
            set_flash('danger', 'The selected asset schedule could not be exported.');
            redirect('modules/exports.php');
        }

        $schedule = fetch_depreciation_rows($pdo, $assetId);
        $rows = [];

        foreach ($schedule as $row) {
            $rows[] = [
                $asset['asset_code'],
                $asset['asset_name'],
                (string) $row['depreciation_year'],
                number_format((float) $asset['acquisition_cost'], 2, '.', ''),
                number_format((float) ($asset['additional_amount'] ?? 0), 2, '.', ''),
                number_format((float) $row['beginning_value'], 2, '.', ''),
                number_format((float) $row['depreciation_expense'], 2, '.', ''),
                number_format((float) $row['accumulated_depreciation'], 2, '.', ''),
                number_format((float) $row['ending_value'], 2, '.', ''),
                number_format(schedule_display_net_value($asset, $row), 2, '.', ''),
            ];
        }

        download_csv(
            'ppe-schedule-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $asset['asset_code']) . '-' . $timestamp . '.csv',
            [
                'Asset Code',
                'Asset Name',
                'Year',
                'Acquisition Cost',
                'Additional Amount',
                'Beginning Value',
                'Depreciation Expense',
                'Accumulated Depreciation',
                'Ending Value',
                'Net Value',
            ],
            $rows
        );

    default:
        set_flash('danger', 'That export type is not available.');
        redirect('modules/exports.php');
}
