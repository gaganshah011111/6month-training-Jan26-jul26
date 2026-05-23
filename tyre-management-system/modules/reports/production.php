<?php

declare(strict_types=1);



require_once __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/../../includes/functions.php';

require_once __DIR__ . '/../../includes/production_entries.php';

require_once __DIR__ . '/../../includes/production_service.php';

require_once __DIR__ . '/../../includes/machine_service.php';



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

$operatorId = (int)($_GET['operator_id'] ?? 0);

$machineStatus = (string)($_GET['machine_status'] ?? '');



if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {

    $from = date('Y-m-01');

}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {

    $to = date('Y-m-d');

}



$report = ['rows' => [], 'summary' => ['total_produced' => 0, 'total_rejected' => 0, 'qc_pass_pct' => 0, 'downtime' => 0, 'active_machines' => 0]];

$error = '';



try {

    $report = prod_entry_report($pdo, $from, $to, $dept, $shift, $machineId, $operatorId, $machineStatus);

} catch (Throwable $e) {

    $error = $e->getMessage();

}



$rows = $report['rows'];

$sum = $report['summary'];



$filterQs = array_filter([

    'from' => $from,

    'to' => $to,

    'department' => $dept !== 'all' ? $dept : null,

    'shift' => $shift !== '' ? $shift : null,

    'machine_id' => $machineId > 0 ? $machineId : null,

    'operator_id' => $operatorId > 0 ? $operatorId : null,

    'machine_status' => $machineStatus !== '' ? $machineStatus : null,

]);

$exportUrl = route_url('reports/production', array_merge($filterQs, ['export' => 'csv']));



if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    header('Content-Type: text/csv; charset=utf-8');

    header('Content-Disposition: attachment; filename="production-report.csv"');

    $out = fopen('php://output', 'w');

    fputcsv($out, ['Date', 'Shift', 'Department', 'Machine', 'Machine dept', 'Machine status', 'Assigned operator', 'Tyre type', 'Produced', 'Rejected', 'Entry operator']);

    foreach ($rows as $r) {

        fputcsv($out, [

            $r['entry_date'],

            $r['shift'],

            $r['department'],

            $r['machine'],

            $r['machine_department'] ?? '',

            $r['machine_status'] ?? '',

            $r['assigned_operator'] ?? '',

            $r['tyre_type'],

            $r['produced'],

            $r['rejected'],

            $r['operator'],

        ]);

    }

    fclose($out);

    exit;

}



$machines = mach_list_machines($pdo, ['include_inactive' => true]);

$operators = [];

if ($dept !== 'all' && $dept !== 'QC') {

    $operators = prod_entry_operators($pdo, $to, $dept);

}



$totalProduced = (float)$sum['total_produced'];

$totalRejected = (float)$sum['total_rejected'];

$totalOutput = $totalProduced + $totalRejected;

$rejectPct = $totalOutput > 0 ? round(($totalRejected / $totalOutput) * 100, 1) : 0.0;

?>



