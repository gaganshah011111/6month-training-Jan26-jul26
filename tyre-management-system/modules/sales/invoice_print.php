<?php
declare(strict_types=1);

/** @var array $inv */
/** @var string $company */
/** @var float $pending */
/** @var string $backUrl */
/** @var bool $autoPrint */

require_once __DIR__ . '/../../includes/erp_document_print.php';

$backUrl = $backUrl ?? route_url('sales/invoices');
$autoPrint = $autoPrint ?? isset($_GET['print']);
$dispRef = sales_invoice_dispatch_ref($inv);
$payLabel = $pending < 0.01 ? 'PAID' : ((float)$inv['amount_paid'] > 0.01 ? 'PARTIAL' : 'UNPAID');
$invNo = (string)$inv['invoice_no'];
$generatedAt = date('d M Y, H:i');

erp_doc_print_begin([
    'title' => 'Invoice — ' . $invNo,
    'back_url' => $backUrl,
    'auto_print' => $autoPrint,
]);
erp_doc_print_header($company, 'Tax Invoice', 'Official sales invoice', 'Sales & CRM · Billing');

erp_doc_section_open('Invoice details');
erp_doc_grid_open();
erp_doc_field('Invoice number', $invNo);
erp_doc_field('Invoice date', (string)$inv['invoice_date']);
erp_doc_field('Due date', (string)($inv['due_date'] ?? '—'));
erp_doc_field('Sales order', (string)($inv['so_number'] ?? '—'));
erp_doc_field('Dispatch reference', $dispRef);
erp_doc_grid_close();
echo '<p style="margin:8px 0 0"><span class="slip__label">Payment status</span><br>';
echo erp_doc_payment_status_badge($payLabel) . '</p>';
erp_doc_section_close();

erp_doc_section_open('Customer details');
erp_doc_grid_open();
erp_doc_field('Customer', (string)$inv['company_name']);
erp_doc_field('Customer code', (string)($inv['customer_code'] ?? '—'));
erp_doc_field('GST number', (string)($inv['gst_number'] ?? '—'), true);
erp_doc_grid_close();
erp_doc_section_close();

erp_doc_section_open('Line items');
echo '<table class="slip__material"><thead><tr>';
echo '<th>Tyre type</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">GST %</th><th class="text-end">Amount</th>';
echo '</tr></thead><tbody>';
foreach ($inv['items'] as $it) {
    echo '<tr><td>' . e($it['tyre_type']) . '</td>';
    echo '<td class="text-end">' . e((string)$it['qty']) . '</td>';
    echo '<td class="text-end">' . e(number_format((float)$it['rate'], 2)) . '</td>';
    echo '<td class="text-end">' . e((string)$it['gst_percent']) . '%</td>';
    echo '<td class="text-end">' . e(number_format((float)$it['line_total'], 2)) . '</td></tr>';
}
echo '</tbody></table>';
echo '<div class="slip__totals">';
echo 'Subtotal: ₹' . e(number_format((float)$inv['subtotal'], 2)) . '<br>';
echo 'GST: ₹' . e(number_format((float)$inv['gst_total'], 2)) . '<br>';
echo 'Paid: ₹' . e(number_format((float)$inv['amount_paid'], 2)) . '<br>';
echo '<strong>Total: ₹' . e(number_format((float)$inv['total_amount'], 2)) . '</strong><br>';
echo '<strong>Balance due: ₹' . e(number_format($pending, 2)) . '</strong>';
echo '</div>';
erp_doc_section_close();

erp_doc_print_footer(
    'Customer signature',
    'Generated: ' . e($generatedAt) . '<br>Document: ' . e($invNo)
);
