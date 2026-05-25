<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $paymentId = sales_save_payment($pdo, $_POST);
        set_flash('success', 'Payment recorded. Receipt: ' . sales_payment_receipt_no($paymentId, (string)($_POST['payment_date'] ?? date('Y-m-d'))));
        $_SESSION['last_payment_receipt_id'] = $paymentId;
    } catch (Throwable $e) {
        sales_log_exception($e, 'save_payment');
        set_flash('danger', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Unable to record payment.');
    }
    redirect('sales/payments');
}

$loadError = false;
$customers = [];
$openInvoices = [];
$recent = [];
$outstanding = [];
$dash = [];
$preCustomer = (int)($_GET['customer_id'] ?? 0);
$preInvoice = (int)($_GET['invoice_id'] ?? 0);
$filterCustomer = (int)($_GET['filter_customer'] ?? 0);
$filterFrom = trim((string)($_GET['filter_from'] ?? ''));
$filterTo = trim((string)($_GET['filter_to'] ?? ''));

try {
    sales_reconcile_invoice_balances($pdo);
    $customers = sales_list_customers($pdo, ['status' => 'Active']);
    $openInvoices = sales_list_invoices($pdo, []);
    $dash = sales_payment_dashboard($pdo);
    $outstanding = sales_customer_outstanding_full($pdo);
    $sql = 'SELECT p.*, i.invoice_no, c.company_name FROM sales_payments p
         INNER JOIN sales_invoices i ON i.id = p.invoice_id
         INNER JOIN sales_customers c ON c.id = p.customer_id WHERE 1=1';
    $params = [];
    if ($filterCustomer > 0) {
        $sql .= ' AND p.customer_id = :cid';
        $params['cid'] = $filterCustomer;
    }
    if ($filterFrom !== '') {
        $sql .= ' AND p.payment_date >= :df';
        $params['df'] = $filterFrom;
    }
    if ($filterTo !== '') {
        $sql .= ' AND p.payment_date <= :dt';
        $params['dt'] = $filterTo;
    }
    $sql .= ' ORDER BY p.payment_date DESC, p.id DESC LIMIT 50';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $recent = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    sales_log_exception($e, 'sales_payments');
    $loadError = true;
}

$invoiceOptions = [];
foreach ($openInvoices as $inv) {
    $bal = sales_invoice_pending_amount((float)$inv['total_amount'], (float)$inv['amount_paid']);
    if ($bal <= 0 && $preInvoice !== (int)$inv['id']) {
        continue;
    }
    $disp = sales_invoice_display_status($inv);
    $invoiceOptions[] = [
        'id' => (int)$inv['id'],
        'customer_id' => (int)$inv['customer_id'],
        'invoice_no' => (string)$inv['invoice_no'],
        'total' => (float)$inv['total_amount'],
        'paid' => (float)$inv['amount_paid'],
        'balance' => $bal,
        'label' => $disp['label'],
    ];
}
?>

