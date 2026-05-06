<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
if (!has_role(['Super Admin','HR Manager'])) { echo 'Access denied'; return; }
$pdo = Database::connection();
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    try {
        if ($action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO attendance(employee_id,attendance_date,shift,status,remarks) VALUES(:e,:d,:s,:st,:rm) ON DUPLICATE KEY UPDATE shift=VALUES(shift), status=VALUES(status), remarks=VALUES(remarks)');
            $stmt->execute(['e'=>post_int('employee_id'),'d'=>$_POST['attendance_date'],'s'=>post_string('shift', 30),'st'=>post_string('status', 20),'rm'=>post_string('remarks')]);
            set_flash('success', 'Attendance saved.');
        } elseif ($action === 'update') {
            $stmt = $pdo->prepare('UPDATE attendance SET shift=:s, status=:st, remarks=:rm WHERE id=:id');
            $stmt->execute(['id'=>post_int('id'),'s'=>post_string('shift', 30),'st'=>post_string('status', 20),'rm'=>post_string('remarks')]);
            set_flash('success', 'Attendance updated.');
        }
    } catch (Throwable $e) {
        set_flash('danger', 'Attendance action failed: ' . $e->getMessage());
    }
    redirect('attendance/list');
}
$emps = $pdo->query('SELECT id, full_name FROM employees ORDER BY full_name')->fetchAll();
$filter = trim((string)($_GET['month'] ?? date('Y-m')));
$rowsStmt = $pdo->prepare("SELECT a.*, e.full_name FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE DATE_FORMAT(a.attendance_date, '%Y-%m') = :month ORDER BY attendance_date DESC, a.id DESC LIMIT 200");
$rowsStmt->execute(['month' => $filter]);
$rows = $rowsStmt->fetchAll();
?>
<h4>Attendance</h4>
<form method="post" class="row g-2 mb-3">
<?= csrf_input() ?>
<input type="hidden" name="action" value="create">
<div class="col"><select class="form-select" name="employee_id"><?php foreach($emps as $e): ?><option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col"><input type="date" class="form-control" name="attendance_date" required></div>
<div class="col"><select class="form-select" name="shift"><option>Morning</option><option>Evening</option><option>Night</option></select></div>
<div class="col"><select class="form-select" name="status"><option>Present</option><option>Absent</option><option>Late</option><option>Half Day</option><option>Leave</option></select></div>
<div class="col"><input class="form-control" name="remarks" placeholder="Remarks"></div>
<div class="col"><button class="btn btn-primary w-100">Mark</button></div>
</form>
<form method="get" class="row g-2 mb-2"><input type="hidden" name="page" value="attendance/list"><div class="col-md-3"><input type="month" class="form-control" name="month" value="<?= e($filter) ?>"></div><div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div></form>
<table class="table table-sm align-middle">
<tr><th>Date</th><th>Employee</th><th>Shift</th><th>Status</th><th>Remarks</th><th>Update</th></tr>
<?php foreach($rows as $r): ?>
<tr>
    <td><?= e($r['attendance_date']) ?></td>
    <td><?= e($r['full_name']) ?></td>
    <td><?= e($r['shift']) ?></td>
    <td><?= e($r['status']) ?></td>
    <td><?= e((string)($r['remarks'] ?? '')) ?></td>
    <td>
        <form method="post" class="d-flex gap-1 align-items-center flex-wrap">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <select class="form-select form-select-sm" name="shift" style="min-width:120px">
                <option <?= $r['shift']==='Morning'?'selected':'' ?>>Morning</option>
                <option <?= $r['shift']==='Evening'?'selected':'' ?>>Evening</option>
                <option <?= $r['shift']==='Night'?'selected':'' ?>>Night</option>
            </select>
            <select class="form-select form-select-sm" name="status" style="min-width:120px">
                <option <?= $r['status']==='Present'?'selected':'' ?>>Present</option>
                <option <?= $r['status']==='Absent'?'selected':'' ?>>Absent</option>
                <option <?= $r['status']==='Late'?'selected':'' ?>>Late</option>
                <option <?= $r['status']==='Half Day'?'selected':'' ?>>Half Day</option>
                <option <?= $r['status']==='Leave'?'selected':'' ?>>Leave</option>
            </select>
            <input class="form-control form-control-sm" name="remarks" value="<?= e((string)($r['remarks'] ?? '')) ?>" placeholder="Remarks" style="min-width:150px">
            <button class="btn btn-sm btn-primary">Save</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>
