<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['Super Admin','HR Manager'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));

$attendance = 0; $payroll = 0.0; $leaves = 0;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE attendance_date BETWEEN :f AND :t');
    $stmt->execute(['f' => $from, 't' => $to]);
    $attendance = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_salary),0) FROM salaries WHERE month_year BETWEEN DATE_FORMAT(:f, '%Y-%m') AND DATE_FORMAT(:t, '%Y-%m')");
    $stmt->execute(['f' => $from, 't' => $to]);
    $payroll = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leaves WHERE status='Approved' AND COALESCE(from_date,start_date) BETWEEN :f AND :t");
    $stmt->execute(['f' => $from, 't' => $to]);
    $leaves = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    set_flash('danger', 'HR report failed: ' . $e->getMessage());
}
?>
<h4>HR Reports</h4>
<form class="row g-2 mb-3"><input type="hidden" name="page" value="reports/hr"><div class="col-md-3"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div><div class="col-md-3"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Apply</button></div></form>
<div class="row g-3 mb-3"><div class="col-md-4"><div class="card"><div class="card-body"><small>Attendance Records</small><h3><?= e((string)$attendance) ?></h3></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><small>Payroll Amount</small><h3><?= e((string)$payroll) ?></h3></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><small>Approved Leaves</small><h3><?= e((string)$leaves) ?></h3></div></div></div></div>
