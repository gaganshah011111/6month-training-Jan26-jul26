<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/attendance_workflow.php';
require_once __DIR__ . '/../../../includes/attendance_policy.php';
require_once __DIR__ . '/../../../includes/department_hierarchy.php';
if (!has_role(['Super Admin', 'HR Manager'])) {
    echo 'Access denied';
    return;
}
$pdo = Database::connection();
verify_csrf();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportDate = trim((string)($_GET['att_date'] ?? $_GET['reg_date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $exportDate)) {
        $exportDate = date('Y-m-d');
    }
    $rows = $pdo->prepare("SELECT e.employee_code, e.full_name, COALESCE(d.department_name, e.department) AS department,
        a.attendance_date, a.status, a.punch_in_time, a.punch_out_time, a.overtime_hours
        FROM attendance a
        INNER JOIN employees e ON e.id = a.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE a.attendance_date = :d ORDER BY e.full_name");
    $rows->execute(['d' => $exportDate]);
    $data = $rows->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance-' . $exportDate . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Employee ID', 'Name', 'Department', 'Date', 'Status', 'Punch In', 'Punch Out', 'OT Hours']);
    foreach ($data as $r) {
        fputcsv($out, [
            (string)($r['employee_code'] ?? ''),
            (string)($r['full_name'] ?? ''),
            (string)($r['department'] ?? ''),
            (string)($r['attendance_date'] ?? ''),
            (string)($r['status'] ?? ''),
            $r['punch_in_time'] ? date('H:i', strtotime((string)$r['punch_in_time'])) : '',
            $r['punch_out_time'] ? date('H:i', strtotime((string)$r['punch_out_time'])) : '',
            (string)($r['overtime_hours'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

$hrStatuses = ['Present', 'Half Day', 'Late', 'Absent'];
$markStatusOpts = ['Present', 'Half Day', 'Late', 'Absent', 'Holiday', 'Leave', ATTENDANCE_STATUS_PENDING_VERIFICATION];
$holidayTypes = ['National Holiday', 'Festival Holiday', 'Company Holiday', 'Emergency Shutdown'];

function att_status_badge(?string $status): string
{
    if ($status === null || $status === '') {
        return '<span class="att-badge att-badge--none">â€”</span>';
    }
    static $map = [
        'Present' => 'present', 'Half Day' => 'half', 'Late' => 'late', 'Absent' => 'absent',
        'Holiday' => 'holiday', 'Paid Leave' => 'leave', 'Unpaid Leave' => 'leave', 'Leave' => 'leave',
        'Emergency Duty' => 'duty',
    ];
    $slug = $map[$status] ?? 'default';
    return '<span class="att-badge att-badge--' . $slug . '">' . e($status) . '</span>';
}

function att_is_leave_locked(array $row): bool
{
    $cur = (string)($row['att_status'] ?? '');
    return in_array($cur, ['Paid Leave', 'Unpaid Leave', 'Half Paid Leave', 'Leave'], true)
        || (is_string($row['att_remarks'] ?? null) && str_starts_with((string)$row['att_remarks'], 'Leave #'));
}

/** @return array<int, array<string, mixed>> */
function att_fetch_active_employees(PDO $pdo, string $attDate, string $dept, string $empType, string $qEmp, string $qName = ''): array
{
    $sql = "SELECT e.*,
        COALESCE(NULLIF(d.department_name, ''), NULLIF(e.department, ''), 'â€”') AS department,
        a.id AS att_pk, a.punch_in_time, a.punch_out_time, a.total_hours, a.overtime_hours,
        a.status AS att_status, a.remarks AS att_remarks, a.is_late AS att_is_late
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = :ad
        WHERE e.status = 'active'";
    $params = ['ad' => $attDate];
    if ($dept !== '') {
        $sql .= ' AND COALESCE(d.department_name, e.department) = :dept';
        $params['dept'] = $dept;
    }
    if ($empType !== '' && in_array($empType, ['Worker', 'Staff'], true)) {
        $sql .= ' AND e.employee_type = :et';
        $params['et'] = $empType;
    }
    if ($qEmp !== '') {
        if (ctype_digit($qEmp)) {
            $sql .= ' AND (e.id = :qid OR e.employee_code LIKE :qcode)';
            $params['qid'] = (int)$qEmp;
            $params['qcode'] = '%' . $qEmp . '%';
        } else {
            $ql = '%' . $qEmp . '%';
            $sql .= ' AND (e.employee_code LIKE :ql OR e.employee_code LIKE :qcode2)';
            $params['ql'] = $ql;
            $params['qcode2'] = $ql;
        }
    }
    if ($qName !== '') {
        $sql .= ' AND e.full_name LIKE :qn';
        $params['qn'] = '%' . $qName . '%';
    }
    $sql .= ' ORDER BY e.full_name ASC LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{present:int,absent:int,late:int,half:int,holiday:int,leave:int,total_marked:int} */
function att_day_summary(PDO $pdo, string $attDate): array
{
    $st = $pdo->prepare("SELECT status, COUNT(*) AS c FROM attendance WHERE attendance_date = :d GROUP BY status");
    $st->execute(['d' => $attDate]);
    $out = ['present' => 0, 'absent' => 0, 'late' => 0, 'half' => 0, 'holiday' => 0, 'leave' => 0, 'total_marked' => 0];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $c = (int)$row['c'];
        $out['total_marked'] += $c;
        $s = (string)$row['status'];
        if (in_array($s, ['Present', 'Emergency Duty'], true)) {
            $out['present'] += $c;
        } elseif ($s === 'Absent') {
            $out['absent'] += $c;
        } elseif ($s === 'Late') {
            $out['late'] += $c;
        } elseif ($s === 'Half Day') {
            $out['half'] += $c;
        } elseif ($s === 'Holiday') {
            $out['holiday'] += $c;
        } elseif (str_contains($s, 'Leave') || $s === 'Leave') {
            $out['leave'] += $c;
        }
    }
    return $out;
}

/** @return list<array<string,mixed>> */
function att_recent_for_date(PDO $pdo, string $attDate, int $limit = 8): array
{
    $st = $pdo->prepare("SELECT e.employee_code, e.full_name, a.status, a.punch_in_time, a.punch_out_time, a.created_at
        FROM attendance a
        INNER JOIN employees e ON e.id = a.employee_id
        WHERE a.attendance_date = :d
        ORDER BY a.id DESC
        LIMIT :lim");
    $st->bindValue(':d', $attDate);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function att_normalize_mark_status(string $status): string
{
    if ($status === 'Leave') {
        return 'Paid Leave';
    }
    return $status;
}

function att_punch_is_empty(string $time): bool
{
    $time = trim($time);
    if ($time === '') {
        return true;
    }
    return in_array($time, ['00:00', '00:00:00', '0:00', '0:00:00'], true);
}

/** @return array{0: string, 1: string} */
function att_normalize_punch_pair(string $pi, string $po): array
{
    $pi = trim($pi);
    $po = trim($po);
    if (att_punch_is_empty($pi)) {
        $pi = '';
    }
    if (att_punch_is_empty($po)) {
        $po = '';
    }
    return [$pi, $po];
}

function attendance_persist_mark(PDO $pdo, array $emp, string $attendanceDate, string $status, string $pi, string $po): void
{
    [$pi, $po] = att_normalize_punch_pair($pi, $po);
    $shiftEnum = employee_shift_enum($emp);
    $pit = null;
    $pot = null;
    $totalH = null;
    $otH = 0.0;
    $isLate = 0;
    $isEarly = 0;

    if ($status === 'Absent') {
        $pi = '';
        $po = '';
        $pit = null;
        $pot = null;
        $totalH = 0.0;
        $otH = 0.0;
        $isLate = 0;
        $isEarly = 0;
    } elseif ($status === 'Holiday') {
        if ($pi !== '' || $po !== '') {
            throw new RuntimeException('Clear punch times for Holiday status.');
        }
        $totalH = employee_scheduled_shift_hours($emp);
    } elseif (in_array($status, ['Paid Leave', 'Unpaid Leave', 'Half Paid Leave'], true)) {
        $pi = '';
        $po = '';
        $pit = null;
        $pot = null;
        $totalH = null;
    } elseif ($pi === '' && $po === '') {
        throw new RuntimeException('Enter punch in and punch out, or set status to Absent.');
    } else {
        if ($pi === '' || $po === '') {
            throw new RuntimeException('Enter both punch in and punch out.');
        }
        [$pit, $pot] = hr_build_punch_datetimes($attendanceDate, $pi, $po);
        $metrics = hr_compute_attendance_metrics($emp, $attendanceDate, $pit, $pot);
        $totalH = $metrics['total_hours'];
        $otH = (float)$metrics['overtime_hours'];
        $isEarly = (int)$metrics['is_early_exit'];
        $isLate = $status === 'Late' ? 1 : ((int)($metrics['is_late'] ?? 0));
    }

    $needsVer = ($status === ATTENDANCE_STATUS_PENDING_VERIFICATION) ? 1 : 0;
    $stmt = $pdo->prepare('INSERT INTO attendance (employee_id, attendance_date, shift, status, remarks, punch_in_time, punch_out_time, total_hours, overtime_hours, is_late, is_early_exit, is_emergency_duty, needs_verification)
        VALUES (:e,:d,:sh,:st,:rm,:pi,:po,:th,:oh,:il,:ie,0,:nv)
        ON DUPLICATE KEY UPDATE shift=VALUES(shift), status=VALUES(status), punch_in_time=VALUES(punch_in_time), punch_out_time=VALUES(punch_out_time), total_hours=VALUES(total_hours), overtime_hours=VALUES(overtime_hours), is_late=VALUES(is_late), is_early_exit=VALUES(is_early_exit), is_emergency_duty=0, needs_verification=VALUES(needs_verification), remarks=VALUES(remarks)');
    $stmt->execute([
        'e' => (int)$emp['id'],
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
        'nv' => $needsVer,
    ]);
}

/** Short label for register / month grid cells */
function att_register_status_abbrev(?string $status): string
{
    if ($status === null || $status === '') {
        return 'â€”';
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
        'q_name' => (string)($_POST['ret_q_name'] ?? ''),
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
            $status = trim((string)($_POST['status'] ?? ''));
            if ($status === '') {
                throw new RuntimeException('Select a status.');
            }
            if (!in_array($status, $markStatusOpts, true)) {
                throw new RuntimeException('Invalid status.');
            }
            $pi = trim((string)($_POST['punch_in'] ?? ''));
            $po = trim((string)($_POST['punch_out'] ?? ''));
            attendance_persist_mark($pdo, $emp, $attendanceDate, att_normalize_mark_status($status), $pi, $po);
            set_flash('success', 'Attendance saved for ' . (string)($emp['full_name'] ?? '') . '.');
            att_redirect_preserving($ret);
        }

        if ($action === 'mark_all_present') {
            $attendanceDate = trim((string)($_POST['attendance_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDate)) {
                throw new RuntimeException('Invalid date.');
            }
            $fDept = trim((string)($_POST['ret_dept'] ?? ''));
            $fType = trim((string)($_POST['ret_emp_type'] ?? ''));
            $fQ = trim((string)($_POST['ret_q_emp'] ?? ''));
            $fN = trim((string)($_POST['ret_q_name'] ?? ''));
            $list = att_fetch_active_employees($pdo, $attendanceDate, $fDept, $fType, $fQ, $fN);
            $done = 0;
            $skip = 0;
            foreach ($list as $em) {
                if (att_is_leave_locked($em)) {
                    $skip++;
                    continue;
                }
                [$sc, $ec] = employee_shift_clock_bounds($em);
                $pi = substr($sc, 0, 5);
                $po = substr($ec, 0, 5);
                try {
                    attendance_persist_mark($pdo, $em, $attendanceDate, 'Present', $pi, $po);
                    $done++;
                } catch (Throwable) {
                    $skip++;
                }
            }
            set_flash('success', 'Marked present: ' . $done . ($skip ? ' Â· Skipped: ' . $skip : '') . '.');
            att_redirect_preserving($ret);
        }

        if ($action === 'bulk_mark') {
            $attendanceDate = trim((string)($_POST['attendance_date'] ?? ''));
            $bulkStatus = trim((string)($_POST['bulk_status'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDate)) {
                throw new RuntimeException('Invalid date.');
            }
            if ($bulkStatus === '') {
                throw new RuntimeException('Select a status for bulk mark.');
            }
            $fDept = trim((string)($_POST['ret_dept'] ?? ''));
            $fType = trim((string)($_POST['ret_emp_type'] ?? ''));
            $fQ = trim((string)($_POST['ret_q_emp'] ?? ''));
            $fN = trim((string)($_POST['ret_q_name'] ?? ''));
            $list = att_fetch_active_employees($pdo, $attendanceDate, $fDept, $fType, $fQ, $fN);
            $done = 0;
            $skip = 0;
            $bulkNorm = att_normalize_mark_status($bulkStatus);
            foreach ($list as $em) {
                if (att_is_leave_locked($em)) {
                    $skip++;
                    continue;
                }
                $pi = '';
                $po = '';
                if (in_array($bulkStatus, ['Present', 'Late', 'Half Day'], true)) {
                    [$sc, $ec] = employee_shift_clock_bounds($em);
                    $pi = substr($sc, 0, 5);
                    $po = substr($ec, 0, 5);
                }
                try {
                    attendance_persist_mark($pdo, $em, $attendanceDate, $bulkNorm, $pi, $po);
                    $done++;
                } catch (Throwable) {
                    $skip++;
                }
            }
            set_flash('success', 'Bulk mark (' . $bulkStatus . '): ' . $done . ($skip ? ' Â· Skipped: ' . $skip : '') . '.');
            att_redirect_preserving($ret);
        }

        if ($action === 'save_batch') {
            $attendanceDate = trim((string)($_POST['attendance_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDate)) {
                throw new RuntimeException('Invalid date.');
            }
            $rows = $_POST['rows'] ?? [];
            if (!is_array($rows)) {
                $rows = [];
            }
            $saved = 0;
            $skipped = 0;
            foreach ($rows as $eid => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $status = trim((string)($row['status'] ?? ''));
                if ($status === '') {
                    continue;
                }
                if (!in_array($status, $markStatusOpts, true)) {
                    $skipped++;
                    continue;
                }
                $empStmt = $pdo->prepare('SELECT e.*, COALESCE(d.department_name, e.department) AS department
                    FROM employees e LEFT JOIN departments d ON d.id = e.department_id
                    WHERE e.id = :id AND e.status = :st LIMIT 1');
                $empStmt->execute(['id' => (int)$eid, 'st' => 'active']);
                $emp = $empStmt->fetch();
                if (!$emp) {
                    $skipped++;
                    continue;
                }
                if (($row['leave_locked'] ?? '') === '1') {
                    $skipped++;
                    continue;
                }
                $pi = trim((string)($row['punch_in'] ?? ''));
                $po = trim((string)($row['punch_out'] ?? ''));
                try {
                    attendance_persist_mark($pdo, $emp, $attendanceDate, att_normalize_mark_status($status), $pi, $po);
                    $saved++;
                } catch (Throwable) {
                    $skipped++;
                }
            }
            set_flash('success', 'Saved attendance: ' . $saved . ($skipped ? ' Â· Skipped: ' . $skipped : '') . '.');
            att_redirect_preserving($ret);
        }

        if ($action === 'verify_attendance') {
            $attId = post_int('attendance_id');
            $newStatus = trim((string)($_POST['verify_status'] ?? ''));
            $notes = trim((string)($_POST['verify_notes'] ?? ''));
            $hrUid = (int)(($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0));
            attendance_hr_resolve_verification($pdo, $attId, $newStatus, $hrUid, $notes);
            set_flash('success', 'Attendance verified and updated.');
            $ret['att_section'] = 'verify';
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

            $empSql = "SELECT e.* FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                WHERE e.status = 'active'";
            $empParams = [];
            if ($deptScope !== '') {
                $empSql .= ' AND COALESCE(d.department_name, e.department) = :dep';
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

$attSectionRaw = (string)($_GET['att_section'] ?? $_POST['ret_att_section'] ?? 'mark');
$att_section = in_array($attSectionRaw, ['register', 'verify'], true) ? $attSectionRaw : 'mark';

$q_emp = trim((string)($_GET['q_emp'] ?? ''));
$q_name = trim((string)($_GET['q_name'] ?? ''));
$dept = trim((string)($_GET['dept'] ?? ''));
$emp_type = trim((string)($_GET['emp_type'] ?? ''));
$att_date = trim((string)($_GET['att_date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $att_date)) {
    $att_date = date('Y-m-d');
}
// Mark tab: load employee list by default (auto-refresh when filters/date change).
$searched = $att_section === 'mark'
    ? (!isset($_GET['search']) || $_GET['search'] === '1')
    : (isset($_GET['search']) && $_GET['search'] === '1');

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
$register_searched = $att_section === 'register'
    ? (!isset($_GET['reg_search']) || $_GET['reg_search'] === '1')
    : false;

$departments = hr_department_filter_options($pdo);

$tableRows = [];
$daySummary = att_day_summary($pdo, $att_date);
$recentAtt = att_recent_for_date($pdo, $att_date, 8);
if ($att_section === 'mark' && $searched) {
    $tableRows = att_fetch_active_employees($pdo, $att_date, $dept, $emp_type, $q_emp, $q_name);
}

$registerDailyRows = [];
$registerMonthEmployees = [];
$registerMonthDays = [];
/** @var array<int, array<string, string>> $registerMonthMap */
$registerMonthMap = [];
$reg_page = 1;
$reg_pages = 1;
$reg_total = 0;

if ($att_section === 'register' && $register_searched) {
    $appendEmpFilters = static function (string &$sql, array &$params, string $deptKey, string $etypeKey, string $qKey, string $dept, string $empType, string $qEmp): void {
        if ($dept !== '') {
            $sql .= ' AND COALESCE(d.department_name, e.department) = :' . $deptKey;
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
                $sql .= ' AND (e.employee_code LIKE :' . $qKey . 'l OR e.full_name LIKE :' . $qKey . 'f OR e.department LIKE :' . $qKey . 'd OR d.department_name LIKE :' . $qKey . 'dn)';
                $params[$qKey . 'l'] = $ql;
                $params[$qKey . 'f'] = $ql;
                $params[$qKey . 'd'] = $ql;
                $params[$qKey . 'dn'] = $ql;
            }
        }
    };

    $reg_page = max(1, (int)($_GET['reg_page'] ?? 1));
    $reg_per_page = 25;

    $reg_total = 0;
    $reg_pages = 1;

    if ($reg_mode === 'daily') {
        $countSql = 'SELECT COUNT(*) FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            INNER JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = :rd
            WHERE e.status = \'active\'';
        $countParams = ['rd' => $reg_date];
        $appendEmpFilters($countSql, $countParams, 'rdept', 'ret', 'rq', $reg_dept, $reg_emp_type, $reg_q_emp);
        $countSt = $pdo->prepare($countSql);
        $countSt->execute($countParams);
        $reg_total = (int)$countSt->fetchColumn();
        $reg_pages = max(1, (int)ceil($reg_total / $reg_per_page));
        if ($reg_page > $reg_pages) {
            $reg_page = $reg_pages;
        }
        $offset = ($reg_page - 1) * $reg_per_page;

        $sql = 'SELECT e.*, COALESCE(d.department_name, e.department) AS department,
            a.attendance_date, a.punch_in_time, a.punch_out_time, a.total_hours, a.overtime_hours,
            a.status AS att_status, a.is_late AS att_is_late
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            INNER JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = :rd
            WHERE e.status = \'active\'';
        $params = ['rd' => $reg_date];
        $appendEmpFilters($sql, $params, 'rdept', 'ret', 'rq', $reg_dept, $reg_emp_type, $reg_q_emp);
        $sql .= ' ORDER BY e.full_name ASC LIMIT ' . (int)$reg_per_page . ' OFFSET ' . (int)$offset;
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $registerDailyRows = $st->fetchAll();
    } else {
        $monthStart = $reg_month . '-01';
        $lastDay = (int)date('t', strtotime($monthStart));
        $registerMonthDays = range(1, $lastDay);

        $sql = 'SELECT e.id, e.employee_code, e.full_name, COALESCE(d.department_name, e.department) AS department, e.employee_type FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.status = \'active\'';
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

$verifyMonth = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['verify_month'] ?? '')) ? (string)$_GET['verify_month'] : date('Y-m');
$verificationPendingCount = attendance_count_pending_verification($pdo, $verifyMonth);
$verificationQueue = $att_section === 'verify' ? attendance_fetch_verification_queue($pdo, $verifyMonth, 100) : [];
$attPolicy = attendance_policy_fetch($pdo);

$markTabQuery = [
    'page' => 'attendance/list',
    'att_section' => 'mark',
    'search' => $searched ? '1' : '0',
    'att_date' => $att_date,
    'q_emp' => $q_emp,
    'q_name' => $q_name,
    'dept' => $dept,
    'emp_type' => $emp_type,
];
$verifyTabQuery = [
    'page' => 'attendance/list',
    'att_section' => 'verify',
    'verify_month' => $verifyMonth,
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
    'reg_page' => $reg_page ?? 1,
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
$exportMarkUrl = 'index.php?page=attendance/list&export=csv&att_date=' . rawurlencode($att_date);
$exportRegUrl = 'index.php?page=attendance/list&export=csv&att_date=' . rawurlencode($reg_mode === 'daily' ? $reg_date : $reg_month . '-01');
require __DIR__ . '/_view.php';
