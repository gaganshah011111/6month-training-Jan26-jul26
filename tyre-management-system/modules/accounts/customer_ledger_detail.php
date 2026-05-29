<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_ledger.php';

if (!has_role(['Accounts Manager', 'Super Admin', 'Admin', 'Sales Manager'])) {
    echo '<div class="alert alert-warning m-3">Access denied.</div>';
    return;
}

$pdo = Database::connection();
$customerId = (int)($_GET['customer_id'] ?? 0);
if ($customerId < 1) {
    echo '<div class="alert alert-warning m-3">Invalid customer. <a href="' . e(route_url('accounts/ledger')) . '">Back to Customer Ledger</a></div>';
    return;
}

$_GET['export_scope'] = 'detail';
acc_ledger_handle_export($pdo, 'detail');

$data = acc_customer_ledger_detail($pdo, $customerId);
$customer = $data['customer'];
if (!$customer) {
    echo '<div class="alert alert-warning m-3">Customer not found. <a href="' . e(route_url('accounts/ledger')) . '">Back</a></div>';
    return;
}

$summary = $data['summary'];
$statusMeta = acc_payment_meta((string)($summary['status'] ?? 'Unpaid'));
$backUrl = route_url('accounts/ledger');
?>

<div class="accounts-page acc-ledger-page">
    <div class="mb-3">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e($backUrl) ?>"><i class="bi bi-arrow-left"></i> Customer Ledger</a>
    </div>

    <header class="prod-page__head d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="prod-page__title h4 mb-1"><?= e((string)$customer['company_name']) ?></h1>
            <p class="prod-page__sub mb-0 font-monospace"><?= e((string)($customer['customer_code'] ?? '')) ?></p>
        </div>
        <?= acc_ledger_export_toolbar('accounts/customer-ledger-detail', 'customer', 'detail', 'customer-ledger-detail') ?>
    </header>

    <div class="sales-kpis mb-3">
        <article class="sales-kpi col"><span class="sales-kpi__label">Contact</span><strong class="small"><?= e((string)($customer['contact_person'] ?? '—')) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">Phone</span><strong class="small"><?= e((string)($customer['phone'] ?? '—')) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">GST</span><strong class="small"><?= e((string)($customer['gst_number'] ?? '—')) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">Total Invoiced</span><strong><?= e(sales_format_money((float)$summary['total_invoiced'])) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">Total Paid</span><strong class="text-success"><?= e(sales_format_money((float)$summary['total_paid'])) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">Outstanding</span><strong class="text-danger"><?= e(sales_format_money((float)$summary['outstanding'])) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">Last Payment</span><strong class="small"><?= e((string)$summary['last_payment']) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">Status</span><strong><span class="badge <?= e($statusMeta['cls']) ?>"><?= e($statusMeta['label']) ?></span></strong></article>
    </div>

    <ul class="nav nav-tabs acc-ledger-tabs mb-0" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabInvoices" type="button">Invoice History</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPayments" type="button">Payment History</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLedger" type="button">Ledger Entries</button></li>
    </ul>

    <div class="tab-content sales-card acc-ledger-tab-panel">
        <div class="tab-pane fade show active" id="tabInvoices">
            <div class="sales-table-wrap">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Invoice</th><th>Date</th><th>Order</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($data['invoices'] as $inv):
                        $im = $inv['status_meta'] ?? acc_payment_meta('Unpaid');
                    ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string)$inv['invoice_no']) ?></td>
                            <td><?= e((string)$inv['invoice_date']) ?></td>
                            <td class="small text-muted"><?= e((string)($inv['so_number'] ?? '—')) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$inv['total_amount'])) ?></td>
                            <td class="text-end text-success"><?= e(sales_format_money((float)$inv['amount_paid'])) ?></td>
                            <td class="text-end text-danger"><?= e(sales_format_money((float)$inv['balance'])) ?></td>
                            <td><span class="badge <?= e($im['cls']) ?>"><?= e($im['label']) ?></span></td>
                            <td><a class="btn btn-sm btn-link p-0" href="<?= e(route_url('accounts/invoice-view', ['id' => (int)$inv['id']])) ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($data['invoices'] === []): ?><tr><td colspan="8" class="sales-empty">No invoices.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="tabPayments">
            <div class="sales-table-wrap">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Date</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th><th>Remarks</th><th>Invoice</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['payments'] as $p): ?>
                        <tr>
                            <td><?= e((string)$p['payment_date']) ?></td>
                            <td class="text-end fw-semibold text-success"><?= e(sales_format_money((float)$p['amount'])) ?></td>
                            <td><?= e((string)($p['payment_mode'] ?? '—')) ?></td>
                            <td><?= e((string)($p['reference_no'] ?? '—')) ?></td>
                            <td class="small"><?= e((string)($p['remarks'] ?? '')) ?></td>
                            <td><?= e((string)($p['invoice_no'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($data['payments'] === []): ?><tr><td colspan="6" class="sales-empty">No payments recorded.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="tabLedger">
            <div class="sales-table-wrap">
                <table class="table table-sm table-hover mb-0" id="acc-ledger-detail-table">
                    <thead><tr><th>Date</th><th>Reference</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['ledger'] as $r): ?>
                        <tr>
                            <td><?= e((string)$r['date']) ?></td>
                            <td><?= e((string)$r['ref']) ?></td>
                            <td class="text-end"><?= (float)$r['debit'] > 0 ? e(sales_format_money((float)$r['debit'])) : '—' ?></td>
                            <td class="text-end"><?= (float)$r['credit'] > 0 ? e(sales_format_money((float)$r['credit'])) : '—' ?></td>
                            <td class="text-end fw-semibold"><?= e(sales_format_money((float)$r['balance'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($data['ledger'] === []): ?><tr><td colspan="5" class="sales-empty">No ledger entries.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
