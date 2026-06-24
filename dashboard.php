<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();    

$pdo = db();
$assets = hydrate_assets_with_metrics(fetch_assets($pdo));
$metrics = build_dashboard_metrics($assets);
$alerts = build_asset_alerts($assets);
$categorySummary = array_slice(build_category_summary($assets), 0, 5);

// Get filter parameters
$currentBranch = $_GET['branch'] ?? '';
$currentBrand = $_GET['brand'] ?? '';

// Use fixed branch groups and fixed known companies
$branches = get_known_branches();
$brands = get_known_companies();

// Apply filters to assets for display
$filteredAssets = filter_assets_by_branch_and_brand($assets, $currentBranch, $currentBrand);
$filterMetrics = build_dashboard_metrics($filteredAssets);

// Build branch and company metrics for filter cards
$branchMetrics = [];
foreach ($branches as $branch) {
    $branchAssets = array_filter($assets, fn($a) => get_asset_branch_name($a) === $branch);
    $branchMetrics[$branch] = build_dashboard_metrics($branchAssets);
}

$companyMetrics = [];
foreach ($brands as $brand) {
    $brandAssets = array_filter($assets, fn($a) => get_asset_brand_tag($a) === $brand);
    $companyMetrics[$brand] = build_dashboard_metrics($brandAssets);
}

// Calculate consolidated totals
$consolidated = [
    'total_cost' => array_sum(array_column($branchMetrics, 'total_cost')),
    'total_accumulated' => array_sum(array_column($branchMetrics, 'total_accumulated')),
    'total_carrying' => array_sum(array_column($branchMetrics, 'total_carrying')),
    'asset_count' => array_sum(array_column($branchMetrics, 'asset_count')),
    'near_end_count' => array_sum(array_column($branchMetrics, 'near_end_count')),
    'fully_depreciated_count' => array_sum(array_column($branchMetrics, 'fully_depreciated_count')),
];

$pageTitle = 'PPE Dashboard';
$pageHeading = 'Industrial Asset Depreciation Dashboard';

require_once APP_ROOT . '/includes/header.php';
?>

