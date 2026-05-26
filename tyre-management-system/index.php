<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/sales_auth.php';
require_once __DIR__ . '/includes/accounts_auth.php';
require_once __DIR__ . '/config/app.php';

require_auth();
$user = current_user();

if (!isset($_GET['page'])) {
    $homeFile = role_home_file((string)($user['role'] ?? ''));
    if ($homeFile !== 'login.php') {
        header('Location: ' . $homeFile);
        exit;
    }
    header('Location: login.php');
    exit;
}

$routes = require __DIR__ . '/routes/web.php';
$defaultPage = role_home_page((string)((current_user()['role'] ?? '')));
$page = $_GET['page'] ?? $defaultPage;
$path = $routes[$page] ?? null;

if (!$path) {
    http_response_code(404);
    echo 'Route not found';
    exit;
}

sales_enforce_department_access($page);
accounts_enforce_department_access($page);

if (!can_access_page($page, $user)) {
    $target = role_home_page((string)($user['role'] ?? ''));
    if ($target === $page) {
        header('Location: 403.php');
        exit;
    }
    if (str_contains($target, '.php')) {
        header('Location: ' . $target);
    } else {
        header('Location: ' . route_url($target));
    }
    exit;
}

if (str_starts_with((string)$page, 'api/')) {
    require __DIR__ . '/' . $path;
    exit;
}

if ($page === 'employees/credential-slip' || $page === 'payroll/payslip' || $page === 'dispatch/slip'
    || $page === 'sales/invoice-print' || $page === 'sales/payment-receipt' || $page === 'sales/order-print'
    || $page === 'inventory/purchase-print' || str_starts_with((string)$page, 'inventory/purchase-print')) {
    require __DIR__ . '/' . $path;
    exit;
}

// Inventory: redirects, exports, AJAX (no HTML shell)
if (in_array($page, ['inventory/inward', 'inventory/usage', 'inventory/index'], true)) {
    require __DIR__ . '/' . $path;
    exit;
}
if ($page === 'inventory/purchase-history' && (isset($_GET['ajax']) || isset($_GET['export']))) {
    require __DIR__ . '/modules/inventory/purchase_history.php';
    exit;
}
if ($page === 'inventory/purchase-payments' && isset($_GET['export'])) {
    require __DIR__ . '/modules/inventory/purchase_payments.php';
    exit;
}
if ($page === 'inventory/supplier-ledger' && isset($_GET['export'])) {
    require __DIR__ . '/modules/inventory/supplier_ledger.php';
    exit;
}
if ($page === 'inventory/materials' && isset($_GET['export'])) {
    require __DIR__ . '/modules/inventory/materials.php';
    exit;
}

if ($page === 'sales/dispatch' && isset($_GET['export'])) {
    require __DIR__ . '/modules/sales/dispatch.php';
    exit;
}

if ($page === 'sales/payments' && isset($_GET['export'])) {
    require __DIR__ . '/modules/sales/payments.php';
    exit;
}

if ($page === 'sales/invoices' && isset($_GET['export'])) {
    require __DIR__ . '/modules/sales/invoices.php';
    exit;
}

if ($page === 'sales/reports' && isset($_GET['export'])) {
    require __DIR__ . '/modules/sales/reports.php';
    exit;
}

if (is_logged_in() && (int)($_SESSION['must_change_password'] ?? 0) === 1) {
    if ($page !== 'employee/change-password') {
        header('Location: ' . route_url('employee/change-password'));
        exit;
    }
}

