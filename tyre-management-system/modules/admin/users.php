<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_users_service.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
admin_users_handle_post($pdo);
$filters = admin_users_parse_filters($_GET);
$users = admin_users_list($pdo, $filters);
$departments = admin_user_departments($pdo);
$assignableRoles = admin_assignable_roles($pdo);
$createBtn = '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">Create User</button>';
?>
<div class="sa-console sa-users-page" id="saUsersPage">
    <?php admin_page_head('Users', 'Manage ERP user accounts, roles, and access control', $createBtn); ?>

    <?php if ($filters['department'] !== ''): ?>
        <div class="alert alert-info py-2 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span>Showing logins for: <strong><?= e($filters['department']) ?></strong></span>
            <a href="<?= e(route_url('admin/users')) ?>" class="btn btn-sm btn-outline-secondary">Clear filter</a>
        </div>
    <?php endif; ?>

    <form method="get" class="sa-um-filters">
        <input type="hidden" name="page" value="admin/users">
        <div class="sa-um-filters__field sa-um-filters__field--grow">
            <label for="um_search">Search User</label>
            <input type="search" id="um_search" name="q" class="form-control form-control-sm" value="<?= e($filters['q']) ?>" placeholder="Name, email, or user ID">
        </div>
        <div class="sa-um-filters__field">
            <label for="um_role">Role</label>
            <select id="um_role" name="role" class="form-select form-select-sm">
                <option value="">All roles</option>
                <?php foreach ($assignableRoles as $role): ?>
                    <option value="<?= e($role) ?>" <?= $filters['role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sa-um-filters__field">
            <label for="um_dept">Department</label>
            <select id="um_dept" name="department" class="form-select form-select-sm">
                <option value="">All departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= e($d) ?>" <?= $filters['department'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sa-um-filters__field">
            <label for="um_status">Status</label>
            <select id="um_status" name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <option value="active" <?= $filters['status']==='active'?'selected':'' ?>>Active</option>
                <option value="locked" <?= $filters['status']==='locked'?'selected':'' ?>>Locked</option>
                <option value="inactive" <?= $filters['status']==='inactive'?'selected':'' ?>>Inactive</option>
            </select>
        </div>
        <div class="sa-um-filters__actions">
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
            <a href="<?= e(route_url('admin/users')) ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <section class="sa-panel sa-um-table-panel">
        <div class="sa-table-wrap">
            <table class="sa-table sa-um-table mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($users === []): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No users match your filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                    <?php $badge = admin_user_status_badge((string)$u['status']); ?>
                    <tr>
                        <td class="sa-um-user">
                            <strong class="sa-um-user__name"><?= e((string)$u['full_name']) ?></strong>
                            <span class="sa-um-user__email"><?= e((string)($u['email'] ?: '—')) ?></span>
                            <span class="sa-um-user__id">ID #<?= (int)$u['id'] ?></span>
                        </td>
                        <td><?= e((string)$u['role']) ?></td>
                        <td><?= e((string)($u['department'] ?? '—')) ?></td>
                        <td><span class="sa-status-badge <?= e($badge['cls']) ?>"><?= e($badge['label']) ?></span></td>
                        <td class="sa-um-login"><?= e((string)($u['last_login'] ?? 'Never')) ?></td>
                        <td class="text-end"><?= admin_user_table_actions((int)$u['id'], (string)$u['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog"><form method="post" action="<?= e(admin_user_form_action()) ?>" class="modal-content">
        <?= csrf_input() ?><input type="hidden" name="action" value="create">
        <div class="modal-header"><h5 class="modal-title">Create User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-12"><label class="form-label">Full Name</label><input class="form-control" name="full_name" required></div>
            <div class="col-12"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
            <div class="col-12"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="6" required></div>
            <div class="col-12"><label class="form-label">Role</label><select class="form-select" name="role"><?php foreach ($assignableRoles as $role): ?><option><?= e($role) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary btn-sm">Create User</button></div>
    </form></div>
</div>

<div class="modal fade" id="resetPwModal" tabindex="-1">
    <div class="modal-dialog modal-sm"><form method="post" action="<?= e(admin_user_form_action()) ?>" class="modal-content admin-confirm-form">
        <?= csrf_input() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" id="resetPwUserId">
        <div class="modal-header"><h5 class="modal-title">Reset Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="small mb-0">A temporary password will be generated. The user must change it on next login.</p>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary btn-sm" data-confirm="Generate a new temporary password for this user?">Reset Password</button></div>
    </form></div>
</div>
