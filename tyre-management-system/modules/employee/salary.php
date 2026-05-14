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
        <?php
        $slDa = (float)($selectedSlip['dearness_allowance'] ?? 0);
        $slTa = (float)($selectedSlip['travel_allowance'] ?? 0);
        $slGr = (float)($selectedSlip['gratuity_accrual'] ?? 0);
        if ($slGr <= 0) {
            $slGr = (float)($employee['gratuity_monthly'] ?? 0);
        }
        $slTax = (float)($selectedSlip['tax_deduction'] ?? 0);
        $slPfEm = (float)($selectedSlip['pf_employer_amount'] ?? 0);
        $slManual = (float)($selectedSlip['deductions'] ?? 0);
        ?>
        <div class="card shadow-sm payslip-erp">
            <div class="payslip-erp__head d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="text-white">Salary slip</h5>
                    <div class="payslip-erp__sub">Pay period <?= e($selectedSlip['month_year']) ?> · <?= e($employee['full_name']) ?></div>
                </div>
                <button type="button" class="btn btn-sm btn-light" onclick="window.print()">Print / PDF</button>
            </div>
            <div class="card-body p-0">
                <div class="row g-0 border-bottom">
                    <div class="col-md-6 p-3 border-end"><span class="text-muted small">Employee</span><div class="fw-semibold"><?= e($employee['full_name']) ?></div></div>
                    <div class="col-md-6 p-3"><span class="text-muted small">Department / Code</span><div class="fw-semibold"><?= e((string)$employee['department']) ?> · <?= e((string)($employee['employee_code'] ?? '')) ?></div></div>
                </div>
                <div class="p-3">
                    <div class="row">
                        <div class="col-lg-6 mb-3 mb-lg-0">
                            <h6 class="text-uppercase small text-muted mb-2">Earnings</h6>
                            <table class="table table-sm table-bordered mb-0">
                                <tbody>
                                <tr><th>Basic</th><td>₹ <?= e(number_format((float)($selectedSlip['basic'] ?? 0), 2)) ?></td></tr>
                                <tr><th>Dearness Allowance (DA)</th><td>₹ <?= e(number_format($slDa, 2)) ?></td></tr>
                                <tr><th>HRA (<?= e((string)($selectedSlip['hra_percentage'] ?? '0')) ?>%)</th><td>₹ <?= e(number_format((float)($selectedSlip['hra_amount'] ?? 0), 2)) ?></td></tr>
                                <tr><th>Medical Allowance</th><td>₹ <?= e(number_format((float)($selectedSlip['medical_allowance'] ?? 0), 2)) ?></td></tr>
                                <tr><th>Travel Allowance (TA)</th><td>₹ <?= e(number_format($slTa, 2)) ?></td></tr>
                                <tr><th>Special Allowance</th><td>₹ <?= e(number_format((float)($selectedSlip['special_allowance'] ?? 0), 2)) ?></td></tr>
                                <tr><th>Other Allowances</th><td>₹ <?= e(number_format((float)($selectedSlip['other_allowances'] ?? 0), 2)) ?></td></tr>
                                <tr><th>Overtime (OT)</th><td>₹ <?= e(number_format((float)($selectedSlip['overtime_amount'] ?? 0), 2)) ?></td></tr>
                                <tr class="table-light"><th>Gross salary</th><td><strong>₹ <?= e(number_format((float)($selectedSlip['gross_salary'] ?? 0), 2)) ?></strong></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="text-uppercase small text-muted mb-2">Deductions &amp; employer</h6>
                            <table class="table table-sm table-bordered mb-0">
                                <tbody>
                                <tr><th>PF (employee) (<?= e((string)($selectedSlip['pf_percentage'] ?? '0')) ?>%)</th><td>₹ <?= e(number_format((float)($selectedSlip['pf_amount'] ?? 0), 2)) ?></td></tr>
                                <tr><th>PF (employer share)</th><td>₹ <?= e(number_format($slPfEm, 2)) ?> <span class="text-muted small">(not deducted from net)</span></td></tr>
                                <tr><th>ESI (employee)</th><td>₹ <?= e(number_format((float)($selectedSlip['esi_employee_amount'] ?? 0), 2)) ?></td></tr>
                                <tr><th>ESI (employer)</th><td>₹ <?= e(number_format((float)($selectedSlip['esi_employer_amount'] ?? 0), 2)) ?> <span class="text-muted small">(not deducted from net)</span></td></tr>
                                <tr><th>Professional / Income tax (TDS)</th><td>₹ <?= e(number_format($slTax, 2)) ?></td></tr>
                                <tr><th>Leave deduction</th><td>₹ <?= e(number_format((float)($selectedSlip['leave_deduction'] ?? 0), 2)) ?></td></tr>
                                <tr><th>Half-day deduction</th><td>₹ <?= e(number_format((float)($selectedSlip['half_day_deduction'] ?? 0), 2)) ?></td></tr>
                                <tr><th>Late deduction</th><td>₹ <?= e(number_format((float)($selectedSlip['late_entry_deduction'] ?? 0), 2)) ?></td></tr>
                                <?php if ($slManual > 0): ?>
                                    <tr><th>Other / manual deduction</th><td>₹ <?= e(number_format($slManual, 2)) ?></td></tr>
                                <?php endif; ?>
                                <tr class="table-light"><th>Total deductions</th><td><strong>₹ <?= e(number_format((float)($selectedSlip['total_deduction'] ?? 0), 2)) ?></strong></td></tr>
                                </tbody>
                            </table>
                            <p class="small text-muted mb-2">Gratuity accrual (employer, statutory provision): <strong>₹ <?= e(number_format($slGr, 2)) ?></strong> per month — informational; not part of net pay.</p>
                            <div class="border rounded p-3 bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold">Net salary (credit)</span>
                                    <span class="fs-4 text-success fw-bold">₹ <?= e(number_format((float)($selectedSlip['net_salary'] ?? 0), 2)) ?></span>
                                </div>
                            </div>
                            <p class="small text-muted mt-2 mb-0">Generated: <?= e((string)($selectedSlip['generated_at'] ?? $selectedSlip['created_at'] ?? '')) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
