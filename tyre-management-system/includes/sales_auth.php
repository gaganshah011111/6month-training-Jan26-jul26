<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

const SALES_MANAGER_ROLE = 'Sales Manager';
const ACCOUNTS_MANAGER_ROLE = 'Accounts Manager';

/** Routes that belong exclusively to the Sales & CRM department. */
function sales_department_routes(): array
{
    return [
        'sales/dashboard',
        'sales/customers',
        'sales/customer',
        'sales/orders',
        'sales/order',
        'sales/invoices',
        'sales/invoice',
        'sales/invoice-print',
        'sales/payment-receipt',
        'sales/order-print',
        'sales/payments',
        'sales/dispatch',
        'sales/reports',
        'sales/analytics',
        'api/sales-stock',
        'api/sales-order-lines',
    ];
}

/** Read-only sales routes needed by Accounts department. */
function accounts_manager_sales_routes(): array
{
    return [
        'sales/invoices',
        'sales/invoice',
        'sales/invoice-print',
        'sales/payment-receipt',
        'sales/payments',
    ];
}

/** Routes a Sales Manager may use outside sales/* (dispatch execution only). */
function sales_manager_operational_routes(): array
{
    return [
        'api/dispatch-save',
        'api/dispatch-stock',
        'api/dispatch-preview',
        'dispatch/slip',
        'dispatch/history',
        'dispatch/list',
        'dispatch/dashboard',
    ];
}

function is_sales_department_route(string $page): bool
{
    if (str_starts_with($page, 'sales/')) {
        return true;
    }

    return in_array($page, ['api/sales-stock', 'api/sales-order-lines'], true);
}

function is_sales_manager(): bool
{
    return has_role(SALES_MANAGER_ROLE);
}

function require_sales_manager(): void
{
    if (!is_sales_manager()) {
        http_response_code(403);
        if (is_logged_in()) {
            $home = role_home_page((string)(current_user()['role'] ?? ''));
            if ($home !== 'login.php' && !is_sales_department_route($home)) {
                header('Location: ' . route_url($home));
                exit;
            }
        }
        header('Location: ' . route_url('login.php'));
        exit;
    }
}

/**
 * Enforce department isolation at the routing layer (call from index.php).
 */
function sales_enforce_department_access(string $page): void
{
    if (!is_logged_in()) {
        return;
    }

    $role = normalize_role_name((string)(current_user()['role'] ?? ''));

    if (is_sales_department_route($page)) {
        if ($role === ACCOUNTS_MANAGER_ROLE && in_array($page, accounts_manager_sales_routes(), true)) {
            return;
        }
        if ($role !== SALES_MANAGER_ROLE) {
            header('Location: ' . route_url(role_home_page($role)));
            exit;
        }

        return;
    }

    if ($role === SALES_MANAGER_ROLE) {
        if (str_starts_with($page, 'accounts/')) {
            return;
        }
        if (in_array($page, sales_manager_operational_routes(), true)) {
            return;
        }
        header('Location: ' . route_url('sales/dashboard'));
        exit;
    }
}

function sales_log_exception(Throwable $e, string $context): void
{
    error_log('[Sales CRM][' . $context . '] ' . $e->getMessage());
}

function sales_error_alert(string $message = 'Unable to load data. Please try again later.'): string
{
    return '<div class="alert alert-warning border mb-3" role="alert">'
        . '<i class="bi bi-exclamation-triangle me-2"></i>'
        . e($message)
        . '</div>';
}

/** @param callable(): mixed $callback */
function sales_try(callable $callback, mixed $default = null, string $context = 'sales'): mixed
{
    try {
        return $callback();
    } catch (Throwable $e) {
        sales_log_exception($e, $context);

        return $default;
    }
}
