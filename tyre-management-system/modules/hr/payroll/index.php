<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/payroll_logic.php';
if (!has_role(['Super Admin','HR Manager'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $employeeId = post_int('employee_id');
        $month = (string)($_POST['month_year'] ?? date('Y-m'));
        $otHours = post_float('overtime_hours');
        $manualDeduction = post_float('deductions');

        $empStmt = $pdo->prepare('SELECT * FROM employees WHERE id=:id');
        $empStmt->execute(['id' => $employeeId]);
        $emp = $empStmt->fetch() ?: [];

        $daysInMonth = (int)date('t', strtotime($month . '-01'));
        $attStmt = $pdo->prepare("SELECT
                SUM(CASE WHEN status IN ('Present','Late','Emergency Duty') THEN 1 WHEN status='Half Day' THEN 0.5 WHEN status IN ('Paid Leave','Holiday') THEN 1 ELSE 0 END) AS present_days,
                SUM(CASE WHEN status='Half Day' THEN 1 ELSE 0 END) AS half_days,
                SUM(CASE WHEN status='Late' OR is_late = 1 THEN 1 ELSE 0 END) AS late_days,
                COALESCE(SUM(overtime_hours),0) AS overtime_hours
            FROM attendance
            WHERE employee_id=:eid AND DATE_FORMAT(attendance_date, '%Y-%m')=:month");
        $attStmt->execute(['eid' => $employeeId, 'month' => $month]);
        $att = $attStmt->fetch() ?: [];

        $leaveStmt = $pdo->prepare("SELECT
                SUM(CASE WHEN status='Approved' AND COALESCE(leave_category,'Paid')='Paid' THEN DATEDIFF(COALESCE(to_date,end_date), COALESCE(from_date,start_date))+1 ELSE 0 END) AS paid_leave_days,
                SUM(CASE WHEN status='Approved' AND COALESCE(leave_category,'Unpaid')='Half Paid' THEN DATEDIFF(COALESCE(to_date,end_date), COALESCE(from_date,start_date))+1 ELSE 0 END) AS half_paid_leave_days,
                SUM(CASE WHEN status='Approved' AND (COALESCE(leave_category,'Unpaid')='Unpaid' OR is_paid=0) THEN DATEDIFF(COALESCE(to_date,end_date), COALESCE(from_date,start_date))+1 ELSE 0 END) AS unpaid_leave_days
            FROM leaves
            WHERE employee_id=:eid AND DATE_FORMAT(COALESCE(from_date,start_date), '%Y-%m')<=:month AND DATE_FORMAT(COALESCE(to_date,end_date), '%Y-%m')>=:month");
        $leaveStmt->execute(['eid' => $employeeId, 'month' => $month]);
        $leave = $leaveStmt->fetch() ?: [];

        $attendanceData = [
            'present_days' => (float)($att['present_days'] ?? 0),
            'half_days' => (float)($att['half_days'] ?? 0),
            'late_days' => (float)($att['late_days'] ?? 0),
            'overtime_hours' => (float)($att['overtime_hours'] ?? 0) + $otHours,
        ];
        $leaveData = [
            'paid_leave_days' => (float)($leave['paid_leave_days'] ?? 0),
            'half_paid_leave_days' => (float)($leave['half_paid_leave_days'] ?? 0),
            'unpaid_leave_days' => (float)($leave['unpaid_leave_days'] ?? 0),
        ];
        $ps = payroll_settings_fetch($pdo);
        $calc = calculate_payroll_breakdown($emp, $attendanceData, $leaveData, $month, $ps);
        $calc['total_deduction'] = round((float)$calc['total_deduction'] + $manualDeduction, 2);
        $calc['net_salary'] = max(0, round((float)$calc['gross_salary'] - (float)$calc['total_deduction'], 2));

        $stmt = $pdo->prepare('INSERT INTO salaries(employee_id,month_year,present_days,paid_leave_days,half_paid_leave_days,unpaid_leave_days,overtime_hours,overtime_amount,deductions,basic,dearness_allowance,travel_allowance,hra_percentage,hra_amount,pf_percentage,pf_amount,pf_employer_amount,esi_employee_percentage,esi_employee_amount,esi_employer_percentage,esi_employer_amount,medical_allowance,special_allowance,other_allowances,tax_deduction,gratuity_accrual,leave_deduction,half_day_deduction,late_entry_deduction,gross_salary,total_deduction,net_salary) VALUES(:e,:m,:pd,:pl,:hpl,:ul,:oh,:oa,:d,:b,:da,:ta,:hrp,:hra,:pfp,:pfa,:pfem,:esiep,:esiea,:esirp,:esira,:ma,:sp,:oa2,:tax,:gr,:ld,:hdd,:led,:gs,:td,:n) ON DUPLICATE KEY UPDATE present_days=VALUES(present_days), paid_leave_days=VALUES(paid_leave_days), half_paid_leave_days=VALUES(half_paid_leave_days), unpaid_leave_days=VALUES(unpaid_leave_days), overtime_hours=VALUES(overtime_hours), overtime_amount=VALUES(overtime_amount), deductions=VALUES(deductions), basic=VALUES(basic), dearness_allowance=VALUES(dearness_allowance), travel_allowance=VALUES(travel_allowance), hra_percentage=VALUES(hra_percentage), hra_amount=VALUES(hra_amount), pf_percentage=VALUES(pf_percentage), pf_amount=VALUES(pf_amount), pf_employer_amount=VALUES(pf_employer_amount), esi_employee_percentage=VALUES(esi_employee_percentage), esi_employee_amount=VALUES(esi_employee_amount), esi_employer_percentage=VALUES(esi_employer_percentage), esi_employer_amount=VALUES(esi_employer_amount), medical_allowance=VALUES(medical_allowance), special_allowance=VALUES(special_allowance), other_allowances=VALUES(other_allowances), tax_deduction=VALUES(tax_deduction), gratuity_accrual=VALUES(gratuity_accrual), leave_deduction=VALUES(leave_deduction), half_day_deduction=VALUES(half_day_deduction), late_entry_deduction=VALUES(late_entry_deduction), gross_salary=VALUES(gross_salary), total_deduction=VALUES(total_deduction), net_salary=VALUES(net_salary)');
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
        ]);

        $monthNum = (int)date('n', strtotime($month . '-01'));
        $yearNum = (int)date('Y', strtotime($month . '-01'));
        $payrollCompat = $pdo->prepare('INSERT INTO payroll(employee_id,month,year,present_days,paid_leave_days,unpaid_leave_days,overtime_amount,basic_salary,deduction,net_salary) VALUES(:e,:m,:y,:pd,:pl,:ul,:oa,:b,:d,:n) ON DUPLICATE KEY UPDATE present_days=VALUES(present_days), paid_leave_days=VALUES(paid_leave_days), unpaid_leave_days=VALUES(unpaid_leave_days), overtime_amount=VALUES(overtime_amount), basic_salary=VALUES(basic_salary), deduction=VALUES(deduction), net_salary=VALUES(net_salary)');
        $payrollCompat->execute(['e'=>$employeeId,'m'=>$monthNum,'y'=>$yearNum,'pd'=>$calc['present_days'],'pl'=>$calc['paid_leave_days'],'ul'=>$calc['unpaid_leave_days'],'oa'=>$calc['overtime_amount'],'b'=>$calc['gross_salary'],'d'=>$calc['total_deduction'],'n'=>$calc['net_salary']]);
        set_flash('success', 'Payroll generated from attendance + leave + overtime.');
    } catch (Throwable $e) {
        set_flash('danger', 'Payroll generation failed: ' . $e->getMessage());
    }
    redirect('payroll/list');
}
$emps = $pdo->query('SELECT * FROM employees ORDER BY full_name')->fetchAll();
$rows = $pdo->query('SELECT s.*, e.full_name FROM salaries s JOIN employees e ON e.id=s.employee_id ORDER BY s.id DESC LIMIT 60')->fetchAll();
?>
<div class="module-shell">
    <h4 class="mb-3">Payroll</h4>
    <div class="card mb-3">
        <div class="card-header section-title">Generate Payroll</div>
        <div class="card-body">
            <form method="post" class="row g-2">
                <?= csrf_input() ?>
                <div class="col-lg-3 col-md-6"><select class="form-select" name="employee_id"><?php foreach ($emps as $e): ?><?php
                    $gSel = (float)($e['gross_salary'] ?? 0) > 0 ? (float)$e['gross_salary'] : employee_fixed_gross_monthly($e);
                    ?><option value="<?= (int)$e['id'] ?>"><?= e((string)$e['full_name']) ?> (₹<?= e(number_format($gSel, 0, '.', ',')) ?> gross/mo)</option><?php endforeach; ?></select></div>
                <div class="col-lg-2 col-md-6"><input class="form-control" type="month" name="month_year" required></div>
                <div class="col-lg-2 col-md-6"><input class="form-control" type="number" step="0.01" name="overtime_hours" placeholder="Overtime Hours" value="0"></div>
                <div class="col-lg-2 col-md-6"><input class="form-control" type="number" step="0.01" name="deductions" placeholder="Manual Deductions" value="0"></div>
                <div class="col-lg-2 col-md-8"><button class="btn btn-primary w-100">Generate</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header section-title">Payroll Register</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr><th>Month</th><th>Employee</th><th>Present</th><th>Paid Leave</th><th>Half Paid</th><th>Unpaid Leave</th><th>OT Hours</th><th>OT Amount</th><th>Gross</th><th>Deductions</th><th>Net Salary</th></tr>
                </thead>
                <tbody>
                <?php foreach($rows as $r): ?>
                    <tr>
                        <td><?= e((string)($r['month_year'] ?? '')) ?></td>
                        <td><?= e((string)($r['full_name'] ?? '')) ?></td>
                        <td><?= e((string)($r['present_days'] ?? 0)) ?></td>
                        <td><?= e((string)($r['paid_leave_days'] ?? 0)) ?></td>
                        <td><?= e((string)($r['half_paid_leave_days'] ?? 0)) ?></td>
                        <td><?= e((string)($r['unpaid_leave_days'] ?? 0)) ?></td>
                        <td><span class="badge chip-overtime"><?= e((string)($r['overtime_hours'] ?? 0)) ?></span></td>
                        <td><?= e((string)($r['overtime_amount'] ?? 0)) ?></td>
                        <td><?= e((string)($r['gross_salary'] ?? 0)) ?></td>
                        <td><span class="badge chip-absent"><?= e((string)($r['total_deduction'] ?? $r['deductions'] ?? 0)) ?></span></td>
                        <td><strong><?= e((string)($r['net_salary'] ?? 0)) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
