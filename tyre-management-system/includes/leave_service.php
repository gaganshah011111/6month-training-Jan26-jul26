<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/attendance_workflow.php';

/** @return list<string> */
function leave_date_range(string $from, string $to): array
{
    $dates = [];
    $start = strtotime($from);
    $end = strtotime($to);
    if ($start === false || $end === false || $start > $end) {
        return [];
    }
    for ($t = $start; $t <= $end; $t = strtotime('+1 day', $t)) {
        $dates[] = date('Y-m-d', $t);
    }

    return $dates;
}

function leave_count_days(string $from, string $to): float
{
    return (float)count(leave_date_range($from, $to));
}

function leave_year_from_date(string $date): int
{
    $ts = strtotime($date);

    return $ts ? (int)date('Y', $ts) : (int)date('Y');
}

/** @return array{paid_total:float,half_total:float,paid_used:float,half_used:float,unpaid_used:float,paid_remaining:float,half_remaining:float,pending_count:int} */
function leave_get_balance(PDO $pdo, int $employeeId, ?int $year = null): array
{
    $year = $year ?? (int)date('Y');
    $empStmt = $pdo->prepare('SELECT paid_leave_limit, half_paid_leave_limit FROM employees WHERE id = :id LIMIT 1');
    $empStmt->execute(['id' => $employeeId]);
    $emp = $empStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $paidTotal = (float)($emp['paid_leave_limit'] ?? 12);
    $halfTotal = (float)($emp['half_paid_leave_limit'] ?? 6);

    $usageStmt = $pdo->prepare("SELECT
            COALESCE(SUM(paid_days), 0) AS paid_used,
            COALESCE(SUM(half_paid_days), 0) AS half_used,
            COALESCE(SUM(unpaid_days), 0) AS unpaid_used
        FROM leaves
        WHERE employee_id = :eid
          AND status IN ('Approved', 'Pending')
          AND YEAR(COALESCE(from_date, start_date)) = :yr");
    $usageStmt->execute(['eid' => $employeeId, 'yr' => $year]);
    $usage = $usageStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM leaves WHERE employee_id = :eid AND status IN ('Pending','Applied')");
    $pendingStmt->execute(['eid' => $employeeId]);
    $pendingCount = (int)$pendingStmt->fetchColumn();

    $paidUsed = (float)($usage['paid_used'] ?? 0);
    $halfUsed = (float)($usage['half_used'] ?? 0);
    $unpaidUsed = (float)($usage['unpaid_used'] ?? 0);

    return [
        'paid_total' => $paidTotal,
        'half_total' => $halfTotal,
        'paid_used' => $paidUsed,
        'half_used' => $halfUsed,
        'unpaid_used' => $unpaidUsed,
        'paid_remaining' => max(0, $paidTotal - $paidUsed),
        'half_remaining' => max(0, $halfTotal - $halfUsed),
        'pending_count' => $pendingCount,
    ];
}

/**
 * Allocate leave days across Paid → Half Paid → Unpaid using remaining balance.
 *
 * @return array{paid_days:float,half_paid_days:float,unpaid_days:float,total_days:float,primary_category:string,day_map:array<string,string>}
 */
function leave_allocate_days(PDO $pdo, array $employee, string $from, string $to): array
{
    $employeeId = (int)$employee['id'];
    $year = leave_year_from_date($from);
    $balance = leave_get_balance($pdo, $employeeId, $year);

    $paidLeft = $balance['paid_remaining'];
    $halfLeft = $balance['half_remaining'];
    $paidDays = 0.0;
    $halfDays = 0.0;
    $unpaidDays = 0.0;
    $dayMap = [];

    $employeeType = (string)($employee['employee_type'] ?? 'Staff');
    $forceUnpaid = $employeeType === 'Worker';

    foreach (leave_date_range($from, $to) as $ymd) {
        if ((int)date('N', strtotime($ymd)) === 7) {
            $dayMap[$ymd] = 'Weekly Off';
            continue;
        }
        if ($forceUnpaid) {
            $dayMap[$ymd] = 'Unpaid';
            $unpaidDays += 1.0;
            continue;
        }
        if (leave_is_holiday($pdo, $ymd, $employee)) {
            $dayMap[$ymd] = 'Holiday';
            continue;
        }
        if ($paidLeft > 0) {
            $dayMap[$ymd] = 'Paid';
            $paidDays += 1.0;
            $paidLeft -= 1.0;
            continue;
        }
        if ($halfLeft > 0) {
            $dayMap[$ymd] = 'Half Paid';
            $halfDays += 1.0;
            $halfLeft -= 1.0;
            continue;
        }
        $dayMap[$ymd] = 'Unpaid';
        $unpaidDays += 1.0;
    }

    $total = $paidDays + $halfDays + $unpaidDays;
    if ($unpaidDays > 0 && $paidDays === 0.0 && $halfDays === 0.0) {
        $primary = 'Unpaid';
    } elseif ($halfDays > 0 && $paidDays === 0.0) {
        $primary = 'Half Paid';
    } elseif ($halfDays > 0 && $paidDays > 0) {
        $primary = 'Paid';
    } elseif ($paidDays > 0) {
        $primary = 'Paid';
    } else {
        $primary = 'Unpaid';
    }

    return [
        'paid_days' => $paidDays,
        'half_paid_days' => $halfDays,
        'unpaid_days' => $unpaidDays,
        'total_days' => $total,
        'primary_category' => $primary,
        'day_map' => $dayMap,
    ];
}

function leave_is_holiday(PDO $pdo, string $ymd, array $employee): bool
{
    $dept = (string)($employee['department'] ?? '');
    $st = $pdo->prepare("SELECT COUNT(*) FROM hr_holidays WHERE holiday_date = :d AND (department_scope = '' OR department_scope = :dept)");
    $st->execute(['d' => $ymd, 'dept' => $dept]);
    if ((int)$st->fetchColumn() > 0) {
        return true;
    }
    $st2 = $pdo->prepare('SELECT COUNT(*) FROM company_holidays WHERE holiday_date = :d');
    $st2->execute(['d' => $ymd]);

    return (int)$st2->fetchColumn() > 0;
}

function leave_department_min_present(PDO $pdo, int $departmentId, int $totalActive): int
{
    if ($departmentId < 1 || $totalActive < 1) {
        return 1;
    }
    $st = $pdo->prepare('SELECT min_staff_required FROM departments WHERE id = :id LIMIT 1');
    $st->execute(['id' => $departmentId]);
    $min = (int)$st->fetchColumn();
    if ($min > 0) {
        return min($min, $totalActive);
    }
    $pct = leave_setting_float($pdo, 'leave_min_present_pct', 50.0);

    return max(1, (int)ceil($totalActive * $pct / 100));
}

function leave_setting_float(PDO $pdo, string $key, float $default): float
{
    $st = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1');
    $st->execute(['k' => $key]);
    $v = $st->fetchColumn();

    return $v !== false && $v !== '' ? (float)$v : $default;
}

/**
 * @return array{risk:string,label:string,total:int,min_required:int,worst_present:int,on_leave:int}
 */
function leave_assess_staffing(PDO $pdo, int $employeeId, string $from, string $to, int $excludeLeaveId = 0): array
{
    $empStmt = $pdo->prepare('SELECT e.id, e.department_id, e.status FROM employees e WHERE e.id = :id LIMIT 1');
    $empStmt->execute(['id' => $employeeId]);
    $emp = $empStmt->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        return ['risk' => 'Critical', 'label' => 'Unknown department', 'total' => 0, 'min_required' => 0, 'worst_present' => 0, 'on_leave' => 0];
    }
    $deptId = (int)($emp['department_id'] ?? 0);

    if ($deptId < 1) {
        return [
            'risk' => 'Warning',
            'label' => 'No department — requires HR approval',
            'total' => 0,
            'min_required' => 0,
            'worst_present' => 0,
            'on_leave' => 0,
        ];
    }

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = :d AND status = 'active'");
    $totalStmt->execute(['d' => $deptId]);
    $total = (int)$totalStmt->fetchColumn();
    $minRequired = leave_department_min_present($pdo, $deptId, $total);

    $worstPresent = $total;
    $worstOnLeave = 0;
    $applicantId = (int)($emp['id'] ?? 0);
    foreach (leave_date_range($from, $to) as $ymd) {
        $onLeave = leave_count_on_leave($pdo, $deptId, $ymd, $excludeLeaveId);
        if ($applicantId > 0 && !leave_employee_on_leave_day($pdo, $applicantId, $ymd, $excludeLeaveId)) {
            $onLeave++;
        }
        $present = max(0, $total - $onLeave);
        if ($present < $worstPresent) {
            $worstPresent = $present;
            $worstOnLeave = $onLeave;
        }
    }

    $risk = 'Safe';
    $label = 'Staffing normal';
    if ($total > 0 && $worstPresent < $minRequired) {
        $gap = $minRequired - $worstPresent;
        if ($gap >= 2 || $worstPresent <= (int)floor($minRequired * 0.7)) {
            $risk = 'Critical';
            $label = 'Critical staffing shortage if approved';
        } else {
            $risk = 'Warning';
            $label = 'Department below minimum workforce';
        }
    }

    return [
        'risk' => $risk,
        'label' => $label,
        'total' => $total,
        'min_required' => $minRequired,
        'worst_present' => $worstPresent,
        'on_leave' => $worstOnLeave,
    ];
}

