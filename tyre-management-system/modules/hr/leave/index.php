<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
$pdo = Database::connection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('INSERT INTO leaves(employee_id,from_date,to_date,reason,status) VALUES(:e,:f,:t,:r,:s)');
    $stmt->execute(['e'=>(int)$_POST['employee_id'],'f'=>$_POST['from_date'],'t'=>$_POST['to_date'],'r'=>trim($_POST['reason']),'s'=>$_POST['status']]);
}
$emps = $pdo->query('SELECT id, full_name FROM employees ORDER BY full_name')->fetchAll();
$rows = $pdo->query('SELECT l.*, e.full_name FROM leaves l JOIN employees e ON e.id=l.employee_id ORDER BY l.id DESC LIMIT 60')->fetchAll();
?>
<h4>Leave Management</h4>
<form method="post" class="row g-2 mb-3">
<div class="col"><select class="form-select" name="employee_id"><?php foreach($emps as $e): ?><option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col"><input class="form-control" type="date" name="from_date" required></div>
<div class="col"><input class="form-control" type="date" name="to_date" required></div>
<div class="col"><input class="form-control" name="reason" placeholder="Reason" required></div>
<div class="col"><select class="form-select" name="status"><option>Applied</option><option>Approved</option><option>Rejected</option></select></div>
<div class="col"><button class="btn btn-primary w-100">Submit</button></div>
</form>
<table class="table table-sm"><tr><th>Employee</th><th>Duration</th><th>Status</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['full_name']) ?></td><td><?= e($r['from_date'].' to '.$r['to_date']) ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></table>
