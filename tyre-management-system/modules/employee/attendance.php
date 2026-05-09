<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/attendance_workflow.php';

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $employee = require_employee_record($pdo);
        if (($employee['employee_type'] ?? 'Staff') !== 'Staff') {
            throw new RuntimeException('Punch in/out is only for staff.');
        }
        $action = (string)($_POST['action'] ?? '');
        $today = date('Y-m-d');
        if ($action === 'punch_in') {
            staff_record_punch_in($pdo, $employee, $today);
            set_flash('success', 'Punch in recorded.');
        } elseif ($action === 'punch_out') {
            staff_record_punch_out($pdo, $employee, $today);
            set_flash('success', 'Punch out recorded. Hours updated.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    $month = preg_match('/^\d{4}-\d{2}$/', (string)($_POST['redirect_month'] ?? '')) ? (string)$_POST['redirect_month'] : date('Y-m');
    header('Location: index.php?page=' . rawurlencode('employee/attendance') . '&month=' . rawurlencode($month));
    exit;
}

$error = '';
$attendanceRows = [];
$calendarData = [];
$selectedMonth = (string)($_GET['month'] ?? date('Y-m'));
$employee = null;
$todayRow = null;

try {
    $employee = require_employee_record($pdo);
    $employeeId = (int)$employee['id'];
    $shiftTiming = (string)($employee['shift_timing'] ?? '09:00-18:00');
    $isStaff = ($employee['employee_type'] ?? 'Staff') === 'Staff';

    if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
        $selectedMonth = date('Y-m');
    }

    if ($isStaff) {
        $todayRow = staff_today_attendance_row($pdo, $employeeId, date('Y-m-d'));
    }

    $stmt = $pdo->prepare("SELECT attendance_date, shift, status, punch_in_time, punch_out_time, total_hours, overtime_hours, is_late, is_early_exit, is_emergency_duty FROM attendance WHERE employee_id = :employee_id AND DATE_FORMAT(attendance_date, '%Y-%m') = :month ORDER BY attendance_date DESC");
    $stmt->execute(['employee_id' => $employeeId, 'month' => $selectedMonth]);
    $attendanceRows = $stmt->fetchAll();

    foreach ($attendanceRows as $row) {
        $calendarData[$row['attendance_date']] = $row['status'];
    }
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
}

$daysInMonth = (int)date('t', strtotime($selectedMonth . '-01'));
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">My Attendance</h4>
    <form class="d-flex gap-2" method="get">
        <input type="hidden" name="page" value="employee/attendance">
        <input type="month" class="form-control" name="month" value="<?= e($selectedMonth) ?>">
        <button class="btn btn-outline-primary" type="submit">Filter</button>
    </form>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if (!$error && $employee && ($employee['employee_type'] ?? 'Staff') === 'Staff'): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Punch In / Punch Out</strong></div>
        <div class="card-body">
            <p class="small text-muted mb-2">Shift reference: <?= e($shiftTiming) ?> (late / half-day / OT are calculated from your profile shift times when you punch out).</p>
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <div class="small text-uppercase text-muted mb-1">Today</div>
                    <p class="mb-0">
                        <strong>Status:</strong> <?= e((string)($todayRow['status'] ?? '—')) ?>
                        <?php if ($todayRow && !empty($todayRow['punch_in_time'])): ?>
                            &nbsp;| <strong>In:</strong> <?= e((string)$todayRow['punch_in_time']) ?>
                        <?php endif; ?>
                        <?php if ($todayRow && !empty($todayRow['punch_out_time'])): ?>
                            &nbsp;| <strong>Out:</strong> <?= e((string)$todayRow['punch_out_time']) ?>
                        <?php endif; ?>
                        <?php if ($todayRow && isset($todayRow['total_hours']) && $todayRow['total_hours'] !== null && $todayRow['total_hours'] !== ''): ?>
                            &nbsp;| <strong>Hours:</strong> <?= e((string)$todayRow['total_hours']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end d-flex flex-wrap gap-2 justify-content-md-end">
                    <form method="post" class="m-0">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="punch_in">
                        <input type="hidden" name="redirect_month" value="<?= e($selectedMonth) ?>">
                        <button class="btn btn-success btn-sm" type="submit">Punch In</button>
                    </form>
                    <form method="post" class="m-0">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="punch_out">
                        <input type="hidden" name="redirect_month" value="<?= e($selectedMonth) ?>">
                        <button class="btn btn-primary btn-sm" type="submit">Punch Out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php elseif (!$error && $employee): ?>
    <div class="alert alert-info">Your attendance is maintained by HR (manual worker records).</div>
<?php endif; ?>

<?php if (!$error && $employee): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Monthly Calendar View</strong></div>
        <div class="card-body">
            <div class="row g-2">
                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                    <?php $dateValue = $selectedMonth . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT); ?>
                    <?php $status = $calendarData[$dateValue] ?? '-'; ?>
                    <?php
                    $badgeClass = 'secondary';
                    if ($status === 'Present') {
                        $badgeClass = 'success';
                    } elseif ($status === 'Absent') {
                        $badgeClass = 'danger';
                    } elseif (in_array($status, ['Paid Leave', 'Unpaid Leave', 'Leave'], true)) {
                        $badgeClass = 'warning text-dark';
                    } elseif ($status === 'Late') {
                        $badgeClass = 'info text-dark';
                    } elseif ($status === 'Half Day') {
                        $badgeClass = 'warning text-dark';
                    } elseif ($status === 'Emergency Duty') {
                        $badgeClass = 'danger';
                    } elseif ($status === 'Holiday') {
                        $badgeClass = 'info text-dark';
                    }
                    ?>
                    <div class="col-6 col-md-3 col-lg-2">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted"><?= e((string)$day) ?></div>
                            <span class="badge bg-<?= e($badgeClass) ?> mt-1"><?= e($status) ?></span>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Attendance History</strong></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Shift</th>
                    <th>Status</th>
                    <th>Punch In</th>
                    <th>Punch Out</th>
                    <th>Total Hours</th>
                    <th>Overtime</th>
                    <th>Late</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$attendanceRows): ?>
                    <tr><td colspan="8" class="text-center text-muted">No attendance found for this month.</td></tr>
                <?php else: ?>
                    <?php foreach ($attendanceRows as $row): ?>
                        <tr>
                            <td><?= e($row['attendance_date']) ?></td>
                            <td><?= e($row['shift']) ?></td>
                            <td><?= e($row['status']) ?></td>
                            <td><?= !empty($row['punch_in_time']) ? e((string)$row['punch_in_time']) : '—' ?></td>
                            <td><?= !empty($row['punch_out_time']) ? e((string)$row['punch_out_time']) : '—' ?></td>
                            <td><?= isset($row['total_hours']) && $row['total_hours'] !== null && $row['total_hours'] !== '' ? e((string)$row['total_hours']) : '—' ?></td>
                            <td><?= e((string)($row['overtime_hours'] ?? 0)) ?></td>
                            <td><?= ((int)($row['is_late'] ?? 0) === 1 || ($row['status'] ?? '') === 'Late') ? 'Yes' : 'No' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
