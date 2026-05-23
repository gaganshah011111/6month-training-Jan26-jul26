<?php
declare(strict_types=1);

if (!function_exists('ensure_session_started')) {
    function ensure_session_started(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $page): void
{
    if (
        str_contains($page, '.php') ||
        str_starts_with($page, 'http://') ||
        str_starts_with($page, 'https://') ||
        str_starts_with($page, '/')
    ) {
        header('Location: ' . $page);
        exit;
    }

    header('Location: index.php?page=' . urlencode($page));
    exit;
}

function route_url(string $page, array $query = []): string
{
    $params = ['page' => $page];
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $params[(string)$key] = $value;
    }

    return 'index.php?' . http_build_query($params);
}

function current_user(): ?array
{
    ensure_session_started();
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    ensure_session_started();
    return isset($_SESSION['user']) || isset($_SESSION['user_id']);
}

function normalize_role_name(string $role): string
{
    $normalized = strtolower(trim($role));
    return match ($normalized) {
        'super admin', 'super_admin', 'superadmin' => 'Super Admin',
        'admin' => 'Admin',
        'hr manager', 'hr_manager', 'hr' => 'HR Manager',
        'production manager', 'production_manager' => 'Production Manager',
        'inventory manager', 'inventory_manager' => 'Inventory Manager',
        'dispatch manager', 'dispatch_manager' => 'Dispatch Manager',
        'quality manager', 'quality_manager' => 'Quality Manager',
        'employee', 'staff' => 'Employee',
        default => trim($role),
    };
}

function has_role($role): bool
{
    ensure_session_started();
    if (!is_logged_in()) {
        return false;
    }

    $sessionRole = normalize_role_name((string)($_SESSION['role'] ?? ($_SESSION['user']['role'] ?? '')));
    if ($sessionRole === '') {
        return false;
    }

    $roles = is_array($role) ? $role : [$role];
    $roles = array_map(static fn($r) => normalize_role_name((string)$r), $roles);
    return in_array($sessionRole, $roles, true);
}

