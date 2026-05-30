<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_monitoring_service.php';
require_once __DIR__ . '/../../includes/admin_dashboard_service.php';
require_once __DIR__ . '/../../includes/admin_backup_service.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
$modules = admin_erp_module_monitor($pdo);
$dash = admin_system_dashboard($pdo);
$h = $dash['system_health'];
$backups = admin_backup_list();
?>
<div class="sa-console">
    <?php admin_page_head('System Monitoring', 'ERP infrastructure health — database, storage, modules, and uptime'); ?>

    <div class="sa-dash-stats sa-dash-stats--4 mb-3">
        <div class="sa-dash-stat sa-dash-stat--<?= e($h['database']['level']) ?>"><span>Database</span><strong><?= e($h['database']['state']) ?></strong></div>
        <div class="sa-dash-stat"><span>Storage</span><strong><?= e($h['storage']['state']) ?></strong></div>
        <div class="sa-dash-stat sa-dash-stat--<?= e($h['server']['level']) ?>"><span>Server</span><strong><?= e($h['server']['state']) ?></strong></div>
        <div class="sa-dash-stat sa-dash-stat--<?= e($h['backup']['level']) ?>"><span>Last Backup</span><strong><?= e($h['backup']['detail'] ?? 'Never') ?></strong></div>
    </div>

    <section class="sa-panel mb-3">
        <div class="sa-panel__head"><h2 class="sa-panel__title">Module Status</h2></div>
        <div class="sa-panel__body">
            <div class="sa-dash-modules">
                <?php foreach ($modules as $m): ?>
                    <?php $state = match ($m['level']) { 'error' => 'Offline', 'warning' => 'Warning', default => 'Healthy' }; ?>
                    <div class="sa-dash-mod sa-dash-mod--<?= e($m['level']) ?>">
                        <span><?= e($m['label']) ?></span>
                        <strong><?= e($state) ?></strong>
                        <small><?= (int)$m['records'] ?> records · Last: <?= e($m['last_activity']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php
    $errors = [];
    foreach ($modules as $m) {
        foreach ($m['issues'] as $issue) {
            $errors[] = $m['label'] . ': ' . $issue;
        }
    }
    ?>
    <section class="sa-panel">
        <div class="sa-panel__head"><h2 class="sa-panel__title">Recent Issues</h2></div>
        <div class="sa-panel__body">
            <?php if ($errors === []): ?>
                <p class="sa-dash-empty mb-0">All modules operating normally.</p>
            <?php else: ?>
                <ul class="sa-dash-actions mb-0">
                    <?php foreach ($errors as $err): ?><li><strong><?= e($err) ?></strong></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>
