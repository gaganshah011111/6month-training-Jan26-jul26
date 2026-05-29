<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_users_service.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
admin_users_handle_post($pdo);
$filters = admin_users_parse_filters($_GET);
$users = admin_users_list($pdo, $filters);
$kpis = admin_users_kpis($pdo);
$departments = admin_user_departments($pdo);
$assignableRoles = admin_assignable_roles($pdo);
$createBtn = '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal"><i class="bi bi-plus-lg"></i> Create User</button>';
?>
<div class="admin-cc module-shell">
    <?php admin_page_head('User Management', 'Create and manage ERP user accounts — no approval workflow', $createBtn); ?>

    <section class="admin-cc__kpis admin-cc__kpis--5">
        <div class="admin-kpi"><span>Total Users</span><strong><?= (int)$kpis['total'] ?></strong></div>
        <div class="admin-kpi admin-kpi--green"><span>Active Users</span><strong><?= (int)$kpis['active'] ?></strong></div>
        <div class="admin-kpi admin-kpi--red"><span>Locked Users</span><strong><?= (int)$kpis['locked'] ?></strong></div>
        <div class="admin-kpi"><span>Logged In Today</span><strong><?= (int)$kpis['today_logins'] ?></strong></div>
        <div class="admin-kpi"><span>Inactive Users</span><strong><?= (int)$kpis['inactive'] ?></strong></div>
    </section>

    <form method="get" class="admin-cc__filters">
        <input type="hidden" name="page" value="admin/users">
        <div class="admin-field"><label>Search</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e($filters['q']) ?>" placeholder="Name or email"></div>
        <div class="admin-field"><label>Role</label><select name="role" class="form-select form-select-sm"><option value="">All roles</option><?php foreach ($assignableRoles as $role): ?><option value="<?= e($role) ?>" <?= $filters['role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option><?php endforeach; ?></select></div>
        <div class="admin-field"><label>Status</label><select name="status" class="form-select form-select-sm"><option value="">All statuses</option><option value="active" <?= $filters['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $filters['status']==='inactive'?'selected':'' ?>>Inactive</option><option value="locked" <?= $filters['status']==='locked'?'selected':'' ?>>Locked</option><option value="frozen" <?= $filters['status']==='frozen'?'selected':'' ?>>Frozen</option><option value="terminated" <?= $filters['status']==='terminated'?'selected':'' ?>>Terminated</option></select></div>
        <div class="admin-field"><label>Department</label><select name="department" class="form-select form-select-sm"><option value="">All departments</option><?php foreach ($departments as $d): ?><option value="<?= e($d) ?>" <?= $filters['department']===$d?'selected':'' ?>><?= e($d) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn-sm btn-primary">Apply Filters</button>
        <a href="<?= e(route_url('admin/users')) ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
    </form>

    <section class="admin-card admin-table-wrap">
        <table class="table table-sm admin-table mb-0">
            <thead><tr><th>User</th><th>Role</th><th>Department</th><th>Status</th><th>Last Login</th><th>Created Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($users === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No users match your filters.</td></tr><?php endif; ?>
            <?php foreach ($users as $u): ?>
                <?php $badge = admin_user_status_badge((string)$u['status']); ?>
                <tr>
                    <td><a href="<?= e(route_url('admin/user', ['id' => $u['id']])) ?>" class="admin-user-link"><strong><?= e((string)$u['full_name']) ?></strong><small><?= e((string)$u['email']) ?></small></a></td>
                    <td><?= e((string)$u['role']) ?></td>
                    <td><?= e((string)($u['department'] ?? '—')) ?></td>
                    <td><span class="admin-badge <?= e($badge['cls']) ?>"><?= e($badge['label']) ?></span></td>
                    <td><?= e((string)($u['last_login'] ?? 'Never')) ?></td>
                    <td><?= e(substr((string)$u['created_at'], 0, 10)) ?></td>
                    <td><?= admin_user_row_actions((int)$u['id'], (string)$u['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>

<div class="modal fade" id="createUserModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content">
    <?= csrf_input() ?><input type="hidden" name="action" value="create">
    <div class="modal-header"><h5 class="modal-title">Create User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-2">
        <div class="col-12"><label class="form-label">Full Name</label><input class="form-control" name="full_name" required></div>
        <div class="col-12"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
        <div class="col-12"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="6" required></div>
        <div class="col-12"><label class="form-label">Role</label><select class="form-select" name="role"><?php foreach ($assignableRoles as $role): ?><option><?= e($role) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Create User</button></div>
</form></div></div>

<div class="modal fade" id="resetPwModal" tabindex="-1"><div class="modal-dialog modal-sm"><form method="post" class="modal-content">
    <?= csrf_input() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" id="resetPwUserId">
    <div class="modal-header"><h5 class="modal-title">Reset Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label">New password</label><input type="password" name="password" class="form-control" minlength="6" required></div>
    <div class="modal-footer"><button class="btn btn-primary btn-sm">Reset Password</button></div>
</form></div></div>