<style>
.bg-primary-soft {
    background-color: rgba(13, 110, 253, 0.15);
}
.bg-success-soft {
    background-color: rgba(25, 135, 84, 0.15);
}
.bg-warning-soft {
    background-color: rgba(255, 193, 7, 0.15);
}
.bg-info-soft {
    background-color: rgba(13, 202, 240, 0.15);
}
.filter-card {
    transition: all 0.2s ease;
    cursor: pointer;
}
.filter-card:hover {
    transform: translateY(-3px);
}
.metric-comparison {
    font-size: 0.85rem;
    border-top: 1px solid rgba(255,255,255,0.1);
    margin-top: 8px;
    padding-top: 8px;
}
.depreciation-bar {
    height: 8px;
    background: linear-gradient(90deg, #ffc107, #dc3545);
    border-radius: 4px;
    transition: width 0.3s ease;
}
.heatmap-cell {
    text-align: center;
    padding: 10px;
    border-radius: 8px;
}
.heatmap-low { background: rgba(40, 167, 69, 0.2); }
.heatmap-medium { background: rgba(255, 193, 7, 0.3); }
.heatmap-high { background: rgba(220, 53, 69, 0.4); }
</style>

<?php if ($assets === []): ?>
    <section class="shell-card">
        <div class="empty-state">
            <h2 class="section-title mb-2">No assets have been recorded yet</h2>
            <p class="mb-3">Start by adding PPE items so the system can generate dashboards, schedules, and reports.</p>
            <?php if (can_manage_assets()): ?>
                <a class="btn btn-primary" href="<?= e(base_url('modules/add_asset.php')) ?>">
                    <i class="bi bi-plus-circle me-2"></i>Add the first asset
                </a>
            <?php endif; ?>
        </div>
    </section>
<?php else: ?>
    
    <!-- COMPANY TOTALS -->
    <div class="metric-grid metric-grid-triple mb-4">
        <section class="metric-card bg-primary-soft">
            <p class="metric-label mb-2">🏢 TOTAL GROUP PPE VALUE</p>
            <h2 class="metric-value mb-1"><?= e(money($consolidated['total_cost'])) ?></h2>
            <p class="metric-meta mb-0">Across <?= count($branches) ?> branches | <?= count($brands) ?> companies</p>
        </section>
        <section class="metric-card bg-warning-soft">
            <p class="metric-label mb-2">📉 TOTAL ACCUMULATED DEPRECIATION</p>
            <h2 class="metric-value mb-1"><?= e(money($consolidated['total_accumulated'])) ?></h2>
            <p class="metric-meta mb-0"><?= round(($consolidated['total_accumulated'] / max($consolidated['total_cost'], 1)) * 100, 1) ?>% of total cost</p>
            <div class="depreciation-bar" style="width: <?= round(($consolidated['total_accumulated'] / max($consolidated['total_cost'], 1)) * 100) ?>%"></div>
        </section>
        <section class="metric-card bg-success-soft">
            <p class="metric-label mb-2">💰 TOTAL NET BOOK VALUE</p>
            <h2 class="metric-value mb-1"><?= e(money($consolidated['total_carrying'])) ?></h2>
            <p class="metric-meta mb-0">Remaining value after depreciation</p>
        </section>
        <section class="metric-card bg-info-soft">
            <p class="metric-label mb-2">⚙️ ASSET COUNT</p>
            <h2 class="metric-value mb-1"><?= e((string) $consolidated['asset_count']) ?></h2>
            <p class="metric-meta mb-0">Total active assets in group</p>
        </section>
        <section class="metric-card bg-danger-soft">
            <p class="metric-label mb-2">⚠️ REPLACEMENT CANDIDATES</p>
            <h2 class="metric-value mb-1"><?= e((string) $consolidated['near_end_count']) ?></h2>
            <p class="metric-meta mb-0">Assets nearing end of life</p>
        </section>
        <section class="metric-card bg-secondary-soft">
            <p class="metric-label mb-2">✅ FULLY DEPRECIATED</p>
            <h2 class="metric-value mb-1"><?= e((string) $consolidated['fully_depreciated_count']) ?></h2>
            <p class="metric-meta mb-0">At salvage value</p>
        </section>
    </div>


    <!-- BRANCH FILTER SECTION (always visible) -->
    <section class="shell-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <p class="eyebrow mb-2">📍 Branches</p>
                <h2 class="section-title mb-1">Filter by branch</h2>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ($branches as $branch): 
                $isActive = ($currentBranch === $branch);
                $branchCount = count(array_filter($assets, fn($a) => get_asset_branch_name($a) === $branch));
                $branchData = $branchMetrics[$branch] ?? ['total_cost' => 0];
            ?>
            <div class="col-md-4">
                <a href="?branch=<?= e(urlencode($branch)) ?><?= $currentBrand ? '&brand=' . urlencode($currentBrand) : '' ?>" 
                   class="text-decoration-none">
                    <div class="filter-card card text-center p-3 <?= $isActive ? 'border-primary bg-primary-soft' : 'bg-dark' ?>">
                        <h5 class="mb-1 <?= $isActive ? 'text-primary' : 'text-white' ?>"><?= e($branch) ?></h5>
                        <small class="text-soft">
                            <?= $branchCount ?> <?= pluralize($branchCount, 'asset') ?>
                        </small>
                        <small class="text-soft mt-1">
                            Net Value: <?= money($branchData['total_carrying'] ?? 0) ?>
                        </small>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($currentBranch): ?>
        <div class="text-end mt-3">
            <a href="?<?= $currentBrand ? 'brand=' . urlencode($currentBrand) : '' ?>" class="btn btn-sm btn-outline-secondary">
                ✕ Clear branch filter
            </a>
        </div>
        <?php endif; ?>
    </section>

    <!-- BRAND FILTER SECTION -->
    <section class="shell-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div> 
                <p class="eyebrow mb-2">🏷️ Companies</p>
                <h2 class="section-title mb-1">Filter by co-company</h2>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ($brands as $brand): 
                $isActive = ($currentBrand === $brand);
                $brandCount = count(array_filter($assets, fn($a) => get_asset_brand_tag($a) === $brand));
                $brandData = $companyMetrics[$brand] ?? ['total_cost' => 0, 'total_carrying' => 0];
            ?>
            <div class="col-md-3">
                <a href="?brand=<?= e(urlencode($brand)) ?><?= $currentBranch ? '&branch=' . urlencode($currentBranch) : '' ?>" 
                   class="text-decoration-none">
                    <div class="filter-card card text-center p-3 <?= $isActive ? 'border-success bg-success-soft' : 'bg-dark' ?>">
                        <h5 class="mb-1 <?= $isActive ? 'text-success' : 'text-white' ?>"><?= e($brand) ?></h5>
                        <small class="text-soft d-block">
                            <?= $brandCount ?> <?= pluralize($brandCount, 'asset') ?>
                        </small>
                        <small class="text-soft d-block">
                            Asset: <?= money($brandData['total_cost'] ?? 0) ?>
                        </small>
                        <small class="text-soft mt-1 d-block">
                            Net: <?= money($brandData['total_carrying'] ?? 0) ?>
                        </small>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($currentBrand): ?>
        <div class="text-end mt-3">
            <a href="?<?= $currentBranch ? 'branch=' . urlencode($currentBranch) : '' ?>" class="btn btn-sm btn-outline-secondary">
                ✕ Clear brand filter
            </a>
        </div>
        <?php endif; ?>
    </section>

    <!-- Current Filter Metrics Display -->
    <div class="metric-grid metric-grid-triple mb-4">
        <section class="metric-card">
            <p class="metric-label mb-2">Current View PPE Cost</p>
            <h2 class="metric-value mb-1"><?= e(money($filterMetrics['total_cost'])) ?></h2>
            <p class="metric-meta mb-0"><?= e((string) $filterMetrics['asset_count']) ?> <?= pluralize((int) $filterMetrics['asset_count'], 'asset') ?></p>
        </section>
        <section class="metric-card">
            <p class="metric-label mb-2">Current Depreciation</p>
            <h2 class="metric-value mb-1"><?= e(money($filterMetrics['total_accumulated'])) ?></h2>
            <p class="metric-meta mb-0"><?= round(($filterMetrics['total_accumulated'] / max($filterMetrics['total_cost'], 1)) * 100, 1) ?>% depreciated</p>
        </section>
        <section class="metric-card">
            <p class="metric-label mb-2">Current Net Value</p>
            <h2 class="metric-value mb-1"><?= e(money($filterMetrics['total_carrying'])) ?></h2>
            <p class="metric-meta mb-0">Book value after depreciation</p>
        </section>
    </div>

    <!-- ASSETS TABLE WITH FILTERS -->
    <div class="row g-4">
        <div class="col-lg-7">
            <section class="shell-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <p class="eyebrow mb-2">Assets</p>
                        <h2 class="section-title mb-1">
                            <?php 
                            $filterDesc = [];
                            if ($currentBranch) $filterDesc[] = "Branch: " . $currentBranch;
                            if ($currentBrand) $filterDesc[] = "Company: " . $currentBrand;
                            if (!$filterDesc) $filterDesc[] = "All Assets";
                            echo implode(' | ', $filterDesc);
                            ?>
                            <span class="badge bg-secondary ms-2"><?= count($filteredAssets) ?> results</span>
                        </h2>
                    </div>
                    <div>
                        <?php if ($currentBranch || $currentBrand): ?>
                        <a class="btn btn-sm btn-outline-secondary me-2" href="?">
                            Clear all filters
                        </a>
                        <?php endif; ?>
                        <a class="btn btn-outline-light" href="<?= e(base_url('modules/assets.php')) ?>">View all assets</a>
                    </div>
                </div>
                
                <?php if (count($filteredAssets) === 0): ?>
                    <div class="empty-state py-4 text-center">
                        <p class="text-soft mb-2">No assets match the selected filters.</p>
                        <a href="?" class="btn btn-sm btn-primary">Reset filters</a>
                    </div>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Branch</th>
                                <th>Company</th>
                                <th>Status</th>
                                <th>Condition</th>
                                <th>Net Amount</th>
                                <th>Dep. %</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($filteredAssets, 0, 10) as $asset): 
                                $depPercent = $asset['acquisition_cost'] > 0 ? round(($asset['accumulated_depreciation'] / asset_total_cost($asset)) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= e($asset['asset_name']) ?></strong>
                                        <div class="text-soft small"><?= e($asset['asset_code']) ?> / <?= e((string) ($asset['category_name'] ?? 'Uncategorized')) ?></div>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= e(get_asset_branch_name($asset)) ?></span></td>
                                    <td><span class="badge bg-info"><?= e(get_asset_brand_tag($asset)) ?></span></td>
                                    <td><span class="badge <?= e(status_badge_class((string) $asset['status'])) ?>"><?= e($asset['status']) ?></span></td>
                                    <td><span class="badge <?= e(condition_badge_class((string) $asset['condition'])) ?>"><?= e($asset['condition']) ?></span></td>
                                    <td><?= e(money((float) $asset['carrying_amount'])) ?></td>
                                    <td>>
                                        <?= $depPercent ?>%
                                        <div class="progress mt-1" style="height: 3px;">
                                            <div class="progress-bar bg-warning" style="width: <?= $depPercent ?>%"></div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-light" onclick="openAssetModal(<?= (int) $asset['asset_id'] ?>)">View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
        </div>

        <div class="col-lg-5">
            <!-- Categories Section -->
            <section class="shell-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="eyebrow mb-2">Categories</p>
                        <h2 class="section-title mb-1">Assets by category</h2>
                    </div>
                    <a class="btn btn-outline-light btn-sm" href="<?= e(base_url('modules/reports.php')) ?>">Full report</a>
                </div>
                <div class="list-panel">
                    <?php foreach ($categorySummary as $category): ?>
                        <div class="list-row">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= e($category['label']) ?></strong>
                                    <div class="text-soft small"><?= e((string) $category['asset_count']) ?> <?= e(pluralize((int) $category['asset_count'], 'asset')) ?></div>
                                </div>
                                <div class="text-end">
                                    <div><?= e(money($category['total_cost'])) ?></div>
                                    <div class="text-soft small"><?= e(money($category['total_carrying'])) ?> net</div>
                                    <div class="text-soft small"><?= round(($category['total_accumulated'] / max($category['total_cost'], 1)) * 100, 1) ?>% dep.</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Depreciation Forecast Widget -->
            <section class="shell-card mb-4" id="forecastWidget">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="eyebrow mb-2">Forecast</p>
                        <h2 class="section-title mb-1">5-Year Depreciation Projection</h2>
                    </div>
                    <button class="btn btn-sm btn-outline-light" onclick="refreshForecast()">
                        <i class="bi bi-arrow-repeat"></i> Refresh
                    </button>
                </div>
                <div id="forecastChart" style="height: 250px;">
                    <canvas id="forecastCanvas"></canvas>
                </div>
                <div class="text-center mt-2">
                    <small class="text-soft">Based on current asset base and remaining useful life</small>
                </div>
            </section>

            <!-- Flagged Records Section -->
            <section class="shell-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="eyebrow mb-2">Audit queue</p>
                        <h2 class="section-title mb-1">Flagged records</h2>
                        <p class="section-copy mb-0">Warnings here usually mean a record needs validation, update, or disposal follow-up.</p>
                    </div>
                    <a class="btn btn-outline-light btn-sm" href="<?= e(base_url('modules/reports.php')) ?>">Open reports</a>
                </div>

                <?php if ($alerts['unusual'] === []): ?>
                    <div class="empty-state">No unusual records are waiting for review.</div>
                <?php else: ?>
                    <div class="list-panel">
                        <?php foreach (array_slice($alerts['unusual'], 0, 4) as $asset): ?>
                            <div class="list-row">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong><?= e($asset['asset_name']) ?></strong>
                                    <span class="badge <?= e(status_badge_class((string) $asset['status'])) ?>"><?= e($asset['status']) ?></span>
                                </div>
                                <p class="text-soft small mb-1"><?= e($asset['asset_code']) ?> / <?= e(get_asset_branch_name($asset)) ?> / <?= e(get_asset_brand_tag($asset)) ?></p>
                                <p class="text-soft small mb-0"><?= e(excerpt(implode('; ', $asset['anomalies']), 180)) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="shell-card">
                <input type="hidden" id="chatBranch" value="<?= e($currentBranch) ?>">
                <input type="hidden" id="chatBrand" value="<?= e($currentBrand) ?>">

            </section>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let forecastChart = null;

async function refreshForecast() {
    const branch = document.getElementById('chatBranch')?.value || '';
    const brand = document.getElementById('chatBrand')?.value || '';
    
    try {
        const response = await fetch('<?= e(base_url('api/forecast.php')) ?>?branch=' + encodeURIComponent(branch) + '&brand=' + encodeURIComponent(brand));
        const data = await response.json();
        
        if (forecastChart) {
            forecastChart.destroy();
        }
        
        const ctx = document.getElementById('forecastCanvas').getContext('2d');
        forecastChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5'],
                datasets: [{
                    label: 'Projected Annual Depreciation',
                    data: data.forecast,
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: { color: '#e0e0e0' }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: { color: '#e0e0e0', callback: function(value) { return '₱' + value.toLocaleString(); } },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    },
                    x: {
                        ticks: { color: '#e0e0e0' },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    }
                }
            }
        });
    } catch (err) {
        console.error('Forecast error:', err);
    }
}

