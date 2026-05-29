<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/../../includes/admin_users_service.php';

require_once __DIR__ . '/../../includes/admin_roles_service.php';

require_once __DIR__ . '/../../includes/admin_security_service.php';

if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }

$pdo = Database::connection();

admin_users_handle_post($pdo);

$id = (int)($_GET['id'] ?? 0);

$user = admin_user_profile($pdo, $id);

if (!$user) { echo '<div class="alert alert-warning m-3">User not found. <a href="' . e(route_url('admin/users')) . '">Back to users</a></div>'; return; }

$edit = !empty($_GET['edit']);

$badge = admin_user_status_badge((string)$user['status']);

$permissions = admin_user_permissions($pdo, (string)$user['role']);

$actions = admin_user_recent_actions($pdo, (string)$user['full_name']);

$loginHistory = admin_login_history($pdo, $id, 20);

$sessions = admin_active_sessions($pdo, $id);

$assignableRoles = admin_assignable_roles($pdo);

$back = route_url('admin/users');

?>

<div class="admin-cc module-shell">

    <?php admin_page_head((string)$user['full_name'], 'Account control · security · permissions', '<a href="' . e($back) . '" class="btn btn-sm btn-outline-secondary">Back to Users</a>'); ?>



    <div class="admin-cc__grid admin-cc__grid--2">

        <section class="admin-card">

            <h2 class="admin-card__title">User Information</h2>

            <?php if ($edit): ?>

            <form method="post" class="admin-profile-form">

                <?= csrf_input() ?><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="return" value="admin/user">

                <div class="mb-2"><label>Full Name</label><input class="form-control form-control-sm" name="full_name" value="<?= e((string)$user['full_name']) ?>" required></div>

                <div class="mb-2"><label>Email</label><input class="form-control form-control-sm" type="email" name="email" value="<?= e((string)$user['email']) ?>" required></div>

                <div class="mb-2"><label>Role</label><select class="form-select form-select-sm" name="role"><?php foreach ($assignableRoles as $role): ?><option <?= (string)$user['role']===$role?'selected':'' ?>><?= e($role) ?></option><?php endforeach; ?></select></div>

                <button class="btn btn-primary btn-sm">Save Changes</button>

            </form>

            <?php else: ?>

            <dl class="admin-dl">

                <div><dt>Email</dt><dd><?= e((string)$user['email']) ?></dd></div>

                <div><dt>Role</dt><dd><?= e((string)$user['role']) ?></dd></div>

                <div><dt>Department</dt><dd><?= e((string)($user['department'] ?? '—')) ?></dd></div>

                <div><dt>Designation</dt><dd><?= e((string)($user['designation'] ?? '—')) ?></dd></div>

                <div><dt>Employee Code</dt><dd><?= e((string)($user['employee_code'] ?? '—')) ?></dd></div>

                <div><dt>Last Login</dt><dd><?= e((string)($user['last_login'] ?? 'Never')) ?></dd></div>

                <div><dt>Must Change PW</dt><dd><?= !empty($user['must_change_password']) ? 'Yes' : 'No' ?></dd></div>

                <div><dt>Created</dt><dd><?= e((string)$user['created_at']) ?></dd></div>

            </dl>

            <a href="<?= e(route_url('admin/user', ['id' => $id, 'edit' => 1])) ?>" class="btn btn-sm btn-outline-primary">Edit Profile</a>

            <?php endif; ?>

        </section>

        <section class="admin-card">

            <h2 class="admin-card__title">Account Control</h2>

            <p class="px-3"><span class="admin-badge <?= e($badge['cls']) ?> admin-badge--lg"><?= e($badge['label']) ?></span></p>

            <div class="px-3 pb-3"><?= admin_user_row_actions($id, (string)$user['status'], 'admin/user') ?></div>

            <h3 class="admin-card__subtitle">Assigned Permissions</h3>

            <ul class="admin-perm-list"><?php foreach ($permissions as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>

        </section>

    </div>



    <div class="admin-cc__grid admin-cc__grid--2">

        <section class="admin-card admin-table-wrap">

            <h2 class="admin-card__title">Login History</h2>

            <table class="table table-sm admin-table mb-0">

                <thead><tr><th>When</th><th>IP</th><th>Result</th><th>Reason</th></tr></thead>

                <tbody>

                <?php if ($loginHistory === []): ?><tr><td colspan="4" class="text-muted">No login history yet.</td></tr><?php endif; ?>

                <?php foreach ($loginHistory as $lh): ?>

                    <tr>

                        <td><?= e(substr((string)$lh['created_at'], 0, 16)) ?></td>

                        <td><?= e((string)($lh['ip_address'] ?? '—')) ?></td>

                        <td><?= !empty($lh['success']) ? 'OK' : 'Failed' ?></td>

                        <td><?= e((string)($lh['failure_reason'] ?? '—')) ?></td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </section>

        <section class="admin-card admin-table-wrap">

            <h2 class="admin-card__title">Active Sessions (24h)</h2>

            <table class="table table-sm admin-table mb-0">

                <thead><tr><th>Last Active</th><th>IP</th><th>Agent</th></tr></thead>

                <tbody>

                <?php if ($sessions === []): ?><tr><td colspan="3" class="text-muted">No active sessions.</td></tr><?php endif; ?>

                <?php foreach ($sessions as $s): ?>

                    <tr>

                        <td><?= e((string)$s['last_active']) ?></td>

                        <td><?= e((string)($s['ip_address'] ?? '—')) ?></td>

                        <td><small><?= e(substr((string)($s['user_agent'] ?? ''), 0, 40)) ?></small></td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </section>

    </div>



    <section class="admin-card">

        <h2 class="admin-card__title">Recent ERP Actions</h2>

        <?php if ($actions === []): ?><p class="admin-card__hint">No logged actions for this user yet.</p><?php else: ?>

        <table class="table table-sm admin-table mb-0"><thead><tr><th>Action</th><th>Module</th><th>Status</th><th>When</th></tr></thead><tbody>

        <?php foreach ($actions as $a): ?><tr><td><?= e($a['action']) ?></td><td><?= e($a['module']) ?></td><td><?= e($a['status']) ?></td><td><?= e($a['when']) ?></td></tr><?php endforeach; ?>

        </tbody></table><?php endif; ?>

    </section>

</div>



<div class="modal fade" id="resetPwModal" tabindex="-1"><div class="modal-dialog modal-sm"><form method="post" class="modal-content">

    <?= csrf_input() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="return" value="admin/user">

    <div class="modal-header"><h5 class="modal-title">Reset Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>

    <div class="modal-body"><input type="password" name="password" class="form-control" minlength="6" required placeholder="New password"><small class="text-muted">User must change on next login.</small></div>

    <div class="modal-footer"><button class="btn btn-primary btn-sm">Reset Password</button></div>

</form></div></div>


