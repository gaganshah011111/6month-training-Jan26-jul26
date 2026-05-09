<?php
declare(strict_types=1);

require_once __DIR__ . '/payroll_logic.php';

/** Status options HR uses for manual worker attendance */
const HR_WORKER_ATTENDANCE_STATUSES = [
    'Present',
    'Absent',
    'Half Day',
    'Paid Leave',
    'Unpaid Leave',
    'Holiday',
    'Emergency Duty',
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

/** HR manual entry: compute hours, late, early exit, OT after shift end, suggested Present/Half Day */
function hr_compute_attendance_metrics(array $employee, string $attendanceDateYmd, string $punchInSql, string $punchOutSql): array
{
    $inTs = strtotime($punchInSql);
    $outTs = strtotime($punchOutSql);
    if ($inTs === false || $outTs === false || $outTs <= $inTs) {
        throw new InvalidArgumentException('Out time must be after in time.');
    }
    $worked = round(($outTs - $inTs) / 3600, 2);
    [$shiftStartTs, $shiftEndTs, $schedH] = employee_shift_window_timestamps($attendanceDateYmd, $employee);
    $isLate = $inTs > $shiftStartTs ? 1 : 0;
    $isEarlyExit = $outTs < $shiftEndTs ? 1 : 0;
    $otAfterShiftEnd = max(0, round(($outTs - $shiftEndTs) / 3600, 2));
    $minFull = min(4.0, max(2.0, round($schedH * 0.5, 2)));
    if ($worked < $minFull) {
        $suggestedStatus = 'Half Day';
    } elseif ($isLate) {
        $suggestedStatus = 'Late';
    } else {
        $suggestedStatus = 'Present';
    }

    return [
        'total_hours' => $worked,
        'overtime_hours' => $otAfterShiftEnd,
        'is_late' => $isLate,
        'is_early_exit' => $isEarlyExit,
        'suggested_status' => $suggestedStatus,
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

/**
 * Staff punch in — real timestamp only; no fake punch-out.
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
        $sel = $pdo->prepare('SELECT id, punch_in_time, punch_out_time FROM attendance WHERE employee_id = :e AND attendance_date = :d FOR UPDATE');
        $sel->execute(['e' => $employeeId, 'd' => $todayYmd]);
        $row = $sel->fetch();

        if ($row && $row['punch_in_time'] !== null && $row['punch_in_time'] !== '') {
            throw new RuntimeException('You have already punched in today.');
        }

        if (!$row) {
            $ins = $pdo->prepare('INSERT INTO attendance (employee_id, attendance_date, shift, status, remarks, punch_in_time, punch_out_time, total_hours, overtime_hours, is_late, is_early_exit, is_emergency_duty)
                VALUES (:e, :d, :sh, :st, :rm, :pi, NULL, NULL, 0, 0, 0, 0)');
            $ins->execute([
                'e' => $employeeId,
                'd' => $todayYmd,
                'sh' => $shift,
                'st' => 'Present',
                'rm' => 'Punch in',
                'pi' => $now,
            ]);
        } else {
            $upd = $pdo->prepare('UPDATE attendance SET shift = :sh, status = :st, remarks = :rm, punch_in_time = :pi, punch_out_time = NULL, total_hours = NULL, overtime_hours = 0, is_late = 0, is_early_exit = 0, is_emergency_duty = 0 WHERE id = :id');
            $upd->execute([
                'sh' => $shift,
                'st' => 'Present',
                'rm' => 'Punch in',
                'pi' => $now,
                'id' => (int)$row['id'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Staff punch out — computes hours, late, half-day, OT, emergency (Sunday / company holiday).
 *
 * @throws RuntimeException
 */
function staff_record_punch_out(PDO $pdo, array $employee, string $todayYmd): void
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
        if (!$row) {
            throw new RuntimeException('Punch in first, then punch out.');
        }
        $punchIn = (string)($row['punch_in_time'] ?? '');
        if ($punchIn === '') {
            throw new RuntimeException('Punch in first, then punch out.');
        }
        if ($row['punch_out_time'] !== null && $row['punch_out_time'] !== '') {
            throw new RuntimeException('You have already punched out today.');
        }

        $punchOut = date('Y-m-d H:i:s');
        if (strtotime($punchOut) <= strtotime($punchIn)) {
            throw new RuntimeException('Invalid punch times.');
        }

        $totalHours = compute_work_hours($punchIn, $punchOut);
        [$startTs, $endTs, $scheduledHrs] = employee_shift_window_timestamps($todayYmd, $employee);
        $punchInTs = strtotime($punchIn);
        $punchOutTs = strtotime($punchOut);
        if ($punchInTs === false || $punchOutTs === false) {
            throw new RuntimeException('Could not evaluate punch times.');
        }

        $isRest = attendance_is_rest_day($pdo, $todayYmd);
        $minFullDayHrs = min(4.0, max(2.0, round($scheduledHrs * 0.5, 2)));

        $isLate = $punchInTs > $startTs;
        $isEarlyExit = $punchOutTs < $endTs;

        if ($isRest) {
            $status = 'Emergency Duty';
            $overtimeHours = $totalHours;
            $remarks = attendance_is_sunday($todayYmd) ? 'Emergency duty (Sunday)' : 'Emergency duty (company holiday)';
            $duty = 1;
            $isLate = 0;
            $isEarlyExit = 0;
        } else {
            $overtimeHours = max(0, round(($punchOutTs - $endTs) / 3600, 2));
            if ($totalHours < $minFullDayHrs) {
                $status = 'Half Day';
            } elseif ($isLate) {
                $status = 'Late';
            } else {
                $status = 'Present';
            }
            $parts = [];
            if ($overtimeHours > 0) {
                $parts[] = 'Overtime ' . $overtimeHours . 'h';
            }
            if ($isEarlyExit && $status !== 'Half Day') {
                $parts[] = 'Early exit';
            }
            $remarks = $parts !== [] ? implode('; ', $parts) : 'Punch out recorded';
            $duty = 0;
        }

        $upd = $pdo->prepare('UPDATE attendance SET punch_out_time = :po, total_hours = :th, overtime_hours = :oh, is_late = :il, is_early_exit = :ie, is_emergency_duty = :ied, status = :st, remarks = :rm WHERE id = :id');
        $upd->execute([
            'po' => $punchOut,
            'th' => $totalHours,
            'oh' => $overtimeHours,
            'il' => $isLate ? 1 : 0,
            'ie' => $isEarlyExit ? 1 : 0,
            'ied' => $duty,
            'st' => $status,
            'rm' => $remarks,
            'id' => (int)$row['id'],
        ]);
        $pdo->commit();
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
