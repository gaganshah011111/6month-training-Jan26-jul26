<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_finance.php';
require_once __DIR__ . '/../../includes/erp_export.php';

$pdo = Database::connection();
$customers = acc_customer_ledger_summary($pdo);
$customerId = (int)($_GET['customer_id'] ?? 0);
$ledger = $customerId > 0 ? sales_customer_ledger($pdo, $customerId) : ['rows' => [], 'customer' => null, 'summary' => []];
$customer = $ledger['customer'];
$summary = $ledger['summary'] ?? [];
$rows = $ledger['rows'] ?? [];
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Customer Ledger</h1>
            <p class="prod-page__sub">Customer outstanding, payment status, and detailed debit/credit ledger.</p>
        </div>
    </header>

    <section class="sales-card mb-3">
        <div class="sales-card__head d-flex justify-content-between align-items-center">
            <h2 class="sales-card__title mb-0">Customer outstanding ledger</h2>
            <?= erp_export_toolbar('acc-customer-ledger-table', 'customer-ledger') ?>
        </div>
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0" id="acc-customer-ledger-table">
                <thead><tr><th>Customer</th><th class="text-end">Total invoiced</th><th class="text-end">Total paid</th><th class="text-end">Pending amount</th><th>Last payment date</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($customers as $c): ?>
                    <?php $meta = $c['status_meta'] ?? acc_payment_meta('Unpaid'); ?>
                    <tr>
                        <td><?= e((string)$c['company_name']) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$c['total_invoiced'])) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$c['total_paid'])) ?></td>
                        <td class="text-end fw-semibold"><?= e(sales_format_money((float)$c['pending'])) ?></td>
                        <td><?= e((string)($c['last_payment'] ?? '—')) ?></td>
                        <td><span class="badge <?= e($meta['cls']) ?>"><?= e((string)$meta['label']) ?></span></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('accounts/ledger', ['customer_id' => (int)$c['id']])) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($customers === []): ?><tr><td colspan="7" class="sales-empty">No customer receivable entries.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($customer): ?>
    <div class="sales-kpis mb-3">
        <article class="sales-kpi"><span class="sales-kpi__label">Opening</span><strong><?= e(sales_format_money((float)($summary['opening'] ?? 0))) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Total sales</span><strong><?= e(sales_format_money((float)($summary['total_sales'] ?? 0))) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Total paid</span><strong class="text-success"><?= e(sales_format_money((float)($summary['total_paid'] ?? 0))) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Outstanding</span><strong class="text-danger"><?= e(sales_format_money((float)($summary['outstanding'] ?? 0))) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Last payment</span><strong><?= e((string)($summary['last_payment'] ?? '—')) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Credit days</span><strong><?= e((string)($summary['credit_days'] ?? '—')) ?></strong></article>
    </div>

    <section class="sales-card">
        <div class="sales-card__head d-flex justify-content-between align-items-center">
            <h2 class="sales-card__title mb-0"><?= e($customer['company_name']) ?> — ledger</h2>
            <?= erp_export_toolbar('acc-ledger-details-table', 'customer-ledger-details') ?>
        </div>
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0" id="acc-ledger-details-table">
                <thead><tr><th>Date</th><th>Ref</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['date']) ?></td>
                        <td><?= e($r['ref']) ?></td>
                        <td class="text-end"><?= (float)$r['debit'] > 0 ? e(sales_format_money((float)$r['debit'])) : '—' ?></td>
                        <td class="text-end"><?= (float)$r['credit'] > 0 ? e(sales_format_money((float)$r['credit'])) : '—' ?></td>
                        <td class="text-end fw-semibold"><?= e(sales_format_money((float)$r['balance'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="5" class="sales-empty">No ledger entries.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php elseif ($customerId > 0): ?>
        <div class="alert alert-warning">Customer not found.</div>
    <?php endif; ?>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
