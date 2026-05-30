<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/admin_control_center.php';
require_once __DIR__ . '/admin_audit_service.php';
require_once __DIR__ . '/admin_roles_service.php';
require_once __DIR__ . '/admin_security_service.php';

const ADMIN_USER_STATUSES = ['active', 'locked', 'inactive'];

function admin_users_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        $type = strtolower((string)($col['Type'] ?? ''));
        if ($type !== '' && str_contains($type, 'enum')) {
            $pdo->exec("ALTER TABLE users MODIFY status VARCHAR(20) NOT NULL DEFAULT 'active'");
        }
        $pdo->exec("UPDATE users SET status = 'active' WHERE status = '' OR status IS NULL");
    } catch (Throwable) {
    }
    admin_users_migrate_legacy_statuses($pdo);
}

function admin_normalize_user_status(string $status): string
{
    $status = strtolower(trim($status));

    return match ($status) {
        'active', '1' => 'active',
        'locked' => 'locked',
        'inactive', 'frozen', 'terminated', 'deactivated' => 'inactive',
        default => 'inactive',
    };
}

function admin_users_migrate_legacy_statuses(PDO $pdo): void
{
    try {
        $pdo->exec("UPDATE users SET status = 'inactive' WHERE status IN ('frozen', 'terminated', 'deactivated')");
    } catch (Throwable) {
    }
}

function admin_user_form_action(): string
{
    $page = trim((string)($_GET['page'] ?? 'admin/users'));
    $params = ['page' => $page];
    if ($page === 'admin/user' && !empty($_GET['id'])) {
        $params['id'] = (int)$_GET['id'];
        if (!empty($_GET['tab'])) {
            $params['tab'] = (string)$_GET['tab'];
        }
    }
    foreach (['q', 'role', 'department', 'status'] as $key) {
        if (!empty($_GET[$key])) {
            $params[$key] = (string)$_GET[$key];
        }
    }

    return route_url($page, $params);
}

function admin_generate_temp_password(): string
{
    return 'Tmp' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

/** @param array<string, mixed> $targetUser */
function admin_user_audit(PDO $pdo, string $action, array $targetUser, string $status = 'success', ?string $oldValue = null, ?string $newValue = null): void
{
    $targetId = (int)($targetUser['id'] ?? 0);
    $targetName = (string)($targetUser['full_name'] ?? ('#' . $targetId));
    admin_audit_log(
        $pdo,
        $action,
        'User Management',
        $status,
        'Target: ' . $targetName,
        $oldValue,
        $newValue,
        'user',
        $targetId > 0 ? $targetId : null
    );
}

/** @return array<string, mixed> */
function admin_user_require(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        throw new InvalidArgumentException('Invalid user.');
    }
    $user = admin_user_profile($pdo, $userId);
    if (!$user) {
        throw new InvalidArgumentException('User not found.');
    }

    return $user;
}

