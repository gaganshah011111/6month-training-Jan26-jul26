<?php
declare(strict_types=1);
$links = [
    'dashboard' => 'Dashboard',
    'users/index' => 'Users',
    'employees/index' => 'Employees',
    'hr/attendance/index' => 'Attendance',
    'hr/payroll/index' => 'Payroll',
    'hr/leave/index' => 'Leave',
    'hr/employee_records/index' => 'Employee Records',
    'raw_materials/index' => 'Raw Materials',
    'suppliers/index' => 'Suppliers',
    'production/index' => 'Production',
    'machines/index' => 'Machines',
    'quality/index' => 'Quality',
    'inventory/index' => 'Inventory',
    'dispatch/index' => 'Dispatch',
    'reports/index' => 'Reports',
    'settings/index' => 'Settings',
];

$user = current_user();
$isEmployee = $user && ($user['role'] ?? '') === 'Employee';
if ($isEmployee) {
    $links = [
        'employee/dashboard' => 'Dashboard',
        'employee/profile' => 'My Profile',
        'employee/attendance' => 'Attendance',
        'employee/leave' => 'Leave',
        'employee/salary' => 'Salary',
        'employee/change-password' => 'Change Password',
    ];
}
?>
<div class="col-md-2 bg-white border-end min-vh-100 p-2">
    <?php foreach ($links as $key => $label): ?>
        <a class="d-block p-2 text-decoration-none text-dark rounded hover-bg" href="<?= e(route_url($key)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

