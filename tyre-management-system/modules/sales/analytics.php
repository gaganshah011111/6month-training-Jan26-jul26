<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();
$d = sales_dashboard($pdo);
$loadError = !empty($d['load_error']);
$trendLabels = array_column($d['monthly_trend'], 'ym');
$trendValues = array_map(static fn($r) => (float)$r['revenue'], $d['monthly_trend']);

$dispatchPct = 0.0;
$ord = sales_try(
    static fn() => $pdo->query('SELECT SUM(qty_ordered) AS o, SUM(qty_dispatched) AS d FROM sales_order_items')->fetch(PDO::FETCH_ASSOC),
    null,
    'analytics_dispatch_pct'
);
if (is_array($ord) && (int)($ord['o'] ?? 0) > 0) {
    $dispatchPct = round(100 * (int)$ord['d'] / (int)$ord['o'], 1);
}
?>

<div class="sales-page">
    <header class="prod-page__head"><div><h1 class="prod-page__title">Sales Analytics</h1><p class="prod-page__sub">Revenue trend, top customers, and fulfilment.</p></div></header>
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if ($loadError): ?><?= sales_error_alert('Unable to load analytics. Showing available data only.') ?><?php endif; ?>
    <div class="sales-kpis">
        <article class="sales-kpi sales-kpi--ok"><span class="sales-kpi__label">Monthly revenue</span><span class="sales-kpi__value"><?= e(sales_format_money((float)$d['monthly_revenue'])) ?></span></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Best selling tyre</span><span class="sales-kpi__value" style="font-size:.95rem"><?= e($d['top_tyre']) ?></span></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Top customer</span><span class="sales-kpi__value" style="font-size:.95rem"><?= e($d['top_customer']) ?></span></article>
        <article class="sales-kpi sales-kpi--warn"><span class="sales-kpi__label">Pending dues</span><span class="sales-kpi__value"><?= e(sales_format_money((float)$d['pending_payments'])) ?></span></article>
        <article class="sales-kpi sales-kpi--ok"><span class="sales-kpi__label">Dispatch completion</span><span class="sales-kpi__value"><?= e((string)$dispatchPct) ?>%</span></article>
    </div>
    <section class="sales-card">
        <div class="sales-card__head"><h2 class="sales-card__title">Revenue trend (6 months)</h2></div>
        <div class="sales-card__body sales-chart-wrap"><canvas id="salesAnalyticsChart" height="140"></canvas></div>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('salesAnalyticsChart');
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, { type: 'bar', data: { labels: <?= json_encode($trendLabels) ?>, datasets: [{ label: 'Revenue', data: <?= json_encode($trendValues) ?>, backgroundColor: '#1a2744' }] }, options: { responsive: true, plugins: { legend: { display: false } } } });
});
</script>
