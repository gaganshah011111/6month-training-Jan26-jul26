<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if (!has_role(['Super Admin','Production Manager'])) { echo 'Access denied'; return; }
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $pdo->beginTransaction();
 try {
    $materialId = post_int('raw_material_id');
    $usedQty = post_float('material_used_qty');
    $matStmt = $pdo->prepare('SELECT stock_qty FROM raw_materials WHERE id=:id FOR UPDATE');
    $matStmt->execute(['id' => $materialId]);
    $material = $matStmt->fetch();
    if (!$material || (float)$material['stock_qty'] < $usedQty) {
        throw new RuntimeException('Insufficient raw material stock.');
    }

    $machineStmt = $pdo->prepare("SELECT status FROM machines WHERE id=:id");
    $machineStmt->execute(['id' => post_int('machine_id')]);
    $machine = $machineStmt->fetch();
    if (!$machine || $machine['status'] !== 'Active') {
        throw new RuntimeException('Machine is not active.');
    }

    $stmt=$pdo->prepare('INSERT INTO production(production_date,machine_id,shift,raw_material_id,material_used_qty,output_quantity) VALUES(:d,:m,:s,:r,:u,:o)');
    $stmt->execute(['d'=>$_POST['production_date'],'m'=>post_int('machine_id'),'s'=>post_string('shift', 20),'r'=>$materialId,'u'=>$usedQty,'o'=>post_int('output_quantity')]);
    $pdo->prepare('UPDATE raw_materials SET stock_qty = stock_qty - :u WHERE id=:id')->execute(['u'=>$usedQty,'id'=>$materialId]);
    $pdo->commit();
    set_flash('success', 'Production entry created and raw material consumed.');
 } catch (Throwable $e) {
    $pdo->rollBack();
    set_flash('danger', 'Production failed: ' . $e->getMessage());
 }
 redirect('production/list');
}
$machines=$pdo->query("SELECT id,machine_name,status FROM machines ORDER BY machine_name")->fetchAll();
$mats=$pdo->query('SELECT id,material_name FROM raw_materials ORDER BY material_name')->fetchAll();
$rows=$pdo->query('SELECT p.*, m.machine_name, rm.material_name FROM production p JOIN machines m ON m.id=p.machine_id JOIN raw_materials rm ON rm.id=p.raw_material_id ORDER BY p.id DESC LIMIT 100')->fetchAll();
?>
<h4>Production</h4>
<form method="post" class="row g-2 mb-3"><?= csrf_input() ?><div class="col"><input class="form-control" type="date" name="production_date" required></div><div class="col"><select class="form-select" name="machine_id"><?php foreach($machines as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['machine_name']) ?> (<?= e($m['status']) ?>)</option><?php endforeach; ?></select></div><div class="col"><select class="form-select" name="shift"><option>Morning</option><option>Evening</option><option>Night</option></select></div><div class="col"><select class="form-select" name="raw_material_id"><?php foreach($mats as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['material_name']) ?></option><?php endforeach; ?></select></div><div class="col"><input class="form-control" type="number" step="0.01" name="material_used_qty" placeholder="Used qty" required></div><div class="col"><input class="form-control" type="number" name="output_quantity" placeholder="Tyres output" required></div><div class="col"><button class="btn btn-primary w-100">Record</button></div></form>
<table class="table table-sm"><tr><th>Date</th><th>Machine</th><th>Material</th><th>Used</th><th>Output</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['production_date']) ?></td><td><?= e($r['machine_name']) ?></td><td><?= e($r['material_name']) ?></td><td><?= e((string)$r['material_used_qty']) ?></td><td><?= e((string)$r['output_quantity']) ?></td></tr><?php endforeach; ?></table>
