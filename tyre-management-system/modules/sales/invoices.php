<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();
$filters = [
    'customer_id' => (int)($_GET['customer_id'] ?? 0) ?: null,
    'payment_status' => (string)($_GET['payment_status'] ?? ''),
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
    'q' => trim((string)($_GET['q'] ?? '')),
];
$loadError = false;
$rows = [];
$customers = [];
$invTotal = 0.0;
$invPaid = 0.0;
$invPending = 0.0;

try {
    sales_reconcile_invoice_balances($pdo);
    $rows = sales_list_invoices($pdo, $filters);
    $customers = sales_list_customers($pdo, []);
    foreach ($rows as $inv) {
        $invTotal += (float)$inv['total_amount'];
        $invPaid += (float)$inv['amount_paid'];
        $invPending += sales_invoice_pending_amount((float)$inv['total_amount'], (float)$inv['amount_paid']);
    }
    if (isset($_GET['export'])) {
        $export = (string)$_GET['export'];
        $headers = ['Invoice', 'Customer', 'Date', 'Total', 'Paid', 'Pending', 'Status'];
        $data = [];
        foreach ($rows as $inv) {
            $disp = sales_invoice_display_status($inv);
            $data[] = [
                $inv['invoice_no'],
                $inv['company_name'],
                $inv['invoice_date'],
                sales_format_money((float)$inv['total_amount']),
                sales_format_money((float)$inv['amount_paid']),
                sales_format_money((float)$disp['pending']),
                $disp['label'],
            ];
        }
        if ($export === 'csv') {
            erp_send_csv('invoices-' . date('Y-m-d') . '.csv', $headers, $data);
        }
        erp_print_html_table('Invoices', $headers, $data, $export === 'pdf');
    }
} catch (Throwable $e) {
    sales_log_exception($e, 'sales_invoices');
    $loadError = true;
}
?>

