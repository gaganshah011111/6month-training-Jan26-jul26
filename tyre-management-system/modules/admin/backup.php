<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_backup_service.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
if (!empty($_GET['download'])) {
    admin_backup_download((string)$_GET['download']);
}
$pdo = Database::connection();
admin_backup_handle_post($pdo);
$files = admin_backup_list();
$lastBackup = $files[0]['modified'] ?? 'Never';
$lastStatus = $files !== [] ? 'Available' : 'No backup yet';
?>
<div class="sa-console">
    <?php admin_page_head('Backup & Restore', 'Database backup, download, and disaster recovery — Super Admin only for create/restore'); ?>

    <div class="sa-kpi-row">
        <div class="sa-kpi"><span>Backup Status</span><strong><?= e($lastStatus) ?></strong></div>
        <div class="sa-kpi"><span>Last Backup</span><strong><?= e($lastBackup) ?></strong></div>
        <div class="sa-kpi"><span>Files on Disk</span><strong><?= count($files) ?></strong></div>
    </div>

    <section class="sa-panel mb-3">
        <div class="sa-panel__body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="sa-panel__title mb-1">Create Backup</h2>
                <p class="small text-muted mb-0">Exports the full database to SQL for disaster recovery.</p>
            </div>
            <?php if (has_role('Super Admin')): ?>
            <form method="post" class="admin-confirm-form"><?= csrf_input() ?><input type="hidden" name="action" value="create"><button class="btn btn-primary btn-sm" data-confirm="Create a new full database backup?">Create Backup</button></form>
            <?php endif; ?>
        </div>
    </section>

    <section class="sa-panel sa-table-wrap mb-3">
        <div class="sa-panel__head"><h2 class="sa-panel__title">Backup History</h2></div>
        <table class="sa-table mb-0"><thead><tr><th>File</th><th>Size</th><th>Last Modified</th><th class="text-end">Actions</th></tr></thead><tbody>
        <?php foreach ($files as $f): ?>
            <tr>
                <td><?= e($f['name']) ?></td>
                <td><?= e($f['size_fmt']) ?></td>
                <td><?= e($f['modified']) ?></td>
                <td class="text-end"><a href="<?= e(route_url('admin/backup', ['download' => $f['name']])) ?>" class="sa-btn sa-btn--primary">Download Backup</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($files === []): ?><tr><td colspan="4" class="text-muted text-center py-3">No backup files yet.</td></tr><?php endif; ?>
        </tbody></table>
    </section>

    <section class="sa-panel">
        <div class="sa-panel__head"><h2 class="sa-panel__title">Restore Backup</h2></div>
        <div class="sa-panel__body">
            <p class="small mb-2">Restore replaces all current data. Use only during disaster recovery.</p>
            <p class="small text-muted mb-0"><strong>CLI:</strong> <code>mysql -u root tyre_erp &lt; database/sql/FULL_DATABASE_BACKUP.sql</code></p>
            <p class="small text-muted mb-0"><strong>phpMyAdmin:</strong> Import the downloaded SQL file after creating a backup of the current state.</p>
        </div>
    </section>
</div>
