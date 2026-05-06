<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['Inventory Manager','Super Admin'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
$items = (int)$pdo->query('SELECT COUNT(*) FROM inventory')->fetchColumn();
$stock = (int)$pdo->query('SELECT COALESCE(SUM(qty),0) FROM inventory')->fetchColumn();
$low = (int)$pdo->query('SELECT COUNT(*) FROM inventory WHERE qty <= reorder_level')->fetchColumn();
?>
<h3>Inventory Dashboard</h3>
<div class="row g-3">
    <div class="col-md-4"><div class="card"><div class="card-body"><small>Total SKUs</small><h4><?= e((string)$items) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><small>Total Stock Qty</small><h4><?= e((string)$stock) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><small>Low Stock Alerts</small><h4><?= e((string)$low) ?></h4></div></div></div>
</div>
