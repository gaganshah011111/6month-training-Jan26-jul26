<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();
$filters = [
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
    'customer_id' => (int)($_GET['customer_id'] ?? 0) ?: null,
];
$loadError = false;
$data = [];
$customers = [];

try {
    $customers = sales_list_customers($pdo, []);
    $data = sales_crm_reports_bundle($pdo, $filters);

    if (isset($_GET['export'])) {
        $export = (string)$_GET['export'];
        $headers = ['Metric', 'Value'];
        $rows = [
            ['Total sales', sales_format_money((float)$data['summary']['total_sales'])],
            ['Total orders', (string)$data['summary']['total_orders']],
            ['Total paid', sales_format_money((float)$data['summary']['total_paid'])],
            ['Total pending', sales_format_money((float)$data['summary']['total_pending'])],
        ];
        if ($export === 'csv') {
            erp_send_csv('crm-reports-' . date('Y-m-d') . '.csv', $headers, $rows);
        }
        erp_print_html_table('CRM Sales Reports', $headers, $rows, $export === 'pdf');
    }
} catch (Throwable $e) {
    sales_log_exception($e, 'sales_reports');
    $loadError = true;
    $data = [
        'summary' => array_fill_keys(['total_sales', 'total_orders', 'total_paid', 'total_pending'], 0),
        'top_customers' => [],
        'tyre_sales' => [],
        'payment_outstanding' => [],
        'dispatch_perf' => ['total' => 0, 'delivered' => 0, 'pending' => 0, 'avg_days' => 0],
    ];
}

$s = $data['summary'];
$dp = $data['dispatch_perf'];
$filterQs = array_filter([
    'page' => 'sales/reports',
    'from' => $filters['from'],
    'to' => $filters['to'],
    'customer_id' => $filters['customer_id'] ? (string)$filters['customer_id'] : '',
], static fn($v) => $v !== '' && $v !== '0');
?>

