<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
$page = $_GET['page'] ?? 'dashboard';
$user = current_user();
$role = (string)($user['role'] ?? '');
$iconMap = [
    'Super Dashboard' => 'bi-speedometer2',
    'ERP Control Center' => 'bi-speedometer2',
    'User Management' => 'bi-people',
    'Roles & Permissions' => 'bi-shield-lock',
    'Department Management' => 'bi-diagram-3',
    'System Settings' => 'bi-gear',
    'Activity Logs' => 'bi-journal-text',
    'Audit Center' => 'bi-journal-text',
    'Employees' => 'bi-person-badge',
    'Sales & CRM' => 'bi-graph-up',
    'Purchase & Suppliers' => 'bi-truck',
    'Finance' => 'bi-bank2',
    'Global Reports' => 'bi-graph-up',
    'System Health' => 'bi-heart-pulse',
    'Backup & Restore' => 'bi-cloud-arrow-down',
    'Dashboard' => 'bi-house-door',
    'Employees' => 'bi-person-badge',
    'Attendance' => 'bi-calendar-check',
    'Leave' => 'bi-calendar-minus',
    'Payroll' => 'bi-cash-coin',
    'Payroll Settings' => 'bi-sliders',
    'HR Reports' => 'bi-clipboard-data',
    'Raw Materials' => 'bi-box-seam',
    'Suppliers' => 'bi-truck',
    'Production' => 'bi-building-gear',
    'Production Entry' => 'bi-clipboard-plus',
    'Machines' => 'bi-cpu',
    'Production Reports' => 'bi-bar-chart',
    'Procurement & Inventory' => 'bi-boxes',
    'Reports' => 'bi-archive',
    'Customer Ledger' => 'bi-journal-text',
    'Supplier Ledger' => 'bi-receipt',
    'Receivables' => 'bi-hourglass-split',
    'Payables' => 'bi-journal-arrow-down',
    'Salary Payments' => 'bi-cash-coin',
    'Expenses' => 'bi-wallet2',
    'Cash & Bank' => 'bi-bank',
    'Transactions History' => 'bi-clock-history',
    'Accounts & Finance' => 'bi-bank2',
    'Orders & Dispatch' => 'bi-send',
    'New Dispatch' => 'bi-truck',
    'Dispatch History' => 'bi-clock-history',
    'Customers' => 'bi-building',
    'Orders' => 'bi-card-checklist',
    'Sales Orders' => 'bi-card-checklist',
    'Invoices' => 'bi-receipt',
    'Dispatch' => 'bi-send',
    'Dispatch Tracking' => 'bi-send',
    'CRM Reports' => 'bi-archive',
    'Dispatch Reports' => 'bi-file-earmark-bar-graph',
    'Logistics' => 'bi-truck',
    'QC Dashboard' => 'bi-speedometer2',
    'Pending Inspections' => 'bi-hourglass-split',
    'Defect Tracking' => 'bi-bug',
    'QC Reports' => 'bi-clipboard-data',
    'Rework / Scrap' => 'bi-arrow-repeat',
    'Inspection & Defects' => 'bi-shield-check',
    'My Profile' => 'bi-person-circle',
    'Salary' => 'bi-receipt-cutoff',
    'Change Password' => 'bi-key',
];
?>
<aside class="col-lg-2 col-md-3 app-sidebar min-vh-100 p-3 sidebar-fixed">
    <?php
    $menu = match ($role) {
        'Super Admin', 'Admin' => [
            'System Administration' => [
                'super/dashboard' => 'ERP Control Center',
                'admin/users' => 'User Management',
                'admin/roles' => 'Roles & Permissions',
                'admin/departments' => 'Department Management',
                'admin/settings' => 'System Settings',
                'admin/activity-logs' => 'Audit Center',
                'admin/system-health' => 'System Health',
                'admin/reports' => 'Global Reports',
                'admin/backup' => 'Backup & Restore',
            ],
            'Oversight' => [
                'admin/employee-oversight' => 'Employees',
                'admin/sales-oversight' => 'Sales & CRM',
                'admin/purchase-oversight' => 'Purchase & Suppliers',
                'admin/finance-oversight' => 'Finance',
            ],
        ],
        'HR Manager' => [
            'HR Management' => ['hr/dashboard' => 'Dashboard', 'employees/list' => 'Employees', 'attendance/list' => 'Attendance', 'leave/list' => 'Leave', 'payroll/list' => 'Payroll', 'hr/payroll-settings' => 'Payroll Settings', 'reports/hr' => 'HR Reports'],
        ],
        'Production Manager' => [
            'Production' => [
                'production/dashboard' => 'Dashboard',
                'production/mixing' => 'Mixing Entry',
                'production/building' => 'Building Entry',
                'production/curing' => 'Curing Entry',
                'machines/dashboard' => 'Machines',
                'reports/production' => 'Reports',
            ],
        ],
        'Inventory Manager' => [
            'Procurement & Inventory' => [
                'inventory/dashboard' => 'Dashboard',
                'inventory/materials' => 'Materials',
                'inventory/add-stock' => 'Purchase Inward',
                'inventory/use-stock' => 'Use Stock',
                'inventory/adjust-stock' => 'Adjust Stock',
                'inventory/suppliers' => 'Suppliers',
                'inventory/purchase-history' => 'Purchase History',
                'reports/inventory' => 'Reports',
            ],
        ],
        'Dispatch Manager' => [
            'Dispatch' => [
                'dispatch/dashboard' => 'Dashboard',
                'dispatch/new' => 'New Dispatch',
                'dispatch/history' => 'Dispatch History',
                'dispatch/customers' => 'Customers',
                'dispatch/logistics' => 'Logistics',
                'reports/dispatch' => 'Dispatch Reports',
            ],
        ],
        'Sales Manager' => [
            'Orders & Dispatch' => [
                'sales/dashboard' => 'Dashboard',
                'sales/customers' => 'Customers',
                'sales/orders' => 'Sales Orders',
                'sales/invoices' => 'Invoices',
                'sales/dispatch' => 'Dispatch Tracking',
                'sales/reports' => 'CRM Reports',
            ],
        ],
        'Quality Manager' => [
            'Quality' => [
                'quality/dashboard' => 'QC Dashboard',
                'quality/pending' => 'Pending Inspections',
                'quality/defects' => 'Defect Tracking',
                'quality/reports' => 'QC Reports',
                'quality/rework' => 'Rework / Scrap',
            ],
        ],
        'Accounts Manager' => [
            'Accounts & Finance' => [
                'accounts/dashboard' => 'Dashboard',
                'accounts/receivables' => 'Receivables',
                'accounts/payables' => 'Payables',
                'accounts/salary-payments' => 'Salary Payments',
                'accounts/ledger' => 'Customer Ledger',
                'accounts/supplier-ledger' => 'Supplier Ledger',
                'accounts/expenses' => 'Expenses',
                'accounts/cashbook' => 'Cash & Bank',
                'accounts/reports' => 'Reports',
                'accounts/transactions-history' => 'Transaction History',
            ],
        ],
        default => [
            'Employee' => ['employee/dashboard' => 'Dashboard', 'employee/profile' => 'My Profile', 'employee/attendance' => 'Attendance', 'employee/leave' => 'Leave', 'employee/salary' => 'Salary', 'employee/change-password' => 'Change Password'],
        ],
    };
    foreach ($menu as $section => $links):
    ?>
        <div class="sidebar-section text-uppercase fw-semibold small mt-2 mb-1"><?= e($section) ?></div>
        <?php foreach ($links as $key => $label): ?>
            <?php $icon = $iconMap[$label] ?? 'bi-circle'; ?>
            <?php
            $isActive = $page === $key;
            if (!$isActive && str_starts_with($key, 'admin/') && str_starts_with($page, 'admin/')) {
                $isActive = $page === $key;
            }
            if (!$isActive && $key === 'super/dashboard' && ($page === 'super/dashboard' || $page === 'admin/dashboard')) {
                $isActive = true;
            }
            if (!$isActive && str_starts_with($key, 'machines/') && str_starts_with($page, 'machines/')) {
                $isActive = true;
            }
            if (!$isActive && $key === 'quality/pending' && str_starts_with($page, 'quality/')) {
                $isActive = true;
            }
            ?>
            <a class="sidebar-link d-flex align-items-center gap-2 p-2 text-decoration-none rounded mb-1 <?= $isActive ? 'active-nav' : '' ?>" href="<?= e(route_url($key)) ?>">
                <i class="bi <?= e($icon) ?>"></i>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <a class="sidebar-link d-flex align-items-center gap-2 p-2 text-decoration-none rounded mb-1 mt-3" href="logout.php">
        <i class="bi bi-box-arrow-right"></i>
        <span>Logout</span>
    </a>
    <div class="sidebar-help-card mt-3">
        <div class="small fw-semibold mb-1"><i class="bi bi-headset me-1"></i>Need Help?</div>
        <div class="small text-muted mb-2">Support team is available.</div>
        <a href="<?= e(route_url('settings/profile')) ?>" class="btn btn-sm btn-outline-light w-100">Contact Support</a>
    </div>
</aside>