function admin_user_set_status(PDO $pdo, int $userId, string $newStatus, bool $revokeSessions = false): array
{
    admin_users_ensure_schema($pdo);
    $user = admin_user_require($pdo, $userId);
    $old = admin_normalize_user_status((string)$user['status']);
    $newStatus = admin_normalize_user_status($newStatus);
    if ($old === $newStatus) {
        return ['user' => $user, 'old' => $old, 'new' => $newStatus];
    }
    $st = $pdo->prepare('UPDATE users SET status = :s WHERE id = :id');
    $st->execute(['s' => $newStatus, 'id' => $userId]);
    $stCheck = $pdo->prepare('SELECT status FROM users WHERE id = :id');
    $stCheck->execute(['id' => $userId]);
    $saved = admin_normalize_user_status((string)$stCheck->fetchColumn());
    if ($saved !== $newStatus) {
        throw new RuntimeException('Failed to update user status.');
    }
    if ($revokeSessions) {
        admin_revoke_all_sessions($pdo, $userId);
    }
    if ($newStatus === 'active') {
        $pdo->prepare('UPDATE users SET force_logout_at = NULL WHERE id = :id')->execute(['id' => $userId]);
    }
    $user['status'] = $newStatus;

    if (!function_exists('auth_status_log')) {
        require_once __DIR__ . '/user_account_status.php';
    }
    auth_status_log($pdo, 'status_store', $userId, $newStatus, 'old=' . $old);

    return ['user' => $user, 'old' => $old, 'new' => $newStatus];
}

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
        'reset_password', 'lock', 'unlock', 'deactivate', 'activate', 'force_logout',
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
    admin_users_ensure_schema($pdo);

    $action = (string)($_POST['action'] ?? '');
    $userId = (int)($_POST['user_id'] ?? 0);
    $return = admin_users_redirect_target();
    $returnParams = array_filter([
        'id' => $userId > 0 && ($return === 'admin/user' || str_contains($return, 'user')) ? $userId : null,
        'department' => $_GET['department'] ?? null,
        'role' => $_GET['role'] ?? null,
        'status' => $_GET['status'] ?? null,
        'q' => $_GET['q'] ?? null,
        'tab' => $_GET['tab'] ?? null,
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
            $user = admin_user_require($pdo, $userId);
            if ($name === '' || $email === '') {
                throw new InvalidArgumentException('Invalid user data.');
            }
            if (!in_array($role, admin_assignable_roles($pdo), true)) {
                throw new InvalidArgumentException('Invalid role.');
            }
            $st = $pdo->prepare('UPDATE users SET full_name = :n, email = :e, role = :r WHERE id = :id');
            $st->execute(['n' => $name, 'e' => $email, 'r' => $role, 'id' => $userId]);
            admin_user_audit($pdo, 'Updated user profile', $user, 'success', (string)$user['role'], $role);
            set_flash('success', 'User updated.');
            $return = 'admin/user';
            $returnParams = ['id' => $userId];
        } elseif ($action === 'deactivate') {
            $result = admin_user_set_status($pdo, $userId, 'inactive', true);
            if ($result['old'] !== 'active') {
                throw new InvalidArgumentException('Only active users can be deactivated.');
            }
            admin_user_audit($pdo, 'User Deactivated', $result['user'], 'warning', 'active', 'inactive');
            set_flash('success', 'User deactivated and sessions terminated.');
        } elseif ($action === 'activate') {
            $result = admin_user_set_status($pdo, $userId, 'active');
            if ($result['old'] !== 'inactive') {
                throw new InvalidArgumentException('Only inactive users can be activated.');
            }
            admin_user_audit($pdo, 'User Activated', $result['user'], 'success', 'inactive', 'active');
            set_flash('success', 'User activated.');
        } elseif ($action === 'reset_password') {
            $user = admin_user_require($pdo, $userId);
            $password = trim((string)($_POST['password'] ?? ''));
            if ($password === '') {
                $password = admin_generate_temp_password();
            }
            if (strlen($password) < 6) {
                throw new InvalidArgumentException('Password must be at least 6 characters.');
            }
            $st = $pdo->prepare('UPDATE users SET password_hash = :p, must_change_password = 1 WHERE id = :id');
            $st->execute(['p' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
            admin_revoke_all_sessions($pdo, $userId);
            admin_user_audit($pdo, 'Password Reset', $user, 'warning');
            set_flash('success', 'Password reset. Temporary password: ' . $password . ' — user must change on next login.');
        } elseif ($action === 'lock') {
            $result = admin_user_set_status($pdo, $userId, 'locked', true);
            if ($result['old'] !== 'active') {
                throw new InvalidArgumentException('Only active users can be locked.');
            }
            admin_user_audit($pdo, 'User Locked', $result['user'], 'warning', 'active', 'locked');
            set_flash('success', 'User locked and all sessions terminated.');
        } elseif ($action === 'unlock') {
            $result = admin_user_set_status($pdo, $userId, 'active');
            if ($result['old'] !== 'locked') {
                throw new InvalidArgumentException('Only locked users can be unlocked.');
            }
            admin_user_audit($pdo, 'User Unlocked', $result['user'], 'success', 'locked', 'active');
            set_flash('success', 'User unlocked.');
        } elseif ($action === 'force_logout') {
            $user = admin_user_require($pdo, $userId);
            if (admin_normalize_user_status((string)$user['status']) !== 'active') {
                throw new InvalidArgumentException('Force logout is only available for active users.');
            }
            admin_revoke_all_sessions($pdo, $userId);
            admin_user_audit($pdo, 'Force Logout', $user, 'warning');
            set_flash('success', 'All active sessions terminated.');
        } elseif ($action === 'delete') {
            $me = (int)(current_user()['id'] ?? 0);
            if ($userId === $me) {
                throw new InvalidArgumentException('You cannot delete your own account.');
            }
            $target = admin_user_require($pdo, $userId);
            if ((string)$target['role'] === 'Super Admin') {
                $cnt = admin_count($pdo, "SELECT COUNT(*) FROM users WHERE role = 'Super Admin' AND status = 'active'");
                if ($cnt <= 1) {
                    throw new InvalidArgumentException('Cannot delete the only active Super Admin account.');
                }
            }
            admin_revoke_all_sessions($pdo, $userId);
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
            admin_user_audit($pdo, 'Deleted user account', $target, 'danger');
            set_flash('success', 'User deleted.');
            redirect('admin/users');
        } else {
            throw new InvalidArgumentException('Unknown action.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }

    redirect($return, $returnParams);
}

/** @return array<string, int> */
function admin_users_kpis(PDO $pdo): array
{
    admin_users_ensure_schema($pdo);
    require_once __DIR__ . '/admin_security_service.php';
    admin_security_ensure_schema($pdo);
    $departments = admin_table_count($pdo, 'departments');
    if ($departments === 0) {
        $departments = (int)$pdo->query("SELECT COUNT(DISTINCT department) FROM employees WHERE department <> ''")->fetchColumn();
    }
    $online = admin_count(
        $pdo,
        'SELECT COUNT(DISTINCT user_id) FROM erp_user_sessions WHERE revoked_at IS NULL AND last_active >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );

    return [
        'total' => admin_table_count($pdo, 'users'),
        'active' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE status = 'active'"),
        'locked' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE status = 'locked'"),
        'inactive' => admin_count($pdo, "SELECT COUNT(*) FROM users WHERE status = 'inactive'"),
        'online' => $online,
        'departments' => $departments,
    ];
}

/** @return list<array<string, mixed>> */
function admin_users_list(PDO $pdo, array $filters): array
{
    require_once __DIR__ . '/admin_departments_service.php';

    $sql = 'SELECT u.id, u.full_name, u.email, u.role, u.status, u.created_at, u.last_login, e.department
            FROM users u
            LEFT JOIN employees e ON e.user_id = u.id
            WHERE 1=1';
    $params = [];

    if ($filters['q'] !== '') {
        if (ctype_digit($filters['q'])) {
            $sql .= ' AND (u.id = :uid OR u.full_name LIKE :q OR u.email LIKE :q)';
            $params['uid'] = (int)$filters['q'];
            $params['q'] = '%' . $filters['q'] . '%';
        } else {
            $sql .= ' AND (u.full_name LIKE :q OR u.email LIKE :q OR u.role LIKE :q OR u.username LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
    }
    if ($filters['role'] !== '') {
        $sql .= ' AND u.role = :role';
        $params['role'] = $filters['role'];
    }
    if ($filters['status'] !== '') {
        if ($filters['status'] === 'inactive') {
            $sql .= " AND u.status = 'inactive'";
        } else {
            $sql .= ' AND u.status = :status';
            $params['status'] = $filters['status'];
        }
    }
    if ($filters['department'] !== '') {
        $match = admin_department_match_filter($pdo, $filters['department']);
        if ($match !== null && $match['roles'] !== []) {
            $rolePh = [];
            foreach ($match['roles'] as $i => $role) {
                $key = 'dr' . $i;
                $rolePh[] = ':' . $key;
                $params[$key] = $role;
            }
            $sql .= ' AND (u.role IN (' . implode(',', $rolePh) . ') OR e.department LIKE :dept OR e.department = :deptExact)';
            $params['dept'] = '%' . $match['name'] . '%';
            $params['deptExact'] = $match['name'];
        } else {
            $sql .= ' AND (e.department LIKE :dept OR e.department = :deptExact)';
            $params['dept'] = '%' . $filters['department'] . '%';
            $params['deptExact'] = $filters['department'];
        }
    }

    $sql .= ' ORDER BY u.full_name ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $row['department'] = admin_user_effective_department($pdo, (string)$row['role'], $row['department'] ?? null);
    }
    unset($row);

    return $rows;
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
    if (!$row) {
        return null;
    }
    require_once __DIR__ . '/admin_departments_service.php';
    $row['department'] = admin_user_effective_department($pdo, (string)$row['role'], $row['department'] ?? null);

    return $row;
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
    require_once __DIR__ . '/admin_departments_service.php';
    $names = [];
    foreach (admin_departments_list($pdo, true) as $row) {
        $names[] = (string)$row['department_name'];
    }
    sort($names);

    return $names;
}

function admin_user_effective_department(PDO $pdo, string $role, ?string $employeeDept): string
{
    $employeeDept = trim((string)($employeeDept ?? ''));
    if ($employeeDept !== '') {
        return $employeeDept;
    }
    require_once __DIR__ . '/admin_departments_service.php';
    $code = admin_role_to_department_code($role);
    if ($code === null) {
        return '—';
    }

    return admin_department_name_by_code($pdo, $code);
}

function admin_user_status_badge(string $status): array
{
    return match (admin_normalize_user_status($status)) {
        'active' => ['label' => 'Active', 'cls' => 'sa-status--active'],
        'locked' => ['label' => 'Locked', 'cls' => 'sa-status--locked'],
        'inactive' => ['label' => 'Inactive', 'cls' => 'sa-status--inactive'],
        default => ['label' => 'Inactive', 'cls' => 'sa-status--inactive'],
    };
}

/** @return array<string, mixed>|null */
function admin_user_drawer_payload(PDO $pdo, int $id): ?array
{
    $user = admin_user_profile($pdo, $id);
    if (!$user) {
        return null;
    }
    $badge = admin_user_status_badge((string)$user['status']);

    return [
        'id' => (int)$user['id'],
        'full_name' => (string)$user['full_name'],
        'email' => (string)($user['email'] ?? ''),
        'role' => (string)$user['role'],
        'department' => (string)($user['department'] ?? '—'),
        'designation' => (string)($user['designation'] ?? '—'),
        'employee_code' => (string)($user['employee_code'] ?? '—'),
        'last_login' => (string)($user['last_login'] ?? 'Never'),
        'created_at' => substr((string)($user['created_at'] ?? ''), 0, 10),
        'status' => (string)$user['status'],
        'status_label' => $badge['label'],
        'status_cls' => $badge['cls'],
        'permissions' => admin_user_permissions($pdo, (string)$user['role']),
        'recent_activity' => admin_user_recent_actions($pdo, (string)$user['full_name']),
        'edit_url' => route_url('admin/user', ['id' => $id, 'edit' => 1]),
    ];
}

function admin_user_table_actions(int $userId, string $status): string
{
    $profile = route_url('admin/user', ['id' => $userId]);
    $edit = route_url('admin/user', ['id' => $userId, 'edit' => 1]);
    $csrf = csrf_input();
    $status = admin_normalize_user_status($status);
    $formAction = e(admin_user_form_action());

    $html = '<div class="dropdown sa-actions-dropdown">';
    $html .= '<button type="button" class="sa-btn sa-btn--ghost dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">⋮ Actions</button>';
    $html .= '<ul class="dropdown-menu dropdown-menu-end sa-more-menu">';

    if ($status === 'active') {
        $html .= '<li><a class="dropdown-item" href="' . e($profile) . '">View Profile</a></li>';
        $html .= '<li><a class="dropdown-item" href="' . e($edit) . '">Edit User</a></li>';
        $html .= '<li><hr class="dropdown-divider"></li>';
        $html .= '<li><button type="button" class="dropdown-item js-reset-pw" data-user-id="' . $userId . '">Reset Password</button></li>';
        $html .= admin_user_menu_form($csrf, $userId, 'force_logout', 'Force Logout', 'Terminate all active sessions for this user?');
        $html .= admin_user_menu_form($csrf, $userId, 'lock', 'Lock User', 'Lock this user and terminate all sessions?');
        $html .= admin_user_menu_form($csrf, $userId, 'deactivate', 'Deactivate User', 'Deactivate this user account?');
    } elseif ($status === 'locked') {
        $html .= '<li><a class="dropdown-item" href="' . e($profile) . '">View Profile</a></li>';
        $html .= '<li><button type="button" class="dropdown-item js-reset-pw" data-user-id="' . $userId . '">Reset Password</button></li>';
        $html .= admin_user_menu_form($csrf, $userId, 'unlock', 'Unlock User');
    } elseif ($status === 'inactive') {
        $html .= '<li><a class="dropdown-item" href="' . e($profile) . '">View Profile</a></li>';
        $html .= admin_user_menu_form($csrf, $userId, 'activate', 'Activate User');
    }

    $html .= '</ul></div>';

    return $html;
}

function admin_user_row_actions(int $userId, string $status, string $returnPage = 'admin/users'): string
{
    unset($returnPage);

    return admin_user_table_actions($userId, $status);
}

function admin_user_menu_form(string $csrf, int $userId, string $action, string $label, string $confirm = '', bool $danger = false): string
{
    $confirmAttr = $confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '';

    return '<li><form method="post" action="' . e(admin_user_form_action()) . '" class="admin-confirm-form m-0">'
        . $csrf
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="user_id" value="' . $userId . '">'
        . '<button type="submit" class="dropdown-item' . ($danger ? ' text-danger' : '') . '"' . $confirmAttr . '>' . e($label) . '</button>'
        . '</form></li>';
}

function admin_user_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    }

    return strtoupper(substr(trim($name), 0, 2));
}

function admin_format_profile_date(?string $dt, bool $withTime = false): string
{
    if ($dt === null || $dt === '' || $dt === 'Never') {
        return 'Never';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return $dt;
    }

    return $withTime ? date('j M Y · H:i', $ts) : date('j M Y', $ts);
}

/** @return list<array{key: string, label: string, tone: string}> */
function admin_user_erp_modules(): array
{
    return [
        ['key' => 'Sales', 'label' => 'Sales CRM', 'tone' => 'violet'],
        ['key' => 'Accounts', 'label' => 'Accounts', 'tone' => 'blue'],
        ['key' => 'Inventory', 'label' => 'Inventory', 'tone' => 'teal'],
        ['key' => 'HR', 'label' => 'HR', 'tone' => 'pink'],
        ['key' => 'Production', 'label' => 'Production', 'tone' => 'orange'],
        ['key' => 'Dispatch', 'label' => 'Dispatch', 'tone' => 'indigo'],
        ['key' => 'Quality', 'label' => 'Quality', 'tone' => 'green'],
        ['key' => 'Administration', 'label' => 'Administration', 'tone' => 'slate'],
    ];
}

/** @return list<array{key: string, label: string, access: bool, tone: string}> */
function admin_user_module_access(PDO $pdo, string $role): array
{
    require_once __DIR__ . '/admin_roles_service.php';
    $effective = role_effective_for_access($role, $pdo);
    $matrix = admin_roles_permission_matrix();
    $row = $matrix[$effective] ?? [];
    $isAdmin = in_array($effective, ['Super Admin', 'Admin'], true);
    $out = [];
    foreach (admin_user_erp_modules() as $mod) {
        if ($mod['key'] === 'Administration') {
            $access = $isAdmin;
        } else {
            $access = $isAdmin || !empty($row[$mod['key']]['view']);
        }
        $out[] = [
            'key' => $mod['key'],
            'label' => $mod['label'],
            'access' => $access,
            'tone' => $mod['tone'],
        ];
    }

    return $out;
}

/** @return array<string, mixed> */
function admin_user_security_summary(PDO $pdo, int $userId, array $user): array
{
    require_once __DIR__ . '/admin_security_service.php';
    $status = admin_normalize_user_status((string)($user['status'] ?? ''));

    return [
        'password_last_changed' => admin_user_password_last_changed($pdo, $userId),
        'failed_logins' => admin_user_failed_login_count($pdo, $userId),
        'lock_status' => $status === 'locked' ? 'locked' : 'clear',
        'lock_label' => $status === 'locked' ? 'Locked' : 'Clear',
        'force_password_change' => !empty($user['must_change_password']),
    ];
}

function admin_user_password_last_changed(PDO $pdo, int $userId): string
{
    admin_audit_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT created_at FROM erp_activity_log
         WHERE (entity_type = 'user' AND entity_id = :u)
            OR (module_name = 'User Management' AND detail = :hash AND action_text LIKE '%password%')
         ORDER BY id DESC LIMIT 1"
    );
    $st->execute(['u' => $userId, 'hash' => '#' . $userId]);
    $row = $st->fetchColumn();
    if ($row) {
        return admin_format_profile_date((string)$row, true);
    }

    return 'Not recorded';
}

/** @return list<array{icon: string, tone: string, title: string, subtitle: string, date: string}> */
function admin_user_activity_timeline(PDO $pdo, string $name): array
{
    $actions = admin_user_recent_actions($pdo, $name);
    $items = [];
    foreach ($actions as $a) {
        $module = (string)$a['module'];
        $action = (string)$a['action'];
        $items[] = [
            'icon' => admin_activity_icon_char($module, $action),
            'tone' => admin_activity_tone($module, (string)$a['status']),
            'title' => $action,
            'subtitle' => $module,
            'date' => admin_format_profile_date((string)$a['when'], true),
        ];
    }

    return $items;
}

function admin_activity_icon_char(string $module, string $action): string
{
    $a = strtolower($action);
    if (str_contains($a, 'login') || str_contains($a, 'logout')) {
        return '⏻';
    }
    if (str_contains($a, 'invoice') || str_contains($a, 'order')) {
        return '📄';
    }
    if (str_contains($a, 'payment') || str_contains($a, 'payroll')) {
        return '💳';
    }

    return match (true) {
        str_contains(strtolower($module), 'sales') => '🛒',
        str_contains(strtolower($module), 'account') => '📊',
        str_contains(strtolower($module), 'inventory') => '📦',
        str_contains(strtolower($module), 'hr') => '👥',
        str_contains(strtolower($module), 'production') => '⚙',
        str_contains(strtolower($module), 'dispatch') => '🚚',
        str_contains(strtolower($module), 'quality') => '✓',
        default => '●',
    };
}

function admin_activity_tone(string $module, string $status): string
{
    if ($status === 'warning') {
        return 'yellow';
    }
    if ($status === 'error' || $status === 'danger') {
        return 'red';
    }

    return match (true) {
        str_contains(strtolower($module), 'sales') => 'violet',
        str_contains(strtolower($module), 'account') => 'blue',
        str_contains(strtolower($module), 'inventory') => 'teal',
        str_contains(strtolower($module), 'hr') => 'pink',
        default => 'blue',
    };
}

function admin_user_profile_quick_actions(int $userId, string $status): string
{
    $csrf = csrf_input();
    $status = admin_normalize_user_status($status);
    $html = '<div class="sa-prof-actions">';

    if ($status === 'active') {
        $html .= '<button type="button" class="sa-prof-action sa-prof-action--primary" data-bs-toggle="modal" data-bs-target="#editUserModal">Edit User</button>';
        $html .= '<button type="button" class="sa-prof-action js-reset-pw" data-user-id="' . $userId . '">Reset Password</button>';
        $html .= admin_prof_inline_form($csrf, $userId, 'lock', 'Lock User', 'Lock account and terminate all sessions?', 'danger');
        $html .= admin_prof_inline_form($csrf, $userId, 'deactivate', 'Deactivate User', 'Deactivate this user account?', 'warn');
        $html .= admin_prof_inline_form($csrf, $userId, 'force_logout', 'Force Logout', 'Terminate all active sessions?', 'neutral');
    } elseif ($status === 'locked') {
        $html .= '<button type="button" class="sa-prof-action js-reset-pw" data-user-id="' . $userId . '">Reset Password</button>';
        $html .= admin_prof_inline_form($csrf, $userId, 'unlock', 'Unlock User', '', 'success');
    } elseif ($status === 'inactive') {
        $html .= admin_prof_inline_form($csrf, $userId, 'activate', 'Activate User', '', 'success');
    }

    $html .= '</div>';

    return $html;
}

function admin_prof_inline_form(string $csrf, int $userId, string $action, string $label, string $confirm = '', string $variant = 'neutral'): string
{
    $confirmAttr = $confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '';
    $cls = 'sa-prof-action sa-prof-action--' . $variant;

    return '<form method="post" action="' . e(admin_user_form_action()) . '" class="admin-confirm-form d-inline">'
        . $csrf
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="user_id" value="' . $userId . '">'
        . '<input type="hidden" name="return" value="admin/user">'
        . '<button type="submit" class="' . $cls . '"' . $confirmAttr . '>' . e($label) . '</button>'
        . '</form>';
}
