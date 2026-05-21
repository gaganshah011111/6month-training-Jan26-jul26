<?php
declare(strict_types=1);
/** @var PDO $pdo */
$resetMarkUrl = 'index.php?' . http_build_query([
    'page' => 'attendance/list',
    'att_section' => 'mark',
    'search' => '1',
    'att_date' => $att_date,
]);
$attJsPath = __DIR__ . '/../../../assets/js/attendance-module.js';
$attJsVer = is_file($attJsPath) ? (int)filemtime($attJsPath) : time();
?>
<div class="hr-page att-page">
    <div class="att-page__top">
        <h1 class="att-page__title">
            Attendance Management
            <span><?= $att_section === 'mark' ? 'Mark & update daily attendance' : 'View attendance records' ?></span>
        </h1>
        <?php if ($att_section === 'mark'): ?>
        <div class="att-page__top-actions">
            <div>
                <label class="form-label">Attendance date</label>
                <input type="date" class="form-control form-control-sm" name="att_date" form="att-date-form" value="<?= e($att_date) ?>">
            </div>
            <button type="button" class="btn btn-ralson-primary btn-sm align-self-end" data-bs-toggle="modal" data-bs-target="#holidayModal">
                <i class="bi bi-calendar-event me-1"></i>Mark Holiday
            </button>
        </div>
        <form id="att-date-form" method="get" class="d-none">
            <input type="hidden" name="page" value="attendance/list">
            <input type="hidden" name="att_section" value="mark">
            <input type="hidden" name="search" value="1">
            <input type="hidden" name="q_emp" value="<?= e($q_emp) ?>">
            <input type="hidden" name="q_name" value="<?= e($q_name) ?>">
            <input type="hidden" name="dept" value="<?= e($dept) ?>">
            <input type="hidden" name="emp_type" value="<?= e($emp_type) ?>">
        </form>
        <?php endif; ?>
    </div>

    <div class="att-seg" role="tablist">
        <a class="att-seg__btn <?= $att_section === 'mark' ? 'is-active' : '' ?>" href="index.php?<?= e(http_build_query($markTabQuery)) ?>">Mark Attendance</a>
        <a class="att-seg__btn <?= $att_section === 'verify' ? 'is-active' : '' ?>" href="index.php?<?= e(http_build_query($verifyTabQuery)) ?>">Verification Queue<?php if (($verificationPendingCount ?? 0) > 0): ?> <span class="badge bg-warning text-dark"><?= (int)$verificationPendingCount ?></span><?php endif; ?></a>
        <a class="att-seg__btn <?= $att_section === 'register' ? 'is-active' : '' ?>" href="index.php?<?= e(http_build_query($registerTabQuery)) ?>">Attendance Register</a>
    </div>

    <?php if ($att_section === 'verify'): ?>
    <?php include __DIR__ . '/_verification.php'; ?>
    <?php elseif ($att_section === 'mark'): ?>

    <div class="att-kpis">
        <div class="att-kpi att-kpi--present"><span>Present</span><strong><?= (int)$daySummary['present'] ?></strong></div>
        <div class="att-kpi att-kpi--absent"><span>Absent</span><strong><?= (int)$daySummary['absent'] ?></strong></div>
        <div class="att-kpi att-kpi--late"><span>Late</span><strong><?= (int)$daySummary['late'] ?></strong></div>
        <div class="att-kpi att-kpi--half"><span>Half Day</span><strong><?= (int)$daySummary['half'] ?></strong></div>
        <div class="att-kpi att-kpi--holiday"><span>Holidays</span><strong><?= (int)$daySummary['holiday'] ?></strong></div>
    </div>

    <div class="att-bar">
        <form id="att-mgmt-search-form" method="get" class="att-filters">
            <input type="hidden" name="page" value="attendance/list">
            <input type="hidden" name="att_section" value="mark">
            <input type="hidden" name="search" value="1">
            <input type="hidden" name="att_date" value="<?= e($att_date) ?>">
            <div class="att-filters__field">
                <label class="form-label">Employee ID</label>
                <input type="text" class="form-control" name="q_emp" value="<?= e($q_emp) ?>" placeholder="Code">
            </div>
            <div class="att-filters__field att-filters__field--wide">
                <label class="form-label">Employee Name</label>
                <input type="text" class="form-control" name="q_name" value="<?= e($q_name) ?>" placeholder="Name">
            </div>
            <div class="att-filters__field">
                <label class="form-label">Department</label>
                <select class="form-select" name="dept">
                    <option value="">All</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= e((string)$d) ?>" <?= $dept === (string)$d ? 'selected' : '' ?>><?= e((string)$d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="att-filters__field">
                <label class="form-label">Type</label>
                <select class="form-select" name="emp_type">
                    <option value="">All</option>
                    <option value="Worker" <?= $emp_type === 'Worker' ? 'selected' : '' ?>>Worker</option>
                    <option value="Staff" <?= $emp_type === 'Staff' ? 'selected' : '' ?>>Staff</option>
                </select>
            </div>
            <button type="submit" class="btn btn-ralson-primary btn-sm">Search</button>
            <a href="<?= e($resetMarkUrl) ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
        </form>
        <div class="att-actions">
            <form method="post" class="d-inline" onsubmit="return confirm('Mark all filtered employees as Present?');">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="mark_all_present">
                <input type="hidden" name="attendance_date" value="<?= e($att_date) ?>">
                <input type="hidden" name="ret_att_section" value="mark">
                <input type="hidden" name="ret_att_date" value="<?= e($att_date) ?>">
                <input type="hidden" name="ret_q_emp" value="<?= e($q_emp) ?>">
                <input type="hidden" name="ret_q_name" value="<?= e($q_name) ?>">
                <input type="hidden" name="ret_dept" value="<?= e($dept) ?>">
                <input type="hidden" name="ret_emp_type" value="<?= e($emp_type) ?>">
                <button type="submit" class="btn btn-outline-secondary btn-sm" title="Mark all present"><i class="bi bi-check2-all"></i> Mark All Present</button>
            </form>
            <button type="submit" form="att-batch-form" class="btn btn-ralson-primary btn-sm"><i class="bi bi-save"></i> Save Attendance</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkModal"><i class="bi bi-people"></i> Bulk Entry</button>
            <a href="<?= e($exportMarkUrl) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Export</a>
        </div>
    </div>

    <div class="att-layout">
        <div class="att-panel">
            <div class="att-panel__head">
                <span>Employees · <?= e(date('d M Y', strtotime($att_date))) ?></span>
                <span class="text-muted"><?= count($tableRows) ?> rows</span>
            </div>
            <form method="post" id="att-batch-form">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="save_batch">
                <input type="hidden" name="attendance_date" value="<?= e($att_date) ?>">
                <input type="hidden" name="ret_att_section" value="mark">
                <input type="hidden" name="ret_att_date" value="<?= e($att_date) ?>">
                <input type="hidden" name="ret_q_emp" value="<?= e($q_emp) ?>">
                <input type="hidden" name="ret_q_name" value="<?= e($q_name) ?>">
                <input type="hidden" name="ret_dept" value="<?= e($dept) ?>">
                <input type="hidden" name="ret_emp_type" value="<?= e($emp_type) ?>">
                <div class="att-table-scroll">
                    <table class="table table-sm att-table mb-0">
                        <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Shift</th>
                            <th>Punch In</th>
                            <th>Punch Out</th>
                            <th class="text-end">OT Hrs</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$tableRows): ?>
                            <tr><td colspan="10"><div class="att-empty-card">No employees found for selected filters.</div></td></tr>
                        <?php else: ?>
                        <?php foreach ($tableRows as $idx => $r):
                            $eid = (int)$r['id'];
                            $shLabel = employee_shift_enum($r);
                            [$sc, $ec] = employee_shift_clock_bounds($r);
                            $shDisp = $shLabel . ' ' . substr($sc, 0, 5) . '–' . substr($ec, 0, 5);
                            $piVal = !empty($r['punch_in_time']) ? date('H:i', strtotime((string)$r['punch_in_time'])) : '';
                            $poVal = !empty($r['punch_out_time']) ? date('H:i', strtotime((string)$r['punch_out_time'])) : '';
                            $curStatus = (string)($r['att_status'] ?? '');
                            $leaveLocked = att_is_leave_locked($r);
                            $displayStatus = $curStatus;
                            if (in_array($curStatus, ['Paid Leave', 'Unpaid Leave', 'Half Paid Leave', 'Leave'], true)) {
                                $displayStatus = 'Leave';
                            }
                            $statusSlugMap = ['Present' => 'present', 'Half Day' => 'half', 'Late' => 'late', 'Absent' => 'absent', 'Holiday' => 'holiday', 'Leave' => 'leave'];
                            $stClass = 'att-st-' . ($statusSlugMap[$displayStatus] ?? 'default');
                            ?>
                            <tr class="att-row" data-row-index="<?= (int)$idx ?>">
                                <td class="font-monospace small"><?= e((string)($r['employee_code'] ?? '')) ?></td>
                                <td><?= e((string)$r['full_name']) ?></td>
                                <td class="small"><?= e((string)($r['department'] ?? '')) ?></td>
                                <td class="small text-muted"><?= e($shDisp) ?></td>
                                <?php if ($leaveLocked): ?>
                                    <td colspan="2" class="small text-muted">—</td>
                                    <td class="text-end small"><?= e((string)($r['overtime_hours'] ?? '0')) ?></td>
                                    <td><?= att_status_badge($curStatus) ?>
                                        <input type="hidden" name="rows[<?= $eid ?>][leave_locked]" value="1">
                                    </td>
                                    <td></td>
                                <?php else: ?>
                                <td>
                                    <input type="time" class="form-control att-in" name="rows[<?= $eid ?>][punch_in]" value="<?= e($piVal) ?>" step="60">
                                </td>
                                <td>
                                    <input type="time" class="form-control att-out" name="rows[<?= $eid ?>][punch_out]" value="<?= e($poVal) ?>" step="60">
                                </td>
                                <td class="text-end"><span class="att-calc-ot">—</span></td>
                                <td>
                                    <select class="form-select att-status-select att-status <?= e($stClass) ?>" name="rows[<?= $eid ?>][status]" data-status-class>
                                        <option value="" <?= $displayStatus === '' ? 'selected' : '' ?>>—</option>
                                        <?php foreach ($markStatusOpts as $st): ?>
                                            <option value="<?= e($st) ?>" <?= $displayStatus === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-link btn-sm p-0 att-row-save" data-employee-id="<?= $eid ?>" title="Save row">Save</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>

        <aside class="att-panel att-recent">
            <div class="att-panel__head">Recent · <?= e(date('d M', strtotime($att_date))) ?></div>
            <?php if (!$recentAtt): ?>
                <div class="p-2 small text-muted">No records saved yet today.</div>
            <?php else: ?>
                <ul class="att-recent__list">
                    <?php foreach ($recentAtt as $rec): ?>
                        <li class="att-recent__item">
                            <strong><?= e((string)$rec['full_name']) ?></strong>
                            <small class="d-block"><?= e((string)($rec['employee_code'] ?? '')) ?> · <?= att_status_badge((string)$rec['status']) ?></small>
                            <?php if (!empty($rec['punch_in_time'])): ?>
                                <small><?= date('H:i', strtotime((string)$rec['punch_in_time'])) ?> – <?= !empty($rec['punch_out_time']) ? date('H:i', strtotime((string)$rec['punch_out_time'])) : '—' ?></small>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    </div>

    <?php else: ?>

    <div class="att-bar">
        <form id="att-register-search-form" method="get" class="att-filters att-reg-layout">
            <input type="hidden" name="page" value="attendance/list">
            <input type="hidden" name="att_section" value="register">
            <input type="hidden" name="reg_search" value="1">
            <div class="att-filters__field">
                <label class="form-label">View</label>
                <select class="form-select" name="reg_mode" id="reg_mode_select">
                    <option value="daily" <?= $reg_mode === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="monthly" <?= $reg_mode === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                </select>
            </div>
            <div class="att-filters__field reg-field-daily">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="reg_date" value="<?= e($reg_date) ?>">
            </div>
            <div class="att-filters__field reg-field-monthly" hidden>
                <label class="form-label">Month</label>
                <input type="month" class="form-control" name="reg_month" value="<?= e($reg_month) ?>">
            </div>
            <div class="att-filters__field">
                <label class="form-label">Employee ID</label>
                <input type="text" class="form-control" name="reg_q_emp" value="<?= e($reg_q_emp) ?>" placeholder="Code">
            </div>
            <div class="att-filters__field">
                <label class="form-label">Department</label>
                <select class="form-select" name="reg_dept">
                    <option value="">All</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= e((string)$d) ?>" <?= $reg_dept === (string)$d ? 'selected' : '' ?>><?= e((string)$d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="att-filters__field">
                <label class="form-label">Type</label>
                <select class="form-select" name="reg_emp_type">
                    <option value="">All</option>
                    <option value="Worker" <?= $reg_emp_type === 'Worker' ? 'selected' : '' ?>>Worker</option>
                    <option value="Staff" <?= $reg_emp_type === 'Staff' ? 'selected' : '' ?>>Staff</option>
                </select>
            </div>
            <button type="submit" class="btn btn-ralson-primary btn-sm">Search</button>
            <a href="<?= e($exportRegUrl) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Export</a>
        </form>
    </div>

    <div class="att-panel">
        <?php if ($reg_mode === 'daily'): ?>
            <div class="att-panel__head">
                <span>Daily register · <?= e(date('d M Y', strtotime($reg_date))) ?></span>
                <span><?= (int)$reg_total ?> records</span>
            </div>
            <div class="att-table-scroll">
                <table class="table table-sm att-table mb-0">
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Punch In</th>
                        <th>Punch Out</th>
                        <th class="text-end">OT</th>
                        <th>Status</th>
                        <th>Late</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$registerDailyRows): ?>
                        <tr><td colspan="7"><div class="att-empty-card">No attendance records for this date.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($registerDailyRows as $r):
                            $piVal = !empty($r['punch_in_time']) ? date('H:i', strtotime((string)$r['punch_in_time'])) : '—';
                            $poVal = !empty($r['punch_out_time']) ? date('H:i', strtotime((string)$r['punch_out_time'])) : '—';
                            $st = (string)($r['att_status'] ?? '');
                            $late = (int)($r['att_is_late'] ?? 0) === 1 || $st === 'Late';
                            ?>
                            <tr>
                                <td>
                                    <strong class="small"><?= e((string)$r['full_name']) ?></strong>
                                    <span class="d-block font-monospace text-muted" style="font-size:0.68rem"><?= e((string)($r['employee_code'] ?? '')) ?></span>
                                </td>
                                <td class="small"><?= e(date('d-m-Y', strtotime($reg_date))) ?></td>
                                <td><?= e($piVal) ?></td>
                                <td><?= e($poVal) ?></td>
                                <td class="text-end"><?= e((string)($r['overtime_hours'] ?? '—')) ?></td>
                                <td><?= att_status_badge($st) ?></td>
                                <td><?= $late ? '<span class="att-badge att-badge--late">Yes</span>' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($reg_pages > 1): ?>
            <div class="att-pagination">
                <span>Page <?= (int)$reg_page ?> of <?= (int)$reg_pages ?></span>
                <div class="btn-group btn-group-sm">
                    <?php
                    $prevQ = $registerTabQuery;
                    $prevQ['reg_page'] = max(1, $reg_page - 1);
                    $nextQ = $registerTabQuery;
                    $nextQ['reg_page'] = min($reg_pages, $reg_page + 1);
                    ?>
                    <a class="btn btn-outline-secondary <?= $reg_page <= 1 ? 'disabled' : '' ?>" href="index.php?<?= e(http_build_query($prevQ)) ?>">Prev</a>
                    <a class="btn btn-outline-secondary <?= $reg_page >= $reg_pages ? 'disabled' : '' ?>" href="index.php?<?= e(http_build_query($nextQ)) ?>">Next</a>
                </div>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="att-panel__head"><span>Monthly · <?= e($reg_month) ?></span></div>
            <div class="att-table-scroll">
                <?php if (!$registerMonthEmployees): ?>
                    <div class="att-empty-card m-3">No employees match filters.</div>
                <?php else: ?>
                <table class="table table-sm att-table att-reg-month mb-0">
                    <thead>
                    <tr>
                        <th class="att-reg-sticky">ID</th>
                        <th class="att-reg-sticky-name">Name</th>
                        <th class="att-reg-sticky-dept">Dept</th>
                        <?php foreach ($registerMonthDays as $dom): ?>
                            <th class="text-center att-reg-day"><?= (int)$dom ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($registerMonthEmployees as $me):
                        $eid = (int)$me['id'];
                        ?>
                        <tr>
                            <td class="att-reg-sticky small font-monospace"><?= e((string)($me['employee_code'] ?? '')) ?></td>
                            <td class="att-reg-sticky-name small"><?= e((string)($me['full_name'] ?? '')) ?></td>
                            <td class="att-reg-sticky-dept small"><?= e((string)($me['department'] ?? '')) ?></td>
                            <?php foreach ($registerMonthDays as $dom):
                                $ds = $reg_month . '-' . str_pad((string)$dom, 2, '0', STR_PAD_LEFT);
                                $stCell = ($registerMonthMap[$eid] ?? [])[$ds] ?? null;
                                $abbr = att_register_status_abbrev($stCell);
                                ?>
                                <td class="text-center att-reg-cell" title="<?= e((string)($stCell ?? 'No record')) ?>"><?= e($abbr) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<?php require __DIR__ . '/_modals.php'; ?>

<script type="application/json" id="attEmpData"><?= $empJs ?: '[]' ?></script>
<script type="application/json" id="attMarkDate"><?= json_encode($att_date, JSON_THROW_ON_ERROR) ?></script>
<script type="application/json" id="attPolicyData"><?= json_encode($attPolicy ?? attendance_policy_defaults(), JSON_THROW_ON_ERROR) ?></script>
<script src="assets/js/attendance-module.js?v=<?= e((string)$attJsVer) ?>"></script>
