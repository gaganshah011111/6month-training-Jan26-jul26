<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
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

if ($page === 'employees/credential-slip' || $page === 'payroll/payslip') {
    require __DIR__ . '/' . $path;
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
    'modules/production/index.php',
    'modules/machines/index.php',
    'modules/dispatch/index.php',
    'modules/suppliers/index.php',
    'modules/settings/index.php',
    'modules/inventory/index.php',
    'modules/quality/index.php',
    'modules/raw_materials/index.php',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($path, $postRedirectPaths, true)) {
    require __DIR__ . '/' . $path;
    exit;
}

if ($page === 'reports/hr' && isset($_GET['export'])) {
    require __DIR__ . '/modules/reports/hr.php';
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

if (str_starts_with((string)$page, 'employee/')) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
$flash = get_flash();
?>
<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <main class="col-lg-10 col-md-9 p-4 offset-content col-main erp-layout">
            <div class="<?= e(erp_ui_page_class()) ?> module-shell">
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

