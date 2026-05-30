<?php

declare(strict_types=1);



require_once __DIR__ . '/functions.php';

require_once __DIR__ . '/admin_control_center.php';

require_once __DIR__ . '/admin_audit_service.php';

require_once __DIR__ . '/department_hierarchy.php';

const ADMIN_BASE_ROLES = [
    'Super Admin', 'Admin', 'HR Manager', 'Accounts Manager', 'Sales Manager',
    'Inventory Manager', 'Production Manager', 'Dispatch Manager', 'Quality Manager', 'Employee',
];

function admin_roles_ensure_schema(PDO $pdo): void

{

    $pdo->exec("CREATE TABLE IF NOT EXISTS erp_custom_roles (

        id INT AUTO_INCREMENT PRIMARY KEY,

        role_name VARCHAR(80) NOT NULL,

        base_role VARCHAR(50) NOT NULL,

        permissions_json TEXT NULL,

        status VARCHAR(20) NOT NULL DEFAULT 'active',

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        UNIQUE KEY uq_custom_role (role_name)

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

}



/** @return array<string, string> role_name => base_role */

function admin_custom_roles_map(PDO $pdo): array

{

    static $cache = null;

    if ($cache !== null) {

        return $cache;

    }

    admin_roles_ensure_schema($pdo);

    try {

        $rows = $pdo->query('SELECT role_name, base_role FROM erp_custom_roles WHERE status = "active"')->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $cache = array_map('strval', $rows);

    } catch (Throwable) {

        $cache = [];

    }



    return $cache;

}



function role_effective_for_access(string $role, ?PDO $pdo = null): string

{

    $role = normalize_role_name($role);

    if (in_array($role, ['Super Admin', 'Admin'], true)) {

        return $role;

    }

    try {

        if ($pdo === null) {
            require_once __DIR__ . '/../config/db.php';
            $pdo = Database::connection();
        }

        $map = admin_custom_roles_map($pdo);



        return $map[$role] ?? $role;

    } catch (Throwable) {

        return $role;

    }

}



/** @return list<string> */

function admin_custom_role_names(PDO $pdo): array

{

    admin_roles_ensure_schema($pdo);



    return array_map('strval', $pdo->query('SELECT role_name FROM erp_custom_roles WHERE status = "active" ORDER BY role_name')->fetchAll(PDO::FETCH_COLUMN) ?: []);

}



function admin_roles_handle_post(PDO $pdo): void

{

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_can_access()) {

        return;

    }

    verify_csrf();

    admin_roles_ensure_schema($pdo);

    $action = (string)($_POST['action'] ?? '');



    try {

        if ($action === 'create_role') {

            $name = trim((string)($_POST['role_name'] ?? ''));

            $base = trim((string)($_POST['base_role'] ?? ''));

            if ($name === '' || $base === '') {

                throw new InvalidArgumentException('Role name and base role are required.');

            }

            if (!in_array($base, array_merge(ACC_ADMIN_ROLES, ADMIN_BASE_ROLES), true)) {

                throw new InvalidArgumentException('Invalid base role.');

            }

            $matrix = admin_roles_permission_matrix();

            $perms = json_encode($matrix[$base] ?? [], JSON_THROW_ON_ERROR);

            $st = $pdo->prepare('INSERT INTO erp_custom_roles (role_name, base_role, permissions_json) VALUES (:n, :b, :p)');

            $st->execute(['n' => $name, 'b' => $base, 'p' => $perms]);

            admin_audit_log($pdo, 'Created custom role ' . $name, 'Role Management', 'success', 'Base: ' . $base);

            set_flash('success', 'Custom role created.');

        } elseif ($action === 'clone_role') {

            $source = trim((string)($_POST['source_role'] ?? ''));

            $name = trim((string)($_POST['new_role_name'] ?? ''));

            if ($source === '' || $name === '') {

                throw new InvalidArgumentException('Source and new role name required.');

            }

            $base = role_effective_for_access($source, $pdo);

            $matrix = admin_roles_permission_matrix();

            $perms = json_encode($matrix[$base] ?? $matrix[$source] ?? [], JSON_THROW_ON_ERROR);

            $st = $pdo->prepare('INSERT INTO erp_custom_roles (role_name, base_role, permissions_json) VALUES (:n, :b, :p)');

            $st->execute(['n' => $name, 'b' => $base, 'p' => $perms]);

            admin_audit_log($pdo, 'Cloned role to ' . $name, 'Role Management', 'success', 'From: ' . $source);

            set_flash('success', 'Role cloned successfully.');

        } elseif ($action === 'deactivate_role') {

            $name = trim((string)($_POST['role_name'] ?? ''));

            $st = $pdo->prepare('UPDATE erp_custom_roles SET status = "inactive" WHERE role_name = :n');

            $st->execute(['n' => $name]);

            admin_audit_log($pdo, 'Removed custom role access', 'Role Management', 'warning', $name);

            set_flash('success', 'Custom role deactivated.');

        } else {

            throw new InvalidArgumentException('Unknown action.');

        }

    } catch (Throwable $e) {

        set_flash('danger', $e->getMessage());

    }

    redirect('admin/roles');

}



