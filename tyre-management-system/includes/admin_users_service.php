<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/admin_control_center.php';
require_once __DIR__ . '/admin_audit_service.php';
require_once __DIR__ . '/admin_roles_service.php';
require_once __DIR__ . '/admin_security_service.php';

const ADMIN_USER_ROLES = [
    'Super Admin',
    'Admin',
    'HR Manager',
    'Accounts Manager',
    'Sales Manager',
    'Inventory Manager',
    'Production Manager',
    'Dispatch Manager',
    'Quality Manager',
    'Employee',
];

/** @return array{department: string, q: string, role: string, status: string} */
function admin_users_parse_filters(array $input): array
{
    return [
        'department' => trim((string)($input['department'] ?? '')),
        'q' => trim((string)($input['q'] ?? $input['search'] ?? '')),
        'role' => trim((string)($input['role'] ?? '')),
        'status' => trim((string)($input['status'] ?? '')),
    ];
}

/** @return list<string> */
function admin_assignable_roles(PDO $pdo): array
{
    $roles = ADMIN_USER_ROLES;
    if (function_exists('admin_custom_role_names')) {
        require_once __DIR__ . '/admin_roles_service.php';
        $roles = array_values(array_unique(array_merge($roles, admin_custom_role_names($pdo))));
    }

    return $roles;
}

function admin_users_redirect_target(): string
{
    $return = trim((string)($_POST['return'] ?? $_GET['return'] ?? ''));
    if ($return !== '' && preg_match('/^admin\/[\w-]+$/', $return)) {
        return $return;
    }
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId > 0 && !empty($_POST['action']) && in_array($_POST['action'], [
        'reset_password', 'lock', 'unlock', 'deactivate', 'activate', 'freeze', 'unfreeze',
        'terminate', 'force_password_change', 'force_logout',
    ], true)) {
        return 'admin/user';
    }

    return 'admin/users';
}

