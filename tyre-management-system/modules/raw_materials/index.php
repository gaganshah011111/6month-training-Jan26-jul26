<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $stmt=$pdo->prepare('INSERT INTO raw_materials(material_name,unit,stock_qty,reorder_level,supplier_id) VALUES(:m,:u,:q,:r,:s)');
 $stmt->execute(['m'=>$_POST['material_name'],'u'=>$_POST['unit'],'q'=>(float)$_POST['stock_qty'],'r'=>(float)$_POST['reorder_level'],'s'=>(int)$_POST['supplier_id']]);
}
$suppliers=$pdo->query('SELECT id,name FROM suppliers ORDER BY name')->fetchAll();
$rows=$pdo->query('SELECT rm.*, s.name supplier_name FROM raw_materials rm LEFT JOIN suppliers s ON s.id=rm.supplier_id ORDER BY rm.id DESC')->fetchAll();
?>
<h4>Raw Materials</h4>
<form method="post" class="row g-2 mb-3"><div class="col"><input class="form-control" name="material_name" placeholder="Material" required></div><div class="col"><input class="form-control" name="unit" placeholder="kg/ltr" required></div><div class="col"><input class="form-control" type="number" step="0.01" name="stock_qty" placeholder="Stock" required></div><div class="col"><input class="form-control" type="number" step="0.01" name="reorder_level" placeholder="Reorder" required></div><div class="col"><select class="form-select" name="supplier_id"><?php foreach($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div><div class="col"><button class="btn btn-primary w-100">Save</button></div></form>
<table class="table table-sm"><tr><th>Material</th><th>Stock</th><th>Unit</th><th>Supplier</th><th>Alert</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['material_name']) ?></td><td><?= e((string)$r['stock_qty']) ?></td><td><?= e($r['unit']) ?></td><td><?= e($r['supplier_name'] ?? '-') ?></td><td><?= $r['stock_qty'] <= $r['reorder_level'] ? '<span class="badge bg-danger">Low</span>' : '<span class="badge bg-success">OK</span>' ?></td></tr><?php endforeach; ?></table>
