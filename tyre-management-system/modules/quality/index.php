<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $stmt=$pdo->prepare('INSERT INTO quality_checks(production_id,inspection_date,inspector_name,passed_qty,failed_qty,defects) VALUES(:p,:d,:i,:pa,:f,:de)');
 $stmt->execute(['p'=>(int)$_POST['production_id'],'d'=>$_POST['inspection_date'],'i'=>$_POST['inspector_name'],'pa'=>(int)$_POST['passed_qty'],'f'=>(int)$_POST['failed_qty'],'de'=>$_POST['defects']]);
 $pdo->prepare('INSERT INTO inventory(product_name,batch_ref,qty,warehouse_location) VALUES("Tyre",:b,:q,:w)')->execute(['b'=>'PRD-'.(int)$_POST['production_id'],'q'=>(int)$_POST['passed_qty'],'w'=>$_POST['warehouse_location']]);
}
$production=$pdo->query('SELECT id, production_date, output_quantity FROM production ORDER BY id DESC LIMIT 100')->fetchAll();
$rows=$pdo->query('SELECT q.*, p.production_date FROM quality_checks q JOIN production p ON p.id=q.production_id ORDER BY q.id DESC LIMIT 100')->fetchAll();
?>
<h4>Quality Control</h4>
<form method="post" class="row g-2 mb-3"><div class="col"><select class="form-select" name="production_id"><?php foreach($production as $p): ?><option value="<?= $p['id'] ?>">#<?= $p['id'] ?> - <?= e($p['production_date']) ?> (<?= e((string)$p['output_quantity']) ?>)</option><?php endforeach; ?></select></div><div class="col"><input class="form-control" type="date" name="inspection_date" required></div><div class="col"><input class="form-control" name="inspector_name" placeholder="Inspector" required></div><div class="col"><input class="form-control" type="number" name="passed_qty" placeholder="Pass" required></div><div class="col"><input class="form-control" type="number" name="failed_qty" placeholder="Fail" required></div><div class="col"><input class="form-control" name="warehouse_location" placeholder="Warehouse" required></div><div class="col"><input class="form-control" name="defects" placeholder="Defects"></div><div class="col"><button class="btn btn-primary w-100">Inspect</button></div></form>
<table class="table table-sm"><tr><th>Production</th><th>Date</th><th>Pass</th><th>Fail</th><th>Defects</th></tr><?php foreach($rows as $r): ?><tr><td>#<?= e((string)$r['production_id']) ?></td><td><?= e($r['inspection_date']) ?></td><td><?= e((string)$r['passed_qty']) ?></td><td><?= e((string)$r['failed_qty']) ?></td><td><?= e($r['defects']) ?></td></tr><?php endforeach; ?></table>
