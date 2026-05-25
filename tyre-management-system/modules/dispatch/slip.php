<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';
require_once __DIR__ . '/../../includes/erp_document_print.php';

if (!is_logged_in() || !can_access_page('dispatch/slip', current_user())) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(404);
    echo 'Dispatch not found';
    exit;
}

$pdo = Database::connection();
$row = dispatch_get_by_id($pdo, $id);
if (!$row) {
    http_response_code(404);
    echo 'Dispatch not found';
    exit;
}

$autoPrint = isset($_GET['print']);
$companyName = dispatch_company_name($pdo);
$generatedAt = date('d M Y, H:i');
$gross = isset($row['gross_weight_kg']) ? (float)$row['gross_weight_kg'] : null;
$tare = isset($row['tare_weight_kg']) ? (float)$row['tare_weight_kg'] : null;
$net = isset($row['net_weight_kg']) ? (float)$row['net_weight_kg'] : null;
$backUrl = has_role(['Sales Manager'])
    ? route_url('sales/dispatch')
    : route_url('dispatch/history');

erp_doc_print_begin([
    'title' => 'Dispatch Slip — ' . (string)($row['dispatch_code'] ?? ''),
    'back_url' => $backUrl,
    'auto_print' => $autoPrint,
]);
erp_doc_print_header($companyName, 'Dispatch Slip', 'Official shipment document', 'Finished goods dispatch & logistics');
erp_doc_section_open('Dispatch details');
erp_doc_grid_open();
erp_doc_field('Dispatch ID', (string)($row['dispatch_code'] ?? '—'));
erp_doc_field('Invoice number', (string)($row['invoice_no'] ?? '—'));
erp_doc_field('Dispatch date', (string)($row['dispatch_date'] ?? '—'));
erp_doc_field('Status', (string)($row['status'] ?? '—'));
erp_doc_field('Customer', (string)($row['customer_name'] ?? '—'), true);
erp_doc_grid_close();
erp_doc_section_close();

erp_doc_section_open('Transport details');
erp_doc_grid_open();
erp_doc_field('Driver', (string)($row['driver_name'] ?? '—'));
erp_doc_field('Vehicle number', (string)($row['vehicle_no'] ?? '—'));
erp_doc_field('Transport company', (string)($row['transport_company'] ?? $row['transport_master_name'] ?? '—'), true);
erp_doc_grid_close();
erp_doc_section_close();

erp_doc_section_open('Material details');
?>
                <table class="slip__material">
                    <thead>
                        <tr>
                            <th>Tyre type</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Gross (kg)</th>
                            <th class="text-end">Tare (kg)</th>
                            <th class="text-end">Net (kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= e((string)($row['tyre_type'] ?? '—')) ?></td>
                            <td class="text-end"><?= e(dispatch_format_qty((int)($row['qty'] ?? 0))) ?></td>
                            <td class="text-end"><?= $gross !== null ? e(number_format($gross, 2)) : '—' ?></td>
                            <td class="text-end"><?= $tare !== null ? e(number_format($tare, 2)) : '—' ?></td>
                            <td class="text-end"><strong><?= $net !== null ? e(number_format($net, 2)) : '—' ?></strong></td>
                        </tr>
                    </tbody>
                </table>
<?php erp_doc_section_close(); ?>

<?php if (!empty($row['remarks'])): ?>
<?php erp_doc_section_open('Remarks'); ?>
<p class="slip__value" style="font-weight:400;"><?= e((string)$row['remarks']) ?></p>
<?php erp_doc_section_close(); ?>
<?php endif; ?>
<?php
erp_doc_print_footer(
    'Authorized signature',
    'Generated: ' . e($generatedAt) . '<br>Order ref: ' . e((string)($row['order_no'] ?? ''))
);