<div class="sales-page crm-layout payments-layout">
    <?= crm_page_header('Payments', 'Record receipts and monitor customer outstanding balances.') ?>

    <?php if ($loadError): ?><?= sales_error_alert('Unable to load payments.') ?><?php endif; ?>

    <div class="crm-summary-4">
        <article class="sales-kpi"><span class="sales-kpi__label">Receivable</span><strong><?= e(sales_format_money((float)($dash['total_receivable'] ?? 0))) ?></strong></article>
        <article class="sales-kpi sales-kpi--ok"><span class="sales-kpi__label">Collected</span><strong><?= e(sales_format_money((float)($dash['collected'] ?? 0))) ?></strong></article>
        <article class="sales-kpi sales-kpi--warn"><span class="sales-kpi__label">Pending</span><strong><?= e(sales_format_money((float)($dash['pending'] ?? 0))) ?></strong></article>
        <article class="sales-kpi sales-kpi--danger"><span class="sales-kpi__label">Overdue</span><strong><?= e(sales_format_money((float)($dash['overdue'] ?? 0))) ?></strong></article>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <section class="sales-card payments-layout__form h-100">
                <div class="sales-card__head"><h2 class="sales-card__title">Record payment</h2></div>
                <div class="sales-card__body">
                    <form method="post" class="vstack gap-2" id="sales-payment-form">
                        <?= csrf_input() ?>
                        <div>
                            <label class="form-label">Customer</label>
                            <select class="form-select form-select-sm erp-select-search" name="customer_id" id="payCustomer" required data-placeholder="Search customer…">
                                <option value="">Search customer…</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= $preCustomer === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Invoice</label>
                            <select class="form-select form-select-sm" name="invoice_id" id="payInvoice" required>
                                <option value="">Select invoice</option>
                                <?php foreach ($invoiceOptions as $inv): ?>
                                    <option value="<?= $inv['id'] ?>"
                                            data-customer="<?= $inv['customer_id'] ?>"
                                            data-total="<?= e((string)$inv['total']) ?>"
                                            data-paid="<?= e((string)$inv['paid']) ?>"
                                            data-balance="<?= e((string)$inv['balance']) ?>"
                                            <?= $preInvoice === $inv['id'] ? 'selected' : '' ?>>
                                        <?= e($inv['invoice_no']) ?> — <?= e(sales_format_money($inv['balance'])) ?> due
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label small text-muted">Invoice total</label><input type="text" class="form-control form-control-sm" id="payInvTotal" readonly></div>
                            <div class="col-6"><label class="form-label small text-muted">Remaining</label><input type="text" class="form-control form-control-sm fw-semibold" id="payInvRemain" readonly></div>
                        </div>
                        <div><label class="form-label">Payment date</label><input type="date" class="form-control form-control-sm" name="payment_date" value="<?= e(date('Y-m-d')) ?>" required></div>
                        <div><label class="form-label">Amount</label><input type="number" class="form-control form-control-sm" name="amount" id="payAmount" min="0.01" step="0.01" required></div>
                        <div><label class="form-label">Mode</label><select class="form-select form-select-sm" name="payment_mode"><?php foreach (SALES_PAYMENT_MODES as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select></div>
                        <div><label class="form-label">Reference</label><input class="form-control form-control-sm" name="reference_no" placeholder="Optional"></div>
                        <button class="btn btn-primary btn-sm w-100 mt-1" type="submit">Save payment</button>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <section class="crm-section h-100">
                <div class="crm-section__head">
                    <h2 class="crm-section__title">Customer outstanding</h2>
                    <?= erp_export_toolbar('payments-outstanding-table', 'customer-outstanding') ?>
                </div>
                <div class="crm-section__body">
                    <?= crm_table_open('payments-outstanding-table') ?>
                    <thead><tr><th>Customer</th><th class="text-end">Invoiced</th><th class="text-end">Pending</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($outstanding, 0, 20) as $o): ?>
                        <tr>
                            <td><a href="<?= e(route_url('sales/payments', ['customer_id' => (int)($o['id'] ?? 0)])) ?>"><?= e($o['company_name']) ?></a></td>
                            <td class="text-end"><?= e(sales_format_money((float)$o['total_invoiced'])) ?></td>
                            <td class="text-end fw-semibold"><?= e(sales_format_money((float)$o['pending'])) ?></td>
                            <td><span class="<?= e($o['status_class']) ?>"><?= e($o['status_label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($outstanding === []): ?>
                        <tr><td colspan="4" class="sales-empty text-center py-3">No outstanding balances.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <?= crm_table_close() ?>
                </div>
            </section>
        </div>
    </div>

    <section class="crm-section">
        <div class="crm-section__head">
            <h2 class="crm-section__title">Payment history</h2>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-0">
                    <input type="hidden" name="page" value="sales/payments">
                    <select class="form-select form-select-sm erp-select-search" name="filter_customer" data-placeholder="All customers" style="width:auto;min-width:160px">
                        <option value="0">All customers</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $filterCustomer === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" class="form-control form-control-sm" name="filter_from" value="<?= e($filterFrom) ?>" style="width:auto">
                    <input type="date" class="form-control form-control-sm" name="filter_to" value="<?= e($filterTo) ?>" style="width:auto">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
                </form>
                <?= erp_export_toolbar('payments-history-table', 'payment-history') ?>
            </div>
        </div>
        <div class="crm-section__body">
            <?= crm_table_open('payments-history-table') ?>
            <thead><tr><th>Date</th><th>Customer</th><th>Invoice</th><th>Mode</th><th class="text-end">Amount</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $p): ?>
                <?php
                $pid = (int)$p['id'];
                $actions = [
                    ['label' => 'View receipt', 'url' => route_url('sales/payment-receipt', ['id' => $pid]), 'icon' => 'bi-eye', 'attrs' => 'target="_blank" rel="noopener"'],
                    ['label' => 'Print receipt', 'url' => route_url('sales/payment-receipt', ['id' => $pid, 'print' => 1]), 'icon' => 'bi-printer', 'attrs' => 'target="_blank" rel="noopener"'],
                    ['label' => 'View invoice', 'url' => route_url('sales/invoice', ['id' => (int)$p['invoice_id']]), 'icon' => 'bi-receipt'],
                ];
                ?>
                <tr>
                    <td><?= e($p['payment_date']) ?></td>
                    <td><?= e($p['company_name']) ?></td>
                    <td><?= e($p['invoice_no']) ?></td>
                    <td><?= e($p['payment_mode']) ?></td>
                    <td class="text-end fw-semibold"><?= e(sales_format_money((float)$p['amount'])) ?></td>
                    <td class="text-end"><?= crm_action_icons($actions) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($recent === []): ?>
                <tr><td colspan="6" class="sales-empty text-center py-4">No payments recorded yet.</td></tr>
            <?php endif; ?>
            </tbody>
            <?= crm_table_close() ?>
        </div>
    </section>
</div>
<script src="assets/js/sales-payments.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/sales-payments.js')) ?>"></script>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
