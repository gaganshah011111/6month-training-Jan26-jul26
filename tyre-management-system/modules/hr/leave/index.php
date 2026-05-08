<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/db.php';
$pdo = Database::connection();
if (!has_role(['Super Admin','HR Manager'])) { echo 'Access denied'; return; }
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        try {
            $employeeId = post_int('employee_id');
            $empStmt = $pdo->prepare('SELECT employee_type FROM employees WHERE id=:id');
            $empStmt->execute(['id' => $employeeId]);
            $employee = $empStmt->fetch() ?: [];
            $employeeType = (string)($employee['employee_type'] ?? 'Staff');
            $leaveCategory = post_string('leave_category', 20) ?: 'Paid';
            if ($employeeType === 'Worker' && $leaveCategory !== 'Unpaid') {
                $leaveCategory = 'Unpaid';
            }
            $isPaid = $leaveCategory === 'Paid' ? 1 : 0;
            $stmt = $pdo->prepare('INSERT INTO leaves(employee_id,from_date,to_date,start_date,end_date,leave_type,leave_category,reason,is_paid,status) VALUES(:e,:f,:t,:sd,:ed,:lt,:lc,:r,:ip,:s)');
            $stmt->execute([
                'e' => $employeeId,
                'f' => $_POST['from_date'],
                't' => $_POST['to_date'],
                'sd' => $_POST['from_date'],
                'ed' => $_POST['to_date'],
                'lt' => post_string('leave_type', 50),
                'lc' => $leaveCategory,
                'r' => post_string('reason'),
                'ip' => $isPaid,
                's' => 'Applied'
            ]);
            set_flash('success', 'Leave request created.');
        } catch (Throwable $e) {
            set_flash('danger', 'Leave request failed: ' . $e->getMessage());
        }
    } elseif ($action === 'status') {
        $leaveId = post_int('id');
        $status = post_string('status', 20);
        $pdo->beginTransaction();
        try {
            $leaveStmt = $pdo->prepare('SELECT * FROM leaves WHERE id=:id FOR UPDATE');
            $leaveStmt->execute(['id' => $leaveId]);
            $leave = $leaveStmt->fetch();
            if ($leave) {
                $pdo->prepare('UPDATE leaves SET status=:s WHERE id=:id')->execute(['s' => $status, 'id' => $leaveId]);
                if ($status === 'Approved') {
                    $rangeStmt = $pdo->prepare("INSERT INTO attendance(employee_id, attendance_date, shift, status, remarks)
                        SELECT :eid, d.day_date, 'Morning', 'Leave', 'Approved leave'
                        FROM (
                            SELECT DATE_ADD(:fromDate, INTERVAL seq.day DAY) AS day_date
                            FROM (
                                SELECT 0 day UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
                                SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL
                                SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL
                                SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL
                                SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL
                                SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30
                            ) seq
                        ) d
                        WHERE d.day_date BETWEEN :fromDate AND :toDate
                        ON DUPLICATE KEY UPDATE status='Leave', remarks='Approved leave'");
                    $fromDate = (string)($leave['from_date'] ?? $leave['start_date'] ?? '');
                    $toDate = (string)($leave['to_date'] ?? $leave['end_date'] ?? '');
                    $rangeStmt->execute(['eid' => (int)$leave['employee_id'], 'fromDate' => $fromDate, 'toDate' => $toDate]);
                }
            }
            $pdo->commit();
            set_flash('success', 'Leave status updated.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            set_flash('danger', 'Leave action failed: ' . $e->getMessage());
        }
    }
    redirect('leave/list');
}
$emps = $pdo->query('SELECT id, full_name FROM employees ORDER BY full_name')->fetchAll();
$rows = $pdo->query("SELECT l.*, e.full_name,
    COALESCE(l.from_date, l.start_date) AS leave_from,
    COALESCE(l.to_date, l.end_date) AS leave_to
    FROM leaves l
    JOIN employees e ON e.id=l.employee_id
    ORDER BY l.id DESC LIMIT 60")->fetchAll();
?>
<h4>Leave Management</h4>
<form method="post" class="row g-2 mb-3">
<?= csrf_input() ?>
<input type="hidden" name="action" value="create">
<div class="col"><select class="form-select" name="employee_id"><?php foreach($emps as $e): ?><option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col"><input class="form-control" type="date" name="from_date" required></div>
<div class="col"><input class="form-control" type="date" name="to_date" required></div>
<div class="col"><input class="form-control" name="leave_type" placeholder="Type (Casual/Sick)" required></div>
<div class="col"><select class="form-select" name="leave_category"><option>Paid</option><option>Half Paid</option><option>Unpaid</option></select></div>
<div class="col"><input class="form-control" name="reason" placeholder="Reason" required></div>
<div class="col"><button class="btn btn-primary w-100">Submit</button></div>
</form>
<table class="table table-sm align-middle"><tr><th>Employee</th><th>Duration</th><th>Type</th><th>Category</th><th>Paid</th><th>Status</th><th>Action</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['full_name']) ?></td><td><?= e(((string)($r['leave_from'] ?? '')) . ' to ' . ((string)($r['leave_to'] ?? ''))) ?></td><td><?= e((string)($r['leave_type'] ?? 'Casual')) ?></td><td><?= e((string)($r['leave_category'] ?? 'Paid')) ?></td><td><?= (int)($r['is_paid'] ?? 0) === 1 ? 'Yes' : 'No' ?></td><td><?= e((string)($r['status'] ?? 'Applied')) ?></td><td class="d-flex gap-1"><?php if (($r['status'] ?? 'Applied') === 'Applied'): ?><form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="status" value="Approved"><button class="btn btn-sm btn-outline-success">Approve</button></form><form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="status" value="Rejected"><button class="btn btn-sm btn-outline-danger">Reject</button></form><?php endif; ?></td></tr><?php endforeach; ?></table>
