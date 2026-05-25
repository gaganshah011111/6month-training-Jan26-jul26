<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';
require_once __DIR__ . '/../../includes/erp_document_print.php';

require_sales_manager();

$pdo = Database::connection();
$id = (int)($_GET['id'] ?? 0);
$order = sales_get_order($pdo, $id);
if (!$order) {
    echo 'Order not found';
    exit;
}

$company = dispatch_company_name($pdo);
$invId = sales_invoice_id_for_order($pdo, $id);
$invSt = $invId ? sales_track_invoice_status_simple($pdo, $invId) : ['label' => 'Not Generated'];
$paySt = sales_track_payment_status($pdo, $id);
$totalOrdered = (int)array_sum(array_column($order['items'], 'qty_ordered'));
$totalDispatched = (int)array_sum(array_column($order['items'], 'qty_dispatched'));
$dspSt = sales_track_dispatch_status($totalOrdered, $totalDispatched, 'Ready for Dispatch');
$soNo = (string)$order['so_number'];
$autoPrint = isset($_GET['print']);
$generatedAt = date('d M Y, H:i');

erp_doc_print_begin([
    'title' => 'Sales Order — ' . $soNo,
    'back_url' => route_url('sales/order', ['id' => $id]),
    'auto_print' => $autoPrint,
]);
erp_doc_print_header($company, 'Sales Order', 'Official customer order', 'Sales & CRM · Order management');

erp_doc_section_open('Order details');
erp_doc_grid_open();
erp_doc_field('SO number', $soNo);
erp_doc_field('Order date', (string)$order['order_date']);
erp_doc_field('Delivery date', (string)($order['delivery_date'] ?? '—'));
erp_doc_field('Priority', (string)($order['priority'] ?? '—'));
erp_doc_field('Order status', (string)$order['status']);
erp_doc_field('Dispatch status', $dspSt['label']);
erp_doc_field('Invoice status', $invSt['label']);
erp_doc_field('Payment status', $paySt['label']);
erp_doc_grid_close();
erp_doc_section_close();

erp_doc_section_open('Customer');
erp_doc_grid_open();
erp_doc_field('Customer', (string)$order['company_name']);
erp_doc_field('Code', (string)($order['customer_code'] ?? '—'));
erp_doc_grid_close();
erp_doc_section_close();

erp_doc_section_open('Tyre lines');
echo '<table class="slip__material"><thead><tr>';
echo '<th>Tyre type</th><th class="text-end">Ordered</th><th class="text-end">Dispatched</th>';
echo '<th class="text-end">Rate</th><th class="text-end">GST %</th><th class="text-end">Line total</th>';
echo '</tr></thead><tbody>';
foreach ($order['items'] as $it) {
    echo '<tr><td>' . e($it['tyre_type']) . '</td>';
    echo '<td class="text-end">' . e((string)$it['qty_ordered']) . '</td>';
    echo '<td class="text-end">' . e((string)$it['qty_dispatched']) . '</td>';
    echo '<td class="text-end">' . e(number_format((float)$it['rate'], 2)) . '</td>';
    echo '<td class="text-end">' . e((string)$it['gst_percent']) . '%</td>';
    echo '<td class="text-end">' . e(number_format((float)$it['line_total'], 2)) . '</td></tr>';
}
echo '</tbody></table>';
echo '<div class="slip__totals">';
echo 'Subtotal: ₹' . e(number_format((float)$order['subtotal'], 2)) . '<br>';
echo 'GST: ₹' . e(number_format((float)$order['gst_total'], 2)) . '<br>';
echo '<strong>Order total: ₹' . e(number_format((float)$order['total_amount'], 2)) . '</strong>';
echo '</div>';
erp_doc_section_close();

erp_doc_print_footer(
    'Customer approval',
    'Generated: ' . e($generatedAt) . '<br>SO: ' . e($soNo)
);
