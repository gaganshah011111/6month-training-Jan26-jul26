<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';

if (!has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$d = dispatch_dashboard($pdo);
?>

<div class="dsp-page">
    <header class="dsp-page__head">
        <div>
            <h1 class="dsp-page__title">Dispatch Dashboard</h1>
            <p class="dsp-page__sub">Create dispatch — stock is reduced and orders are marked delivered in one step.</p>
        </div>
        <nav class="dsp-nav-quick">
            <a href="<?= e(route_url('dispatch/new')) ?>">New Dispatch</a>
            <a href="<?= e(route_url('dispatch/history')) ?>">History</a>
            <a href="<?= e(route_url('dispatch/logistics')) ?>">Logistics</a>
            <a href="<?= e(route_url('reports/dispatch')) ?>">Reports</a>
        </nav>
    </header>

    <div class="dsp-kpis">
        <article class="dsp-kpi dsp-kpi--qty">
            <div>
                <span class="dsp-kpi__label">Tyres dispatched today</span>
                <span class="dsp-kpi__value"><?= e(dispatch_format_qty($d['today_qty'])) ?></span>
            </div>
        </article>
        <article class="dsp-kpi dsp-kpi--done">
            <div>
                <span class="dsp-kpi__label">Dispatches today</span>
                <span class="dsp-kpi__value"><?= e((string)$d['dispatches_today']) ?></span>
            </div>
        </article>
        <article class="dsp-kpi dsp-kpi--vehicle">
            <div>
                <span class="dsp-kpi__label">Vehicles used today</span>
                <span class="dsp-kpi__value"><?= e((string)$d['vehicles_today']) ?></span>
            </div>
        </article>
    </div>

    <section>
        <h2 class="dsp-section__heading">Recent dispatch</h2>
        <div class="dsp-table-wrap">
            <table class="dsp-table">
                <thead>
                    <tr>
                        <th>Dispatch ID</th><th>Invoice</th><th>Customer</th><th>Vehicle</th>
                        <th class="text-end">Qty</th><th>Driver</th><th>Date</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($d['recent_rows'] as $r): ?>
                    <tr>
                        <td><?= e((string)($r['dispatch_code'] ?? '—')) ?></td>
                        <td><?= e((string)$r['invoice_no']) ?></td>
                        <td><?= e((string)$r['customer_name']) ?></td>
                        <td><?= e((string)($r['vehicle_no'] ?? '—')) ?></td>
                        <td class="text-end"><?= e(dispatch_format_qty((int)$r['qty'])) ?></td>
                        <td><?= e((string)($r['driver_name'] ?? '—')) ?></td>
                        <td><?= e((string)($r['dispatch_date'] ?? '—')) ?></td>
                        <td><span class="dsp-badge dsp-badge--delivered">Delivered</span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($d['recent_rows'] === []): ?>
                    <tr><td colspan="8" class="dsp-empty">No dispatches yet. <a href="<?= e(route_url('dispatch/new')) ?>">Create your first dispatch</a>.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
