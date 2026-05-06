<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['Super Admin','Inventory Manager'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));

$stock = 0; $low = 0; $dispatched = 0;
try {
    $stock = (int)$pdo->query('SELECT COALESCE(SUM(qty),0) FROM inventory')->fetchColumn();
    $low = (int)$pdo->query('SELECT COUNT(*) FROM inventory WHERE qty <= reorder_level')->fetchColumn();
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(qty),0) FROM dispatch WHERE dispatch_date BETWEEN :f AND :t');
    $stmt->execute(['f' => $from, 't' => $to]);
    $dispatched = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    set_flash('danger', 'Inventory report failed: ' . $e->getMessage());
}
?>
<h4>Inventory Reports</h4>
<form class="row g-2 mb-3"><input type="hidden" name="page" value="reports/inventory"><div class="col-md-3"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div><div class="col-md-3"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Apply</button></div></form>
<div class="row g-3 mb-3"><div class="col-md-4"><div class="card"><div class="card-body"><small>Total Stock</small><h3><?= e((string)$stock) ?></h3></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><small>Low Stock Items</small><h3><?= e((string)$low) ?></h3></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><small>Dispatched Qty</small><h3><?= e((string)$dispatched) ?></h3></div></div></div></div>
