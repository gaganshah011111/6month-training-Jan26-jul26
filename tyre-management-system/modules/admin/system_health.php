<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_control_center.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
$health = admin_system_health_full($pdo);
?>
<div class="admin-cc module-shell">
    <?php admin_page_head('System Health', 'Live status of ERP modules, database, and backups'); ?>

    <div class="admin-health-grid">
        <?php foreach ($health as $mod): ?>
            <div class="admin-health-tile admin-health-tile--<?= e($mod['level']) ?>">
                <div class="admin-health-tile__indicator"></div>
                <h3><?= e($mod['label']) ?></h3>
                <p class="admin-health-tile__status"><?= e($mod['status']) ?></p>
                <p class="admin-health-tile__detail"><?= e($mod['health']) ?></p>
                <div class="admin-health-tile__meta"><?= (int)$mod['records'] ?> records</div>
                <?php if (($mod['route'] ?? '') !== 'admin/system-health'): ?>
                    <a href="<?= e(route_url($mod['route'])) ?>" class="admin-link-btn">Open module</a>
                <?php elseif ($mod['key'] === 'backup'): ?>
                    <a href="<?= e(route_url('admin/backup')) ?>" class="admin-link-btn">Manage backups</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
