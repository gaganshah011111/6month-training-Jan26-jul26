<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
if (!has_role(['Super Admin','Admin'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('INSERT INTO attendance(employee_id,attendance_date,shift,status) VALUES(:e,:d,:s,:st)');
    $stmt->execute(['e'=>(int)$_POST['employee_id'],'d'=>$_POST['attendance_date'],'s'=>$_POST['shift'],'st'=>$_POST['status']]);
}
$emps = $pdo->query('SELECT id, full_name FROM employees ORDER BY full_name')->fetchAll();
$rows = $pdo->query('SELECT a.*, e.full_name FROM attendance a JOIN employees e ON e.id=a.employee_id ORDER BY attendance_date DESC LIMIT 60')->fetchAll();
?>
<h4>Attendance</h4>
<form method="post" class="row g-2 mb-3">
<div class="col"><select class="form-select" name="employee_id"><?php foreach($emps as $e): ?><option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col"><input type="date" class="form-control" name="attendance_date" required></div>
<div class="col"><select class="form-select" name="shift"><option>Morning</option><option>Evening</option><option>Night</option></select></div>
<div class="col"><select class="form-select" name="status"><option>Present</option><option>Absent</option><option>Leave</option></select></div>
<div class="col"><button class="btn btn-primary w-100">Mark</button></div>
</form>
<table class="table table-sm"><tr><th>Date</th><th>Employee</th><th>Shift</th><th>Status</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['attendance_date']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['shift']) ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></table>
