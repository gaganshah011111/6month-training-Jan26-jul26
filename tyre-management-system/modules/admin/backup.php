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
?>
<div class="admin-cc module-shell">
    <?php admin_page_head('Backup & Restore', 'Database backup management and disaster recovery'); ?>

    <section class="admin-card">
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div>
                <h2 class="admin-card__title mb-1">Create Backup</h2>
                <p class="admin-card__hint mb-0">Exports full database to SQL file for disaster recovery.</p>
            </div>
            <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="create"><button class="btn btn-primary btn-sm"><i class="bi bi-cloud-download"></i> Create Backup</button></form>
        </div>
    </section>

    <section class="admin-card admin-table-wrap">
        <h2 class="admin-card__title">Backup History</h2>
        <table class="table table-sm admin-table mb-0">
            <thead><tr><th>File</th><th>Size</th><th>Last Modified</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($files as $f): ?>
                <tr>
                    <td><?= e($f['name']) ?></td>
                    <td><?= e($f['size_fmt']) ?></td>
                    <td><?= e($f['modified']) ?></td>
                    <td class="text-end"><a href="<?= e(route_url('admin/backup', ['download' => $f['name']])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Download</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($files === []): ?><tr><td colspan="4" class="text-muted text-center py-3">No backup files found. Create one above.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="admin-card">
        <h2 class="admin-card__title">Restore Instructions</h2>
        <p class="admin-card__hint mb-0">Restore via CLI: <code>mysql -u root tyre_erp &lt; database/sql/FULL_DATABASE_BACKUP.sql</code> or use phpMyAdmin import. Web restore is disabled for safety.</p>
    </section>
</div>
