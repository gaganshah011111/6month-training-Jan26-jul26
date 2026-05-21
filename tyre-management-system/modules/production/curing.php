<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_service.php';
require_once __DIR__ . '/../../includes/production_departments.php';

if (!has_role(['Super Admin', 'Production Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        prod_save_curing_batch($pdo, $_POST, $userId);
        set_flash('success', 'Curing batch recorded.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('production/curing');
}

$from = (string)($_GET['from'] ?? $today);
$to = (string)($_GET['to'] ?? $today);
$rows = prod_list_curing_batches($pdo, $from, $to, 80);
$orders = prod_open_orders_dropdown($pdo);
$buildingBatches = prod_list_building_batches_ready($pdo);
$machines = prod_machines_for_department($pdo, PROD_DEPT_CURING);
if ($machines === []) {
    $machines = production_list_machines($pdo);
}
$operators = production_department_operators($pdo, $today);
$todayOut = (int)$pdo->query('SELECT COALESCE(SUM(cured_qty),0) FROM curing_batches WHERE production_date = CURDATE()')->fetchColumn();
?>

<div class="prod-page prod-page--dept">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Curing Department</h1>
            <p class="prod-page__sub">Curing batches (CUR) — link to green tyre batch (GBT). Independent daily operation.</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('production/building')) ?>">Building</a>
            <a href="<?= e(route_url('production/qc')) ?>">QC</a>
            <a href="<?= e(route_url('production/dashboard')) ?>">Dashboard</a>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="prod-card prod-dept-card prod-dept-card--curing">
                <div class="prod-card__head"><h2 class="prod-card__title">New curing batch</h2></div>
                <div class="prod-card__body">
                    <form method="post" class="vstack gap-2">
                        <?= csrf_input() ?>
                        <div>
                            <label class="form-label">Green tyre batch (GBT)</label>
                            <select class="form-select form-select-sm" name="building_batch_id">
                                <option value="">— Optional link —</option>
                                <?php foreach ($buildingBatches as $bb): ?>
                                    <option value="<?= (int)$bb['id'] ?>"><?= e($bb['batch_code']) ?> (<?= e((string)$bb['produced_qty']) ?> pcs)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Production order</label>
                            <select class="form-select form-select-sm" name="order_id">
                                <option value="">— Optional —</option>
                                <?php foreach ($orders as $o): ?>
                                    <option value="<?= (int)$o['id'] ?>"><?= e($o['order_code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Cured qty</label>
                                <input class="form-control form-control-sm" type="number" name="cured_qty" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Rejected</label>
                                <input class="form-control form-control-sm" type="number" name="rejected_qty" min="0" value="0">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label">Cycle (min)</label>
                                <input class="form-control form-control-sm" type="number" name="cycle_time_min" min="0" value="0">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Downtime</label>
                                <input class="form-control form-control-sm" type="number" name="downtime_min" min="0" value="0">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Shift</label>
                                <select class="form-select form-select-sm" name="shift">
                                    <?php foreach (PRODUCTION_SHIFTS as $sh): ?><option><?= e($sh) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Date</label>
                            <input class="form-control form-control-sm" type="date" name="production_date" value="<?= e($today) ?>">
                        </div>
                        <div>
                            <label class="form-label">Machine</label>
                            <select class="form-select form-select-sm" name="machine_id">
                                <option value="">—</option>
                                <?php foreach ($machines as $m): ?>
                                    <?php if (production_machine_can_run((string)$m['status'])): ?>
                                        <option value="<?= (int)$m['id'] ?>"><?= e($m['machine_code']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Operator</label>
                            <select class="form-select form-select-sm" name="operator_id">
                                <option value="">—</option>
                                <?php foreach ($operators as $op): ?>
                                    <option value="<?= (int)$op['id'] ?>"><?= e($op['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Save curing batch</button>
                    </form>
                </div>
            </section>
            <article class="prod-dash-kpi mt-3">
                <span class="prod-dash-kpi__k">Today cured</span>
                <span class="prod-dash-kpi__v"><?= e((string)$todayOut) ?></span>
            </article>
        </div>
        <div class="col-lg-8">
            <section class="prod-card prod-card--table">
                <div class="prod-card__head"><h2 class="prod-card__title">Curing production log</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm prod-table mb-0">
                        <thead>
                            <tr><th>CUR</th><th>GBT</th><th class="text-end">Cured</th><th class="text-end">Rej.</th><th class="text-end">Down</th><th>Shift</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><strong><?= e($r['batch_code']) ?></strong></td>
                                <td><?= e($r['building_code'] ?? '—') ?></td>
                                <td class="text-end"><?= e((string)$r['cured_qty']) ?></td>
                                <td class="text-end"><?= e((string)$r['rejected_qty']) ?></td>
                                <td class="text-end"><?= e((string)$r['downtime_min']) ?>m</td>
                                <td><?= e($r['shift']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?>
                            <tr><td colspan="6" class="text-muted text-center py-4">No curing batches yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
