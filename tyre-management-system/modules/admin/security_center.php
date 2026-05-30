<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_security_center_service.php';
require_once __DIR__ . '/../../includes/admin_users_service.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
admin_users_handle_post($pdo);
admin_security_center_handle_post($pdo);
$data = admin_security_center_data($pdo);
$stats = $data['stats'];
$settings = admin_settings_load($pdo, ADMIN_SYSTEM_KEYS);
?>
<div class="sa-console">
    <?php admin_page_head('Security Center', 'Account security, login threats, password policy, and incident response'); ?>

    <div class="sa-dash-stats sa-dash-stats--4 mb-3">
        <div class="sa-dash-stat sa-dash-stat--red"><span>Locked Users</span><strong><?= (int)$stats['locked'] ?></strong></div>
        <div class="sa-dash-stat"><span>Inactive Users</span><strong><?= (int)$stats['inactive'] ?></strong></div>
        <div class="sa-dash-stat sa-dash-stat--red"><span>Failed Logins (7d)</span><strong><?= (int)$stats['failed_7d'] ?></strong></div>
        <div class="sa-dash-stat"><span>Password Change Required</span><strong><?= (int)$stats['must_change_pw'] ?></strong></div>
    </div>

    <div class="sa-dash-row sa-dash-row--2">
        <section class="sa-panel">
            <div class="sa-panel__head"><h2 class="sa-panel__title">Locked & Frozen Accounts</h2></div>
            <div class="sa-panel__body">
                <?php if ($data['locked_users'] === []): ?>
                    <div class="sa-prof-empty"><p class="sa-prof-empty__title">No locked accounts</p></div>
                <?php else: ?>
                    <?php foreach ($data['locked_users'] as $u): ?>
                        <div class="sa-sec-user">
                            <div>
                                <strong><?= e((string)$u['full_name']) ?></strong>
                                <span class="sa-status-badge sa-status--locked"><?= e(ucfirst((string)$u['status'])) ?></span>
                                <small class="d-block text-muted"><?= e((string)$u['role']) ?> · <?= e((string)($u['email'] ?? '')) ?></small>
                            </div>
                            <div class="sa-sec-user__acts">
                                <a href="<?= e(route_url('admin/user', ['id' => $u['id']])) ?>" class="sa-btn">View</a>
                                <form method="post" action="<?= e(route_url('admin/security-center')) ?>" class="admin-confirm-form d-inline"><?= csrf_input() ?><input type="hidden" name="action" value="unlock"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="return" value="admin/security-center"><button class="sa-btn sa-btn--success">Unlock</button></form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="sa-panel">
            <div class="sa-panel__head"><h2 class="sa-panel__title">Recent Security Events</h2></div>
            <div class="sa-panel__body">
                <?php if ($data['security_events'] === []): ?>
                    <p class="sa-dash-empty mb-0">No security events.</p>
                <?php else: ?>
                    <ul class="sa-dash-actions">
                        <?php foreach ($data['security_events'] as $ev): ?>
                            <li><strong><?= e((string)$ev['action_text']) ?></strong><span><?= e((string)$ev['user_name']) ?> · <?= e(substr((string)$ev['created_at'], 0, 16)) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="sa-dash-row sa-dash-row--2">
        <section class="sa-panel sa-table-wrap">
            <div class="sa-panel__head"><h2 class="sa-panel__title">Failed Login Attempts</h2></div>
            <table class="sa-table mb-0"><thead><tr><th>Date / Time</th><th>Login</th><th>IP</th><th>Reason</th></tr></thead><tbody>
            <?php if ($data['failed_logins'] === []): ?><tr><td colspan="4" class="text-center text-muted py-4">No failed attempts.</td></tr><?php endif; ?>
            <?php foreach ($data['failed_logins'] as $f): ?>
                <tr><td><?= e(substr((string)$f['created_at'], 0, 16)) ?></td><td><?= e((string)$f['login_name']) ?></td><td><?= e((string)($f['ip_address'] ?? '—')) ?></td><td><?= e((string)($f['failure_reason'] ?? '—')) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </section>

        <section class="sa-panel sa-table-wrap">
            <div class="sa-panel__head"><h2 class="sa-panel__title">Password Reset History</h2></div>
            <table class="sa-table mb-0"><thead><tr><th>When</th><th>By</th><th>Action</th></tr></thead><tbody>
            <?php if ($data['password_history'] === []): ?><tr><td colspan="3" class="text-center text-muted py-4">No password events.</td></tr><?php endif; ?>
            <?php foreach ($data['password_history'] as $p): ?>
                <tr><td><?= e(substr((string)$p['created_at'], 0, 16)) ?></td><td><?= e((string)$p['user_name']) ?></td><td><?= e((string)$p['action_text']) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </section>
    </div>

    <section class="sa-panel mt-3">
        <div class="sa-panel__head"><h2 class="sa-panel__title">Security Policy</h2></div>
        <form method="post" class="sa-panel__body">
            <?= csrf_input() ?><input type="hidden" name="action" value="save_security_policy">
            <div class="sa-form-grid">
                <div><label class="form-label small">Min Password Length</label><input class="form-control form-control-sm" type="number" name="password_min_length" value="<?= e($settings['password_min_length'] ?? '') ?>"></div>
                <div><label class="form-label small">Max Failed Logins</label><input class="form-control form-control-sm" type="number" name="login_max_attempts" value="<?= e($settings['login_max_attempts'] ?? '') ?>"></div>
                <div><label class="form-label small">Lockout (minutes)</label><input class="form-control form-control-sm" type="number" name="login_lockout_minutes" value="<?= e($settings['login_lockout_minutes'] ?? '') ?>"></div>
                <div><label class="form-label small">Session Timeout (minutes)</label><input class="form-control form-control-sm" type="number" name="session_timeout_minutes" value="<?= e($settings['session_timeout_minutes'] ?? '') ?>"></div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm mt-3">Save Security Policy</button>
        </form>
    </section>
</div>
