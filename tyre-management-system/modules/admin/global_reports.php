<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_control_center.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
$r = admin_executive_reports($pdo);
?>
<div class="admin-cc module-shell" id="adminGlobalReports">
    <?php admin_page_head('Global Reports', 'Read-only enterprise analytics — no approval or transaction actions'); ?>

    <section class="admin-exec-grid">
        <div class="admin-exec-metric admin-exec-metric--green"><span>Revenue (MTD)</span><strong><?= e(sales_format_money((float)$r['revenue'])) ?></strong></div>
        <div class="admin-exec-metric admin-exec-metric--red"><span>Expenses (MTD)</span><strong><?= e(sales_format_money((float)$r['expenses'])) ?></strong></div>
        <div class="admin-exec-metric"><span>Profit (MTD)</span><strong><?= e(sales_format_money((float)$r['profit'])) ?></strong></div>
        <div class="admin-exec-metric"><span>Customers</span><strong><?= (int)$r['customers'] ?></strong></div>
        <div class="admin-exec-metric"><span>Suppliers</span><strong><?= (int)$r['suppliers'] ?></strong></div>
        <div class="admin-exec-metric"><span>Inventory Value</span><strong><?= e(sales_format_money((float)$r['inventory_value'])) ?></strong></div>
        <div class="admin-exec-metric"><span>Payroll Cost</span><strong><?= e(sales_format_money((float)$r['payroll_cost'])) ?></strong></div>
        <div class="admin-exec-metric admin-exec-metric--warn"><span>Receivables</span><strong><?= e(sales_format_money((float)$r['receivables'])) ?></strong></div>
        <div class="admin-exec-metric"><span>Payables</span><strong><?= e(sales_format_money((float)$r['payables'])) ?></strong></div>
    </section>

    <section class="admin-card">
        <h2 class="admin-card__title">Drill-down Reports</h2>
        <div class="admin-quick-links">
            <a href="<?= e(route_url('accounts/reports')) ?>">Accounts Analytics</a>
            <a href="<?= e(route_url('reports/hr')) ?>">HR Reports</a>
            <a href="<?= e(route_url('sales/reports')) ?>">Sales Reports</a>
            <a href="<?= e(route_url('reports/inventory')) ?>">Inventory Reports</a>
            <a href="<?= e(route_url('reports/production')) ?>">Production Reports</a>
            <a href="<?= e(route_url('reports/dispatch')) ?>">Dispatch Reports</a>
        </div>
    </section>
</div>
