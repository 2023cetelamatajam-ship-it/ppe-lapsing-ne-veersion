<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$assetLookup = fetch_asset_lookup($pdo);
$selectedAssetId = (int) request_value('asset_id', $assetLookup[0]['asset_id'] ?? 0);
$selectedAsset = $selectedAssetId > 0 ? fetch_asset_by_id($pdo, $selectedAssetId) : null;

if ($selectedAssetId > 0 && !$selectedAsset && $assetLookup !== []) {
    $selectedAssetId = (int) $assetLookup[0]['asset_id'];
    $selectedAsset = fetch_asset_by_id($pdo, $selectedAssetId);
}

$pageTitle = 'Exports';
$pageHeading = 'Exports and Print Center';
$pageDescription = 'Download clean CSV files or open print-friendly views for assets, alerts, transfers, and depreciation schedules.';

require_once APP_ROOT . '/includes/header.php';
?>
<section class="shell-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="eyebrow mb-2">Schedule selection</p>
            <h2 class="section-title mb-1">Choose an asset for schedule output</h2>
            <p class="section-copy mb-0">System-wide exports work immediately. Depreciation schedule export and print actions use the selected asset below.</p>
        </div>
    </div>

    <?php if ($assetLookup === []): ?>
        <div class="empty-state">No assets are available yet, so schedule-specific export options are currently unavailable.</div>
    <?php else: ?>
        <form method="get" class="row g-3 align-items-end">
            <div class="col-lg-9">
                <label class="form-label" for="asset_id">Asset</label>
                <select class="form-select" id="asset_id" name="asset_id">
                    <?php foreach ($assetLookup as $assetOption): ?>
                        <option value="<?= e((string) $assetOption['asset_id']) ?>" <?= selected_if($selectedAssetId, $assetOption['asset_id']) ?>>
                            <?= e($assetOption['asset_code'] . ' - ' . $assetOption['asset_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-search me-2"></i>Load Asset
                </button>
            </div>
        </form>
    <?php endif; ?>
</section>

<div class="row g-4">
    <div class="col-xl-6">
        <section class="shell-card h-100">
            <div class="mb-4">
                <p class="eyebrow mb-2">Downloads</p>
                <h2 class="section-title mb-1">CSV exports</h2>
                <p class="section-copy mb-0">Use these files for audit support, reconciliations, or spreadsheet analysis.</p>
            </div>

            <div class="list-panel">
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Asset register</strong>
                        <p class="text-soft small mb-0">Full PPE listing with net values, conditions, and remarks.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/export.php?type=assets')) ?>">Download</a>
                </div>
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Alerts queue</strong>
                        <p class="text-soft small mb-0">Near-end, status mismatch, and data-quality issues in one export.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/export.php?type=alerts')) ?>">Download</a>
                </div>
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Transfer history</strong>
                        <p class="text-soft small mb-0">Department and location movement history for all transferred assets.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/export.php?type=transfers')) ?>">Download</a>
                </div>
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Selected schedule</strong>
                        <p class="text-soft small mb-0">
                            <?php if ($selectedAsset): ?>
                                Export the depreciation schedule for <?= e($selectedAsset['asset_name']) ?>.
                            <?php else: ?>
                                Choose an asset above to enable schedule export.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($selectedAsset): ?>
                        <a class="btn btn-outline-light" href="<?= e(base_url('modules/export.php?type=schedule&asset_id=' . $selectedAsset['asset_id'])) ?>">Download</a>
                    <?php else: ?>
                        <button class="btn btn-outline-light" type="button" disabled>Unavailable</button>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="shell-card h-100">
            <div class="mb-4">
                <p class="eyebrow mb-2">Output views</p>
                <h2 class="section-title mb-1">Print layouts</h2>
                <p class="section-copy mb-0">Open clean printer-friendly pages for physical files, reviews, or sign-off packs.</p>
            </div>

            <div class="list-panel">
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Asset register print view</strong>
                        <p class="text-soft small mb-0">A printable version of the current PPE register.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/print_view.php?type=assets')) ?>" target="_blank" rel="noopener">Open</a>
                </div>
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Alert digest print view</strong>
                        <p class="text-soft small mb-0">A review-ready summary of assets needing action.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/print_view.php?type=alerts')) ?>" target="_blank" rel="noopener">Open</a>
                </div>
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Transfer log print view</strong>
                        <p class="text-soft small mb-0">A printable history of assignment movements and location changes.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/print_view.php?type=transfers')) ?>" target="_blank" rel="noopener">Open</a>
                </div>
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Selected schedule print view</strong>
                        <p class="text-soft small mb-0">
                            <?php if ($selectedAsset): ?>
                                Open a printable schedule for <?= e($selectedAsset['asset_code']) ?>.
                            <?php else: ?>
                                Choose an asset above to enable schedule printing.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($selectedAsset): ?>
                        <a class="btn btn-outline-light" href="<?= e(base_url('modules/print_view.php?type=schedule&asset_id=' . $selectedAsset['asset_id'])) ?>" target="_blank" rel="noopener">Open</a>
                    <?php else: ?>
                        <button class="btn btn-outline-light" type="button" disabled>Unavailable</button>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
