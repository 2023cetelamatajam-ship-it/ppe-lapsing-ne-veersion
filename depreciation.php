<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$assetLookup = fetch_asset_lookup($pdo);
$branchGroup = trim((string) request_value('branch_group', 'all'));
$branchGroups = [
    'all' => 'All branches',
    'mitsubishi' => 'Mitsubishi',
    'hyundai' => 'Hyundai',
    'fuso' => 'Fuso',
];

$filteredAssetLookup = array_values(array_filter($assetLookup, function (array $asset) use ($branchGroup): bool {
    if ($branchGroup === 'all') {
        return true;
    }

    $branchName = get_asset_branch_name($asset);
    return match ($branchGroup) {
        'mitsubishi' => str_starts_with($branchName, 'Mitsubishi'),
        'hyundai' => str_starts_with($branchName, 'Hyundai'),
        'fuso' => str_starts_with($branchName, 'Fuso'),
        default => true,
    };
}));

$selectedAssetId = (int) request_value('asset_id', $filteredAssetLookup[0]['asset_id'] ?? 0);
$selectedAsset = $selectedAssetId > 0 ? fetch_asset_by_id($pdo, $selectedAssetId) : null;
$schedule = $selectedAsset ? fetch_depreciation_rows($pdo, $selectedAssetId) : [];
$metrics = $selectedAsset ? get_asset_metrics($selectedAsset) : null;
$monthlyDepreciation = $metrics ? round($metrics['annual_depreciation'] / 12, 2) : 0.0;
$yearlyDepreciationTotal = $metrics ? round($metrics['annual_depreciation'], 2) : 0.0;
$monthlyDepreciationSchedule = [];
if ($metrics) {
    $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $annualAmount = $metrics['annual_depreciation'];
    $baseMonth = $monthlyDepreciation;
    $lastMonth = round($annualAmount - ($baseMonth * 11), 2);
    foreach ($monthNames as $index => $monthName) {
        $monthlyDepreciationSchedule[$monthName] = $index === 11 ? $lastMonth : $baseMonth;
    }
}

$pageTitle = 'Depreciation';
$pageHeading = 'Depreciation Schedule';

require_once APP_ROOT . '/includes/header.php';
?>
<style>
    body {
        background: #000 !important;
        color: #f8f8f8 !important;
    }
    .shell-card,
    .metric-card,
    .table-wrap,
    .depreciation-workbook,
    .modal-content {
        background: #0f0f0f !important;
        border-color: #2b2b2b !important;
        color: #f8f8f8 !important;
    }
    .btn-outline-light,
    .btn-outline-secondary,
    .btn-outline-dark,
    .btn-outline-primary,
    .btn-outline-success {
        color: #f8f8f8 !important;
        border-color: #5c5c5c !important;
    }
    .btn-outline-light:hover,
    .btn-outline-secondary:hover,
    .btn-outline-dark:hover,
    .btn-outline-primary:hover,
    .btn-outline-success:hover {
        background: #1a1a1a !important;
        border-color: #8c8c8c !important;
    }
    .eyebrow,
    .metric-label,
    .metric-meta,
    .section-copy,
    .text-soft,
    .small,
    .badge {
        color: #d1d1d1 !important;
    }
    .badge {
        background: #1c1c1c !important;
    }
    .metric-card::after {
        display: none;
    }
    .table {
        color: #f8f8f8 !important;
    }
    .table th,
    .table td {
        border-color: rgba(255, 255, 255, 0.08) !important;
    }
    .table thead th {
        color: #fff !important;
    }
    .table tbody tr:hover {
        background: rgba(255, 255, 255, 0.04) !important;
    }
    #monthlyDepreciationModal .modal-body {
        max-height: calc(100vh - 220px);
        overflow-y: auto;
        padding-right: 1rem;
    }
    #monthlyDepreciationModal .table-wrap {
        background: rgba(255,255,255,0.03) !important;
    }
    #monthlyDepreciationModal .modal-header,
    #monthlyDepreciationModal .modal-footer {
        border-color: #2b2b2b !important;
    }
</style>
<?php if ($assetLookup === []): ?>
    <section class="shell-card">
        <div class="empty-state">
            No assets are available yet. Add a PPE record first to generate a depreciation schedule.
        </div>
    </section>
