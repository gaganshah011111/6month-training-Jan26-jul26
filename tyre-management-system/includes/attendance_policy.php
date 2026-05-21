<?php
declare(strict_types=1);

require_once __DIR__ . '/indian_payroll.php';

const ATTENDANCE_STATUS_IN_PROGRESS = 'In Progress';
const ATTENDANCE_STATUS_PENDING_VERIFICATION = 'Pending Verification';

/** @return array<string, float|int> */
function attendance_policy_defaults(): array
{
    return [
        'full_day_min_hours' => 8.0,
        'half_day_min_hours' => 4.0,
        'grace_late_minutes' => 15,
        'min_valid_punch_hours' => 0.5,
        'auto_absent_after_hours' => 0.0,
        'ot_threshold_hours' => 0.0,
    ];
}

/** @return array<string, float|int> */
function attendance_policy_fetch(PDO $pdo): array
{
    $defaults = attendance_policy_defaults();
    $ps = payroll_settings_fetch($pdo);
    foreach ($defaults as $key => $val) {
        if (array_key_exists($key, $ps) && $ps[$key] !== null && $ps[$key] !== '') {
            $defaults[$key] = is_int($val) ? (int)$ps[$key] : (float)$ps[$key];
        }
    }

    return $defaults;
}

/**
 * Effective full-day hours: employee scheduled shift or policy default (whichever applies).
 */
function attendance_effective_full_day_hours(array $employee, array $policy): float
{
    $scheduled = employee_scheduled_shift_hours($employee);
    $policyFull = max(0.5, (float)($policy['full_day_min_hours'] ?? 8));
    $shiftDefault = max(0.5, (float)($policy['shift_hours_default'] ?? $policyFull));

    return max($scheduled, $policyFull, $shiftDefault);
}

/**
 * Evaluate worked time against industrial attendance rules.
 *
 * @return array{
 *   status: string,
 *   total_hours: float,
 *   overtime_hours: float,
 *   is_late: int,
 *   is_early_exit: int,
 *   is_emergency_duty: int,
 *   needs_verification: int,
 *   remarks: string,
 *   warning_message: string
 * }
 */
function attendance_evaluate_workday(
    PDO $pdo,
    array $employee,
    string $attendanceDateYmd,
    string $punchInSql,
    string $punchOutSql,
    bool $isRestDay = false
): array {
    $inTs = strtotime($punchInSql);
    $outTs = strtotime($punchOutSql);
    if ($inTs === false || $outTs === false || $outTs <= $inTs) {
        throw new InvalidArgumentException('Punch out must be after punch in.');
    }

    $policy = attendance_policy_fetch($pdo);
    $worked = round(($outTs - $inTs) / 3600, 2);
    [$shiftStartTs, $shiftEndTs, $schedH] = employee_shift_window_timestamps($attendanceDateYmd, $employee);
    $graceSec = max(0, (int)($policy['grace_late_minutes'] ?? 0)) * 60;
    $isLate = ($inTs > ($shiftStartTs + $graceSec)) ? 1 : 0;
    $isEarlyExit = $outTs < $shiftEndTs ? 1 : 0;

    // ERP status thresholds use payroll policy hours (default 8 / 4), not shift length alone.
    $fullDayHrs = max(0.5, (float)($policy['full_day_min_hours'] ?? 8));
    $halfDayMin = max(0.5, (float)($policy['half_day_min_hours'] ?? 4));
    $minValid = max(0.25, (float)($policy['min_valid_punch_hours'] ?? 0.5));
    $otThreshold = max(0.0, (float)($policy['ot_threshold_hours'] ?? 0));
    $otAfterShift = max(0, round(($outTs - $shiftEndTs) / 3600, 2));
    $overtimeHours = $otThreshold > 0 && $otAfterShift < $otThreshold ? 0.0 : $otAfterShift;

    if ($isRestDay) {
        return [
            'status' => 'Emergency Duty',
            'total_hours' => $worked,
            'overtime_hours' => $worked,
            'is_late' => 0,
            'is_early_exit' => 0,
            'is_emergency_duty' => 1,
            'needs_verification' => 0,
            'remarks' => ((int)date('w', strtotime($attendanceDateYmd . ' 12:00:00')) === 0) ? 'Emergency duty (Sunday)' : 'Emergency duty (company holiday)',
            'warning_message' => '',
        ];
    }

    $needsVerification = 0;
    $warning = '';

    if ($worked < $halfDayMin) {
        $status = 'Absent';
        $remarks = 'Insufficient hours (' . $worked . 'h, minimum half-day ' . $halfDayMin . 'h)';
        if ($worked < $minValid) {
            $warning = 'Working duration below minimum. Marked absent — contact HR if this was an error.';
            $remarks = 'Short attendance (' . $worked . 'h) — marked absent';
        } else {
            $warning = 'Worked below half-day threshold (' . $halfDayMin . 'h). Marked absent for payroll.';
        }
    } elseif ($worked >= $fullDayHrs) {
        $status = $isLate ? 'Late' : 'Present';
        $remarksParts = [];
        if ($overtimeHours > 0) {
            $remarksParts[] = 'Overtime ' . $overtimeHours . 'h';
        }
        if ($isEarlyExit) {
            $remarksParts[] = 'Early exit';
        }
        $remarks = $remarksParts !== [] ? implode('; ', $remarksParts) : 'Punch out recorded';
    } else {
        $status = 'Half Day';
        $remarks = 'Half day (' . $worked . 'h of ' . round($fullDayHrs, 1) . 'h required)';
        if ($isLate) {
            $remarks .= '; Late arrival';
        }
    }

    return [
        'status' => $status,
        'total_hours' => $worked,
        'overtime_hours' => $overtimeHours,
        'is_late' => $isLate,
        'is_early_exit' => $isEarlyExit,
        'is_emergency_duty' => 0,
        'needs_verification' => $needsVerification,
        'remarks' => $remarks,
        'warning_message' => $warning,
    ];
}

