<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_service.php';
require_once __DIR__ . '/../../includes/production_departments.php';

if (!has_role(['Production Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$s = prod_department_dashboard($pdo);
$recentOrders = prod_list_master_orders($pdo, 6);
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Production Dashboard</h1>
            <p class="prod-page__sub">Live department output — parallel plant operations with batch traceability.</p>
        </div>
        <a class="btn btn-primary btn-sm" href="<?= e(route_url('production/orders')) ?>">Master orders</a>
    </header>

    <div class="row g-3 prod-dash-kpis">
        <div class="col-6 col-md-4 col-lg-2">
            <article class="prod-dash-kpi prod-dept-card--mixing">
                <span class="prod-dash-kpi__k">Mixing today</span>
                <span class="prod-dash-kpi__v"><?= e((string)$s['mixing_today']) ?> <small>kg</small></span>
            </article>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <article class="prod-dash-kpi prod-dept-card--building">
                <span class="prod-dash-kpi__k">Building today</span>
                <span class="prod-dash-kpi__v"><?= e((string)$s['building_today']) ?></span>
            </article>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <article class="prod-dash-kpi prod-dept-card--curing">
                <span class="prod-dash-kpi__k">Curing today</span>
                <span class="prod-dash-kpi__v"><?= e((string)$s['curing_today']) ?></span>
            </article>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <article class="prod-dash-kpi prod-dept-card--qc">
                <span class="prod-dash-kpi__k">QC passed</span>
                <span class="prod-dash-kpi__v"><?= e((string)$s['qc_passed_today']) ?></span>
            </article>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <article class="prod-dash-kpi">
                <span class="prod-dash-kpi__k">QC rejected</span>
                <span class="prod-dash-kpi__v text-danger"><?= e((string)$s['qc_rejected_today']) ?></span>
            </article>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <article class="prod-dash-kpi">
                <span class="prod-dash-kpi__k">Downtime (min)</span>
                <span class="prod-dash-kpi__v"><?= e((string)$s['downtime_today']) ?></span>
            </article>
        </div>
    </div>

    <div class="prod-dept-quick mb-3">
        <a class="prod-dept-quick__item" href="<?= e(route_url('production/mixing')) ?>"><i class="bi bi-droplet"></i> Mixing</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('production/building')) ?>"><i class="bi bi-gear"></i> Building</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('production/curing')) ?>"><i class="bi bi-fire"></i> Curing</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('production/qc')) ?>"><i class="bi bi-clipboard-check"></i> QC</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('machines/list')) ?>"><i class="bi bi-cpu"></i> Machines</a>
        <a class="prod-dept-quick__item" href="<?= e(route_url('reports/production')) ?>"><i class="bi bi-file-bar-graph"></i> Reports</a>
    </div>

    <section class="prod-card prod-card--table">
        <div class="prod-card__head">
            <h2 class="prod-card__title">Master orders vs department output</h2>
            <span class="text-muted small"><?= e((string)$s['open_orders']) ?> open orders · <?= e((string)$s['running_machines']) ?> machines running</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm prod-table mb-0">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Tyre</th>
                        <th class="text-end">Target</th>
                        <th class="text-end">Mixing</th>
                        <th class="text-end">Building</th>
                        <th class="text-end">Curing</th>
                        <th class="text-end">QC pass</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentOrders as $o): ?>
                    <?php $pct = min(100, (int)round(((int)$o['qc_passed'] / max(1, (int)$o['target_qty'])) * 100)); ?>
                    <tr>
                        <td><a href="<?= e(route_url('production/order')) ?>&id=<?= (int)$o['id'] ?>"><?= e($o['order_code']) ?></a></td>
                        <td><?= e($o['tyre_type']) ?></td>
                        <td class="text-end"><?= e((string)$o['target_qty']) ?></td>
                        <td class="text-end"><?= e((string)$o['mixing_output']) ?></td>
                        <td class="text-end"><?= e((string)$o['building_output']) ?></td>
                        <td class="text-end"><?= e((string)$o['curing_output']) ?></td>
                        <td class="text-end"><?= e((string)$o['qc_passed']) ?> <span class="text-muted">(<?= e((string)$pct) ?>%)</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
