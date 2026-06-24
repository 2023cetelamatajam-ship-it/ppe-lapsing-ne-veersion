<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$type = trim((string) request_value('type', 'assets'));
$printedAt = date('M d, Y h:i A');
$title = '';
$assets = [];
$alerts = [];
$transfers = [];
$asset = null;
$schedule = [];
$metrics = null;

switch ($type) {
    case 'assets':
        $title = 'PPE Asset Register';
        $assets = hydrate_assets_with_metrics(fetch_assets($pdo));
        break;

    case 'alerts':
        $title = 'PPE Alert Digest';
        $assets = hydrate_assets_with_metrics(fetch_assets($pdo));
        $alerts = build_asset_alerts($assets);
        break;

    case 'transfers':
        $title = 'PPE Transfer History';
        $transfers = fetch_asset_transfers($pdo);
        break;

    case 'schedule':
        $assetId = (int) request_value('asset_id', 0);
        $asset = fetch_asset_by_id($pdo, $assetId);

        if (!$asset) {
            set_flash('danger', 'The selected asset schedule could not be printed.');
            redirect('modules/exports.php');
        }

        $title = 'Depreciation Schedule - ' . $asset['asset_code'];
        $schedule = fetch_depreciation_rows($pdo, $assetId);
        $metrics = get_asset_metrics($asset);
        break;

    default:
        set_flash('danger', 'That print view is not available.');
        redirect('modules/exports.php');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title . ' - ' . APP_NAME) ?></title>
    <style>
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #efe8e0;
            color: #1d1d1d;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.45;
            padding: 24px;
        }

        .sheet {
            max-width: 1200px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #d8d1ca;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.08);
            padding: 32px;
        }

        .toolbar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border: 1px solid #c7bfb8;
            border-radius: 8px;
            color: #1d1d1d;
            text-decoration: none;
            background: #faf7f4;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        p {
            margin: 0 0 8px;
        }

        .meta {
            color: #666666;
            margin-bottom: 20px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin: 22px 0;
        }

        .summary-card {
            border: 1px solid #ddd5cf;
            padding: 14px;
        }

        .summary-card strong {
            display: block;
            margin-bottom: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            font-size: 13px;
        }

        th,
        td {
            border: 1px solid #ddd5cf;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3eee9;
        }

        .section {
            margin-top: 28px;
        }

        .muted {
            color: #666666;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .sheet {
                max-width: none;
                border: 0;
                box-shadow: none;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            a {
                color: inherit;
                text-decoration: none;
            }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar">
            <a class="btn" href="javascript:window.print()">Print</a>
            <a class="btn" href="<?= e(base_url('modules/exports.php' . ($asset ? '?asset_id=' . $asset['asset_id'] : ''))) ?>">Back to Exports</a>
        </div>

        <header>
            <h1><?= e($title) ?></h1>
            <p><?= e(APP_NAME) ?></p>
            <p class="meta">Generated on <?= e($printedAt) ?></p>
        </header>

        <?php if ($type === 'assets'): ?>
            <div class="summary">
                <div class="summary-card">
                    <strong>Total assets</strong>
                    <span><?= e((string) count($assets)) ?></span>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Category</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Condition</th>
                        <th>Total Cost</th>
                        <th>Net Amount</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $assetRow): ?>
                        <tr>
                            <td>
                                <strong><?= e($assetRow['asset_name']) ?></strong><br>
                                <span class="muted"><?= e($assetRow['asset_code']) ?></span>
                            </td>
                            <td><?= e((string) ($assetRow['category_name'] ?? 'Uncategorized')) ?></td>
                            <td><?= e(asset_department_label($assetRow)) ?></td>
                            <td><?= e((string) $assetRow['status']) ?></td>
                            <td><?= e((string) $assetRow['condition']) ?></td>
                            <td><?= e(money(asset_total_cost($assetRow))) ?></td>
                            <td><?= e(money((float) $assetRow['carrying_amount'])) ?></td>
                            <td><?= e(asset_location_label((string) ($assetRow['location'] ?? ''))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($type === 'alerts'): ?>
            <?php
            $flaggedAssetIds = [];
            foreach ($alerts as $group) {
                foreach ($group as $assetRow) {
                    $flaggedAssetIds[(int) $assetRow['asset_id']] = true;
                }
            }
            ?>
            <div class="summary">
                <div class="summary-card">
                    <strong>Flagged Assets</strong>
                    <span><?= e((string) count($flaggedAssetIds)) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Near End of Life</strong>
                    <span><?= e((string) count($alerts['near_end'])) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Fully Depreciated Active</strong>
                    <span><?= e((string) count($alerts['fully_depreciated_active'])) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Data Quality Flags</strong>
                    <span><?= e((string) count($alerts['unusual'])) ?></span>
                </div>
            </div>

            <section class="section">
                <h2>Near-end assets</h2>
                <?php if ($alerts['near_end'] === []): ?>
                    <p class="muted">No assets are currently near the end of useful life.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Department</th>
                                <th>Remaining Years</th>
                                <th>Carrying Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alerts['near_end'] as $assetRow): ?>
                                <tr>
                                    <td><?= e($assetRow['asset_name'] . ' (' . $assetRow['asset_code'] . ')') ?></td>
                                    <td><?= e(asset_department_label($assetRow)) ?></td>
                                    <td><?= e((string) $assetRow['remaining_years']) ?></td>
                                    <td><?= e(money((float) $assetRow['carrying_amount'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="section">
                <h2>Fully depreciated but active</h2>
                <?php if ($alerts['fully_depreciated_active'] === []): ?>
                    <p class="muted">No active assets are fully depreciated at this time.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Carrying Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alerts['fully_depreciated_active'] as $assetRow): ?>
                                <tr>
                                    <td><?= e($assetRow['asset_name'] . ' (' . $assetRow['asset_code'] . ')') ?></td>
                                    <td><?= e(asset_department_label($assetRow)) ?></td>
                                    <td><?= e((string) $assetRow['status']) ?></td>
                                    <td><?= e(money((float) $assetRow['carrying_amount'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="section">
                <h2>Unusual records</h2>
                <?php if ($alerts['unusual'] === []): ?>
                    <p class="muted">No unusual records were detected by the current validation rules.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Condition</th>
                                <th>Review Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alerts['unusual'] as $assetRow): ?>
                                <tr>
                                    <td><?= e($assetRow['asset_name'] . ' (' . $assetRow['asset_code'] . ')') ?></td>
                                    <td><?= e((string) $assetRow['condition']) ?></td>
                                    <td><?= e(implode('; ', $assetRow['anomalies'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php elseif ($type === 'transfers'): ?>
            <div class="summary">
                <div class="summary-card">
                    <strong>Total transfer entries</strong>
                    <span><?= e((string) count($transfers)) ?></span>
                </div>
            </div>

            <?php if ($transfers === []): ?>
                <p class="muted">No transfers have been recorded yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Asset</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Recorded By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $transfer): ?>
                            <tr>
                                <td><?= e(format_date((string) $transfer['transfer_date'])) ?></td>
                                <td><?= e($transfer['asset_name'] . ' (' . $transfer['asset_code'] . ')') ?></td>
                                <td><?= e((trim((string) ($transfer['from_department_name'] ?? '')) ?: 'Unassigned') . ' / ' . asset_location_label((string) ($transfer['from_location'] ?? ''))) ?></td>
                                <td><?= e((trim((string) ($transfer['to_department_name'] ?? '')) ?: 'Unassigned') . ' / ' . asset_location_label((string) ($transfer['to_location'] ?? ''))) ?></td>
                                <td><?= e((string) ($transfer['transferred_by_name'] ?? 'System')) ?></td>
                                <td><?= e((string) ($transfer['notes'] ?: 'No notes')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php elseif ($type === 'schedule' && $asset && $metrics): ?>
            <div class="summary">
                <div class="summary-card">
                    <strong>Asset</strong>
                    <span><?= e($asset['asset_name']) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Annual Depreciation</strong>
                    <span><?= e(money($metrics['annual_depreciation'])) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Carrying Amount</strong>
                    <span><?= e(money($metrics['carrying_amount'])) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Status</strong>
                    <span><?= e((string) $asset['status']) ?></span>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Cost</th>
                        <th>Additional</th>
                        <th>Beginning Value</th>
                        <th>Depreciation Expense</th>
                        <th>Accumulated Depreciation</th>
                        <th>Ending Value</th>
                        <th>Net Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedule as $row): ?>
                        <tr>
                            <td><?= e((string) $row['depreciation_year']) ?></td>
                            <td><?= e(money((float) $asset['acquisition_cost'])) ?></td>
                            <td><?= e(money((float) ($asset['additional_amount'] ?? 0))) ?></td>
                            <td><?= e(money((float) $row['beginning_value'])) ?></td>
                            <td><?= e(money((float) $row['depreciation_expense'])) ?></td>
                            <td><?= e(money((float) $row['accumulated_depreciation'])) ?></td>
                            <td><?= e(money((float) $row['ending_value'])) ?></td>
                            <td><?= e(money(schedule_display_net_value($asset, $row))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
