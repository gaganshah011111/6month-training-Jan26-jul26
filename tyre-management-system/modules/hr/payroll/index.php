<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
if (!has_role(['Super Admin','HR Manager'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $employeeId = post_int('employee_id');
        $month = (string)($_POST['month_year'] ?? date('Y-m'));
        $otHours = post_float('overtime_hours');
        $manualDeduction = post_float('deductions');

        $empStmt = $pdo->prepare('SELECT basic_salary FROM employees WHERE id=:id');
        $empStmt->execute(['id' => $employeeId]);
        $emp = $empStmt->fetch() ?: [];
        $basic = (float)($emp['basic_salary'] ?? 0);

        $daysInMonth = (int)date('t', strtotime($month . '-01'));
        $attStmt = $pdo->prepare("SELECT
                SUM(CASE WHEN status='Present' THEN 1 WHEN status='Late' THEN 1 WHEN status='Half Day' THEN 0.5 ELSE 0 END) AS present_days,
                SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) AS absent_days
            FROM attendance
            WHERE employee_id=:eid AND DATE_FORMAT(attendance_date, '%Y-%m')=:month");
        $attStmt->execute(['eid' => $employeeId, 'month' => $month]);
        $att = $attStmt->fetch() ?: [];

        $leaveStmt = $pdo->prepare("SELECT
                SUM(CASE WHEN status='Approved' AND is_paid=1 THEN DATEDIFF(COALESCE(to_date,end_date), COALESCE(from_date,start_date))+1 ELSE 0 END) AS paid_leave_days,
                SUM(CASE WHEN status='Approved' AND is_paid=0 THEN DATEDIFF(COALESCE(to_date,end_date), COALESCE(from_date,start_date))+1 ELSE 0 END) AS unpaid_leave_days
            FROM leaves
            WHERE employee_id=:eid AND DATE_FORMAT(COALESCE(from_date,start_date), '%Y-%m')<=:month AND DATE_FORMAT(COALESCE(to_date,end_date), '%Y-%m')>=:month");
        $leaveStmt->execute(['eid' => $employeeId, 'month' => $month]);
        $leave = $leaveStmt->fetch() ?: [];

        $presentDays = (float)($att['present_days'] ?? 0);
        $paidLeaveDays = (float)($leave['paid_leave_days'] ?? 0);
        $unpaidLeaveDays = (float)($leave['unpaid_leave_days'] ?? 0);
        $dailyRate = $daysInMonth > 0 ? ($basic / $daysInMonth) : 0;
        $overtimeAmount = $otHours * ($dailyRate / 8);
        $attendanceDeduction = max($daysInMonth - ($presentDays + $paidLeaveDays), 0) * $dailyRate;
        $deductions = $attendanceDeduction + $manualDeduction + ($unpaidLeaveDays * $dailyRate);
        $net = max($basic + $overtimeAmount - $deductions, 0);

        $stmt = $pdo->prepare('INSERT INTO salaries(employee_id,month_year,present_days,paid_leave_days,unpaid_leave_days,overtime_hours,overtime_amount,deductions,basic,net_salary) VALUES(:e,:m,:pd,:pl,:ul,:oh,:oa,:d,:b,:n) ON DUPLICATE KEY UPDATE present_days=VALUES(present_days), paid_leave_days=VALUES(paid_leave_days), unpaid_leave_days=VALUES(unpaid_leave_days), overtime_hours=VALUES(overtime_hours), overtime_amount=VALUES(overtime_amount), deductions=VALUES(deductions), basic=VALUES(basic), net_salary=VALUES(net_salary)');
        $stmt->execute(['e'=>$employeeId,'m'=>$month,'pd'=>$presentDays,'pl'=>$paidLeaveDays,'ul'=>$unpaidLeaveDays,'oh'=>$otHours,'oa'=>$overtimeAmount,'d'=>$deductions,'b'=>$basic,'n'=>$net]);

        $monthNum = (int)date('n', strtotime($month . '-01'));
        $yearNum = (int)date('Y', strtotime($month . '-01'));
        $payrollCompat = $pdo->prepare('INSERT INTO payroll(employee_id,month,year,present_days,paid_leave_days,unpaid_leave_days,overtime_amount,basic_salary,deduction,net_salary) VALUES(:e,:m,:y,:pd,:pl,:ul,:oa,:b,:d,:n) ON DUPLICATE KEY UPDATE present_days=VALUES(present_days), paid_leave_days=VALUES(paid_leave_days), unpaid_leave_days=VALUES(unpaid_leave_days), overtime_amount=VALUES(overtime_amount), basic_salary=VALUES(basic_salary), deduction=VALUES(deduction), net_salary=VALUES(net_salary)');
        $payrollCompat->execute(['e'=>$employeeId,'m'=>$monthNum,'y'=>$yearNum,'pd'=>$presentDays,'pl'=>$paidLeaveDays,'ul'=>$unpaidLeaveDays,'oa'=>$overtimeAmount,'b'=>$basic,'d'=>$deductions,'n'=>$net]);
        set_flash('success', 'Payroll generated from attendance + leave + overtime.');
    } catch (Throwable $e) {
        set_flash('danger', 'Payroll generation failed: ' . $e->getMessage());
    }
    redirect('payroll/list');
}
$emps = $pdo->query('SELECT id, full_name, basic_salary FROM employees ORDER BY full_name')->fetchAll();
$rows = $pdo->query('SELECT s.*, e.full_name FROM salaries s JOIN employees e ON e.id=s.employee_id ORDER BY s.id DESC LIMIT 60')->fetchAll();
?>
<h4>Payroll</h4>
<form method="post" class="row g-2 mb-3">
<?= csrf_input() ?>
<div class="col"><select class="form-select" name="employee_id"><?php foreach($emps as $e): ?><option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col"><input class="form-control" type="month" name="month_year" required></div>
<div class="col"><input class="form-control" type="number" step="0.01" name="overtime_hours" placeholder="Overtime Hours" value="0"></div>
<div class="col"><input class="form-control" type="number" step="0.01" name="deductions" placeholder="Deductions" value="0"></div>
<div class="col"><button class="btn btn-primary w-100">Generate</button></div>
</form>
<table class="table table-sm"><tr><th>Month</th><th>Employee</th><th>Present</th><th>Paid Leave</th><th>Unpaid Leave</th><th>Overtime</th><th>Deductions</th><th>Net Salary</th></tr><?php foreach($rows as $r): ?><tr><td><?= e((string)($r['month_year'] ?? '')) ?></td><td><?= e((string)($r['full_name'] ?? '')) ?></td><td><?= e((string)($r['present_days'] ?? 0)) ?></td><td><?= e((string)($r['paid_leave_days'] ?? 0)) ?></td><td><?= e((string)($r['unpaid_leave_days'] ?? 0)) ?></td><td><?= e((string)($r['overtime_amount'] ?? 0)) ?></td><td><?= e((string)($r['deductions'] ?? 0)) ?></td><td><?= e((string)($r['net_salary'] ?? 0)) ?></td></tr><?php endforeach; ?></table>
