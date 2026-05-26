<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_finance.php';

$pdo = Database::connection();
$dash = acc_dashboard_data($pdo);
$customerPayments = $dash['recent_customer_payments'] ?? [];
$supplierPayments = $dash['recent_supplier_payments'] ?? [];
$alerts = $dash['alerts'] ?? [];
$revTrend = $dash['revenue_trend'] ?? [];
$expTrend = $dash['expense_trend'] ?? [];
$rp = $dash['receivable_vs_payable'] ?? ['receivable' => 0, 'payable' => 0];
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Accounts &amp; Finance Dashboard</h1>
            <p class="prod-page__sub">Receivables, payables, cash/bank, expenses, and finance alerts in one ERP panel.</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('accounts/receivables')) ?>">Receivables</a>
            <a href="<?= e(route_url('accounts/payables')) ?>">Payables</a>
            <a href="<?= e(route_url('accounts/reports')) ?>">Reports</a>
        </nav>
    </header>

    <div class="sales-kpis accounts-kpis">
        <article class="sales-kpi"><span class="sales-kpi__label">Total receivables</span><strong class="text-warning"><?= e(sales_format_money((float)$dash['total_receivables'])) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Total payables</span><strong class="text-danger"><?= e(sales_format_money((float)$dash['total_payables'])) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Cash in hand</span><strong><?= e(sales_format_money((float)$dash['cash_in_hand'])) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Bank balance</span><strong><?= e(sales_format_money((float)$dash['bank_balance'])) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Monthly revenue</span><strong class="text-success"><?= e(sales_format_money((float)$dash['monthly_revenue'])) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Monthly expenses</span><strong><?= e(sales_format_money((float)$dash['monthly_expenses'])) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Estimated profit</span><strong class="<?= (float)$dash['estimated_profit'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= e(sales_format_money((float)$dash['estimated_profit'])) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Overdue payments</span><strong class="text-danger"><?= e(sales_format_money((float)$dash['overdue_payments'])) ?></strong></article>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="sales-card">
                <div class="sales-card__head d-flex justify-content-between align-items-center">
                    <h2 class="sales-card__title mb-0">Recent customer payments</h2>
                    <a href="<?= e(route_url('accounts/receivables')) ?>" class="small">Receivables</a>
                </div>
                <div class="sales-table-wrap sales-table-scroll">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Date</th><th>Customer</th><th>Ref</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($customerPayments as $r): ?>
                            <tr>
                                <td><?= e((string)$r['payment_date']) ?></td>
                                <td><?= e((string)$r['company_name']) ?></td>
                                <td><?= e((string)$r['invoice_no']) ?></td>
                                <td class="text-end text-success fw-semibold"><?= e(sales_format_money((float)$r['amount'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($customerPayments === []): ?><tr><td colspan="4" class="sales-empty">No customer payments yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="sales-card">
                <div class="sales-card__head d-flex justify-content-between align-items-center">
                    <h2 class="sales-card__title mb-0">Recent supplier payments</h2>
                    <a href="<?= e(route_url('accounts/payables')) ?>" class="small">Payables</a>
                </div>
                <div class="sales-table-wrap sales-table-scroll">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Date</th><th>Supplier</th><th>PINV</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($supplierPayments as $s): ?>
                            <tr>
                                <td><?= e((string)$s['payment_date']) ?></td>
                                <td><?= e((string)($s['supplier_name'] ?? '—')) ?></td>
                                <td><?= e((string)($s['pinv_no'] ?? '—')) ?></td>
                                <td class="text-end text-danger fw-semibold"><?= e(sales_format_money((float)$s['amount'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($supplierPayments === []): ?><tr><td colspan="4" class="sales-empty">No supplier payments yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="sales-card h-100">
                <div class="sales-card__head"><h2 class="sales-card__title mb-0">Finance alerts</h2></div>
                <div class="sales-card__body small">
                    <ul class="mb-0">
                        <?php foreach ($alerts as $a): ?>
                            <li class="<?= $a['level'] === 'danger' ? 'text-danger' : 'text-warning' ?> mb-1"><?= e((string)$a['text']) ?></li>
                        <?php endforeach; ?>
                        <?php if ($alerts === []): ?><li class="text-success">No critical finance alerts.</li><?php endif; ?>
                        <li class="mt-1">Pending supplier payable: <strong><?= e(sales_format_money((float)$dash['pending_supplier_payables'])) ?></strong></li>
                        <li>Overdue suppliers: <strong><?= e((string)($dash['overdue_suppliers'] ?? 0)) ?></strong></li>
                    </ul>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-4">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Revenue trend</h2></div>
                <div class="sales-card__body">
                    <div class="accounts-bar-chart">
                        <?php
                        $maxRev = max(1.0, ...array_map(static fn($r) => (float)$r['amount'], $revTrend ?: [['amount' => 1]]));
                        foreach ($revTrend as $row):
                            $pct = min(100, (int)round(((float)$row['amount'] / $maxRev) * 100));
                        ?>
                            <div class="accounts-bar-chart__row">
                                <span class="accounts-bar-chart__label"><?= e((string)$row['ym']) ?></span>
                                <div class="accounts-bar-chart__track"><div class="accounts-bar-chart__fill" style="width:<?= $pct ?>%"></div></div>
                                <span class="accounts-bar-chart__val"><?= e(sales_format_money((float)$row['amount'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($revTrend === []): ?><p class="small text-muted mb-0">No revenue trend yet.</p><?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Expense trend</h2></div>
                <div class="sales-card__body">
                    <div class="accounts-bar-chart">
                        <?php
                        $maxExp = max(1.0, ...array_map(static fn($r) => (float)$r['amount'], $expTrend ?: [['amount' => 1]]));
                        foreach ($expTrend as $row):
                            $pct = min(100, (int)round(((float)$row['amount'] / $maxExp) * 100));
                        ?>
                            <div class="accounts-bar-chart__row">
                                <span class="accounts-bar-chart__label"><?= e((string)$row['ym']) ?></span>
                                <div class="accounts-bar-chart__track"><div class="accounts-bar-chart__fill bg-warning" style="width:<?= $pct ?>%"></div></div>
                                <span class="accounts-bar-chart__val"><?= e(sales_format_money((float)$row['amount'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($expTrend === []): ?><p class="small text-muted mb-0">No expense trend yet.</p><?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Receivable vs payable</h2></div>
                <div class="sales-card__body">
                    <?php $maxRp = max(1.0, (float)$rp['receivable'], (float)$rp['payable']); ?>
                    <div class="accounts-bar-chart__row">
                        <span class="accounts-bar-chart__label">Receivable</span>
                        <div class="accounts-bar-chart__track"><div class="accounts-bar-chart__fill bg-success" style="width:<?= (int)round(((float)$rp['receivable'] / $maxRp) * 100) ?>%"></div></div>
                        <span class="accounts-bar-chart__val"><?= e(sales_format_money((float)$rp['receivable'])) ?></span>
                    </div>
                    <div class="accounts-bar-chart__row mb-0">
                        <span class="accounts-bar-chart__label">Payable</span>
                        <div class="accounts-bar-chart__track"><div class="accounts-bar-chart__fill bg-danger" style="width:<?= (int)round(((float)$rp['payable'] / $maxRp) * 100) ?>%"></div></div>
                        <span class="accounts-bar-chart__val"><?= e(sales_format_money((float)$rp['payable'])) ?></span>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
