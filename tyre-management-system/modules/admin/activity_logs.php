<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/../../includes/admin_audit_service.php';

require_once __DIR__ . '/../../includes/admin_control_center.php';

require_once __DIR__ . '/../../includes/admin_security_service.php';

if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }

$pdo = Database::connection();

$q = trim((string)($_GET['q'] ?? ''));

$module = trim((string)($_GET['module'] ?? ''));

$stored = admin_audit_list($pdo, ['q' => $q, 'module' => $module], 150);

$failedLogins = admin_failed_logins($pdo, 15);

?>

<div class="admin-cc module-shell">

    <?php admin_page_head('Audit Center', 'Who changed what, when, from where — full change tracking'); ?>

    <form method="get" class="admin-cc__filters">

        <input type="hidden" name="page" value="admin/activity-logs">

        <div class="admin-field"><label>Search</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="User, action, detail"></div>

        <div class="admin-field"><label>Module</label><input type="text" name="module" class="form-control form-control-sm" value="<?= e($module) ?>" placeholder="e.g. User Management"></div>

        <button class="btn btn-sm btn-primary">Filter</button>

        <a href="<?= e(route_url('admin/activity-logs')) ?>" class="btn btn-sm btn-outline-secondary">Reset</a>

    </form>



    <section class="admin-card admin-table-wrap">

        <h2 class="admin-card__title">Activity Log</h2>

        <table class="table table-sm admin-table admin-audit-table mb-0">

            <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Old → New</th><th>IP</th><th>Status</th></tr></thead>

            <tbody>

            <?php if ($stored === []): ?><tr><td colspan="7" class="text-muted py-3">No audit entries yet. Actions across admin modules will appear here.</td></tr><?php endif; ?>

            <?php foreach ($stored as $r): ?>

                <?php $badge = admin_audit_status_badge((string)$r['status']); ?>

                <tr>

                    <td><small><?= e($r['date']) ?> <?= e($r['time']) ?></small></td>

                    <td><?= e($r['user']) ?><br><small class="text-muted"><?= e($r['department']) ?></small></td>

                    <td><span class="admin-badge admin-badge--muted"><?= e($r['module']) ?></span></td>

                    <td><?= e($r['action']) ?><?php if ($r['detail'] !== ''): ?><br><small><?= e($r['detail']) ?></small><?php endif; ?></td>

                    <td><?php if ($r['old_value'] !== '' || $r['new_value'] !== ''): ?><small><?= e($r['old_value'] ?: '—') ?> → <?= e($r['new_value'] ?: '—') ?></small><?php else: ?>—<?php endif; ?></td>

                    <td><small><?= e($r['ip']) ?></small></td>

                    <td><span class="admin-badge <?= e($badge['cls']) ?>"><?= e($badge['label']) ?></span></td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </section>



    <section class="admin-card admin-table-wrap mt-3">

        <h2 class="admin-card__title">Recent Failed Login Attempts</h2>

        <table class="table table-sm admin-table mb-0">

            <thead><tr><th>When</th><th>Login</th><th>IP</th><th>Reason</th></tr></thead>

            <tbody>

            <?php foreach ($failedLogins as $f): ?>

                <tr>

                    <td><?= e(substr((string)$f['created_at'], 0, 16)) ?></td>

                    <td><?= e((string)$f['login_name']) ?></td>

                    <td><?= e((string)($f['ip_address'] ?? '—')) ?></td>

                    <td><?= e((string)($f['failure_reason'] ?? '—')) ?></td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </section>

</div>


