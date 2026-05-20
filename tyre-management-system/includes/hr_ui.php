<?php
declare(strict_types=1);

/**
 * @deprecated Use includes/erp_ui.php — HR-specific extras only.
 */
require_once __DIR__ . '/erp_ui.php';

function hr_ui_pages(): array
{
    return [
        'hr/dashboard',
        'attendance/list',
        'leave/list',
        'payroll/list',
        'hr/payroll-settings',
        'employees/list',
        'employees/create',
        'reports/hr',
    ];
}

function hr_ui_role_ok(): bool
{
    $role = (string)(current_user()['role'] ?? '');

    return in_array($role, ['Super Admin', 'HR Manager', 'Admin'], true);
}

function hr_ui_is_active_page(): bool
{
    return in_array((string)($_GET['page'] ?? ''), hr_ui_pages(), true);
}

function hr_ui_enqueue(): void
{
    erp_ui_enqueue();
}

function hr_ui_flash(): void
{
    erp_ui_flash();
}
