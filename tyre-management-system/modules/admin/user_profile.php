<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_users_service.php';
require_once __DIR__ . '/../../includes/admin_roles_service.php';
require_once __DIR__ . '/../../includes/admin_security_service.php';

if (!admin_can_access()) {
    echo '<div class="alert alert-warning m-3">Access denied.</div>';
    return;
}

$pdo = Database::connection();
admin_users_handle_post($pdo);

$id = (int)($_GET['id'] ?? 0);
$user = admin_user_profile($pdo, $id);
if (!$user) {
    echo '<div class="alert alert-warning m-3">User not found. <a href="' . e(route_url('admin/users')) . '">Back</a></div>';
    return;
}

$edit = !empty($_GET['edit']);
$tab = (string)($_GET['tab'] ?? 'profile');
$badge = admin_user_status_badge((string)$user['status']);
$modules = admin_user_module_access($pdo, (string)$user['role']);
$matrix = admin_roles_permission_matrix();
$effective = role_effective_for_access((string)$user['role'], $pdo);
$rolePerms = $matrix[$effective] ?? [];
$timeline = admin_user_activity_timeline($pdo, (string)$user['full_name']);
$security = admin_user_security_summary($pdo, $id, $user);
$loginHistory = admin_login_history($pdo, $id, 15);
$sessions = admin_active_sessions($pdo, $id);
$assignableRoles = admin_assignable_roles($pdo);
$initials = admin_user_initials((string)$user['full_name']);
$avatarTone = match ((string)$user['role']) {
    'Super Admin', 'Admin' => 'slate', 'Sales Manager' => 'violet', 'Accounts Manager' => 'blue',
    'Inventory Manager' => 'teal', 'HR Manager' => 'pink', 'Production Manager' => 'orange',
    'Dispatch Manager' => 'indigo', 'Quality Manager' => 'green', default => 'blue',
};
$baseUrl = route_url('admin/user', ['id' => $id]);
?>

