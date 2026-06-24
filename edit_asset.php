<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

if (!can_manage_assets()) {
    set_flash('danger', 'Only Admin and Accounting Staff can update PPE records.');
    redirect('modules/assets.php');
}

$pdo = db();
$assetId = (int) request_value('asset_id', 0);
$asset = fetch_asset_by_id($pdo, $assetId);

if (!$asset) {
    set_flash('danger', 'The requested asset could not be found.');
    redirect('modules/assets.php');
}

$categories = fetch_categories($pdo);
$departments = fetch_departments($pdo);
$errors = [];
$form = normalize_asset_payload($asset);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = normalize_asset_payload($_POST);
    $errors = validate_asset_payload($form);

    if ($errors === []) {
        try {
            save_asset($pdo, $form, $assetId);
            set_flash('success', 'Asset updated and schedule refreshed.');
            redirect('modules/view_asset.php?asset_id=' . $assetId);
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$pageTitle = 'Edit Asset';
$pageHeading = 'Edit PPE Asset';
$pageDescription = 'Adjust the asset profile, then the system will rebuild the depreciation schedule using the latest values.';
$submitLabel = 'Update Asset';
$cancelUrl = 'modules/view_asset.php?asset_id=' . $assetId;

require_once APP_ROOT . '/includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-8">
        <section class="shell-card">
            <div class="mb-4">
                <p class="eyebrow mb-2">Asset maintenance</p>
                <h2 class="section-title mb-2"><?= e($asset['asset_name']) ?></h2>
                <p class="section-subtitle">Keep the master record aligned with actual use, disposal status, and accounting assumptions.</p>
            </div>
            <?php require APP_ROOT . '/includes/asset_form.php'; ?>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="shell-card">
            <p class="eyebrow mb-2">Current snapshot</p>
            <h2 class="section-title mb-3">Asset context</h2>
            <?php $metrics = get_asset_metrics($asset); ?>
            <div class="list-panel">
                <div class="list-row">
                    <strong>Current net amount</strong>
                    <p class="text-soft small mb-0"><?= e(money($metrics['carrying_amount'])) ?></p>
                </div>
                <div class="list-row">
                    <strong>Annual depreciation</strong>
                    <p class="text-soft small mb-0"><?= e(money($metrics['annual_depreciation'])) ?></p>
                </div>
                <div class="list-row">
                    <strong>Remaining useful life</strong>
                    <p class="text-soft small mb-0"><?= e((string) $metrics['remaining_years']) ?> year(s)</p>
                </div>
            </div>
        </section>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
