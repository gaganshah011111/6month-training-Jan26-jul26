<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_audit_service.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
$q = trim((string)($_GET['q'] ?? ''));
$module = trim((string)($_GET['module'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$stored = admin_audit_list($pdo, ['q' => $q, 'module' => $module, 'date_from' => $dateFrom], 200);
?>
<div class="sa-console">
    <?php admin_page_head('Activity Logs', 'Professional audit trail — who did what, when, and from where'); ?>

    <form method="get" class="sa-um-filters">
        <input type="hidden" name="page" value="admin/activity-logs">
        <div class="sa-um-filters__field"><label>User / Action</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Search"></div>
        <div class="sa-um-filters__field"><label>Module</label><input type="text" name="module" class="form-control form-control-sm" value="<?= e($module) ?>" placeholder="Module"></div>
        <div class="sa-um-filters__field"><label>Date From</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>"></div>
        <div class="sa-um-filters__actions"><button class="btn btn-sm btn-primary">Filter</button><a href="<?= e(route_url('admin/activity-logs')) ?>" class="btn btn-sm btn-outline-secondary">Reset</a></div>
    </form>

    <section class="sa-panel sa-table-wrap">
        <table class="sa-table mb-0">
            <thead><tr><th>Date</th><th>Time</th><th>User</th><th>Module</th><th>Action</th><th>IP Address</th></tr></thead>
            <tbody>
            <?php if ($stored === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No activity logs found.</td></tr>
            <?php endif; ?>
            <?php foreach ($stored as $r): ?>
                <tr>
                    <td><?= e($r['date']) ?></td>
                    <td><?= e($r['time']) ?></td>
                    <td><?= e($r['user']) ?></td>
                    <td><span class="sa-badge sa-badge--muted"><?= e($r['module']) ?></span></td>
                    <td><?= e($r['action']) ?></td>
                    <td class="font-monospace small"><?= e($r['ip'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
