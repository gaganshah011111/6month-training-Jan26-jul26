<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_control_center.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
$b = admin_dashboard_bundle($pdo);
$k = $b['kpis'];
$h = $b['health'];
$a = $b['activity'];
$alerts = $b['alerts'];
$quickActions = admin_quick_actions();
?>
<div class="admin-cc module-shell" id="adminControlCenter">
    <?php admin_page_head('ERP Control Center', 'Command, monitor, and configure the entire ERP — no operational approval layer'); ?>

    <section class="admin-quick-actions" aria-label="Quick actions">
        <?php foreach ($quickActions as $qa): ?>
            <a href="<?= e($qa['url']) ?>" class="admin-quick-card admin-quick-card--<?= e($qa['tone']) ?>">
                <i class="bi <?= e($qa['icon']) ?>"></i>
                <span><?= e($qa['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="admin-cc__kpis" aria-label="Organization overview">
        <div class="admin-kpi"><span>Total Users</span><strong><?= (int)$k['users'] ?></strong><a href="<?= e(route_url('admin/users')) ?>">Manage</a></div>
        <div class="admin-kpi"><span>Total Employees</span><strong><?= (int)$k['employees'] ?></strong><a href="<?= e(route_url('admin/employee-oversight')) ?>">Oversight</a></div>
        <div class="admin-kpi"><span>Departments</span><strong><?= (int)$k['departments'] ?></strong><a href="<?= e(route_url('admin/departments')) ?>">Manage</a></div>
        <div class="admin-kpi admin-kpi--green"><span>Revenue (MTD)</span><strong><?= e(sales_format_money((float)$k['revenue'])) ?></strong></div>
        <div class="admin-kpi admin-kpi--red"><span>Expenses (MTD)</span><strong><?= e(sales_format_money((float)$k['expenses'])) ?></strong></div>
        <div class="admin-kpi"><span>Profit (MTD)</span><strong><?= e(sales_format_money((float)$k['profit'])) ?></strong></div>
    </section>

    <section class="admin-cc__kpis admin-cc__kpis--finance" aria-label="Financial & operations">
        <div class="admin-kpi admin-kpi--warn"><span>Receivables</span><strong><?= e(sales_format_money((float)$k['receivables'])) ?></strong><a href="<?= e(route_url('admin/finance-oversight')) ?>">View</a></div>
        <div class="admin-kpi"><span>Payables</span><strong><?= e(sales_format_money((float)$k['payables'])) ?></strong><a href="<?= e(route_url('accounts/payables')) ?>">View</a></div>
        <div class="admin-kpi admin-kpi--green"><span>Cash Balance</span><strong><?= e(sales_format_money((float)$k['cash'])) ?></strong><a href="<?= e(route_url('accounts/cashbook')) ?>">Treasury</a></div>
        <div class="admin-kpi"><span>Recent Logins</span><strong><?= (int)$k['today_logins'] ?></strong><span class="admin-kpi__hint">Today</span></div>
        <div class="admin-kpi admin-kpi--warn"><span>Low Stock Alerts</span><strong><?= (int)$k['low_stock'] ?></strong><a href="<?= e(route_url('inventory/materials')) ?>">Materials</a></div>
        <div class="admin-kpi"><span>Pending Dispatch</span><strong><?= (int)$k['pending_dispatch'] ?></strong><a href="<?= e(route_url('dispatch/history')) ?>">Dispatch</a></div>
    </section>

    <section class="admin-cc__kpis admin-cc__kpis--dues" aria-label="Outstanding dues">
        <div class="admin-kpi"><span>Open Customer Dues</span><strong><?= e(sales_format_money((float)$k['customer_dues'])) ?></strong></div>
        <div class="admin-kpi"><span>Open Supplier Dues</span><strong><?= e(sales_format_money((float)$k['supplier_dues'])) ?></strong></div>
    </section>

    <div class="admin-cc__grid admin-cc__grid--3">
        <section class="admin-card">
            <div class="admin-card__head"><h2 class="admin-card__title mb-0">System Health</h2><a href="<?= e(route_url('admin/system-health')) ?>" class="admin-link-btn">Details</a></div>
            <div class="admin-health-list">
                <?php foreach ($h as $mod): ?>
                    <div class="admin-health-pill admin-health-pill--<?= e($mod['level']) ?>">
                        <span class="admin-health-pill__dot"></span>
                        <div><strong><?= e($mod['label']) ?></strong><small><?= e($mod['health']) ?> · <?= (int)$mod['records'] ?> records</small></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="admin-card">
            <div class="admin-card__head"><h2 class="admin-card__title mb-0">Recent Activity</h2><a href="<?= e(route_url('admin/activity-logs')) ?>" class="admin-link-btn">Audit center</a></div>
            <?php admin_render_timeline(array_slice($a, 0, 8)); ?>
        </section>
        <section class="admin-card">
            <div class="admin-card__head"><h2 class="admin-card__title mb-0">Alert Center</h2><span class="admin-card__hint-inline">Monitor · act when needed</span></div>
            <ul class="admin-notify-list">
                <?php foreach ($alerts as $note): ?>
                    <li class="admin-notify admin-notify--<?= e($note['type']) ?>">
                        <a href="<?= e($note['url']) ?>">
                            <strong><?= e($note['title']) ?></strong>
                            <span><?= e($note['message']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <section class="admin-card mt-3">
        <h2 class="admin-card__title">Oversight Modules</h2>
        <div class="admin-oversight-links">
            <a href="<?= e(route_url('admin/employee-oversight')) ?>" class="admin-oversight-link"><i class="bi bi-person-badge"></i> Employee Oversight</a>
            <a href="<?= e(route_url('admin/sales-oversight')) ?>" class="admin-oversight-link"><i class="bi bi-graph-up"></i> Sales Oversight</a>
            <a href="<?= e(route_url('admin/purchase-oversight')) ?>" class="admin-oversight-link"><i class="bi bi-truck"></i> Purchase Oversight</a>
            <a href="<?= e(route_url('admin/finance-oversight')) ?>" class="admin-oversight-link"><i class="bi bi-bank2"></i> Finance Oversight</a>
        </div>
    </section>
</div>
