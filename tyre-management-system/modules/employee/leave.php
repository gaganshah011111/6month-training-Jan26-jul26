<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = Database::connection();
$error = '';
$success = '';
$rows = [];

try {
    $employee = require_employee_record($pdo);
    $employeeId = (int)$employee['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $fromDate = (string)($_POST['from_date'] ?? '');
        $toDate = (string)($_POST['to_date'] ?? '');
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($fromDate === '' || $toDate === '' || $reason === '') {
            $error = 'All fields are required.';
        } elseif ($fromDate > $toDate) {
            $error = 'From date must be before or equal to To date.';
        } elseif (strlen($reason) > 255) {
            $error = 'Reason is too long.';
        } else {
            $leaveCategory = (string)($_POST['leave_category'] ?? 'Paid');
            $isPaid = $leaveCategory === 'Paid' ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO leaves(employee_id, from_date, to_date, start_date, end_date, leave_type, leave_category, reason, is_paid, status) VALUES(:employee_id, :from_date, :to_date, :start_date, :end_date, :leave_type, :leave_category, :reason, :is_paid, 'Applied')");
            $stmt->execute([
                'employee_id' => $employeeId,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'start_date' => $fromDate,
                'end_date' => $toDate,
                'leave_type' => 'General',
                'leave_category' => $leaveCategory,
                'reason' => $reason,
                'is_paid' => $isPaid,
            ]);
            $success = 'Leave request submitted successfully.';
        }
    }

    $pageNo = max(1, (int)($_GET['p'] ?? 1));
    $perPage = 10;
    $offset = ($pageNo - 1) * $perPage;

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM leaves WHERE employee_id = :employee_id');
    $countStmt->execute(['employee_id' => $employeeId]);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));

    $stmt = $pdo->prepare('SELECT * FROM leaves WHERE employee_id = :employee_id ORDER BY id DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
    $totalPages = 1;
    $pageNo = 1;
}
?>

<h4 class="mb-3">Leave Management</h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<?php if (!$error): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Apply for Leave</strong></div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">From Date</label>
                    <input class="form-control" type="date" name="from_date" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Date</label>
                    <input class="form-control" type="date" name="to_date" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Reason</label>
                    <input class="form-control" type="text" name="reason" maxlength="255" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Leave Category</label>
                    <select class="form-select" name="leave_category">
                        <option>Paid</option>
                        <option>Half Paid</option>
                        <option>Unpaid</option>
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Submit Leave Request</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Leave History</strong></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>From</th>
                    <th>To</th>
                    <th>Reason</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Applied On</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="text-center text-muted">No leave records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $status = (string)$row['status'];
                        $badge = 'secondary';
                        if ($status === 'Approved') {
                            $badge = 'success';
                        } elseif ($status === 'Rejected') {
                            $badge = 'danger';
                        } elseif ($status === 'Applied') {
                            $badge = 'warning text-dark';
                        }
                        ?>
                        <tr>
                            <td><?= e($row['from_date']) ?></td>
                            <td><?= e($row['to_date']) ?></td>
                            <td><?= e($row['reason']) ?></td>
                            <td><?= e((string)($row['leave_category'] ?? (($row['is_paid'] ?? 0) ? 'Paid' : 'Unpaid'))) ?></td>
                            <td><span class="badge bg-<?= e($badge) ?>"><?= e($status) ?></span></td>
                            <td><?= e((string)$row['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $pageNo <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(route_url('employee/leave')) ?>&p=<?= e((string)($pageNo - 1)) ?>">Previous</a>
                    </li>
                    <li class="page-item disabled"><span class="page-link">Page <?= e((string)$pageNo) ?> of <?= e((string)$totalPages) ?></span></li>
                    <li class="page-item <?= $pageNo >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(route_url('employee/leave')) ?>&p=<?= e((string)($pageNo + 1)) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
<?php endif; ?>
