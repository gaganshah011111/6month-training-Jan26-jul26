<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/payroll_service.php';
require_once __DIR__ . '/../../../includes/payroll_test_data.php';

if (!has_role(['Super Admin', 'HR Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
verify_csrf();

$payrollMonth = (string)($_GET['month_year'] ?? $_POST['month_year'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $payrollMonth)) {
    $payrollMonth = date('Y-m');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'generate');
    try {
        if ($action === 'mark_paid') {
            $sid = post_int('salary_id');
            if (!payroll_mark_paid($pdo, $sid)) {
                throw new RuntimeException('Could not mark payslip as paid.');
            }
            set_flash('success', 'Salary marked as paid.');
        } elseif (in_array($action, ['generate_test_payroll', 'clear_test_data', 'clear_test_data_all'], true)) {
            if (!is_payroll_test_tools_enabled()) {
                throw new RuntimeException('Payroll test tools are disabled in this environment.');
            }
            $month = (string)($_POST['month_year'] ?? $payrollMonth);
            $clearPayroll = !empty($_POST['clear_payroll']);
            if ($action === 'generate_test_payroll') {
                $defaults = payroll_test_default_counts();
                $testOpts = [
                    'present' => isset($_POST['test_present']) && $_POST['test_present'] !== '' ? post_int('test_present') : $defaults['present'],
                    'half_days' => isset($_POST['test_half_days']) && $_POST['test_half_days'] !== '' ? post_int('test_half_days') : $defaults['half_days'],
                    'absent' => isset($_POST['test_absent']) && $_POST['test_absent'] !== '' ? post_int('test_absent') : $defaults['absent'],
                    'late' => isset($_POST['test_late']) && $_POST['test_late'] !== '' ? post_int('test_late') : $defaults['late'],
                    'ot_hours' => isset($_POST['test_ot_hours']) && $_POST['test_ot_hours'] !== '' ? post_float('test_ot_hours') : $defaults['ot_hours'],
                    'regenerate' => true,
                    'clear_payroll' => $clearPayroll,
                ];
                $employeeId = post_int('employee_id');
                $res = payroll_test_generate_with_payroll($pdo, $employeeId, $month, $testOpts);
                payroll_test_set_notice(payroll_test_notice_from_calc(
                    $res['calc'],
                    $employeeId,
                    $res['employee_name'],
                    $res['employee_code'],
                    $month,
                    (int)$res['salary_id']
                ));
                set_flash('success', 'Test payroll generated successfully for ' . $res['employee_name'] . '.');
            } elseif ($action === 'clear_test_data') {
                $employeeId = post_int('employee_id');
                $r = payroll_test_clear($pdo, $employeeId, $month, $clearPayroll);
                set_flash('success', "Cleared {$r['deleted']} test record(s) for this employee.");
            } else {
                $r = payroll_test_clear_all($pdo, $month, $clearPayroll);
                set_flash('success', "Cleared test data ({$r['deleted']} attendance rows).");
            }
        } elseif ($action === 'generate_all_pending') {
            $month = (string)($_POST['month_year'] ?? $payrollMonth);
            $filters = ['salary_status' => 'pending'];
            $pending = payroll_list_employees($pdo, $month, $filters);
            $n = 0;
            foreach ($pending as $emp) {
                $calc = payroll_build_calculation($pdo, $emp, $month);
                payroll_save_record($pdo, (int)$emp['id'], $month, $calc, false);
                $n++;
            }
            set_flash('success', "Payroll generated for {$n} employee(s) for " . payroll_format_month_label($month) . '.');
        } else {
            $employeeId = post_int('employee_id');
            $month = (string)($_POST['month_year'] ?? $payrollMonth);
            $extraOt = post_float('overtime_hours');
            $manualDed = post_float('deductions');
            $asDraft = $action === 'save_draft';

            $empStmt = $pdo->prepare('SELECT * FROM employees WHERE id=:id');
            $empStmt->execute(['id' => $employeeId]);
            $emp = $empStmt->fetch(PDO::FETCH_ASSOC);
            if (!$emp) {
                throw new RuntimeException('Employee not found.');
            }
            $calc = payroll_build_calculation($pdo, $emp, $month, $extraOt, $manualDed);
            payroll_save_record($pdo, $employeeId, $month, $calc, $asDraft);
            if (!$asDraft) {
                payroll_test_set_notice(payroll_test_notice_from_calc(
                    $calc,
                    $employeeId,
                    (string)$emp['full_name'],
                    (string)$emp['employee_code'],
                    $month,
                    payroll_get_salary_id($pdo, $employeeId, $month)
                ));
            }
            set_flash('success', $asDraft ? 'Payroll saved as draft.' : 'Payroll generated. Review the salary summary below.');
        }
    } catch (Throwable $e) {
        set_flash('danger', 'Payroll action failed: ' . $e->getMessage());
    }
    header('Location: ' . route_url('payroll/list') . '&month_year=' . urlencode($payrollMonth));
    exit;
}

$filters = [
    'employee_code' => trim((string)($_GET['employee_code'] ?? '')),
    'employee_name' => trim((string)($_GET['employee_name'] ?? '')),
    'department' => trim((string)($_GET['department'] ?? '')),
    'employee_type' => trim((string)($_GET['employee_type'] ?? '')),
    'salary_status' => trim((string)($_GET['salary_status'] ?? '')),
];

$summary = payroll_dashboard_summary($pdo, $payrollMonth);
$employees = payroll_list_employees($pdo, $payrollMonth, $filters);
$deptOptions = payroll_department_filter_options($pdo);
$monthLabel = payroll_format_month_label($payrollMonth);
$calcApiUrl = route_url('api/payroll-calculate');
$testPreviewApiUrl = route_url('api/payroll-test-preview');
$scriptBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
$calcApiUrlAbs = ($scriptBase !== '' ? $scriptBase . '/' : '/') . ltrim($calcApiUrl, '/');
$testPreviewApiUrlAbs = ($scriptBase !== '' ? $scriptBase . '/' : '/') . ltrim($testPreviewApiUrl, '/');
$previewScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$testPreviewApiUrlFull = $previewScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $testPreviewApiUrlAbs;
$testToolsEnabled = is_payroll_test_tools_enabled();
$testDefaults = payroll_test_default_counts();
$activeEmployeesForTest = [];
if ($testToolsEnabled) {
    $activeEmployeesForTest = $pdo->query("SELECT id, employee_code, full_name FROM employees WHERE status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
}
$payrollNotice = payroll_test_take_notice();
$highlightEmployeeId = (int)($payrollNotice['employee_id'] ?? 0);
?>
<div class="module-shell payroll-dashboard">
    <div class="payroll-dashboard__head d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h4 class="mb-1">Payroll Management</h4>
            <p class="text-muted mb-0 small">Generate monthly salary from attendance, leave, overtime &amp; payroll settings — <?= e($monthLabel) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <form method="get" class="d-flex align-items-center gap-2">
                <input type="hidden" name="page" value="payroll/list">
                <?php foreach ($filters as $fk => $fv): if ($fk !== 'month_year' && $fv !== ''): ?>
                    <input type="hidden" name="<?= e($fk) ?>" value="<?= e($fv) ?>">
                <?php endif; endforeach; ?>
                <label class="small text-muted mb-0">Payroll month</label>
                <input type="month" class="form-control form-control-sm" name="month_year" value="<?= e($payrollMonth) ?>" onchange="this.form.submit()">
            </form>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('hr/payroll-settings')) ?>"><i class="bi bi-sliders me-1"></i>Settings</a>
        </div>
    </div>

    <div class="row g-3 mb-4 payroll-stat-row">
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="payroll-stat-card payroll-stat-card--blue">
                <span class="payroll-stat-card__icon"><i class="bi bi-people"></i></span>
                <span class="payroll-stat-card__label">Total Employees</span>
                <span class="payroll-stat-card__value"><?= e((string)$summary['total_employees']) ?></span>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="payroll-stat-card payroll-stat-card--green">
                <span class="payroll-stat-card__icon"><i class="bi bi-check-circle"></i></span>
                <span class="payroll-stat-card__label">Payroll Generated</span>
                <span class="payroll-stat-card__value"><?= e((string)$summary['generated']) ?></span>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="payroll-stat-card payroll-stat-card--red">
                <span class="payroll-stat-card__icon"><i class="bi bi-hourglass-split"></i></span>
                <span class="payroll-stat-card__label">Payroll Pending</span>
                <span class="payroll-stat-card__value"><?= e((string)$summary['pending']) ?></span>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="payroll-stat-card payroll-stat-card--purple">
                <span class="payroll-stat-card__icon"><i class="bi bi-currency-rupee"></i></span>
                <span class="payroll-stat-card__label">Total Salary Expense</span>
                <span class="payroll-stat-card__value">₹<?= e(number_format($summary['total_expense'], 0)) ?></span>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="payroll-stat-card payroll-stat-card--amber">
                <span class="payroll-stat-card__icon"><i class="bi bi-clock-history"></i></span>
                <span class="payroll-stat-card__label">Total OT Amount</span>
                <span class="payroll-stat-card__value">₹<?= e(number_format($summary['total_ot'], 0)) ?></span>
            </div>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="payroll-stat-card payroll-stat-card--teal">
                <span class="payroll-stat-card__icon"><i class="bi bi-calendar-x"></i></span>
                <span class="payroll-stat-card__label">On Leave (month)</span>
                <span class="payroll-stat-card__value"><?= e((string)$summary['on_leave']) ?></span>
            </div>
        </div>
    </div>

    <?php if ($testToolsEnabled): ?>
    <div class="card payroll-test-tools-card mb-4 border-warning">
        <div class="card-body py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <strong class="text-warning-emphasis"><i class="bi bi-lightning-charge me-1"></i>Development testing</strong>
                <p class="small text-muted mb-0">One click: sample attendance + payroll for <?= e($monthLabel) ?>.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#payrollTestDataModal">
                    <i class="bi bi-lightning-charge me-1"></i>Generate Test Payroll
                </button>
                <form method="post" class="d-inline mb-0" onsubmit="return confirm('Clear all test data for this month?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="clear_test_data_all">
                    <input type="hidden" name="month_year" value="<?= e($payrollMonth) ?>">
                    <button type="submit" class="btn btn-link btn-sm text-muted">Clear test data</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <div class="card payroll-filter-card mb-4">
        <div class="card-header"><strong><i class="bi bi-funnel me-2"></i>Search &amp; filter employees</strong></div>
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="payroll/list">
                <input type="hidden" name="month_year" value="<?= e($payrollMonth) ?>">
                <div class="col-md-6 col-lg-2">
                    <label class="form-label">Employee ID</label>
                    <input class="form-control" name="employee_code" value="<?= e($filters['employee_code']) ?>" placeholder="EMP001">
                </div>
                <div class="col-md-6 col-lg-3">
                    <label class="form-label">Employee name</label>
                    <input class="form-control" name="employee_name" value="<?= e($filters['employee_name']) ?>" placeholder="Search name">
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label">Department</label>
                    <select class="form-select" name="department">
                        <option value="">All departments</option>
                        <?php foreach ($deptOptions as $dept): ?>
                            <option value="<?= e($dept) ?>" <?= $filters['department'] === $dept ? 'selected' : '' ?>><?= e($dept) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label">Employee type</label>
                    <select class="form-select" name="employee_type">
                        <option value="">All types</option>
                        <option value="Staff" <?= $filters['employee_type'] === 'Staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="Worker" <?= $filters['employee_type'] === 'Worker' ? 'selected' : '' ?>>Worker</option>
                    </select>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label">Salary status</label>
                    <select class="form-select" name="salary_status">
                        <option value="">All statuses</option>
                        <option value="pending" <?= $filters['salary_status'] === 'pending' ? 'selected' : '' ?>>Payroll pending</option>
                        <option value="generated" <?= $filters['salary_status'] === 'generated' ? 'selected' : '' ?>>Payroll generated</option>
                        <option value="draft" <?= $filters['salary_status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="paid" <?= $filters['salary_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="unpaid" <?= $filters['salary_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid (generated)</option>
                    </select>
                </div>
                <div class="col-md-6 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Search</button>
                    <a class="btn btn-outline-secondary" href="<?= e(route_url('payroll/list') . '&month_year=' . urlencode($payrollMonth)) ?>">Reset</a>
                </div>
            </form>
            <?php if ($summary['pending'] > 0): ?>
            <form method="post" class="mt-3 pt-3 border-top" onsubmit="return confirm('Generate payroll for all <?= (int)$summary['pending'] ?> pending employees for <?= e($monthLabel) ?>?');">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="generate_all_pending">
                <input type="hidden" name="month_year" value="<?= e($payrollMonth) ?>">
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-lightning me-1"></i>Generate all pending (<?= (int)$summary['pending'] ?>)</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card payroll-table-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong>Employee payroll — <?= e($monthLabel) ?></strong>
            <span class="small text-muted"><?= count($employees) ?> shown</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 payroll-emp-table">
                <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th class="text-end">Gross</th>
                    <th class="text-end">OT</th>
                    <th class="text-end">Deductions</th>
                    <th class="text-end">Net salary</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$employees): ?>
                    <tr><td colspan="10" class="text-center text-muted py-5">No employees match your filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($employees as $emp):
                    $badge = payroll_status_badge((string)$emp['ui_status']);
                    $salaryId = (int)($emp['salary_id'] ?? 0);
                    $otAmt = (float)($emp['overtime_amount'] ?? 0);
                    $ded = (float)($emp['total_deduction'] ?? 0);
                    $net = (float)($emp['net_salary'] ?? 0);
                    $gross = (float)($emp['display_gross'] ?? 0);
                ?>
                    <tr id="payroll-emp-row-<?= (int)$emp['id'] ?>" class="<?= $highlightEmployeeId === (int)$emp['id'] ? 'payroll-row-highlight' : '' ?>">
                        <td><span class="font-monospace small fw-semibold"><?= e((string)$emp['employee_code']) ?></span></td>
                        <td class="fw-semibold"><?= e((string)$emp['full_name']) ?></td>
                        <td><span class="payroll-dept-cell" title="<?= e((string)($emp['dept_label'] ?? '')) ?>"><?= e((string)($emp['dept_label'] ?? '—')) ?></span></td>
                        <td class="small text-muted"><?= e((string)($emp['desig_label'] ?? '—')) ?></td>
                        <td class="text-end">₹<?= e(number_format($gross, 0)) ?></td>
                        <td class="text-end"><?= $salaryId ? '₹' . e(number_format($otAmt, 0)) : '—' ?></td>
                        <td class="text-end"><?= $salaryId ? '₹' . e(number_format($ded, 0)) : '—' ?></td>
                        <td class="text-end fw-bold"><?= $salaryId ? '₹' . e(number_format($net, 0)) : '—' ?></td>
                        <td><span class="<?= e($badge['class']) ?>"><?= e($badge['label']) ?></span></td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-primary payroll-gen-btn"
                                data-bs-toggle="modal" data-bs-target="#payrollGenModal"
                                data-employee-id="<?= (int)$emp['id'] ?>"
                                data-employee-name="<?= e((string)$emp['full_name']) ?>"
                                data-employee-code="<?= e((string)$emp['employee_code']) ?>"
                                data-department="<?= e((string)($emp['dept_label'] ?? '')) ?>"
                                data-designation="<?= e((string)($emp['desig_label'] ?? '')) ?>"
                                data-gross="<?= e((string)$gross) ?>"
                                title="Generate / recalculate payroll">
                                <i class="bi bi-calculator"></i>
                            </button>
                            <?php if ($salaryId): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('payroll/payslip') . '&id=' . $salaryId) ?>" target="_blank" title="View payslip"><i class="bi bi-eye"></i></a>
                                <a class="btn btn-sm btn-outline-danger" href="<?= e(route_url('payroll/payslip') . '&id=' . $salaryId) ?>" target="_blank" onclick="setTimeout(function(){window.print();},500); return true;" title="Download PDF"><i class="bi bi-file-pdf"></i></a>
                                <?php if ((string)$emp['ui_status'] !== 'paid'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Mark this salary as paid?');">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="mark_paid">
                                    <input type="hidden" name="salary_id" value="<?= $salaryId ?>">
                                    <input type="hidden" name="month_year" value="<?= e($payrollMonth) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Mark paid"><i class="bi bi-check2"></i></button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Generate Payroll Modal -->
<div class="modal fade" id="payrollGenModal" tabindex="-1" aria-labelledby="payrollGenModalLabel" aria-hidden="true"
     data-calc-url="<?= e($calcApiUrlAbs) ?>" data-month="<?= e($payrollMonth) ?>">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <form method="post" id="payrollGenForm">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="generate" id="payrollFormAction">
                <input type="hidden" name="employee_id" id="payrollEmpId" value="">
                <input type="hidden" name="month_year" value="<?= e($payrollMonth) ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="payrollGenModalLabel">Generate payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="payrollModalLoading" class="text-center py-5 text-muted d-none">
                        <div class="spinner-border text-danger" role="status"></div>
                        <p class="mt-2 mb-0">Calculating from attendance &amp; settings…</p>
                    </div>
                    <div id="payrollModalBody">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="payroll-modal-panel">
                                    <h6 class="payroll-modal-panel__title">Employee information</h6>
                                    <dl class="row small mb-0 payroll-dl">
                                        <dt class="col-5">Name</dt><dd class="col-7" id="pmName">—</dd>
                                        <dt class="col-5">Employee ID</dt><dd class="col-7" id="pmCode">—</dd>
                                        <dt class="col-5">Department</dt><dd class="col-7" id="pmDept">—</dd>
                                        <dt class="col-5">Designation</dt><dd class="col-7" id="pmDesig">—</dd>
                                        <dt class="col-5">Fixed gross</dt><dd class="col-7" id="pmFixedGross">—</dd>
                                    </dl>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="payroll-modal-panel">
                                    <h6 class="payroll-modal-panel__title">Attendance summary</h6>
                                    <dl class="row small mb-0 payroll-dl">
                                        <dt class="col-6">Present days</dt><dd class="col-6" id="pmPresent">—</dd>
                                        <dt class="col-6">Half days</dt><dd class="col-6" id="pmHalf">—</dd>
                                        <dt class="col-6">Absent days</dt><dd class="col-6" id="pmAbsent">—</dd>
                                        <dt class="col-6">Late days</dt><dd class="col-6" id="pmLate">—</dd>
                                        <dt class="col-6">OT hours</dt><dd class="col-6" id="pmOtH">—</dd>
                                        <dt class="col-6">Paid leave</dt><dd class="col-6" id="pmPL">—</dd>
                                        <dt class="col-6">Unpaid leave</dt><dd class="col-6" id="pmUL">—</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Extra OT hours (optional)</label>
                                <input class="form-control" type="number" step="0.01" name="overtime_hours" id="pmExtraOt" value="0">
                                <small class="text-muted">Added to attendance OT before calculation</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Manual deduction (₹)</label>
                                <input class="form-control" type="number" step="0.01" name="deductions" id="pmManualDed" value="0">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-primary w-100" id="pmRecalcBtn"><i class="bi bi-arrow-clockwise me-1"></i>Recalculate preview</button>
                            </div>
                        </div>
                        <div class="payroll-modal-panel">
                            <h6 class="payroll-modal-panel__title">Payroll calculation (auto)</h6>
                            <div class="row g-2 small">
                                <div class="col-6 col-md-3"><span class="text-muted">Basic</span><div class="fw-semibold" id="pmBasic">—</div></div>
                                <div class="col-6 col-md-3"><span class="text-muted">HRA</span><div class="fw-semibold" id="pmHra">—</div></div>
                                <div class="col-6 col-md-3"><span class="text-muted">DA</span><div class="fw-semibold" id="pmDa">—</div></div>
                                <div class="col-6 col-md-3"><span class="text-muted">Medical</span><div class="fw-semibold" id="pmMed">—</div></div>
                                <div class="col-6 col-md-3"><span class="text-muted">Travel</span><div class="fw-semibold" id="pmTravel">—</div></div>
                                <div class="col-6 col-md-3"><span class="text-muted">Special</span><div class="fw-semibold" id="pmSpecial">—</div></div>
                                <div class="col-6 col-md-3"><span class="text-muted">PF</span><div class="fw-semibold" id="pmPf">—</div></div>
                                <div class="col-6 col-md-3"><span class="text-muted">ESI</span><div class="fw-semibold" id="pmEsi">—</div></div>
                                <div class="col-6 col-md-3"><span class="text-muted">OT amount</span><div class="fw-semibold" id="pmOtAmt">—</div></div>
                            </div>
                        </div>
                        <div class="payroll-final-summary mt-3">
                            <div class="row g-2 text-center">
                                <div class="col-4"><span class="small text-muted">Gross salary</span><div class="fs-5 fw-bold" id="pmGross">—</div></div>
                                <div class="col-4"><span class="small text-muted">Total deductions</span><div class="fs-5 fw-bold text-danger" id="pmDed">—</div></div>
                                <div class="col-4"><span class="small text-muted">In-hand salary</span><div class="fs-5 fw-bold text-success" id="pmNet">—</div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-outline-warning" formaction="" onclick="document.getElementById('payrollFormAction').value='save_draft'">Save draft</button>
                    <button type="submit" class="btn btn-primary" onclick="document.getElementById('payrollFormAction').value='generate'"><i class="bi bi-check-lg me-1"></i>Generate payroll</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($testToolsEnabled): ?>
<div class="modal fade payroll-test-modal" id="payrollTestDataModal" tabindex="-1" aria-labelledby="payrollTestDataModalLabel" aria-hidden="true"
     data-preview-url="<?= e($testPreviewApiUrlAbs) ?>" data-month="<?= e($payrollMonth) ?>">
    <div class="modal-dialog modal-dialog-scrollable payroll-test-modal__dialog">
        <div class="modal-content payroll-test-modal__content">
            <form method="post" id="payrollTestDataForm" class="payroll-test-modal__form" onsubmit="return confirm('Generate test attendance and payroll for the selected employee?');">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="generate_test_payroll">
                <input type="hidden" name="month_year" value="<?= e($payrollMonth) ?>">
                <input type="hidden" name="regenerate" value="1">
                <div class="modal-header payroll-test-modal__header">
                    <div>
                        <h5 class="modal-title mb-1" id="payrollTestDataModalLabel"><i class="bi bi-lightning-charge-fill me-2"></i>Generate Test Payroll</h5>
                        <p class="payroll-test-modal__subtitle mb-0">Sample attendance + payroll for <?= e($monthLabel) ?> · Dev / localhost only</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body payroll-test-modal__body">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="payroll-test-panel mb-4">
                                <label class="form-label fw-semibold" for="payrollTestEmployeeId">Employee</label>
                                <select class="form-select form-select-lg payroll-test-modal__select" name="employee_id" id="payrollTestEmployeeId" required onchange="if(window.loadPayrollTestPreview){window.loadPayrollTestPreview();}">
                                    <option value="">— Select employee —</option>
                                    <?php foreach ($activeEmployeesForTest as $ae): ?>
                                        <option value="<?= (int)$ae['id'] ?>"><?= e((string)$ae['employee_code']) ?> — <?= e((string)$ae['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="payroll-test-panel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="payroll-test-panel__title mb-0"><i class="bi bi-sliders me-1"></i>Sample attendance counts</h6>
                                    <span class="badge text-bg-light border">Optional adjustments</span>
                                </div>
                                <div class="row g-3 payroll-test-counts">
                                    <div class="col-sm-6 col-xl-4">
                                        <label class="form-label" for="ptPresent">Present days</label>
                                        <input class="form-control form-control-lg" type="number" name="test_present" id="ptPresent" min="0" max="31" value="<?= (int)$testDefaults['present'] ?>">
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <label class="form-label" for="ptHalf">Half days</label>
                                        <input class="form-control form-control-lg" type="number" name="test_half_days" id="ptHalf" min="0" max="15" value="<?= (int)$testDefaults['half_days'] ?>">
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <label class="form-label" for="ptAbsent">Absent days</label>
                                        <input class="form-control form-control-lg" type="number" name="test_absent" id="ptAbsent" min="0" max="15" value="<?= (int)$testDefaults['absent'] ?>">
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <label class="form-label" for="ptOt">OT hours (total)</label>
                                        <input class="form-control form-control-lg" type="number" step="0.01" name="test_ot_hours" id="ptOt" min="0" value="<?= e((string)$testDefaults['ot_hours']) ?>">
                                    </div>
                                    <div class="col-sm-6 col-xl-4">
                                        <label class="form-label" for="ptLate">Late entries</label>
                                        <input class="form-control form-control-lg" type="number" name="test_late" id="ptLate" min="0" max="15" value="<?= (int)$testDefaults['late'] ?>">
                                    </div>
                                </div>
                                <p class="small text-muted mb-0 mt-3"><i class="bi bi-info-circle me-1"></i>Preview updates when you change employee or counts. Actual rows are created on generate.</p>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="payroll-test-preview-wrap">
                                <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
                                    <h6 class="payroll-test-panel__title mb-0"><i class="bi bi-graph-up-arrow me-1"></i>Estimated payroll preview</h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="ptRefreshPreviewBtn"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                                </div>
                                <div id="ptPreviewLoading" class="payroll-test-preview-loading d-none">
                                    <div class="spinner-border text-danger" role="status"></div>
                                    <p class="small text-muted mt-2 mb-0">Calculating estimate…</p>
                                </div>
                                <div id="ptPreviewEmpty" class="payroll-test-preview-empty">
                                    <i class="bi bi-person-badge"></i>
                                    <p class="mb-0">Select an employee to see estimated gross, deductions, and net salary.</p>
                                </div>
                                <div id="ptPreviewBody" class="d-none">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="payroll-test-preview-card payroll-test-preview-card--gross">
                                                <span class="payroll-test-preview-card__label">Gross salary</span>
                                                <span class="payroll-test-preview-card__value" id="ptGross">—</span>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="payroll-test-preview-card payroll-test-preview-card--ded">
                                                <span class="payroll-test-preview-card__label">Estimated deduction</span>
                                                <span class="payroll-test-preview-card__value" id="ptDed">—</span>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="payroll-test-preview-card payroll-test-preview-card--net">
                                                <span class="payroll-test-preview-card__label">Estimated net salary</span>
                                                <span class="payroll-test-preview-card__value" id="ptNet">—</span>
                                            </div>
                                        </div>
                                    </div>
                                    <dl class="row small payroll-test-preview-meta mt-3 mb-0">
                                        <dt class="col-6 text-muted">Present (eff.)</dt><dd class="col-6 fw-semibold mb-1" id="ptMetaPresent">—</dd>
                                        <dt class="col-6 text-muted">OT amount</dt><dd class="col-6 fw-semibold mb-1" id="ptMetaOt">—</dd>
                                        <dt class="col-6 text-muted">PF</dt><dd class="col-6 fw-semibold mb-0" id="ptMetaPf">—</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer payroll-test-modal__footer">
                    <button type="button" class="btn btn-lg btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-lg btn-danger payroll-test-modal__submit"><i class="bi bi-lightning-charge-fill me-1"></i>Generate Test Payroll</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Generated payroll result preview -->
<div class="modal fade" id="payrollResultModal" tabindex="-1" aria-labelledby="payrollResultModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success-subtle">
                <h5 class="modal-title" id="payrollResultModalLabel"><i class="bi bi-check-circle me-1"></i>Payroll generated</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3" id="prEmpLine">—</p>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="payroll-stat-card payroll-stat-card--purple py-3">
                            <span class="payroll-stat-card__label">Gross salary</span>
                            <span class="payroll-stat-card__value" id="prGross">—</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="payroll-stat-card payroll-stat-card--red py-3">
                            <span class="payroll-stat-card__label">Total deductions</span>
                            <span class="payroll-stat-card__value" id="prDed">—</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="payroll-stat-card payroll-stat-card--green py-3">
                            <span class="payroll-stat-card__label">In-hand (net)</span>
                            <span class="payroll-stat-card__value" id="prNet">—</span>
                        </div>
                    </div>
                </div>
                <div class="payroll-modal-panel">
                    <h6 class="payroll-modal-panel__title">Breakdown</h6>
                    <div class="row g-2 small">
                        <div class="col-6 col-md-4"><span class="text-muted">Basic</span><div class="fw-semibold" id="prBasic">—</div>
                        <div class="col-6 col-md-4"><span class="text-muted">PF</span><div class="fw-semibold" id="prPf">—</div>
                        <div class="col-6 col-md-4"><span class="text-muted">ESI</span><div class="fw-semibold" id="prEsi">—</div>
                        <div class="col-6 col-md-4"><span class="text-muted">OT amount</span><div class="fw-semibold" id="prOtAmt">—</div>
                        <div class="col-6 col-md-4"><span class="text-muted">OT hours</span><div class="fw-semibold" id="prOtH">—</div>
                        <div class="col-6 col-md-4"><span class="text-muted">Present days</span><div class="fw-semibold" id="prPresent">—</div>
                    </div>
                </div>
                <p class="small text-muted mt-3 mb-0">Dashboard summary cards and the employee table below are updated for this month.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <a class="btn btn-outline-danger d-none" id="prPayslipLink" href="#" target="_blank"><i class="bi bi-file-pdf me-1"></i>View payslip</a>
            </div>
        </div>
    </div>
</div>

<?php
$pdVer = is_file(__DIR__ . '/../../../assets/js/payroll-dashboard.js') ? (int)filemtime(__DIR__ . '/../../../assets/js/payroll-dashboard.js') : time();
$payrollNoticeJson = $payrollNotice ? json_encode($payrollNotice, JSON_THROW_ON_ERROR) : 'null';
$payslipBase = route_url('payroll/payslip');
?>
<script>
window.payrollDashboardConfig = {
    notice: <?= $payrollNoticeJson ?>,
    payslipBase: <?= json_encode($payslipBase, JSON_THROW_ON_ERROR) ?>,
    calcApiUrl: <?= json_encode($calcApiUrlAbs, JSON_THROW_ON_ERROR) ?>,
    testPreviewApiUrl: <?= json_encode($testPreviewApiUrlAbs, JSON_THROW_ON_ERROR) ?>,
    highlightEmployeeId: <?= (int)$highlightEmployeeId ?>
};
</script>
<?php if ($testToolsEnabled): ?>
<script>
(function () {
    'use strict';
    var PREVIEW_API = <?= json_encode($testPreviewApiUrlFull, JSON_THROW_ON_ERROR) ?>;
    var PREVIEW_MONTH = <?= json_encode($payrollMonth, JSON_THROW_ON_ERROR) ?>;

    function inr(n) {
        return '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
    }

    function previewUrl() {
        return PREVIEW_API;
    }

    function setPreviewState(state, message) {
        var loading = document.getElementById('ptPreviewLoading');
        var empty = document.getElementById('ptPreviewEmpty');
        var body = document.getElementById('ptPreviewBody');
        if (!loading || !empty || !body) {
            return;
        }
        loading.classList.toggle('d-none', state !== 'loading');
        empty.classList.toggle('d-none', state !== 'empty');
        body.classList.toggle('d-none', state !== 'ready');
        var p = empty.querySelector('p');
        if (p) {
            p.textContent = (state === 'empty' && message)
                ? message
                : 'Select an employee to see estimated gross, deductions, and net salary.';
        }
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) {
            el.textContent = val;
        }
    }

    var reqId = 0;

    function loadPayrollTestPreviewInline() {
        var sel = document.getElementById('payrollTestEmployeeId');
        var empId = sel ? String(sel.value || '').trim() : '';
        if (!empId) {
            setPreviewState('empty');
            return;
        }

        var present = (document.getElementById('ptPresent') || {}).value || '0';
        var half = (document.getElementById('ptHalf') || {}).value || '0';
        var absent = (document.getElementById('ptAbsent') || {}).value || '0';
        var ot = (document.getElementById('ptOt') || {}).value || '0';
        var late = (document.getElementById('ptLate') || {}).value || '0';

        var myId = ++reqId;
        setPreviewState('loading');

        var url = previewUrl()
            + (previewUrl().indexOf('?') >= 0 ? '&' : '?')
            + 'employee_id=' + encodeURIComponent(empId)
            + '&month_year=' + encodeURIComponent(PREVIEW_MONTH)
            + '&test_present=' + encodeURIComponent(present)
            + '&test_half_days=' + encodeURIComponent(half)
            + '&test_absent=' + encodeURIComponent(absent)
            + '&test_ot_hours=' + encodeURIComponent(ot)
            + '&test_late=' + encodeURIComponent(late);

        fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
            .then(function (r) {
                return r.text().then(function (text) {
                    var data = {};
                    try {
                        data = text ? JSON.parse(text) : {};
                    } catch (e) {
                        throw new Error('Invalid server response. Open preview URL in a new tab to debug.');
                    }
                    if (!r.ok) {
                        throw new Error(data.error || ('HTTP ' + r.status));
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (myId !== reqId) {
                    return;
                }
                if (!data.ok || !data.calc) {
                    setPreviewState('empty', data.error || 'Preview calculation failed.');
                    return;
                }
                var c = data.calc;
                var att = c.attendance || {};
                setText('ptGross', inr(c.gross_salary));
                setText('ptDed', inr(c.total_deduction));
                setText('ptNet', inr(c.net_salary));
                setText('ptMetaPresent', att.present_days != null ? String(att.present_days) : '—');
                setText('ptMetaOt', inr(c.overtime_amount));
                setText('ptMetaPf', inr(c.pf_amount));
                setPreviewState('ready');
            })
            .catch(function (err) {
                if (myId !== reqId) {
                    return;
                }
                setPreviewState('empty', (err && err.message) ? err.message : 'Could not load preview.');
            });
    }

    window.loadPayrollTestPreview = loadPayrollTestPreviewInline;

    function bindPreview() {
        var sel = document.getElementById('payrollTestEmployeeId');
        var modal = document.getElementById('payrollTestDataModal');
        var refresh = document.getElementById('ptRefreshPreviewBtn');
        var form = document.getElementById('payrollTestDataForm');
        if (!sel || !modal) {
            return;
        }
        sel.addEventListener('change', loadPayrollTestPreviewInline);
        if (form) {
            form.addEventListener('change', loadPayrollTestPreviewInline);
            form.addEventListener('input', function () {
                clearTimeout(bindPreview._t);
                bindPreview._t = setTimeout(loadPayrollTestPreviewInline, 400);
            });
        }
        if (refresh) {
            refresh.addEventListener('click', loadPayrollTestPreviewInline);
        }
        modal.addEventListener('shown.bs.modal', loadPayrollTestPreviewInline);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindPreview);
    } else {
        bindPreview();
    }
})();
</script>
<?php endif; ?>
<script src="assets/js/payroll-dashboard.js?v=<?= e((string)$pdVer) ?>"></script>
