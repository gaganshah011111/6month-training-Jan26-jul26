<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/payroll_logic.php';

$pdo = Database::connection();
$error = '';
$attendanceRows = [];
$calendarData = [];
$selectedMonth = (string)($_GET['month'] ?? date('Y-m'));
$success = '';

try {
    $employee = require_employee_record($pdo);
    $employeeId = (int)$employee['id'];
    $shiftTiming = (string)($employee['shift_timing'] ?? '09:00-18:00');

    if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
        $selectedMonth = date('Y-m');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        $today = date('Y-m-d');

        if ($action === 'punch_in') {
            $stmt = $pdo->prepare("INSERT INTO attendance(employee_id,attendance_date,shift,status,remarks,punch_in_time) VALUES(:employee_id,:attendance_date,:shift,'Present','Punch in recorded',:punch_in_time)
                ON DUPLICATE KEY UPDATE punch_in_time=IFNULL(punch_in_time, VALUES(punch_in_time)), remarks='Punch in recorded', status=IF(status='Absent','Late',status)");
            $stmt->execute([
                'employee_id' => $employeeId,
                'attendance_date' => $today,
                'shift' => 'Morning',
                'punch_in_time' => date('Y-m-d H:i:s'),
            ]);
            $success = 'Punch in recorded.';
        } elseif ($action === 'punch_out') {
            $rowStmt = $pdo->prepare('SELECT id, punch_in_time, attendance_date FROM attendance WHERE employee_id=:employee_id AND attendance_date=:attendance_date LIMIT 1');
            $rowStmt->execute(['employee_id' => $employeeId, 'attendance_date' => $today]);
            $attendanceToday = $rowStmt->fetch();
            if ($attendanceToday) {
                $punchOut = date('Y-m-d H:i:s');
                $totalHours = compute_work_hours((string)$attendanceToday['punch_in_time'], $punchOut);
                $overtimeHours = max(0, $totalHours - 8);
                $isLate = ((strtotime((string)$attendanceToday['punch_in_time']) ?: 0) > strtotime($today . ' 09:15:00')) ? 1 : 0;
                $isSunday = (int)date('w', strtotime($today)) === 0;
                if ($isSunday) {
                    $overtimeHours += $totalHours;
                }
                $status = $totalHours < 4 ? 'Half Day' : ($isLate ? 'Late' : 'Present');

                $updateStmt = $pdo->prepare('UPDATE attendance SET punch_out_time=:punch_out_time,total_hours=:total_hours,overtime_hours=:overtime_hours,is_late=:is_late,is_emergency_duty=:ied,status=:status,remarks=:remarks WHERE id=:id');
                $updateStmt->execute([
                    'punch_out_time' => $punchOut,
                    'total_hours' => $totalHours,
                    'overtime_hours' => $overtimeHours,
                    'is_late' => $isLate,
                    'ied' => $isSunday ? 1 : 0,
                    'status' => $status,
                    'remarks' => $isSunday ? 'Emergency duty (Sunday)' : ($overtimeHours > 0 ? 'Overtime applied' : 'Punch out recorded'),
                    'id' => (int)$attendanceToday['id'],
                ]);
                $success = 'Punch out recorded. Total hours calculated.';
            } else {
                $error = 'Punch in first, then punch out.';
            }
        }
    }

    $stmt = $pdo->prepare("SELECT attendance_date, shift, status, punch_in_time, punch_out_time, total_hours, overtime_hours FROM attendance WHERE employee_id = :employee_id AND DATE_FORMAT(attendance_date, '%Y-%m') = :month ORDER BY attendance_date DESC");
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
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<?php if (!$error): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Punch In / Punch Out</strong></div>
        <div class="card-body d-flex gap-2 align-items-center">
            <span class="text-muted small">Shift Timing: <?= e($shiftTiming) ?></span>
            <form method="post" class="m-0"><?= csrf_input() ?><input type="hidden" name="action" value="punch_in"><button class="btn btn-success btn-sm">Punch In</button></form>
            <form method="post" class="m-0"><?= csrf_input() ?><input type="hidden" name="action" value="punch_out"><button class="btn btn-primary btn-sm">Punch Out</button></form>
        </div>
    </div>

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
                    } elseif ($status === 'Leave') {
                        $badgeClass = 'warning text-dark';
                    } elseif ($status === 'Late') {
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
                </tr>
                </thead>
                <tbody>
                <?php if (!$attendanceRows): ?>
                    <tr><td colspan="7" class="text-center text-muted">No attendance found for this month.</td></tr>
                <?php else: ?>
                    <?php foreach ($attendanceRows as $row): ?>
                        <tr>
                            <td><?= e($row['attendance_date']) ?></td>
                            <td><?= e($row['shift']) ?></td>
                            <td><?= e($row['status']) ?></td>
                            <td><?= e((string)($row['punch_in_time'] ?? '-')) ?></td>
                            <td><?= e((string)($row['punch_out_time'] ?? '-')) ?></td>
                            <td><?= e((string)($row['total_hours'] ?? 0)) ?></td>
                            <td><?= e((string)($row['overtime_hours'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
