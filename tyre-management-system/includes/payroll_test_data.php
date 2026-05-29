<?php
declare(strict_types=1);

require_once __DIR__ . '/attendance_workflow.php';
require_once __DIR__ . '/payroll_service.php';

const PAYROLL_TEST_REMARK = '[PAYROLL_TEST]';

/** Dev-only payroll test utilities (localhost / APP_ENV local|development + HR admin roles). */
function is_payroll_test_tools_enabled(): bool
{
    if (!function_exists('has_role') || !has_role(['Super Admin', 'HR Manager', 'Admin'])) {
        return false;
    }

    if (defined('APP_ENV') && in_array((string)APP_ENV, ['local', 'development', 'dev'], true)) {
        return true;
    }

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));

    return $host === 'localhost'
        || $host === '127.0.0.1'
        || $host === '::1'
        || str_starts_with($host, 'localhost:')
        || str_starts_with($host, '127.0.0.1:');
}

/**
 * @return list<string> Y-m-d dates (Mon–Sat) in month
 */
function payroll_test_working_dates(string $month): array
{
    $start = strtotime($month . '-01');
    if ($start === false) {
        return [];
    }
    $daysInMonth = (int)date('t', $start);
    $dates = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $ymd = sprintf('%s-%02d', $month, $d);
        $dow = (int)date('w', strtotime($ymd));
        if ($dow === 0) {
            continue;
        }
        $dates[] = $ymd;
    }

    return $dates;
}

/** @return array{present:int,half_days:int,absent:int,ot_hours:float,late:int} */
function payroll_test_default_counts(): array
{
    return [
        'present' => 22,
        'half_days' => 2,
        'absent' => 1,
        'ot_hours' => 10.0,
        'late' => 3,
    ];
}

function payroll_test_count_rows(PDO $pdo, int $employeeId, string $month): int
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM attendance
        WHERE employee_id = :eid
          AND DATE_FORMAT(attendance_date, '%Y-%m') = :m
          AND remarks LIKE :mark");
    $st->execute(['eid' => $employeeId, 'm' => $month, 'mark' => PAYROLL_TEST_REMARK . '%']);

    return (int)$st->fetchColumn();
}

/**
 * @return array{deleted:int}
 */
function payroll_test_clear(PDO $pdo, int $employeeId, string $month, bool $clearPayroll = false): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new InvalidArgumentException('Invalid month.');
    }

    $del = $pdo->prepare("DELETE FROM attendance
        WHERE employee_id = :eid
          AND DATE_FORMAT(attendance_date, '%Y-%m') = :m
          AND remarks LIKE :mark");
    $del->execute(['eid' => $employeeId, 'm' => $month, 'mark' => PAYROLL_TEST_REMARK . '%']);
    $deleted = $del->rowCount();

    $payrollDeleted = 0;
    if ($clearPayroll && $employeeId > 0) {
        $ps = $pdo->prepare('DELETE FROM salaries WHERE employee_id = :eid AND month_year = :m');
        $ps->execute(['eid' => $employeeId, 'm' => $month]);
        $payrollDeleted = $ps->rowCount();
    }

    return ['deleted' => $deleted, 'payroll_deleted' => $payrollDeleted];
}

/**
 * @param array{present?:int,half_days?:int,absent?:int,ot_hours?:float,late?:int,regenerate?:bool,clear_payroll?:bool} $options
 * @return array{created:int,summary:array<string,float|int>,dates_used:int}
 */
