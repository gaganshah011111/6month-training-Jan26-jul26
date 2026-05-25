<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_service.php';

$pdo = Database::connection();
$dash = sales_accounts_dashboard($pdo);
$trend = $dash['trend'] ?? [];
$top = $dash['top_receivables'] ?? [];
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Accounts Dashboard</h1>
            <p class="prod-page__sub">Financial overview — sales, collections, pending receivables, and profit snapshot.</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('accounts/receivables')) ?>">Receivables</a>
            <a href="<?= e(route_url('sales/payments')) ?>">Record payment</a>
        </nav>
    </header>

    <div class="sales-kpis accounts-kpis">
        <article class="sales-kpi"><span class="sales-kpi__label">Total sales</span><strong><?= e(sales_format_money((float)$dash['total_sales'])) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Total collected</span><strong class="text-success"><?= e(sales_format_money((float)$dash['total_received'])) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Outstanding</span><strong class="text-warning"><?= e(sales_format_money((float)$dash['total_pending'])) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Overdue</span><strong class="text-danger"><?= e(sales_format_money((float)$dash['overdue'])) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">GST collected</span><strong><?= e(sales_format_money((float)($dash['gst_payable'] ?? 0))) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Monthly revenue</span><strong><?= e(sales_format_money((float)$dash['monthly_revenue'])) ?></strong></article>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Monthly sales (6 months)</h2></div>
                <div class="sales-card__body">
                    <?php if ($trend === []): ?>
                        <p class="text-muted small mb-0">No invoice data yet.</p>
                    <?php else: ?>
                    <div class="accounts-bar-chart">
                        <?php
                        $max = max(1.0, ...array_map(static fn($r) => (float)$r['amount'], $trend));
                        foreach ($trend as $row):
                            $pct = min(100, (int)round(((float)$row['amount'] / $max) * 100));
                        ?>
                        <div class="accounts-bar-chart__row">
                            <span class="accounts-bar-chart__label"><?= e($row['label']) ?></span>
                            <div class="accounts-bar-chart__track"><div class="accounts-bar-chart__fill" style="width:<?= $pct ?>%"></div></div>
                            <span class="accounts-bar-chart__val"><?= e(sales_format_money((float)$row['amount'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Top pending receivables</h2></div>
                <div class="sales-table-wrap sales-table-scroll">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Customer</th><th class="text-end">Pending</th></tr></thead>
                        <tbody>
                        <?php foreach ($top as $c): ?>
                            <tr>
                                <td><a href="<?= e(route_url('accounts/ledger', ['customer_id' => (int)$c['id']])) ?>"><?= e($c['company_name']) ?></a></td>
                                <td class="text-end"><?= e(sales_format_money((float)$c['pending'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($top === []): ?>
                            <tr><td colspan="2" class="sales-empty">No outstanding balances.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="sales-card mt-3">
                <div class="sales-card__head"><h2 class="sales-card__title">Collection trend</h2></div>
                <div class="sales-card__body small">
                    <p class="mb-1">Today: <strong><?= e(sales_format_money((float)($dash['payment']['today'] ?? 0))) ?></strong></p>
                    <p class="mb-0">This month: <strong><?= e(sales_format_money((float)($dash['payment']['month'] ?? 0))) ?></strong></p>
                </div>
            </section>
        </div>
    </div>
</div>
