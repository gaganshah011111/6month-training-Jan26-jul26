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

$deptTagClass = static function (string $dept): string {
    return match ($dept) {
        'Mixing' => 'prod-dept-tag prod-dept-tag--mixing',
        'Building' => 'prod-dept-tag prod-dept-tag--building',
        'Curing' => 'prod-dept-tag prod-dept-tag--curing',
        default => 'prod-dept-tag',
    };
};
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Production Dashboard</h1>
            <p class="prod-page__sub">Today’s factory output — mixing, building, and curing only.</p>
        </div>
    </header>

    <?php inv_render_low_stock_banner($pdo); ?>

    <div class="row g-2 prod-dash-kpis mb-3">
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi prod-dept-card--mixing"><span class="prod-dash-kpi__k">Today mixing</span><span class="prod-dash-kpi__v"><?= e((string)$s['mixing_today']) ?> <small>kg</small></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi prod-dept-card--building"><span class="prod-dash-kpi__k">Today building</span><span class="prod-dash-kpi__v"><?= e((string)$s['building_today']) ?></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi prod-dept-card--curing"><span class="prod-dash-kpi__k">Today curing</span><span class="prod-dash-kpi__v"><?= e((string)$s['curing_today']) ?></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Total rejected</span><span class="prod-dash-kpi__v text-danger"><?= e((string)$s['rejected_today']) ?></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Active machines</span><span class="prod-dash-kpi__v"><?= e((string)$s['running_machines']) ?></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Under maintenance</span><span class="prod-dash-kpi__v"><?= e((string)$s['maint_machines']) ?></span></article></div>
        <div class="col-6 col-md-4 col-lg-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Downtime today</span><span class="prod-dash-kpi__v"><?= e((string)$s['downtime_today']) ?> min</span></article></div>
    </div>

    <div class="prod-dept-quick mb-3">
        <a class="prod-dept-quick__item" href="<?= e(route_url('production/mixing')) ?>">Mixing Entry</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('production/building')) ?>">Building Entry</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('production/curing')) ?>">Curing Entry</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('machines/list')) ?>">Machines</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('reports/production')) ?>">Reports</a>
    </div>

    <div class="row g-3 prod-dash-main">
        <div class="col-lg-8">
            <section class="prod-card prod-card--table prod-dash-entries-card">
                <div class="prod-card__head d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="prod-card__title mb-0">Latest production entries</h2>
                    <a class="prod-card__link" href="<?= e(route_url('reports/production')) ?>">Full report →</a>
                </div>
                <div class="prod-dash-table-wrap table-responsive">
                    <table class="table table-sm prod-table mb-0">
                        <thead><tr><th>Date</th><th>Dept</th><th>Shift</th><th>Tyre</th><th class="text-end">Output</th><th class="text-end">Rej.</th><th>Machine</th><th>Operator</th></tr></thead>
                        <tbody>
                        <?php foreach ($s['recent'] as $r): ?>
                            <tr>
                                <td><?= e($r['dt']) ?></td>
                                <td><span class="<?= e($deptTagClass((string)$r['department'])) ?>"><?= e($r['department']) ?></span></td>
                                <td><?= e($r['shift'] ?? '—') ?></td>
                                <td><?= e($r['tyre_type'] ?? '—') ?></td>
                                <td class="text-end"><?= e((string)($r['produced_qty'] ?? 0)) ?></td>
                                <td class="text-end"><?= e((string)($r['rejected_qty'] ?? 0)) ?></td>
                                <td><?= e($r['machine_code'] ?? '—') ?></td>
                                <td><?= e($r['op'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$s['recent']): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No production entries yet. Use Mixing, Building, or Curing entry.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($s['recent']): ?>
                    <p class="prod-dash-table-hint mb-0">Mixing, building &amp; curing only · scroll for more rows</p>
                <?php endif; ?>
            </section>
        </div>
        <div class="col-lg-4 prod-dash-aside">
            <section class="prod-card prod-dash-status mb-3">
                <div class="prod-card__head"><h2 class="prod-card__title mb-0">Shop floor status</h2></div>
                <div class="prod-dash-status__grid">
                    <div class="prod-dash-status__item">
                        <span class="prod-dash-status__k">Running</span>
                        <span class="prod-dash-status__v text-success"><?= e((string)$s['running_machines']) ?></span>
                    </div>
                    <div class="prod-dash-status__item">
                        <span class="prod-dash-status__k">Maintenance</span>
                        <span class="prod-dash-status__v <?= (int)$s['maint_machines'] > 0 ? 'text-warning' : '' ?>"><?= e((string)$s['maint_machines']) ?></span>
                    </div>
                    <div class="prod-dash-status__item">
                        <span class="prod-dash-status__k">Downtime</span>
                        <span class="prod-dash-status__v"><?= e((string)$s['downtime_today']) ?> min</span>
                    </div>
                    <div class="prod-dash-status__item">
                        <span class="prod-dash-status__k">Rejected</span>
                        <span class="prod-dash-status__v text-danger"><?= e((string)$s['rejected_today']) ?></span>
                    </div>
                </div>
            </section>
            <?php if ($s['machine_alerts']): ?>
                <section class="prod-card mb-3">
                    <div class="prod-card__head"><h2 class="prod-card__title text-warning mb-0">Machine alerts</h2></div>
                    <ul class="list-unstyled small mb-0 px-3 pb-3">
                        <?php foreach ($s['machine_alerts'] as $a): ?>
                            <li class="py-2 border-bottom"><?= e($a) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php elseif ((int)$s['maint_machines'] === 0): ?>
                <section class="prod-card prod-dash-ok mb-3">
                    <p class="small mb-0 px-3 py-3"><i class="bi bi-check-circle text-success me-1"></i> No machines flagged for maintenance.</p>
                </section>
            <?php endif; ?>
            <?php if ((int)$s['downtime_today'] > 0): ?>
                <section class="prod-card">
                    <div class="prod-card__head"><h2 class="prod-card__title mb-0">Downtime alert</h2></div>
                    <p class="small px-3 pb-3 mb-0"><?= e((string)$s['downtime_today']) ?> minutes logged in curing today. <a href="<?= e(route_url('production/curing')) ?>">Review curing entries</a>.</p>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
