<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_service.php';
require_once __DIR__ . '/../../includes/inv_ui.php';

if (!has_role(['Inventory Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
inv_purchase_ensure_schema($pdo);

$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$q = trim((string)($_GET['q'] ?? ''));
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$export = (string)($_GET['export'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

$filters = ['from' => $from, 'to' => $to, 'q' => $q, 'supplier_id' => $supplierId];
$rows = inv_purchase_list_payments($pdo, null, $filters);
$ledger = inv_supplier_ledger_list($pdo);
$suppliers = inv_list_suppliers($pdo);
$baseQs = 'page=inventory/purchase-payments&from=' . rawurlencode($from) . '&to=' . rawurlencode($to)
    . '&supplier_id=' . $supplierId . '&q=' . rawurlencode($q);

if ($export === 'print' || $export === 'pdf') {
    require_once __DIR__ . '/../../includes/erp_document_print.php';
    $company = erp_doc_company_name($pdo);
    erp_doc_print_begin([
        'title' => 'Payment History',
        'back_url' => route_url('inventory/purchase-payments'),
        'auto_print' => $export === 'print',
    ]);
    erp_doc_print_header($company, 'Payment History', $from . ' to ' . $to, inv_module_label() . ' · Payments');
    echo '<table class="slip__material"><thead><tr><th>Date</th><th>PINV</th><th>Supplier</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td>' . e((string)$r['payment_date']) . '</td><td>' . e((string)($r['pinv_no'] ?? '—')) . '</td>';
        echo '<td>' . e((string)($r['supplier_name'] ?? '—')) . '</td>';
        echo '<td class="text-end">₹' . e(number_format((float)$r['amount'], 2)) . '</td>';
        echo '<td>' . e((string)($r['payment_mode'] ?? '—')) . '</td>';
        echo '<td>' . e((string)($r['payment_ref'] ?? '—')) . '</td></tr>';
    }
    echo '</tbody></table>';
    erp_doc_print_footer('Authorised signature', 'Generated ' . date('d M Y H:i'));
    exit;
}

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="purchase-payments.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'PINV', 'Supplier', 'Amount', 'Mode', 'Reference', 'User']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['payment_date'],
            $r['pinv_no'] ?? '',
            $r['supplier_name'] ?? '',
            $r['amount'],
            $r['payment_mode'] ?? '',
            $r['payment_ref'] ?? '',
            $r['recorded_by'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}
?>

<div class="inv-page">
<?php inv_page_header('Payment History', 'Every supplier payment recorded against purchase inward (PINV).'); ?>

    <section class="inv-card mb-3">
        <div class="inv-card__head"><h2 class="inv-card__title mb-0">Supplier outstanding</h2></div>
        <?php inv_table_scroll_open('min(200px, 28vh)'); ?>
            <table class="table table-sm inv-table mb-0">
                <thead><tr><th>Supplier</th><th class="text-end">Purchased</th><th class="text-end">Paid</th><th class="text-end">Pending</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($ledger, 0, 15) as $s): ?>
                    <?php if ((float)($s['pending_balance'] ?? 0) <= inv_purchase_tolerance()) {
                        continue;
                    } ?>
                    <tr>
                        <td><?= e((string)$s['name']) ?></td>
                        <td class="text-end">₹<?= e(number_format((float)$s['total_purchased'], 2)) ?></td>
                        <td class="text-end">₹<?= e(number_format((float)$s['total_paid'], 2)) ?></td>
                        <td class="text-end text-danger fw-semibold">₹<?= e(number_format((float)$s['pending_balance'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php inv_table_scroll_close(); ?>
    </section>

    <form method="get" class="inv-filter-bar">
        <input type="hidden" name="page" value="inventory/purchase-payments">
        <div class="inv-filter-bar__row">
            <div><label class="inv-label">From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
            <div><label class="inv-label">To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
            <div><label class="inv-label">Supplier</label>
                <select class="form-select form-select-sm erp-select-search" name="supplier_id" style="min-width:140px">
                    <option value="0">All</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>><?= e((string)$s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="inv-label">Search</label><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="PINV, ref…"></div>
            <div class="align-self-end"><button class="btn btn-primary btn-sm">Apply</button></div>
            <?= inv_filter_exports($baseQs, true, false, false) ?>
        </div>
    </form>

    <section class="inv-card">
        <?php inv_table_scroll_open('min(52vh, 480px)'); ?>
            <table class="table table-sm inv-table mb-0">
                <thead>
                    <tr><th>Date</th><th>PINV</th><th>Supplier</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th><th>User</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e((string)$r['payment_date']) ?></td>
                        <td><?= e((string)($r['pinv_no'] ?? '—')) ?></td>
                        <td><?= e((string)($r['supplier_name'] ?? '—')) ?></td>
                        <td class="text-end">₹<?= e(number_format((float)$r['amount'], 2)) ?></td>
                        <td><?= e((string)($r['payment_mode'] ?? '—')) ?></td>
                        <td><?= e((string)($r['payment_ref'] ?? '—')) ?></td>
                        <td><?= e((string)($r['recorded_by'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7" class="text-center inv-muted py-4">No payments in this period.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        <?php inv_table_scroll_close(); ?>
    </section>
</div>