<?php else: ?>
    <section class="shell-card mb-4">
        <div class="mb-4">
            <p class="eyebrow mb-2">Schedule lookup</p>
            <h2 class="section-title mb-1">Choose an asset</h2>
        </div>
        <form method="get" class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label visually-hidden" for="branch_group">Branch group</label>
                <select class="form-select" id="branch_group" name="branch_group" onchange="this.form.submit()">
                    <?php foreach ($branchGroups as $groupKey => $groupLabel): ?>
                        <option value="<?= e($groupKey) ?>" <?= selected_if($branchGroup, $groupKey) ?>>
                            <?= e($groupLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-6">
                <label class="form-label visually-hidden" for="asset_id">Asset</label>
                <select class="form-select" id="asset_id" name="asset_id" onchange="this.form.submit()">
                    <?php foreach ($filteredAssetLookup as $assetOption): ?>
                        <option value="<?= e((string) $assetOption['asset_id']) ?>" <?= selected_if($selectedAssetId, $assetOption['asset_id']) ?>>
                            <?= e($assetOption['asset_code'] . ' - ' . $assetOption['asset_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 d-flex gap-2">
                <?php if ($selectedAsset): ?>
                    <a class="btn btn-outline-light w-100" href="<?= e(base_url('modules/view_asset.php?asset_id=' . $selectedAssetId)) ?>">Asset Detail</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <?php if ($selectedAsset && $metrics): ?>
        <div class="metric-grid mb-4">
            <section class="metric-card">
                <p class="metric-label mb-2">Acquisition Price</p>
                <h2 class="metric-value mb-1"><?= e(money((float) $selectedAsset['acquisition_cost'])) ?></h2>
                <p class="metric-meta mb-0">Initial recorded cost</p>
            </section>
            <section class="metric-card">
                <p class="metric-label mb-2">Salvage Value</p>
                <h2 class="metric-value mb-1"><?= e(money((float) $selectedAsset['salvage_value'])) ?></h2>
                <p class="metric-meta mb-0">Expected residual value</p>
            </section>
            <section class="metric-card">
                <p class="metric-label mb-2">Useful Life</p>
                <h2 class="metric-value mb-1"><?= e((string) $selectedAsset['useful_life']) ?> years</h2>
                <p class="metric-meta mb-0">Applied straight-line period</p>
            </section>
            <section class="metric-card">
                <p class="metric-label mb-2">Annual Depreciation</p>
                <h2 class="metric-value mb-1"><?= e(money($metrics['annual_depreciation'])) ?></h2>
                <p class="metric-meta mb-0">Recognized over 12 months</p>
            </section>
            <section class="metric-card metric-card-clickable" data-bs-toggle="modal" data-bs-target="#monthlyDepreciationModal">
                <p class="metric-label mb-2">Monthly Depreciation</p>
                <h2 class="metric-value mb-1"><?= e(money($monthlyDepreciation)) ?></h2>
                <p class="metric-meta mb-0">Click to view January–December breakdown</p>
            </section>
            <section class="metric-card">
                <p class="metric-label mb-2">1-Year Total</p>
                <h2 class="metric-value mb-1"><?= e(money($yearlyDepreciationTotal)) ?></h2>
                <p class="metric-meta mb-0">Total depreciation for one year</p>
            </section>
            <section class="metric-card">
                <p class="metric-label mb-2">Net Book Value</p>
                <h2 class="metric-value mb-1"><?= e(money($metrics['carrying_amount'])) ?></h2>
                <p class="metric-meta mb-0">Cost less accumulated depreciation</p>
            </section>
        </div>

        <section class="shell-card depreciation-workbook">
            <div class="worksheet-summary">
                <p class="eyebrow mb-2">Depreciation workbook</p>
                <h2 class="section-title mb-1">Excel-style depreciation sheet</h2>
                <p class="section-copy mb-0">This worksheet shows monthly values and a one-year depreciation total automatically.</p>
            </div>
            <div class="worksheet-summary worksheet-summary-secondary">
                <div class="summary-entry">
                    <span>Annual depreciation</span>
                    <strong><?= e(money($metrics['annual_depreciation'])) ?></strong>
                </div>
                <div class="summary-entry summary-entry-clickable" data-bs-toggle="modal" data-bs-target="#monthlyDepreciationModal">
                    <span>Monthly depreciation</span>
                    <strong><?= e(money($monthlyDepreciation)) ?></strong>
                    <small class="text-soft">Click to expand monthly details</small>
                </div>
                <div class="summary-entry">
                    <span>1-year depreciation total</span>
                    <strong><?= e(money($yearlyDepreciationTotal)) ?></strong>
                </div>
            </div>
            <div class="text-end mt-3">
                <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#monthlyDepreciationModal">
                    View monthly depreciation schedule
                </button>
            </div>
        </section>
        <div class="modal fade" id="monthlyDepreciationModal" tabindex="-1" aria-labelledby="monthlyDepreciationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content bg-dark border-secondary">
                    <div class="modal-header">
                        <h5 class="modal-title" id="monthlyDepreciationModalLabel">Monthly Depreciation Breakdown</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="monthlyDepreciationModalClose"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">This shows the monthly depreciation amount for each month of the year based on the current annual depreciation amount.</p>
                        <div class="table-wrap">
                            <table class="table table-borderless mb-0">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th class="text-end">Depreciation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyDepreciationSchedule as $month => $amount): ?>
                                        <tr>
                                            <td><?= e($month) ?></td>
                                            <td class="text-end"><?= e(money($amount)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th class="text-end"><?= e(money($yearlyDepreciationTotal)) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            (function () {
                const modalEl = document.getElementById('monthlyDepreciationModal');
                const closeButtons = [
                    document.getElementById('monthlyDepreciationModalClose')
                ].filter(Boolean);

                const hideModalFallback = () => {
                    if (!modalEl) return;
                    modalEl.classList.remove('show');
                    modalEl.style.display = 'none';
                    document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                };

                closeButtons.forEach((button) => {
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        if (window.bootstrap && window.bootstrap.Modal) {
                            const modalInstance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                            modalInstance.hide();
                        } else {
                            hideModalFallback();
                        }
                    });
                });

                window.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modalEl && modalEl.classList.contains('show')) {
                        if (window.bootstrap && window.bootstrap.Modal) {
                            const modalInstance = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                            modalInstance.hide();
                        } else {
                            hideModalFallback();
                        }
                    }
                });
            })();
        </script>

        <section class="shell-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-2"><?= e($selectedAsset['asset_code']) ?></p>
                    <h2 class="section-title mb-1"><?= e($selectedAsset['asset_name']) ?></h2>
                    <p class="section-copy mb-0">Net value in this table already reflects the salvage deduction from the opening year.</p>
                </div>
                <div class="stack-inline justify-content-end">
                    <a class="btn btn-sm btn-outline-light" href="<?= e(base_url('modules/export.php?type=schedule&asset_id=' . $selectedAssetId)) ?>">Export CSV</a>
                    <a class="btn btn-sm btn-outline-light" href="<?= e(base_url('modules/print_view.php?type=schedule&asset_id=' . $selectedAssetId)) ?>" target="_blank" rel="noopener">Print</a>
                    <span class="badge <?= e(status_badge_class((string) $selectedAsset['status'])) ?>"><?= e($selectedAsset['status']) ?></span>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table align-middle lapsing-table">
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Cost</th>
                            <th>Additional</th>
                            <th>Annual Depreciation</th>
                            <th>Monthly Depreciation</th>
                            <th>Accumulated Depreciation</th>
                            <th>Net Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedule as $row): ?>
                            <tr>
                                <td><?= e((string) $row['depreciation_year']) ?></td>
                                <td><?= e(money((float) $selectedAsset['acquisition_cost'])) ?></td>
                                <td><?= e(money((float) ($selectedAsset['additional_amount'] ?? 0))) ?></td>
                                <td><?= e(money((float) $row['depreciation_expense'])) ?></td>
                                <td><?= e(money(round((float) $row['depreciation_expense'] / 12, 2))) ?></td>
                                <td><?= e(money((float) $row['accumulated_depreciation'])) ?></td>
                                <td><?= e(money(schedule_display_net_value($selectedAsset, $row))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
