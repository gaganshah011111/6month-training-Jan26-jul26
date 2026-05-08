<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/payroll_logic.php';
if (!has_role(['Super Admin','HR Manager'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    try {
        if ($action === 'create') {
            $employeeId = post_int('employee_id');
            $empStmt = $pdo->prepare('SELECT employee_type FROM employees WHERE id=:id');
            $empStmt->execute(['id' => $employeeId]);
            $employeeType = (string)($empStmt->fetchColumn() ?: 'Staff');
            $status = post_string('status', 20);
            if ($employeeType === 'Worker' && $status === 'Leave') {
                $status = 'Absent';
            }
            $stmt = $pdo->prepare('INSERT INTO attendance(employee_id,attendance_date,shift,status,remarks) VALUES(:e,:d,:s,:st,:rm) ON DUPLICATE KEY UPDATE shift=VALUES(shift), status=VALUES(status), remarks=VALUES(remarks)');
            $stmt->execute(['e'=>$employeeId,'d'=>$_POST['attendance_date'],'s'=>post_string('shift', 30),'st'=>$status,'rm'=>post_string('remarks')]);
            set_flash('success', 'Attendance saved.');
        } elseif ($action === 'update') {
            $stmt = $pdo->prepare('UPDATE attendance SET shift=:s, status=:st, remarks=:rm, punch_in_time=:pit, punch_out_time=:pot, total_hours=:th, overtime_hours=:oh, is_emergency_duty=:ied WHERE id=:id');
            $punchIn = (string)($_POST['punch_in_time'] ?? '');
            $punchOut = (string)($_POST['punch_out_time'] ?? '');
            $totalHours = compute_work_hours($punchIn, $punchOut);
            $overtime = max(0, $totalHours - 8);
            $stmt->execute([
                'id'=>post_int('id'),
                's'=>post_string('shift', 30),
                'st'=>post_string('status', 20),
                'rm'=>post_string('remarks'),
                'pit'=>$punchIn !== '' ? $punchIn : null,
                'pot'=>$punchOut !== '' ? $punchOut : null,
                'th'=>$totalHours,
                'oh'=>$overtime,
                'ied'=>isset($_POST['is_emergency_duty']) ? 1 : 0
            ]);
            set_flash('success', 'Attendance updated.');
        }
    } catch (Throwable $e) {
        set_flash('danger', 'Attendance action failed: ' . $e->getMessage());
    }
    redirect('attendance/list');
}
$emps = $pdo->query('SELECT id, full_name, employee_type FROM employees ORDER BY full_name')->fetchAll();
$filter = trim((string)($_GET['month'] ?? date('Y-m')));
$rowsStmt = $pdo->prepare("SELECT a.*, e.full_name FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE DATE_FORMAT(a.attendance_date, '%Y-%m') = :month ORDER BY attendance_date DESC, a.id DESC LIMIT 200");
$rowsStmt->execute(['month' => $filter]);
$rows = $rowsStmt->fetchAll();

$summaryStmt = $pdo->query("SELECT
    SUM(CASE WHEN attendance_date = CURDATE() AND status='Present' THEN 1 ELSE 0 END) AS present_today,
    SUM(CASE WHEN attendance_date = CURDATE() AND status='Absent' THEN 1 ELSE 0 END) AS absent_today,
    SUM(CASE WHEN attendance_date = CURDATE() AND status='Late' THEN 1 ELSE 0 END) AS late_today,
    SUM(CASE WHEN attendance_date = CURDATE() AND overtime_hours > 0 THEN 1 ELSE 0 END) AS overtime_today
    FROM attendance");
$summary = $summaryStmt->fetch() ?: [];
?>
<div class="module-shell">
    <h4 class="mb-3">Attendance</h4>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card kpi-card"><div class="card-body"><small class="text-muted">Present Today</small><h4 class="mb-0"><?= e((string)($summary['present_today'] ?? 0)) ?></h4></div></div></div>
        <div class="col-md-3"><div class="card kpi-card"><div class="card-body"><small class="text-muted">Absent Today</small><h4 class="mb-0"><?= e((string)($summary['absent_today'] ?? 0)) ?></h4></div></div></div>
        <div class="col-md-3"><div class="card kpi-card"><div class="card-body"><small class="text-muted">Late Employees</small><h4 class="mb-0"><?= e((string)($summary['late_today'] ?? 0)) ?></h4></div></div></div>
        <div class="col-md-3"><div class="card kpi-card"><div class="card-body"><small class="text-muted">Overtime Employees</small><h4 class="mb-0"><?= e((string)($summary['overtime_today'] ?? 0)) ?></h4></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header section-title">Mark Attendance</div>
        <div class="card-body">
            <form method="post" class="row g-2">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="create">
                <div class="col-lg-3 col-md-6"><select class="form-select" name="employee_id"><?php foreach($emps as $e): ?><option value="<?= $e['id'] ?>"><?= e($e['full_name'] . ' (' . ($e['employee_type'] ?? 'Staff') . ')') ?></option><?php endforeach; ?></select></div>
                <div class="col-lg-2 col-md-6"><input type="date" class="form-control" name="attendance_date" required></div>
                <div class="col-lg-2 col-md-4"><select class="form-select" name="shift"><option>Morning</option><option>Evening</option><option>Night</option></select></div>
                <div class="col-lg-2 col-md-4"><select class="form-select" name="status"><option>Present</option><option>Absent</option><option>Late</option><option>Half Day</option><option>Leave</option></select></div>
                <div class="col-lg-2 col-md-4"><input class="form-control" name="remarks" placeholder="Remarks"></div>
                <div class="col-lg-1 col-md-12"><button class="btn btn-primary w-100">Mark</button></div>
            </form>
        </div>
    </div>

    <form method="get" class="row g-2 mb-2"><input type="hidden" name="page" value="attendance/list"><div class="col-md-3"><input type="month" class="form-control" name="month" value="<?= e($filter) ?>"></div><div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div></form>
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead>
        <tr><th>Date</th><th>Employee</th><th>Shift</th><th>Status</th><th>Punch In</th><th>Punch Out</th><th>Total Hrs</th><th>OT Hrs</th><th>Emergency Duty</th><th>Remarks</th><th>Update</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="11" class="text-center text-muted">No attendance records found.</td></tr>
        <?php endif; ?>
        <?php foreach($rows as $r): ?>
            <tr>
                <td><?= e($r['attendance_date']) ?></td>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e($r['shift']) ?></td>
                <td>
                    <?php
                    $status = (string)($r['status'] ?? '');
                    $statusClass = match ($status) {
                        'Late' => 'chip-late',
                        'Absent' => 'chip-absent',
                        default => 'bg-success',
                    };
                    ?>
                    <span class="badge <?= e($statusClass) ?>"><?= e($status) ?></span>
                </td>
                <td><?= e((string)($r['punch_in_time'] ?? '-')) ?></td>
                <td><?= e((string)($r['punch_out_time'] ?? '-')) ?></td>
                <td><?= e((string)($r['total_hours'] ?? 0)) ?></td>
                <td><span class="badge chip-overtime"><?= e((string)($r['overtime_hours'] ?? 0)) ?></span></td>
                <td><?= (int)($r['is_emergency_duty'] ?? 0) === 1 ? 'Yes' : 'No' ?></td>
                <td><?= e((string)($r['remarks'] ?? '')) ?></td>
                <td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#attEdit<?= (int)$r['id'] ?>">Edit</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php foreach($rows as $r): ?>
    <div class="modal fade" id="attEdit<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="post" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Attendance - <?= e($r['full_name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-2">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <div class="col-md-6">
                        <label class="form-label">Shift</label>
                        <select class="form-select" name="shift">
                            <option <?= $r['shift']==='Morning'?'selected':'' ?>>Morning</option>
                            <option <?= $r['shift']==='Evening'?'selected':'' ?>>Evening</option>
                            <option <?= $r['shift']==='Night'?'selected':'' ?>>Night</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option <?= $r['status']==='Present'?'selected':'' ?>>Present</option>
                            <option <?= $r['status']==='Absent'?'selected':'' ?>>Absent</option>
                            <option <?= $r['status']==='Late'?'selected':'' ?>>Late</option>
                            <option <?= $r['status']==='Half Day'?'selected':'' ?>>Half Day</option>
                            <option <?= $r['status']==='Leave'?'selected':'' ?>>Leave</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Punch In</label>
                        <input class="form-control" type="datetime-local" name="punch_in_time" value="<?= !empty($r['punch_in_time']) ? e(date('Y-m-d\TH:i', strtotime((string)$r['punch_in_time']))) : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Punch Out</label>
                        <input class="form-control" type="datetime-local" name="punch_out_time" value="<?= !empty($r['punch_out_time']) ? e(date('Y-m-d\TH:i', strtotime((string)$r['punch_out_time']))) : '' ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Remarks</label>
                        <input class="form-control" name="remarks" value="<?= e((string)($r['remarks'] ?? '')) ?>" placeholder="Remarks">
                    </div>
                    <div class="col-md-12 form-check mt-2 ms-1">
                        <input class="form-check-input" type="checkbox" id="emergency<?= (int)$r['id'] ?>" name="is_emergency_duty" <?= (int)($r['is_emergency_duty'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="emergency<?= (int)$r['id'] ?>">Emergency Duty</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>
