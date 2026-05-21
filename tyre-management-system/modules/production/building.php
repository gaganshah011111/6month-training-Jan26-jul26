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
        prod_save_building_batch($pdo, $_POST, $userId);
        set_flash('success', 'Building batch recorded.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('production/building');
}

$from = (string)($_GET['from'] ?? $today);
$to = (string)($_GET['to'] ?? $today);
$rows = prod_list_building_batches($pdo, $from, $to, 80);
$orders = prod_open_orders_dropdown($pdo);
$mixingBatches = prod_list_mixing_batches_ready($pdo);
$machines = prod_machines_for_department($pdo, PROD_DEPT_BUILDING);
if ($machines === []) {
    $machines = production_list_machines($pdo);
}
$operators = production_department_operators($pdo, $today);
$todayOut = (int)$pdo->query('SELECT COALESCE(SUM(produced_qty),0) FROM building_batches WHERE production_date = CURDATE()')->fetchColumn();
?>

<div class="prod-page prod-page--dept">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Building Department</h1>
            <p class="prod-page__sub">Green tyre batches (GBT) — optionally consume compound batch (CMP). Runs parallel to Mixing and Curing.</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('production/mixing')) ?>">Mixing</a>
            <a href="<?= e(route_url('production/dashboard')) ?>">Dashboard</a>
            <a href="<?= e(route_url('production/curing')) ?>">Curing</a>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="prod-card prod-dept-card prod-dept-card--building">
                <div class="prod-card__head"><h2 class="prod-card__title">New building batch</h2></div>
                <div class="prod-card__body">
                    <form method="post" class="vstack gap-2">
                        <?= csrf_input() ?>
                        <div>
                            <label class="form-label">Compound batch (CMP)</label>
                            <select class="form-select form-select-sm" name="mixing_batch_id">
                                <option value="">— Optional link —</option>
                                <?php foreach ($mixingBatches as $mb): ?>
                                    <option value="<?= (int)$mb['id'] ?>"><?= e($mb['batch_code']) ?> (<?= e((string)$mb['produced_qty']) ?> <?= e($mb['unit'] ?? 'kg') ?>)</option>
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
                                <label class="form-label">Green tyres produced</label>
                                <input class="form-control form-control-sm" type="number" name="produced_qty" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Rejected</label>
                                <input class="form-control form-control-sm" type="number" name="rejected_qty" min="0" value="0">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Date</label>
                                <input class="form-control form-control-sm" type="date" name="production_date" value="<?= e($today) ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Shift</label>
                                <select class="form-select form-select-sm" name="shift">
                                    <?php foreach (PRODUCTION_SHIFTS as $sh): ?><option><?= e($sh) ?></option><?php endforeach; ?>
                                </select>
                            </div>
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
                        <div>
                            <label class="form-label">Notes</label>
                            <textarea class="form-control form-control-sm" name="notes" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Save building batch</button>
                    </form>
                </div>
            </section>
            <article class="prod-dash-kpi mt-3">
                <span class="prod-dash-kpi__k">Today building output</span>
                <span class="prod-dash-kpi__v"><?= e((string)$todayOut) ?> tyres</span>
            </article>
        </div>
        <div class="col-lg-8">
            <section class="prod-card prod-card--table">
                <div class="prod-card__head">
                    <h2 class="prod-card__title">Building production log</h2>
                    <form method="get" class="d-flex gap-2">
                        <input type="hidden" name="page" value="production/building">
                        <input class="form-control form-control-sm" type="date" name="from" value="<?= e($from) ?>">
                        <input class="form-control form-control-sm" type="date" name="to" value="<?= e($to) ?>">
                        <button class="btn btn-sm btn-outline-secondary">Filter</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm prod-table mb-0">
                        <thead>
                            <tr><th>GBT</th><th>CMP used</th><th>Order</th><th class="text-end">Produced</th><th class="text-end">Rej.</th><th>Shift</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><strong><?= e($r['batch_code']) ?></strong></td>
                                <td><?= e($r['mixing_code'] ?? '—') ?></td>
                                <td><?= e($r['order_code'] ?? '—') ?></td>
                                <td class="text-end"><?= e((string)$r['produced_qty']) ?></td>
                                <td class="text-end"><?= e((string)$r['rejected_qty']) ?></td>
                                <td><?= e($r['shift']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?>
                            <tr><td colspan="6" class="text-muted text-center py-4">No building batches yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
