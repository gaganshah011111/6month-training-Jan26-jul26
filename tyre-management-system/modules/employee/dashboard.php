<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = Database::connection();
$error = '';

try {
    $employee = require_employee_record($pdo);
    $employeeId = (int)$employee['id'];

    $currentMonth = date('Y-m');
    $attendanceStmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM attendance WHERE employee_id = :employee_id AND DATE_FORMAT(attendance_date, '%Y-%m') = :month GROUP BY status");
    $attendanceStmt->execute(['employee_id' => $employeeId, 'month' => $currentMonth]);
    $attendanceRows = $attendanceStmt->fetchAll();
    $attendanceSummary = ['Present' => 0, 'Absent' => 0, 'Leave' => 0, 'Late' => 0];
    foreach ($attendanceRows as $row) {
        $status = (string)$row['status'];
        $attendanceSummary[$status] = (int)$row['total'];
    }

    $leaveStmt = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN COALESCE(leave_category,'Paid')='Paid' AND status='Approved' THEN DATEDIFF(COALESCE(to_date,end_date), COALESCE(from_date,start_date))+1 ELSE 0 END), 0) AS paid_days,
        COALESCE(SUM(CASE WHEN COALESCE(leave_category,'Half Paid')='Half Paid' AND status='Approved' THEN DATEDIFF(COALESCE(to_date,end_date), COALESCE(from_date,start_date))+1 ELSE 0 END), 0) AS half_paid_days
        FROM leaves
        WHERE employee_id = :employee_id AND YEAR(COALESCE(from_date,start_date)) = YEAR(CURDATE())");
    $leaveStmt->execute(['employee_id' => $employeeId]);
    $leaveStat = $leaveStmt->fetch() ?: [];
    $annualLeaveLimit = (float)($employee['paid_leave_limit'] ?? 12);
    $halfLeaveLimit = (float)($employee['half_paid_leave_limit'] ?? 6);
    $approvedDays = (float)($leaveStat['paid_days'] ?? 0);
    $halfPaidDays = (float)($leaveStat['half_paid_days'] ?? 0);
    $leaveBalance = max(0, $annualLeaveLimit - $approvedDays);

    $salaryStmt = $pdo->prepare('SELECT * FROM salaries WHERE employee_id = :employee_id ORDER BY month_year DESC, id DESC LIMIT 1');
    $salaryStmt->execute(['employee_id' => $employeeId]);
    $latestSalary = $salaryStmt->fetch();

    $leaveAlertStmt = $pdo->prepare('SELECT from_date, to_date, status, created_at FROM leaves WHERE employee_id = :employee_id ORDER BY id DESC LIMIT 3');
    $leaveAlertStmt->execute(['employee_id' => $employeeId]);
    $recentLeaves = $leaveAlertStmt->fetchAll();

    $notifications = [];
    if ($latestSalary) {
        $notifications[] = 'Salary generated for ' . $latestSalary['month_year'] . ' (Net: INR ' . number_format((float)$latestSalary['net_salary'], 2) . ').';
    }
    foreach ($recentLeaves as $leaveItem) {
        $notifications[] = 'Leave ' . strtolower((string)$leaveItem['status']) . ' for ' . $leaveItem['from_date'] . ' to ' . $leaveItem['to_date'] . '.';
    }
    $notifications[] = 'Shift timings and monthly policies are updated by HR every Monday.';
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Employee Dashboard</h4>
        <p class="text-muted mb-0">Welcome <?= e($_SESSION['user']['name'] ?? 'Employee') ?>, here is your account overview.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="logout.php">Logout</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-warning"><?= e($error) ?></div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">My Attendance (<?= e(date('M Y')) ?>)</small>
                    <h3 class="mt-2 mb-1"><?= e((string)array_sum($attendanceSummary)) ?></h3>
                    <p class="small mb-0 text-muted">Present: <?= e((string)$attendanceSummary['Present']) ?> | Absent: <?= e((string)$attendanceSummary['Absent']) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Leave Balance (Paid/Half Paid)</small>
                    <h3 class="mt-2 mb-1"><?= e((string)$leaveBalance) ?> days</h3>
                    <p class="small mb-0 text-muted">Paid used: <?= e((string)$approvedDays) ?>/<?= e((string)$annualLeaveLimit) ?> | Half Paid: <?= e((string)$halfPaidDays) ?>/<?= e((string)$halfLeaveLimit) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Latest Salary</small>
                    <h3 class="mt-2 mb-1"><?= e($latestSalary ? 'INR ' . number_format((float)$latestSalary['net_salary'], 2) : 'N/A') ?></h3>
                    <p class="small mb-0 text-muted"><?= e($latestSalary['month_year'] ?? 'No salary generated yet') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Notifications</small>
                    <h3 class="mt-2 mb-1"><?= e((string)count($notifications)) ?></h3>
                    <p class="small mb-0 text-muted">Latest updates from payroll and leave.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Recent Notifications</strong></div>
        <div class="card-body">
            <?php if (!$notifications): ?>
                <p class="text-muted mb-0">No notifications available.</p>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($notifications as $message): ?>
                        <li class="mb-2"><?= e($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
