<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_settings_service.php';
require_once __DIR__ . '/../../includes/admin_audit_service.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_company_settings_save($pdo, $_POST);
    admin_audit_log($pdo, 'Updated company settings', 'Company Settings', 'success');
    set_flash('success', 'Company settings saved.');
    redirect('admin/company-settings');
}
$settings = admin_settings_load($pdo, ADMIN_COMPANY_KEYS);
?>
<div class="sa-console">
    <?php admin_page_head('Company Settings', 'Company identity, GST, financial year, and ERP branding'); ?>

    <section class="sa-panel">
        <form method="post" class="sa-panel__body">
            <?= csrf_input() ?>
            <div class="sa-form-section">Company Profile</div>
            <div class="sa-form-grid">
                <?php
                $profileKeys = ['company_name', 'company_address', 'company_phone', 'company_email', 'gst_number', 'company_logo'];
                foreach ($profileKeys as $key):
                    if (!isset(ADMIN_COMPANY_KEYS[$key])) continue;
                    $meta = ADMIN_COMPANY_KEYS[$key];
                ?>
                    <div class="<?= ($meta['type'] ?? '') === 'textarea' ? 'col-12' : '' ?>">
                        <label class="form-label small fw-semibold"><?= e($meta['label']) ?></label>
                        <?php if (($meta['type'] ?? '') === 'textarea'): ?>
                            <textarea class="form-control form-control-sm" name="<?= e($key) ?>" rows="2"><?= e($settings[$key] ?? '') ?></textarea>
                        <?php else: ?>
                            <input class="form-control form-control-sm" type="<?= e($meta['type']) ?>" name="<?= e($key) ?>" value="<?= e($settings[$key] ?? '') ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="sa-form-section">Financial & Branding</div>
            <div class="sa-form-grid">
                <?php foreach (['financial_year_start', 'currency', 'default_tax_rate', 'timezone'] as $key): ?>
                    <?php $meta = ADMIN_COMPANY_KEYS[$key]; ?>
                    <div>
                        <label class="form-label small fw-semibold"><?= e($meta['label']) ?></label>
                        <input class="form-control form-control-sm" name="<?= e($key) ?>" value="<?= e($settings[$key] ?? '') ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary btn-sm mt-3">Save Company Settings</button>
        </form>
    </section>
</div>
