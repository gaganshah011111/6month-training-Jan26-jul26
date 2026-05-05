<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = Database::connection();
$error = '';
$attendanceRows = [];
$calendarData = [];
$selectedMonth = (string)($_GET['month'] ?? date('Y-m'));

try {
    $employee = require_employee_record($pdo);
    $employeeId = (int)$employee['id'];

    if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
        $selectedMonth = date('Y-m');
    }

    $stmt = $pdo->prepare("SELECT attendance_date, shift, status FROM attendance WHERE employee_id = :employee_id AND DATE_FORMAT(attendance_date, '%Y-%m') = :month ORDER BY attendance_date DESC");
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

<?php if (!$error): ?>
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
                </tr>
                </thead>
                <tbody>
                <?php if (!$attendanceRows): ?>
                    <tr><td colspan="3" class="text-center text-muted">No attendance found for this month.</td></tr>
                <?php else: ?>
                    <?php foreach ($attendanceRows as $row): ?>
                        <tr>
                            <td><?= e($row['attendance_date']) ?></td>
                            <td><?= e($row['shift']) ?></td>
                            <td><?= e($row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
