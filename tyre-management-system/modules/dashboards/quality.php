<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['Quality Manager','Super Admin'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
$inspections = (int)$pdo->query('SELECT COUNT(*) FROM quality_checks WHERE inspection_date = CURDATE()')->fetchColumn();
$passed = (int)$pdo->query('SELECT COALESCE(SUM(passed_qty),0) FROM quality_checks WHERE inspection_date = CURDATE()')->fetchColumn();
$failed = (int)$pdo->query('SELECT COALESCE(SUM(failed_qty),0) FROM quality_checks WHERE inspection_date = CURDATE()')->fetchColumn();
?>
<h3>Quality Dashboard</h3>
<div class="row g-3">
    <div class="col-md-4"><div class="card"><div class="card-body"><small>Today Inspections</small><h4><?= e((string)$inspections) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><small>Passed Qty</small><h4><?= e((string)$passed) ?></h4></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><small>Failed Qty</small><h4><?= e((string)$failed) ?></h4></div></div></div>
</div>