function leave_count_on_leave(PDO $pdo, int $departmentId, string $ymd, int $excludeLeaveId = 0): int
{
    $sql = "SELECT COUNT(DISTINCT l.employee_id) FROM leaves l
        INNER JOIN employees e ON e.id = l.employee_id
        WHERE e.department_id = :dept
          AND e.status = 'active'
          AND l.status IN ('Approved', 'Pending')
          AND :d BETWEEN COALESCE(l.from_date, l.start_date) AND COALESCE(l.to_date, l.end_date)";
    if ($excludeLeaveId > 0) {
        $sql .= ' AND l.id <> :lid';
    }
    $st = $pdo->prepare($sql);
    $params = ['dept' => $departmentId, 'd' => $ymd];
    if ($excludeLeaveId > 0) {
        $params['lid'] = $excludeLeaveId;
    }
    $st->execute($params);

    return (int)$st->fetchColumn();
}

function leave_employee_on_leave_day(PDO $pdo, int $employeeId, string $ymd, int $excludeLeaveId = 0): bool
{
    $sql = "SELECT COUNT(*) FROM leaves WHERE employee_id = :eid AND status IN ('Approved','Pending')
        AND :d BETWEEN COALESCE(from_date, start_date) AND COALESCE(to_date, end_date)";
    if ($excludeLeaveId > 0) {
        $sql .= ' AND id <> :lid';
    }
    $st = $pdo->prepare($sql);
    $params = ['eid' => $employeeId, 'd' => $ymd];
    if ($excludeLeaveId > 0) {
        $params['lid'] = $excludeLeaveId;
    }
    $st->execute($params);

    return (int)$st->fetchColumn() > 0;
}

function leave_should_auto_approve(PDO $pdo, array $employee, array $staffing, bool $isEmergency): bool
{
    unset($isEmergency);
    if (leave_setting_float($pdo, 'leave_auto_approve_enabled', 0.0) < 1) {
        return false;
    }
    if ((int)($employee['department_id'] ?? 0) < 1) {
        return false;
    }

    return ($staffing['risk'] ?? '') === 'Safe';
}

function leave_is_paid_flag(string $category): int
{
    return $category === 'Paid' ? 1 : 0;
}

/**
 * @return array{ok:bool,message:string,leave_id?:int}
 */