<div class="sales-page crm-layout crm-reports-page">
    <?= crm_page_header('CRM Reports', 'Business summary — sales, customers, tyres, collections, and dispatch.') ?>

    <?php if ($loadError): ?><?= sales_error_alert('Unable to load report data.') ?><?php endif; ?>

    <form method="get" class="crm-filter-inline">
        <input type="hidden" name="page" value="sales/reports">
        <div class="crm-filter-inline__field"><label>From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($filters['from']) ?>"></div>
        <div class="crm-filter-inline__field"><label>To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($filters['to']) ?>"></div>
        <div class="crm-filter-inline__field">
            <label>Customer</label>
                <select class="form-select form-select-sm erp-select-search" name="customer_id" data-placeholder="All customers">
                <option value="0">All</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $filters['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="crm-filter-inline__actions">
            <button class="btn btn-primary btn-sm" type="submit">Apply</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/reports')) ?>">Reset</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/reports', array_merge($filterQs, ['export' => 'pdf']))) ?>" target="_blank">PDF</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/reports', array_merge($filterQs, ['export' => 'csv']))) ?>">Excel</a>
        </div>
    </form>

    <div class="crm-summary-4 crm-reports-kpis">
        <article class="sales-kpi sales-kpi--primary"><span class="sales-kpi__label">Total sales</span><strong><?= e(sales_format_money((float)$s['total_sales'])) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Orders</span><strong><?= e((string)$s['total_orders']) ?></strong></article>
        <article class="sales-kpi sales-kpi--ok"><span class="sales-kpi__label">Paid</span><strong><?= e(sales_format_money((float)$s['total_paid'])) ?></strong></article>
        <article class="sales-kpi sales-kpi--danger"><span class="sales-kpi__label">Pending</span><strong><?= e(sales_format_money((float)$s['total_pending'])) ?></strong></article>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <section class="crm-section crm-reports-card">
                <div class="crm-section__head"><h2 class="crm-section__title">Dispatch performance</h2></div>
                <div class="crm-section__body crm-mini-stats">
                    <div class="crm-mini-stats__item"><span>Total</span><strong><?= e((string)($dp['total'] ?? 0)) ?></strong></div>
                    <div class="crm-mini-stats__item crm-mini-stats__item--ok"><span>Delivered</span><strong><?= e((string)($dp['delivered'] ?? 0)) ?></strong></div>
                    <div class="crm-mini-stats__item crm-mini-stats__item--warn"><span>Pending</span><strong><?= e((string)($dp['pending'] ?? 0)) ?></strong></div>
                    <div class="crm-mini-stats__item"><span>Avg days</span><strong><?= e((string)($dp['avg_days'] ?? 0)) ?></strong></div>
                </div>
            </section>
        </div>
        <div class="col-md-6">
            <section class="crm-section crm-reports-card">
                <div class="crm-section__head">
                    <h2 class="crm-section__title">Tyre-wise sales</h2>
                    <?= erp_export_toolbar('crm-tyre-sales', 'tyre-sales') ?>
                </div>
                <div class="crm-section__body">
                    <?= crm_table_open('crm-tyre-sales', true) ?>
                    <thead><tr><th>Tyre</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($data['tyre_sales'], 0, 8) as $r): ?>
                        <tr>
                            <td><?= e($r['tyre_type']) ?></td>
                            <td class="text-end"><?= e((string)$r['qty_sold']) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$r['revenue'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($data['tyre_sales'] ?? []) === []): ?>
                        <tr><td colspan="3" class="sales-empty text-center py-3">No tyre sales for filters.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <?= crm_table_close() ?>
                </div>
            </section>
        </div>
    </div>

    <section class="crm-section crm-reports-card mb-4">
        <div class="crm-section__head">
            <h2 class="crm-section__title">Top customers</h2>
            <?= erp_export_toolbar('crm-top-customers', 'top-customers') ?>
        </div>
        <div class="crm-section__body">
            <?= crm_table_open('crm-top-customers') ?>
            <thead><tr><th>#</th><th>Customer</th><th class="text-end">Orders</th><th class="text-end">Total</th><th class="text-end">Pending</th></tr></thead>
            <tbody>
            <?php foreach ($data['top_customers'] as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($r['company_name']) ?></td>
                    <td class="text-end"><?= e((string)$r['order_count']) ?></td>
                    <td class="text-end"><?= e(sales_format_money((float)$r['total_amount'])) ?></td>
                    <td class="text-end"><?= e(sales_format_money((float)$r['pending'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($data['top_customers'] === []): ?><tr><td colspan="5" class="sales-empty text-center py-3">No data.</td></tr><?php endif; ?>
            </tbody>
            <?= crm_table_close() ?>
        </div>
    </section>

    <section class="crm-section crm-reports-card">
        <div class="crm-section__head">
            <h2 class="crm-section__title">Payment outstanding</h2>
            <?= erp_export_toolbar('crm-payment-outstanding', 'payment-outstanding') ?>
        </div>
        <div class="crm-section__body">
            <?= crm_table_open('crm-payment-outstanding') ?>
            <thead><tr><th>Customer</th><th>Invoice</th><th class="text-end">Pending</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($data['payment_outstanding'] as $r): ?>
                <tr>
                    <td><?= e($r['company_name']) ?></td>
                    <td><?= e($r['invoice_no']) ?></td>
                    <td class="text-end fw-semibold"><?= e(sales_format_money((float)$r['pending'])) ?></td>
                    <td><?= e((string)($r['due_date'] ?? '—')) ?></td>
                    <td><span class="<?= e($r['status_class']) ?>"><?= e($r['status_label']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($data['payment_outstanding'] === []): ?><tr><td colspan="5" class="sales-empty text-center py-3">No outstanding invoices.</td></tr><?php endif; ?>
            </tbody>
            <?= crm_table_close() ?>
        </div>
    </section>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
