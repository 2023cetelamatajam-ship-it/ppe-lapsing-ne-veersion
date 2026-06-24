<?php
declare(strict_types=1);
?>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <?= e(implode(' ', $errors)) ?>
    </div>
<?php endif; ?>

<form method="post" class="stack-gap">
    <section class="field-section">
        <div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="asset_code">Asset code</label>
                <input class="form-control" id="asset_code" name="asset_code" value="<?= e((string) ($form['asset_code'] ?? '')) ?>" placeholder="" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="asset_name">Asset name</label>
                <input class="form-control" id="asset_name" name="asset_name" value="<?= e((string) ($form['asset_name'] ?? '')) ?>" placeholder="" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="category_id">Category</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['category_id']) ?>" <?= selected_if($form['category_id'] ?? '', $category['category_id']) ?>>
                            <?= e($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="department_id">Department</label>
                <select class="form-select" id="department_id" name="department_id">
                    <option value="">Select a department</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= e((string) $department['department_id']) ?>" <?= selected_if($form['department_id'] ?? '', $department['department_id']) ?>>
                            <?= e($department['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="location">Location</label>
                <input class="form-control" id="location" name="location" value="<?= e((string) ($form['location'] ?? '')) ?>" placeholder="">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="Active" <?= selected_if($form['status'] ?? 'Active', 'Active') ?>>Active</option>
                    <option value="Disposed" <?= selected_if($form['status'] ?? '', 'Disposed') ?>>Disposed</option>
                    <option value="Fully Depreciated" <?= selected_if($form['status'] ?? '', 'Fully Depreciated') ?>>Fully Depreciated</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="remarks">Remarks</label>
                <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder=""><?= e((string) ($form['remarks'] ?? '')) ?></textarea>
            </div>
        </div>
    </section>

    <section class="field-section">
        <div>
            <h3 class="field-section-title">Depreciation setup</h3>
            <p class="field-section-copy">These values drive the yearly schedule, net amount, and report totals.</p>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="acquisition_date">Acquisition date</label>
                <input class="form-control" id="acquisition_date" name="acquisition_date" type="date" value="<?= e((string) ($form['acquisition_date'] ?? '')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="acquisition_cost">Acquisition cost</label>
                <input class="form-control" id="acquisition_cost" name="acquisition_cost" type="number" step="0.01" min="0.01" inputmode="decimal" value="<?= e((string) ($form['acquisition_cost'] ?? '')) ?>" placeholder="0.00" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="additional_amount">Additional</label>
                <input class="form-control" id="additional_amount" name="additional_amount" type="number" step="0.01" min="0" inputmode="decimal" value="<?= e((string) ($form['additional_amount'] ?? '0')) ?>" placeholder="0.00">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="salvage_value">Salvage value</label>
                <input class="form-control" id="salvage_value" name="salvage_value" type="number" step="0.01" min="0" inputmode="decimal" value="<?= e((string) ($form['salvage_value'] ?? '0')) ?>" placeholder="0.00">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="useful_life">Useful life (years)</label>
                <input class="form-control" id="useful_life" name="useful_life" type="number" min="1" step="1" value="<?= e((string) ($form['useful_life'] ?? '')) ?>" placeholder="e.g. 5" required>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-save me-2"></i><?= e($submitLabel ?? 'Save Asset') ?>
        </button>
        <a class="btn btn-outline-light" href="<?= e(base_url($cancelUrl ?? 'modules/assets.php')) ?>">
            Cancel
        </a>
    </div>
</form>
