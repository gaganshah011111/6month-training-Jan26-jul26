<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
$page = $_GET['page'] ?? 'dashboard';
$user = current_user();
$role = (string)($user['role'] ?? '');
?>
<aside class="col-lg-2 col-md-3 bg-white border-end min-vh-100 p-3 sidebar-fixed">
    <?php
    $menu = match ($role) {
        'Super Admin' => [
            'System' => ['super/dashboard' => 'Super Dashboard', 'users/index' => 'User Management', 'settings/profile' => 'System Settings', 'reports/hr' => 'Global Reports'],
        ],
        'HR Manager' => [
            'HR Management' => ['hr/dashboard' => 'Dashboard', 'employees/list' => 'Employees', 'attendance/list' => 'Attendance', 'leave/list' => 'Leave', 'payroll/list' => 'Payroll', 'reports/hr' => 'HR Reports'],
        ],
        'Production Manager' => [
            'Production' => ['production/dashboard' => 'Dashboard', 'raw-materials/list' => 'Raw Materials', 'suppliers/list' => 'Suppliers', 'production/list' => 'Production', 'machines/list' => 'Machines', 'reports/production' => 'Production Reports'],
        ],
        'Inventory Manager' => [
            'Inventory' => ['inventory/dashboard' => 'Dashboard', 'inventory/list' => 'Inventory', 'reports/inventory' => 'Inventory Reports'],
        ],
        'Dispatch Manager' => [
            'Dispatch' => ['dispatch/dashboard' => 'Dashboard', 'dispatch/list' => 'Orders & Dispatch'],
        ],
        'Quality Manager' => [
            'Quality' => ['quality/dashboard' => 'Dashboard', 'quality/list' => 'Inspection & Defects'],
        ],
        default => [
            'Employee' => ['employee/dashboard' => 'Dashboard', 'employee/profile' => 'My Profile', 'employee/attendance' => 'Attendance', 'employee/leave' => 'Leave', 'employee/salary' => 'Salary', 'employee/change-password' => 'Change Password'],
        ],
    };
    foreach ($menu as $section => $links):
    ?>
        <div class="text-uppercase text-muted fw-semibold small mt-2 mb-1"><?= e($section) ?></div>
        <?php foreach ($links as $key => $label): ?>
            <a class="d-block p-2 text-decoration-none text-dark rounded hover-bg mb-1 <?= $page === $key ? 'active-nav' : '' ?>" href="<?= e(route_url($key)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <a class="d-block p-2 text-decoration-none text-dark rounded hover-bg mb-1 mt-3" href="logout.php">Logout</a>
</aside>