function format_currency(float $amount): string
{
    return 'INR ' . number_format($amount, 2);
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['_csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = (string)($_POST['_token'] ?? '');
    if (!$token || !hash_equals((string)($_SESSION['_csrf_token'] ?? ''), $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($flash) ? $flash : null;
}

function post_string(string $key, int $max = 255): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    return mb_substr($value, 0, $max);
}

function post_float(string $key): float
{
    return (float)($_POST[$key] ?? 0);
}

function post_int(string $key): int
{
    return (int)($_POST[$key] ?? 0);
}

function role_home_page(string $role): string
{
    $role = normalize_role_name($role);
    return match ($role) {
        'Super Admin' => 'super/dashboard',
        'Admin' => 'super/dashboard',
        'HR Manager' => 'hr/dashboard',
        'Production Manager' => 'production/dashboard',
        'Inventory Manager' => 'inventory/dashboard',
        'Dispatch Manager' => 'dispatch/dashboard',
        'Quality Manager' => 'quality/dashboard',
        'Employee' => 'employee/dashboard',
        default => 'login.php',
    };
}

function role_home_file(string $role): string
{
    $role = normalize_role_name($role);
    return match ($role) {
        'Super Admin', 'Admin' => 'super_dashboard.php',
        'HR Manager' => 'hr_dashboard.php',
        'Production Manager' => 'production_dashboard.php',
        'Inventory Manager' => 'inventory_dashboard.php',
        'Dispatch Manager' => 'dispatch_dashboard.php',
        'Quality Manager' => 'quality_dashboard.php',
        'Employee' => 'employee_dashboard.php',
        default => 'login.php',
    };
}

function page_allowed_roles(): array
{
    return [
        'super/dashboard' => ['Super Admin', 'Admin'],
        'dashboard' => ['Super Admin', 'Admin'],
        'users/index' => ['Super Admin', 'Admin'],
        'settings/profile' => ['Super Admin', 'Admin'],

        'hr/dashboard' => ['HR Manager', 'Super Admin', 'Admin'],
        'employees/list' => ['HR Manager', 'Super Admin', 'Admin'],
        'employees/create' => ['HR Manager', 'Super Admin', 'Admin'],
        'employees/credentials' => ['HR Manager', 'Super Admin', 'Admin'],
        'employees/credential-slip' => ['HR Manager', 'Super Admin', 'Admin'],
        'api/departments' => ['HR Manager', 'Super Admin', 'Admin'],
        'api/payroll-settings' => ['HR Manager', 'Super Admin', 'Admin'],
        'attendance/list' => ['HR Manager', 'Super Admin', 'Admin'],
        'leave/list' => ['HR Manager', 'Super Admin', 'Admin'],
        'payroll/list' => ['HR Manager', 'Super Admin', 'Admin'],
        'payroll/payslip' => ['HR Manager', 'Super Admin', 'Admin'],
        'api/payroll-calculate' => ['HR Manager', 'Super Admin', 'Admin'],
        'api/payroll-test-preview' => ['HR Manager', 'Super Admin', 'Admin'],
        'api/hr-notifications' => ['HR Manager', 'Super Admin', 'Admin'],
        'hr/payroll-settings' => ['HR Manager', 'Super Admin', 'Admin'],
        'reports/hr' => ['HR Manager', 'Super Admin', 'Admin'],

        'production/dashboard' => ['Production Manager', 'Super Admin', 'Admin'],
        'raw-materials/list' => ['Production Manager', 'Super Admin', 'Admin'],
        'suppliers/list' => ['Production Manager', 'Super Admin', 'Admin'],
        'production/list' => ['Production Manager', 'Super Admin', 'Admin'],
        'production/mixing' => ['Production Manager', 'Super Admin', 'Admin'],
        'production/building' => ['Production Manager', 'Super Admin', 'Admin'],
        'production/curing' => ['Production Manager', 'Super Admin', 'Admin'],
        'production/entry' => ['Production Manager', 'Super Admin', 'Admin'],
        'machines/list' => ['Production Manager', 'Super Admin', 'Admin'],
        'machines/dashboard' => ['Production Manager', 'Super Admin', 'Admin'],
        'machines/assignments' => ['Production Manager', 'Super Admin', 'Admin'],
        'machines/inventory' => ['Production Manager', 'Super Admin', 'Admin'],
        'machines/history' => ['Production Manager', 'Super Admin', 'Admin'],
        'api/machine-operator' => ['Production Manager', 'Super Admin', 'Admin'],
        'reports/production' => ['Production Manager', 'Super Admin', 'Admin'],

        'quality/dashboard' => ['Quality Manager', 'Super Admin', 'Admin'],
        'quality/list' => ['Quality Manager', 'Super Admin', 'Admin'],
        'quality/pending' => ['Quality Manager', 'Super Admin', 'Admin'],
        'quality/inspect' => ['Quality Manager', 'Super Admin', 'Admin'],
        'quality/defects' => ['Quality Manager', 'Super Admin', 'Admin'],
        'quality/reports' => ['Quality Manager', 'Super Admin', 'Admin'],
        'quality/rework' => ['Quality Manager', 'Super Admin', 'Admin'],
        'reports/quality' => ['Quality Manager', 'Super Admin', 'Admin'],

        'inventory/dashboard' => ['Inventory Manager', 'Super Admin', 'Admin'],
        'inventory/list' => ['Inventory Manager', 'Super Admin', 'Admin'],
        'inventory/materials' => ['Inventory Manager', 'Super Admin', 'Admin'],
        'inventory/add-stock' => ['Inventory Manager', 'Super Admin', 'Admin'],
        'inventory/use-stock' => ['Inventory Manager', 'Super Admin', 'Admin'],
        'inventory/adjust-stock' => ['Inventory Manager', 'Super Admin', 'Admin'],
        'api/material-history' => ['Inventory Manager', 'Super Admin', 'Admin', 'Production Manager'],
        'inventory/inward' => ['Inventory Manager', 'Super Admin', 'Admin'],
        'inventory/usage' => ['Inventory Manager', 'Super Admin', 'Admin'],
        'inventory/suppliers' => ['Inventory Manager', 'Super Admin', 'Admin'],
        'reports/inventory' => ['Inventory Manager', 'Super Admin', 'Admin'],

        'dispatch/dashboard' => ['Dispatch Manager', 'Super Admin', 'Admin'],
        'dispatch/list' => ['Dispatch Manager', 'Super Admin', 'Admin'],
        'dispatch/new' => ['Dispatch Manager', 'Super Admin', 'Admin'],
        'dispatch/history' => ['Dispatch Manager', 'Super Admin', 'Admin'],
        'dispatch/customers' => ['Dispatch Manager', 'Super Admin', 'Admin'],
        'dispatch/drivers' => ['Dispatch Manager', 'Super Admin', 'Admin'],
        'dispatch/transport' => ['Dispatch Manager', 'Super Admin', 'Admin'],
        'dispatch/logistics' => ['Dispatch Manager', 'Super Admin', 'Admin'],
        'dispatch/slip' => ['Dispatch Manager', 'Super Admin', 'Admin'],
        'api/dispatch-stock' => ['Dispatch Manager', 'Super Admin', 'Admin'],
        'api/dispatch-save' => ['Dispatch Manager', 'Super Admin', 'Admin'],
        'api/dispatch-preview' => ['Dispatch Manager', 'Super Admin', 'Admin'],
        'reports/dispatch' => ['Dispatch Manager', 'Super Admin', 'Admin'],

        'employee/dashboard' => ['Employee'],
        'employee/profile' => ['Employee'],
        'employee/attendance' => ['Employee'],
        'employee/leave' => ['Employee'],
        'employee/salary' => ['Employee'],
        'employee/export' => ['Employee'],
        'employee/change-password' => ['Employee', 'Super Admin', 'Admin', 'HR Manager', 'Production Manager', 'Inventory Manager', 'Dispatch Manager', 'Quality Manager'],
        'api/employee-notifications' => ['Employee'],
    ];
}

function can_access_page(string $page, ?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }

    $role = normalize_role_name((string)($user['role'] ?? ''));
    $rules = page_allowed_roles();
    if (!isset($rules[$page])) {
        return in_array($role, ['Super Admin', 'Admin'], true);
    }

    return in_array($role, $rules[$page], true);
}

/** Standard ERP text collation (must match DB migration). */
function erp_collation(): string
{
    return 'utf8mb4_general_ci';
}

/** Apply ERP collation to a SQL column/expression for cross-table text ops. */
function erp_collate(string $sqlExpr): string
{
    return $sqlExpr . ' COLLATE ' . erp_collation();
}

/** COALESCE of department master name and employee free-text department. */
function erp_dept_label_sql(string $deptAlias = 'd', string $empAlias = 'e'): string
{
    return 'COALESCE(' . erp_collate("{$deptAlias}.department_name") . ', ' . erp_collate("{$empAlias}.department") . ')';
}

/** COALESCE of designation master name and employee free-text designation. */
function erp_desig_label_sql(string $desAlias = 'des', string $empAlias = 'e'): string
{
    return 'COALESCE(' . erp_collate("{$desAlias}.designation_name") . ', ' . erp_collate("{$empAlias}.designation") . ')';
}
