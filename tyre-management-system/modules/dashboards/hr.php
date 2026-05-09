<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['HR Manager','Super Admin'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
$employees = (int)$pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
$present = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status IN ('Present','Late','Half Day','Emergency Duty')")->fetchColumn();
$leavePending = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status='Applied'")->fetchColumn();
$payroll = (float)$pdo->query("SELECT COALESCE(SUM(net_salary),0) FROM salaries WHERE month_year = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
?>
<h3>HR Dashboard</h3>
<div class="row g-3">
    <div class="col-md-3"><div class="card kpi-card"><div class="card-body d-flex justify-content-between align-items-start"><div><small>Employees</small><h4><?= e((string)$employees) ?></h4></div><span class="kpi-icon"><i class="bi bi-people"></i></span></div></div></div>
    <div class="col-md-3"><div class="card kpi-card"><div class="card-body d-flex justify-content-between align-items-start"><div><small>Present Today</small><h4><?= e((string)$present) ?></h4></div><span class="kpi-icon"><i class="bi bi-calendar-check"></i></span></div></div></div>
    <div class="col-md-3"><div class="card kpi-card"><div class="card-body d-flex justify-content-between align-items-start"><div><small>Pending Leaves</small><h4><?= e((string)$leavePending) ?></h4></div><span class="kpi-icon"><i class="bi bi-calendar-minus"></i></span></div></div></div>
    <div class="col-md-3"><div class="card kpi-card"><div class="card-body d-flex justify-content-between align-items-start"><div><small>Monthly Payroll</small><h4><?= e((string)$payroll) ?></h4></div><span class="kpi-icon"><i class="bi bi-cash-coin"></i></span></div></div></div>
</div>
