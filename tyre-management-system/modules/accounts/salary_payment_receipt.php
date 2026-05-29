<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_salary_payroll.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';
require_once __DIR__ . '/../../includes/erp_document_print.php';

if (!has_role(['Accounts Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$id = (int)($_GET['id'] ?? 0);
$pay = acc_salary_get_payment($pdo, $id);
if (!$pay) {
    echo 'Payment not found';
    return;
}

$company = dispatch_company_name($pdo);
$receiptNo = acc_salary_payment_receipt_no($id, (string)$pay['payment_date']);
$net = (float)($pay['net_salary'] ?? 0);
$paidTotal = (float)($pay['salary_paid'] ?? 0);
$remaining = max(0, $net - $paidTotal);
$statusLabel = $remaining < 0.02 ? 'PAID' : ($paidTotal > 0.02 ? 'PARTIAL' : 'UNPAID');
$autoPrint = isset($_GET['print']);

erp_doc_print_begin([
    'title' => 'Salary Receipt — ' . $receiptNo,
    'back_url' => route_url('accounts/salary-payments'),
    'auto_print' => $autoPrint,
]);
erp_doc_print_header($company, 'Salary Payment Receipt', 'Employee salary disbursement', 'Accounts & Finance');

erp_doc_section_open('Receipt details');
erp_doc_grid_open();
erp_doc_field('Receipt number', $receiptNo);
erp_doc_field('Payment date', (string)$pay['payment_date']);
erp_doc_field('Payment mode', (string)$pay['payment_mode']);
erp_doc_field('Reference', (string)($pay['reference_no'] ?? '—'));
erp_doc_field('Recorded by', (string)($pay['recorded_by'] ?? 'Accounts'));
echo '<div style="grid-column:1/-1"><span class="slip__label">Status</span><br>';
echo erp_doc_payment_status_badge($statusLabel) . '</div>';
erp_doc_grid_close();
erp_doc_section_close();

erp_doc_section_open('Employee');
erp_doc_grid_open();
erp_doc_field('Name', (string)$pay['full_name']);
erp_doc_field('Employee ID', (string)$pay['employee_code']);
erp_doc_field('Department', (string)($pay['dept_label'] ?? '—'));
erp_doc_field('Payroll month', payroll_format_month_label((string)($pay['month_year'] ?? '')));
erp_doc_grid_close();
erp_doc_section_close();

erp_doc_section_open('Amount');
echo '<table class="slip__material"><thead><tr><th>Description</th><th class="text-end">Amount (₹)</th></tr></thead><tbody>';
echo '<tr><td>Net salary</td><td class="text-end">' . e(number_format($net, 2)) . '</td></tr>';
echo '<tr><td>This payment</td><td class="text-end"><strong>' . e(number_format((float)$pay['amount'], 2)) . '</strong></td></tr>';
echo '<tr><td>Total paid to date</td><td class="text-end">' . e(number_format($paidTotal, 2)) . '</td></tr>';
echo '<tr><td>Remaining</td><td class="text-end">' . e(number_format($remaining, 2)) . '</td></tr>';
echo '</tbody></table>';
erp_doc_section_close();

erp_doc_print_footer('This receipt is system-generated. Salary expense is posted to Cash & Bank and transaction history automatically.');