/** @return array<string, array<string, bool>> */

function admin_roles_permission_matrix(): array

{

    $roles = array_merge(['Super Admin'], ACC_ADMIN_ROLES);

    $rules = page_allowed_roles();

    $modules = [

        'HR' => ['employees/list', 'attendance/list', 'leave/list', 'payroll/list', 'reports/hr'],

        'Accounts' => ['accounts/dashboard', 'accounts/receivables', 'accounts/payables', 'accounts/expenses', 'accounts/reports'],

        'Sales' => ['sales/dashboard', 'sales/customers', 'sales/orders', 'sales/invoices', 'sales/reports'],

        'Inventory' => ['inventory/dashboard', 'inventory/materials', 'inventory/purchase-history', 'reports/inventory'],

        'Production' => ['production/dashboard', 'production/mixing', 'machines/dashboard', 'reports/production'],

        'Dispatch' => ['dispatch/dashboard', 'dispatch/new', 'dispatch/history', 'reports/dispatch'],

        'Quality' => ['quality/dashboard', 'quality/pending', 'quality/defects', 'quality/reports'],

        'Administration' => ['admin/dashboard', 'admin/users', 'admin/settings'],

    ];



    $matrix = [];

    foreach ($roles as $role) {

        $matrix[$role] = [];

        foreach ($modules as $mod => $pages) {

            $canView = false;

            foreach ($pages as $page) {

                $allowed = $rules[$page] ?? [];

                if (in_array($role, $allowed, true) || in_array($role, ['Super Admin', 'Admin'], true)) {

                    $canView = true;

                    break;

                }

            }

            $matrix[$role][$mod] = [

                'view' => $canView || $role === 'Super Admin',

                'create' => $canView && !in_array($role, ['Employee'], true),

                'edit' => $canView && !in_array($role, ['Employee'], true),

                'delete' => in_array($role, ['Super Admin', 'Admin', 'HR Manager', 'Inventory Manager'], true),

                'export' => $canView,

            ];

        }

    }



    return $matrix;

}



/** @return list<array<string, mixed>> */

function admin_roles_cards(PDO $pdo): array

{

    $matrix = admin_roles_permission_matrix();

    $custom = admin_custom_role_names($pdo);

    $allRoles = array_merge(array_keys($matrix), $custom);

    $allRoles = array_values(array_unique($allRoles));

    $cards = [];

    foreach ($allRoles as $role) {

        $mods = $matrix[$role] ?? $matrix[role_effective_for_access($role, $pdo)] ?? [];

        $usersAssigned = admin_count($pdo, 'SELECT COUNT(*) FROM users WHERE role = ' . $pdo->quote($role));

        $accessible = 0;

        $permCount = 0;

        foreach ($mods as $flags) {

            if (!empty($flags['view'])) {

                ++$accessible;

            }

            foreach ($flags as $ok) {

                if ($ok) {

                    ++$permCount;

                }

            }

        }

        $cards[] = [

            'role' => $role,

            'users' => $usersAssigned,

            'modules' => $accessible,

            'permissions' => $permCount,

            'custom' => in_array($role, $custom, true),

            'url' => route_url('admin/roles', ['role' => $role]),

        ];

    }



    return $cards;

}



/** @return array{role: string, modules: array, users: int, custom: bool, base_role: string}|null */

function admin_role_detail(PDO $pdo, string $role): ?array

{

    $matrix = admin_roles_permission_matrix();

    $customMap = admin_custom_roles_map($pdo);

    $base = $customMap[$role] ?? $role;

    $mods = $matrix[$role] ?? $matrix[$base] ?? null;

    if ($mods === null && !isset($customMap[$role])) {

        return null;

    }



    return [

        'role' => $role,

        'modules' => $mods ?? [],

        'users' => admin_count($pdo, 'SELECT COUNT(*) FROM users WHERE role = ' . $pdo->quote($role)),

        'custom' => isset($customMap[$role]),

        'base_role' => $base,

    ];

}


