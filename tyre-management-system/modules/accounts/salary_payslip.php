<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/accounts_salary_payroll.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';

if (!has_role(['Accounts Manager', 'Super Admin', 'Admin', 'HR Manager'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$salaryId = (int)($_GET['id'] ?? 0);
$salary = acc_salary_get_employee_row($pdo, $salaryId);
if (!$salary) {
    echo 'Payslip not found';
    return;
}

$employee = $salary;
$selectedSlip = $salary;
$slDa = (float)($salary['dearness_allowance'] ?? 0);
$slTa = (float)($salary['travel_allowance'] ?? 0);
$slGr = (float)($salary['gratuity_accrual'] ?? 0);
$slTax = (float)($salary['tax_deduction'] ?? 0);
$slManual = (float)($salary['deductions'] ?? 0);
$company = dispatch_company_name($pdo);
$payMeta = acc_salary_status_meta((string)$salary['pay_status']);
$printMode = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Salary Slip — <?= e((string)$salary['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; padding: 1.5rem; }
        @media print { body { background: #fff; padding: 0; } .no-print { display: none !important; } }
        .slip-pay-box { border: 1px dashed #cbd5e1; border-radius: 8px; padding: 0.75rem; margin-top: 1rem; background: #f8fafc; }
    </style>
</head>
<body>
<div class="no-print mb-3 d-flex gap-2 flex-wrap">
    <button type="button" class="btn btn-primary" onclick="window.print()">Download / Print PDF</button>
    <a class="btn btn-outline-secondary" href="javascript:history.back()">Back</a>
</div>
<div class="text-center mb-3">
    <h4 class="mb-0"><?= e($company) ?></h4>
    <p class="text-muted small mb-0">Official Salary Slip · Accounts &amp; Finance</p>
</div>
<?php require __DIR__ . '/../hr/payroll/payslip_partial.php'; ?>
<div class="slip-pay-box mx-auto" style="max-width:960px">
    <div class="row g-2 small">
        <div class="col-md-3"><span class="text-muted">Paid amount</span><div class="fw-semibold text-success">₹ <?= e(number_format((float)$salary['amount_paid'], 2)) ?></div></div>
        <div class="col-md-3"><span class="text-muted">Pending</span><div class="fw-semibold text-danger">₹ <?= e(number_format((float)$salary['pending'], 2)) ?></div></div>
        <div class="col-md-3"><span class="text-muted">Payment status</span><div><span class="badge bg-secondary"><?= e((string)$payMeta['label']) ?></span></div></div>
        <div class="col-md-3"><span class="text-muted">Generated</span><div><?= e(date('d M Y')) ?></div></div>
    </div>
    <p class="small text-muted mt-2 mb-0 text-center">Authorized signature — Accounts Department</p>
</div>
<?php if ($printMode): ?><script>window.addEventListener('load', () => window.print());</script><?php endif; ?>
</body>
</html>
