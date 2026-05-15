<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/payroll_service.php';

if (!has_role(['Super Admin', 'HR Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$salaryId = (int)($_GET['id'] ?? 0);
if ($salaryId < 1) {
    echo 'Invalid payslip';
    return;
}

$st = $pdo->prepare('SELECT s.*, e.full_name, e.employee_code, e.department, e.designation, ' . erp_dept_label_sql('d', 'e') . ' AS dept_label FROM salaries s JOIN employees e ON e.id = s.employee_id LEFT JOIN departments d ON d.id = e.department_id WHERE s.id = :id LIMIT 1');
$st->execute(['id' => $salaryId]);
$slip = $st->fetch(PDO::FETCH_ASSOC);
if (!$slip) {
    echo 'Payslip not found';
    return;
}

$employee = $slip;
$selectedSlip = $slip;
$slDa = (float)($slip['dearness_allowance'] ?? 0);
$slTa = (float)($slip['travel_allowance'] ?? 0);
$slGr = (float)($slip['gratuity_accrual'] ?? 0);
$slTax = (float)($slip['tax_deduction'] ?? 0);
$slPfEm = (float)($slip['pf_employer_amount'] ?? 0);
$slManual = (float)($slip['deductions'] ?? 0);
$printMode = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payslip — <?= e((string)$slip['full_name']) ?> — <?= e((string)$slip['month_year']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; padding: 1.5rem; }
        @media print { body { background: #fff; padding: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="no-print mb-3 d-flex gap-2">
    <button type="button" class="btn btn-primary" onclick="window.print()">Download / Print PDF</button>
    <a class="btn btn-outline-secondary" href="<?= e(route_url('payroll/list')) ?>">Back to Payroll</a>
</div>
<?php require __DIR__ . '/payslip_partial.php'; ?>
</body>
</html>
