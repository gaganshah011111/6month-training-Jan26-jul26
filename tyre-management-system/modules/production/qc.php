<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_departments.php';

if (!has_role(['Super Admin', 'Production Manager', 'Quality Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        prod_save_qc_entry($pdo, $_POST, $userId);
        set_flash('success', 'QC inspection saved. Passed tyres added to finished goods.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('production/qc');
}

$from = (string)($_GET['from'] ?? $today);
$to = (string)($_GET['to'] ?? $today);
$rows = prod_list_qc_entries($pdo, $from, $to, 80);
$orders = prod_open_orders_dropdown($pdo);
$curingBatches = $pdo->query(
    'SELECT id, batch_code, cured_qty, production_date FROM curing_batches ORDER BY id DESC LIMIT 100'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$stats = $pdo->query(
    "SELECT COALESCE(SUM(passed_qty),0) AS p, COALESCE(SUM(rejected_qty),0) AS r
     FROM production_qc_entries WHERE inspection_date = CURDATE()"
)->fetch(PDO::FETCH_ASSOC) ?: ['p' => 0, 'r' => 0];
?>

<div class="prod-page prod-page--dept">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">QC Department</h1>
            <p class="prod-page__sub">Independent quality inspection — link to curing batch for traceability. Approved stock updates inventory.</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('production/curing')) ?>">Curing</a>
            <a href="<?= e(route_url('production/dashboard')) ?>">Dashboard</a>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="prod-card prod-dept-card prod-dept-card--qc">
                <div class="prod-card__head"><h2 class="prod-card__title">QC inspection entry</h2></div>
                <div class="prod-card__body">
                    <form method="post" class="vstack gap-2">
                        <?= csrf_input() ?>
                        <div>
                            <label class="form-label">Curing batch (CUR)</label>
                            <select class="form-select form-select-sm" name="curing_batch_id">
                                <option value="">— Optional —</option>
                                <?php foreach ($curingBatches as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= e($c['batch_code']) ?> (<?= e((string)$c['cured_qty']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Production order</label>
                            <select class="form-select form-select-sm" name="order_id">
                                <option value="">— Optional —</option>
                                <?php foreach ($orders as $o): ?>
                                    <option value="<?= (int)$o['id'] ?>"><?= e($o['order_code']) ?> — <?= e($o['tyre_type']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Inspector</label>
                            <input class="form-control form-control-sm" name="inspector_name" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label">Inspected</label>
                                <input class="form-control form-control-sm" type="number" name="inspected_qty" min="0" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Passed</label>
                                <input class="form-control form-control-sm" type="number" name="passed_qty" min="0" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Rejected</label>
                                <input class="form-control form-control-sm" type="number" name="rejected_qty" min="0" value="0">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Defect type</label>
                            <input class="form-control form-control-sm" name="defect_type" placeholder="sidewall crack, air bubble…">
                        </div>
                        <div>
                            <label class="form-label">Warehouse</label>
                            <input class="form-control form-control-sm" name="warehouse_location" value="FG-A1">
                        </div>
                        <div>
                            <label class="form-label">Date</label>
                            <input class="form-control form-control-sm" type="date" name="inspection_date" value="<?= e($today) ?>">
                        </div>
                        <div>
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control form-control-sm" name="remarks" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success btn-sm w-100">Record QC &amp; update stock</button>
                    </form>
                </div>
            </section>
            <div class="row g-2 mt-2">
                <div class="col-6"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Passed today</span><span class="prod-dash-kpi__v"><?= e((string)$stats['p']) ?></span></article></div>
                <div class="col-6"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Rejected today</span><span class="prod-dash-kpi__v text-danger"><?= e((string)$stats['r']) ?></span></article></div>
            </div>
        </div>
        <div class="col-lg-8">
            <section class="prod-card prod-card--table">
                <div class="prod-card__head"><h2 class="prod-card__title">QC inspection log</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm prod-table mb-0">
                        <thead>
                            <tr><th>Date</th><th>Batch</th><th>Order</th><th class="text-end">Pass</th><th class="text-end">Fail</th><th>Defect</th><th>Inspector</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e($r['inspection_date']) ?></td>
                                <td><?= e($r['batch_ref'] ?? $r['curing_code'] ?? '—') ?></td>
                                <td><?= e($r['order_code'] ?? '—') ?></td>
                                <td class="text-end"><?= e((string)$r['passed_qty']) ?></td>
                                <td class="text-end"><?= e((string)$r['rejected_qty']) ?></td>
                                <td><?= e($r['defect_type'] ?? '—') ?></td>
                                <td><?= e($r['inspector_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?>
                            <tr><td colspan="7" class="text-muted text-center py-4">No QC entries yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
