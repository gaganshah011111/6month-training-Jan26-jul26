<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attendance_leave_bridge.php';

if (!is_logged_in() || !has_role(['Employee'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$pdo = Database::connection();
$employee = require_employee_record($pdo);
$employeeId = (int)$employee['id'];
$type = (string)($_GET['type'] ?? 'attendance');
$month = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$filename = 'employee-' . $type . '-' . $month . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}

if ($type === 'leave') {
    fputcsv($out, ['From', 'To', 'Status', 'Category', 'Paid days', 'Half paid', 'Unpaid', 'Reason', 'Rejection reason', 'Applied']);
    $st = $pdo->prepare("SELECT COALESCE(from_date, start_date) AS f, COALESCE(to_date, end_date) AS t, status, leave_category, paid_days, half_paid_days, unpaid_days, reason, rejection_reason, created_at
        FROM leaves WHERE employee_id = :e ORDER BY id DESC");
    $st->execute(['e' => $employeeId]);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['f'], $row['t'], $row['status'], $row['leave_category'],
            $row['paid_days'], $row['half_paid_days'], $row['unpaid_days'],
            $row['reason'], $row['rejection_reason'] ?? '', $row['created_at'],
        ]);
    }
} elseif ($type === 'salary') {
    fputcsv($out, ['Month', 'Gross', 'Total deduction', 'Net', 'OT amount', 'Payment status']);
    $st = $pdo->prepare('SELECT month_year, gross_salary, total_deduction, net_salary, overtime_amount, payment_status FROM salaries WHERE employee_id = :e ORDER BY month_year DESC');
    $st->execute(['e' => $employeeId]);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['month_year'], $row['gross_salary'], $row['total_deduction'],
            $row['net_salary'], $row['overtime_amount'], $row['payment_status'],
        ]);
    }
} else {
    attendance_leave_reconcile($pdo, $employeeId, $month);
    fputcsv($out, ['Date', 'Status', 'Punch in', 'Punch out', 'Hours', 'OT', 'Late', 'Remarks']);
    $view = attendance_fetch_month_view($pdo, $employeeId, $month);
    foreach ($view['rows'] as $row) {
        fputcsv($out, [
            $row['attendance_date'] ?? '',
            $row['status'] ?? '',
            $row['punch_in_time'] ?? '',
            $row['punch_out_time'] ?? '',
            $row['total_hours'] ?? '',
            $row['overtime_hours'] ?? '',
            !empty($row['is_late']) ? 'Yes' : 'No',
            $row['remarks'] ?? '',
        ]);
    }
}

fclose($out);
exit;