function leave_apply(PDO $pdo, int $employeeId, string $from, string $to, string $reason, bool $isEmergency = false): array
{
    $reason = trim($reason);
    if ($from === '' || $to === '' || $reason === '') {
        return ['ok' => false, 'message' => 'From date, to date, and reason are required.'];
    }
    if ($from > $to) {
        return ['ok' => false, 'message' => 'From date must be on or before to date.'];
    }
    if (strlen($reason) > 255) {
        return ['ok' => false, 'message' => 'Reason is too long (max 255 characters).'];
    }

    $empStmt = $pdo->prepare('SELECT e.*, d.department_name AS dept_label FROM employees e LEFT JOIN departments d ON d.id = e.department_id WHERE e.id = :id LIMIT 1');
    $empStmt->execute(['id' => $employeeId]);
    $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        return ['ok' => false, 'message' => 'Employee not found.'];
    }

    if (leave_has_overlap($pdo, $employeeId, $from, $to)) {
        return ['ok' => false, 'message' => 'You already have a leave request overlapping these dates.'];
    }

    $alloc = leave_allocate_days($pdo, $employee, $from, $to);
    if ($alloc['total_days'] < 1 && $alloc['unpaid_days'] < 1) {
        return ['ok' => false, 'message' => 'No working leave days in this range (only holidays/weekends may apply).'];
    }

    $year = leave_year_from_date($from);
    $balance = leave_get_balance($pdo, $employeeId, $year);
    $staffing = leave_assess_staffing($pdo, $employeeId, $from, $to);
    $autoApprove = leave_should_auto_approve($pdo, $employee, $staffing, $isEmergency);
    $status = $autoApprove ? 'Approved' : 'Pending';
    $convertedNote = '';
    if ($alloc['unpaid_days'] > 0 && ($alloc['paid_days'] > 0 || $alloc['half_paid_days'] > 0)) {
        $convertedNote = ' (includes unpaid days — balance exhausted)';
    } elseif ($alloc['unpaid_days'] > 0) {
        $convertedNote = ' — converted to unpaid (no balance)';
    }

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("INSERT INTO leaves (
            employee_id, from_date, to_date, start_date, end_date,
            leave_type, leave_category, reason, is_paid, status,
            paid_days, half_paid_days, unpaid_days, total_days,
            is_emergency, auto_approved, entry_source, staffing_risk, system_note,
            day_allocation_json
        ) VALUES (
            :eid, :fd, :td, :sd, :ed,
            'General', :lc, :reason, :ip, :status,
            :pd, :hd, :ud, :tdays,
            :emg, :auto, 'employee', :risk, :note,
            :json
        )");
        $ins->execute([
            'eid' => $employeeId,
            'fd' => $from,
            'td' => $to,
            'sd' => $from,
            'ed' => $to,
            'lc' => $alloc['primary_category'],
            'reason' => $reason,
            'ip' => leave_is_paid_flag($alloc['primary_category']),
            'status' => $status,
            'pd' => $alloc['paid_days'],
            'hd' => $alloc['half_paid_days'],
            'ud' => $alloc['unpaid_days'],
            'tdays' => $alloc['total_days'],
            'emg' => $isEmergency ? 1 : 0,
            'auto' => $autoApprove ? 1 : 0,
            'risk' => $staffing['risk'],
            'note' => trim($alloc['primary_category'] . $convertedNote),
            'json' => json_encode($alloc['day_map'], JSON_THROW_ON_ERROR),
        ]);
        $leaveId = (int)$pdo->lastInsertId();

        if ($status === 'Approved') {
            leave_sync_attendance($pdo, $leaveId);
            $pdo->prepare('UPDATE leaves SET approved_at = NOW() WHERE id = :id')->execute(['id' => $leaveId]);
        }

        $pdo->commit();

        if (file_exists(__DIR__ . '/attendance_leave_bridge.php')) {
            require_once __DIR__ . '/attendance_leave_bridge.php';
            attendance_leave_reconcile($pdo, $employeeId, substr($from, 0, 7));
        }

        if ($status === 'Approved') {
            $msg = 'Leave approved. Classified as ' . $alloc['primary_category'] . ' (staffing OK).';
        } elseif ($staffing['risk'] !== 'Safe') {
            $msg = 'Leave submitted — Pending HR approval. ' . $staffing['label'] . '.';
        } else {
            $msg = 'Leave submitted for HR approval. Classified as ' . $alloc['primary_category'] . '.';
        }

        leave_push_notice($pdo, $employeeId, $status === 'Approved' ? 'approved' : 'pending', $msg, $leaveId);

        if ($alloc['unpaid_days'] > 0) {
            leave_push_notice($pdo, $employeeId, 'converted_unpaid', 'Part or all of this leave is unpaid due to exhausted balance.', $leaveId);
        }

        if ($balance['paid_remaining'] <= 2 && $balance['paid_remaining'] > 0) {
            leave_push_notice($pdo, $employeeId, 'balance_low', 'Paid leave balance is running low.', $leaveId);
        }

        if ($staffing['risk'] !== 'Safe') {
            leave_push_hr_staffing_alert($pdo, $employeeId, $staffing, $from, $to);
        }

        return ['ok' => true, 'message' => $msg, 'leave_id' => $leaveId, 'allocation' => $alloc, 'status' => $status];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => 'Could not submit leave: ' . $e->getMessage()];
    }
}

function leave_has_overlap(PDO $pdo, int $employeeId, string $from, string $to, int $excludeId = 0): bool
{
    $sql = "SELECT COUNT(*) FROM leaves WHERE employee_id = :eid AND status IN ('Pending','Approved')
        AND NOT (COALESCE(to_date, end_date) < :from OR COALESCE(from_date, start_date) > :to)";
    if ($excludeId > 0) {
        $sql .= ' AND id <> :xid';
    }
    $st = $pdo->prepare($sql);
    $params = ['eid' => $employeeId, 'from' => $from, 'to' => $to];
    if ($excludeId > 0) {
        $params['xid'] = $excludeId;
    }
    $st->execute($params);

    return (int)$st->fetchColumn() > 0;
}

function leave_attendance_status_for_category(string $category): string
{
    return match ($category) {
        'Half Paid' => 'Half Paid Leave',
        'Unpaid' => 'Unpaid Leave',
        'Holiday' => 'Holiday',
        default => 'Paid Leave',
    };
}

