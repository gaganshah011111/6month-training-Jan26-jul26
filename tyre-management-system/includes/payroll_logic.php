<?php
declare(strict_types=1);

require_once __DIR__ . '/indian_payroll.php';

function normalize_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return (int)$value === 1;
    }

    return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
}

function compute_work_hours(?string $punchIn, ?string $punchOut): float
{
    if (!$punchIn || !$punchOut) {
        return 0.0;
    }
    $in = strtotime($punchIn);
    $out = strtotime($punchOut);
    if ($in === false || $out === false || $out <= $in) {
        return 0.0;
    }

    return round(($out - $in) / 3600, 2);
}

/**
 * Resolved HRA amount for an employee row (explicit hra_amount, else basic × HRA %).
 */
function employee_hra_amount_value(array $employee): float
{
    $basic = (float)($employee['basic_salary'] ?? 0);
    $hraPercent = (float)($employee['hra_percentage'] ?? 0);

    return (float)($employee['hra_amount'] ?? ($basic * $hraPercent / 100));
}

/**
 * Fixed monthly gross earnings (no overtime, no PF/ESI): Basic + DA + HRA + Medical + Travel + Special + Other.
 */
function employee_fixed_gross_monthly(array $employee): float
{
    if ((float)($employee['gross_salary'] ?? 0) > 0 && employee_payroll_auto_indian($employee)) {
        return round((float)$employee['gross_salary'], 2);
    }

    $basic = (float)($employee['basic_salary'] ?? 0);
    $da = (float)($employee['dearness_allowance'] ?? 0);
    $hraAmount = employee_hra_amount_value($employee);
    $medical = (float)($employee['medical_allowance'] ?? 0);
    $travel = (float)($employee['travel_allowance'] ?? 0);
    $special = (float)($employee['special_allowance'] ?? 0);
    $other = (float)($employee['other_allowances'] ?? 0);

    return round($basic + $da + $hraAmount + $medical + $travel + $special + $other, 2);
}

/** @deprecated Use employee_fixed_gross_monthly() */
function employee_monthly_ctc(array $employee): float
{
    return employee_fixed_gross_monthly($employee);
}

