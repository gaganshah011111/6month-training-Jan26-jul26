<?php
declare(strict_types=1);

require_once __DIR__ . '/payroll_logic.php';
require_once __DIR__ . '/attendance_policy.php';

/** Status options HR uses for manual worker attendance */
const HR_WORKER_ATTENDANCE_STATUSES = [
    'Present',
    'Absent',
    'Half Day',
    'Paid Leave',
    'Unpaid Leave',
    'Holiday',
    'Emergency Duty',
    ATTENDANCE_STATUS_PENDING_VERIFICATION,
];

function attendance_normalize_worker_status(string $status): string
{
    $status = trim($status);
    if ($status === 'Leave') {
        return 'Paid Leave';
    }
    return $status;
}

function employee_shift_enum(array $employee): string
{
    $t = strtoupper((string)($employee['shift_timing'] ?? ''));
    if (str_contains($t, 'EVENING')) {
        return 'Evening';
    }
    if (str_contains($t, 'NIGHT')) {
        return 'Night';
    }
    return 'Morning';
}

/**
 * @return array{0: string, 1: string} Clock times HH:MM:SS for shift start/end on a calendar day
 */
function employee_shift_clock_bounds(array $employee): array
{
    $start = $employee['shift_start'] ?? null;
    $end = $employee['shift_end'] ?? null;
    if ($start && $end) {
        $startStr = strlen((string)$start) <= 5 ? (string)$start . ':00' : (string)$start;
        $endStr = strlen((string)$end) <= 5 ? (string)$end . ':00' : (string)$end;
        return [$startStr, $endStr];
    }
    if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', (string)($employee['shift_timing'] ?? ''), $m)) {
        return [$m[1] . ':00', $m[2] . ':00'];
    }
    return ['09:00:00', '18:00:00'];
}

/**
 * Shift start/end Unix timestamps for an attendance calendar date (handles night shift crossing midnight).
 *
 * @return array{0: int, 1: int, 2: float} startTs, endTs, scheduledHours
 */
function employee_shift_window_timestamps(string $attendanceDateYmd, array $employee): array
{
    [$startClock, $endClock] = employee_shift_clock_bounds($employee);
    $startTs = strtotime($attendanceDateYmd . ' ' . $startClock);
    if ($startTs === false) {
        $startTs = strtotime($attendanceDateYmd . ' 09:00:00');
    }
    $endSameDay = strtotime($attendanceDateYmd . ' ' . $endClock);
    if ($endSameDay === false) {
        $endSameDay = strtotime($attendanceDateYmd . ' 18:00:00');
    }
    if ($endSameDay <= $startTs) {
        $nextDay = date('Y-m-d', strtotime($attendanceDateYmd . ' +1 day'));
        $endTs = strtotime($nextDay . ' ' . $endClock);
        if ($endTs === false) {
            $endTs = $startTs + (8 * 3600);
        }
    } else {
        $endTs = $endSameDay;
    }
    $hours = round(($endTs - $startTs) / 3600, 2);

    return [$startTs, $endTs, max(0.5, $hours)];
}

function employee_scheduled_shift_hours(array $employee): float
{
    $ref = date('Y-m-d');
    return employee_shift_window_timestamps($ref, $employee)[2];
}

/**
 * Build punch datetimes from attendance date + time-of-day; if out is not after in on same calendar day, roll out to next day (night shift).
 *
 * @return array{0: string, 1: string} MySQL datetime strings
 */
function hr_build_punch_datetimes(string $attendanceDateYmd, string $inHi, string $outHi): array
{
    $inHi = trim($inHi);
    $outHi = trim($outHi);
    if ($inHi === '' || $outHi === '') {
        throw new InvalidArgumentException('Both punch times are required.');
    }
    if (strlen($inHi) === 5) {
        $inHi .= ':00';
    }
    if (strlen($outHi) === 5) {
        $outHi .= ':00';
    }
    $pit = strtotime($attendanceDateYmd . ' ' . $inHi);
    $potSame = strtotime($attendanceDateYmd . ' ' . $outHi);
    if ($pit === false || $potSame === false) {
        throw new InvalidArgumentException('Invalid punch time.');
    }
    if ($potSame <= $pit) {
        $next = date('Y-m-d', strtotime($attendanceDateYmd . ' +1 day'));
        $pot = strtotime($next . ' ' . $outHi);
    } else {
        $pot = $potSame;
    }
    if ($pot === false || $pot <= $pit) {
        throw new InvalidArgumentException('Punch out must be after punch in.');
    }

    return [date('Y-m-d H:i:s', $pit), date('Y-m-d H:i:s', $pot)];
}

