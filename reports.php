<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$assets = hydrate_assets_with_metrics(fetch_assets($pdo));
$metrics = build_dashboard_metrics($assets);
$categorySummary = build_category_summary($assets);
$departmentSummary = build_department_summary($assets);
$alerts = build_asset_alerts($assets);

$pageTitle = 'Reports';
$pageHeading = 'Reports and Audit View';

require_once APP_ROOT . '/includes/header.php';
?>
<?php if ($assets === []): ?>
    <section class="shell-card">
        <div class="empty-state">No assets are available yet, so there are no report totals to display.</div>
    </section>
<?php else: ?>
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <section class="shell-card h-100">
                <p class="eyebrow mb-2">Portfolio summary</p>
                <h2 class="section-title mb-3">Risk statement</h2>
                <p class="mb-0"><?= e(build_risk_summary($metrics, $alerts)) ?></p>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="shell-card h-100">
                <p class="eyebrow mb-2">Replacement planning</p>
                <h2 class="section-title mb-3">Near end of life</h2>
                <p class="mb-0"><?= e((string) count($alerts['near_end'])) ?> asset(s) are approaching the end of useful life.</p>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="shell-card h-100">
                <p class="eyebrow mb-2">Record quality</p>
                <h2 class="section-title mb-3">Flagged entries</h2>
                <p class="mb-0"><?= e((string) count($alerts['unusual'])) ?> asset(s) need closer checking for possible setup or status issues.</p>
            </section>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <section class="shell-card h-100">
                <div class="mb-3">
                    <p class="eyebrow mb-2">Breakdown</p>
                    <h2 class="section-title mb-1">Category summary</h2>
                </div>
                <div class="table-wrap">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Assets</th>
                                <th>Total Cost</th>
                                <th>Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categorySummary as $category): ?>
                                <tr>
                                    <td><?= e($category['label']) ?></td>
                                    <td><?= e((string) $category['asset_count']) ?></td>
                                    <td><?= e(money($category['total_cost'])) ?></td>
                                    <td><?= e(money($category['total_carrying'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-xl-6">
            <section class="shell-card h-100">
                <div class="mb-3">
                    <p class="eyebrow mb-2">Allocation</p>
                    <h2 class="section-title mb-1">Department summary</h2>
                </div>
                <div class="table-wrap">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Assets</th>
                                <th>Total Cost</th>
                                <th>Carrying</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departmentSummary as $department): ?>
                                <tr>
                                    <td><?= e($department['label']) ?></td>
                                    <td><?= e((string) $department['asset_count']) ?></td>
                                    <td><?= e(money($department['total_cost'])) ?></td>
                                    <td><?= e(money($department['total_carrying'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <section class="shell-card h-100">
                <div class="mb-3">
                    <p class="eyebrow mb-2">Priority list</p>
                    <h2 class="section-title mb-0">Near-end assets</h2>
                </div>
                <?php if ($alerts['near_end'] === []): ?>
                    <div class="empty-state">No assets are currently near the end of useful life.</div>
                <?php else: ?>
                    <div class="list-panel">
                        <?php foreach (array_slice($alerts['near_end'], 0, 6) as $asset): ?>
                            <div class="list-row">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= e($asset['asset_name']) ?></strong>
                                        <div class="text-soft small"><?= e($asset['asset_code']) ?> / <?= e((string) $asset['department_name']) ?></div>
                                    </div>
                                    <div class="text-end">
                                        <div><?= e((string) $asset['remaining_years']) ?> year(s)</div>
                                        <div class="text-soft small">remaining</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="shell-card h-100">
                <div class="mb-3">
                    <p class="eyebrow mb-2">Audit queue</p>
                    <h2 class="section-title mb-0">Unusual records</h2>
                </div>
                <?php if ($alerts['unusual'] === []): ?>
                    <div class="empty-state">No unusual records were detected by the current validation checks.</div>
                <?php else: ?>
                    <div class="list-panel">
                        <?php foreach (array_slice($alerts['unusual'], 0, 6) as $asset): ?>
                            <div class="list-row">
                                <strong><?= e($asset['asset_name']) ?> (<?= e($asset['asset_code']) ?>)</strong>
                                <p class="text-soft small mb-0"><?= e(implode('; ', $asset['anomalies'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
<?php endif; ?>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
