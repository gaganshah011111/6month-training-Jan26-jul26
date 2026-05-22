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
            <p class="dsp-page__sub">Finished goods shipping — Production → Inventory → Dispatch → Delivery</p>
        </div>
        <nav class="dsp-nav-quick">
            <a href="<?= e(route_url('dispatch/new')) ?>">New Dispatch</a>
            <a href="<?= e(route_url('dispatch/history')) ?>">History</a>
            <a href="<?= e(route_url('dispatch/customers')) ?>">Customers</a>
            <a href="<?= e(route_url('reports/dispatch')) ?>">Reports</a>
        </nav>
    </header>

    <div class="dsp-kpis">
        <article class="dsp-kpi dsp-kpi--qty">
            <div>
                <span class="dsp-kpi__label">Today dispatch qty</span>
                <span class="dsp-kpi__value"><?= e(dispatch_format_qty($d['today_qty'])) ?></span>
            </div>
        </article>
        <article class="dsp-kpi dsp-kpi--pending">
            <div>
                <span class="dsp-kpi__label">Pending dispatch</span>
                <span class="dsp-kpi__value"><?= e((string)$d['pending_count']) ?></span>
            </div>
        </article>
        <article class="dsp-kpi dsp-kpi--done">
            <div>
                <span class="dsp-kpi__label">Delivered today</span>
                <span class="dsp-kpi__value"><?= e((string)$d['delivered_today']) ?></span>
            </div>
        </article>
        <article class="dsp-kpi dsp-kpi--vehicle">
            <div>
                <span class="dsp-kpi__label">Vehicles out</span>
                <span class="dsp-kpi__value"><?= e((string)$d['vehicles_out']) ?></span>
            </div>
        </article>
    </div>

    <section class="mb-3">
        <h2 class="dsp-section__heading">Pending dispatch</h2>
        <div class="dsp-table-wrap">
            <table class="dsp-table">
                <thead>
                    <tr>
                        <th>Order no</th><th>Customer</th><th>Tyre type</th><th class="text-end">Qty</th>
                        <th>Dispatch date</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($d['pending_rows'] as $r): ?>
                    <tr>
                        <td><?= e((string)($r['order_no'] ?? $r['dispatch_code'])) ?></td>
                        <td><?= e((string)$r['customer_name']) ?></td>
                        <td><?= e((string)$r['tyre_type']) ?></td>
                        <td class="text-end"><?= e(dispatch_format_qty((int)$r['qty'])) ?></td>
                        <td><?= e((string)$r['dispatch_date']) ?></td>
                        <td><span class="dsp-badge dsp-badge--pending">Pending</span></td>
                        <td>
                            <form method="post" action="<?= e(route_url('dispatch/history')) ?>" class="d-inline">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="dispatch">
                                <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-link btn-sm p-0">Ship</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($d['pending_rows'] === []): ?>
                    <tr><td colspan="7" class="dsp-empty">No pending dispatch orders.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2 class="dsp-section__heading">Recent dispatch</h2>
        <div class="dsp-table-wrap">
            <table class="dsp-table">
                <thead>
                    <tr>
                        <th>Invoice no</th><th>Customer</th><th>Vehicle</th><th class="text-end">Qty</th>
                        <th>Driver</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($d['recent_rows'] as $r): ?>
                    <tr>
                        <td><?= e((string)$r['invoice_no']) ?></td>
                        <td><?= e((string)$r['customer_name']) ?></td>
                        <td><?= e((string)($r['vehicle_no'] ?? '—')) ?></td>
                        <td class="text-end"><?= e(dispatch_format_qty((int)$r['qty'])) ?></td>
                        <td><?= e((string)($r['driver_name'] ?? '—')) ?></td>
                        <td><span class="dsp-badge dsp-badge--<?= e(dispatch_status_badge((string)$r['status'])) ?>"><?= e((string)$r['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($d['recent_rows'] === []): ?>
                    <tr><td colspan="6" class="dsp-empty">No recent dispatches yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
