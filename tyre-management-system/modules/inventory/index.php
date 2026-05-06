<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
if (!has_role(['Super Admin','Inventory Manager'])) { echo 'Access denied'; return; }
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare('UPDATE inventory SET reorder_level=:r, warehouse_location=:w WHERE id=:id');
        $stmt->execute(['r' => post_int('reorder_level'), 'w' => post_string('warehouse_location',120), 'id' => post_int('id')]);
        set_flash('success', 'Inventory settings updated.');
    } catch (Throwable $e) {
        set_flash('danger', 'Inventory update failed: ' . $e->getMessage());
    }
    redirect('inventory/list');
}
$rows=$pdo->query('SELECT * FROM inventory ORDER BY id DESC LIMIT 200')->fetchAll();
?>
<h4>Inventory</h4>
<table class="table table-sm table-striped align-middle"><tr><th>Product</th><th>Batch</th><th>Qty</th><th>Reorder</th><th>Warehouse</th><th>Alert</th><th>Updated</th><th>Update</th></tr><?php foreach($rows as $r): ?><tr><td><?= e((string)($r['product_name'] ?? '')) ?></td><td><?= e((string)($r['batch_ref'] ?? '')) ?></td><td><?= e((string)($r['qty'] ?? 0)) ?></td><td><?= e((string)($r['reorder_level'] ?? 0)) ?></td><td><?= e((string)($r['warehouse_location'] ?? '')) ?></td><td><?= (int)($r['qty'] ?? 0) <= (int)($r['reorder_level'] ?? 0) ? '<span class="badge bg-danger">Low</span>' : '<span class="badge bg-success">OK</span>' ?></td><td><?= e((string)($r['updated_at'] ?? '')) ?></td><td><form method="post" class="d-flex gap-1"><?= csrf_input() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input class="form-control form-control-sm" type="number" name="reorder_level" value="<?= (int)($r['reorder_level'] ?? 0) ?>" style="width:90px"><input class="form-control form-control-sm" name="warehouse_location" value="<?= e((string)($r['warehouse_location'] ?? '')) ?>" style="width:150px"><button class="btn btn-sm btn-primary">Save</button></form></td></tr><?php endforeach; ?></table>
