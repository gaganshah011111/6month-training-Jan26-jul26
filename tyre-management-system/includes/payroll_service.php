<?php
declare(strict_types=1);

require_once __DIR__ . '/payroll_logic.php';
require_once __DIR__ . '/functions.php';

/** @return array{present_days:float,half_days:float,late_days:float,absent_days:float,overtime_hours:float} */
function payroll_fetch_attendance_summary(PDO $pdo, int $employeeId, string $month): array
{
    $st = $pdo->prepare("SELECT
            SUM(CASE WHEN status IN ('Present','Late','Emergency Duty') THEN 1 WHEN status='Half Day' THEN 0.5 WHEN status IN ('Paid Leave','Holiday') THEN 1 ELSE 0 END) AS present_days,
            SUM(CASE WHEN status='Half Day' THEN 1 ELSE 0 END) AS half_days,
            SUM(CASE WHEN status='Late' OR is_late = 1 THEN 1 ELSE 0 END) AS late_days,
            SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) AS absent_days,
            COALESCE(SUM(overtime_hours),0) AS overtime_hours
        FROM attendance
        WHERE employee_id=:eid AND DATE_FORMAT(attendance_date, '%Y-%m')=:month");
    $st->execute(['eid' => $employeeId, 'month' => $month]);

    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'present_days' => (float)($row['present_days'] ?? 0),
        'half_days' => (float)($row['half_days'] ?? 0),
        'late_days' => (float)($row['late_days'] ?? 0),
        'absent_days' => (float)($row['absent_days'] ?? 0),
        'overtime_hours' => (float)($row['overtime_hours'] ?? 0),
    ];
}