<div class="prod-page mach-page">

    <header class="prod-page__head">

        <div>

            <h1 class="prod-page__title">Production Reports</h1>

            <p class="prod-page__sub">Daily production with machine assignment, operator, and machine status visibility.</p>

        </div>

        <nav class="prod-page__links">

            <a href="<?= e(route_url('machines/dashboard')) ?>">Machines</a>

            <a href="<?= e(route_url('production/dashboard')) ?>">Dashboard</a>

        </nav>

    </header>



    <?php if ($error !== ''): ?>

        <div class="alert alert-danger"><?= e($error) ?></div>

    <?php endif; ?>



    <form method="get" class="mach-filter-bar">

        <input type="hidden" name="page" value="reports/production">

        <div class="mach-filter-bar__grid">

            <div class="mach-filter-bar__field">

                <label for="rpt_from">From</label>

                <input type="date" class="form-control form-control-sm" id="rpt_from" name="from" value="<?= e($from) ?>">

            </div>

            <div class="mach-filter-bar__field">

                <label for="rpt_to">To</label>

                <input type="date" class="form-control form-control-sm" id="rpt_to" name="to" value="<?= e($to) ?>">

            </div>

            <div class="mach-filter-bar__field">

                <label for="rpt_dept">Department</label>

                <select class="form-select form-select-sm" id="rpt_dept" name="department">

                    <option value="all" <?= $dept === 'all' ? 'selected' : '' ?>>All</option>

                    <?php foreach (prod_entry_departments() as $d): ?>

                        <option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="mach-filter-bar__field mach-filter-bar__field--wide">

                <label for="rpt_machine">Machine</label>

                <select class="form-select form-select-sm erp-select-search" id="rpt_machine" name="machine_id" data-placeholder="All machines">

                    <option value="0">All machines</option>

                    <?php foreach ($machines as $m): ?>

                        <option value="<?= (int)$m['id'] ?>" <?= $machineId === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['machine_code']) ?> — <?= e($m['machine_name']) ?></option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="mach-filter-bar__field mach-filter-bar__field--wide">

                <label for="rpt_operator">Operator</label>

                <select class="form-select form-select-sm erp-select-search" id="rpt_operator" name="operator_id" data-placeholder="All operators">

                    <option value="0">All operators</option>

                    <?php foreach ($operators as $op): ?>

                        <option value="<?= (int)$op['id'] ?>" <?= $operatorId === (int)$op['id'] ? 'selected' : '' ?>><?= e($op['full_name']) ?></option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="mach-filter-bar__field">

                <label for="rpt_mach_status">Machine status</label>

                <select class="form-select form-select-sm" id="rpt_mach_status" name="machine_status">

                    <option value="">All statuses</option>

                    <?php foreach (MACHINE_MASTER_STATUSES as $st): ?>

                        <option value="<?= e($st) ?>" <?= $machineStatus === $st ? 'selected' : '' ?>><?= e($st) ?></option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="mach-filter-bar__field">

                <label for="rpt_shift">Shift</label>

                <select class="form-select form-select-sm" id="rpt_shift" name="shift">

                    <option value="">All shifts</option>

                    <?php foreach (PRODUCTION_SHIFTS as $sh): ?>

                        <option value="<?= e($sh) ?>" <?= $shift === $sh ? 'selected' : '' ?>><?= e($sh) ?></option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>

        <div class="mach-filter-bar__actions">

            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Apply filters</button>

            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('reports/production')) ?>">Reset</a>

            <a class="btn btn-outline-secondary btn-sm" href="<?= e($exportUrl) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> CSV</a>

            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>

        </div>

    </form>



    <div class="mach-kpis prod-rpt-kpis">

        <article class="mach-kpi">

            <span class="mach-kpi__label">Total production</span>

            <span class="mach-kpi__value"><?= e(number_format($totalProduced, 0)) ?></span>

        </article>

        <article class="mach-kpi mach-kpi--scrap">

            <span class="mach-kpi__label">Total rejected</span>

            <span class="mach-kpi__value"><?= e(number_format($totalRejected, 1)) ?></span>

        </article>

        <article class="mach-kpi mach-kpi--repair">

            <span class="mach-kpi__label">Reject %</span>

            <span class="mach-kpi__value"><?= e((string)$rejectPct) ?>%</span>

        </article>

        <article class="mach-kpi mach-kpi--idle">

            <span class="mach-kpi__label">Machine downtime</span>

            <span class="mach-kpi__value"><?= e((string)$sum['downtime']) ?> <span class="small fw-normal">min</span></span>

        </article>

        <article class="mach-kpi mach-kpi--active">

            <span class="mach-kpi__label">Active machines</span>

            <span class="mach-kpi__value"><?= e((string)$sum['active_machines']) ?></span>

        </article>

        <?php if ($dept === 'QC' || $dept === 'all'): ?>

            <article class="mach-kpi">

                <span class="mach-kpi__label">QC pass %</span>

                <span class="mach-kpi__value"><?= e((string)$sum['qc_pass_pct']) ?>%</span>

            </article>

        <?php endif; ?>

    </div>



    <section class="prod-card prod-card--table prod-rpt-table-card">

        <div class="prod-card__head d-flex justify-content-between align-items-center flex-wrap gap-2">

            <h2 class="prod-card__title mb-0">Production entries</h2>

            <span class="small text-muted"><?= e((string)count($rows)) ?> entries</span>

        </div>

        <div class="prod-entry-table-scroll-hint" data-prod-entry-scroll-hint>

            <i class="bi bi-arrows-expand" aria-hidden="true"></i> Scroll down or sideways to view all rows and columns

        </div>

        <div class="prod-rpt-table-wrap" tabindex="0" data-prod-entry-scroll aria-label="Production report table">

            <table class="table table-sm prod-table mb-0 prod-rpt-table">

                <thead>

                    <tr>

                        <th>Date</th>

                        <th>Shift</th>

                        <th>Dept</th>

                        <th>Machine</th>

                        <th>Mach. dept</th>

                        <th>Mach. status</th>

                        <th>Assigned op.</th>

                        <th>Tyre type</th>

                        <th class="text-end">Produced</th>

                        <th class="text-end">Rejected</th>

                        <th>Entry operator</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($rows as $r): ?>

                    <?php $mb = mach_status_badge((string)($r['machine_status'] ?? '')); ?>

                    <tr>

                        <td class="text-nowrap"><?= e($r['entry_date']) ?></td>

                        <td><?= e($r['shift']) ?></td>

                        <td><?= e($r['department']) ?></td>

                        <td><strong><?= e($r['machine']) ?></strong></td>

                        <td><?= e($r['machine_department'] ?? '—') ?></td>

                        <td><span class="<?= e($mb['class']) ?>"><?= e($mb['label']) ?></span></td>

                        <td><?= e($r['assigned_operator'] ?? '—') ?></td>

                        <td><?= e($r['tyre_type']) ?></td>

                        <td class="text-end"><?= e((string)$r['produced']) ?></td>

                        <td class="text-end"><?= e((string)$r['rejected']) ?></td>

                        <td><?= e($r['operator']) ?></td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$rows): ?>

                    <tr>

                        <td colspan="11" class="text-center text-muted py-4">No production entries found for the selected filters.</td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>

