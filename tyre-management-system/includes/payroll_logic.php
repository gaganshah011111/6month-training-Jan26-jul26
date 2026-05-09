<?php
declare(strict_types=1);

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

function calculate_payroll_breakdown(array $employee, array $attendance, array $leaveSummary, string $month): array
{
    $daysInMonth = max(1, (int)date('t', strtotime($month . '-01')));
    $employeeType = (string)($employee['employee_type'] ?? 'Staff');
    $basic = (float)($employee['basic_salary'] ?? 0);

    $hraPercent = (float)($employee['hra_percentage'] ?? 0);
    $hraAmount = (float)($employee['hra_amount'] ?? ($basic * $hraPercent / 100));
    $medicalAllowance = (float)($employee['medical_allowance'] ?? 0);
    $otherAllowances = (float)($employee['other_allowances'] ?? 0);
    $dailyWage = (float)($employee['daily_wage'] ?? ($daysInMonth > 0 ? $basic / $daysInMonth : 0));
    $hourlyRate = (float)($employee['hourly_rate'] ?? ($dailyWage / 8));
    $overtimeRate = (float)($employee['overtime_rate'] ?? $hourlyRate);

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
    $grossSalary = $basic + $hraAmount + $medicalAllowance + $otherAllowances + $overtimeAmount;

    $lateEntryDeduction = $lateDays * ($dailyWage * 0.1);
    $halfDayDeduction = ($halfDays * 0.5 * $dailyWage) + ($halfPaidLeaveDays * 0.5 * $dailyWage) + ($excessHalfPaidLeaveDays * 0.5 * $dailyWage);
    $leaveDeduction = ($unpaidLeaveDays * $dailyWage) + ($excessPaidLeaveDays * $dailyWage);

    if ($employeeType === 'Worker') {
        // present_days from attendance is already a weighted paid-day total (incl. half-days as 0.5, paid leave, holiday).
        $unpaidCalendarGap = max(0, (float)$daysInMonth - (float)$presentDays);
        $leaveDeduction += $unpaidCalendarGap * $dailyWage;
        $paidLeaveDays = 0;
        $halfPaidLeaveDays = 0;
    }

    $pfApplicable = normalize_bool($employee['pf_applicable'] ?? 1);
    $pfPercent = (float)($employee['pf_percentage'] ?? 12);
    $pfAmount = $pfApplicable ? ($basic * $pfPercent / 100) : 0;

    $esiApplicable = normalize_bool($employee['esi_applicable'] ?? 1);
    $esiPercentEmployee = (float)($employee['esi_percentage'] ?? 0.75);
    $esiPercentEmployer = 3.25;
    $esiLimit = (float)($employee['esi_salary_limit'] ?? 21000);
    $esiEnabled = $esiApplicable && $grossSalary <= $esiLimit;
    $esiEmployeeAmount = $esiEnabled ? ($grossSalary * $esiPercentEmployee / 100) : 0;
    $esiEmployerAmount = $esiEnabled ? ($grossSalary * $esiPercentEmployer / 100) : 0;

    $totalDeduction = $pfAmount + $esiEmployeeAmount + $leaveDeduction + $halfDayDeduction + $lateEntryDeduction;
    $netSalary = max(0, $grossSalary - $totalDeduction);

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
        'hra_percentage' => $hraPercent,
        'hra_amount' => $hraAmount,
        'medical_allowance' => $medicalAllowance,
        'other_allowances' => $otherAllowances,
        'pf_percentage' => $pfPercent,
        'pf_amount' => $pfAmount,
        'esi_employee_percentage' => $esiPercentEmployee,
        'esi_employee_amount' => $esiEmployeeAmount,
        'esi_employer_percentage' => $esiPercentEmployer,
        'esi_employer_amount' => $esiEmployerAmount,
        'leave_deduction' => $leaveDeduction,
        'half_day_deduction' => $halfDayDeduction,
        'late_entry_deduction' => $lateEntryDeduction,
        'gross_salary' => $grossSalary,
        'total_deduction' => $totalDeduction,
        'net_salary' => $netSalary,
    ];
}
