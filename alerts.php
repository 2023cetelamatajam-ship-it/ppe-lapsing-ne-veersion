<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$assets = hydrate_assets_with_metrics(fetch_assets($pdo));
$alerts = build_asset_alerts($assets);
$flaggedAssetIds = [];

foreach ($alerts as $group) {
    foreach ($group as $asset) {
        $flaggedAssetIds[(int) $asset['asset_id']] = true;
    }
}

$pageTitle = 'Alerts';
$pageHeading = 'Alerts and Review Queue';
$pageDescription = 'Focus on upcoming replacements, assets that should be reclassified, and records with unusual setup issues.';

require_once APP_ROOT . '/includes/header.php';
?>
<?php if ($assets === []): ?>
    <section class="shell-card">
        <div class="empty-state">
            <h2 class="section-title mb-2">No alerts are available yet</h2>
            <p class="mb-0">Add PPE assets first so the system can evaluate remaining life, status mismatches, and data quality checks.</p>
        </div>
    </section>
<?php else: ?>
    <div class="page-actions mb-4">
        <a class="btn btn-outline-light" href="<?= e(base_url('modules/export.php?type=alerts')) ?>">Export Alerts CSV</a>
        <a class="btn btn-outline-light" href="<?= e(base_url('modules/print_view.php?type=alerts')) ?>" target="_blank" rel="noopener">Print Alert Digest</a>
    </div>

    <div class="metric-grid mb-4">
        <section class="metric-card">
            <p class="metric-label mb-2">Flagged Assets</p>
            <h2 class="metric-value mb-1"><?= e((string) count($flaggedAssetIds)) ?></h2>
            <p class="metric-meta mb-0">Unique records that need review</p>
        </section>
        <section class="metric-card">
            <p class="metric-label mb-2">Near End of Life</p>
            <h2 class="metric-value mb-1"><?= e((string) count($alerts['near_end'])) ?></h2>
            <p class="metric-meta mb-0">Replacement planning candidates</p>
        </section>
        <section class="metric-card">
            <p class="metric-label mb-2">Fully Depreciated Active</p>
            <h2 class="metric-value mb-1"><?= e((string) count($alerts['fully_depreciated_active'])) ?></h2>
            <p class="metric-meta mb-0">Status updates may be overdue</p>
        </section>
        <section class="metric-card">
            <p class="metric-label mb-2">Data Quality Flags</p>
            <h2 class="metric-value mb-1"><?= e((string) count($alerts['unusual'])) ?></h2>
            <p class="metric-meta mb-0">Records with unusual values or setup</p>
        </section>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <section class="shell-card h-100">
                <div class="mb-3">
                    <p class="eyebrow mb-2">Replacement planning</p>
                    <h2 class="section-title mb-1">Near-end assets</h2>
                    <p class="section-copy mb-0">These assets have one year or less of useful life left based on the current schedule.</p>
                </div>

                <?php if ($alerts['near_end'] === []): ?>
                    <div class="empty-state">No assets are currently near the end of useful life.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Department</th>
                                    <th>Remaining</th>
                                    <th>Net</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alerts['near_end'] as $asset): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($asset['asset_name']) ?></strong>
                                            <div class="text-soft small"><?= e($asset['asset_code']) ?></div>
                                        </td>
                                        <td><?= e(asset_department_label($asset)) ?></td>
                                        <td><?= e((string) $asset['remaining_years']) ?> year(s)</td>
                                        <td><?= e(money((float) $asset['carrying_amount'])) ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-light" href="<?= e(base_url('modules/view_asset.php?asset_id=' . $asset['asset_id'])) ?>">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <div class="col-xl-6">
            <section class="shell-card h-100">
                <div class="mb-3">
                    <p class="eyebrow mb-2">Status follow-up</p>
                    <h2 class="section-title mb-1">Fully depreciated but active</h2>
                    <p class="section-copy mb-0">These assets reached salvage value but are still tagged as active in the register.</p>
                </div>

                <?php if ($alerts['fully_depreciated_active'] === []): ?>
                    <div class="empty-state">No active assets are currently fully depreciated.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Net Amount</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alerts['fully_depreciated_active'] as $asset): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($asset['asset_name']) ?></strong>
                                            <div class="text-soft small"><?= e($asset['asset_code']) ?></div>
                                        </td>
                                        <td><?= e(asset_department_label($asset)) ?></td>
                                        <td><span class="badge <?= e(status_badge_class((string) $asset['status'])) ?>"><?= e($asset['status']) ?></span></td>
                                        <td><?= e(money((float) $asset['carrying_amount'])) ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-light" href="<?= e(base_url('modules/view_asset.php?asset_id=' . $asset['asset_id'])) ?>">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <section class="shell-card mt-4">
        <div class="mb-3">
            <p class="eyebrow mb-2">Record quality</p>
            <h2 class="section-title mb-1">Unusual records</h2>
            <p class="section-copy mb-0">These entries have validation or lifecycle issues that should be checked and corrected.</p>
        </div>

        <?php if ($alerts['unusual'] === []): ?>
            <div class="empty-state">No unusual records were detected by the current validation rules.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Condition</th>
                            <th>Review notes</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts['unusual'] as $asset): ?>
                            <tr>
                                <td>
                                    <strong><?= e($asset['asset_name']) ?></strong>
                                    <div class="text-soft small"><?= e($asset['asset_code']) ?> / <?= e(asset_department_label($asset)) ?></div>
                                </td>
                                <td><span class="badge <?= e(condition_badge_class((string) $asset['condition'])) ?>"><?= e($asset['condition']) ?></span></td>
                                <td><?= e(excerpt(implode('; ', $asset['anomalies']), 220)) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-light" href="<?= e(base_url('modules/view_asset.php?asset_id=' . $asset['asset_id'])) ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