refreshForecast();
</script>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>

<!-- Asset detail modal -->
<div class="modal fade" id="assetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="assetModalTitle">Asset details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <strong id="modalAssetName">&nbsp;</strong>
                        <div class="text-soft small" id="modalAssetCode">&nbsp;</div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <p class="mb-1 small text-muted">Acquisition Price</p>
                        <div id="modalAcquisition">—</div>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1 small text-muted">Salvage Value</p>
                        <div id="modalSalvage">—</div>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1 small text-muted">Useful Life</p>
                        <div id="modalLife">—</div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <p class="mb-1 small text-muted">Depreciation Expense (annual)</p>
                        <div id="modalAnnual">—</div>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1 small text-muted">Net Book Value</p>
                        <div id="modalCarrying">—</div>
                    </div>
                </div>

                <h6 class="mb-2">Depreciation schedule</h6>
                <div class="table-wrap">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Annual Depreciation</th>
                                <th>Accumulated</th>
                                <th>Net Value</th>
                            </tr>
                        </thead>
                        <tbody id="assetScheduleBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
                <a id="modalFullView" class="btn btn-primary" href="#">Open full view</a>
            </div>
        </div>
    </div>
</div>

<script>
async function openAssetModal(assetId) {
        const modalEl = document.getElementById('assetModal');
        const modalTitle = document.getElementById('assetModalTitle');
        const modalName = document.getElementById('modalAssetName');
        const modalCode = document.getElementById('modalAssetCode');
        const modalAcq = document.getElementById('modalAcquisition');
        const modalSalv = document.getElementById('modalSalvage');
        const modalLife = document.getElementById('modalLife');
        const modalAnnual = document.getElementById('modalAnnual');
        const modalCarrying = document.getElementById('modalCarrying');
        const scheduleBody = document.getElementById('assetScheduleBody');
        const modalFullView = document.getElementById('modalFullView');

        modalTitle.textContent = 'Loading...';
        modalName.textContent = '';
        modalCode.textContent = '';
        modalAcq.textContent = '—';
        modalSalv.textContent = '—';
        modalLife.textContent = '—';
        modalAnnual.textContent = '—';
        modalCarrying.textContent = '—';
        scheduleBody.innerHTML = '';

        try {
                const res = await fetch('<?= e(base_url('api/asset_detail.php')) ?>?asset_id=' + encodeURIComponent(assetId), { cache: 'no-store' });
                if (!res.ok) throw new Error('Failed to fetch asset details');
                const data = await res.json();
                 if (data.error) throw new Error(data.error);

                const asset = data.asset || {};
                const metrics = data.metrics || {};
                const schedule = data.schedule || [];

                modalTitle.textContent = asset.asset_name || 'Asset details';
                modalName.textContent = asset.asset_name || '';
                modalCode.textContent = asset.asset_code ? asset.asset_code + ' / ' + (asset.category_name || 'Uncategorized') : '';
                modalAcq.textContent = asset.acquisition_cost ? (new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(asset.acquisition_cost)) : '—';
                modalSalv.textContent = asset.salvage_value ? (new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(asset.salvage_value)) : '—';
                modalLife.textContent = asset.useful_life ? (asset.useful_life + ' years') : '—';
                modalAnnual.textContent = metrics.annual_depreciation ? (new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(metrics.annual_depreciation)) : '—';
                modalCarrying.textContent = metrics.carrying_amount ? (new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(metrics.carrying_amount)) : '—';

                scheduleBody.innerHTML = schedule.map(row => `
                        <tr>
                                <td>${row.depreciation_year}</td>
                                <td>${new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(row.depreciation_expense)}</td>
                                <td>${new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(row.accumulated_depreciation)}</td>
                                <td>${new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(row.net_value)}</td>
                        </tr>
                `).join('');

                modalFullView.href = '<?= e(base_url('modules/view_asset.php?asset_id=')) ?>' + encodeURIComponent(assetId);

        } catch (err) {
                modalTitle.textContent = 'Error';
                modalName.textContent = err.message || 'Unable to load asset';
        }

        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
}
</script>