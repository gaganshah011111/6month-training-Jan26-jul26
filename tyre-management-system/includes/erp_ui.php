<?php
declare(strict_types=1);

/**
 * Ralson ERP — unified page shell (HR Command Center visual language).
 * Loaded on every authenticated module page via includes/header.php.
 * New modules: put content inside the auto wrapper in index.php; use .erp-page__* helpers when needed.
 */
require_once __DIR__ . '/functions.php';

/** Canonical wrapper class for module content. */
function erp_ui_page_class(): string
{
    return 'erp-page';
}

/** Page-specific stylesheets (attendance, HR dashboard, leave, reports). */
function erp_ui_extra_stylesheets(): array
{
    $page = (string)($_GET['page'] ?? '');

    $map = [
        'attendance/list' => ['attendance-module.css'],
        'hr/dashboard' => ['hr-dashboard.css'],
        'leave/list' => ['leave-dashboard.css'],
        'reports/hr' => ['hr-reports.css'],
        'employees/list' => ['employee-list.css'],
        'employee/leave' => ['leave-dashboard.css', 'employee-module.css'],
        'employee/dashboard' => ['employee-module.css'],
        'employee/profile' => ['employee-module.css'],
        'employee/attendance' => ['employee-module.css'],
        'employee/salary' => ['employee-module.css'],
        'production/dashboard' => ['production-module.css'],
        'production/mixing' => ['production-module.css'],
        'production/building' => ['production-module.css'],
        'production/curing' => ['production-module.css'],
        'quality/dashboard' => ['qc-module.css'],
        'quality/list' => ['qc-module.css'],
        'quality/pending' => ['qc-module.css'],
        'quality/inspect' => ['qc-module.css'],
        'quality/defects' => ['qc-module.css'],
        'quality/reports' => ['qc-module.css'],
        'quality/rework' => ['qc-module.css'],
        'reports/quality' => ['qc-module.css'],
        'machines/list' => ['production-module.css', 'machine-module.css'],
        'machines/dashboard' => ['production-module.css', 'machine-module.css'],
        'machines/assignments' => ['production-module.css', 'machine-module.css'],
        'machines/inventory' => ['production-module.css', 'machine-module.css'],
        'machines/history' => ['production-module.css', 'machine-module.css'],
        'reports/production' => ['production-module.css', 'machine-module.css'],
        'sales/dashboard' => ['sales-module.css'],
        'sales/customers' => ['sales-module.css'],
        'sales/customer' => ['sales-module.css'],
        'sales/orders' => ['sales-module.css'],
        'sales/order' => ['sales-module.css'],
        'sales/invoices' => ['sales-module.css'],
        'sales/invoice' => ['sales-module.css'],
        'sales/payments' => ['sales-module.css'],
        'sales/dispatch' => ['sales-module.css'],
        'sales/ledger' => ['sales-module.css'],
        'sales/reports' => ['sales-module.css'],
        'sales/analytics' => ['sales-module.css'],
        'accounts/dashboard' => ['sales-module.css'],
        'accounts/ledger' => ['sales-module.css'],
        'accounts/supplier-ledger' => ['sales-module.css'],
        'accounts/receivables' => ['sales-module.css'],
        'accounts/payables' => ['sales-module.css'],
        'accounts/expenses' => ['sales-module.css'],
        'accounts/cashbook' => ['sales-module.css'],
        'accounts/bankbook' => ['sales-module.css'],
        'accounts/gst' => ['sales-module.css'],
        'accounts/pnl' => ['sales-module.css'],
        'accounts/balance-sheet' => ['sales-module.css'],
        'accounts/payments' => ['sales-module.css'],
        'accounts/transactions-history' => ['sales-module.css'],
        'accounts/reports' => ['sales-module.css'],
        'inventory/dashboard' => ['inventory-module.css'],
        'inventory/list' => ['inventory-module.css'],
        'inventory/materials' => ['inventory-module.css'],
        'inventory/add-stock' => ['inventory-module.css'],
        'inventory/purchase-inward' => ['inventory-module.css'],
        'inventory/purchase-history' => ['inventory-module.css', 'sales-module.css'],
        'inventory/purchase-print' => ['inventory-module.css'],
        'inventory/purchase-edit' => ['inventory-module.css'],
        'inventory/purchase-payments' => ['inventory-module.css', 'sales-module.css'],
        'inventory/supplier-ledger' => ['inventory-module.css'],
        'inventory/use-stock' => ['inventory-module.css'],
        'inventory/adjust-stock' => ['inventory-module.css'],
        'inventory/inward' => ['inventory-module.css'],
        'inventory/usage' => ['inventory-module.css'],
        'inventory/suppliers' => ['inventory-module.css'],
        'reports/inventory' => ['inventory-module.css'],
        'dispatch/dashboard' => ['dispatch-module.css'],
        'dispatch/new' => ['dispatch-module.css'],
        'dispatch/history' => ['dispatch-module.css'],
        'dispatch/customers' => ['dispatch-module.css'],
        'dispatch/drivers' => ['dispatch-module.css'],
        'dispatch/transport' => ['dispatch-module.css'],
        'dispatch/logistics' => ['dispatch-module.css'],
        'reports/dispatch' => ['dispatch-module.css'],
    ];

    $styles = $map[$page] ?? [];
    if (str_starts_with($page, 'accounts/')) {
        if (!in_array('sales-module.css', $styles, true)) {
            $styles[] = 'sales-module.css';
        }
        if (!in_array('accounts-module.css', $styles, true)) {
            $styles[] = 'accounts-module.css';
        }
    }

    return $styles;
}

/** Load global ERP shell CSS (+ optional page CSS). */
function erp_ui_enqueue(): void
{
    static $done = false;
    if ($done || !is_logged_in()) {
        return;
    }
    $done = true;

    $base = __DIR__ . '/../assets/css/';
    $files = ['erp-module.css', 'erp-ui-polish.css', 'accounts-module.css'];

    foreach (erp_ui_extra_stylesheets() as $extra) {
        $files[] = $extra;
    }

    foreach ($files as $file) {
        $path = $base . $file;
        if (!is_file($path)) {
            continue;
        }
        echo '<link href="assets/css/' . e($file) . '?v=' . e((string)filemtime($path)) . '" rel="stylesheet">' . "\n";
    }
}

function erp_ui_flash(): void
{
    $flash = get_flash();
    if (!$flash) {
        return;
    }
    echo '<div class="alert alert-' . e((string)$flash['type']) . ' erp-page__flash py-2 mb-2 alert-dismissible fade show" role="alert">'
        . e((string)$flash['message'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

/**
 * Standard page header block.
 *
 * @param array{title:string,subtitle?:string,actions_html?:string} $opts
 */
function erp_ui_page_head(array $opts): void
{
    $title = (string)($opts['title'] ?? '');
    $subtitle = (string)($opts['subtitle'] ?? '');
    $actions = (string)($opts['actions_html'] ?? '');
    ?>
    <header class="erp-page__top">
        <div>
            <h1 class="erp-page__title"><?= e($title) ?>
                <?php if ($subtitle !== ''): ?><span><?= e($subtitle) ?></span><?php endif; ?>
            </h1>
        </div>
        <?php if ($actions !== ''): ?>
            <div class="erp-page__top-actions"><?= $actions ?></div>
        <?php endif; ?>
    </header>
    <?php
}
