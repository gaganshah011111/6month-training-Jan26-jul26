<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if (!has_role(['Super Admin','Production Manager'])) { echo 'Access denied'; return; }
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $action = $_POST['action'] ?? 'create';
 if ($action === 'create') {
    $stmt=$pdo->prepare('INSERT INTO machines(machine_code,machine_name,status,last_maintenance_date) VALUES(:c,:n,:s,:d)');
    $stmt->execute(['c'=>post_string('machine_code',50),'n'=>post_string('machine_name',150),'s'=>post_string('status',30),'d'=>$_POST['last_maintenance_date'] ?: null]);
    set_flash('success', 'Machine added.');
 } elseif ($action === 'status') {
    $stmt = $pdo->prepare('UPDATE machines SET status=:s, last_maintenance_date=:d WHERE id=:id');
    $stmt->execute(['s'=>post_string('status',30),'d'=>$_POST['last_maintenance_date'] ?: null,'id'=>post_int('id')]);
    set_flash('success', 'Machine status updated.');
 }
 redirect('machines/list');
}
$rows=$pdo->query('SELECT * FROM machines ORDER BY id DESC')->fetchAll();
?>
<h4>Machines</h4>
<form method="post" class="row g-2 mb-3"><?= csrf_input() ?><input type="hidden" name="action" value="create"><div class="col"><input class="form-control" name="machine_code" placeholder="Code" required></div><div class="col"><input class="form-control" name="machine_name" placeholder="Machine name" required></div><div class="col"><select class="form-select" name="status"><option>Active</option><option>Under Maintenance</option><option>Inactive</option></select></div><div class="col"><input class="form-control" type="date" name="last_maintenance_date"></div><div class="col"><button class="btn btn-primary w-100">Add</button></div></form>
<table class="table table-sm align-middle"><tr><th>Code</th><th>Name</th><th>Status</th><th>Maintenance</th><th>Update</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['machine_code']) ?></td><td><?= e($r['machine_name']) ?></td><td><?= e($r['status']) ?></td><td><?= e((string)$r['last_maintenance_date']) ?></td><td><form method="post" class="d-flex gap-1"><?= csrf_input() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><select class="form-select form-select-sm" name="status"><option <?= $r['status']==='Active'?'selected':'' ?>>Active</option><option <?= $r['status']==='Under Maintenance'?'selected':'' ?>>Under Maintenance</option><option <?= $r['status']==='Inactive'?'selected':'' ?>>Inactive</option></select><input class="form-control form-control-sm" type="date" name="last_maintenance_date" value="<?= e((string)$r['last_maintenance_date']) ?>"><button class="btn btn-sm btn-outline-primary">Save</button></form></td></tr><?php endforeach; ?></table>