<div class="sales-page crm-layout">
    <?= crm_page_header(
        'Invoices',
        'Generated after dispatch — view, print, and track payment status.',
        '<a href="' . e(route_url('sales/payments')) . '" class="btn btn-outline-secondary btn-sm">Record payment</a>'
    ) ?>

    <?php if ($loadError): ?><?= sales_error_alert('Unable to load invoices.') ?><?php endif; ?>

    <?php if ($filters['from'] !== '' || $filters['to'] !== '' || $filters['q'] !== '' || $filters['customer_id'] || $filters['payment_status'] !== ''): ?>
        <div class="crm-active-filters mb-2">
            <?php if ($filters['q'] !== ''): ?><span class="badge bg-light text-dark border">Search: <?= e($filters['q']) ?></span><?php endif; ?>
            <?php if ($filters['from'] !== ''): ?><span class="badge bg-light text-dark border">From: <?= e($filters['from']) ?></span><?php endif; ?>
            <?php if ($filters['to'] !== ''): ?><span class="badge bg-light text-dark border">To: <?= e($filters['to']) ?></span><?php endif; ?>
            <a class="small ms-1" href="<?= e(route_url('sales/invoices')) ?>">Clear all</a>
        </div>
    <?php endif; ?>

    <form method="get" class="crm-filter-inline crm-invoices-search-bar" id="sales-invoice-filter-form" action="<?= e(route_url('sales/invoices')) ?>">
        <input type="hidden" name="page" value="sales/invoices">
        <div class="crm-filter-inline__field crm-invoices-search-wrap" style="flex:2 1 200px">
            <label for="sales-invoice-search">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control" name="q" id="sales-invoice-search"
                       value="<?= e($filters['q']) ?>" placeholder="Invoice no, customer, SO…" autocomplete="off">
            </div>
        </div>
        <div class="crm-filter-inline__field">
            <label for="sales-invoice-from">From date</label>
            <input type="date" class="form-control form-control-sm" name="from" id="sales-invoice-from" value="<?= e($filters['from']) ?>">
        </div>
        <div class="crm-filter-inline__field">
            <label for="sales-invoice-to">To date</label>
            <input type="date" class="form-control form-control-sm" name="to" id="sales-invoice-to" value="<?= e($filters['to']) ?>">
        </div>
        <div class="crm-filter-inline__field">
            <label>Customer</label>
            <select class="form-select form-select-sm erp-select-search" name="customer_id" data-placeholder="All customers">
                <option value="0">All</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $filters['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="crm-filter-inline__field">
            <label>Status</label>
            <select class="form-select form-select-sm" name="payment_status">
                <option value="">All</option>
                <?php foreach (SALES_PAYMENT_STATUSES as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filters['payment_status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="crm-filter-inline__actions">
            <button class="btn btn-primary btn-sm" type="submit">Apply</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/invoices')) ?>">Reset</a>
        </div>
        <span class="crm-search-hint w-100">Filters update table and summary automatically</span>
    </form>

    <div class="crm-summary-4" id="crm-invoices-kpis">
        <article class="sales-kpi"><span class="sales-kpi__label">Invoices</span><strong id="inv-kpi-count"><?= count($rows) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Total billed</span><strong id="inv-kpi-billed"><?= e(sales_format_money($invTotal)) ?></strong></article>
        <article class="sales-kpi sales-kpi--ok"><span class="sales-kpi__label">Collected</span><strong id="inv-kpi-collected"><?= e(sales_format_money($invPaid)) ?></strong></article>
        <article class="sales-kpi sales-kpi--warn"><span class="sales-kpi__label">Outstanding</span><strong id="inv-kpi-outstanding"><?= e(sales_format_money($invPending)) ?></strong></article>
    </div>

    <section class="crm-section">
        <div class="crm-section__head">
            <h2 class="crm-section__title">Invoice register</h2>
            <span class="small text-muted" id="sales-invoice-count"><?= count($rows) ?> invoices</span>
            <?= erp_export_toolbar('sales-invoice-table', 'invoices') ?>
        </div>
        <div class="crm-section__body">
            <?= crm_table_open('sales-invoice-table') ?>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Pending</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $inv): ?>
                <?php
                $disp = sales_invoice_display_status($inv);
                $iid = (int)$inv['id'];
                $hay = strtolower(implode(' ', [
                    $inv['invoice_no'],
                    $inv['company_name'],
                    (string)($inv['so_number'] ?? ''),
                    $disp['label'],
                    (string)($inv['invoice_date'] ?? ''),
                ]));
                $pendingAmt = (float)$disp['pending'];
                $actions = [
                    ['label' => 'View', 'url' => route_url('sales/invoice', ['id' => $iid]), 'icon' => 'bi-eye'],
                    ['label' => 'PDF', 'url' => route_url('sales/invoice-print', ['id' => $iid]), 'icon' => 'bi-file-pdf', 'attrs' => 'target="_blank" rel="noopener"'],
                    ['label' => 'Print', 'url' => route_url('sales/invoice-print', ['id' => $iid, 'print' => 1]), 'icon' => 'bi-printer', 'attrs' => 'target="_blank" rel="noopener"'],
                    ['label' => 'Payment', 'url' => route_url('sales/payments', ['invoice_id' => $iid]), 'icon' => 'bi-cash-stack'],
                ];
                ?>
                <tr class="sales-invoice-row"
                    data-search="<?= e($hay) ?>"
                    data-date="<?= e((string)($inv['invoice_date'] ?? '')) ?>"
                    data-total="<?= e((string)$inv['total_amount']) ?>"
                    data-paid="<?= e((string)$inv['amount_paid']) ?>"
                    data-pending="<?= e((string)$pendingAmt) ?>">
                    <td><strong><?= e($inv['invoice_no']) ?></strong></td>
                    <td><?= e($inv['company_name']) ?></td>
                    <td><?= e($inv['invoice_date']) ?></td>
                    <td class="text-end"><?= e(sales_format_money((float)$inv['total_amount'])) ?></td>
                    <td class="text-end fw-semibold"><?= e(sales_format_money((float)$disp['pending'])) ?></td>
                    <td><span class="<?= e($disp['class']) ?>"><?= e($disp['label']) ?></span></td>
                    <td class="text-end"><?= crm_action_icons($actions) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr class="sales-invoice-empty"><td colspan="7" class="sales-empty text-center py-4">No invoices yet.</td></tr>
            <?php endif; ?>
            </tbody>
            <?= crm_table_close() ?>
        </div>
    </section>
</div>
<script src="assets/js/sales-invoices.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/sales-invoices.js')) ?>"></script>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
