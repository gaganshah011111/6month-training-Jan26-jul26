<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['HR Manager','Super Admin'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
$employees = (int)$pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
$present = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status='Present'")->fetchColumn();
$leavePending = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status='Applied'")->fetchColumn();
$payroll = (float)$pdo->query("SELECT COALESCE(SUM(net_salary),0) FROM salaries WHERE month_year = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
?>
<h3>HR Dashboard</h3>
<div class="row g-3">
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Employees</small><h4><?= e((string)$employees) ?></h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Present Today</small><h4><?= e((string)$present) ?></h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Pending Leaves</small><h4><?= e((string)$leavePending) ?></h4></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Monthly Payroll</small><h4><?= e((string)$payroll) ?></h4></div></div></div>
</div>
