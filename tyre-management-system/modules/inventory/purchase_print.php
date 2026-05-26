<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_service.php';
require_once __DIR__ . '/../../includes/erp_document_print.php';

if (!has_role(['Inventory Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$id = (int)($_GET['id'] ?? 0);
$row = $id > 0 ? inv_purchase_get($pdo, $id) : null;
if (!$row) {
    echo 'Purchase entry not found.';
    return;
}

$company = erp_doc_company_name($pdo);
$pending = (float)($row['pending_amount'] ?? 0);
$pm = inv_purchase_payment_meta((string)($row['payment_status'] ?? 'Unpaid'));
$pinv = (string)$row['pinv_no'];
$autoPrint = isset($_GET['print']) || isset($_GET['download']);

erp_doc_print_begin([
    'title' => 'Purchase Inward — ' . $pinv,
    'back_url' => route_url('inventory/purchase-history', ['view' => $id]),
    'auto_print' => $autoPrint,
]);
erp_doc_print_header($company, 'Purchase Inward', $pinv, 'Procurement & Inventory · Material purchase');

erp_doc_section_open('Supplier details');
erp_doc_grid_open();
erp_doc_field('Supplier', (string)($row['supplier_name'] ?? '—'));
erp_doc_field('GST number', (string)($row['supplier_gst'] ?? '—'));
erp_doc_field('Contact', (string)($row['contact_person'] ?? '—'));
erp_doc_field('Phone', (string)($row['supplier_phone'] ?? '—'));
erp_doc_field('Supplier invoice', (string)($row['invoice_no'] ?? '—'));
erp_doc_field('Challan', (string)($row['challan_no'] ?? '—'));
erp_doc_grid_close();
erp_doc_section_close();

erp_doc_section_open('Material details');
erp_doc_grid_open();
erp_doc_field('Material', (string)$row['material_name']);
erp_doc_field('Quantity', number_format((float)$row['quantity'], 2) . ' ' . (string)$row['unit']);
erp_doc_field('Batch', (string)($row['batch_no'] ?? '—'));
erp_doc_field('Expiry', (string)($row['expiry_date'] ?? '—'));
erp_doc_field('Warehouse', (string)($row['warehouse_location'] ?? '—'));
erp_doc_field('Purchase date', (string)$row['inward_date']);
erp_doc_grid_close();
erp_doc_section_close();

erp_doc_section_open('Pricing & payment');
echo '<table class="slip__material"><tbody>';
echo '<tr><td>Rate per unit</td><td class="text-end">₹' . e(number_format((float)$row['rate'], 2)) . '</td></tr>';
echo '<tr><td>Subtotal</td><td class="text-end">₹' . e(number_format((float)$row['subtotal'], 2)) . '</td></tr>';
echo '<tr><td>GST (' . e((string)$row['gst_percent']) . '%)</td><td class="text-end">₹' . e(number_format((float)$row['gst_amount'], 2)) . '</td></tr>';
echo '<tr><td>Transport</td><td class="text-end">₹' . e(number_format((float)$row['transport_charges'], 2)) . '</td></tr>';
echo '<tr><td>Loading / unloading</td><td class="text-end">₹' . e(number_format((float)$row['loading_charges'], 2)) . '</td></tr>';
echo '<tr><td>Other charges</td><td class="text-end">₹' . e(number_format((float)$row['other_charges'], 2)) . '</td></tr>';
echo '<tr><td>Discount</td><td class="text-end">−₹' . e(number_format((float)$row['discount_amount'], 2)) . '</td></tr>';
echo '<tr><td><strong>Final amount</strong></td><td class="text-end"><strong>₹' . e(number_format((float)$row['total_amount'], 2)) . '</strong></td></tr>';
echo '<tr><td>Paid</td><td class="text-end">₹' . e(number_format((float)$row['paid_amount'], 2)) . '</td></tr>';
echo '<tr><td>Pending</td><td class="text-end">₹' . e(number_format($pending, 2)) . '</td></tr>';
echo '</tbody></table>';
echo '<p style="margin:10px 0 0"><span class="slip__label">Payment status</span><br>';
echo erp_doc_payment_status_badge($pm['label']) . '</p>';
if (!empty($row['payment_mode'])) {
    echo '<p class="small">Mode: ' . e((string)$row['payment_mode']) . '</p>';
}
if (!empty($row['payment_ref'])) {
    echo '<p class="small">Reference: ' . e((string)$row['payment_ref']) . '</p>';
}
if (!empty($row['remarks'])) {
    echo '<p class="small">Notes: ' . e((string)$row['remarks']) . '</p>';
}
$payRows = inv_purchase_list_payments($pdo, (int)$row['id']);
if ($payRows !== []) {
    echo '<p class="small fw-semibold mt-2">Payment history</p>';
    echo '<table class="slip__material"><thead><tr><th>Date</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th></tr></thead><tbody>';
    foreach ($payRows as $pr) {
        echo '<tr><td>' . e((string)$pr['payment_date']) . '</td>';
        echo '<td class="text-end">₹' . e(number_format((float)$pr['amount'], 2)) . '</td>';
        echo '<td>' . e((string)($pr['payment_mode'] ?? '—')) . '</td>';
        echo '<td>' . e((string)($pr['payment_ref'] ?? '—')) . '</td></tr>';
    }
    echo '</tbody></table>';
}
erp_doc_section_close();

erp_doc_print_footer(
    'Authorised signature',
    'Generated: ' . e(date('d M Y, H:i')) . '<br>Document: ' . e($pinv)
);
