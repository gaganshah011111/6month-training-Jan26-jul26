<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/qc_service.php';
require_once __DIR__ . '/../../includes/production_service.php';
require_once __DIR__ . '/_flow.php';

if (!has_role(['Quality Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$reportType = (string)($_GET['type'] ?? 'daily');
$shift = (string)($_GET['shift'] ?? '');
$machineId = (int)($_GET['machine_id'] ?? 0);
$export = (string)($_GET['export'] ?? '');

$report = qc_report($pdo, $from, $to, $reportType, $shift, $machineId);
$rows = $report['rows'];
$sum = $report['summary'];
$inspectors = qc_inspector_stats($pdo, $from, $to);
$machines = production_list_machines($pdo);

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="qc-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Batch ID', 'Date', 'Tyre', 'Machine', 'Shift', 'Inspected', 'Passed', 'Reject', 'Rework', 'Status', 'Inspector']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['batch_code'], $r['inspection_date'], $r['tyre_type'], $r['machine_code'] ?? '',
            $r['inspection_shift'], $r['inspected_qty'], $r['passed_qty'], $r['rejected_qty'],
            $r['rework_qty'], $r['qc_status'], $r['inspector_name'],
        ]);
    }
    fclose($out);
    exit;
}

if ($export === 'pdf' || $export === 'print') {
    $reportTitle = 'QC Report';
    require __DIR__ . '/reports_print.php';
    exit;
}

$baseQs = 'page=quality/reports&from=' . rawurlencode($from) . '&to=' . rawurlencode($to)
    . '&type=' . rawurlencode($reportType) . '&shift=' . rawurlencode($shift)
    . '&machine_id=' . $machineId;
?>

<div class="qc-page">
    <?php qc_render_flow_bar('qc'); ?>

    <header class="qc-page__head">
        <div>
            <h1 class="qc-page__title">QC Reports</h1>
            <p class="qc-page__sub">Daily, shift, reject, machine and inspector quality reports</p>
        </div>
        <div class="d-flex gap-1">
            <a class="btn btn-sm btn-outline-secondary" href="index.php?<?= e($baseQs) ?>&export=print" target="_blank">Print</a>
            <a class="btn btn-sm btn-outline-secondary" href="index.php?<?= e($baseQs) ?>&export=csv">CSV</a>
        </div>
    </header>

    <form method="get" class="row g-2 mb-3 align-items-end">
        <input type="hidden" name="page" value="quality/reports">
        <div class="col-auto"><label class="form-label small">From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
        <div class="col-auto"><label class="form-label small">To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
        <div class="col-auto"><label class="form-label small">Report</label>
            <select class="form-select form-select-sm" name="type">
                <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Daily QC</option>
                <option value="reject" <?= $reportType === 'reject' ? 'selected' : '' ?>>Reject report</option>
            </select>
        </div>
        <div class="col-auto"><label class="form-label small">Shift</label>
            <select class="form-select form-select-sm" name="shift">
                <option value="">All</option>
                <?php foreach (PRODUCTION_SHIFTS as $sh): ?>
                    <option value="<?= e($sh) ?>" <?= $shift === $sh ? 'selected' : '' ?>><?= e($sh) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><label class="form-label small">Machine</label>
            <select class="form-select form-select-sm" name="machine_id">
                <option value="0">All</option>
                <?php foreach ($machines as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= $machineId === (int)$m['id'] ? 'selected' : '' ?>><?= e((string)$m['machine_code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-sm qc-btn-primary">Apply</button></div>
    </form>

    <div class="qc-kpis qc-kpis--compact mb-3">
        <article class="qc-kpi"><span class="qc-kpi__label">Records</span><span class="qc-kpi__value"><?= e((string)$sum['count']) ?></span></article>
        <article class="qc-kpi"><span class="qc-kpi__label">Inspected</span><span class="qc-kpi__value"><?= e(qc_format_qty((int)$sum['inspected'])) ?></span></article>
        <article class="qc-kpi qc-kpi--pass"><span class="qc-kpi__label">Pass %</span><span class="qc-kpi__value"><?= e((string)$sum['pass_pct']) ?>%</span></article>
    </div>

    <section class="qc-card mb-3">
        <header class="qc-card__head"><h2 class="qc-card__title">Inspector report</h2></header>
        <table class="qc-table">
            <thead><tr><th>Inspector</th><th class="text-end">Inspections</th><th class="text-end">Passed</th><th class="text-end">Rejected</th></tr></thead>
            <tbody>
            <?php foreach ($inspectors as $ins): ?>
                <tr>
                    <td><?= e((string)$ins['inspector_name']) ?></td>
                    <td class="text-end"><?= e((string)$ins['inspections']) ?></td>
                    <td class="text-end"><?= e(qc_format_qty((int)$ins['passed'])) ?></td>
                    <td class="text-end"><?= e(qc_format_qty((int)$ins['rejected'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="qc-card">
        <header class="qc-card__head"><h2 class="qc-card__title">Inspection detail</h2></header>
        <div class="table-responsive">
            <table class="qc-table">
                <thead>
                    <tr>
                        <th>Batch ID</th><th>Date</th><th>Tyre</th><th>Machine</th><th>Shift</th>
                        <th class="text-end">Inspected</th><th class="text-end">Passed</th><th class="text-end">Reject</th>
                        <th>QC status</th><th>Inspector</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e((string)$r['batch_code']) ?></td>
                        <td><?= e((string)$r['inspection_date']) ?></td>
                        <td><?= e((string)$r['tyre_type']) ?></td>
                        <td><?= e((string)($r['machine_code'] ?? '—')) ?></td>
                        <td><?= e((string)$r['inspection_shift']) ?></td>
                        <td class="text-end"><?= e(qc_format_qty((int)$r['inspected_qty'])) ?></td>
                        <td class="text-end"><?= e(qc_format_qty((int)$r['passed_qty'])) ?></td>
                        <td class="text-end"><?= e(qc_format_qty((int)$r['rejected_qty'])) ?></td>
                        <td><span class="qc-badge qc-badge--<?= e(qc_status_badge((string)$r['qc_status'])) ?>"><?= e((string)$r['qc_status']) ?></span></td>
                        <td><?= e((string)$r['inspector_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="10" class="qc-empty">No records for selected filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
