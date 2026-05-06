<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if (!has_role(['Super Admin','Production Manager'])) { echo 'Access denied'; return; }
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $action = $_POST['action'] ?? 'create';
 if ($action === 'create') {
    $stmt=$pdo->prepare('INSERT INTO raw_materials(material_name,unit,stock_qty,reorder_level,supplier_id) VALUES(:m,:u,:q,:r,:s)');
    $stmt->execute(['m'=>post_string('material_name',150),'u'=>post_string('unit',20),'q'=>post_float('stock_qty'),'r'=>post_float('reorder_level'),'s'=>post_int('supplier_id') ?: null]);
    set_flash('success', 'Raw material added.');
 } elseif ($action === 'restock') {
    $stmt = $pdo->prepare('UPDATE raw_materials SET stock_qty = stock_qty + :q WHERE id=:id');
    $stmt->execute(['q'=>post_float('qty'),'id'=>post_int('id')]);
    set_flash('success', 'Stock updated.');
 }
 redirect('raw-materials/list');
}
$suppliers=$pdo->query('SELECT id,name FROM suppliers ORDER BY name')->fetchAll();
$rows=$pdo->query('SELECT rm.*, s.name supplier_name FROM raw_materials rm LEFT JOIN suppliers s ON s.id=rm.supplier_id ORDER BY rm.id DESC')->fetchAll();
?>
<h4>Raw Materials</h4>
<form method="post" class="row g-2 mb-3"><?= csrf_input() ?><input type="hidden" name="action" value="create"><div class="col"><input class="form-control" name="material_name" placeholder="Material" required></div><div class="col"><input class="form-control" name="unit" placeholder="kg/ltr" required></div><div class="col"><input class="form-control" type="number" step="0.01" name="stock_qty" placeholder="Stock" required></div><div class="col"><input class="form-control" type="number" step="0.01" name="reorder_level" placeholder="Reorder" required></div><div class="col"><select class="form-select" name="supplier_id"><option value="">Select supplier</option><?php foreach($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div><div class="col"><button class="btn btn-primary w-100">Save</button></div></form>
<table class="table table-sm align-middle"><tr><th>Material</th><th>Stock</th><th>Unit</th><th>Supplier</th><th>Alert</th><th>Restock</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['material_name']) ?></td><td><?= e((string)$r['stock_qty']) ?></td><td><?= e($r['unit']) ?></td><td><?= e($r['supplier_name'] ?? '-') ?></td><td><?= $r['stock_qty'] <= $r['reorder_level'] ? '<span class="badge bg-danger">Low</span>' : '<span class="badge bg-success">OK</span>' ?></td><td><form method="post" class="d-flex gap-1"><?= csrf_input() ?><input type="hidden" name="action" value="restock"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input class="form-control form-control-sm" type="number" step="0.01" name="qty" placeholder="Qty" required><button class="btn btn-sm btn-outline-primary">Add</button></form></td></tr><?php endforeach; ?></table>
