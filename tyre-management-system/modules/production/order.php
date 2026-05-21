<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_departments.php';

if (!has_role(['Super Admin', 'Production Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$orderId = (int)($_GET['id'] ?? 0);
if ($orderId < 1) {
    redirect('production/orders');
}

$trace = prod_order_traceability($pdo, $orderId);
if ($trace === []) {
    set_flash('danger', 'Order not found.');
    redirect('production/orders');
}

$o = $trace['order'];
$target = max(1, (int)$o['target_qty']);
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title"><?= e($o['order_code']) ?></h1>
            <p class="prod-page__sub"><?= e($o['tyre_type']) ?> · Target <?= e((string)$target) ?> · Batch traceability (departments work in parallel)</p>
        </div>
        <a href="<?= e(route_url('production/orders')) ?>" class="btn btn-sm btn-outline-secondary">← Orders</a>
    </header>

    <div class="row g-2 mb-3">
        <div class="col-md-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Target</span><span class="prod-dash-kpi__v"><?= e((string)$target) ?></span></article></div>
        <div class="col-md-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">QC achieved</span><span class="prod-dash-kpi__v"><?= e((string)$trace['achieved_pct']) ?>%</span></article></div>
        <div class="col-md-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Priority</span><span class="prod-dash-kpi__v" style="font-size:1rem"><?= e($o['priority']) ?></span></article></div>
        <div class="col-md-3"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Deadline</span><span class="prod-dash-kpi__v" style="font-size:1rem"><?= e((string)($o['deadline'] ?? '—')) ?></span></article></div>
    </div>

    <p class="alert alert-light border small py-2">
        <strong>Realistic model:</strong> Mixing may output 1200 kg while Building outputs 800 green tyres and Curing 700 — linked by batch IDs, not sequential locks.
    </p>

    <div class="row g-3">
        <div class="col-md-6">
            <section class="prod-card prod-card--table mb-3">
                <div class="prod-card__head"><h2 class="prod-card__title prod-dept-card--mixing">Mixing (CMP)</h2><a href="<?= e(route_url('production/mixing')) ?>">+ Add</a></div>
                <table class="table table-sm prod-table mb-0">
                    <tr><th>Batch</th><th class="text-end">kg</th><th>Status</th></tr>
                    <?php foreach ($trace['mixing'] as $r): ?>
                        <tr><td><?= e($r['batch_code']) ?></td><td class="text-end"><?= e((string)$r['produced_qty']) ?></td><td><?= e($r['status']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$trace['mixing']): ?><tr><td colspan="3" class="text-muted">No mixing batches linked.</td></tr><?php endif; ?>
                </table>
            </section>
            <section class="prod-card prod-card--table mb-3">
                <div class="prod-card__head"><h2 class="prod-card__title">Building (GBT)</h2><a href="<?= e(route_url('production/building')) ?>">+ Add</a></div>
                <table class="table table-sm prod-table mb-0">
                    <tr><th>Batch</th><th>CMP</th><th class="text-end">Qty</th></tr>
                    <?php foreach ($trace['building'] as $r): ?>
                        <tr><td><?= e($r['batch_code']) ?></td><td><?= e($r['mixing_code'] ?? '—') ?></td><td class="text-end"><?= e((string)$r['produced_qty']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$trace['building']): ?><tr><td colspan="3" class="text-muted">No building batches.</td></tr><?php endif; ?>
                </table>
            </section>
        </div>
        <div class="col-md-6">
            <section class="prod-card prod-card--table mb-3">
                <div class="prod-card__head"><h2 class="prod-card__title">Curing (CUR)</h2><a href="<?= e(route_url('production/curing')) ?>">+ Add</a></div>
                <table class="table table-sm prod-table mb-0">
                    <tr><th>Batch</th><th>GBT</th><th class="text-end">Cured</th></tr>
                    <?php foreach ($trace['curing'] as $r): ?>
                        <tr><td><?= e($r['batch_code']) ?></td><td><?= e($r['building_code'] ?? '—') ?></td><td class="text-end"><?= e((string)$r['cured_qty']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$trace['curing']): ?><tr><td colspan="3" class="text-muted">No curing batches.</td></tr><?php endif; ?>
                </table>
            </section>
            <section class="prod-card prod-card--table">
                <div class="prod-card__head"><h2 class="prod-card__title">QC</h2><a href="<?= e(route_url('production/qc')) ?>">+ Inspect</a></div>
                <table class="table table-sm prod-table mb-0">
                    <tr><th>Date</th><th class="text-end">Pass</th><th class="text-end">Fail</th><th>Defect</th></tr>
                    <?php foreach ($trace['qc'] as $r): ?>
                        <tr><td><?= e($r['inspection_date']) ?></td><td class="text-end"><?= e((string)$r['passed_qty']) ?></td><td class="text-end"><?= e((string)$r['rejected_qty']) ?></td><td><?= e($r['defect_type'] ?? '—') ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$trace['qc']): ?><tr><td colspan="4" class="text-muted">No QC entries.</td></tr><?php endif; ?>
                </table>
            </section>
        </div>
    </div>
</div>
