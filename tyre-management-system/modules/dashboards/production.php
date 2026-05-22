<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_entries.php';
require_once __DIR__ . '/../../includes/inventory_service.php';

if (!has_role(['Production Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$s = prod_entry_dashboard($pdo);
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Production Dashboard</h1>
            <p class="prod-page__sub">Today’s factory output — enter production in each department module.</p>
        </div>
    </header>

    <?php inv_render_low_stock_banner($pdo); ?>

    <div class="row g-2 prod-dash-kpis mb-3">
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Today mixing</span><span class="prod-dash-kpi__v"><?= e((string)$s['mixing_today']) ?> <small>kg</small></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Today building</span><span class="prod-dash-kpi__v"><?= e((string)$s['building_today']) ?></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Today curing</span><span class="prod-dash-kpi__v"><?= e((string)$s['curing_today']) ?></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">QC passed</span><span class="prod-dash-kpi__v"><?= e((string)$s['qc_passed_today']) ?></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Total rejected</span><span class="prod-dash-kpi__v text-danger"><?= e((string)$s['rejected_today']) ?></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Active machines</span><span class="prod-dash-kpi__v"><?= e((string)$s['running_machines']) ?></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Under maintenance</span><span class="prod-dash-kpi__v"><?= e((string)$s['maint_machines']) ?></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Downtime today</span><span class="prod-dash-kpi__v"><?= e((string)$s['downtime_today']) ?> min</span></article></div>
    </div>

    <div class="prod-dept-quick mb-3">
        <a class="prod-dept-quick__item" href="<?= e(route_url('production/mixing')) ?>">Mixing Entry</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('production/building')) ?>">Building Entry</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('production/curing')) ?>">Curing Entry</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('production/qc')) ?>">QC Entry</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('machines/list')) ?>">Machines</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('reports/production')) ?>">Reports</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <section class="prod-card prod-card--table">
                <div class="prod-card__head"><h2 class="prod-card__title">Latest production entries</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm prod-table mb-0">
                        <thead><tr><th>Date</th><th>Dept</th><th>Shift</th><th>Tyre</th><th class="text-end">Output</th><th class="text-end">Rej.</th><th>Machine</th><th>Operator</th></tr></thead>
                        <tbody>
                        <?php foreach ($s['recent'] as $r): ?>
                            <tr>
                                <td><?= e($r['dt']) ?></td>
                                <td><?= e($r['department']) ?></td>
                                <td><?= e($r['shift'] ?? '—') ?></td>
                                <td><?= e($r['tyre_type'] ?? '—') ?></td>
                                <td class="text-end"><?= e((string)($r['produced_qty'] ?? 0)) ?></td>
                                <td class="text-end"><?= e((string)($r['rejected_qty'] ?? 0)) ?></td>
                                <td><?= e($r['machine_code'] ?? '—') ?></td>
                                <td><?= e($r['op'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$s['recent']): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No production entries found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <?php if ($s['machine_alerts']): ?>
                <section class="prod-card mb-3">
                    <div class="prod-card__head"><h2 class="prod-card__title text-warning">Machine alerts</h2></div>
                    <ul class="list-unstyled small mb-0 px-3 pb-2">
                        <?php foreach ($s['machine_alerts'] as $a): ?>
                            <li class="py-1 border-bottom"><?= e($a) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
            <?php if ((int)$s['downtime_today'] > 0): ?>
                <section class="prod-card">
                    <div class="prod-card__head"><h2 class="prod-card__title">Downtime alert</h2></div>
                    <p class="small px-3 pb-2 mb-0"><?= e((string)$s['downtime_today']) ?> minutes logged in curing today. Check curing entries.</p>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
