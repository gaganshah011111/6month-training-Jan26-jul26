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
$trendLabels = array_column($d['monthly_trend'], 'ym');
$trendValues = array_map(static fn($r) => (float)$r['revenue'], $d['monthly_trend']);
?>

<div class="sales-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Sales Manager Dashboard</h1>
            <p class="prod-page__sub">Sales Manager only — customers, orders, dispatch fulfilment, invoicing, and payments.</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('sales/order')) ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Create Sales Order</a>
            <a href="<?= e(route_url('sales/dispatch-entry')) ?>">Record dispatch</a>
        </nav>
    </header>

    <?php require __DIR__ . '/_nav.php'; ?>

    <?php if ($loadError): ?>
        <?= sales_error_alert('Unable to load dashboard. Some figures may be unavailable.') ?>
    <?php endif; ?>

    <div class="sales-kpis">
        <article class="sales-kpi"><span class="sales-kpi__label">Total customers</span><span class="sales-kpi__value"><?= e((string)$d['total_customers']) ?></span></article>
        <article class="sales-kpi sales-kpi--ok"><span class="sales-kpi__label">Active orders</span><span class="sales-kpi__value"><?= e((string)$d['active_orders']) ?></span></article>
        <article class="sales-kpi sales-kpi--warn"><span class="sales-kpi__label">Pending dispatch</span><span class="sales-kpi__value"><?= e((string)$d['pending_dispatch']) ?></span></article>
        <article class="sales-kpi sales-kpi--ok"><span class="sales-kpi__label">Monthly revenue</span><span class="sales-kpi__value"><?= e(sales_format_money((float)$d['monthly_revenue'])) ?></span></article>
        <article class="sales-kpi sales-kpi--warn"><span class="sales-kpi__label">Pending payments</span><span class="sales-kpi__value"><?= e(sales_format_money((float)$d['pending_payments'])) ?></span></article>
        <article class="sales-kpi sales-kpi--danger"><span class="sales-kpi__label">Overdue</span><span class="sales-kpi__value"><?= e(sales_format_money((float)$d['overdue_payments'])) ?></span></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Top customer</span><span class="sales-kpi__value" style="font-size:.95rem"><?= e($d['top_customer']) ?></span></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Most sold tyre</span><span class="sales-kpi__value" style="font-size:.95rem"><?= e($d['top_tyre']) ?></span></article>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Monthly sales trend</h2></div>
                <div class="sales-card__body sales-chart-wrap">
                    <canvas id="salesTrendChart" height="120" aria-label="Monthly revenue trend"></canvas>
                </div>
            </section>
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Recent sales orders</h2><a class="small" href="<?= e(route_url('sales/orders')) ?>">View all</a></div>
                <div class="sales-table-wrap">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>SO #</th><th>Customer</th><th>Date</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($d['recent_orders'] as $o): ?>
                            <?php $sb = sales_status_badge((string)$o['status']); ?>
                            <tr>
                                <td><a href="<?= e(route_url('sales/order', ['id' => (int)$o['id']])) ?>"><?= e($o['so_number']) ?></a></td>
                                <td><?= e($o['company_name']) ?></td>
                                <td><?= e($o['order_date']) ?></td>
                                <td class="text-end"><?= e(sales_format_money((float)$o['total_amount'])) ?></td>
                                <td><span class="<?= e($sb['class']) ?>"><?= e($sb['label']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$d['recent_orders']): ?><tr><td colspan="5" class="text-center text-muted py-3">No orders yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Payment alerts</h2></div>
                <div class="sales-card__body p-0">
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($d['payment_alerts'] as $a): ?>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><?= e($a['company_name']) ?><br><span class="text-muted"><?= e($a['invoice_no']) ?></span></span>
                                <strong class="text-danger"><?= e(sales_format_money((float)$a['due_amt'])) ?></strong>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$d['payment_alerts']): ?><li class="list-group-item text-muted">No pending payments.</li><?php endif; ?>
                    </ul>
                </div>
            </section>
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Recent invoices</h2><a class="small" href="<?= e(route_url('sales/invoices')) ?>">View all</a></div>
                <div class="sales-table-wrap sales-table-wrap--short">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Invoice</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($d['recent_invoices'] as $inv): ?>
                            <?php $sb = sales_status_badge((string)$inv['payment_status']); ?>
                            <tr>
                                <td><a href="<?= e(route_url('sales/invoice', ['id' => (int)$inv['id']])) ?>"><?= e($inv['invoice_no']) ?></a></td>
                                <td class="text-end"><?= e(sales_format_money((float)$inv['total_amount'])) ?></td>
                                <td><span class="<?= e($sb['class']) ?>"><?= e($sb['label']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$d['recent_invoices']): ?><tr><td colspan="3" class="text-center text-muted py-3">No invoices yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Dispatch status</h2><a class="small" href="<?= e(route_url('sales/dispatch')) ?>">Tracking</a></div>
                <div class="sales-table-wrap sales-table-wrap--short">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Dispatch</th><th>SO</th><th class="text-end">Qty</th></tr></thead>
                        <tbody>
                        <?php foreach ($d['recent_dispatch'] as $dsp): ?>
                            <tr>
                                <td><?= e((string)($dsp['dispatch_code'] ?? '—')) ?></td>
                                <td><?= e((string)($dsp['so_number'] ?? '—')) ?></td>
                                <td class="text-end"><?= e((string)($dsp['alloc_qty'] ?? '0')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$d['recent_dispatch']): ?><tr><td colspan="3" class="text-center text-muted py-3">No linked dispatches yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('salesTrendChart');
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, {
        type: 'line',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [{ label: 'Revenue', data: <?= json_encode($trendValues) ?>, borderColor: '#1a2744', backgroundColor: 'rgba(26,39,68,.08)', fill: true, tension: 0.3 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
});
</script>
