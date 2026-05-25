<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/** Roles that may use Accounts & Finance. */
function accounts_allowed_roles(): array
{
    return ['Super Admin', 'Admin', 'Sales Manager'];
}

function is_accounts_department_route(string $page): bool
{
    return str_starts_with($page, 'accounts/');
}

function can_access_accounts(): bool
{
    $role = normalize_role_name((string)(current_user()['role'] ?? ''));

    return in_array($role, accounts_allowed_roles(), true);
}

function accounts_enforce_department_access(string $page): void
{
    if (!is_logged_in() || !is_accounts_department_route($page)) {
        return;
    }
    if (!can_access_accounts()) {
        header('Location: ' . route_url(role_home_page((string)(current_user()['role'] ?? ''))));
        exit;
    }
}
