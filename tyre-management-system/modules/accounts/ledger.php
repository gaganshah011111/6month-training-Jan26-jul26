<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_service.php';

$pdo = Database::connection();
$customers = sales_list_customers($pdo, ['status' => 'Active']);
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
            <p class="prod-page__sub">Debit / credit entries per customer with running balance.</p>
        </div>
    </header>

    <form method="get" class="sales-filter-bar mb-3">
        <input type="hidden" name="page" value="accounts/ledger">
        <div class="sales-filter-bar__field" style="max-width:320px">
            <label>Customer</label>
            <select class="form-select form-select-sm" name="customer_id" onchange="this.form.submit()">
                <option value="0">Select customer…</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

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
        <div class="sales-card__head"><h2 class="sales-card__title"><?= e($customer['company_name']) ?> — ledger</h2></div>
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Invoice / ref</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
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
    <?php else: ?>
        <p class="text-muted">Select a customer to view ledger.</p>
    <?php endif; ?>
</div>
