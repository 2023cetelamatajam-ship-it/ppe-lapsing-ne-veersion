<?php
declare(strict_types=1);

$navItems = [
    ['label' => 'Dashboard', 'href' => 'modules/dashboard.php', 'icon' => 'bi-speedometer2', 'match' => '/modules/dashboard.php'],
    ['label' => 'Assets', 'href' => 'modules/assets.php', 'icon' => 'bi-pc-display', 'match' => '/modules/assets.php'],
    ['label' => 'Depreciation', 'href' => 'modules/depreciation.php', 'icon' => 'bi-graph-up-arrow', 'match' => '/modules/depreciation.php'],
    ['label' => 'Reports', 'href' => 'modules/reports.php', 'icon' => 'bi-bar-chart-line', 'match' => '/modules/reports.php'],
];

if (can_manage_assets()) {
    $navItems[] = ['label' => 'Transfers', 'href' => 'modules/transfers.php', 'icon' => 'bi-arrow-left-right', 'match' => '/modules/transfers.php'];
}

$navItems[] = ['label' => 'Exports', 'href' => 'modules/exports.php', 'icon' => 'bi-download', 'match' => ['/modules/exports.php', '/modules/export.php', '/modules/print_view.php']];
?>
<aside class="sidebar">
    

    <div class="sidebar-section">
        <p class="nav-caption mb-0">Workspace</p>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
            <?php $isActive = active_path($item['match']); ?>
            <a class="sidebar-link <?= $isActive ? 'active' : '' ?>" href="<?= e(base_url($item['href'])) ?>">
                <i class="bi <?= e($item['icon']) ?>"></i>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (can_manage_assets()): ?>
        <div class="sidebar-action">
            <p class="eyebrow mb-2">Quick action</p>
            <a class="btn btn-primary w-100" href="<?= e(base_url('modules/add_asset.php')) ?>">
                <i class="bi bi-plus-circle me-2"></i>Add Asset
            </a>
        </div>
    <?php endif; ?>

    <div class="sidebar-footer">
        <div class="sidebar-note">
            <span>Today</span>
            <strong><?= e(date('M d, Y')) ?></strong>
        </div>
    </div>
</aside>
