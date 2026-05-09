<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
if (!has_role(['Super Admin', 'HR Manager'])) {
    echo 'Access denied';
    return;
}
$pdo = Database::connection();
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));

$attendance = 0;
$payroll = 0.0;
$leaves = 0;
$workerStats = [];
$staffStats = [];
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

    $breakdownSql = "SELECT
        SUM(CASE WHEN a.status IN ('Present','Late','Emergency Duty') THEN 1 ELSE 0 END) AS present_like,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_n,
        SUM(CASE WHEN a.status = 'Half Day' THEN 1 ELSE 0 END) AS half_n,
        SUM(CASE WHEN a.status IN ('Paid Leave','Unpaid Leave') OR a.status = 'Leave' THEN 1 ELSE 0 END) AS leave_n,
        SUM(CASE WHEN a.status = 'Holiday' THEN 1 ELSE 0 END) AS holiday_n,
        COALESCE(SUM(a.overtime_hours),0) AS ot_h,
        SUM(CASE WHEN a.is_emergency_duty = 1 OR a.status = 'Emergency Duty' THEN 1 ELSE 0 END) AS emerg_n,
        SUM(CASE WHEN a.status = 'Late' OR a.is_late = 1 THEN 1 ELSE 0 END) AS late_n
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        WHERE a.attendance_date BETWEEN :f AND :t AND e.employee_type = :etype";

    $w = $pdo->prepare($breakdownSql);
    $w->execute(['f' => $from, 't' => $to, 'etype' => 'Worker']);
    $workerStats = $w->fetch() ?: [];

    $s = $pdo->prepare($breakdownSql);
    $s->execute(['f' => $from, 't' => $to, 'etype' => 'Staff']);
    $staffStats = $s->fetch() ?: [];
} catch (Throwable $e) {
    set_flash('danger', 'HR report failed: ' . $e->getMessage());
}
?>
<h4>HR Reports</h4>
<form class="row g-2 mb-3"><input type="hidden" name="page" value="reports/hr"><div class="col-md-3"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div><div class="col-md-3"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Apply</button></div></form>
<div class="row g-3 mb-3"><div class="col-md-4"><div class="card"><div class="card-body"><small>Attendance Records</small><h3><?= e((string)$attendance) ?></h3></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><small>Payroll Amount</small><h3><?= e((string)$payroll) ?></h3></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><small>Approved Leaves</small><h3><?= e((string)$leaves) ?></h3></div></div></div></div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><strong>Workers</strong> <span class="text-muted small">(manual HR attendance)</span></div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li>Present / late / emergency duty days: <?= e((string)($workerStats['present_like'] ?? 0)) ?></li>
                    <li>Absent: <?= e((string)($workerStats['absent_n'] ?? 0)) ?></li>
                    <li>Half days: <?= e((string)($workerStats['half_n'] ?? 0)) ?></li>
                    <li>Leave rows: <?= e((string)($workerStats['leave_n'] ?? 0)) ?></li>
                    <li>Holidays (status): <?= e((string)($workerStats['holiday_n'] ?? 0)) ?></li>
                    <li>Total OT hours: <?= e((string)($workerStats['ot_h'] ?? 0)) ?></li>
                    <li>Emergency duty: <?= e((string)($workerStats['emerg_n'] ?? 0)) ?></li>
                    <li>Late flags: <?= e((string)($workerStats['late_n'] ?? 0)) ?></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><strong>Staff</strong> <span class="text-muted small">(punch in / out)</span></div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li>Present / late / emergency duty days: <?= e((string)($staffStats['present_like'] ?? 0)) ?></li>
                    <li>Absent: <?= e((string)($staffStats['absent_n'] ?? 0)) ?></li>
                    <li>Half days: <?= e((string)($staffStats['half_n'] ?? 0)) ?></li>
                    <li>Leave rows: <?= e((string)($staffStats['leave_n'] ?? 0)) ?></li>
                    <li>Holidays (status): <?= e((string)($staffStats['holiday_n'] ?? 0)) ?></li>
                    <li>Total OT hours: <?= e((string)($staffStats['ot_h'] ?? 0)) ?></li>
                    <li>Emergency duty: <?= e((string)($staffStats['emerg_n'] ?? 0)) ?></li>
                    <li>Late flags: <?= e((string)($staffStats['late_n'] ?? 0)) ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
