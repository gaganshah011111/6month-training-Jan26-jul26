<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/employee.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = Database::connection();
$error = '';
$rows = [];
$selectedSlip = null;

try {
    $employee = require_employee_record($pdo);
    $employeeId = (int)$employee['id'];

    $pageNo = max(1, (int)($_GET['p'] ?? 1));
    $perPage = 10;
    $offset = ($pageNo - 1) * $perPage;

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM salaries WHERE employee_id = :employee_id');
    $countStmt->execute(['employee_id' => $employeeId]);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));

    $stmt = $pdo->prepare('SELECT * FROM salaries WHERE employee_id = :employee_id ORDER BY month_year DESC, id DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (isset($_GET['slip']) && ctype_digit((string)$_GET['slip'])) {
        $slipStmt = $pdo->prepare('SELECT * FROM salaries WHERE id = :id AND employee_id = :employee_id LIMIT 1');
        $slipStmt->execute(['id' => (int)$_GET['slip'], 'employee_id' => $employeeId]);
        $selectedSlip = $slipStmt->fetch() ?: null;
    }
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
    $totalPages = 1;
    $pageNo = 1;
}
?>

<h4 class="mb-3">Salary & Payslips</h4>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if (!$error): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white"><strong>Salary History</strong></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Month</th>
                    <th>Gross</th>
                    <th>Total Deduction</th>
                    <th>Net Salary</th>
                    <th>Payslip</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="text-center text-muted">No salary records available.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['month_year']) ?></td>
                            <td><?= e(number_format((float)($row['gross_salary'] ?? $row['basic'] ?? 0), 2)) ?></td>
                            <td><?= e(number_format((float)($row['total_deduction'] ?? $row['deductions'] ?? 0), 2)) ?></td>
                            <td><strong><?= e(number_format((float)$row['net_salary'], 2)) ?></strong></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('employee/salary')) ?>&slip=<?= e((string)$row['id']) ?>">View</a></td>
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
                        <a class="page-link" href="<?= e(route_url('employee/salary')) ?>&p=<?= e((string)($pageNo - 1)) ?>">Previous</a>
                    </li>
                    <li class="page-item disabled"><span class="page-link">Page <?= e((string)$pageNo) ?> of <?= e((string)$totalPages) ?></span></li>
                    <li class="page-item <?= $pageNo >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(route_url('employee/salary')) ?>&p=<?= e((string)($pageNo + 1)) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <?php if ($selectedSlip): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Payslip - <?= e($selectedSlip['month_year']) ?></strong>
                <button class="btn btn-sm btn-secondary" onclick="window.print()">Download / Print</button>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6"><p><strong>Employee:</strong> <?= e($employee['full_name']) ?></p></div>
                    <div class="col-md-6"><p><strong>Department:</strong> <?= e($employee['department']) ?></p></div>
                    <div class="col-md-6"><p><strong>Month:</strong> <?= e($selectedSlip['month_year']) ?></p></div>
                    <div class="col-md-6"><p><strong>Generated:</strong> <?= e((string)($selectedSlip['generated_at'] ?? $selectedSlip['created_at'] ?? '')) ?></p></div>
                </div>
                <hr>
                <table class="table table-bordered">
                    <tr class="table-light"><th colspan="2">Earnings</th></tr>
                    <tr><th>Basic Salary</th><td>INR <?= e(number_format((float)($selectedSlip['basic'] ?? 0), 2)) ?></td></tr>
                    <tr><th>HRA (<?= e((string)($selectedSlip['hra_percentage'] ?? 0)) ?>%)</th><td>INR <?= e(number_format((float)($selectedSlip['hra_amount'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Medical Allowance</th><td>INR <?= e(number_format((float)($selectedSlip['medical_allowance'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Other Allowances</th><td>INR <?= e(number_format((float)($selectedSlip['other_allowances'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Overtime Amount</th><td>INR <?= e(number_format((float)($selectedSlip['overtime_amount'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Gross Salary</th><td><strong>INR <?= e(number_format((float)($selectedSlip['gross_salary'] ?? 0), 2)) ?></strong></td></tr>
                    <tr class="table-light"><th colspan="2">Deductions</th></tr>
                    <tr><th>PF (<?= e((string)($selectedSlip['pf_percentage'] ?? 0)) ?>%)</th><td>INR <?= e(number_format((float)($selectedSlip['pf_amount'] ?? 0), 2)) ?></td></tr>
                    <tr><th>ESI Employee (<?= e((string)($selectedSlip['esi_employee_percentage'] ?? 0)) ?>%)</th><td>INR <?= e(number_format((float)($selectedSlip['esi_employee_amount'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Leave Deduction</th><td>INR <?= e(number_format((float)($selectedSlip['leave_deduction'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Half Day Deduction</th><td>INR <?= e(number_format((float)($selectedSlip['half_day_deduction'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Late Entry Deduction</th><td>INR <?= e(number_format((float)($selectedSlip['late_entry_deduction'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Total Deduction</th><td>INR <?= e(number_format((float)($selectedSlip['total_deduction'] ?? $selectedSlip['deductions'] ?? 0), 2)) ?></td></tr>
                    <tr><th>Net Salary</th><td><strong>INR <?= e(number_format((float)($selectedSlip['net_salary'] ?? 0), 2)) ?></strong></td></tr>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
