<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();
sales_try(static fn() => sales_seed_demo_data($pdo), null, 'seed_demo');
$d = sales_dashboard($pdo);
$loadError = !empty($d['load_error']);

$alerts = array_values(array_filter(
    $d['payment_alerts'] ?? [],
    static fn($a) => (string)($a['payment_status'] ?? '') === 'Overdue'
        || ((string)($a['due_date'] ?? '') !== '' && (string)($a['due_date'] ?? '') < date('Y-m-d'))
));
if ($alerts === []) {
    $alerts = array_slice($d['payment_alerts'] ?? [], 0, 6);
}
?>

<div class="sales-page crm-layout">
    <?= crm_page_header('Dashboard', 'Sales workflow at a glance — orders, dispatch, invoices, and collections.') ?>

    <?php if ($loadError): ?>
        <?= sales_error_alert('Unable to load dashboard. Some figures may be unavailable.') ?>
    <?php endif; ?>

    <?= crm_quick_actions([
        ['label' => 'Create SO', 'url' => route_url('sales/order'), 'icon' => 'bi-plus-lg', 'primary' => true],
        ['label' => 'Invoices', 'url' => route_url('sales/invoices'), 'icon' => 'bi-receipt'],
        ['label' => 'Record payment', 'url' => route_url('sales/payments'), 'icon' => 'bi-cash-stack'],
        ['label' => 'Dispatch tracking', 'url' => route_url('sales/dispatch'), 'icon' => 'bi-truck'],
    ]) ?>

    <div class="crm-summary-4">
        <article class="sales-kpi"><span class="sales-kpi__label">Active orders</span><strong><?= e((string)$d['active_orders']) ?></strong></article>
        <article class="sales-kpi sales-kpi--warn"><span class="sales-kpi__label">Pending dispatch</span><strong><?= e((string)$d['pending_dispatch']) ?></strong></article>
        <article class="sales-kpi sales-kpi--warn"><span class="sales-kpi__label">Pending payments</span><strong><?= e(sales_format_money((float)$d['pending_payments'])) ?></strong></article>
        <article class="sales-kpi sales-kpi--ok"><span class="sales-kpi__label">Monthly revenue</span><strong><?= e(sales_format_money((float)$d['monthly_revenue'])) ?></strong></article>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="crm-section">
                <div class="crm-section__head">
                    <h2 class="crm-section__title">Recent activity</h2>
                    <a class="small text-decoration-none" href="<?= e(route_url('sales/orders')) ?>">All orders</a>
                </div>
                <div class="crm-section__body">
                    <?= crm_table_open('crm-dash-activity') ?>
                    <thead><tr><th>Reference</th><th>Customer</th><th>Date</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($d['recent_orders'], 0, 8) as $o): ?>
                        <?php $sb = sales_order_status_badge((string)$o['status']); ?>
                        <tr>
                            <td><a href="<?= e(route_url('sales/order', ['id' => (int)$o['id']])) ?>"><?= e($o['so_number']) ?></a></td>
                            <td><?= e($o['company_name']) ?></td>
                            <td><?= e($o['order_date']) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$o['total_amount'])) ?></td>
                            <td><span class="<?= e($sb['class']) ?>"><?= e($sb['label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$d['recent_orders']): ?>
                        <tr><td colspan="5" class="sales-empty text-center py-4">No recent orders.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <?= crm_table_close() ?>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="crm-section">
                <div class="crm-section__head">
                    <h2 class="crm-section__title">Important alerts</h2>
                    <a class="small text-decoration-none" href="<?= e(route_url('sales/payments')) ?>">Payments</a>
                </div>
                <div class="crm-section__body">
                    <ul class="crm-alerts-list">
                        <?php foreach ($alerts as $a): ?>
                            <li>
                                <span>
                                    <strong><?= e($a['company_name']) ?></strong><br>
                                    <span class="text-muted"><?= e($a['invoice_no']) ?> · due <?= e((string)($a['due_date'] ?? '—')) ?></span>
                                </span>
                                <strong class="text-danger"><?= e(sales_format_money((float)$a['due_amt'])) ?></strong>
                            </li>
                        <?php endforeach; ?>
                        <?php if ($alerts === []): ?>
                            <li class="text-muted justify-content-center">No overdue or pending alerts.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </section>
        </div>
    </div>
</div>
