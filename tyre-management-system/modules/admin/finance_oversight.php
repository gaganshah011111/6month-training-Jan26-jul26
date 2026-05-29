<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_oversight_service.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
$k = admin_oversight_finance_kpis($pdo);
?>
<div class="admin-cc module-shell">
    <?php admin_page_head('Finance Oversight', 'Enterprise financial visibility — read-only monitoring with drill-down links'); ?>

    <section class="admin-cc__kpis admin-cc__kpis--5">
        <div class="admin-kpi admin-kpi--warn"><span>Receivables</span><strong><?= e(sales_format_money((float)$k['receivables'])) ?></strong></div>
        <div class="admin-kpi"><span>Payables</span><strong><?= e(sales_format_money((float)$k['payables'])) ?></strong></div>
        <div class="admin-kpi admin-kpi--green"><span>Cash Balance</span><strong><?= e(sales_format_money((float)$k['cash'])) ?></strong></div>
        <div class="admin-kpi admin-kpi--red"><span>Expenses (MTD)</span><strong><?= e(sales_format_money((float)$k['expenses_mtd'])) ?></strong></div>
        <div class="admin-kpi"><span>Payroll Pending</span><strong><?= (int)$k['salary_pending'] ?></strong></div>
    </section>

    <section class="admin-card">
        <h2 class="admin-card__title">Financial Modules</h2>
        <div class="admin-oversight-links">
            <a href="<?= e(route_url('accounts/receivables')) ?>" class="admin-oversight-link"><i class="bi bi-hourglass-split"></i> Receivables</a>
            <a href="<?= e(route_url('accounts/payables')) ?>" class="admin-oversight-link"><i class="bi bi-journal-arrow-down"></i> Payables</a>
            <a href="<?= e(route_url('accounts/expenses')) ?>" class="admin-oversight-link"><i class="bi bi-wallet2"></i> Expenses</a>
            <a href="<?= e(route_url('accounts/salary-payments')) ?>" class="admin-oversight-link"><i class="bi bi-cash-coin"></i> Salary Payments</a>
            <a href="<?= e(route_url('accounts/cashbook')) ?>" class="admin-oversight-link"><i class="bi bi-bank"></i> Cash & Bank</a>
            <a href="<?= e(route_url('accounts/transactions-history')) ?>" class="admin-oversight-link"><i class="bi bi-clock-history"></i> Transaction History</a>
            <a href="<?= e(route_url('accounts/reports')) ?>" class="admin-oversight-link"><i class="bi bi-graph-up"></i> Financial Reports</a>
            <a href="<?= e(route_url('admin/reports')) ?>" class="admin-oversight-link"><i class="bi bi-bar-chart"></i> Global Reports</a>
        </div>
    </section>

    <section class="admin-cc__grid admin-cc__grid--2">
        <div class="admin-kpi admin-kpi--inline"><span>Revenue (MTD)</span><strong><?= e(sales_format_money((float)$k['revenue'])) ?></strong></div>
        <div class="admin-kpi admin-kpi--inline"><span>Profit (MTD)</span><strong><?= e(sales_format_money((float)$k['profit'])) ?></strong></div>
        <?php if ((float)$k['loans'] > 0): ?>
        <div class="admin-kpi admin-kpi--inline"><span>Outstanding Loans</span><strong><?= e(sales_format_money((float)$k['loans'])) ?></strong></div>
        <?php endif; ?>
    </section>
</div>