/** Count attendance rows pending HR verification for payroll month. */
function attendance_count_pending_verification(PDO $pdo, string $monthYyyyMm, ?int $employeeId = null): int
{
    $sql = "SELECT COUNT(*) FROM attendance
        WHERE status = :st AND needs_verification = 1
        AND DATE_FORMAT(attendance_date, '%Y-%m') = :m";
    $params = ['st' => ATTENDANCE_STATUS_PENDING_VERIFICATION, 'm' => $monthYyyyMm];
    if ($employeeId !== null) {
        $sql .= ' AND employee_id = :e';
        $params['e'] = $employeeId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return (int)$st->fetchColumn();
}

/**
 * @return list<array<string, mixed>>
 */
function attendance_fetch_verification_queue(PDO $pdo, ?string $monthYyyyMm = null, int $limit = 50): array
{
    $sql = "SELECT a.*, e.full_name, e.employee_code, COALESCE(d.department_name, e.department) AS department
        FROM attendance a
        INNER JOIN employees e ON e.id = a.employee_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE a.needs_verification = 1 AND a.status = :st";
    $params = ['st' => ATTENDANCE_STATUS_PENDING_VERIFICATION];
    if ($monthYyyyMm !== null && preg_match('/^\d{4}-\d{2}$/', $monthYyyyMm)) {
        $sql .= " AND DATE_FORMAT(a.attendance_date, '%Y-%m') = :m";
        $params['m'] = $monthYyyyMm;
    }
    $sql .= ' ORDER BY a.attendance_date DESC, a.id DESC LIMIT ' . max(1, min(200, $limit));
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function attendance_hr_resolve_verification(PDO $pdo, int $attendanceId, string $newStatus, int $hrUserId, string $notes = ''): void
{
    $allowed = ['Present', 'Half Day', 'Late', 'Absent', ATTENDANCE_STATUS_PENDING_VERIFICATION];
    if (!in_array($newStatus, $allowed, true)) {
        throw new InvalidArgumentException('Invalid verification status.');
    }
    $remarks = trim($notes) !== '' ? trim($notes) : 'HR verified';
    $st = $pdo->prepare('UPDATE attendance SET status = :st, needs_verification = 0, verified_by = :vb, verified_at = NOW(), remarks = CONCAT(COALESCE(remarks, \'\'), CASE WHEN remarks IS NULL OR remarks = \'\' THEN :rm ELSE CONCAT(\'; \', :rm2) END) WHERE id = :id AND needs_verification = 1');
    $st->execute([
        'st' => $newStatus,
        'vb' => $hrUserId > 0 ? $hrUserId : null,
        'rm' => $remarks,
        'rm2' => $remarks,
        'id' => $attendanceId,
    ]);
    if ($st->rowCount() === 0) {
        throw new RuntimeException('Record not found or already verified.');
    }
}

function attendance_status_badge_class(string $status): string
{
    return match ($status) {
        'Present' => 'emp-att--present',
        'Half Day' => 'emp-att--half',
        'Late' => 'emp-att--late',
        'Absent' => 'emp-att--absent',
        'Paid Leave', 'Leave' => 'emp-att--leave',
        'Unpaid Leave' => 'emp-att--unpaid',
        'Holiday' => 'emp-att--holiday',
        'Emergency Duty' => 'emp-att--duty',
        ATTENDANCE_STATUS_PENDING_VERIFICATION => 'emp-att--pending',
        ATTENDANCE_STATUS_IN_PROGRESS => 'emp-att--progress',
        default => 'emp-att--default',
    };
}