/** HR manual entry: compute hours, late, early exit, OT, suggested status via rule engine */
function hr_compute_attendance_metrics(array $employee, string $attendanceDateYmd, string $punchInSql, string $punchOutSql, ?PDO $pdo = null): array
{
    if ($pdo === null) {
        require_once __DIR__ . '/../config/db.php';
        $pdo = Database::connection();
    }
    $isRest = attendance_is_rest_day($pdo, $attendanceDateYmd);
    $eval = attendance_evaluate_workday($pdo, $employee, $attendanceDateYmd, $punchInSql, $punchOutSql, $isRest);

    return [
        'total_hours' => $eval['total_hours'],
        'overtime_hours' => $eval['overtime_hours'],
        'is_late' => $eval['is_late'],
        'is_early_exit' => $eval['is_early_exit'],
        'suggested_status' => $eval['status'],
        'needs_verification' => $eval['needs_verification'],
        'warning_message' => $eval['warning_message'],
    ];
}

function attendance_is_company_holiday(PDO $pdo, string $dateYmd): bool
{
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM hr_holidays WHERE holiday_date = :d LIMIT 1');
        $stmt->execute(['d' => $dateYmd]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    } catch (Throwable $e) {
        // hr_holidays may not exist on very old DBs before migration runs
    }
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM company_holidays WHERE holiday_date = :d LIMIT 1');
        $stmt->execute(['d' => $dateYmd]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function attendance_is_sunday(string $dateYmd): bool
{
    return (int)date('w', strtotime($dateYmd . ' 12:00:00')) === 0;
}

function attendance_is_rest_day(PDO $pdo, string $dateYmd): bool
{
    return attendance_is_sunday($dateYmd) || attendance_is_company_holiday($pdo, $dateYmd);
}

/** Whether today's row has a punch-in timestamp. */
function staff_has_punch_in(?array $row): bool
{
    return $row !== null && trim((string)($row['punch_in_time'] ?? '')) !== '';
}

/** Whether today's attendance session is finalized (punch out recorded). */
function staff_has_punch_out(?array $row): bool
{
    return $row !== null && trim((string)($row['punch_out_time'] ?? '')) !== '';
}

/**
 * One session per day: none | active (in only) | completed (in + out).
 */
function staff_punch_session_state(?array $row): string
{
    if ($row === null) {
        return 'none';
    }
    if (staff_has_punch_out($row)) {
        return 'completed';
    }
    if (staff_has_punch_in($row)) {
        return 'active';
    }

    return 'none';
}

/**
 * Live DB permissions for employee punch UI (do not rely on frontend alone).
 *
 * @return array{
 *   state: string,
 *   can_punch_in: bool,
 *   can_punch_out: bool,
 *   locked: bool,
 *   message: string,
 *   row: ?array
 * }
 */
function staff_punch_ui_state(PDO $pdo, int $employeeId, string $todayYmd): array
{
    $row = staff_today_attendance_row($pdo, $employeeId, $todayYmd);
    $state = staff_punch_session_state($row);

    return match ($state) {
        'active' => [
            'state' => 'active',
            'can_punch_in' => false,
            'can_punch_out' => true,
            'locked' => false,
            'message' => 'Work session active — punch out when you finish.',
            'row' => $row,
        ],
        'completed' => [
            'state' => 'completed',
            'can_punch_in' => false,
            'can_punch_out' => false,
            'locked' => true,
            'message' => 'Attendance completed for today.',
            'row' => $row,
        ],
        default => [
            'state' => 'none',
            'can_punch_in' => true,
            'can_punch_out' => false,
            'locked' => false,
            'message' => '',
            'row' => $row,
        ],
    };
}

/** Format decimal hours as "8h 32m" for completed session card. */
function staff_format_worked_duration($hours): string
{
    if ($hours === null || $hours === '') {
        return '—';
    }
    $totalMins = (int)round((float)$hours * 60);
    $h = intdiv($totalMins, 60);
    $m = $totalMins % 60;
    if ($h > 0 && $m > 0) {
        return $h . 'h ' . $m . 'm';
    }
    if ($h > 0) {
        return $h . 'h';
    }

    return $m . 'm';
}

/**
 * Staff punch in — real timestamp only; status In Progress until punch out.
 *
 * @throws RuntimeException
 */
function staff_record_punch_in(PDO $pdo, array $employee, string $todayYmd): void
{
    if (($employee['employee_type'] ?? 'Staff') !== 'Staff') {
        throw new RuntimeException('Punch in is only available for staff.');
    }

    $employeeId = (int)$employee['id'];
    $shift = employee_shift_enum($employee);
    $now = date('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $sel = $pdo->prepare('SELECT id, status, punch_in_time, punch_out_time FROM attendance WHERE employee_id = :e AND attendance_date = :d FOR UPDATE');
        $sel->execute(['e' => $employeeId, 'd' => $todayYmd]);
        $row = $sel->fetch();

        $state = staff_punch_session_state($row ?: null);
        if ($state === 'completed') {
            throw new RuntimeException('Attendance completed for today. Contact HR if a correction is needed.');
        }
        if ($state === 'active') {
            throw new RuntimeException('You are already punched in. Punch out before starting again.');
        }

        $leaveOnly = ['Paid Leave', 'Unpaid Leave', 'Half Paid Leave', 'Leave'];
        if ($row && in_array((string)($row['status'] ?? ''), $leaveOnly, true)) {
            throw new RuntimeException('Today is recorded as leave. Contact HR before punching attendance.');
        }

        if (!$row) {
            $ins = $pdo->prepare('INSERT INTO attendance (employee_id, attendance_date, shift, status, remarks, punch_in_time, punch_out_time, total_hours, overtime_hours, is_late, is_early_exit, is_emergency_duty, needs_verification)
                VALUES (:e, :d, :sh, :st, :rm, :pi, NULL, NULL, NULL, 0, 0, 0, 0)');
            $ins->execute([
                'e' => $employeeId,
                'd' => $todayYmd,
                'sh' => $shift,
                'st' => ATTENDANCE_STATUS_IN_PROGRESS,
                'rm' => 'Punch in',
                'pi' => $now,
            ]);
        } else {
            $upd = $pdo->prepare('UPDATE attendance SET shift = :sh, status = :st, remarks = :rm, punch_in_time = :pi, punch_out_time = NULL, total_hours = NULL, overtime_hours = 0, is_late = 0, is_early_exit = 0, is_emergency_duty = 0, needs_verification = 0, verified_by = NULL, verified_at = NULL WHERE id = :id AND (punch_out_time IS NULL OR punch_out_time = \'\') AND (punch_in_time IS NULL OR punch_in_time = \'\')');
            $upd->execute([
                'sh' => $shift,
                'st' => ATTENDANCE_STATUS_IN_PROGRESS,
                'rm' => 'Punch in',
                'pi' => $now,
                'id' => (int)$row['id'],
            ]);
            if ($upd->rowCount() === 0) {
                throw new RuntimeException('Cannot punch in — today\'s attendance is locked or already started.');
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Staff punch out — rule engine for status; short punches → Pending Verification.
 *
 * @return string|null Warning message for flash display
 * @throws RuntimeException
 */
function staff_record_punch_out(PDO $pdo, array $employee, string $todayYmd): ?string
{
    if (($employee['employee_type'] ?? 'Staff') !== 'Staff') {
        throw new RuntimeException('Punch out is only available for staff.');
    }

    $employeeId = (int)$employee['id'];
    $pdo->beginTransaction();
    try {
        $sel = $pdo->prepare('SELECT * FROM attendance WHERE employee_id = :e AND attendance_date = :d FOR UPDATE');
        $sel->execute(['e' => $employeeId, 'd' => $todayYmd]);
        $row = $sel->fetch();
        if (!$row || staff_punch_session_state($row) === 'none') {
            throw new RuntimeException('Punch in first, then punch out.');
        }
        if (staff_punch_session_state($row) === 'completed') {
            throw new RuntimeException('Attendance completed for today. Contact HR if a correction is needed.');
        }

        $punchIn = (string)($row['punch_in_time'] ?? '');
        $punchOut = date('Y-m-d H:i:s');
        $inTs = strtotime($punchIn);
        $outTs = strtotime($punchOut);
        if ($inTs === false || $outTs === false || $outTs <= $inTs) {
            throw new RuntimeException('Invalid punch times.');
        }

        $policy = attendance_policy_fetch($pdo);
        $minValidH = max(0.25, (float)($policy['min_valid_punch_hours'] ?? 0.5));
        $minSeconds = (int)max(60, round($minValidH * 3600));
        if (($outTs - $inTs) < $minSeconds) {
            throw new RuntimeException('Invalid attendance duration. Work the minimum required time before punching out.');
        }

        $isRest = attendance_is_rest_day($pdo, $todayYmd);
        $eval = attendance_evaluate_workday($pdo, $employee, $todayYmd, $punchIn, $punchOut, $isRest);

        $upd = $pdo->prepare('UPDATE attendance SET punch_out_time = :po, total_hours = :th, overtime_hours = :oh, is_late = :il, is_early_exit = :ie, is_emergency_duty = :ied, status = :st, remarks = :rm, needs_verification = :nv WHERE id = :id AND (punch_out_time IS NULL OR punch_out_time = \'\')');
        $upd->execute([
            'po' => $punchOut,
            'th' => $eval['total_hours'],
            'oh' => $eval['overtime_hours'],
            'il' => $eval['is_late'],
            'ie' => $eval['is_early_exit'],
            'ied' => $eval['is_emergency_duty'],
            'st' => $eval['status'],
            'rm' => $eval['remarks'],
            'nv' => $eval['needs_verification'],
            'id' => (int)$row['id'],
        ]);
        if ($upd->rowCount() === 0) {
            throw new RuntimeException('Attendance already finalized for today.');
        }
        $pdo->commit();

        return ($eval['warning_message'] ?? '') !== '' ? (string)$eval['warning_message'] : null;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Today's staff attendance row for dashboard / punch UI.
 */
function staff_today_attendance_row(PDO $pdo, int $employeeId, string $todayYmd): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM attendance WHERE employee_id = :e AND attendance_date = :d LIMIT 1');
    $stmt->execute(['e' => $employeeId, 'd' => $todayYmd]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Display label for in-progress / pending statuses on employee UI. */
function attendance_status_display_label(?string $status, bool $hasPunchIn, bool $hasPunchOut): string
{
    if ($hasPunchOut) {
        return $status !== null && $status !== '' && $status !== ATTENDANCE_STATUS_IN_PROGRESS
            ? $status
            : 'Completed';
    }
    if ($status === ATTENDANCE_STATUS_IN_PROGRESS && $hasPunchIn) {
        return 'Punched In';
    }
    if ($hasPunchIn && !$hasPunchOut) {
        return 'Session open (punch out pending)';
    }
    if ($status === null || $status === '') {
        return 'Not marked';
    }

    return $status;
}

/**
 * HR/Admin only: reopen a finalized staff punch session for correction.
 *
 * @throws RuntimeException
 */
function attendance_hr_reopen_staff_session(PDO $pdo, int $attendanceId, int $hrUserId, string $note = ''): void
{
    $st = $pdo->prepare('SELECT a.*, e.employee_type FROM attendance a INNER JOIN employees e ON e.id = a.employee_id WHERE a.id = :id LIMIT 1');
    $st->execute(['id' => $attendanceId]);
    $row = $st->fetch();
    if (!$row) {
        throw new RuntimeException('Attendance record not found.');
    }
    if (($row['employee_type'] ?? '') !== 'Staff') {
        throw new RuntimeException('Reopen is only for staff punch attendance.');
    }
    if (!staff_has_punch_out($row)) {
        throw new RuntimeException('Record is not finalized — no reopen needed.');
    }
    $rm = trim($note) !== '' ? trim($note) : 'HR reopened session';
    $upd = $pdo->prepare('UPDATE attendance SET punch_out_time = NULL, total_hours = NULL, overtime_hours = 0, status = :st, needs_verification = 0, remarks = CONCAT(COALESCE(remarks, \'\'), CASE WHEN remarks IS NULL OR remarks = \'\' THEN :rm ELSE CONCAT(\'; \', :rm2) END), verified_by = :vb, verified_at = NOW() WHERE id = :id');
    $upd->execute([
        'st' => ATTENDANCE_STATUS_IN_PROGRESS,
        'rm' => $rm,
        'rm2' => $rm,
        'vb' => $hrUserId > 0 ? $hrUserId : null,
        'id' => $attendanceId,
    ]);
}
