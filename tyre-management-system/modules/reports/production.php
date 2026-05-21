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
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$dept = (string)($_GET['dept'] ?? 'mixing');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

$export = (string)($_GET['export'] ?? '');
$rows = match ($dept) {
    'building' => prod_list_building_batches($pdo, $from, $to, 500),
    'curing' => prod_list_curing_batches($pdo, $from, $to, 500),
    'qc' => prod_list_qc_entries($pdo, $from, $to, 500),
    'rejection' => prod_list_qc_entries($pdo, $from, $to, 500),
    default => prod_list_mixing_batches($pdo, $from, $to, 500),
};

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="production-' . $dept . '.csv"');
    $out = fopen('php://output', 'w');
    if ($rows) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) {
            fputcsv($out, $r);
        }
    }
    fclose($out);
    exit;
}

$summary = $pdo->prepare(
    "SELECT
        (SELECT COALESCE(SUM(produced_qty),0) FROM mixing_batches WHERE production_date BETWEEN :f AND :t) AS mix_kg,
        (SELECT COALESCE(SUM(produced_qty),0) FROM building_batches WHERE production_date BETWEEN :f AND :t) AS bld_qty,
        (SELECT COALESCE(SUM(cured_qty),0) FROM curing_batches WHERE production_date BETWEEN :f AND :t) AS cur_qty,
        (SELECT COALESCE(SUM(passed_qty),0) FROM production_qc_entries WHERE inspection_date BETWEEN :f AND :t) AS qc_pass,
        (SELECT COALESCE(SUM(rejected_qty),0) FROM production_qc_entries WHERE inspection_date BETWEEN :f AND :t) AS qc_rej"
);
$summary->execute(['f' => $from, 't' => $to]);
$sum = $summary->fetch(PDO::FETCH_ASSOC) ?: [];
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Production Reports</h1>
            <p class="prod-page__sub">Department-wise reports — parallel operations, batch traceability.</p>
        </div>
    </header>

    <form method="get" class="row g-2 mb-3 align-items-end">
        <input type="hidden" name="page" value="reports/production">
        <div class="col-auto"><label class="small">From</label><input class="form-control form-control-sm" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-auto"><label class="small">To</label><input class="form-control form-control-sm" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-auto">
            <label class="small">Department</label>
            <select class="form-select form-select-sm" name="dept">
                <option value="mixing" <?= $dept === 'mixing' ? 'selected' : '' ?>>Mixing</option>
                <option value="building" <?= $dept === 'building' ? 'selected' : '' ?>>Building</option>
                <option value="curing" <?= $dept === 'curing' ? 'selected' : '' ?>>Curing</option>
                <option value="qc" <?= $dept === 'qc' ? 'selected' : '' ?>>QC</option>
                <option value="rejection" <?= $dept === 'rejection' ? 'selected' : '' ?>>Rejection (QC)</option>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-primary btn-sm">Apply</button></div>
        <div class="col-auto ms-auto">
            <a class="btn btn-outline-secondary btn-sm" href="index.php?page=reports/production&from=<?= rawurlencode($from) ?>&to=<?= rawurlencode($to) ?>&dept=<?= rawurlencode($dept) ?>&export=csv">Export CSV</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print</button>
        </div>
    </form>

    <div class="row g-2 mb-3">
        <div class="col"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Mixing kg</span><span class="prod-dash-kpi__v"><?= e((string)($sum['mix_kg'] ?? 0)) ?></span></article></div>
        <div class="col"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Building</span><span class="prod-dash-kpi__v"><?= e((string)($sum['bld_qty'] ?? 0)) ?></span></article></div>
        <div class="col"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">Cured</span><span class="prod-dash-kpi__v"><?= e((string)($sum['cur_qty'] ?? 0)) ?></span></article></div>
        <div class="col"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">QC pass</span><span class="prod-dash-kpi__v"><?= e((string)($sum['qc_pass'] ?? 0)) ?></span></article></div>
        <div class="col"><article class="prod-dash-kpi"><span class="prod-dash-kpi__k">QC reject</span><span class="prod-dash-kpi__v text-danger"><?= e((string)($sum['qc_rej'] ?? 0)) ?></span></article></div>
    </div>

    <section class="prod-card prod-card--table">
        <div class="table-responsive">
            <table class="table table-sm prod-table mb-0">
                <thead><tr>
                    <?php if ($dept === 'mixing'): ?>
                        <th>Batch</th><th>Date</th><th>Order</th><th class="text-end">kg</th><th>Shift</th><th>Machine</th>
                    <?php elseif ($dept === 'building'): ?>
                        <th>GBT</th><th>CMP</th><th class="text-end">Produced</th><th class="text-end">Rej.</th><th>Shift</th>
                    <?php elseif ($dept === 'curing'): ?>
                        <th>CUR</th><th>GBT</th><th class="text-end">Cured</th><th class="text-end">Down</th>
                    <?php else: ?>
                        <th>Date</th><th>Batch</th><th class="text-end">Pass</th><th class="text-end">Fail</th><th>Defect</th>
                    <?php endif; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <?php if ($dept === 'mixing'): ?>
                            <td><?= e($r['batch_code']) ?></td><td><?= e($r['production_date']) ?></td><td><?= e($r['order_code'] ?? '—') ?></td>
                            <td class="text-end"><?= e((string)$r['produced_qty']) ?></td><td><?= e($r['shift']) ?></td><td><?= e($r['machine_code'] ?? '—') ?></td>
                        <?php elseif ($dept === 'building'): ?>
                            <td><?= e($r['batch_code']) ?></td><td><?= e($r['mixing_code'] ?? '—') ?></td>
                            <td class="text-end"><?= e((string)$r['produced_qty']) ?></td><td class="text-end"><?= e((string)$r['rejected_qty']) ?></td><td><?= e($r['shift']) ?></td>
                        <?php elseif ($dept === 'curing'): ?>
                            <td><?= e($r['batch_code']) ?></td><td><?= e($r['building_code'] ?? '—') ?></td>
                            <td class="text-end"><?= e((string)$r['cured_qty']) ?></td><td class="text-end"><?= e((string)$r['downtime_min']) ?></td>
                        <?php else: ?>
                            <td><?= e($r['inspection_date']) ?></td><td><?= e($r['batch_ref'] ?? '—') ?></td>
                            <td class="text-end"><?= e((string)$r['passed_qty']) ?></td><td class="text-end"><?= e((string)$r['rejected_qty']) ?></td><td><?= e($r['defect_type'] ?? '—') ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No records in range.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
