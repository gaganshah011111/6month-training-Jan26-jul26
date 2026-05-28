<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_service.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';
require_once __DIR__ . '/../../includes/erp_document_print.php';

$pdo = Database::connection();
$id = (int)($_GET['id'] ?? 0);
$pay = sales_get_payment($pdo, $id);
if (!$pay) {
    echo 'Payment not found';
    exit;
}

$company = dispatch_company_name($pdo);
$receiptNo = sales_payment_receipt_no($id, (string)$pay['payment_date']);
$invTotal = (float)$pay['invoice_total'];
$invPaid = (float)$pay['invoice_paid'];
$remaining = max(0, $invTotal - $invPaid);
$statusLabel = $remaining < 0.01 ? 'PAID' : ($invPaid > 0.01 ? 'PARTIAL' : 'UNPAID');
$collector = (string)(current_user()['name'] ?? current_user()['username'] ?? 'Accounts');
$autoPrint = isset($_GET['print']);
$generatedAt = date('d M Y, H:i');

erp_doc_print_begin([
    'title' => 'Receipt — ' . $receiptNo,
    'back_url' => route_url('accounts/receivables'),
    'auto_print' => $autoPrint,
]);
erp_doc_print_header($company, 'Payment Receipt', 'Official payment acknowledgement', 'Accounts & Finance · Receipts');

erp_doc_section_open('Receipt details');
erp_doc_grid_open();
erp_doc_field('Receipt number', $receiptNo);
erp_doc_field('Payment date', (string)$pay['payment_date']);
erp_doc_field('Payment mode', (string)$pay['payment_mode']);
erp_doc_field('Transaction ref', (string)($pay['reference_no'] ?? '—'));
erp_doc_field('Accounts user', $collector);
echo '<div style="grid-column:1/-1"><span class="slip__label">Status</span><br>';
echo erp_doc_payment_status_badge($statusLabel) . '</div>';
erp_doc_grid_close();
erp_doc_section_close();

erp_doc_section_open('Customer & invoice');
erp_doc_grid_open();
erp_doc_field('Customer', (string)$pay['company_name']);
erp_doc_field('Customer code', (string)($pay['customer_code'] ?? '—'));
erp_doc_field('Invoice number', (string)$pay['invoice_no'], true);
erp_doc_grid_close();
erp_doc_section_close();

erp_doc_section_open('Amount summary');
echo '<table class="slip__material"><thead><tr>';
echo '<th>Description</th><th class="text-end">Amount (₹)</th>';
echo '</tr></thead><tbody>';
echo '<tr><td>Invoice amount</td><td class="text-end">' . e(number_format($invTotal, 2)) . '</td></tr>';
echo '<tr><td>Paid (this receipt)</td><td class="text-end"><strong>' . e(number_format((float)$pay['amount'], 2)) . '</strong></td></tr>';
echo '<tr><td>Total paid on invoice</td><td class="text-end">' . e(number_format($invPaid, 2)) . '</td></tr>';
echo '<tr><td>Remaining balance</td><td class="text-end"><strong>' . e(number_format($remaining, 2)) . '</strong></td></tr>';
echo '</tbody></table>';
erp_doc_section_close();

if ((string)($pay['remarks'] ?? '') !== '') {
    erp_doc_section_open('Remarks');
    echo '<p class="slip__value" style="font-weight:400;">' . e($pay['remarks']) . '</p>';
    erp_doc_section_close();
}

erp_doc_print_footer(
    'Accounts signature',
    'Generated: ' . e($generatedAt) . '<br>Receipt: ' . e($receiptNo)
);
