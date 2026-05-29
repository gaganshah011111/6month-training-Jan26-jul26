<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/../../includes/admin_settings_service.php';

require_once __DIR__ . '/../../includes/admin_audit_service.php';

if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    admin_settings_save($pdo, $_POST);

    admin_audit_log($pdo, 'Updated system settings', 'System Settings', 'success', null, null, null, 'settings', null);

    set_flash('success', 'System settings saved.');

    redirect('admin/settings');

}

$settings = admin_settings_load($pdo);

$groups = ['company' => 'Company Profile', 'tax' => 'Tax Settings', 'finance' => 'Financial Year', 'security' => 'Security & Login Policy'];

?>

<div class="admin-cc module-shell">

    <?php admin_page_head('System Settings', 'Company profile, tax, financial year, password and login policies'); ?>

    <section class="admin-card">

        <form method="post" class="admin-settings-form">

            <?= csrf_input() ?>

            <?php foreach ($groups as $gKey => $gLabel): ?>

                <h3 class="admin-settings-group"><?= e($gLabel) ?></h3>

                <div class="admin-settings-grid">

                    <?php foreach (ADMIN_SETTING_KEYS as $key => $meta): if (($meta['group'] ?? '') !== $gKey) continue; ?>

                        <div class="admin-settings-field">

                            <label for="set_<?= e($key) ?>"><?= e($meta['label']) ?></label>

                            <?php if (($meta['type'] ?? '') === 'textarea'): ?>

                                <textarea class="form-control form-control-sm" id="set_<?= e($key) ?>" name="<?= e($key) ?>" rows="2"><?= e($settings[$key] ?? '') ?></textarea>

                            <?php else: ?>

                                <input class="form-control form-control-sm" id="set_<?= e($key) ?>" type="<?= e($meta['type']) ?>" name="<?= e($key) ?>" value="<?= e($settings[$key] ?? '') ?>">

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endforeach; ?>

            <div class="mt-3 d-flex gap-2">

                <button class="btn btn-primary btn-sm">Save Settings</button>

                <a href="<?= e(route_url('admin/backup')) ?>" class="btn btn-sm btn-outline-secondary">Backup & Restore</a>

            </div>

        </form>

    </section>

</div>


