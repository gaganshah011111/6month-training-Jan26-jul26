<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/../../includes/admin_roles_service.php';

if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }

$pdo = Database::connection();

admin_roles_handle_post($pdo);

$roleKey = trim((string)($_GET['role'] ?? ''));

$detail = $roleKey !== '' ? admin_role_detail($pdo, $roleKey) : null;

$cards = admin_roles_cards($pdo);

$baseRoles = ADMIN_BASE_ROLES;

?>

<div class="admin-cc module-shell">

    <?php if ($detail): ?>

        <?php admin_page_head('Role: ' . $detail['role'], 'Permission matrix · ' . (int)$detail['users'] . ' users' . ($detail['custom'] ? ' · custom (base: ' . $detail['base_role'] . ')' : ''), '<a href="' . e(route_url('admin/roles')) . '" class="btn btn-sm btn-outline-secondary">Back</a>'); ?>

        <section class="admin-card admin-table-wrap">

            <table class="table table-sm admin-table mb-0">

                <thead><tr><th>Module</th><?php foreach (ACC_ADMIN_PERMISSIONS as $p): ?><th><?= e(ucfirst($p)) ?></th><?php endforeach; ?></tr></thead>

                <tbody>

                <?php foreach ($detail['modules'] as $mod => $flags): ?>

                    <tr><td><strong><?= e($mod) ?></strong></td><?php foreach (ACC_ADMIN_PERMISSIONS as $p): ?><td><?= !empty($flags[$p]) ? '<span class="admin-badge admin-badge--ok">Yes</span>' : '<span class="admin-badge admin-badge--muted">No</span>' ?></td><?php endforeach; ?></tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </section>

        <div class="d-flex gap-2 flex-wrap">

            <a href="<?= e(route_url('admin/users', ['role' => $detail['role']])) ?>" class="btn btn-sm btn-primary">View users with this role</a>

            <?php if ($detail['custom']): ?>

            <form method="post" class="admin-confirm-form"><?= csrf_input() ?><input type="hidden" name="action" value="deactivate_role"><input type="hidden" name="role_name" value="<?= e($detail['role']) ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Remove this custom role?">Remove Role Access</button></form>

            <?php endif; ?>

        </div>

    <?php else: ?>

        <?php admin_page_head('Role Management', 'View, assign, clone, and create custom roles', '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createRoleModal">Create Custom Role</button>'); ?>

        <div class="admin-role-cards">

            <?php foreach ($cards as $c): ?>

                <a class="admin-role-card" href="<?= e($c['url']) ?>">

                    <h3><?= e($c['role']) ?><?= !empty($c['custom']) ? ' <small class="admin-badge admin-badge--muted">Custom</small>' : '' ?></h3>

                    <div class="admin-role-card__stats">

                        <div><span>Users Assigned</span><strong><?= (int)$c['users'] ?></strong></div>

                        <div><span>Modules Accessible</span><strong><?= (int)$c['modules'] ?></strong></div>

                        <div><span>Permission Count</span><strong><?= (int)$c['permissions'] ?></strong></div>

                    </div>

                    <span class="admin-role-card__cta">View permissions →</span>

                </a>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>



<div class="modal fade" id="createRoleModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content">

    <?= csrf_input() ?><input type="hidden" name="action" value="create_role">

    <div class="modal-header"><h5 class="modal-title">Create Custom Role</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>

    <div class="modal-body row g-2">

        <div class="col-12"><label>Role Name</label><input class="form-control" name="role_name" required placeholder="e.g. Senior HR Executive"></div>

        <div class="col-12"><label>Base Role (inherits access)</label><select class="form-select" name="base_role" required><?php foreach ($baseRoles as $r): if ($r==='Super Admin') continue; ?><option><?= e($r) ?></option><?php endforeach; ?></select></div>

    </div>

    <div class="modal-footer"><button class="btn btn-primary btn-sm">Create Role</button></div>

</form></div></div>



<div class="modal fade" id="cloneRoleModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content">

    <?= csrf_input() ?><input type="hidden" name="action" value="clone_role">

    <div class="modal-header"><h5 class="modal-title">Clone Role Permissions</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>

    <div class="modal-body row g-2">

        <div class="col-12"><label>Source Role</label><select class="form-select" name="source_role" required><?php foreach ($cards as $c): ?><option><?= e($c['role']) ?></option><?php endforeach; ?></select></div>

        <div class="col-12"><label>New Role Name</label><input class="form-control" name="new_role_name" required></div>

    </div>

    <div class="modal-footer"><button class="btn btn-primary btn-sm">Clone Role</button></div>

</form></div></div>



<?php if (!$detail): ?>

<p class="mt-2"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#cloneRoleModal">Clone Existing Role</button></p>

<?php endif; ?>