function employee_sync_gross_salary(PDO $pdo, int $employeeId): void
{
    $st = $pdo->prepare('SELECT * FROM employees WHERE id = :id LIMIT 1');
    $st->execute(['id' => $employeeId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }

    if (employee_payroll_auto_indian($row) && (float)($row['gross_salary'] ?? 0) > 0) {
        indian_apply_components_to_employee($pdo, $employeeId);

        return;
    }

    $g = employee_fixed_gross_monthly($row);
    $pdo->prepare('UPDATE employees SET gross_salary = :g WHERE id = :id')->execute(['g' => $g, 'id' => $employeeId]);
}

/**
 * @return array<string, float|int|string>
 */
function calculate_payroll_breakdown(array $employee, array $attendance, array $leaveSummary, string $month, ?array $payrollSettings = null): array
{
    $ps = $payrollSettings ?? payroll_settings_defaults();
    $daysInMonth = max(1, (int)date('t', strtotime($month . '-01')));
    $employeeType = (string)($employee['employee_type'] ?? 'Staff');
    $basic = (float)($employee['basic_salary'] ?? 0);
    $da = (float)($employee['dearness_allowance'] ?? 0);
    $hraPercent = (float)($employee['hra_percentage'] ?? 0);
    $hraAmount = employee_hra_amount_value($employee);
    $medicalAllowance = (float)($employee['medical_allowance'] ?? 0);
    $travelAllowance = (float)($employee['travel_allowance'] ?? 0);
    $specialAllowance = (float)($employee['special_allowance'] ?? 0);
    $otherAllowances = (float)($employee['other_allowances'] ?? 0);

    $fixedGross = (float)($employee['gross_salary'] ?? 0) > 0 && employee_payroll_auto_indian($employee)
        ? (float)$employee['gross_salary']
        : round($basic + $da + $hraAmount + $medicalAllowance + $travelAllowance + $specialAllowance + $otherAllowances, 2);

    $wdDefault = max(1.0, (float)($ps['working_days_default'] ?? 26));
    $dailyWage = (float)($employee['daily_wage'] ?? 0);
    if ($dailyWage <= 0 && $fixedGross > 0) {
        $dailyWage = round($fixedGross / $wdDefault, 2);
    }
    if ($dailyWage <= 0 && $daysInMonth > 0) {
        $dailyWage = round($basic / $daysInMonth, 2);
    }

    $hourlyRate = (float)($employee['hourly_rate'] ?? 0);
    if ($hourlyRate <= 0 && $dailyWage > 0) {
        $sh = max(0.5, (float)($ps['shift_hours_default'] ?? 8));
        $hourlyRate = round($dailyWage / $sh, 2);
    }

    $overtimeRate = (float)($employee['overtime_rate'] ?? 0);
    if ($overtimeRate <= 0) {
        $overtimeRate = round($hourlyRate * max(0.5, (float)($ps['ot_multiplier'] ?? 1)), 2);
    }

    $presentDays = (float)($attendance['present_days'] ?? 0);
    $halfDays = (float)($attendance['half_days'] ?? 0);
    $lateDays = (float)($attendance['late_days'] ?? 0);
    $overtimeHours = (float)($attendance['overtime_hours'] ?? 0);

    $paidLeaveDays = (float)($leaveSummary['paid_leave_days'] ?? 0);
    $halfPaidLeaveDays = (float)($leaveSummary['half_paid_leave_days'] ?? 0);
    $unpaidLeaveDays = (float)($leaveSummary['unpaid_leave_days'] ?? 0);

    $paidLeaveLimit = (float)($employee['paid_leave_limit'] ?? 0);
    $halfPaidLeaveLimit = (float)($employee['half_paid_leave_limit'] ?? 0);

    $excessPaidLeaveDays = max(0, $paidLeaveDays - $paidLeaveLimit);
    $excessHalfPaidLeaveDays = max(0, $halfPaidLeaveDays - $halfPaidLeaveLimit);

    $overtimeAmount = $overtimeHours * $overtimeRate;
    $grossSalary = $basic + $da + $hraAmount + $medicalAllowance + $travelAllowance + $specialAllowance + $otherAllowances + $overtimeAmount;

    $latePct = (float)($ps['late_deduction_pct_of_daily'] ?? 10);
    $lateEntryDeduction = $lateDays * ($dailyWage * $latePct / 100);
    $halfDayDeduction = ($halfDays * 0.5 * $dailyWage) + ($halfPaidLeaveDays * 0.5 * $dailyWage) + ($excessHalfPaidLeaveDays * 0.5 * $dailyWage);
    $leaveDeduction = ($unpaidLeaveDays * $dailyWage) + ($excessPaidLeaveDays * $dailyWage);

    if ($employeeType === 'Worker') {
        $unpaidCalendarGap = max(0, (float)$daysInMonth - (float)$presentDays);
        $leaveDeduction += $unpaidCalendarGap * $dailyWage;
        $paidLeaveDays = 0;
        $halfPaidLeaveDays = 0;
    }

    $pfApplicable = normalize_bool($employee['pf_applicable'] ?? 1);
    $pfPercent = (float)($employee['pf_percentage'] ?? (float)($ps['pf_employee_pct'] ?? 12));
    $pfAmount = $pfApplicable ? round($basic * $pfPercent / 100, 2) : 0.0;

    $pfEmployerPct = (float)($ps['pf_employer_pct'] ?? 12);
    $pfEmployerAmount = $pfApplicable ? round($basic * $pfEmployerPct / 100, 2) : 0.0;

    $esiApplicableHr = normalize_bool($employee['esi_applicable'] ?? 1);
    $esiPercentEmployee = (float)($employee['esi_percentage'] ?? (float)($ps['esi_employee_pct'] ?? 0.75));
    $esiPercentEmployer = (float)($ps['esi_employer_pct'] ?? 3.25);
    $esiLimit = (float)($employee['esi_salary_limit'] ?? (float)($ps['esi_gross_limit'] ?? 21000));
    $esiEnabled = $esiApplicableHr && $grossSalary <= $esiLimit;
    $esiEmployeeAmount = $esiEnabled ? round($grossSalary * $esiPercentEmployee / 100, 2) : 0.0;
    $esiEmployerAmount = $esiEnabled ? round($grossSalary * $esiPercentEmployer / 100, 2) : 0.0;

    $taxDeduction = 0.0;
    $gratuityAccrual = round((float)($employee['gratuity_monthly'] ?? 0), 2);

    $statutoryDeductions = $pfAmount + $esiEmployeeAmount + $leaveDeduction + $halfDayDeduction + $lateEntryDeduction + $taxDeduction;
    $totalDeduction = $statutoryDeductions;
    $netSalary = max(0, round($grossSalary - $totalDeduction, 2));

    return [
        'days_in_month' => $daysInMonth,
        'present_days' => $presentDays,
        'half_days' => $halfDays,
        'late_days' => $lateDays,
        'paid_leave_days' => $paidLeaveDays,
        'half_paid_leave_days' => $halfPaidLeaveDays,
        'unpaid_leave_days' => $unpaidLeaveDays,
        'overtime_hours' => $overtimeHours,
        'overtime_rate' => $overtimeRate,
        'overtime_amount' => $overtimeAmount,
        'basic' => $basic,
        'dearness_allowance' => $da,
        'hra_percentage' => $hraPercent,
        'hra_amount' => $hraAmount,
        'medical_allowance' => $medicalAllowance,
        'travel_allowance' => $travelAllowance,
        'special_allowance' => $specialAllowance,
        'other_allowances' => $otherAllowances,
        'pf_percentage' => $pfPercent,
        'pf_amount' => $pfAmount,
        'pf_employer_amount' => $pfEmployerAmount,
        'esi_employee_percentage' => $esiPercentEmployee,
        'esi_employee_amount' => $esiEmployeeAmount,
        'esi_employer_percentage' => $esiPercentEmployer,
        'esi_employer_amount' => $esiEmployerAmount,
        'tax_deduction' => $taxDeduction,
        'gratuity_accrual' => $gratuityAccrual,
        'leave_deduction' => $leaveDeduction,
        'half_day_deduction' => $halfDayDeduction,
        'late_entry_deduction' => $lateEntryDeduction,
        'gross_salary' => $grossSalary,
        'total_deduction' => $totalDeduction,
        'net_salary' => $netSalary,
    ];
}
