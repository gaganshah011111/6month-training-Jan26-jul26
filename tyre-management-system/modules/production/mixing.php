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
production_seed_default_bom($pdo);
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        prod_save_mixing_batch($pdo, $_POST, $userId);
        set_flash('success', 'Mixing batch recorded.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('production/mixing');
}

$from = (string)($_GET['from'] ?? $today);
$to = (string)($_GET['to'] ?? $today);
$rows = prod_list_mixing_batches($pdo, $from, $to, 80);
$orders = prod_open_orders_dropdown($pdo);
$machines = prod_machines_for_department($pdo, PROD_DEPT_MIXING);
if ($machines === []) {
    $machines = production_list_machines($pdo);
}
$operators = production_department_operators($pdo, $today);
$todayKg = (float)$pdo->query('SELECT COALESCE(SUM(produced_qty),0) FROM mixing_batches WHERE production_date = CURDATE()')->fetchColumn();
?>

<div class="prod-page prod-page--dept">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Mixing Department</h1>
            <p class="prod-page__sub">Independent compound production — batch traceability via CMP codes. No dependency on Building or Curing.</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('production/dashboard')) ?>">Dashboard</a>
            <a href="<?= e(route_url('production/building')) ?>">Building</a>
            <a href="<?= e(route_url('production/curing')) ?>">Curing</a>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="prod-card prod-dept-card prod-dept-card--mixing">
                <div class="prod-card__head"><h2 class="prod-card__title">New mixing batch</h2></div>
                <div class="prod-card__body">
                    <form method="post" class="prod-form vstack gap-2">
                        <?= csrf_input() ?>
                        <div>
                            <label class="form-label">Production order (optional)</label>
                            <select class="form-select form-select-sm" name="order_id">
                                <option value="">— Standalone batch —</option>
                                <?php foreach ($orders as $o): ?>
                                    <option value="<?= (int)$o['id'] ?>"><?= e($o['order_code']) ?> — <?= e($o['tyre_type']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Compound name</label>
                            <input class="form-control form-control-sm" name="compound_name" value="Rubber Compound" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Produced (kg)</label>
                                <input class="form-control form-control-sm" type="number" step="0.01" name="produced_qty" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Wastage (kg)</label>
                                <input class="form-control form-control-sm" type="number" step="0.01" name="wastage_qty" min="0" value="0">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Date</label>
                                <input class="form-control form-control-sm" type="date" name="production_date" value="<?= e($today) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Shift</label>
                                <select class="form-select form-select-sm" name="shift">
                                    <?php foreach (PRODUCTION_SHIFTS as $sh): ?>
                                        <option><?= e($sh) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Machine</label>
                            <select class="form-select form-select-sm" name="machine_id">
                                <option value="">—</option>
                                <?php foreach ($machines as $m): ?>
                                    <?php if (!production_machine_can_run((string)$m['status'])) {
                                        continue;
                                    } ?>
                                    <option value="<?= (int)$m['id'] ?>"><?= e($m['machine_code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Operator</label>
                            <select class="form-select form-select-sm" name="operator_id">
                                <option value="">—</option>
                                <?php foreach ($operators as $op): ?>
                                    <option value="<?= (int)$op['id'] ?>" <?= (int)($op['is_absent'] ?? 0) ? 'disabled' : '' ?>><?= e($op['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <option>Ready</option>
                                <option>In Progress</option>
                                <option>Completed</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Notes</label>
                            <textarea class="form-control form-control-sm" name="notes" rows="2"></textarea>
                        </div>
                        <p class="prod-form__hint mb-0">Raw materials deduct when linked to a production order.</p>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Save mixing batch</button>
                    </form>
                </div>
            </section>
            <article class="prod-dash-kpi mt-3">
                <span class="prod-dash-kpi__k">Today mixing output</span>
                <span class="prod-dash-kpi__v"><?= e((string)$todayKg) ?> kg</span>
            </article>
        </div>
        <div class="col-lg-8">
            <section class="prod-card prod-card--table">
                <div class="prod-card__head">
                    <h2 class="prod-card__title">Mixing production log</h2>
                    <form method="get" class="d-flex gap-2">
                        <input type="hidden" name="page" value="production/mixing">
                        <input class="form-control form-control-sm" type="date" name="from" value="<?= e($from) ?>">
                        <input class="form-control form-control-sm" type="date" name="to" value="<?= e($to) ?>">
                        <button class="btn btn-sm btn-outline-secondary">Filter</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm prod-table mb-0">
                        <thead>
                            <tr>
                                <th>Batch</th>
                                <th>Order</th>
                                <th>Compound</th>
                                <th class="text-end">kg</th>
                                <th>Shift</th>
                                <th>Machine</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><strong><?= e($r['batch_code']) ?></strong></td>
                                <td><?= e($r['order_code'] ?? '—') ?></td>
                                <td><?= e($r['compound_name']) ?></td>
                                <td class="text-end"><?= e((string)$r['produced_qty']) ?></td>
                                <td><?= e($r['shift']) ?></td>
                                <td><?= e($r['machine_code'] ?? '—') ?></td>
                                <td><span class="pw-badge pw-badge--running"><?= e($r['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No mixing batches yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
