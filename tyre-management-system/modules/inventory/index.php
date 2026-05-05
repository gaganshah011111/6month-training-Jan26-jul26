<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
$pdo = Database::connection();
$rows=$pdo->query('SELECT * FROM inventory ORDER BY id DESC LIMIT 200')->fetchAll();
?>
<h4>Inventory</h4>
<table class="table table-sm table-striped"><tr><th>Product</th><th>Batch</th><th>Qty</th><th>Warehouse</th><th>Updated</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['product_name']) ?></td><td><?= e($r['batch_ref']) ?></td><td><?= e((string)$r['qty']) ?></td><td><?= e($r['warehouse_location']) ?></td><td><?= e($r['updated_at']) ?></td></tr><?php endforeach; ?></table>