function payroll_test_generate(PDO $pdo, int $employeeId, string $month, array $options = []): array
{
    if ($employeeId < 1 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new InvalidArgumentException('Invalid employee or month.');
    }

    $defaults = payroll_test_default_counts();
    $present = max(0, (int)($options['present'] ?? $defaults['present']));
    $halfDays = max(0, (int)($options['half_days'] ?? $defaults['half_days']));
    $absent = max(0, (int)($options['absent'] ?? $defaults['absent']));
    $late = max(0, (int)($options['late'] ?? $defaults['late']));
    $otHours = max(0, round((float)($options['ot_hours'] ?? $defaults['ot_hours']), 2));
    $regenerate = !empty($options['regenerate']);
    $clearPayroll = !empty($options['clear_payroll']);

    $empStmt = $pdo->prepare("SELECT * FROM employees WHERE id = :id AND LOWER(COALESCE(status, '')) = 'active' LIMIT 1");
    $empStmt->execute(['id' => $employeeId]);
    $emp = $empStmt->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        throw new RuntimeException('Active employee not found.');
    }

    if ($regenerate) {
        payroll_test_clear($pdo, $employeeId, $month, $clearPayroll);
    }

    $needed = $present + $halfDays + $absent;
    $dates = payroll_test_working_dates($month);
    if (count($dates) < $needed) {
        throw new RuntimeException(
            'Not enough working days in ' . $month . " (need {$needed}, have " . count($dates) . '). Reduce counts or pick another month.'
        );
    }

    $useDates = array_slice($dates, 0, $needed);
    $absentDates = array_splice($useDates, 0, $absent);
    $halfDates = array_splice($useDates, 0, $halfDays);
    $presentDates = $useDates;

    if ($late > count($presentDates)) {
        $late = count($presentDates);
    }
    $lateDates = array_slice($presentDates, 0, $late);
    $lateSet = array_fill_keys($lateDates, true);

    $otPerDay = 0.0;
    $otDays = [];
    $plainPresent = array_values(array_filter($presentDates, static fn (string $d) => !isset($lateSet[$d])));
    if ($otHours > 0 && $plainPresent !== []) {
        $otDays = array_slice($plainPresent, -min(5, count($plainPresent)));
        $otPerDay = round($otHours / count($otDays), 2);
        $remainder = round($otHours - ($otPerDay * count($otDays)), 2);
        if ($remainder !== 0.0 && $otDays !== []) {
            $otPerDay = round($otPerDay + $remainder, 2);
        }
    }

    $shift = employee_shift_enum($emp);
    [$shiftIn, $shiftOut] = employee_shift_clock_bounds($emp);
    $schedH = employee_scheduled_shift_hours($emp);

    $ins = $pdo->prepare('INSERT INTO attendance (employee_id, attendance_date, shift, status, remarks, punch_in_time, punch_out_time, total_hours, overtime_hours, is_late, is_early_exit, is_emergency_duty)
        VALUES (:e,:d,:sh,:st,:rm,:pi,:po,:th,:oh,:il,0,0)
        ON DUPLICATE KEY UPDATE shift=VALUES(shift), status=VALUES(status), remarks=VALUES(remarks), punch_in_time=VALUES(punch_in_time), punch_out_time=VALUES(punch_out_time), total_hours=VALUES(total_hours), overtime_hours=VALUES(overtime_hours), is_late=VALUES(is_late), is_early_exit=0, is_emergency_duty=0');

    $created = 0;
    $remark = PAYROLL_TEST_REMARK . ' Auto-generated for payroll testing';

    foreach ($absentDates as $date) {
        $ins->execute([
            'e' => $employeeId,
            'd' => $date,
            'sh' => $shift,
            'st' => 'Absent',
            'rm' => $remark,
            'pi' => null,
            'po' => null,
            'th' => 0,
            'oh' => 0,
            'il' => 0,
        ]);
        $created++;
    }

    foreach ($halfDates as $date) {
        $in = substr($shiftIn, 0, 5);
        $mid = '13:00';
        [$pi, $po] = hr_build_punch_datetimes($date, $in, $mid);
        $ins->execute([
            'e' => $employeeId,
            'd' => $date,
            'sh' => $shift,
            'st' => 'Half Day',
            'rm' => $remark,
            'pi' => $pi,
            'po' => $po,
            'th' => round($schedH / 2, 2),
            'oh' => 0,
            'il' => 0,
        ]);
        $created++;
    }

    foreach ($presentDates as $date) {
        $isLate = isset($lateSet[$date]);
        $status = $isLate ? 'Late' : 'Present';
        $inTime = $isLate ? '09:25' : substr($shiftIn, 0, 5);
        $outTime = substr($shiftOut, 0, 5);
        [$pi, $po] = hr_build_punch_datetimes($date, $inTime, $outTime);
        $dayOt = in_array($date, $otDays, true) ? $otPerDay : 0.0;
        $totalH = round($schedH + $dayOt, 2);
        $ins->execute([
            'e' => $employeeId,
            'd' => $date,
            'sh' => $shift,
            'st' => $status,
            'rm' => $remark,
            'pi' => $pi,
            'po' => $po,
            'th' => $totalH,
            'oh' => $dayOt,
            'il' => $isLate ? 1 : 0,
        ]);
        $created++;
    }

    $summary = payroll_fetch_attendance_summary($pdo, $employeeId, $month);

    return [
        'created' => $created,
        'summary' => $summary,
        'dates_used' => $needed,
    ];
}

/**
 * @param array<string, mixed> $options
 * @return array{employees:int,created:int,errors:list<string>}
 */
