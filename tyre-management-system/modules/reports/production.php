<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_entries.php';
require_once __DIR__ . '/../../includes/production_service.php';

if (!has_role(['Super Admin', 'Production Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$dept = (string)($_GET['department'] ?? 'all');
$shift = (string)($_GET['shift'] ?? '');
$machineId = (int)($_GET['machine_id'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

$report = ['rows' => [], 'summary' => ['total_produced' => 0, 'total_rejected' => 0, 'qc_pass_pct' => 0, 'downtime' => 0, 'active_machines' => 0]];
$error = '';

try {
    $report = prod_entry_report($pdo, $from, $to, $dept, $shift, $machineId);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$rows = $report['rows'];
$sum = $report['summary'];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="production-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Shift', 'Department', 'Machine', 'Tyre type', 'Produced', 'Rejected', 'Operator']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['entry_date'],
            $r['shift'],
            $r['department'],
            $r['machine'],
            $r['tyre_type'],
            $r['produced'],
            $r['rejected'],
            $r['operator'],
        ]);
    }
    fclose($out);
    exit;
}

$machines = production_list_machines($pdo);
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Production Reports</h1>
            <p class="prod-page__sub">Daily production entries by department — simple operational report.</p>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="get" class="row g-2 mb-3 align-items-end">
        <input type="hidden" name="page" value="reports/production">
        <div class="col-auto"><label class="form-label small">From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
        <div class="col-auto"><label class="form-label small">To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
        <div class="col-auto">
            <label class="form-label small">Department</label>
            <select class="form-select form-select-sm" name="department">
                <option value="all" <?= $dept === 'all' ? 'selected' : '' ?>>All</option>
                <?php foreach (prod_entry_departments() as $d): ?>
                    <option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small">Shift</label>
            <select class="form-select form-select-sm" name="shift">
                <option value="">All</option>
                <?php foreach (PRODUCTION_SHIFTS as $sh): ?>
                    <option value="<?= e($sh) ?>" <?= $shift === $sh ? 'selected' : '' ?>><?= e($sh) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small">Machine</label>
            <select class="form-select form-select-sm" name="machine_id">
                <option value="0">All</option>
                <?php foreach ($machines as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= $machineId === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['machine_code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-primary btn-sm">Apply</button></div>
        <div class="col-auto ms-auto">
            <a class="btn btn-outline-secondary btn-sm" href="index.php?page=reports/production&amp;from=<?= rawurlencode($from) ?>&amp;to=<?= rawurlencode($to) ?>&amp;department=<?= rawurlencode($dept) ?>&amp;shift=<?= rawurlencode($shift) ?>&amp;machine_id=<?= $machineId ?>&amp;export=csv">CSV</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print</button>
        </div>
    </form>

    <div class="row g-2 mb-3">
        <div class="col"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Total production</span><span class="prod-dash-kpi__v"><?= e((string)$sum['total_produced']) ?></span></article></div>
        <div class="col"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Total rejected</span><span class="prod-dash-kpi__v text-danger"><?= e((string)$sum['total_rejected']) ?></span></article></div>
        <div class="col"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">QC pass %</span><span class="prod-dash-kpi__v"><?= e((string)$sum['qc_pass_pct']) ?>%</span></article></div>
        <div class="col"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Machine downtime</span><span class="prod-dash-kpi__v"><?= e((string)$sum['downtime']) ?> min</span></article></div>
        <div class="col"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Active machines</span><span class="prod-dash-kpi__v"><?= e((string)$sum['active_machines']) ?></span></article></div>
    </div>

    <section class="prod-card prod-card--table">
        <div class="table-responsive">
            <table class="table table-sm prod-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Shift</th>
                        <th>Department</th>
                        <th>Machine</th>
                        <th>Tyre type</th>
                        <th class="text-end">Produced</th>
                        <th class="text-end">Rejected</th>
                        <th>Operator</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['entry_date']) ?></td>
                        <td><?= e($r['shift']) ?></td>
                        <td><?= e($r['department']) ?></td>
                        <td><?= e($r['machine']) ?></td>
                        <td><?= e($r['tyre_type']) ?></td>
                        <td class="text-end"><?= e((string)$r['produced']) ?></td>
                        <td class="text-end"><?= e((string)$r['rejected']) ?></td>
                        <td><?= e($r['operator']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No production entries found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
