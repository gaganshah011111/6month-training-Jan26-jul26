<?php
declare(strict_types=1);
header('Location: index.php?page=' . rawurlencode('production/orders'));
exit;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_service.php';

if (!has_role(['Super Admin', 'Production Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        production_save_entry($pdo, $_POST);
        set_flash('success', 'Production entry recorded. QC can verify rejected tyres next.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('production/entry');
}

$today = date('Y-m-d');
$filterFrom = (string)($_GET['from'] ?? $today);
$filterTo = (string)($_GET['to'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterFrom)) {
    $filterFrom = $today;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterTo)) {
    $filterTo = $today;
}

$machines = production_running_machines($pdo);
$operators = production_available_operators($pdo, $today);
$rows = production_list_entries($pdo, 80, $filterFrom, $filterTo);
$formDate = (string)($_GET['date'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $formDate)) {
    $formDate = $today;
}
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Production Entry</h1>
            <p class="prod-page__sub">Record shift-wise tyre production — machines must be <strong>Running</strong>. Operators marked absent today are excluded (HR attendance).</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('production/dashboard')) ?>">Dashboard</a>
            <a href="<?= e(route_url('machines/list')) ?>">Machines</a>
            <a href="<?= e(route_url('reports/production')) ?>">Reports</a>
        </nav>
    </header>

    <div class="row g-3 align-items-start">
        <div class="col-lg-4">
            <section class="prod-card">
                <div class="prod-card__head">
                    <h2 class="prod-card__title">New production entry</h2>
                </div>
                <div class="prod-card__body">
                    <?php if (!$machines): ?>
                        <div class="alert alert-warning py-2 small mb-0">
                            No machines in <strong>Running</strong> status. Update status in <a href="<?= e(route_url('machines/list')) ?>">Machines</a>.
                        </div>
                    <?php elseif (!$operators): ?>
                        <div class="alert alert-warning py-2 small mb-0">No operators available — check HR attendance for today.</div>
                    <?php else: ?>
                    <form method="post" class="prod-form">
                        <?= csrf_input() ?>
                        <div class="mb-2">
                            <label class="form-label">Production date</label>
                            <input type="date" class="form-control form-control-sm" name="production_date" value="<?= e($formDate) ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Shift</label>
                            <select class="form-select form-select-sm" name="shift" required>
                                <?php foreach (PRODUCTION_SHIFTS as $sh): ?>
                                    <option value="<?= e($sh) ?>"><?= e($sh) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Machine</label>
                            <select class="form-select form-select-sm" name="machine_id" required>
                                <option value="">Select machine</option>
                                <?php foreach ($machines as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>">
                                        <?= e($m['machine_code']) ?> — <?= e($m['machine_name']) ?>
                                        <?php if (!empty($m['shift_capacity'])): ?> (cap <?= (int)$m['shift_capacity'] ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Operator</label>
                            <select class="form-select form-select-sm" name="operator_id" required>
                                <option value="">Select operator</option>
                                <?php foreach ($operators as $op): ?>
                                    <option value="<?= (int)$op['id'] ?>">
                                        <?= e($op['full_name']) ?> (<?= e($op['employee_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Tyre type</label>
                            <select class="form-select form-select-sm" name="tyre_type" required>
                                <option value="">Select type</option>
                                <?php foreach (TYRE_TYPES as $tt): ?>
                                    <option value="<?= e($tt) ?>"><?= e($tt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-4">
                                <label class="form-label">Planned</label>
                                <input type="number" class="form-control form-control-sm" name="planned_quantity" min="0" value="0">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Produced</label>
                                <input type="number" class="form-control form-control-sm" name="produced_quantity" min="0" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Rejected</label>
                                <input type="number" class="form-control form-control-sm" name="rejected_quantity" min="0" value="0">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Downtime (minutes)</label>
                            <input type="number" class="form-control form-control-sm" name="downtime_minutes" min="0" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control form-control-sm" name="remarks" rows="2" maxlength="500" placeholder="Optional notes"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Save production entry</button>
                    </form>
                    <p class="prod-form__hint mt-2 mb-0">Inventory raw-material deduction is prepared for a future release.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="col-lg-8">
            <section class="prod-card prod-card--table">
                <div class="prod-card__head">
                    <h2 class="prod-card__title">Production log</h2>
                    <form method="get" class="prod-filter d-flex flex-wrap gap-2 align-items-center">
                        <input type="hidden" name="page" value="production/entry">
                        <input type="date" class="form-control form-control-sm" name="from" value="<?= e($filterFrom) ?>">
                        <span class="text-muted small">to</span>
                        <input type="date" class="form-control form-control-sm" name="to" value="<?= e($filterTo) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover prod-table mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Shift</th>
                                <th>Machine</th>
                                <th>Operator</th>
                                <th>Tyre</th>
                                <th class="text-end">Prod.</th>
                                <th class="text-end">Rej.</th>
                                <th class="text-end">Eff.</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No production entries for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <?php $badge = production_entry_status_badge((string)($r['entry_status'] ?? 'Submitted')); ?>
                                <tr>
                                    <td class="text-nowrap"><?= e($r['production_date']) ?></td>
                                    <td><?= e($r['shift']) ?></td>
                                    <td><?= e($r['machine_code']) ?></td>
                                    <td><?= e($r['operator_name'] ?? '—') ?></td>
                                    <td><?= e($r['tyre_type'] ?? '—') ?></td>
                                    <td class="text-end"><?= e((string)($r['output_quantity'] ?? 0)) ?></td>
                                    <td class="text-end"><?= e((string)($r['rejected_quantity'] ?? 0)) ?></td>
                                    <td class="text-end"><?= isset($r['efficiency_pct']) && $r['efficiency_pct'] !== null && $r['efficiency_pct'] !== '' ? e((string)$r['efficiency_pct']) . '%' : '—' ?></td>
                                    <td><span class="badge <?= e($badge['class']) ?>"><?= e($badge['label']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
