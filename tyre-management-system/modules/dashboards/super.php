<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role('Super Admin')) { echo 'Access denied'; return; }
$pdo = Database::connection();
$users = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$employees = (int)$pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
$production = (int)$pdo->query('SELECT COALESCE(SUM(output_quantity),0) FROM production WHERE production_date = CURDATE()')->fetchColumn();
$dispatch = (int)$pdo->query("SELECT COUNT(*) FROM dispatch WHERE dispatch_status IN ('Created','In Transit')")->fetchColumn();
?>
<h3>Super Admin Dashboard</h3>
<div class="row g-3">
    <div class="col-md-3"><div class="card kpi-card"><div class="card-body d-flex justify-content-between align-items-start"><div><small>Total Users</small><h4><?= e((string)$users) ?></h4></div><span class="kpi-icon"><i class="bi bi-person-lines-fill"></i></span></div></div></div>
    <div class="col-md-3"><div class="card kpi-card"><div class="card-body d-flex justify-content-between align-items-start"><div><small>Total Employees</small><h4><?= e((string)$employees) ?></h4></div><span class="kpi-icon"><i class="bi bi-people-fill"></i></span></div></div></div>
    <div class="col-md-3"><div class="card kpi-card"><div class="card-body d-flex justify-content-between align-items-start"><div><small>Today Production</small><h4><?= e((string)$production) ?></h4></div><span class="kpi-icon"><i class="bi bi-building-gear"></i></span></div></div></div>
    <div class="col-md-3"><div class="card kpi-card"><div class="card-body d-flex justify-content-between align-items-start"><div><small>Pending Dispatch</small><h4><?= e((string)$dispatch) ?></h4></div><span class="kpi-icon"><i class="bi bi-send"></i></span></div></div></div>
</div>