<div class="sa-console sa-profile-page sa-profile-tabs" id="saProfilePage" data-auto-edit="<?= $edit ? '1' : '0' ?>">
    <a href="<?= e(route_url('admin/users')) ?>" class="sa-prof-back">← Back to Users</a>

    <header class="sa-prof-hero sa-prof-hero--compact">
        <div class="sa-prof-hero__main">
            <div class="sa-prof-avatar sa-prof-avatar--<?= e($avatarTone) ?>"><?= e($initials) ?></div>
            <div>
                <h1 class="sa-prof-hero__name"><?= e((string)$user['full_name']) ?></h1>
                <p class="sa-prof-hero__meta"><strong><?= e((string)$user['role']) ?></strong> · <?= e((string)($user['department'] ?? 'No department')) ?></p>
                <span class="sa-status-badge <?= e($badge['cls']) ?>"><?= e($badge['label']) ?></span>
            </div>
        </div>
        <?= admin_user_profile_quick_actions($id, (string)$user['status']) ?>
    </header>

    <ul class="nav nav-tabs sa-prof-tabs" role="tablist">
        <li class="nav-item"><a class="nav-link <?= $tab === 'profile' ? 'active' : '' ?>" href="<?= e($baseUrl . '&tab=profile') ?>">Profile</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'status' ? 'active' : '' ?>" href="<?= e($baseUrl . '&tab=status') ?>">Account Status</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'permissions' ? 'active' : '' ?>" href="<?= e($baseUrl . '&tab=permissions') ?>">Permissions</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'activity' ? 'active' : '' ?>" href="<?= e($baseUrl . '&tab=activity') ?>">Activity</a></li>
    </ul>

    <div class="sa-prof-tab-body">
        <?php if ($tab === 'profile'): ?>
            <div class="sa-prof-overview">
                <div class="sa-prof-stat"><span class="sa-prof-stat__label">Email</span><span class="sa-prof-stat__value"><?= e((string)($user['email'] ?: '—')) ?></span></div>
                <div class="sa-prof-stat"><span class="sa-prof-stat__label">Role</span><span class="sa-prof-stat__value"><?= e((string)$user['role']) ?></span></div>
                <div class="sa-prof-stat"><span class="sa-prof-stat__label">Department</span><span class="sa-prof-stat__value"><?= e((string)($user['department'] ?? '—')) ?></span></div>
                <div class="sa-prof-stat"><span class="sa-prof-stat__label">Employee ID</span><span class="sa-prof-stat__value"><?= e((string)($user['employee_code'] ?? '—')) ?></span></div>
                <div class="sa-prof-stat"><span class="sa-prof-stat__label">Created</span><span class="sa-prof-stat__value"><?= e(admin_format_profile_date((string)($user['created_at'] ?? null))) ?></span></div>
                <div class="sa-prof-stat"><span class="sa-prof-stat__label">Last Login</span><span class="sa-prof-stat__value"><?= e(admin_format_profile_date((string)($user['last_login'] ?? null), true)) ?></span></div>
            </div>

        <?php elseif ($tab === 'status'): ?>
            <div class="sa-prof-security">
                <div class="sa-prof-sec-item"><div class="sa-prof-sec-item__label">Current Status</div><div class="sa-prof-sec-item__value"><span class="sa-status-badge <?= e($badge['cls']) ?>"><?= e($badge['label']) ?></span></div></div>
                <div class="sa-prof-sec-item"><div class="sa-prof-sec-item__label">Failed Login Attempts</div><div class="sa-prof-sec-item__value"><?= (int)$security['failed_logins'] ?></div></div>
                <div class="sa-prof-sec-item"><div class="sa-prof-sec-item__label">Password Last Changed</div><div class="sa-prof-sec-item__value"><?= e($security['password_last_changed']) ?></div></div>
                <div class="sa-prof-sec-item"><div class="sa-prof-sec-item__label">Force Password Change</div><div class="sa-prof-sec-item__value"><?= $security['force_password_change'] ? 'Required' : 'Not required' ?></div></div>
            </div>
            <div class="sa-status-help mt-3">
                <p><strong>Active</strong> — Can login and use assigned modules.</p>
                <p><strong>Locked</strong> — Cannot login; all sessions terminated.</p>
                <p><strong>Inactive</strong> — Cannot login.</p>
            </div>

        <?php elseif ($tab === 'permissions'): ?>
            <div class="sa-prof-modules mb-3">
                <?php foreach ($modules as $mod): ?>
                    <article class="sa-mod-card sa-mod-card--<?= e($mod['tone']) ?><?= $mod['access'] ? '' : ' sa-mod-card--denied' ?>">
                        <h3 class="sa-mod-card__name"><?= e($mod['label']) ?></h3>
                        <span class="sa-mod-card__access sa-mod-card__access--<?= $mod['access'] ? 'yes' : 'no' ?>"><?= $mod['access'] ? '✓ Access' : '✗ No Access' ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($rolePerms !== []): ?>
            <section class="sa-panel sa-table-wrap">
                <table class="sa-table sa-matrix mb-0">
                    <thead><tr><th>Module</th><?php foreach (ACC_ADMIN_PERMISSIONS as $p): ?><th><?= e(ucfirst($p)) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($rolePerms as $mod => $flags): ?>
                        <tr><td><strong><?= e($mod) ?></strong></td><?php foreach (ACC_ADMIN_PERMISSIONS as $p): ?><td><?= !empty($flags[$p]) ? '<span class="sa-matrix-yes">✓</span>' : '<span class="sa-matrix-no">—</span>' ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            <?php endif; ?>

        <?php else: ?>
            <div class="sa-prof-grid">
                <section class="sa-prof-card">
                    <div class="sa-prof-card__head">Recent Actions</div>
                    <div class="sa-prof-card__body">
                        <?php if ($timeline === []): ?><div class="sa-prof-empty"><p class="sa-prof-empty__title">No activity</p></div>
                        <?php else: ?>
                            <ul class="sa-prof-timeline"><?php foreach ($timeline as $item): ?>
                                <li class="sa-prof-timeline__item"><div class="sa-prof-timeline__icon"><?= e($item['icon']) ?></div><div><div class="sa-prof-timeline__date"><?= e($item['date']) ?></div><p class="sa-prof-timeline__title"><?= e($item['title']) ?></p></div></li>
                            <?php endforeach; ?></ul>
                        <?php endif; ?>
                    </div>
                </section>
                <section class="sa-prof-card">
                    <div class="sa-prof-card__head">Login History</div>
                    <div class="sa-prof-card__body">
                        <?php if ($loginHistory === []): ?><div class="sa-prof-empty"><p class="sa-prof-empty__title">No login records</p></div>
                        <?php else: foreach ($loginHistory as $lh): $ok = !empty($lh['success']); ?>
                            <div class="sa-prof-login"><span class="sa-prof-login__badge sa-prof-login__badge--<?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? 'OK' : 'Fail' ?></span><div><div class="sa-prof-login__when"><?= e(admin_format_profile_date((string)$lh['created_at'], true)) ?></div><div class="sa-prof-login__detail">IP <?= e((string)($lh['ip_address'] ?? '—')) ?></div></div></div>
                        <?php endforeach; endif; ?>
                    </div>
                </section>
            </div>
            <section class="sa-prof-card mt-3">
                <div class="sa-prof-card__head">Active Sessions</div>
                <div class="sa-prof-card__body">
                    <?php if ($sessions === []): ?><div class="sa-prof-empty"><p class="sa-prof-empty__title">No active sessions</p></div>
                    <?php else: foreach ($sessions as $s): ?>
                        <div class="sa-prof-session"><span class="sa-prof-session__dot"></span><div><p class="sa-prof-session__title">Active session</p><p class="sa-prof-session__meta"><?= e(admin_format_profile_date((string)$s['last_active'], true)) ?> · IP <?= e((string)($s['ip_address'] ?? '—')) ?></p></div></div>
                    <?php endforeach; endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1"><div class="modal-dialog"><form method="post" action="<?= e(admin_user_form_action()) ?>" class="modal-content">
    <?= csrf_input() ?><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="return" value="admin/user">
    <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-12"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= e((string)$user['full_name']) ?>" required></div>
        <div class="col-12"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e((string)$user['email']) ?>" required></div>
        <div class="col-12"><label class="form-label">Role</label><select class="form-select" name="role"><?php foreach ($assignableRoles as $role): ?><option <?= (string)$user['role']===$role?'selected':'' ?>><?= e($role) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary btn-sm">Save</button></div>
</form></div></div>

<div class="modal fade" id="resetPwModal" tabindex="-1"><div class="modal-dialog modal-sm"><form method="post" action="<?= e(admin_user_form_action()) ?>" class="modal-content admin-confirm-form">
    <?= csrf_input() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?= $id ?>" id="resetPwUserId"><input type="hidden" name="return" value="admin/user">
    <div class="modal-header"><h5 class="modal-title">Reset Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="small mb-0">A temporary password will be generated. The user must change it on next login.</p></div>
    <div class="modal-footer"><button type="submit" class="btn btn-primary btn-sm" data-confirm="Generate a new temporary password for this user?">Reset Password</button></div>
</form></div></div>

<script>(function(){var p=document.getElementById('saProfilePage');if(!p||p.getAttribute('data-auto-edit')!=='1')return;var m=document.getElementById('editUserModal');if(m&&window.bootstrap)bootstrap.Modal.getOrCreateInstance(m).show();})();</script>