// POST handlers that redirect (PRG) must run before any HTML output; otherwise header() fails.
$postRedirectPaths = [
    'modules/hr/attendance/index.php',
    'modules/hr/leave/index.php',
    'modules/hr/payroll/index.php',
    'modules/hr/payroll_settings/index.php',
    'modules/employees/index.php',
    'modules/employees/create.php',
    'modules/employees/credential_reveal.php',
    'modules/employee/attendance.php',
    'modules/employee/dashboard.php',
    'modules/machines/index.php',
    'modules/machines/assignments.php',
    'modules/dispatch/new.php',
    'modules/dispatch/history.php',
    'modules/dispatch/customers.php',
    'modules/dispatch/drivers.php',
    'modules/dispatch/transport.php',
    'modules/dispatch/logistics.php',
    'modules/suppliers/index.php',
    'modules/settings/index.php',
    'modules/inventory/materials.php',
    'modules/inventory/add_stock.php',
    'modules/inventory/use_stock.php',
    'modules/inventory/suppliers.php',
    'modules/inventory/adjust_stock.php',
    'modules/inventory/purchase_history.php',
    'modules/inventory/purchase_edit.php',
    'modules/quality/pending.php',
    'modules/quality/inspect.php',
    'modules/raw_materials/index.php',
    'modules/sales/customers.php',
    'modules/sales/order.php',
    'modules/sales/payments.php',
    'modules/accounts/expenses.php',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($path, $postRedirectPaths, true)) {
    require __DIR__ . '/' . $path;
    exit;
}

if ($page === 'reports/hr' && isset($_GET['export'])) {
    require __DIR__ . '/modules/reports/hr.php';
    exit;
}

if ($page === 'reports/production' && isset($_GET['export'])) {
    require __DIR__ . '/modules/reports/production.php';
    exit;
}

if ($page === 'machines/inventory' && isset($_GET['export'])) {
    require __DIR__ . '/modules/machines/inventory.php';
    exit;
}

if ($page === 'reports/inventory' && isset($_GET['export'])) {
    require __DIR__ . '/modules/reports/inventory.php';
    exit;
}

if ($page === 'reports/dispatch' && isset($_GET['export'])) {
    require __DIR__ . '/modules/reports/dispatch.php';
    exit;
}

if ($page === 'quality/reports' && isset($_GET['export'])) {
    require __DIR__ . '/modules/quality/reports.php';
    exit;
}

if ($page === 'dispatch/history' && isset($_GET['export'])) {
    require __DIR__ . '/modules/dispatch/history.php';
    exit;
}

if ($page === 'sales/invoices' && isset($_GET['export'])) {
    require __DIR__ . '/modules/sales/invoices.php';
    exit;
}

if ($page === 'employee/export') {
    require __DIR__ . '/' . $path;
    exit;
}

if ($page === 'attendance/list' && isset($_GET['export'])) {
    require __DIR__ . '/modules/hr/attendance/index.php';
    exit;
}

if ($page === 'employees/list' && (isset($_GET['export']) || isset($_GET['inc_export']) || isset($_GET['profile_export']))) {
    require __DIR__ . '/modules/employees/index.php';
    exit;
}

if ($page === 'production/qc') {
    header('Location: ' . route_url('production/dashboard'));
    exit;
}

if (str_starts_with((string)$page, 'employee/')) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$salesShell = is_sales_department_route($page);
$accountsShell = is_accounts_department_route($page);
require __DIR__ . '/includes/header.php';
if ($salesShell) {
    require __DIR__ . '/includes/sales_navbar.php';
} elseif ($accountsShell) {
    require __DIR__ . '/includes/navbar.php';
} else {
    require __DIR__ . '/includes/navbar.php';
}
$flash = get_flash();
?>
<div class="container-fluid<?= $salesShell ? ' sales-shell' : ($accountsShell ? ' accounts-shell' : '') ?>">
    <div class="row">
        <?php if ($salesShell) {
            require __DIR__ . '/includes/sales_sidebar.php';
        } elseif ($accountsShell) {
            require __DIR__ . '/includes/accounts_sidebar.php';
        } else {
            require __DIR__ . '/includes/sidebar.php';
        } ?>
        <main class="col-lg-10 col-md-9 p-4 offset-content col-main erp-layout<?= $salesShell ? ' sales-layout' : ($accountsShell ? ' accounts-layout' : '') ?>">
            <div class="<?= e(erp_ui_page_class()) ?> module-shell<?= $salesShell ? ' sales-module-shell' : ($accountsShell ? ' accounts-module-shell' : '') ?>">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> erp-page__flash alert-dismissible fade show py-2 mb-2" role="alert">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php require __DIR__ . '/' . $path; ?>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

