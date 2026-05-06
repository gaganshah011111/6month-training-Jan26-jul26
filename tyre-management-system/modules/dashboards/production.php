<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['Production Manager','Super Admin'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
$rawCount = (int)$pdo->query('SELECT COUNT(*) FROM raw_materials')->fetchColumn();
$lowRaw = (int)$pdo->query('SELECT COUNT(*) FROM raw_materials WHERE stock_qty <= reorder_level')->fetchColumn();
$todayProd = (int)$pdo->query('SELECT COALESCE(SUM(output_quantity),0) FROM production WHERE production_date = CURDATE()')->fetchColumn();
$activeMachines = (int)$pdo->query("SELECT COUNT(*) FROM machines WHERE status='Active'")->fetchColumn();
?>
<h3>Production Dashboard</h3>
<div class="row g-3">
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Raw Materials</small><h4><?= e((string)$rawCount) ?></h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Low Raw Stock</small><h4><?= e((string)$lowRaw) ?></h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Today Production</small><h4><?= e((string)$todayProd) ?></h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Active Machines</small><h4><?= e((string)$activeMachines) ?></h4></div></div></div>
</div>
