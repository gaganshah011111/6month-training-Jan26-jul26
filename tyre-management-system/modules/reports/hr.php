<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/hr_reports_service.php';

if (!has_role(['Super Admin', 'HR Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$filters = hr_reports_parse_filters($_GET);
$bundle = hr_reports_bundle($pdo, $filters);
$companyName = hr_reports_company_name($pdo);

$export = (string)($_GET['export'] ?? '');
if ($export === 'excel') {
    hr_reports_export_csv($filters, $bundle, $companyName);
    exit;
}

if ($export === 'pdf' || $export === 'print') {
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/hr_print.php';
    exit;
}

$departments = $pdo->query('SELECT id, department_name FROM departments ORDER BY department_name')->fetchAll(PDO::FETCH_ASSOC);
$summary = $bundle['summary'];
$totals = $bundle['totals'];

$filterQs = array_filter([
    'page' => 'reports/hr',
    'from' => $filters['from'],
    'to' => $filters['to'],
    'department_id' => $filters['department_id'] > 0 ? $filters['department_id'] : null,
    'employee_type' => $filters['employee_type'] !== '' ? $filters['employee_type'] : null,
]);
$exportBase = 'index.php?' . http_build_query($filterQs);

$jsPath = __DIR__ . '/../../assets/js/hr-reports.js';
$jsVer = is_file($jsPath) ? (int)filemtime($jsPath) : time();
?>

<div class="hr-page hr-reports module-shell" id="hrReportsRoot">
    <header class="hr-reports__head">
        <h1 class="hr-reports__title">HR Reports</h1>
        <p class="hr-reports__sub">Operational reports · <?= e($filters['from']) ?> to <?= e($filters['to']) ?></p>
    </header>

    <form method="get" class="hr-reports__filters" id="hrReportsFilterForm">
        <input type="hidden" name="page" value="reports/hr">
        <div class="hr-reports__field">
            <label for="hr_from">From Date</label>
            <input type="date" name="from" id="hr_from" class="form-control form-control-sm" value="<?= e($filters['from']) ?>" required>
        </div>
        <div class="hr-reports__field">
            <label for="hr_to">To Date</label>
            <input type="date" name="to" id="hr_to" class="form-control form-control-sm" value="<?= e($filters['to']) ?>" required>
        </div>
        <div class="hr-reports__field">
            <label for="hr_dept">Department</label>
            <select name="department_id" id="hr_dept" class="form-select form-select-sm">
                <option value="">All departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $filters['department_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= e((string)$d['department_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="hr-reports__field">
            <label for="hr_etype">Employee Type</label>
            <select name="employee_type" id="hr_etype" class="form-select form-select-sm">
                <option value="">All types</option>
                <option value="Staff" <?= $filters['employee_type'] === 'Staff' ? 'selected' : '' ?>>Staff</option>
                <option value="Worker" <?= $filters['employee_type'] === 'Worker' ? 'selected' : '' ?>>Worker</option>
            </select>
        </div>
        <div class="hr-reports__actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="hrReportPrint"><i class="bi bi-printer"></i> Print</button>
            <a href="<?= e($exportBase . '&export=pdf') ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
            <a href="<?= e($exportBase . '&export=excel') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
        </div>
    </form>

    <section class="hr-reports__summary" aria-label="Report summary">
        <div class="hr-sum-card"><span>Present %</span><strong><?= e($summary['present_pct']) ?></strong></div>
        <div class="hr-sum-card"><span>Payroll Amount</span><strong><?= e($summary['payroll_amount']) ?></strong></div>
        <div class="hr-sum-card"><span>Leave Requests</span><strong><?= e((string)$summary['leave_requests']) ?></strong></div>
        <div class="hr-sum-card"><span>Overtime Hours</span><strong><?= e($summary['overtime_hours']) ?></strong></div>
    </section>

    <section class="hr-report-block">
        <h2 class="hr-report-block__title">Employee Attendance Report</h2>
        <p class="hr-report-block__desc">Attendance summary by employee for the selected period.</p>
        <div class="table-responsive">
            <table class="table table-sm hr-rpt-table mb-0">
                <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th class="text-end">Present Days</th>
                    <th class="text-end">Absent Days</th>
                    <th class="text-end">Leave Days</th>
                    <th class="text-end">Late Count</th>
                    <th class="text-end">OT Hours</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$bundle['attendance']): ?>
                    <tr><td colspan="8" class="text-muted text-center py-3">No attendance records for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($bundle['attendance'] as $r): ?>
                        <tr>
                            <td><?= e((string)$r['employee_code']) ?></td>
                            <td><?= e((string)$r['full_name']) ?></td>
                            <td><?= e((string)$r['department_name']) ?></td>
                            <td class="text-end"><?= (int)$r['present_days'] ?></td>
                            <td class="text-end"><?= (int)$r['absent_days'] ?></td>
                            <td class="text-end"><?= (int)$r['leave_days'] ?></td>
                            <td class="text-end"><?= (int)$r['late_count'] ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['ot_hours'], 1)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="hr-rpt-table__total">
                        <td colspan="3"><strong>Total</strong></td>
                        <td class="text-end"><strong><?= (int)$totals['attendance_present'] ?></strong></td>
                        <td class="text-end"><strong><?= (int)$totals['attendance_absent'] ?></strong></td>
                        <td colspan="3"></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="hr-report-block">
        <h2 class="hr-report-block__title">Payroll Register</h2>
        <p class="hr-report-block__desc">Payroll summary by employee for months in the selected date range.</p>
        <div class="table-responsive">
            <table class="table table-sm hr-rpt-table mb-0">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th class="text-end">Gross Salary</th>
                    <th class="text-end">PF</th>
                    <th class="text-end">ESI</th>
                    <th class="text-end">Deductions</th>
                    <th class="text-end">OT Amount</th>
                    <th class="text-end">Net Salary</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$bundle['payroll']): ?>
                    <tr><td colspan="9" class="text-muted text-center py-3">No payroll records for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($bundle['payroll'] as $r): ?>
                        <tr>
                            <td><?= e((string)$r['full_name']) ?></td>
                            <td><?= e((string)$r['department_name']) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['gross_salary'], 2)) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['pf_amount'], 2)) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['esi_amount'], 2)) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['deductions'], 2)) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['ot_amount'], 2)) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['net_salary'], 2)) ?></td>
                            <td><span class="hr-status-pill"><?= e(ucfirst((string)$r['payment_status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="hr-rpt-table__total">
                        <td colspan="2"><strong>Total</strong></td>
                        <td class="text-end"><strong><?= e(number_format((float)$totals['payroll_gross'], 2)) ?></strong></td>
                        <td colspan="3"></td>
                        <td class="text-end"><strong><?= e(number_format((float)$totals['payroll_net'], 2)) ?></strong></td>
                        <td></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="hr-report-block">
        <h2 class="hr-report-block__title">Leave Report</h2>
        <p class="hr-report-block__desc">Leave usage and pending requests by employee.</p>
        <div class="table-responsive">
            <table class="table table-sm hr-rpt-table mb-0">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th class="text-end">Leave Days</th>
                    <th class="text-end">Paid Leave</th>
                    <th class="text-end">Half Paid</th>
                    <th class="text-end">Unpaid</th>
                    <th class="text-end">Pending</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$bundle['leave']): ?>
                    <tr><td colspan="7" class="text-muted text-center py-3">No leave records for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($bundle['leave'] as $r): ?>
                        <tr>
                            <td><?= e((string)$r['full_name']) ?></td>
                            <td><?= e((string)$r['department_name']) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['leave_days'], 1)) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['paid_leave'], 1)) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['half_paid'], 1)) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['unpaid'], 1)) ?></td>
                            <td class="text-end"><?= (int)$r['pending_requests'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="hr-report-block">
        <h2 class="hr-report-block__title">Department Summary</h2>
        <p class="hr-report-block__desc">Workforce and payroll overview by department.</p>
        <div class="table-responsive">
            <table class="table table-sm hr-rpt-table mb-0">
                <thead>
                <tr>
                    <th>Department</th>
                    <th class="text-end">Employees</th>
                    <th class="text-end">Present %</th>
                    <th class="text-end">Leave %</th>
                    <th class="text-end">OT Hours</th>
                    <th class="text-end">Payroll Cost</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$bundle['departments']): ?>
                    <tr><td colspan="6" class="text-muted text-center py-3">No department data for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($bundle['departments'] as $d): ?>
                        <tr>
                            <td><?= e((string)$d['department']) ?></td>
                            <td class="text-end"><?= (int)$d['employees'] ?></td>
                            <td class="text-end"><?= (int)$d['present_pct'] ?>%</td>
                            <td class="text-end"><?= (int)$d['leave_pct'] ?>%</td>
                            <td class="text-end"><?= e(number_format((float)$d['ot_hours'], 1)) ?></td>
                            <td class="text-end"><?= e(number_format((float)$d['payroll_cost'], 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script src="assets/js/hr-reports.js?v=<?= e((string)$jsVer) ?>"></script>
