<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';
require_once __DIR__ . '/../../includes/erp_export.php';

if (!has_role(['Sales Manager', 'Accounts Manager', 'Super Admin', 'Admin'])) {
    echo '<div class="alert alert-warning">Access denied.</div>';
    return;
}

$pdo = Database::connection();
$loadError = false;

$customerId = (int)($_GET['customer_id'] ?? 0);
$status = trim((string)($_GET['payment_status'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$historyInvoiceId = (int)($_GET['invoice_id'] ?? 0);
$export = (string)($_GET['export'] ?? '');

if (!in_array($status, ['', 'Pending', 'Partial', 'Paid', 'Overdue'], true)) {
    $status = '';
}

$customers = [];
$dash = ['total_receivable' => 0, 'collected' => 0, 'pending' => 0, 'overdue' => 0];
$rows = [];
$history = [];
$overdueTop = [];
$receiptByInvoice = [];

try {
    sales_reconcile_invoice_balances($pdo);
    $customers = sales_list_customers($pdo, ['status' => 'Active']);
    $dash = sales_payment_dashboard($pdo);

    $rows = sales_list_invoices($pdo, [
        'customer_id' => $customerId > 0 ? $customerId : null,
        'payment_status' => $status !== '' ? $status : '',
        'from' => $from,
        'to' => $to,
    ]);

    $st = $pdo->prepare(
        "SELECT invoice_id, MAX(id) AS payment_id
         FROM sales_payments
         GROUP BY invoice_id"
    );
    $st->execute();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $receiptByInvoice[(int)$r['invoice_id']] = (int)$r['payment_id'];
    }

    $hSql = 'SELECT p.*, i.invoice_no, i.payment_status, c.company_name
             FROM sales_payments p
             INNER JOIN sales_invoices i ON i.id = p.invoice_id
             INNER JOIN sales_customers c ON c.id = p.customer_id
             WHERE 1=1';
    $hParams = [];
    if ($customerId > 0) {
        $hSql .= ' AND p.customer_id = :cid';
        $hParams['cid'] = $customerId;
    }
    if ($from !== '') {
        $hSql .= ' AND p.payment_date >= :df';
        $hParams['df'] = $from;
    }
    if ($to !== '') {
        $hSql .= ' AND p.payment_date <= :dt';
        $hParams['dt'] = $to;
    }
    if ($historyInvoiceId > 0) {
        $hSql .= ' AND p.invoice_id = :iid';
        $hParams['iid'] = $historyInvoiceId;
    }
    $hSql .= ' ORDER BY p.payment_date DESC, p.id DESC LIMIT 120';
    $hst = $pdo->prepare($hSql);
    $hst->execute($hParams);
    $history = $hst->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $ost = $pdo->prepare(
        "SELECT i.id, i.invoice_no, i.due_date, (i.total_amount - i.amount_paid) AS pending, c.company_name
         FROM sales_invoices i
         INNER JOIN sales_customers c ON c.id = i.customer_id
         WHERE (i.total_amount - i.amount_paid) > 0.01
           AND i.due_date IS NOT NULL
           AND i.due_date <> ''
           AND i.due_date < CURDATE()
         ORDER BY pending DESC
         LIMIT 5"
    );
    $ost->execute();
    $overdueTop = $ost->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($export === 'csv' || $export === 'pdf') {
        $headers = ['Invoice no', 'Customer', 'Invoice amount', 'Paid amount', 'Remaining', 'Due date', 'Payment status'];
        $data = [];
        foreach ($rows as $inv) {
            $disp = sales_invoice_display_status($inv);
            $statusLabel = match ((string)$disp['label']) {
                'Paid' => 'PAID',
                'Partially Paid', 'Partial' => 'PARTIAL',
                'Overdue' => 'OVERDUE',
                default => 'UNPAID',
            };
            $data[] = [
                (string)$inv['invoice_no'],
                (string)$inv['company_name'],
                sales_format_money((float)$inv['total_amount']),
                sales_format_money((float)$inv['amount_paid']),
                sales_format_money((float)$disp['pending']),
                (string)($inv['due_date'] ?? '—'),
                $statusLabel,
            ];
        }
        if ($export === 'csv') {
            erp_send_csv('payment-status-' . date('Y-m-d') . '.csv', $headers, $data);
        }
        erp_print_html_table('Payment Status', $headers, $data, true);
    }
} catch (Throwable $e) {
    sales_log_exception($e, 'sales_payment_status');
    $loadError = true;
}

$filterQs = array_filter([
    'page' => 'sales/payments',
    'customer_id' => $customerId > 0 ? (string)$customerId : '',
    'payment_status' => $status,
    'from' => $from,
    'to' => $to,
], static fn($v) => $v !== '');
?>

<div class="sales-page crm-layout payments-layout">
    <?= crm_page_header('Payment Status', 'Read-only payment monitoring for invoices, pending collections, and customer follow-up.') ?>

    <?php if ($loadError): ?><?= sales_error_alert('Unable to load payment status.') ?><?php endif; ?>

    <form method="get" class="crm-filter-inline">
        <input type="hidden" name="page" value="sales/payments">
        <div class="crm-filter-inline__field">
            <label>Customer search</label>
            <select class="form-select form-select-sm erp-select-search" name="customer_id" data-placeholder="All customers">
                <option value="0">All customers</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="crm-filter-inline__field">
            <label>Status</label>
            <select class="form-select form-select-sm" name="payment_status">
                <option value="">All</option>
                <option value="Paid" <?= $status === 'Paid' ? 'selected' : '' ?>>PAID</option>
                <option value="Partial" <?= $status === 'Partial' ? 'selected' : '' ?>>PARTIAL</option>
                <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>UNPAID</option>
                <option value="Overdue" <?= $status === 'Overdue' ? 'selected' : '' ?>>OVERDUE</option>
            </select>
        </div>
        <div class="crm-filter-inline__field"><label>From date</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
        <div class="crm-filter-inline__field"><label>To date</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
        <div class="crm-filter-inline__actions">
            <button class="btn btn-primary btn-sm" type="submit">Apply filter</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/payments')) ?>">Reset</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/payments', array_merge($filterQs, ['export' => 'pdf']))) ?>" target="_blank" rel="noopener">PDF</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/payments', array_merge($filterQs, ['export' => 'csv']))) ?>">Excel</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print</button>
        </div>
    </form>

    <div class="crm-summary-4">
        <article class="sales-kpi"><span class="sales-kpi__label">Total receivable</span><strong><?= e(sales_format_money((float)($dash['total_receivable'] ?? 0))) ?></strong></article>
        <article class="sales-kpi sales-kpi--ok"><span class="sales-kpi__label">Collected</span><strong><?= e(sales_format_money((float)($dash['collected'] ?? 0))) ?></strong></article>
        <article class="sales-kpi sales-kpi--warn"><span class="sales-kpi__label">Pending</span><strong><?= e(sales_format_money((float)($dash['pending'] ?? 0))) ?></strong></article>
        <article class="sales-kpi sales-kpi--danger"><span class="sales-kpi__label">Overdue</span><strong><?= e(sales_format_money((float)($dash['overdue'] ?? 0))) ?></strong></article>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <section class="crm-section h-100">
                <div class="crm-section__head">
                    <h2 class="crm-section__title">Payment status table</h2>
                    <?= erp_export_toolbar('sales-payment-status-table', 'payment-status') ?>
                </div>
                <div class="crm-section__body">
                    <?= crm_table_open('sales-payment-status-table', false) ?>
                    <thead><tr><th>Invoice no</th><th>Customer</th><th class="text-end">Invoice amount</th><th class="text-end">Paid amount</th><th class="text-end">Remaining</th><th>Due date</th><th>Payment status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $inv): ?>
                        <?php
                        $disp = sales_invoice_display_status($inv);
                        $iid = (int)$inv['id'];
                        $payId = (int)($receiptByInvoice[$iid] ?? 0);
                        $actions = [
                            ['label' => 'View invoice', 'url' => route_url('sales/invoice', ['id' => $iid]), 'icon' => 'bi-eye'],
                            ['label' => 'View receipt PDF', 'url' => $payId > 0 ? route_url('sales/payment-receipt', ['id' => $payId]) : '#', 'icon' => 'bi-file-pdf', 'attrs' => 'target="_blank" rel="noopener"', 'disabled' => $payId < 1],
                            ['label' => 'View payment history', 'url' => route_url('sales/payments', array_merge($filterQs, ['invoice_id' => $iid])) . '#payment-history', 'icon' => 'bi-clock-history'],
                        ];
                        $statusLabel = match ((string)$disp['label']) {
                            'Paid' => 'PAID',
                            'Partially Paid', 'Partial' => 'PARTIAL',
                            'Overdue' => 'OVERDUE',
                            default => 'UNPAID',
                        };
                        ?>
                        <tr>
                            <td><strong><?= e((string)$inv['invoice_no']) ?></strong></td>
                            <td><?= e((string)$inv['company_name']) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$inv['total_amount'])) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$inv['amount_paid'])) ?></td>
                            <td class="text-end fw-semibold"><?= e(sales_format_money((float)$disp['pending'])) ?></td>
                            <td><?= e((string)($inv['due_date'] ?? '—')) ?></td>
                            <td><span class="<?= e($disp['class']) ?>"><?= e($statusLabel) ?></span></td>
                            <td class="text-end"><?= crm_action_icons($actions) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?><tr><td colspan="8" class="sales-empty text-center py-4">No invoices for selected filters.</td></tr><?php endif; ?>
                    </tbody>
                    <?= crm_table_close() ?>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="crm-section h-100">
                <div class="crm-section__head">
                    <h2 class="crm-section__title">Top overdue customers</h2>
                </div>
                <div class="crm-section__body p-3">
                    <div class="vstack gap-2">
                        <?php foreach ($overdueTop as $o): ?>
                            <article class="sales-kpi mb-0">
                                <span class="sales-kpi__label"><?= e((string)$o['company_name']) ?></span>
                                <strong class="text-danger"><?= e(sales_format_money((float)$o['pending'])) ?></strong>
                                <div class="small text-muted mt-1">Invoice: <?= e((string)$o['invoice_no']) ?> · Due: <?= e((string)$o['due_date']) ?></div>
                            </article>
                        <?php endforeach; ?>
                        <?php if ($overdueTop === []): ?><p class="small text-muted mb-0">No overdue customers right now.</p><?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <section class="crm-section" id="payment-history">
        <div class="crm-section__head">
            <h2 class="crm-section__title">Payment history</h2>
            <?= erp_export_toolbar('sales-payment-history-table', 'payment-history') ?>
        </div>
        <div class="crm-section__body">
            <?= crm_table_open('sales-payment-history-table') ?>
            <thead><tr><th>Date</th><th>Customer</th><th>Invoice</th><th class="text-end">Paid amount</th><th>Mode</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($history as $p): ?>
                <?php
                    $stLabel = (string)($p['payment_status'] ?? 'Pending');
                    $stClass = match ($stLabel) {
                        'Paid' => 'crm-track crm-track--paid',
                        'Partial' => 'crm-track crm-track--partial-pay',
                        'Overdue' => 'crm-track crm-track--overdue',
                        default => 'crm-track crm-track--unpaid',
                    };
                ?>
                <tr>
                    <td><?= e((string)$p['payment_date']) ?></td>
                    <td><?= e((string)$p['company_name']) ?></td>
                    <td><?= e((string)$p['invoice_no']) ?></td>
                    <td class="text-end fw-semibold"><?= e(sales_format_money((float)$p['amount'])) ?></td>
                    <td><?= e((string)$p['payment_mode']) ?></td>
                    <td><span class="<?= e($stClass) ?>"><?= e(strtoupper($stLabel === 'Pending' ? 'UNPAID' : $stLabel)) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($history === []): ?><tr><td colspan="6" class="sales-empty text-center py-4">No payment history for selected filters.</td></tr><?php endif; ?>
            </tbody>
            <?= crm_table_close() ?>
        </div>
    </section>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
