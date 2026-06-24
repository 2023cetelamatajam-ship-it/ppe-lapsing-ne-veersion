<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

if (!can_manage_assets()) {
    set_flash('danger', 'Only Admin and Accounting Staff can record asset transfers.');
    redirect('modules/assets.php');
}

$pdo = db();
$assetLookup = fetch_asset_lookup($pdo);
$departments = fetch_departments($pdo);
$selectedAssetId = (int) request_value('asset_id', $assetLookup[0]['asset_id'] ?? 0);
$selectedAsset = $selectedAssetId > 0 ? fetch_asset_by_id($pdo, $selectedAssetId) : null;

if ($selectedAssetId > 0 && !$selectedAsset && $assetLookup !== []) {
    $selectedAssetId = (int) $assetLookup[0]['asset_id'];
    $selectedAsset = fetch_asset_by_id($pdo, $selectedAssetId);
}

$errors = [];
$form = normalize_transfer_payload([
    'asset_id' => $selectedAssetId,
    'transfer_date' => date('Y-m-d'),
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = normalize_transfer_payload($_POST);
    $selectedAssetId = (int) $form['asset_id'];
    $selectedAsset = $selectedAssetId > 0 ? fetch_asset_by_id($pdo, $selectedAssetId) : null;

    if (!$selectedAsset) {
        $errors[] = 'The selected asset could not be found.';
    } else {
        $errors = validate_transfer_payload($form, $selectedAsset, $departments);

        if ($errors === []) {
            save_asset_transfer($pdo, $selectedAssetId, $form, (int) (current_user()['user_id'] ?? 0) ?: null);
            set_flash('success', 'Asset transfer recorded successfully.');
            redirect('modules/transfers.php?asset_id=' . $selectedAssetId);
        }
    }
}

$recentTransfers = fetch_asset_transfers($pdo, null, 15);

$pageTitle = 'Transfers';
$pageHeading = 'Asset Transfers';

require_once APP_ROOT . '/includes/header.php';
?>
<?php if ($assetLookup === []): ?>
    <section class="shell-card">
        <div class="empty-state">
            <h2 class="section-title mb-2">No assets are available for transfer</h2>
            <a class="btn btn-outline-light" href="<?= e(base_url('modules/assets.php')) ?>">Open asset register</a>
        </div>
    </section>
<?php else: ?>
    <div class="page-actions mb-4">
        <a class="btn btn-outline-light" href="<?= e(base_url('modules/export.php?type=transfers')) ?>">Export Transfer CSV</a>
        <a class="btn btn-outline-light" href="<?= e(base_url('modules/print_view.php?type=transfers')) ?>" target="_blank" rel="noopener">Print Transfer Log</a>
    </div>

    <section class="shell-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <p class="eyebrow mb-2">Transfer setup</p>
                <h2 class="section-title mb-1">Choose an asset</h2>
            </div>
            <?php if ($selectedAsset): ?>
                <a class="btn btn-outline-light" href="<?= e(base_url('modules/view_asset.php?asset_id=' . $selectedAsset['asset_id'])) ?>">View Asset Detail</a>
            <?php endif; ?>
        </div>
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
                    <i class="bi bi-arrow-repeat me-2"></i>Load Asset
                </button>
            </div>
        </form>
    </section>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <?= e(implode(' ', $errors)) ?>
        </div>
    <?php endif; ?>

    <?php if ($selectedAsset): ?>
        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <section class="shell-card h-100">
                    <div class="mb-4">
                        <p class="eyebrow mb-2">Transfer form</p>
                        <h2 class="section-title mb-1">Record a new movement</h2>
                        <p class="section-copy mb-0">Leave a field unchanged if only one part of the assignment is moving.</p>
                    </div>

                    <form method="post" class="stack-gap">
                        <input type="hidden" name="asset_id" value="<?= e((string) $selectedAsset['asset_id']) ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="transfer_date">Transfer date</label>
                                <input class="form-control" id="transfer_date" name="transfer_date" type="date" value="<?= e((string) $form['transfer_date']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="to_department_id">New department</label>
                                <select class="form-select" id="to_department_id" name="to_department_id">
                                    <option value="">Current Department</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?= e((string) $department['department_id']) ?>" <?= selected_if($form['to_department_id'] ?? '', $department['department_id']) ?>>
                                            <?= e($department['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="to_location">New location</label>
                                <input
                                    class="form-control"
                                    id="to_location"
                                    name="to_location"
                                    value="<?= e((string) ($form['to_location'] ?? '')) ?>"
                                    placeholder=""
                                >
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="notes">Transfer notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder=""><?= e((string) ($form['notes'] ?? '')) ?></textarea>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-arrow-left-right me-2"></i>Save Transfer
                            </button>
                            <a class="btn btn-outline-light" href="<?= e(base_url('modules/view_asset.php?asset_id=' . $selectedAsset['asset_id'])) ?>">Cancel</a>
                        </div>
                    </form>
                </section>
            </div>

            <div class="col-lg-5">
                <section class="shell-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <p class="eyebrow mb-2">Current assignment</p>
                            <h2 class="section-title mb-1"><?= e($selectedAsset['asset_name']) ?></h2>
                            <p class="section-copy mb-0"><?= e($selectedAsset['asset_code']) ?></p>
                        </div>
                        <span class="badge <?= e(status_badge_class((string) $selectedAsset['status'])) ?>"><?= e($selectedAsset['status']) ?></span>
                    </div>

                    <div class="list-panel">
                        <div class="list-row">
                            <strong>Department</strong>
                            <p class="text-soft small mb-0"><?= e(asset_department_label($selectedAsset)) ?></p>
                        </div>
                        <div class="list-row">
                            <strong>Location</strong>
                            <p class="text-soft small mb-0"><?= e(asset_location_label((string) ($selectedAsset['location'] ?? ''))) ?></p>
                        </div>
                        <div class="list-row">
                            <strong>Category</strong>
                            <p class="text-soft small mb-0"><?= e((string) ($selectedAsset['category_name'] ?? 'Uncategorized')) ?></p>
                        </div>
                        <div class="list-row">
                            <strong>Acquisition date</strong>
                            <p class="text-soft small mb-0"><?= e(format_date((string) $selectedAsset['acquisition_date'])) ?></p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    <?php endif; ?>

    <section class="shell-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <p class="eyebrow mb-2">History</p>
                <h2 class="section-title mb-1">Recent transfers</h2>
                <p class="section-copy mb-0">Each row shows the last recorded movement and who captured it.</p>
            </div>
            <span class="badge text-bg-dark"><?= e((string) count($recentTransfers)) ?> recent entries</span>
        </div>

        <?php if ($recentTransfers === []): ?>
            <div class="empty-state">No transfers have been recorded yet.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Asset</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Recorded by</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransfers as $transfer): ?>
                            <?php
                            $fromDepartment = trim((string) ($transfer['from_department_name'] ?? '')) ?: 'Unassigned';
                            $toDepartment = trim((string) ($transfer['to_department_name'] ?? '')) ?: 'Unassigned';
                            ?>
                            <tr>
                                <td><?= e(format_date((string) $transfer['transfer_date'])) ?></td>
                                <td>
                                    <strong><?= e($transfer['asset_name']) ?></strong>
                                    <div class="text-soft small"><?= e($transfer['asset_code']) ?></div>
                                </td>
                                <td>
                                    <div><?= e($fromDepartment) ?></div>
                                    <div class="text-soft small"><?= e(asset_location_label((string) ($transfer['from_location'] ?? ''))) ?></div>
                                </td>
                                <td>
                                    <div><?= e($toDepartment) ?></div>
                                    <div class="text-soft small"><?= e(asset_location_label((string) ($transfer['to_location'] ?? ''))) ?></div>
                                </td>
                                <td><?= e((string) ($transfer['transferred_by_name'] ?? 'System')) ?></td>
                                <td><?= e((string) ($transfer['notes'] ?: 'No notes')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
