<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attendance_workflow.php';

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dash_punch'])) {
    verify_csrf();
    try {
        $emp = require_employee_record($pdo);
        if (($emp['employee_type'] ?? 'Staff') !== 'Staff') {
            throw new RuntimeException('Punch is only for staff.');
        }
        $today = date('Y-m-d');
        if ($_POST['dash_punch'] === 'in') {
            staff_record_punch_in($pdo, $emp, $today);
            set_flash('success', 'Punch in recorded.');
        } elseif ($_POST['dash_punch'] === 'out') {
            staff_record_punch_out($pdo, $emp, $today);
            set_flash('success', 'Punch out recorded.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('employee/dashboard');
}

$error = '';
$todayAttendance = null;

try {
    $employee = require_employee_record($pdo);
    $employeeId = (int)$employee['id'];

    $currentMonth = date('Y-m');
    $attendanceStmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM attendance WHERE employee_id = :employee_id AND DATE_FORMAT(attendance_date, '%Y-%m') = :month GROUP BY status");
    $attendanceStmt->execute(['employee_id' => $employeeId, 'month' => $currentMonth]);
    $attendanceRows = $attendanceStmt->fetchAll();
    $attendanceSummary = [];
    $attendanceDaysRecorded = 0;
    foreach ($attendanceRows as $row) {
        $status = (string)$row['status'];
        $attendanceSummary[$status] = (int)$row['total'];
        $attendanceDaysRecorded += (int)$row['total'];
    }

    if (($employee['employee_type'] ?? 'Staff') === 'Staff') {
        $todayAttendance = staff_today_attendance_row($pdo, $employeeId, date('Y-m-d'));
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

<header class="erp-page__top">
    <div>
        <h1 class="erp-page__title">Employee Dashboard<span>Welcome <?= e($_SESSION['user']['name'] ?? 'Employee') ?> — your account overview</span></h1>
    </div>
    <div class="erp-page__top-actions">
        <a class="btn btn-outline-secondary btn-sm" href="logout.php">Logout</a>
    </div>
</header>

<?php if ($error): ?>
    <div class="alert alert-warning"><?= e($error) ?></div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <small class="text-muted">My Attendance (<?= e(date('M Y')) ?>)</small>
                    <h3 class="mt-2 mb-1"><?= e((string)$attendanceDaysRecorded) ?></h3>
                    <p class="small mb-0 text-muted">Present: <?= e((string)($attendanceSummary['Present'] ?? 0)) ?> | Late: <?= e((string)($attendanceSummary['Late'] ?? 0)) ?> | Absent: <?= e((string)($attendanceSummary['Absent'] ?? 0)) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <small class="text-muted">Leave Balance (Paid/Half Paid)</small>
                    <h3 class="mt-2 mb-1"><?= e((string)$leaveBalance) ?> days</h3>
                    <p class="small mb-0 text-muted">Paid used: <?= e((string)$approvedDays) ?>/<?= e((string)$annualLeaveLimit) ?> | Half Paid: <?= e((string)$halfPaidDays) ?>/<?= e((string)$halfLeaveLimit) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <small class="text-muted">Latest Salary</small>
                    <h3 class="mt-2 mb-1"><?= e($latestSalary ? 'INR ' . number_format((float)$latestSalary['net_salary'], 2) : 'N/A') ?></h3>
                    <p class="small mb-0 text-muted"><?= e($latestSalary['month_year'] ?? 'No salary generated yet') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <small class="text-muted">Notifications</small>
                    <h3 class="mt-2 mb-1"><?= e((string)count($notifications)) ?></h3>
                    <p class="small mb-0 text-muted">Latest updates from payroll and leave.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (($employee['employee_type'] ?? 'Staff') === 'Staff'): ?>
    <div class="card shadow-sm mb-4 border-primary border-opacity-25">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong>Today — Punch In / Punch Out</strong>
            <span class="small text-muted">Staff only</span>
        </div>
        <div class="card-body">
            <div class="row align-items-center g-3">
                <div class="col-md-8">
                    <p class="mb-1"><strong>Status:</strong> <?= e((string)($todayAttendance['status'] ?? 'Not marked')) ?></p>
                    <p class="mb-0 small text-muted">
                        <?php if ($todayAttendance && !empty($todayAttendance['punch_in_time'])): ?>
                            In: <?= e((string)$todayAttendance['punch_in_time']) ?>
                        <?php else: ?>
                            No punch in yet
                        <?php endif; ?>
                        <?php if ($todayAttendance && !empty($todayAttendance['punch_out_time'])): ?>
                            &nbsp;· Out: <?= e((string)$todayAttendance['punch_out_time']) ?>
                        <?php endif; ?>
                        <?php if ($todayAttendance && isset($todayAttendance['total_hours']) && $todayAttendance['total_hours'] !== null && $todayAttendance['total_hours'] !== ''): ?>
                            &nbsp;· Hours: <?= e((string)$todayAttendance['total_hours']) ?> (OT <?= e((string)($todayAttendance['overtime_hours'] ?? 0)) ?>)
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end d-flex flex-wrap gap-2 justify-content-md-end">
                    <form method="post" class="m-0">
                        <?= csrf_input() ?>
                        <input type="hidden" name="dash_punch" value="in">
                        <button type="submit" class="btn btn-success btn-sm">Punch In</button>
                    </form>
                    <form method="post" class="m-0">
                        <?= csrf_input() ?>
                        <input type="hidden" name="dash_punch" value="out">
                        <button type="submit" class="btn btn-primary btn-sm">Punch Out</button>
                    </form>
                </div>
            </div>
            <p class="small text-muted mt-3 mb-0"><a href="<?= e(route_url('employee/attendance')) ?>">Full attendance calendar →</a></p>
        </div>
    </div>
    <?php endif; ?>

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
