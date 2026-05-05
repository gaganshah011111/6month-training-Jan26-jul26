<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $stmt=$pdo->prepare('INSERT INTO machines(machine_code,machine_name,status,last_maintenance_date) VALUES(:c,:n,:s,:d)');
 $stmt->execute(['c'=>$_POST['machine_code'],'n'=>$_POST['machine_name'],'s'=>$_POST['status'],'d'=>$_POST['last_maintenance_date']]);
}
$rows=$pdo->query('SELECT * FROM machines ORDER BY id DESC')->fetchAll();
?>
<h4>Machines</h4>
<form method="post" class="row g-2 mb-3"><div class="col"><input class="form-control" name="machine_code" placeholder="Code" required></div><div class="col"><input class="form-control" name="machine_name" placeholder="Machine name" required></div><div class="col"><select class="form-select" name="status"><option>Active</option><option>Under Maintenance</option><option>Inactive</option></select></div><div class="col"><input class="form-control" type="date" name="last_maintenance_date" required></div><div class="col"><button class="btn btn-primary w-100">Add</button></div></form>
<table class="table table-sm"><tr><th>Code</th><th>Name</th><th>Status</th><th>Maintenance</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['machine_code']) ?></td><td><?= e($r['machine_name']) ?></td><td><?= e($r['status']) ?></td><td><?= e($r['last_maintenance_date']) ?></td></tr><?php endforeach; ?></table>