function payroll_test_generate_all_active(PDO $pdo, string $month, array $options = []): array
{
    $ids = $pdo->query("SELECT id FROM employees WHERE LOWER(COALESCE(status, '')) = 'active' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $employees = 0;
    $created = 0;
    $errors = [];
    foreach ($ids as $id) {
        $eid = (int)$id;
        if ($eid < 1) {
            continue;
        }
        try {
            $opts = $options;
            $opts['regenerate'] = !empty($options['regenerate']);
            $res = payroll_test_generate($pdo, $eid, $month, $opts);
            $employees++;
            $created += $res['created'];
        } catch (Throwable $ex) {
            $errors[] = 'Employee #' . $eid . ': ' . $ex->getMessage();
        }
    }

    return ['employees' => $employees, 'created' => $created, 'errors' => $errors];
}

/**
 * @return array{employees:int,deleted:int,payroll_deleted:int}
 */
function payroll_test_clear_all(PDO $pdo, string $month, bool $clearPayroll = false): array
{
    $st = $pdo->prepare("SELECT DISTINCT employee_id FROM attendance
        WHERE DATE_FORMAT(attendance_date, '%Y-%m') = :m AND remarks LIKE :mark");
    $st->execute(['m' => $month, 'mark' => PAYROLL_TEST_REMARK . '%']);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    $deleted = 0;
    $payrollDeleted = 0;
    foreach ($ids as $id) {
        $r = payroll_test_clear($pdo, (int)$id, $month, $clearPayroll);
        $deleted += $r['deleted'];
        $payrollDeleted += $r['payroll_deleted'];
    }

    return ['employees' => count($ids), 'deleted' => $deleted, 'payroll_deleted' => $payrollDeleted];
}

/** @param array<string, mixed> $notice */
function payroll_test_set_notice(array $notice): void
{
    ensure_session_started();
    $_SESSION['payroll_test_notice'] = $notice;
}

/** @return array<string, mixed>|null */
function payroll_test_take_notice(): ?array
{
    ensure_session_started();
    $notice = $_SESSION['payroll_test_notice'] ?? null;
    unset($_SESSION['payroll_test_notice']);

    return is_array($notice) ? $notice : null;
}

/**
 * @param array<string, mixed> $options
 * @return array<string, mixed>
 */
function payroll_test_generate_with_payroll(PDO $pdo, int $employeeId, string $month, array $options = []): array
{
    $attRes = payroll_test_generate($pdo, $employeeId, $month, $options);

    $empStmt = $pdo->prepare('SELECT * FROM employees WHERE id = :id LIMIT 1');
    $empStmt->execute(['id' => $employeeId]);
    $emp = $empStmt->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        throw new RuntimeException('Employee not found.');
    }

    $calc = payroll_build_calculation($pdo, $emp, $month);
    payroll_save_record($pdo, $employeeId, $month, $calc, false);
    $salaryId = payroll_get_salary_id($pdo, $employeeId, $month);

    return [
        'attendance' => $attRes,
        'calc' => $calc,
        'salary_id' => $salaryId,
        'employee_id' => $employeeId,
        'employee_name' => (string)$emp['full_name'],
        'employee_code' => (string)$emp['employee_code'],
        'month' => $month,
    ];
}

/**
 * @param array<string, mixed> $options
 * @return array{employees:int,payrolls:int,errors:list<string>}
 */
function payroll_test_generate_all_with_payroll(PDO $pdo, string $month, array $options = []): array
{
    $ids = $pdo->query("SELECT id FROM employees WHERE LOWER(COALESCE(status, '')) = 'active' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $employees = 0;
    $payrolls = 0;
    $errors = [];
    foreach ($ids as $id) {
        $eid = (int)$id;
        if ($eid < 1) {
            continue;
        }
        try {
            $opts = $options;
            $opts['regenerate'] = !empty($options['regenerate']);
            payroll_test_generate_with_payroll($pdo, $eid, $month, $opts);
            $employees++;
            $payrolls++;
        } catch (Throwable $ex) {
            $errors[] = '#' . $eid . ': ' . $ex->getMessage();
        }
    }

    return ['employees' => $employees, 'payrolls' => $payrolls, 'errors' => $errors];
}

/** @param array<string, mixed> $calc */
function payroll_test_notice_from_calc(array $calc, int $employeeId, string $employeeName, string $employeeCode, string $month, int $salaryId): array
{
    return [
        'step' => 'payroll',
        'employee_id' => $employeeId,
        'employee_name' => $employeeName,
        'employee_code' => $employeeCode,
        'month' => $month,
        'salary_id' => $salaryId,
        'gross_salary' => (float)($calc['gross_salary'] ?? 0),
        'total_deduction' => (float)($calc['total_deduction'] ?? 0),
        'net_salary' => (float)($calc['net_salary'] ?? 0),
        'overtime_amount' => (float)($calc['overtime_amount'] ?? 0),
        'pf_amount' => (float)($calc['pf_amount'] ?? 0),
        'esi_employee_amount' => (float)($calc['esi_employee_amount'] ?? 0),
        'basic' => (float)($calc['basic'] ?? 0),
        'present_days' => (float)($calc['present_days'] ?? 0),
        'overtime_hours' => (float)($calc['overtime_hours'] ?? 0),
    ];
}