function leave_sync_attendance(PDO $pdo, int $leaveId): void
{
    $st = $pdo->prepare('SELECT l.*, e.shift_timing, e.shift_start, e.shift_end FROM leaves l
        INNER JOIN employees e ON e.id = l.employee_id WHERE l.id = :id LIMIT 1');
    $st->execute(['id' => $leaveId]);
    $leave = $st->fetch(PDO::FETCH_ASSOC);
    if (!$leave || (string)($leave['status'] ?? '') !== 'Approved') {
        leave_remove_attendance($pdo, $leaveId);

        return;
    }

    $dayMap = [];
    if (!empty($leave['day_allocation_json'])) {
        $decoded = json_decode((string)$leave['day_allocation_json'], true);
        if (is_array($decoded)) {
            $dayMap = $decoded;
        }
    }
    if (!$dayMap) {
        $from = (string)($leave['from_date'] ?? $leave['start_date'] ?? '');
        $to = (string)($leave['to_date'] ?? $leave['end_date'] ?? '');
        $cat = (string)($leave['leave_category'] ?? 'Paid');
        foreach (leave_date_range($from, $to) as $ymd) {
            $dayMap[$ymd] = $cat;
        }
    }

    $remark = 'Leave #' . $leaveId . ' — ' . (string)($leave['system_note'] ?? $leave['leave_category']);
    $shiftEnum = employee_shift_enum($leave);
    $employeeId = (int)$leave['employee_id'];

    $upsertSql = "INSERT INTO attendance (employee_id, attendance_date, shift, status, remarks, linked_leave_id, punch_in_time, punch_out_time, total_hours, overtime_hours, is_late, is_early_exit, is_emergency_duty, needs_verification)
        VALUES (:eid, :d, :sh, :st, :rm, :lid, NULL, NULL, NULL, 0, 0, 0, 0, 0)
        ON DUPLICATE KEY UPDATE
            shift = IF(punch_in_time IS NULL AND punch_out_time IS NULL, VALUES(shift), shift),
            status = IF(punch_in_time IS NULL AND punch_out_time IS NULL, VALUES(status), status),
            remarks = IF(punch_in_time IS NULL AND punch_out_time IS NULL, VALUES(remarks), remarks),
            linked_leave_id = IF(punch_in_time IS NULL AND punch_out_time IS NULL, VALUES(linked_leave_id), linked_leave_id),
            is_late = IF(punch_in_time IS NULL AND punch_out_time IS NULL, 0, is_late),
            is_early_exit = IF(punch_in_time IS NULL AND punch_out_time IS NULL, 0, is_early_exit)";

    try {
        $upsert = $pdo->prepare($upsertSql);
    } catch (Throwable $e) {
        $upsertSql = str_replace(', linked_leave_id', '', $upsertSql);
        $upsertSql = str_replace(', :lid', '', $upsertSql);
        $upsertSql = str_replace('linked_leave_id = IF(punch_in_time IS NULL AND punch_out_time IS NULL, VALUES(linked_leave_id), linked_leave_id),', '');
        $upsert = $pdo->prepare($upsertSql);
    }

    foreach ($dayMap as $ymd => $cat) {
        if (in_array($cat, ['Holiday', 'Weekly Off'], true)) {
            continue;
        }
        $params = [
            'eid' => $employeeId,
            'd' => $ymd,
            'sh' => $shiftEnum,
            'st' => leave_attendance_status_for_category((string)$cat),
            'rm' => $remark,
        ];
        if (str_contains($upsertSql, ':lid')) {
            $params['lid'] = $leaveId;
        }
        $upsert->execute($params);
    }
}

function leave_remove_attendance(PDO $pdo, int $leaveId): void
{
    try {
        $pdo->prepare('DELETE FROM attendance WHERE linked_leave_id = :id')->execute(['id' => $leaveId]);
    } catch (Throwable) {
    }
    $pdo->prepare("DELETE FROM attendance WHERE remarks LIKE :rm AND (punch_in_time IS NULL OR punch_in_time = '') AND (punch_out_time IS NULL OR punch_out_time = '')")
        ->execute(['rm' => 'Leave #' . $leaveId . '%']);
}

/** @return array{ok:bool,message:string} */
function leave_approve(PDO $pdo, int $leaveId, int $approverUserId): array
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM leaves WHERE id = :id FOR UPDATE');
        $st->execute(['id' => $leaveId]);
        $leave = $st->fetch(PDO::FETCH_ASSOC);
        if (!$leave) {
            $pdo->rollBack();

            return ['ok' => false, 'message' => 'Leave request not found.'];
        }
        if ((string)$leave['status'] === 'Approved') {
            $pdo->commit();

            return ['ok' => true, 'message' => 'Already approved.'];
        }
        if ((string)$leave['status'] === 'Rejected') {
            $pdo->rollBack();

            return ['ok' => false, 'message' => 'Cannot approve a rejected request.'];
        }

        $pdo->prepare("UPDATE leaves SET status = 'Approved', approved_by = :uid, approved_at = NOW() WHERE id = :id")
            ->execute(['uid' => $approverUserId, 'id' => $leaveId]);
        leave_sync_attendance($pdo, $leaveId);
        $pdo->commit();
        if (file_exists(__DIR__ . '/attendance_leave_bridge.php')) {
            require_once __DIR__ . '/attendance_leave_bridge.php';
            attendance_leave_cleanup_orphans($pdo, (int)$leave['employee_id'], null);
        }

        leave_push_notice($pdo, (int)$leave['employee_id'], 'approved', 'Your leave request was approved.', $leaveId);

        return ['ok' => true, 'message' => 'Leave approved and attendance updated.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

/** @return array{ok:bool,message:string} */
function leave_reject(PDO $pdo, int $leaveId, int $approverUserId, string $reason = ''): array
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM leaves WHERE id = :id FOR UPDATE');
        $st->execute(['id' => $leaveId]);
        $leave = $st->fetch(PDO::FETCH_ASSOC);
        if (!$leave) {
            $pdo->rollBack();

            return ['ok' => false, 'message' => 'Leave request not found.'];
        }
        leave_remove_attendance($pdo, $leaveId);
        if (file_exists(__DIR__ . '/attendance_leave_bridge.php')) {
            require_once __DIR__ . '/attendance_leave_bridge.php';
            attendance_leave_cleanup_orphans($pdo, (int)$leave['employee_id'], null);
        }
        $pdo->prepare("UPDATE leaves SET status = 'Rejected', approved_by = :uid, approved_at = NOW(), rejection_reason = :rr WHERE id = :id")
            ->execute(['uid' => $approverUserId, 'rr' => trim($reason), 'id' => $leaveId]);
        $pdo->commit();

        leave_push_notice($pdo, (int)$leave['employee_id'], 'rejected', 'Your leave request was rejected.' . ($reason !== '' ? ' Reason: ' . $reason : ''), $leaveId);

        return ['ok' => true, 'message' => 'Leave rejected.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function leave_push_notice(PDO $pdo, int $employeeId, string $type, string $message, int $leaveId = 0): void
{
    if (!erp_table_exists_leave($pdo, 'leave_notifications')) {
        return;
    }
    $pdo->prepare('INSERT INTO leave_notifications (employee_id, leave_id, notice_type, message) VALUES (:e, :l, :t, :m)')
        ->execute(['e' => $employeeId, 'l' => $leaveId ?: null, 't' => $type, 'm' => $message]);
}

function leave_push_hr_staffing_alert(PDO $pdo, int $employeeId, array $staffing, string $from, string $to): void
{
    if (!erp_table_exists_leave($pdo, 'leave_notifications')) {
        return;
    }
    $msg = 'Staffing ' . $staffing['risk'] . ' for leave ' . $from . ' to ' . $to . ' (emp #' . $employeeId . ')';
    $pdo->prepare('INSERT INTO leave_notifications (employee_id, leave_id, notice_type, message, audience) VALUES (NULL, NULL, :t, :m, :a)')
        ->execute(['t' => 'hr_staffing', 'm' => $msg, 'a' => 'hr']);
}

function erp_table_exists_leave(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1');
    $st->execute(['t' => $table]);

    return (bool)$st->fetchColumn();
}

/** @return list<array<string,mixed>> */
function leave_department_staffing_overview(PDO $pdo, string $date): array
{
    $depts = $pdo->query("SELECT d.id, d.department_name,
        (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.status = 'active') AS total_employees
        FROM departments d
        ORDER BY d.department_name")->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    foreach ($depts as $d) {
        $deptId = (int)$d['id'];
        $total = (int)$d['total_employees'];
        if ($total < 1) {
            continue;
        }
        $onLeave = leave_count_on_leave($pdo, $deptId, $date);
        $absentStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance a
            INNER JOIN employees e ON e.id = a.employee_id
            WHERE e.department_id = :d AND e.status = 'active' AND a.attendance_date = :dt AND a.status = 'Absent'");
        $absentStmt->execute(['d' => $deptId, 'dt' => $date]);
        $absent = (int)$absentStmt->fetchColumn();
        $present = max(0, $total - $onLeave - $absent);
        $minReq = leave_department_min_present($pdo, $deptId, $total);
        $risk = 'Safe';
        if ($present < $minReq) {
            $risk = $present < (int)floor($minReq * 0.7) ? 'Critical' : 'Warning';
        }
        $rows[] = [
            'department_id' => $deptId,
            'department_name' => (string)$d['department_name'],
            'total' => $total,
            'present' => $present,
            'on_leave' => $onLeave,
            'absent' => $absent,
            'min_required' => $minReq,
            'status' => $risk,
        ];
    }

    return $rows;
}

/** HR dashboard summary */
function leave_hr_dashboard_summary(PDO $pdo): array
{
    $today = date('Y-m-d');
    $pending = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status IN ('Pending','Applied')")->fetchColumn();
    $approved = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'Approved'")->fetchColumn();
    $rejected = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'Rejected'")->fetchColumn();
    $onLeaveStmt = $pdo->prepare("SELECT COUNT(DISTINCT l.employee_id) FROM leaves l WHERE l.status = 'Approved' AND :d BETWEEN COALESCE(l.from_date,l.start_date) AND COALESCE(l.to_date,l.end_date)");
    $onLeaveStmt->execute(['d' => $today]);
    $onLeaveToday = (int)$onLeaveStmt->fetchColumn();

    $criticalDepts = 0;
    $warningDepts = 0;
    foreach (leave_department_staffing_overview($pdo, $today) as $row) {
        if ($row['status'] === 'Critical') {
            $criticalDepts++;
        } elseif ($row['status'] === 'Warning' || $row['status'] === 'Low Staff') {
            $warningDepts++;
        }
    }

    $approvedTodayStmt = $pdo->prepare("SELECT COUNT(*) FROM leaves WHERE status = 'Approved' AND DATE(approved_at) = :d");
    $approvedTodayStmt->execute(['d' => $today]);
    $approvedToday = (int)$approvedTodayStmt->fetchColumn();

    return [
        'pending' => $pending,
        'approved_today' => $approvedToday,
        'approved' => $approved,
        'rejected' => $rejected,
        'on_leave_today' => $onLeaveToday,
        'critical_depts' => $criticalDepts,
        'warning_depts' => $warningDepts,
    ];
}

/** @return list<array<string,mixed>> */
function leave_calendar_events(PDO $pdo, string $month): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }
    $start = $month . '-01';
    $end = date('Y-m-t', strtotime($start));
    $st = $pdo->prepare("SELECT l.*, e.full_name, e.employee_code, d.department_name
        FROM leaves l
        INNER JOIN employees e ON e.id = l.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE l.status IN ('Approved','Pending')
          AND COALESCE(l.to_date, l.end_date) >= :s
          AND COALESCE(l.from_date, l.start_date) <= :e
        ORDER BY l.from_date");
    $st->execute(['s' => $start, 'e' => $end]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function leave_status_badge(string $status): array
{
    return match ($status) {
        'Approved' => ['class' => 'lv-status lv-status--approved', 'label' => 'Approved'],
        'Rejected' => ['class' => 'lv-status lv-status--rejected', 'label' => 'Rejected'],
        'Pending' => ['class' => 'lv-status lv-status--pending', 'label' => 'Pending'],
        default => ['class' => 'lv-status lv-status--pending', 'label' => $status],
    };
}

function leave_risk_badge(string $risk): array
{
    return match ($risk) {
        'Critical' => ['class' => 'leave-staff-badge leave-staff-badge--critical', 'label' => 'Critical'],
        'Warning', 'Low Staff' => ['class' => 'leave-staff-badge leave-staff-badge--low', 'label' => 'Warning'],
        default => ['class' => 'leave-staff-badge leave-staff-badge--safe', 'label' => 'Safe'],
    };
}

function leave_payroll_impact(array $row): string
{
    $pd = (float)($row['paid_days'] ?? 0);
    $hd = (float)($row['half_paid_days'] ?? 0);
    $ud = (float)($row['unpaid_days'] ?? 0);
    if ($ud > 0 && $pd === 0.0 && $hd === 0.0) {
        return 'Full deduction';
    }
    if ($hd > 0) {
        return '50% on half days';
    }
    if ($pd > 0) {
        return 'No deduction';
    }
    $cat = (string)($row['leave_category'] ?? 'Paid');

    return match ($cat) {
        'Unpaid' => 'Full deduction',
        'Half Paid' => '50% deduction',
        default => 'No deduction',
    };
}

function leave_approver_name(PDO $pdo, array $row): string
{
    $uid = (int)($row['approved_by'] ?? 0);
    if ($uid < 1) {
        return !empty($row['auto_approved']) ? 'System' : '—';
    }
    $st = $pdo->prepare('SELECT full_name FROM users WHERE id = :id LIMIT 1');
    $st->execute(['id' => $uid]);

    return (string)($st->fetchColumn() ?: 'HR');
}

function leave_display_status(string $status): string
{
    return $status === 'Applied' ? 'Pending' : $status;
}

/** @return list<array<string,mixed>> */
function leave_fetch_notifications(PDO $pdo, ?int $employeeId = null, string $audience = 'employee', int $limit = 8): array
{
    if (!erp_table_exists_leave($pdo, 'leave_notifications')) {
        return [];
    }
    if ($audience === 'hr') {
        $st = $pdo->prepare('SELECT * FROM leave_notifications WHERE audience = :a ORDER BY id DESC LIMIT :lim');
        $st->bindValue(':a', 'hr');
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($employeeId < 1) {
        return [];
    }
    $st = $pdo->prepare('SELECT * FROM leave_notifications WHERE employee_id = :e AND audience = :a ORDER BY id DESC LIMIT :lim');
    $st->bindValue(':e', $employeeId, PDO::PARAM_INT);
    $st->bindValue(':a', 'employee');
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function leave_pending_requests(PDO $pdo, int $limit = 30): array
{
    $st = $pdo->prepare("SELECT l.*, e.full_name, e.employee_code, d.department_name,
        COALESCE(l.from_date, l.start_date) AS leave_from,
        COALESCE(l.to_date, l.end_date) AS leave_to
        FROM leaves l
        INNER JOIN employees e ON e.id = l.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE l.status IN ('Pending','Applied')
          AND COALESCE(l.entry_source, 'employee') = 'employee'
        ORDER BY l.is_emergency DESC, l.id DESC
        LIMIT :lim");
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * HR manual entry — offline/emergency records, not the employee approval workflow.
 *
 * @return array{ok:bool,message:string,leave_id?:int}
 */
function leave_apply_hr_manual(PDO $pdo, int $employeeId, string $from, string $to, string $reason, int $recordedByUserId, bool $isEmergency = false): array
{
    $reason = trim($reason);
    if ($from === '' || $to === '' || $reason === '') {
        return ['ok' => false, 'message' => 'From date, to date, and reason are required.'];
    }
    if ($from > $to) {
        return ['ok' => false, 'message' => 'From date must be on or before to date.'];
    }

    $empStmt = $pdo->prepare('SELECT e.*, d.department_name AS dept_label FROM employees e LEFT JOIN departments d ON d.id = e.department_id WHERE e.id = :id LIMIT 1');
    $empStmt->execute(['id' => $employeeId]);
    $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        return ['ok' => false, 'message' => 'Employee not found.'];
    }

    if (leave_has_overlap($pdo, $employeeId, $from, $to)) {
        return ['ok' => false, 'message' => 'Overlapping leave already exists for these dates.'];
    }

    $alloc = leave_allocate_days($pdo, $employee, $from, $to);
    if ($alloc['total_days'] < 1 && $alloc['unpaid_days'] < 1) {
        return ['ok' => false, 'message' => 'No working leave days in this range.'];
    }

    $staffing = leave_assess_staffing($pdo, $employeeId, $from, $to);
    $note = 'Manual HR entry — ' . $alloc['primary_category'];

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("INSERT INTO leaves (
            employee_id, from_date, to_date, start_date, end_date,
            leave_type, leave_category, reason, is_paid, status,
            paid_days, half_paid_days, unpaid_days, total_days,
            is_emergency, auto_approved, entry_source, recorded_by, approved_by,
            staffing_risk, system_note, day_allocation_json, approved_at
        ) VALUES (
            :eid, :fd, :td, :sd, :ed,
            'General', :lc, :reason, :ip, 'Approved',
            :pd, :hd, :ud, :tdays,
            :emg, 0, 'hr_manual', :rec, NULL,
            :risk, :note, :json, NOW()
        )");
        $ins->execute([
            'eid' => $employeeId,
            'fd' => $from,
            'td' => $to,
            'sd' => $from,
            'ed' => $to,
            'lc' => $alloc['primary_category'],
            'reason' => $reason,
            'ip' => leave_is_paid_flag($alloc['primary_category']),
            'pd' => $alloc['paid_days'],
            'hd' => $alloc['half_paid_days'],
            'ud' => $alloc['unpaid_days'],
            'tdays' => $alloc['total_days'],
            'emg' => $isEmergency ? 1 : 0,
            'rec' => $recordedByUserId > 0 ? $recordedByUserId : null,
            'risk' => $staffing['risk'],
            'note' => $note,
            'json' => json_encode($alloc['day_map'], JSON_THROW_ON_ERROR),
        ]);
        $leaveId = (int)$pdo->lastInsertId();
        leave_sync_attendance($pdo, $leaveId);
        $pdo->commit();

        leave_push_notice($pdo, $employeeId, 'approved', 'Leave recorded by HR (manual entry).', $leaveId);

        return ['ok' => true, 'message' => 'Manual leave entry saved and attendance updated.', 'leave_id' => $leaveId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => 'Could not save manual entry: ' . $e->getMessage()];
    }
}

/** @return array{ok:bool,message:string} */
function leave_convert_to_unpaid(PDO $pdo, int $leaveId, int $approverUserId): array
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM leaves WHERE id = :id FOR UPDATE');
        $st->execute(['id' => $leaveId]);
        $leave = $st->fetch(PDO::FETCH_ASSOC);
        if (!$leave) {
            $pdo->rollBack();

            return ['ok' => false, 'message' => 'Leave request not found.'];
        }
        if (!in_array((string)($leave['status'] ?? ''), ['Pending', 'Applied', 'Approved'], true)) {
            $pdo->rollBack();

            return ['ok' => false, 'message' => 'Cannot convert this leave to unpaid.'];
        }

        $from = (string)($leave['from_date'] ?? $leave['start_date'] ?? '');
        $to = (string)($leave['to_date'] ?? $leave['end_date'] ?? '');
        $dayMap = [];
        foreach (leave_date_range($from, $to) as $ymd) {
            if ((int)date('N', strtotime($ymd)) === 7) {
                $dayMap[$ymd] = 'Weekly Off';
                continue;
            }
            $dayMap[$ymd] = 'Unpaid';
        }
        $unpaidDays = 0.0;
        foreach ($dayMap as $cat) {
            if ($cat === 'Unpaid') {
                $unpaidDays += 1.0;
            }
        }

        $pdo->prepare("UPDATE leaves SET
            leave_category = 'Unpaid',
            is_paid = 0,
            paid_days = 0,
            half_paid_days = 0,
            unpaid_days = :ud,
            total_days = :td,
            day_allocation_json = :json,
            system_note = CONCAT(COALESCE(system_note,''), ' | HR converted to unpaid')
            WHERE id = :id")->execute([
            'ud' => $unpaidDays,
            'td' => $unpaidDays,
            'json' => json_encode($dayMap, JSON_THROW_ON_ERROR),
            'id' => $leaveId,
        ]);

        if ((string)($leave['status'] ?? '') === 'Approved') {
            leave_sync_attendance($pdo, $leaveId);
        }

        $pdo->commit();
        leave_push_notice($pdo, (int)$leave['employee_id'], 'converted_unpaid', 'Your leave was converted to unpaid by HR.', $leaveId);

        return ['ok' => true, 'message' => 'Leave converted to unpaid.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function leave_category_badge(string $category): array
{
    return match ($category) {
        'Paid' => ['class' => 'lv-cat lv-cat--paid', 'label' => 'Paid'],
        'Half Paid' => ['class' => 'lv-cat lv-cat--half', 'label' => 'Half Paid'],
        'Unpaid' => ['class' => 'lv-cat lv-cat--unpaid', 'label' => 'Unpaid'],
        default => ['class' => 'lv-cat', 'label' => $category !== '' ? $category : '—'],
    };
}

function leave_approval_type_label(array $row): string
{
    $source = (string)($row['entry_source'] ?? 'employee');
    $status = leave_display_status((string)($row['status'] ?? 'Pending'));

    if ($source === 'hr_manual') {
        return 'Manual HR Entry';
    }
    if ($status === 'Rejected') {
        return 'Rejected';
    }
    if ($status === 'Pending') {
        return 'Pending Review';
    }
    if (!empty($row['auto_approved'])) {
        return 'Auto Approved';
    }
    if ((int)($row['approved_by'] ?? 0) > 0) {
        return 'HR Approved';
    }

    return $status === 'Approved' ? 'Approved' : '—';
}

/** @return list<string> */
function leave_smart_alerts(PDO $pdo, array $leaveRow): array
{
    $alerts = [];
    $employeeId = (int)($leaveRow['employee_id'] ?? 0);
    $from = (string)($leaveRow['leave_from'] ?? $leaveRow['from_date'] ?? $leaveRow['start_date'] ?? '');
    $to = (string)($leaveRow['leave_to'] ?? $leaveRow['to_date'] ?? $leaveRow['end_date'] ?? '');
    $deptName = (string)($leaveRow['department_name'] ?? '');
    $leaveId = (int)($leaveRow['id'] ?? 0);

    if ($employeeId > 0 && $from !== '') {
        $balance = leave_get_balance($pdo, $employeeId, leave_year_from_date($from));
        if ($balance['paid_remaining'] <= 1 && $balance['paid_remaining'] >= 0) {
            $alerts[] = 'Employee has only ' . (int)$balance['paid_remaining'] . ' paid leave remaining';
        }
    }

    $risk = (string)($leaveRow['staffing_risk'] ?? '');
    if ($risk === 'Critical') {
        $alerts[] = 'Approving may create critical staffing shortage';
    } elseif ($risk === 'Warning') {
        $alerts[] = 'Department may fall below minimum workforce';
    }

    $empStmt = $pdo->prepare('SELECT department_id FROM employees WHERE id = :id LIMIT 1');
    $empStmt->execute(['id' => $employeeId]);
    $deptId = (int)$empStmt->fetchColumn();
    if ($deptId > 0 && $from !== '') {
        $onLeave = leave_count_on_leave($pdo, $deptId, $from, $leaveId);
        if ($onLeave >= 2) {
            $label = $deptName !== '' ? $deptName : 'this department';
            $alerts[] = $onLeave . ' employees already on leave in ' . $label;
        }
    }

    if (!empty($leaveRow['is_emergency'])) {
        $alerts[] = 'Emergency leave request';
    }

    return $alerts;
}

/** @param array<string,mixed> $filters */
function leave_fetch_hr_requests(PDO $pdo, array $filters = [], int $limit = 100): array
{
    $sql = "SELECT l.*, e.full_name, e.employee_code, e.employee_type, d.department_name, d.id AS department_id,
        u.full_name AS approver_name,
        COALESCE(l.from_date, l.start_date) AS leave_from,
        COALESCE(l.to_date, l.end_date) AS leave_to
        FROM leaves l
        JOIN employees e ON e.id = l.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN users u ON u.id = l.approved_by
        WHERE 1=1";
    $params = [];

    if (!empty($filters['department_id'])) {
        $sql .= ' AND e.department_id = :dept';
        $params['dept'] = (int)$filters['department_id'];
    }
    if (!empty($filters['status'])) {
        $st = (string)$filters['status'];
        if ($st === 'Pending') {
            $sql .= " AND l.status IN ('Pending','Applied')";
        } else {
            $sql .= ' AND l.status = :status';
            $params['status'] = $st;
        }
    }
    if (!empty($filters['leave_type'])) {
        $sql .= ' AND l.leave_category = :ltype';
        $params['ltype'] = (string)$filters['leave_type'];
    }
    if (!empty($filters['date_from'])) {
        $sql .= ' AND COALESCE(l.to_date, l.end_date) >= :df';
        $params['df'] = (string)$filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= ' AND COALESCE(l.from_date, l.start_date) <= :dt';
        $params['dt'] = (string)$filters['date_to'];
    }
    if (!empty($filters['critical_only'])) {
        $sql .= " AND l.staffing_risk = 'Critical'";
    }
    if (!empty($filters['pending_only'])) {
        $sql .= " AND l.status IN ('Pending','Applied') AND COALESCE(l.entry_source,'employee') = 'employee'";
    }
    if (!empty($filters['search'])) {
        $sql .= ' AND (e.full_name LIKE :q OR e.employee_code LIKE :q)';
        $params['q'] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string)$filters['search']) . '%';
    }

    $sql .= " ORDER BY FIELD(l.status, 'Pending', 'Applied', 'Approved', 'Rejected'), l.is_emergency DESC, l.id DESC LIMIT " . max(1, min(200, $limit));

    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Single top-of-page staffing notice (first low-staff dept only). */
function leave_top_staffing_notice(PDO $pdo): ?string
{
    $today = date('Y-m-d');
    foreach (leave_department_staffing_overview($pdo, $today) as $row) {
        if (in_array((string)($row['status'] ?? ''), ['Critical', 'Warning'], true)) {
            return (string)$row['department_name'] . ' department has low staffing today.';
        }
    }

    return null;
}

/** @return array<string,mixed>|null */
function leave_hr_detail(PDO $pdo, int $leaveId): ?array
{
    $st = $pdo->prepare("SELECT l.*, e.full_name, e.employee_code, e.employee_type, e.paid_leave_limit, e.half_paid_leave_limit,
        d.department_name, d.id AS department_id,
        u.full_name AS approver_name, ru.full_name AS recorded_by_name,
        COALESCE(l.from_date, l.start_date) AS leave_from,
        COALESCE(l.to_date, l.end_date) AS leave_to
        FROM leaves l
        JOIN employees e ON e.id = l.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN users u ON u.id = l.approved_by
        LEFT JOIN users ru ON ru.id = l.recorded_by
        WHERE l.id = :id LIMIT 1");
    $st->execute(['id' => $leaveId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $employeeId = (int)$row['employee_id'];
    $from = (string)$row['leave_from'];
    $to = (string)$row['leave_to'];
    $deptId = (int)($row['department_id'] ?? 0);

    $row['balance'] = leave_get_balance($pdo, $employeeId, leave_year_from_date($from));
    $row['staffing'] = leave_assess_staffing($pdo, $employeeId, $from, $to, $leaveId);
    $row['alerts'] = leave_smart_alerts($pdo, $row);
    $row['approval_type'] = leave_approval_type_label($row);
    $row['payroll_impact'] = leave_payroll_impact($row);

    $overlap = [];
    if ($deptId > 0) {
        $ov = $pdo->prepare("SELECT e.full_name, COALESCE(l.from_date,l.start_date) AS lf, COALESCE(l.to_date,l.end_date) AS lt, l.status
            FROM leaves l
            INNER JOIN employees e ON e.id = l.employee_id
            WHERE e.department_id = :d AND e.id <> :eid AND l.status IN ('Approved','Pending')
              AND NOT (COALESCE(l.to_date,l.end_date) < :from OR COALESCE(l.from_date,l.start_date) > :to)
            ORDER BY lf LIMIT 10");
        $ov->execute(['d' => $deptId, 'eid' => $employeeId, 'from' => $from, 'to' => $to]);
        $overlap = $ov->fetchAll(PDO::FETCH_ASSOC);
    }
    $row['overlapping'] = $overlap;

    if ($deptId > 0) {
        $staffDate = $from !== '' ? $from : date('Y-m-d');
        $onLeave = leave_count_on_leave($pdo, $deptId, $staffDate, $leaveId);
        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = :d AND status = 'active'");
        $totalStmt->execute(['d' => $deptId]);
        $total = (int)$totalStmt->fetchColumn();
        $row['dept_staffing'] = [
            'date' => $staffDate,
            'total' => $total,
            'on_leave' => $onLeave,
            'present' => max(0, $total - $onLeave),
            'min_required' => leave_department_min_present($pdo, $deptId, $total),
        ];
    }

    return $row;
}
