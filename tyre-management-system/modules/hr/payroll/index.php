<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
if (!has_role(['Super Admin','Admin'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $basic = (float)$_POST['basic']; $ot = (float)$_POST['overtime']; $ded = (float)$_POST['deductions'];
    $net = $basic + $ot - $ded;
    $stmt = $pdo->prepare('INSERT INTO salaries(employee_id,month_year,basic,overtime,deductions,net_salary) VALUES(:e,:m,:b,:o,:d,:n)');
    $stmt->execute(['e'=>(int)$_POST['employee_id'],'m'=>$_POST['month_year'],'b'=>$basic,'o'=>$ot,'d'=>$ded,'n'=>$net]);
}
$emps = $pdo->query('SELECT id, full_name, basic_salary FROM employees ORDER BY full_name')->fetchAll();
$rows = $pdo->query('SELECT s.*, e.full_name FROM salaries s JOIN employees e ON e.id=s.employee_id ORDER BY s.id DESC LIMIT 60')->fetchAll();
?>
<h4>Payroll</h4>
<form method="post" class="row g-2 mb-3">
<div class="col"><select class="form-select" name="employee_id"><?php foreach($emps as $e): ?><option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col"><input class="form-control" type="month" name="month_year" required></div>
<div class="col"><input class="form-control" type="number" step="0.01" name="basic" placeholder="Basic" required></div>
<div class="col"><input class="form-control" type="number" step="0.01" name="overtime" placeholder="Overtime" value="0"></div>
<div class="col"><input class="form-control" type="number" step="0.01" name="deductions" placeholder="Deductions" value="0"></div>
<div class="col"><button class="btn btn-primary w-100">Generate</button></div>
</form>
<table class="table table-sm"><tr><th>Month</th><th>Employee</th><th>Net Salary</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['month_year']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e((string)$r['net_salary']) ?></td></tr><?php endforeach; ?></table>
