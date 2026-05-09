<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/attendance_workflow.php';
if (!has_role(['Super Admin', 'HR Manager'])) {
    echo 'Access denied';
    return;
}
$pdo = Database::connection();
verify_csrf();

$hrStatuses = ['Present', 'Half Day', 'Late', 'Absent'];
$holidayTypes = ['National Holiday', 'Festival Holiday', 'Company Holiday', 'Emergency Shutdown'];

/** Short label for register / month grid cells */
function att_register_status_abbrev(?string $status): string
{
    if ($status === null || $status === '') {
        return '—';
    }
    static $map = [
        'Present' => 'P',
        'Half Day' => 'HD',
        'Late' => 'L',
        'Absent' => 'A',
        'Holiday' => 'H',
        'Paid Leave' => 'PL',
        'Unpaid Leave' => 'UL',
        'Leave' => 'LV',
        'Emergency Duty' => 'ED',
    ];
    return $map[$status] ?? (strlen($status) <= 3 ? $status : substr($status, 0, 3));
}

function att_redirect_preserving(array $p): void
{
    $p['page'] = 'attendance/list';
    header('Location: index.php?' . http_build_query($p));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ret = [
        'search' => '1',
        'att_section' => (string)($_POST['ret_att_section'] ?? 'mark'),
        'att_date' => (string)($_POST['ret_att_date'] ?? date('Y-m-d')),
        'q_emp' => (string)($_POST['ret_q_emp'] ?? ''),
        'dept' => (string)($_POST['ret_dept'] ?? ''),
        'emp_type' => (string)($_POST['ret_emp_type'] ?? ''),
    ];
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'mark_attendance') {
            $employeeId = post_int('employee_id');
            $attendanceDate = trim((string)($_POST['attendance_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDate)) {
                throw new RuntimeException('Invalid date.');
            }
            $empStmt = $pdo->prepare('SELECT * FROM employees WHERE id = :id AND status = :st LIMIT 1');
            $empStmt->execute(['id' => $employeeId, 'st' => 'active']);
            $emp = $empStmt->fetch();
            if (!$emp) {
                throw new RuntimeException('Employee not found.');
            }
            $etype = (string)($emp['employee_type'] ?? 'Staff');
            $allowed = $hrStatuses;
            $allowed[] = 'Holiday';
            $allowed = array_values(array_unique($allowed));
            $status = trim((string)($_POST['status'] ?? ''));
            if ($status === '') {
                throw new RuntimeException('Select a status.');
            }
            if (!in_array($status, $allowed, true)) {
                throw new RuntimeException('Invalid status for this employee type.');
            }
            $pi = trim((string)($_POST['punch_in'] ?? ''));
            $po = trim((string)($_POST['punch_out'] ?? ''));
            $shiftEnum = employee_shift_enum($emp);

            $pit = null;
            $pot = null;
            $totalH = null;
            $otH = 0.0;
            $isLate = 0;
            $isEarly = 0;

            if ($status === 'Holiday') {
                if ($pi !== '' || $po !== '') {
                    throw new RuntimeException('Clear punch times for Holiday status.');
                }
                $totalH = employee_scheduled_shift_hours($emp);
            } elseif ($pi === '' && $po === '') {
                if ($status !== 'Absent') {
                    throw new RuntimeException('Enter punch in/out, or set status to Absent.');
                }
            } else {
                if ($pi === '' || $po === '') {
                    throw new RuntimeException('Enter both punch in and punch out.');
                }
                [$pit, $pot] = hr_build_punch_datetimes($attendanceDate, $pi, $po);
                $metrics = hr_compute_attendance_metrics($emp, $attendanceDate, $pit, $pot);
                $totalH = $metrics['total_hours'];
                $otH = (float)$metrics['overtime_hours'];
                $isEarly = (int)$metrics['is_early_exit'];
                $isLate = $status === 'Late' ? 1 : 0;
                if ($status === 'Absent') {
                    throw new RuntimeException('Remove punch times when marking Absent.');
                }
            }

            $stmt = $pdo->prepare('INSERT INTO attendance (employee_id, attendance_date, shift, status, remarks, punch_in_time, punch_out_time, total_hours, overtime_hours, is_late, is_early_exit, is_emergency_duty)
                VALUES (:e,:d,:sh,:st,:rm,:pi,:po,:th,:oh,:il,:ie,0)
                ON DUPLICATE KEY UPDATE shift=VALUES(shift), status=VALUES(status), punch_in_time=VALUES(punch_in_time), punch_out_time=VALUES(punch_out_time), total_hours=VALUES(total_hours), overtime_hours=VALUES(overtime_hours), is_late=VALUES(is_late), is_early_exit=VALUES(is_early_exit), is_emergency_duty=0');
            $stmt->execute([
                'e' => $employeeId,
                'd' => $attendanceDate,
                'sh' => $shiftEnum,
                'st' => $status,
                'rm' => null,
                'pi' => $pit,
                'po' => $pot,
                'th' => $totalH,
                'oh' => $otH,
                'il' => $isLate,
                'ie' => $isEarly,
            ]);
            set_flash('success', 'Attendance saved for ' . (string)($emp['full_name'] ?? '') . '.');
            att_redirect_preserving($ret);
        }

        if ($action === 'save_holiday') {
            $hDate = trim((string)($_POST['holiday_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hDate)) {
                throw new RuntimeException('Invalid holiday date.');
            }
            $hName = post_string('holiday_name', 160);
            if ($hName === '') {
                throw new RuntimeException('Holiday name is required.');
            }
            $hType = post_string('holiday_type', 50);
            if (!in_array($hType, $holidayTypes, true)) {
                throw new RuntimeException('Invalid holiday type.');
            }
            $deptScope = trim((string)($_POST['holiday_department'] ?? ''));
            $hRemarks = post_string('holiday_remarks');
            $uid = (int)(($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0));

            $dup = $pdo->prepare('SELECT id FROM hr_holidays WHERE holiday_date = :d AND department_scope = :ds LIMIT 1');
            $dup->execute(['d' => $hDate, 'ds' => $deptScope]);
            if ($dup->fetch()) {
                throw new RuntimeException('A holiday is already defined for this date and department scope.');
            }

            $pdo->prepare('INSERT INTO hr_holidays (holiday_date, holiday_name, holiday_type, department_scope, remarks, created_by) VALUES (:d,:n,:t,:ds,:r,:u)')
                ->execute(['d' => $hDate, 'n' => $hName, 't' => $hType, 'ds' => $deptScope, 'r' => $hRemarks ?: null, 'u' => $uid > 0 ? $uid : null]);

            $empSql = "SELECT * FROM employees WHERE status = 'active'";
            $empParams = [];
            if ($deptScope !== '') {
                $empSql .= ' AND department = :dep';
                $empParams['dep'] = $deptScope;
            }
            $empSql .= ' ORDER BY id';
            $empStmt = $pdo->prepare($empSql);
            $empStmt->execute($empParams);
            $emps = $empStmt->fetchAll();

            $marked = 0;
            $skipped = 0;
            $insAtt = $pdo->prepare('INSERT INTO attendance (employee_id, attendance_date, shift, status, remarks, punch_in_time, punch_out_time, total_hours, overtime_hours, is_late, is_early_exit, is_emergency_duty)
                VALUES (:e,:d,:sh,:st,:rm,NULL,NULL,:th,0,0,0,0)');
            $updAtt = $pdo->prepare('UPDATE attendance SET shift=:sh, status=:st, remarks=:rm, punch_in_time=NULL, punch_out_time=NULL, total_hours=:th, overtime_hours=0, is_late=0, is_early_exit=0, is_emergency_duty=0 WHERE id=:id');

            foreach ($emps as $em) {
                $ex = $pdo->prepare('SELECT id, punch_in_time FROM attendance WHERE employee_id = :e AND attendance_date = :d LIMIT 1');
                $ex->execute(['e' => (int)$em['id'], 'd' => $hDate]);
                $row = $ex->fetch();
                if ($row && !empty($row['punch_in_time'])) {
                    $skipped++;
                    continue;
                }
                $schedH = employee_scheduled_shift_hours($em);
                $sh = employee_shift_enum($em);
                $rm = 'Holiday: ' . $hName . ($hType ? ' (' . $hType . ')' : '');
                try {
                    if ($row) {
                        $updAtt->execute([
                            'sh' => $sh,
                            'st' => 'Holiday',
                            'rm' => $rm,
                            'th' => $schedH,
                            'id' => (int)$row['id'],
                        ]);
                    } else {
                        $insAtt->execute([
                            'e' => (int)$em['id'],
                            'd' => $hDate,
                            'sh' => $sh,
                            'st' => 'Holiday',
                            'rm' => $rm,
                            'th' => $schedH,
                        ]);
                    }
                    $marked++;
                } catch (Throwable $e) {
                    $skipped++;
                }
            }

            set_flash('success', 'Holiday saved. Attendance marked: ' . $marked . ' employees. Skipped (already punched in): ' . $skipped . '.');
            att_redirect_preserving($ret);
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
        att_redirect_preserving($ret);
    }
}

$att_section = (isset($_GET['att_section']) && $_GET['att_section'] === 'register') ? 'register' : 'mark';

$q_emp = trim((string)($_GET['q_emp'] ?? ''));
$dept = trim((string)($_GET['dept'] ?? ''));
$emp_type = trim((string)($_GET['emp_type'] ?? ''));
$att_date = trim((string)($_GET['att_date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $att_date)) {
    $att_date = date('Y-m-d');
}
$searched = isset($_GET['search']) && $_GET['search'] === '1';

$reg_mode = (isset($_GET['reg_mode']) && $_GET['reg_mode'] === 'monthly') ? 'monthly' : 'daily';
$reg_date = trim((string)($_GET['reg_date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reg_date)) {
    $reg_date = date('Y-m-d');
}
$reg_month = trim((string)($_GET['reg_month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $reg_month)) {
    $reg_month = date('Y-m');
}
$reg_dept = trim((string)($_GET['reg_dept'] ?? ''));
$reg_q_emp = trim((string)($_GET['reg_q_emp'] ?? ''));
$reg_emp_type = trim((string)($_GET['reg_emp_type'] ?? ''));
$register_searched = isset($_GET['reg_search']) && $_GET['reg_search'] === '1';

$departments = $pdo->query("SELECT DISTINCT TRIM(department) AS d FROM employees WHERE status = 'active' AND COALESCE(department,'') != '' ORDER BY d")->fetchAll(PDO::FETCH_COLUMN);

$tableRows = [];
if ($att_section === 'mark' && $searched) {
    $sql = "SELECT e.*, a.id AS att_pk, a.punch_in_time, a.punch_out_time, a.total_hours, a.overtime_hours,
        a.status AS att_status
        FROM employees e
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = :ad
        WHERE e.status = 'active' AND a.id IS NULL";
    $params = ['ad' => $att_date];
    if ($dept !== '') {
        $sql .= ' AND e.department = :dept';
        $params['dept'] = $dept;
    }
    if ($emp_type !== '' && in_array($emp_type, ['Worker', 'Staff'], true)) {
        $sql .= ' AND e.employee_type = :et';
        $params['et'] = $emp_type;
    }
    if ($q_emp !== '') {
        if (ctype_digit($q_emp)) {
            $sql .= ' AND (e.id = :qid OR e.employee_code LIKE :qcode)';
            $params['qid'] = (int)$q_emp;
            $params['qcode'] = '%' . $q_emp . '%';
        } else {
            $ql = '%' . $q_emp . '%';
            $sql .= ' AND (e.employee_code LIKE :ql OR e.full_name LIKE :qf OR e.department LIKE :qd)';
            $params['ql'] = $ql;
            $params['qf'] = $ql;
            $params['qd'] = $ql;
        }
    }
    $sql .= ' ORDER BY e.full_name ASC LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $tableRows = $st->fetchAll();
}

$registerDailyRows = [];
$registerMonthEmployees = [];
$registerMonthDays = [];
/** @var array<int, array<string, string>> $registerMonthMap */
$registerMonthMap = [];

if ($att_section === 'register' && $register_searched) {
    $appendEmpFilters = static function (string &$sql, array &$params, string $deptKey, string $etypeKey, string $qKey, string $dept, string $empType, string $qEmp): void {
        if ($dept !== '') {
            $sql .= ' AND e.department = :' . $deptKey;
            $params[$deptKey] = $dept;
        }
        if ($empType !== '' && in_array($empType, ['Worker', 'Staff'], true)) {
            $sql .= ' AND e.employee_type = :' . $etypeKey;
            $params[$etypeKey] = $empType;
        }
        if ($qEmp !== '') {
            if (ctype_digit($qEmp)) {
                $sql .= ' AND (e.id = :' . $qKey . 'id OR e.employee_code LIKE :' . $qKey . 'code)';
                $params[$qKey . 'id'] = (int)$qEmp;
                $params[$qKey . 'code'] = '%' . $qEmp . '%';
            } else {
                $ql = '%' . $qEmp . '%';
                $sql .= ' AND (e.employee_code LIKE :' . $qKey . 'l OR e.full_name LIKE :' . $qKey . 'f OR e.department LIKE :' . $qKey . 'd)';
                $params[$qKey . 'l'] = $ql;
                $params[$qKey . 'f'] = $ql;
                $params[$qKey . 'd'] = $ql;
            }
        }
    };

    if ($reg_mode === 'daily') {
        $sql = 'SELECT e.*, a.punch_in_time, a.punch_out_time, a.total_hours, a.overtime_hours, a.status AS att_status
            FROM employees e
            INNER JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = :rd
            WHERE e.status = \'active\'';
        $params = ['rd' => $reg_date];
        $appendEmpFilters($sql, $params, 'rdept', 'ret', 'rq', $reg_dept, $reg_emp_type, $reg_q_emp);
        $sql .= ' ORDER BY e.full_name ASC LIMIT 500';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $registerDailyRows = $st->fetchAll();
    } else {
        $monthStart = $reg_month . '-01';
        $lastDay = (int)date('t', strtotime($monthStart));
        $registerMonthDays = range(1, $lastDay);

        $sql = 'SELECT e.id, e.employee_code, e.full_name, e.department, e.employee_type FROM employees e WHERE e.status = \'active\'';
        $params = [];
        $appendEmpFilters($sql, $params, 'mdept', 'met', 'mq', $reg_dept, $reg_emp_type, $reg_q_emp);
        $sql .= ' ORDER BY e.full_name ASC LIMIT 400';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $registerMonthEmployees = $st->fetchAll();

        $ids = array_map(static fn ($row) => (int)$row['id'], $registerMonthEmployees);
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $monthEnd = $reg_month . '-' . str_pad((string)$lastDay, 2, '0', STR_PAD_LEFT);
            $attSql = "SELECT employee_id, attendance_date, status FROM attendance
                WHERE attendance_date >= ? AND attendance_date <= ? AND employee_id IN ($placeholders)";
            $attStmt = $pdo->prepare($attSql);
            $bind = array_merge([$monthStart, $monthEnd], $ids);
            $attStmt->execute($bind);
            while ($ar = $attStmt->fetch(PDO::FETCH_ASSOC)) {
                $eid = (int)$ar['employee_id'];
                $d = $ar['attendance_date'];
                if (is_string($d) && strlen($d) > 10) {
                    $d = substr($d, 0, 10);
                }
                $registerMonthMap[$eid][(string)$d] = (string)$ar['status'];
            }
        }
    }
}

$markTabQuery = [
    'page' => 'attendance/list',
    'att_section' => 'mark',
    'search' => $searched ? '1' : '0',
    'att_date' => $att_date,
    'q_emp' => $q_emp,
    'dept' => $dept,
    'emp_type' => $emp_type,
];
$registerTabQuery = [
    'page' => 'attendance/list',
    'att_section' => 'register',
    'reg_search' => $register_searched ? '1' : '0',
    'reg_mode' => $reg_mode,
    'reg_date' => $reg_date,
    'reg_month' => $reg_month,
    'reg_dept' => $reg_dept,
    'reg_q_emp' => $reg_q_emp,
    'reg_emp_type' => $reg_emp_type,
];

$empPayload = [];
$empJs = '[]';
if ($att_section === 'mark') {
    foreach ($tableRows as $er) {
        [$sc, $ec] = employee_shift_clock_bounds($er);
        $empPayload[] = [
            'id' => (int)$er['id'],
            'employee_code' => (string)($er['employee_code'] ?? ''),
            'full_name' => (string)($er['full_name'] ?? ''),
            'department' => (string)($er['department'] ?? ''),
            'employee_type' => (string)($er['employee_type'] ?? 'Staff'),
            'shift_timing' => (string)($er['shift_timing'] ?? ''),
            'shift_start' => $er['shift_start'] ?? null,
            'shift_end' => $er['shift_end'] ?? null,
            'shift_clock_start' => substr($sc, 0, 5),
            'shift_clock_end' => substr($ec, 0, 5),
            'scheduled_hours' => employee_scheduled_shift_hours($er),
        ];
    }
    $empJs = json_encode($empPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
?>
<div class="att-mgmt">
    <header class="att-mgmt__header mb-2">
        <h4 class="att-mgmt__title mb-1">Attendance Management</h4>
        <p class="text-muted small mb-0">Mark new attendance or review saved records by day or month.</p>
    </header>

    <ul class="nav nav-tabs att-mgmt__tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $att_section === 'mark' ? 'active' : '' ?>" href="index.php?<?= e(http_build_query($markTabQuery)) ?>">Mark attendance</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $att_section === 'register' ? 'active' : '' ?>" href="index.php?<?= e(http_build_query($registerTabQuery)) ?>">Attendance register</a>
        </li>
    </ul>

    <?php if ($att_section === 'mark'): ?>
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <p class="text-muted small mb-0 att-mgmt__mark-hint">Only employees <strong>without</strong> a saved row for the date appear here. Use <strong>Attendance register</strong> to view or verify marked attendance.</p>
        <div class="att-mgmt__sheet-date">
            <label class="form-label small text-muted mb-0">Attendance date</label>
            <input type="date" class="form-control form-control-sm" name="att_date" form="att-mgmt-search-form" value="<?= e($att_date) ?>" required>
        </div>
    </div>

    <form id="att-mgmt-search-form" method="get" class="att-mgmt__filters row g-2 align-items-end mb-3">
        <input type="hidden" name="page" value="attendance/list">
        <input type="hidden" name="att_section" value="mark">
        <input type="hidden" name="search" value="1">
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-0">Employee ID / Code</label>
            <input type="text" class="form-control form-control-sm" name="q_emp" value="<?= e($q_emp) ?>" placeholder="ID or code">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-0">Department</label>
            <select class="form-select form-select-sm" name="dept">
                <option value="">All</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= e((string)$d) ?>" <?= $dept === (string)$d ? 'selected' : '' ?>><?= e((string)$d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-0">Type</label>
            <select class="form-select form-select-sm" name="emp_type">
                <option value="">All</option>
                <option value="Worker" <?= $emp_type === 'Worker' ? 'selected' : '' ?>>Worker</option>
                <option value="Staff" <?= $emp_type === 'Staff' ? 'selected' : '' ?>>Staff</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <button type="submit" class="btn btn-ralson-primary btn-sm w-100">Search</button>
        </div>
        <div class="col-6 col-md-2">
            <button type="button" class="btn btn-ralson-outline btn-sm w-100" data-bs-toggle="modal" data-bs-target="#holidayModal">
                <i class="bi bi-calendar-event me-1"></i>Holiday
            </button>
        </div>
    </form>

    <?php if (!$searched): ?>
        <p class="text-muted small">Choose <strong>Attendance date</strong>, set filters, then click <strong>Search</strong>.</p>
    <?php elseif (!$tableRows): ?>
        <p class="text-muted small">No employees left to mark for this date (everyone matching your filters already has attendance saved), or no employees match your filters.</p>
    <?php else: ?>
        <div class="table-responsive att-mgmt__table-wrap">
            <table class="table table-sm table-bordered att-mgmt__table mb-0">
                <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Type</th>
                    <th>Shift</th>
                    <th>Punch In</th>
                    <th>Punch Out</th>
                    <th class="text-end">Total Hrs</th>
                    <th class="text-end">OT Hrs</th>
                    <th>Status</th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tableRows as $idx => $r):
                    $etype = (string)($r['employee_type'] ?? 'Staff');
                    $statusOpts = $hrStatuses;
                    $shLabel = employee_shift_enum($r);
                    [$sc, $ec] = employee_shift_clock_bounds($r);
                    $shDisp = $shLabel . ' ' . substr($sc, 0, 5) . '–' . substr($ec, 0, 5);
                    $piVal = !empty($r['punch_in_time']) ? date('H:i', strtotime((string)$r['punch_in_time'])) : '';
                    $poVal = !empty($r['punch_out_time']) ? date('H:i', strtotime((string)$r['punch_out_time'])) : '';
                    $curStatus = (string)($r['att_status'] ?? '');
                    if ($curStatus === '' || $curStatus === null) {
                        $curStatus = '';
                    }
                    if (in_array($curStatus, ['Leave', 'Paid Leave', 'Unpaid Leave'], true)) {
                        $curStatus = 'Absent';
                    }
                    if ($curStatus === 'Holiday' && !in_array('Holiday', $statusOpts, true)) {
                        $statusOpts = array_values(array_unique(array_merge(['Holiday'], $statusOpts)));
                    }
                    if ($curStatus !== '' && !in_array($curStatus, $statusOpts, true)) {
                        $curStatus = 'Absent';
                    }
                    $fid = 'att-form-' . (int)$r['id'];
                    ?>
                    <tr class="att-row" data-row-index="<?= (int)$idx ?>">
                        <td>
                            <form method="post" id="<?= e($fid) ?>" class="d-none" aria-hidden="true">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="mark_attendance">
                                <input type="hidden" name="employee_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="attendance_date" value="<?= e($att_date) ?>">
                                <input type="hidden" name="ret_att_section" value="mark">
                                <input type="hidden" name="ret_att_date" value="<?= e($att_date) ?>">
                                <input type="hidden" name="ret_q_emp" value="<?= e($q_emp) ?>">
                                <input type="hidden" name="ret_dept" value="<?= e($dept) ?>">
                                <input type="hidden" name="ret_emp_type" value="<?= e($emp_type) ?>">
                            </form>
                            <?= e((string)($r['employee_code'] ?? '')) ?>
                        </td>
                        <td><?= e((string)$r['full_name']) ?></td>
                        <td><?= e((string)($r['department'] ?? '')) ?></td>
                        <td><?= e($etype) ?></td>
                        <td class="small"><?= e($shDisp) ?></td>
                        <td><input type="time" class="form-control form-control-sm att-in" name="punch_in" form="<?= e($fid) ?>" value="<?= e($piVal) ?>" step="60" autocomplete="off"></td>
                        <td><input type="time" class="form-control form-control-sm att-out" name="punch_out" form="<?= e($fid) ?>" value="<?= e($poVal) ?>" step="60" autocomplete="off"></td>
                        <td class="text-end"><span class="att-calc-total text-nowrap">—</span></td>
                        <td class="text-end"><span class="att-calc-ot text-nowrap">—</span></td>
                        <td>
                            <select class="form-select form-select-sm att-status" name="status" form="<?= e($fid) ?>" required>
                                <option value="" <?= $curStatus === '' ? 'selected' : '' ?>>— Select —</option>
                                <?php foreach ($statusOpts as $st): ?>
                                    <option value="<?= e($st) ?>" <?= $curStatus === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="text-center"><button type="submit" class="btn btn-ralson-primary btn-sm" form="<?= e($fid) ?>">Mark</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php else: /* register */ ?>
    <form id="att-register-search-form" method="get" class="att-mgmt__filters row g-2 align-items-end mb-3">
        <input type="hidden" name="page" value="attendance/list">
        <input type="hidden" name="att_section" value="register">
        <input type="hidden" name="reg_search" value="1">
        <div class="col-12 col-md-3">
            <label class="form-label small text-muted mb-0">View</label>
            <select class="form-select form-select-sm" name="reg_mode" id="reg_mode_select">
                <option value="daily" <?= $reg_mode === 'daily' ? 'selected' : '' ?>>Daily (one date)</option>
                <option value="monthly" <?= $reg_mode === 'monthly' ? 'selected' : '' ?>>Monthly (whole month)</option>
            </select>
        </div>
        <div class="col-6 col-md-2 reg-field-daily">
            <label class="form-label small text-muted mb-0">Date</label>
            <input type="date" class="form-control form-control-sm" name="reg_date" value="<?= e($reg_date) ?>">
        </div>
        <div class="col-6 col-md-2 reg-field-monthly" hidden>
            <label class="form-label small text-muted mb-0">Month</label>
            <input type="month" class="form-control form-control-sm" name="reg_month" value="<?= e($reg_month) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-0">Department</label>
            <select class="form-select form-select-sm" name="reg_dept">
                <option value="">All</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= e((string)$d) ?>" <?= $reg_dept === (string)$d ? 'selected' : '' ?>><?= e((string)$d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-0">Employee ID / Code</label>
            <input type="text" class="form-control form-control-sm" name="reg_q_emp" value="<?= e($reg_q_emp) ?>" placeholder="Optional">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small text-muted mb-0">Type</label>
            <select class="form-select form-select-sm" name="reg_emp_type">
                <option value="">All</option>
                <option value="Worker" <?= $reg_emp_type === 'Worker' ? 'selected' : '' ?>>Worker</option>
                <option value="Staff" <?= $reg_emp_type === 'Staff' ? 'selected' : '' ?>>Staff</option>
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <label class="form-label small text-muted mb-0 d-block">&nbsp;</label>
            <button type="submit" class="btn btn-ralson-primary btn-sm">Search</button>
        </div>
    </form>

    <?php if (!$register_searched): ?>
        <p class="text-muted small">Choose <strong>Daily</strong> or <strong>Monthly</strong>, set filters, then click <strong>Search</strong>.</p>
    <?php elseif ($reg_mode === 'daily'): ?>
        <?php if (!$registerDailyRows): ?>
            <p class="text-muted small">No attendance records found for this date and filters.</p>
        <?php else: ?>
            <div class="table-responsive att-mgmt__table-wrap">
                <table class="table table-sm table-bordered att-mgmt__table att-mgmt__register-daily mb-0">
                    <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Type</th>
                        <th>Shift</th>
                        <th>Punch In</th>
                        <th>Punch Out</th>
                        <th class="text-end">Total Hrs</th>
                        <th class="text-end">OT Hrs</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($registerDailyRows as $r):
                        $shLabel = employee_shift_enum($r);
                        [$sc, $ec] = employee_shift_clock_bounds($r);
                        $shDisp = $shLabel . ' ' . substr($sc, 0, 5) . '–' . substr($ec, 0, 5);
                        $piVal = !empty($r['punch_in_time']) ? date('H:i', strtotime((string)$r['punch_in_time'])) : '—';
                        $poVal = !empty($r['punch_out_time']) ? date('H:i', strtotime((string)$r['punch_out_time'])) : '—';
                        $th = $r['total_hours'];
                        $thDisp = $th !== null && $th !== '' ? e((string)$th) : '—';
                        $ot = $r['overtime_hours'];
                        $otDisp = $ot !== null && $ot !== '' ? e((string)$ot) : '—';
                        ?>
                        <tr>
                            <td><?= e((string)($r['employee_code'] ?? '')) ?></td>
                            <td><?= e((string)$r['full_name']) ?></td>
                            <td><?= e((string)($r['department'] ?? '')) ?></td>
                            <td><?= e((string)($r['employee_type'] ?? '')) ?></td>
                            <td class="small"><?= e($shDisp) ?></td>
                            <td><?= e($piVal) ?></td>
                            <td><?= e($poVal) ?></td>
                            <td class="text-end"><?= $thDisp ?></td>
                            <td class="text-end"><?= $otDisp ?></td>
                            <td><?= e((string)($r['att_status'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else: /* monthly */ ?>
        <?php if (!$registerMonthEmployees): ?>
            <p class="text-muted small">No employees match your filters.</p>
        <?php else: ?>
            <p class="text-muted small mb-2 att-register-legend">
                <strong>Legend:</strong>
                P Present · HD Half day · L Late · A Absent · H Holiday · PL Paid leave · UL Unpaid · LV Leave · — No record
            </p>
            <div class="table-responsive att-mgmt__table-wrap att-register-month-wrap">
                <table class="table table-sm table-bordered att-mgmt__table att-register-month mb-0">
                    <thead>
                    <tr>
                        <th class="att-reg-sticky">Emp ID</th>
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
                            <td class="att-reg-sticky small"><?= e((string)($me['employee_code'] ?? '')) ?></td>
                            <td class="att-reg-sticky-name small"><?= e((string)($me['full_name'] ?? '')) ?></td>
                            <td class="att-reg-sticky-dept small"><?= e((string)($me['department'] ?? '')) ?></td>
                            <?php foreach ($registerMonthDays as $dom):
                                $ds = $reg_month . '-' . str_pad((string)$dom, 2, '0', STR_PAD_LEFT);
                                $st = ($registerMonthMap[$eid] ?? [])[$ds] ?? null;
                                $abbr = att_register_status_abbrev($st);
                                $title = $st !== null && $st !== '' ? $st : 'No record';
                                ?>
                                <td class="text-center small att-reg-cell" title="<?= e($title) ?>"><?= e($abbr) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="save_holiday">
            <input type="hidden" name="ret_att_section" value="mark">
            <input type="hidden" name="ret_att_date" value="<?= e($att_date) ?>">
            <input type="hidden" name="ret_q_emp" value="<?= e($q_emp) ?>">
            <input type="hidden" name="ret_dept" value="<?= e($dept) ?>">
            <input type="hidden" name="ret_emp_type" value="<?= e($emp_type) ?>">
            <div class="modal-header">
                <h5 class="modal-title">Add holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-2">
                <div class="col-12">
                    <label class="form-label small">Holiday date</label>
                    <input type="date" class="form-control form-control-sm" name="holiday_date" required value="<?= e($att_date) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label small">Holiday name</label>
                    <input type="text" class="form-control form-control-sm" name="holiday_name" required placeholder="e.g. Diwali">
                </div>
                <div class="col-12">
                    <label class="form-label small">Holiday type</label>
                    <select class="form-select form-select-sm" name="holiday_type" required>
                        <?php foreach ($holidayTypes as $ht): ?>
                            <option value="<?= e($ht) ?>"><?= e($ht) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small">Department <span class="text-muted">(optional)</span></label>
                    <select class="form-select form-select-sm" name="holiday_department">
                        <option value="">All employees</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= e((string)$d) ?>"><?= e((string)$d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small">Remarks</label>
                    <input type="text" class="form-control form-control-sm" name="holiday_remarks" placeholder="Optional">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-ralson-primary btn-sm">Save holiday</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var regMode = document.getElementById('reg_mode_select');
    var dailyFields = document.querySelectorAll('.reg-field-daily');
    var monthlyFields = document.querySelectorAll('.reg-field-monthly');
    function syncRegMode() {
        if (!regMode || !dailyFields.length || !monthlyFields.length) return;
        var m = regMode.value === 'monthly';
        dailyFields.forEach(function (el) { el.hidden = m; });
        monthlyFields.forEach(function (el) { el.hidden = !m; });
    }
    if (regMode) {
        syncRegMode();
        regMode.addEventListener('change', syncRegMode);
    }
})();
</script>
<?php if ($att_section === 'mark' && $searched && $tableRows): ?>
<script>
(function () {
    var EMP = <?= $empJs ?: '[]' ?>;
    var ATT_DATE = <?= json_encode($att_date, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function shiftWindowTs(dateYmd, emp) {
        var sc = emp.shift_clock_start ? emp.shift_clock_start + ':00' : '09:00:00';
        var ec = emp.shift_clock_end ? emp.shift_clock_end + ':00' : '18:00:00';
        var startTs = new Date(dateYmd + 'T' + sc).getTime();
        var endSame = new Date(dateYmd + 'T' + ec).getTime();
        var endTs = endSame;
        if (endSame <= startTs) {
            var d = new Date(dateYmd + 'T12:00:00');
            d.setDate(d.getDate() + 1);
            var nd = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            endTs = new Date(nd + 'T' + ec).getTime();
        }
        var schedH = Math.max(0.5, Math.round(((endTs - startTs) / 3600000) * 100) / 100);
        return [startTs, endTs, schedH];
    }
    function recalcRow(tr) {
        var idx = parseInt(tr.getAttribute('data-row-index'), 10);
        var emp = EMP[idx];
        if (!emp) return;
        var vin = tr.querySelector('.att-in');
        var vout = tr.querySelector('.att-out');
        var stSel = tr.querySelector('.att-status');
        var elT = tr.querySelector('.att-calc-total');
        var elO = tr.querySelector('.att-calc-ot');
        if (!vin || !vout || !elT) return;
        if (!vin.value || !vout.value) {
            elT.textContent = '—';
            elO.textContent = '—';
            return;
        }
        var inTs = new Date(ATT_DATE + 'T' + vin.value + ':00').getTime();
        var outSame = new Date(ATT_DATE + 'T' + vout.value + ':00').getTime();
        var outTs = outSame;
        if (outSame <= inTs) {
            var d2 = new Date(ATT_DATE + 'T12:00:00');
            d2.setDate(d2.getDate() + 1);
            var nd2 = d2.getFullYear() + '-' + pad(d2.getMonth() + 1) + '-' + pad(d2.getDate());
            outTs = new Date(nd2 + 'T' + vout.value + ':00').getTime();
        }
        if (!(outTs > inTs)) {
            elT.textContent = '—';
            return;
        }
        var worked = Math.round(((outTs - inTs) / 3600000) * 100) / 100;
        var st = shiftWindowTs(ATT_DATE, emp)[0];
        var et = shiftWindowTs(ATT_DATE, emp)[1];
        var schedH = shiftWindowTs(ATT_DATE, emp)[2];
        var late = inTs > st;
        var ot = Math.max(0, Math.round(((outTs - et) / 3600000) * 100) / 100);
        var minHalf = Math.min(4, Math.max(2, Math.round(schedH * 0.5 * 100) / 100));
        elT.textContent = String(worked);
        elO.textContent = String(ot);
        if (stSel && !stSel.dataset.manual) {
            var sv = stSel.value;
            if (sv === 'Holiday' || sv === 'Absent') {
                return;
            }
            if (worked < minHalf) stSel.value = 'Half Day';
            else if (late) stSel.value = 'Late';
            else stSel.value = 'Present';
        }
    }
    document.querySelectorAll('.att-row').forEach(function (tr) {
        tr.querySelectorAll('.att-in, .att-out').forEach(function (inp) {
            inp.addEventListener('input', function () { recalcRow(tr); });
            inp.addEventListener('change', function () { recalcRow(tr); });
        });
        var st = tr.querySelector('.att-status');
        if (st) {
            st.addEventListener('change', function () { st.dataset.manual = '1'; });
        }
        recalcRow(tr);
    });
})();
</script>
<?php endif; ?>
