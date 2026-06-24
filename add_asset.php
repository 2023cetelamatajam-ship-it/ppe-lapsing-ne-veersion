<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

if (!can_manage_assets()) {
    set_flash('danger', 'Only Admin and Accounting Staff can add PPE records.');
    redirect('modules/assets.php');
}

$pdo = db();
$categories = fetch_categories($pdo);
$departments = fetch_departments($pdo);
$errors = [];
$form = normalize_asset_payload([
    'status' => 'Active',
    'depreciation_method' => 'Straight-line',
    'additional_amount' => 0,
    'salvage_value' => 0,
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = normalize_asset_payload($_POST);
    $errors = validate_asset_payload($form);

    if ($errors === []) {
        try {
            $assetId = save_asset($pdo, $form);
            set_flash('success', 'Asset added and depreciation schedule generated.');
            redirect('modules/view_asset.php?asset_id=' . $assetId);
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$pageTitle = 'Add Asset';
$pageHeading = 'Add PPE Asset';
$submitLabel = 'Save Asset';
$cancelUrl = 'modules/assets.php';

require_once APP_ROOT . '/includes/header.php';
?>
<section class="shell-card">
    <div class="mb-4">
        <p class="eyebrow mb-2">Asset setup</p>
        <h2 class="section-title mb-2">Record a new PPE item</h2>
    </div>
    <?php require APP_ROOT . '/includes/asset_form.php'; ?>
</section>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