function admin_users_handle_post(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_can_access()) {
        return;
    }
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $userId = (int)($_POST['user_id'] ?? 0);
    $return = admin_users_redirect_target();
    $returnParams = array_filter([
        'id' => $userId > 0 && ($return === 'admin/user' || str_contains($return, 'user')) ? $userId : null,
        'department' => $_GET['department'] ?? null,
        'role' => $_GET['role'] ?? null,
        'status' => $_GET['status'] ?? null,
        'q' => $_GET['q'] ?? null,
    ], static fn($v) => $v !== null && $v !== '');

    try {
        if ($action === 'create') {
            $name = trim((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? 'Employee');
            if ($name === '' || $email === '' || strlen($password) < 6) {
                throw new InvalidArgumentException('Name, email, and password (min 6 chars) are required.');
            }
            if (!in_array($role, admin_assignable_roles($pdo), true)) {
                throw new InvalidArgumentException('Invalid role selected.');
            }
            $st = $pdo->prepare('INSERT INTO users(full_name, email, password_hash, role, status) VALUES (:n, :e, :p, :r, "active")');
            $st->execute(['n' => $name, 'e' => $email, 'p' => password_hash($password, PASSWORD_DEFAULT), 'r' => $role]);
            admin_audit_log($pdo, 'Created user account', 'User Management', 'success', $name);
            set_flash('success', 'User created successfully.');
        } elseif ($action === 'update') {
            $name = trim((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $role = (string)($_POST['role'] ?? '');
            if ($userId <= 0 || $name === '' || $email === '') {
                throw new InvalidArgumentException('Invalid user data.');
            }
            if (!in_array($role, admin_assignable_roles($pdo), true)) {
                throw new InvalidArgumentException('Invalid role.');
            }
            $oldSt = $pdo->prepare('SELECT role, full_name FROM users WHERE id = :id');
            $oldSt->execute(['id' => $userId]);
            $oldRow = $oldSt->fetch(PDO::FETCH_ASSOC) ?: [];
            $st = $pdo->prepare('UPDATE users SET full_name = :n, email = :e, role = :r WHERE id = :id');
            $st->execute(['n' => $name, 'e' => $email, 'r' => $role, 'id' => $userId]);
            admin_audit_log($pdo, 'Updated user profile', 'User Management', 'success', $name, (string)($oldRow['role'] ?? ''), $role, 'user', $userId);
            set_flash('success', 'User updated.');
            $return = 'admin/user';
            $returnParams = ['id' => $userId];
        } elseif ($action === 'deactivate') {
            $st = $pdo->prepare('UPDATE users SET status = "inactive" WHERE id = :id');
            $st->execute(['id' => $userId]);
            admin_audit_log($pdo, 'Deactivated user account', 'User Management', 'warning', '#' . $userId);
            set_flash('success', 'User deactivated.');
        } elseif ($action === 'activate') {
            $st = $pdo->prepare('UPDATE users SET status = "active" WHERE id = :id');
            $st->execute(['id' => $userId]);
            admin_audit_log($pdo, 'Activated user account', 'User Management', 'success', '#' . $userId);
            set_flash('success', 'User activated.');
        } elseif ($action === 'reset_password') {
            $password = (string)($_POST['password'] ?? '');
            if ($userId <= 0 || strlen($password) < 6) {
                throw new InvalidArgumentException('Password must be at least 6 characters.');
            }
            $st = $pdo->prepare('UPDATE users SET password_hash = :p, must_change_password = 1 WHERE id = :id');
            $st->execute(['p' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
            admin_audit_log($pdo, 'Reset user password', 'User Management', 'warning', '#' . $userId);
            set_flash('success', 'Password reset. User must change on next login.');
        } elseif ($action === 'lock') {
            $st = $pdo->prepare('UPDATE users SET status = "locked" WHERE id = :id');
            $st->execute(['id' => $userId]);
            admin_audit_log($pdo, 'Locked user account', 'User Management', 'warning', '#' . $userId, 'active', 'locked', 'user', $userId);
            set_flash('success', 'Account locked.');
        } elseif ($action === 'unlock') {
            $st = $pdo->prepare('UPDATE users SET status = "active" WHERE id = :id');
            $st->execute(['id' => $userId]);
            admin_audit_log($pdo, 'Unlocked user account', 'User Management', 'success', '#' . $userId, 'locked', 'active', 'user', $userId);
            set_flash('success', 'Account unlocked.');
        } elseif ($action === 'freeze') {
            $st = $pdo->prepare('UPDATE users SET status = "frozen" WHERE id = :id');
            $st->execute(['id' => $userId]);
            admin_revoke_all_sessions($pdo, $userId);
            admin_audit_log($pdo, 'Froze user account', 'User Management', 'warning', '#' . $userId, null, 'frozen', 'user', $userId);
            set_flash('success', 'Account frozen and sessions revoked.');
        } elseif ($action === 'unfreeze') {
            $st = $pdo->prepare('UPDATE users SET status = "active" WHERE id = :id');
            $st->execute(['id' => $userId]);
            admin_audit_log($pdo, 'Unfroze user account', 'User Management', 'success', '#' . $userId, 'frozen', 'active', 'user', $userId);
            set_flash('success', 'Account unfrozen.');
        } elseif ($action === 'terminate') {
            $st = $pdo->prepare('UPDATE users SET status = "terminated" WHERE id = :id');
            $st->execute(['id' => $userId]);
            admin_revoke_all_sessions($pdo, $userId);
            admin_audit_log($pdo, 'Terminated user account', 'User Management', 'danger', '#' . $userId, null, 'terminated', 'user', $userId);
            set_flash('success', 'Account terminated.');
        } elseif ($action === 'force_password_change') {
            $st = $pdo->prepare('UPDATE users SET must_change_password = 1 WHERE id = :id');
            $st->execute(['id' => $userId]);
            admin_audit_log($pdo, 'Forced password change on next login', 'User Management', 'warning', '#' . $userId);
            set_flash('success', 'User must change password on next login.');
        } elseif ($action === 'force_logout') {
            admin_revoke_all_sessions($pdo, $userId);
            admin_audit_log($pdo, 'Forced logout from all devices', 'User Management', 'warning', '#' . $userId);
            set_flash('success', 'All sessions revoked for this user.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }

    redirect($return, $returnParams);
}

/** @return array<string, int> */
function admin_users_kpis(PDO $pdo): array
{
    return [
        'total' => admin_table_count($pdo, 'users'),
        'active' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE status = 'active'"),
        'locked' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE status = 'locked'"),
        'today_logins' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE last_login >= CURDATE()"),
        'frozen' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE status = 'frozen'"),
        'terminated' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE status = 'terminated'"),
    ];
}

/** @return list<array<string, mixed>> */
function admin_users_list(PDO $pdo, array $filters): array
{
    $sql = 'SELECT u.id, u.full_name, u.email, u.role, u.status, u.created_at, u.last_login, e.department
            FROM users u
            LEFT JOIN employees e ON e.user_id = u.id
            WHERE 1=1';
    $params = [];

    if ($filters['q'] !== '') {
        $sql .= ' AND (u.full_name LIKE :q OR u.email LIKE :q OR u.role LIKE :q)';
        $params['q'] = '%' . $filters['q'] . '%';
    }
    if ($filters['role'] !== '') {
        $sql .= ' AND u.role = :role';
        $params['role'] = $filters['role'];
    }
    if ($filters['status'] !== '') {
        $sql .= ' AND u.status = :status';
        $params['status'] = $filters['status'];
    }
    if ($filters['department'] !== '') {
        $sql .= ' AND e.department LIKE :dept';
        $params['dept'] = '%' . $filters['department'] . '%';
    }

    $sql .= ' ORDER BY u.full_name ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, mixed>|null */
function admin_user_profile(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        'SELECT u.*, e.department, e.designation, e.employee_code
         FROM users u
         LEFT JOIN employees e ON e.user_id = u.id
         WHERE u.id = :id LIMIT 1'
    );
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return list<string> */
function admin_user_permissions(PDO $pdo, string $role): array
{
    unset($pdo);
    $matrix = admin_roles_permission_matrix();
    $perms = [];
    foreach ($matrix[$role] ?? [] as $mod => $flags) {
        foreach ($flags as $p => $ok) {
            if ($ok) {
                $perms[] = $mod . ': ' . ucfirst($p);
            }
        }
    }
    if ($role === 'Super Admin') {
        $perms = ['Full system access'];
    }

    return array_slice($perms, 0, 24);
}

/** @return list<array<string, string>> */
function admin_user_recent_actions(PDO $pdo, string $name): array
{
    admin_audit_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT action_text, module_name, status, created_at FROM erp_activity_log WHERE user_name = :u ORDER BY id DESC LIMIT 10'
    );
    $st->execute(['u' => $name]);
    $rows = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $ts = (string)$r['created_at'];
        $rows[] = [
            'action' => (string)$r['action_text'],
            'module' => (string)$r['module_name'],
            'status' => (string)$r['status'],
            'when' => substr($ts, 0, 16),
        ];
    }

    return $rows;
}

/** @return list<string> */
function admin_user_departments(PDO $pdo): array
{
    $rows = $pdo->query("SELECT DISTINCT department FROM employees WHERE department <> '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN) ?: [];

    return array_map('strval', $rows);
}

function admin_user_status_badge(string $status): array
{
    return match ($status) {
        'active' => ['label' => 'Active', 'cls' => 'admin-badge--ok'],
        'inactive' => ['label' => 'Inactive', 'cls' => 'admin-badge--muted'],
        'locked' => ['label' => 'Locked', 'cls' => 'admin-badge--danger'],
        'frozen' => ['label' => 'Frozen', 'cls' => 'admin-badge--warn'],
        'terminated' => ['label' => 'Terminated', 'cls' => 'admin-badge--danger'],
        default => ['label' => ucfirst($status), 'cls' => 'admin-badge--muted'],
    };
}

function admin_user_row_actions(int $userId, string $status, string $returnPage = 'admin/users'): string
{
    $csrf = csrf_input();
    $returnField = $returnPage !== 'admin/users' ? '<input type="hidden" name="return" value="' . e($returnPage) . '">' : '';
    $view = route_url('admin/user', ['id' => $userId]);
    $edit = route_url('admin/user', ['id' => $userId, 'edit' => 1]);
    $html = '<div class="admin-labeled-actions">';
    $html .= '<a href="' . e($view) . '" class="admin-action-btn admin-action-btn--primary">View</a>';
    $html .= '<a href="' . e($edit) . '" class="admin-action-btn admin-action-btn--neutral">Edit</a>';
    $html .= '<button type="button" class="admin-action-btn admin-action-btn--neutral js-reset-pw" data-user-id="' . $userId . '">Reset Password</button>';

    $formBtn = static function (string $label, string $action, string $tone, string $confirm = '') use ($csrf, $returnField, $userId): string {
        $confirmAttr = $confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '';

        return '<form method="post" class="d-inline admin-confirm-form">'
            . $csrf . $returnField
            . '<input type="hidden" name="action" value="' . e($action) . '">'
            . '<input type="hidden" name="user_id" value="' . $userId . '">'
            . '<button type="submit" class="admin-action-btn admin-action-btn--' . e($tone) . '"' . $confirmAttr . '>' . e($label) . '</button></form>';
    };

    if ($status === 'locked') {
        $html .= $formBtn('Unlock', 'unlock', 'success');
    } elseif ($status !== 'terminated') {
        $html .= $formBtn('Lock', 'lock', 'warn', 'Lock this user account?');
    }
    if ($status === 'frozen') {
        $html .= $formBtn('Unfreeze', 'unfreeze', 'success');
    } elseif (!in_array($status, ['terminated', 'locked'], true)) {
        $html .= $formBtn('Freeze', 'freeze', 'warn', 'Freeze this account and revoke sessions?');
    }
    if ($status === 'inactive') {
        $html .= $formBtn('Activate', 'activate', 'success');
    } elseif ($status !== 'terminated') {
        $html .= $formBtn('Deactivate', 'deactivate', 'danger', 'Deactivate this user?');
    }
    if ($status !== 'terminated') {
        $html .= $formBtn('Force PW Change', 'force_password_change', 'neutral', 'Require password change on next login?');
        $html .= $formBtn('Force Logout', 'force_logout', 'warn', 'Revoke all active sessions for this user?');
        $html .= $formBtn('Terminate', 'terminate', 'danger', 'Permanently terminate this account? This cannot be undone easily.');
    }
    $html .= '</div>';

    return $html;
}
