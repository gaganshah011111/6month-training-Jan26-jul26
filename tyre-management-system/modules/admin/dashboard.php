<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_dashboard_service.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
$d = admin_system_dashboard($pdo);
$o = $d['overview'];
$h = $d['system_health'];
?>
<div class="sa-console sa-dash">
    <?php admin_page_head('Dashboard', 'System health overview — users, modules, security, and infrastructure'); ?>

    <div class="sa-dash-row">
        <section class="sa-dash-block">
            <h2 class="sa-dash-block__title">System Overview</h2>
            <div class="sa-dash-stats sa-dash-stats--6">
                <a class="sa-dash-stat" href="<?= e(route_url('admin/users')) ?>"><span>Total Users</span><strong><?= (int)$o['total'] ?></strong></a>
                <a class="sa-dash-stat sa-dash-stat--green" href="<?= e(route_url('admin/users', ['status' => 'active'])) ?>"><span>Active Users</span><strong><?= (int)$o['active'] ?></strong></a>
                <a class="sa-dash-stat sa-dash-stat--red" href="<?= e(route_url('admin/users', ['status' => 'locked'])) ?>"><span>Locked Users</span><strong><?= (int)$o['locked'] ?></strong></a>
                <a class="sa-dash-stat" href="<?= e(route_url('admin/users', ['status' => 'inactive'])) ?>"><span>Inactive Users</span><strong><?= (int)$o['inactive'] ?></strong></a>
                <a class="sa-dash-stat" href="<?= e(route_url('admin/departments')) ?>"><span>Departments</span><strong><?= (int)$o['departments'] ?></strong></a>
                <a class="sa-dash-stat sa-dash-stat--blue" href="<?= e(route_url('admin/users')) ?>"><span>Online Users</span><strong><?= (int)$o['online'] ?></strong></a>
            </div>
        </section>
    </div>

    <div class="sa-dash-row sa-dash-row--2">
        <section class="sa-dash-block">
            <h2 class="sa-dash-block__title">System Health</h2>
            <div class="sa-dash-health">
                <?php foreach ($h as $item): ?>
                    <div class="sa-dash-health__item sa-dash-health__item--<?= e($item['level']) ?>">
                        <span class="sa-dash-health__label"><?= e($item['label']) ?></span>
                        <strong><?= e($item['state']) ?></strong>
                        <?php if (!empty($item['detail'])): ?><small><?= e($item['detail']) ?></small><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <h3 class="sa-dash-subtitle">Module Status</h3>
            <div class="sa-dash-modules">
                <?php foreach ($d['modules'] as $m): ?>
                    <div class="sa-dash-mod sa-dash-mod--<?= e($m['level']) ?>">
                        <span><?= e($m['label']) ?> Module</span>
                        <strong><?= e($m['state']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="sa-dash-block">
            <h2 class="sa-dash-block__title">Security Alerts</h2>
            <?php if ($d['security_alerts'] === []): ?>
                <p class="sa-dash-empty">No security alerts.</p>
            <?php else: ?>
                <ul class="sa-dash-alerts">
                    <?php foreach ($d['security_alerts'] as $a): ?>
                        <li class="sa-dash-alert sa-dash-alert--<?= e($a['level']) ?>">
                            <a href="<?= e($a['url']) ?>">
                                <strong><?= e($a['title']) ?></strong>
                                <span><?= e($a['detail']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h3 class="sa-dash-subtitle">Quick Actions</h3>
            <div class="sa-dash-quick">
                <a class="sa-dash-quick__btn" href="<?= e(route_url('admin/users')) ?>">Create User</a>
                <a class="sa-dash-quick__btn" href="<?= e(route_url('admin/backup')) ?>">Backup Now</a>
                <a class="sa-dash-quick__btn" href="<?= e(route_url('admin/users')) ?>">Reset User Password</a>
                <a class="sa-dash-quick__btn" href="<?= e(route_url('admin/activity-logs')) ?>">Open Activity Logs</a>
            </div>
        </section>
    </div>

    <section class="sa-dash-block sa-dash-block--compact">
        <div class="sa-dash-block__head">
            <h2 class="sa-dash-block__title mb-0">Recent Admin Actions</h2>
            <a href="<?= e(route_url('admin/activity-logs')) ?>" class="sa-panel__link">View all →</a>
        </div>
        <?php if ($d['admin_actions'] === []): ?>
            <p class="sa-dash-empty mb-0">No admin actions recorded yet.</p>
        <?php else: ?>
            <ul class="sa-dash-actions">
                <?php foreach ($d['admin_actions'] as $a): ?>
                    <li>
                        <strong><?= e($a['action']) ?></strong>
                        <span><?= e($a['user']) ?> · <?= e($a['module']) ?> · <?= e($a['when']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