/** @return array{paid_leave_days:float,half_paid_leave_days:float,unpaid_leave_days:float} */
function payroll_fetch_leave_summary(PDO $pdo, int $employeeId, string $month): array
{
    $st = $pdo->prepare("SELECT
            SUM(CASE WHEN status='Approved' THEN
                CASE WHEN COALESCE(paid_days, 0) > 0 THEN paid_days
                WHEN COALESCE(leave_category,'Paid')='Paid' THEN DATEDIFF(COALESCE(to_date,end_date), COALESCE(from_date,start_date))+1
                ELSE 0 END
            ELSE 0 END) AS paid_leave_days,
            SUM(CASE WHEN status='Approved' THEN
                CASE WHEN COALESCE(half_paid_days, 0) > 0 THEN half_paid_days
                WHEN COALESCE(leave_category,'')='Half Paid' THEN DATEDIFF(COALESCE(to_date,end_date), COALESCE(from_date,start_date))+1
                ELSE 0 END
            ELSE 0 END) AS half_paid_leave_days,
            SUM(CASE WHEN status='Approved' THEN
                CASE WHEN COALESCE(unpaid_days, 0) > 0 THEN unpaid_days
                WHEN COALESCE(leave_category,'Unpaid')='Unpaid' OR is_paid=0 THEN DATEDIFF(COALESCE(to_date,end_date), COALESCE(from_date,start_date))+1
                ELSE 0 END
            ELSE 0 END) AS unpaid_leave_days
        FROM leaves
        WHERE employee_id=:eid AND DATE_FORMAT(COALESCE(from_date,start_date), '%Y-%m')<=:month_from AND DATE_FORMAT(COALESCE(to_date,end_date), '%Y-%m')>=:month_to");
    $st->execute(['eid' => $employeeId, 'month_from' => $month, 'month_to' => $month]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'paid_leave_days' => (float)($row['paid_leave_days'] ?? 0),
        'half_paid_leave_days' => (float)($row['half_paid_leave_days'] ?? 0),
        'unpaid_leave_days' => (float)($row['unpaid_leave_days'] ?? 0),
    ];
}

/**
 * @return array<string, mixed>
 */
function payroll_build_calculation(PDO $pdo, array $employee, string $month, float $extraOtHours = 0.0, float $manualDeduction = 0.0): array
{
    $att = payroll_fetch_attendance_summary($pdo, (int)$employee['id'], $month);
    $leave = payroll_fetch_leave_summary($pdo, (int)$employee['id'], $month);
    $attendanceData = [
        'present_days' => $att['present_days'],
        'half_days' => $att['half_days'],
        'late_days' => $att['late_days'],
        'overtime_hours' => $att['overtime_hours'] + $extraOtHours,
    ];
    $ps = payroll_settings_fetch($pdo);
    $calc = calculate_payroll_breakdown($employee, $attendanceData, $leave, $month, $ps);
    if ($manualDeduction > 0) {
        $calc['total_deduction'] = round((float)$calc['total_deduction'] + $manualDeduction, 2);
        $calc['net_salary'] = max(0, round((float)$calc['gross_salary'] - (float)$calc['total_deduction'], 2));
    }
    $calc['manual_deduction'] = $manualDeduction;
    $calc['attendance'] = $att;
    $calc['leave'] = $leave;
    $calc['fixed_gross_monthly'] = employee_fixed_gross_monthly($employee);

    return $calc;
}

/**
 * Estimate payroll from sample attendance counts (test modal preview; no DB writes).
 *
 * @param array{present?:int|float,half_days?:int|float,late?:int|float,ot_hours?:float} $sample
 * @return array<string, mixed>
 */
function payroll_build_calculation_synthetic(PDO $pdo, array $employee, string $month, array $sample, float $manualDeduction = 0.0): array
{
    $present = max(0, (float)($sample['present'] ?? 0));
    $halfDays = max(0, (float)($sample['half_days'] ?? 0));
    $lateDays = max(0, (float)($sample['late'] ?? 0));
    $otHours = max(0, round((float)($sample['ot_hours'] ?? 0), 2));

    $attendanceData = [
        'present_days' => $present + ($halfDays * 0.5),
        'half_days' => $halfDays,
        'late_days' => $lateDays,
        'overtime_hours' => $otHours,
    ];
    $leave = payroll_fetch_leave_summary($pdo, (int)$employee['id'], $month);
    $ps = payroll_settings_fetch($pdo);
    $calc = calculate_payroll_breakdown($employee, $attendanceData, $leave, $month, $ps);
    if ($manualDeduction > 0) {
        $calc['total_deduction'] = round((float)$calc['total_deduction'] + $manualDeduction, 2);
        $calc['net_salary'] = max(0, round((float)$calc['gross_salary'] - (float)$calc['total_deduction'], 2));
    }
    $calc['manual_deduction'] = $manualDeduction;
    $calc['attendance'] = [
        'present_days' => $attendanceData['present_days'],
        'half_days' => $halfDays,
        'late_days' => $lateDays,
        'absent_days' => max(0, (float)($sample['absent'] ?? 0)),
        'overtime_hours' => $otHours,
    ];
    $calc['leave'] = $leave;
    $calc['fixed_gross_monthly'] = employee_fixed_gross_monthly($employee);
    $calc['preview_synthetic'] = true;

    return $calc;
}

function payroll_save_record(PDO $pdo, int $employeeId, string $month, array $calc, bool $asDraft = false): void
{
    $manualDeduction = (float)($calc['manual_deduction'] ?? 0);
    $paymentStatus = $asDraft ? 'unpaid' : 'unpaid';
    $isDraft = $asDraft ? 1 : 0;

    $stmt = $pdo->prepare('INSERT INTO salaries(employee_id,month_year,present_days,paid_leave_days,half_paid_leave_days,unpaid_leave_days,overtime_hours,overtime_amount,deductions,basic,dearness_allowance,travel_allowance,hra_percentage,hra_amount,pf_percentage,pf_amount,pf_employer_amount,esi_employee_percentage,esi_employee_amount,esi_employer_percentage,esi_employer_amount,medical_allowance,special_allowance,other_allowances,tax_deduction,gratuity_accrual,leave_deduction,half_day_deduction,late_entry_deduction,gross_salary,total_deduction,net_salary,payment_status,is_draft) VALUES(:e,:m,:pd,:pl,:hpl,:ul,:oh,:oa,:d,:b,:da,:ta,:hrp,:hra,:pfp,:pfa,:pfem,:esiep,:esiea,:esirp,:esira,:ma,:sp,:oa2,:tax,:gr,:ld,:hdd,:led,:gs,:td,:n,:ps,:dr) ON DUPLICATE KEY UPDATE present_days=VALUES(present_days), paid_leave_days=VALUES(paid_leave_days), half_paid_leave_days=VALUES(half_paid_leave_days), unpaid_leave_days=VALUES(unpaid_leave_days), overtime_hours=VALUES(overtime_hours), overtime_amount=VALUES(overtime_amount), deductions=VALUES(deductions), basic=VALUES(basic), dearness_allowance=VALUES(dearness_allowance), travel_allowance=VALUES(travel_allowance), hra_percentage=VALUES(hra_percentage), hra_amount=VALUES(hra_amount), pf_percentage=VALUES(pf_percentage), pf_amount=VALUES(pf_amount), pf_employer_amount=VALUES(pf_employer_amount), esi_employee_percentage=VALUES(esi_employee_percentage), esi_employee_amount=VALUES(esi_employee_amount), esi_employer_percentage=VALUES(esi_employer_percentage), esi_employer_amount=VALUES(esi_employer_amount), medical_allowance=VALUES(medical_allowance), special_allowance=VALUES(special_allowance), other_allowances=VALUES(other_allowances), tax_deduction=VALUES(tax_deduction), gratuity_accrual=VALUES(gratuity_accrual), leave_deduction=VALUES(leave_deduction), half_day_deduction=VALUES(half_day_deduction), late_entry_deduction=VALUES(late_entry_deduction), gross_salary=VALUES(gross_salary), total_deduction=VALUES(total_deduction), net_salary=VALUES(net_salary), payment_status=VALUES(payment_status), is_draft=VALUES(is_draft), generated_at=CURRENT_TIMESTAMP');
    $stmt->execute([
        'e' => $employeeId,
        'm' => $month,
        'pd' => $calc['present_days'],
        'pl' => $calc['paid_leave_days'],
        'hpl' => $calc['half_paid_leave_days'],
        'ul' => $calc['unpaid_leave_days'],
        'oh' => $calc['overtime_hours'],
        'oa' => $calc['overtime_amount'],
        'd' => $manualDeduction,
        'b' => $calc['basic'],
        'da' => $calc['dearness_allowance'],
        'ta' => $calc['travel_allowance'],
        'hrp' => $calc['hra_percentage'],
        'hra' => $calc['hra_amount'],
        'pfp' => $calc['pf_percentage'],
        'pfa' => $calc['pf_amount'],
        'pfem' => $calc['pf_employer_amount'],
        'esiep' => $calc['esi_employee_percentage'],
        'esiea' => $calc['esi_employee_amount'],
        'esirp' => $calc['esi_employer_percentage'],
        'esira' => $calc['esi_employer_amount'],
        'ma' => $calc['medical_allowance'],
        'sp' => $calc['special_allowance'],
        'oa2' => $calc['other_allowances'],
        'tax' => $calc['tax_deduction'],
        'gr' => $calc['gratuity_accrual'],
        'ld' => $calc['leave_deduction'],
        'hdd' => $calc['half_day_deduction'],
        'led' => $calc['late_entry_deduction'],
        'gs' => $calc['gross_salary'],
        'td' => $calc['total_deduction'],
        'n' => $calc['net_salary'],
        'ps' => $paymentStatus,
        'dr' => $isDraft,
    ]);

    $monthNum = (int)date('n', strtotime($month . '-01'));
    $yearNum = (int)date('Y', strtotime($month . '-01'));
    $payrollCompat = $pdo->prepare('INSERT INTO payroll(employee_id,month,year,present_days,paid_leave_days,unpaid_leave_days,overtime_amount,basic_salary,deduction,net_salary) VALUES(:e,:m,:y,:pd,:pl,:ul,:oa,:b,:d,:n) ON DUPLICATE KEY UPDATE present_days=VALUES(present_days), paid_leave_days=VALUES(paid_leave_days), unpaid_leave_days=VALUES(unpaid_leave_days), overtime_amount=VALUES(overtime_amount), basic_salary=VALUES(basic_salary), deduction=VALUES(deduction), net_salary=VALUES(net_salary)');
    $payrollCompat->execute([
        'e' => $employeeId,
        'm' => $monthNum,
        'y' => $yearNum,
        'pd' => $calc['present_days'],
        'pl' => $calc['paid_leave_days'],
        'ul' => $calc['unpaid_leave_days'],
        'oa' => $calc['overtime_amount'],
        'b' => $calc['gross_salary'],
        'd' => $calc['total_deduction'],
        'n' => $calc['net_salary'],
    ]);
}

function payroll_get_salary_id(PDO $pdo, int $employeeId, string $month): int
{
    $st = $pdo->prepare('SELECT id FROM salaries WHERE employee_id = :e AND month_year = :m LIMIT 1');
    $st->execute(['e' => $employeeId, 'm' => $month]);

    return (int)$st->fetchColumn();
}

function payroll_mark_paid(PDO $pdo, int $salaryId): bool
{
    $st = $pdo->prepare('UPDATE salaries SET payment_status = \'paid\', is_draft = 0, paid_at = COALESCE(paid_at, NOW()) WHERE id = :id');
    $st->execute(['id' => $salaryId]);

    return $st->rowCount() > 0;
}

/** UI status: pending | generated | draft | paid */
function payroll_row_status(?array $salaryRow): string
{
    if (!$salaryRow) {
        return 'pending';
    }
    if ((string)($salaryRow['payment_status'] ?? '') === 'paid') {
        return 'paid';
    }
    if (!empty($salaryRow['is_draft'])) {
        return 'draft';
    }

    return 'generated';
}

function payroll_status_badge(string $status): array
{
    return match ($status) {
        'paid' => ['label' => 'Paid', 'class' => 'payroll-badge payroll-badge--paid'],
        'draft' => ['label' => 'Draft', 'class' => 'payroll-badge payroll-badge--draft'],
        'generated' => ['label' => 'Payroll Generated', 'class' => 'payroll-badge payroll-badge--generated'],
        default => ['label' => 'Pending', 'class' => 'payroll-badge payroll-badge--pending'],
    };
}

function payroll_format_month_label(string $month): string
{
    $ts = strtotime($month . '-01');

    return $ts ? date('F Y', $ts) : $month;
}

/** @return list<string> */
function payroll_department_filter_options(PDO $pdo): array
{
    $preferred = [
        'Production',
        'Quality',
        'Quality Assurance',
        'Dispatch',
        'Maintenance',
        'HR',
        'Human Resources',
        'Administration',
        'Logistics',
        'IT',
        'Accounts',
        'Production Planning & Control (PPC)',
        'Tire Building / Product Assembly',
        'Mixing & Compounding',
    ];
    $deptExpr = erp_dept_label_sql('d', 'e');
    $fromDb = $pdo->query("SELECT DISTINCT TRIM({$deptExpr}) AS dept
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE CHAR_LENGTH(TRIM({$deptExpr})) > 0
        ORDER BY dept")->fetchAll(PDO::FETCH_COLUMN);
    $merged = [];
    foreach (array_merge($preferred, $fromDb ?: []) as $label) {
        $label = trim((string)$label);
        if ($label !== '' && !in_array($label, $merged, true)) {
            $merged[] = $label;
        }
    }

    return $merged;
}

/**
 * @return array{
 *   total_employees:int,generated:int,pending:int,total_expense:float,total_ot:float,on_leave:int
 * }
 */
function payroll_dashboard_summary(PDO $pdo, string $month): array
{
    $totalEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
    $genStmt = $pdo->prepare('SELECT COUNT(*) FROM salaries WHERE month_year = :m AND is_draft = 0');
    $genStmt->execute(['m' => $month]);
    $generated = (int)$genStmt->fetchColumn();
    $expStmt = $pdo->prepare('SELECT COALESCE(SUM(net_salary),0), COALESCE(SUM(overtime_amount),0) FROM salaries WHERE month_year = :m AND is_draft = 0');
    $expStmt->execute(['m' => $month]);
    $expRow = $expStmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
    $leaveStmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM leaves WHERE status='Approved' AND DATE_FORMAT(COALESCE(from_date,start_date), '%Y-%m')<=:m_from AND DATE_FORMAT(COALESCE(to_date,end_date), '%Y-%m')>=:m_to");
    $leaveStmt->execute(['m_from' => $month, 'm_to' => $month]);
    $onLeave = (int)$leaveStmt->fetchColumn();

    return [
        'total_employees' => $totalEmployees,
        'generated' => $generated,
        'pending' => max(0, $totalEmployees - $generated),
        'total_expense' => (float)($expRow[0] ?? 0),
        'total_ot' => (float)($expRow[1] ?? 0),
        'on_leave' => $onLeave,
    ];
}

/**
 * @param array<string, string> $filters
 * @return list<array<string, mixed>>
 */
function payroll_list_employees(PDO $pdo, string $month, array $filters = []): array
{
    $where = ["e.status = 'active'"];
    $params = ['month' => $month];

    if (($filters['employee_code'] ?? '') !== '') {
        $where[] = 'e.employee_code LIKE :code';
        $params['code'] = '%' . $filters['employee_code'] . '%';
    }
    if (($filters['employee_name'] ?? '') !== '') {
        $where[] = 'e.full_name LIKE :name';
        $params['name'] = '%' . $filters['employee_name'] . '%';
    }
    if (($filters['department'] ?? '') !== '') {
        $where[] = '(' . erp_collate('d.department_name') . ' LIKE :dept OR ' . erp_collate('e.department') . ' LIKE :dept2)';
        $params['dept'] = '%' . $filters['department'] . '%';
        $params['dept2'] = '%' . $filters['department'] . '%';
    }
    if (($filters['employee_type'] ?? '') !== '') {
        $where[] = 'e.employee_type = :etype';
        $params['etype'] = $filters['employee_type'];
    }

    $statusFilter = (string)($filters['salary_status'] ?? '');
    $having = '';
    if ($statusFilter === 'pending') {
        $having = 'HAVING salary_id IS NULL';
    } elseif ($statusFilter === 'generated') {
        $having = 'HAVING salary_id IS NOT NULL AND COALESCE(is_draft,0) = 0 AND COALESCE(payment_status,\'unpaid\') <> \'paid\'';
    } elseif ($statusFilter === 'paid') {
        $having = 'HAVING salary_id IS NOT NULL AND payment_status = \'paid\'';
    } elseif ($statusFilter === 'unpaid') {
        $having = 'HAVING salary_id IS NOT NULL AND COALESCE(payment_status,\'unpaid\') = \'unpaid\' AND COALESCE(is_draft,0) = 0';
    } elseif ($statusFilter === 'draft') {
        $having = 'HAVING salary_id IS NOT NULL AND is_draft = 1';
    }

    $sql = "SELECT e.*,
        " . erp_dept_label_sql('d', 'e') . " AS dept_label,
        " . erp_desig_label_sql('des', 'e') . " AS desig_label,
        s.id AS salary_id,
        s.month_year,
        s.gross_salary AS payroll_gross,
        s.overtime_amount,
        s.total_deduction,
        s.net_salary,
        s.payment_status,
        s.is_draft,
        s.present_days AS sal_present,
        s.generated_at
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN designations des ON des.id = e.designation_id
        LEFT JOIN salaries s ON s.employee_id = e.id AND s.month_year = :month
        WHERE " . implode(' AND ', $where) . "
        {$having}
        ORDER BY e.full_name ASC";

    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $st->bindValue(':' . $k, $v);
    }
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['ui_status'] = $row['salary_id'] ? payroll_row_status($row) : 'pending';
        $row['display_gross'] = (float)($row['payroll_gross'] ?? 0) > 0
            ? (float)$row['payroll_gross']
            : employee_fixed_gross_monthly($row);
    }
    unset($row);

    return $rows;
}
