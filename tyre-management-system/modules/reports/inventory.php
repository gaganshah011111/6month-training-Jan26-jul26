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
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$materialId = (int)($_GET['material_id'] ?? 0);
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$tab = (string)($_GET['tab'] ?? 'stock');
$paymentStatus = (string)($_GET['payment_status'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

$export = (string)($_GET['export'] ?? '');
$tabExport = (string)($_GET['tab'] ?? 'stock');

if ($export === 'csv') {
    $report = inv_report($pdo, $from, $to, (int)($_GET['material_id'] ?? 0), (int)($_GET['supplier_id'] ?? 0));
    if ($tabExport === 'purchase') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="purchase-report.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['PINV', 'Date', 'Supplier', 'Material', 'Qty', 'Total', 'Paid', 'Pending', 'Status']);
        foreach (inv_purchase_list($pdo, [
            'from' => $from,
            'to' => $to,
            'material_id' => (int)($_GET['material_id'] ?? 0),
            'supplier_id' => (int)($_GET['supplier_id'] ?? 0),
            'payment_status' => $paymentStatus,
        ]) as $r) {
            fputcsv($out, [
                $r['pinv_no'], $r['inward_date'], $r['supplier_name'] ?? '', $r['material_name'],
                $r['quantity'], $r['total_amount'], $r['paid_amount'], $r['pending_amount'], $r['payment_status'],
            ]);
        }
        fclose($out);
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory-report-' . $tabExport . '.csv"');
    $out = fopen('php://output', 'w');
    if ($tabExport === 'history') {
        fputcsv($out, ['Date', 'Type', 'Qty', 'Department', 'Operator', 'Remarks']);
        foreach (inv_report_transactions($pdo, $from, $to, (int)($_GET['material_id'] ?? 0)) as $h) {
            fputcsv($out, [$h['dt'] ?? '', $h['txn_type'] ?? '', $h['qty_signed'] ?? $h['qty'] ?? '', $h['department'] ?? '', $h['operator_name'] ?? '', $h['remarks'] ?? '']);
        }
    } elseif ($tabExport === 'usage') {
        fputcsv($out, ['Date', 'Material', 'Qty', 'Department', 'Type', 'Remarks']);
        foreach ($report['usage'] as $r) {
            fputcsv($out, [$r['usage_date'], $r['material_name'], $r['quantity'], $r['department'] ?? '', $r['usage_type'] ?? '', $r['remarks'] ?? '']);
        }
    } elseif ($tabExport === 'low') {
        fputcsv($out, ['Material', 'Stock', 'Minimum', 'Unit']);
        foreach ($report['low'] as $r) {
            fputcsv($out, [$r['material_name'], $r['stock_qty'], $r['reorder_level'], $r['unit']]);
        }
    } else {
        fputcsv($out, ['Code', 'Material', 'Stock', 'Minimum', 'Unit', 'Status']);
        foreach (inv_list_stock_analytics($pdo, true) as $r) {
            $meta = inv_stock_status_meta((float)$r['current_stock'], (float)$r['reorder_level']);
            fputcsv($out, [$r['material_code'], $r['material_name'], $r['current_stock'], $r['reorder_level'], $r['unit'], $meta['label']]);
        }
    }
    fclose($out);
    exit;
}

if ($export === 'pdf' || $export === 'print') {
    $report = inv_report($pdo, $from, $to, (int)($_GET['material_id'] ?? 0), (int)($_GET['supplier_id'] ?? 0));
    $transactions = $tabExport === 'history' ? inv_report_transactions($pdo, $from, $to, (int)($_GET['material_id'] ?? 0)) : [];
    $stockRows = inv_list_stock_analytics($pdo, true);
    require __DIR__ . '/inventory_print.php';
    exit;
}

$report = ['summary' => [], 'stock_summary' => [], 'inward' => [], 'usage' => [], 'low' => [], 'supplier_stock' => []];
$purchaseSummary = ['total_purchases' => 0, 'total_paid' => 0, 'total_pending' => 0, 'top_material' => null, 'supplier_outstanding' => []];
$purchaseRows = [];
$error = '';
try {
    $report = inv_report($pdo, $from, $to, $materialId, $supplierId);
    $purchaseSummary = inv_purchase_report_summary($pdo, $from, $to, $supplierId, $materialId, $paymentStatus);
    if ($tab === 'purchase') {
        $purchaseRows = inv_purchase_list($pdo, [
            'from' => $from,
            'to' => $to,
            'supplier_id' => $supplierId,
            'material_id' => $materialId,
            'payment_status' => $paymentStatus,
        ]);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$sum = $report['summary'];
$materials = inv_list_materials_master($pdo);
$suppliers = inv_list_suppliers($pdo);
$baseQs = 'page=reports/inventory&from=' . rawurlencode($from) . '&to=' . rawurlencode($to)
    . '&material_id=' . $materialId . '&supplier_id=' . $supplierId . '&payment_status=' . rawurlencode($paymentStatus);
?>

<div class="inv-page">
<?php inv_page_header('Reports', 'Stock, purchases, usage, and supplier payables for the selected period.'); ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="inv-kpi"><span class="inv-kpi__k">Active materials</span><span class="inv-kpi__v"><?= e((string)($sum['materials'] ?? 0)) ?></span></div></div>
        <div class="col-6 col-md-3"><div class="inv-kpi"><span class="inv-kpi__k">Total stock</span><span class="inv-kpi__v"><?= e(number_format((float)($sum['total_stock'] ?? 0), 1)) ?></span></div></div>
        <div class="col-6 col-md-3"><div class="inv-kpi inv-kpi--warn"><span class="inv-kpi__k">Low stock</span><span class="inv-kpi__v"><?= e((string)($sum['low'] ?? 0)) ?></span></div></div>
        <div class="col-6 col-md-3"><div class="inv-kpi inv-kpi--danger"><span class="inv-kpi__k">Out of stock</span><span class="inv-kpi__v"><?= e((string)($sum['out'] ?? 0)) ?></span></div></div>
    </div>

    <form method="get" class="inv-filter-bar">
        <input type="hidden" name="page" value="reports/inventory">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="inv-filter-bar__row">
            <div><label class="inv-label">From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
            <div><label class="inv-label">To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
            <div><label class="inv-label">Material</label>
                <select class="form-select form-select-sm erp-select-search" name="material_id" data-placeholder="Search material…" style="min-width:140px">
                    <option value="0">All</option>
                    <?php foreach ($materials as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= $materialId === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['material_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="inv-label">Supplier</label>
                <select class="form-select form-select-sm erp-select-search" name="supplier_id" data-placeholder="Search supplier…" style="min-width:140px">
                    <option value="0">All</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label class="inv-label">Payment</label>
                <select class="form-select form-select-sm" name="payment_status">
                    <option value="">All</option>
                    <?php foreach (['Paid', 'Partial', 'Unpaid'] as $ps): ?>
                        <option value="<?= e($ps) ?>" <?= $paymentStatus === $ps ? 'selected' : '' ?>><?= e($ps) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="align-self-end"><button class="btn btn-primary btn-sm">Apply</button></div>
            <?= inv_filter_exports($baseQs . '&tab=' . rawurlencode($tab)) ?>
        </div>
    </form>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $tab === 'stock' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=stock">Stock summary</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'purchase' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=purchase">Purchases</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'inward' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=inward">Inward log</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'usage' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=usage">Stock usage</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'low' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=low">Low stock</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'supplier' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=supplier">Supplier stock</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'history' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=history">Transaction history</a></li>
    </ul>

    <section class="inv-card">
            <?php if ($tab === 'purchase'): ?>
                <div class="row g-2 p-3 border-bottom inv-card__summary">
                    <div class="col-md-3"><span class="inv-kpi__k">Total purchases</span><br><strong>₹<?= e(number_format((float)$purchaseSummary['total_purchases'], 2)) ?></strong></div>
                    <div class="col-md-3"><span class="inv-kpi__k">Total paid</span><br><strong>₹<?= e(number_format((float)$purchaseSummary['total_paid'], 2)) ?></strong></div>
                    <div class="col-md-3"><span class="inv-kpi__k">Total pending</span><br><strong class="text-danger">₹<?= e(number_format((float)$purchaseSummary['total_pending'], 2)) ?></strong></div>
                    <div class="col-md-3"><span class="inv-kpi__k">Top material</span><br><strong><?= e((string)($purchaseSummary['top_material']['material_name'] ?? '—')) ?></strong></div>
                </div>
                <?php inv_table_scroll_open('min(52vh, 480px)'); ?>
                <table class="table table-sm inv-table mb-0">
                    <thead><tr><th>PINV</th><th>Date</th><th>Supplier</th><th>Material</th><th class="text-end">Total</th><th class="text-end">Pending</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($purchaseRows as $r): ?>
                        <?php $pm = inv_purchase_payment_meta((string)($r['payment_status'] ?? 'Unpaid')); ?>
                        <tr>
                            <td><?= e((string)$r['pinv_no']) ?></td>
                            <td><?= e((string)$r['inward_date']) ?></td>
                            <td><?= e((string)($r['supplier_name'] ?? '—')) ?></td>
                            <td><?= e((string)$r['material_name']) ?></td>
                            <td class="text-end">₹<?= e(number_format((float)$r['total_amount'], 2)) ?></td>
                            <td class="text-end">₹<?= e(number_format((float)$r['pending_amount'], 2)) ?></td>
                            <td><span class="badge inv-pay--<?= e($pm['badge']) ?>"><?= e($pm['label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($purchaseRows === []): ?><tr><td colspan="7" class="text-center inv-muted py-3">No purchases in this period.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <?php inv_table_scroll_close(); ?>
            <?php elseif ($tab === 'stock'): ?>
                <?php inv_table_scroll_open('min(52vh, 480px)'); ?>
                <table class="table table-sm inv-table mb-0">
                    <thead><tr><th>Code</th><th>Material</th><th class="text-end">Added</th><th class="text-end">Used</th><th class="text-end">Remaining</th><th class="text-end">Minimum</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php
                    $stockAnalytics = inv_list_stock_analytics($pdo, true);
                    foreach ($stockAnalytics as $r):
                        $meta = inv_stock_status_meta((float)$r['current_stock'], (float)$r['reorder_level']);
                    ?>
                        <tr>
                            <td><?= e((string)$r['material_code']) ?></td>
                            <td><?= e((string)$r['material_name']) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['total_added'], 2)) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['total_used'], 2)) ?></td>
                            <td class="text-end fw-semibold"><?= e(number_format((float)$r['current_stock'], 2)) ?> <?= e((string)$r['unit']) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['reorder_level'], 2)) ?></td>
                            <td><span class="badge inv-badge--<?= e($meta['badge']) ?>"><?= e($meta['label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($stockAnalytics === []): ?><tr><td colspan="7" class="text-center text-muted">No materials found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <?php inv_table_scroll_close(); ?>
            <?php elseif ($tab === 'inward'): ?>
                <?php inv_table_scroll_open('min(52vh, 480px)'); ?>
                <table class="table table-sm inv-table mb-0">
                    <thead><tr><th>Date</th><th>Supplier</th><th>Invoice</th><th>Material</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th>Received by</th></tr></thead>
                    <tbody>
                    <?php foreach ($report['inward'] as $r): ?>
                        <tr>
                            <td><?= e((string)$r['inward_date']) ?></td>
                            <td><?= e((string)($r['supplier_name'] ?? '—')) ?></td>
                            <td><?= e((string)($r['invoice_no'] ?? '—')) ?></td>
                            <td><?= e((string)$r['material_name']) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['quantity'], 2)) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['rate'], 2)) ?></td>
                            <td><?= e((string)($r['received_by'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($report['inward'] === []): ?><tr><td colspan="7" class="text-center text-muted">No inward entries in this period.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <?php inv_table_scroll_close(); ?>
            <?php elseif ($tab === 'usage'): ?>
                <?php inv_table_scroll_open('min(52vh, 480px)'); ?>
                <table class="table table-sm inv-table mb-0">
                    <thead><tr><th>Date</th><th>Type</th><th>Department</th><th>Material</th><th class="text-end">Qty</th><th>Remarks</th></tr></thead>
                    <tbody>
                    <?php foreach ($report['usage'] as $r): ?>
                        <tr>
                            <td><?= e((string)$r['usage_date']) ?></td>
                            <td><?= e((string)$r['usage_type']) ?></td>
                            <td><?= e((string)($r['department'] ?? '—')) ?></td>
                            <td><?= e((string)$r['material_name']) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['quantity'], 2)) ?> <?= e((string)$r['unit']) ?></td>
                            <td><?= e((string)($r['remarks'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($report['usage'] === []): ?><tr><td colspan="6" class="text-center text-muted">No usage entries in this period.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <?php inv_table_scroll_close(); ?>
            <?php elseif ($tab === 'history'): ?>
                <?php inv_table_scroll_open('min(52vh, 480px)'); ?>
                <table class="table table-sm inv-table mb-0">
                    <thead><tr><th>Date</th><th>Type</th><th class="text-end">Qty</th><th>Dept</th><th>Operator</th><th>Remarks</th></tr></thead>
                    <tbody>
                    <?php $txRows = inv_report_transactions($pdo, $from, $to, $materialId); ?>
                    <?php foreach ($txRows as $h): ?>
                        <?php $q = (float)($h['qty_signed'] ?? 0); if (!isset($h['qty_signed']) && ($h['txn_type'] ?? '') === 'Added') { $q = (float)($h['qty'] ?? 0); } ?>
                        <tr>
                            <td><?= e((string)($h['dt'] ?? '')) ?></td>
                            <td><?= e((string)($h['txn_type'] ?? '')) ?></td>
                            <td class="text-end"><?= e(($q >= 0 ? '+' : '') . inv_format_qty($q)) ?></td>
                            <td><?= e((string)($h['department'] ?? '—')) ?></td>
                            <td><?= e((string)($h['operator_name'] ?? '—')) ?></td>
                            <td><?= e((string)($h['remarks'] ?? $h['reason'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($txRows === []): ?><tr><td colspan="6" class="text-center text-muted">No transactions in this period.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <?php inv_table_scroll_close(); ?>
            <?php elseif ($tab === 'low'): ?>
                <?php inv_table_scroll_open('min(52vh, 480px)'); ?>
                <table class="table table-sm inv-table mb-0">
                    <thead><tr><th>Code</th><th>Material</th><th class="text-end">Stock</th><th class="text-end">Min</th><th>Supplier</th><th>Location</th></tr></thead>
                    <tbody>
                    <?php foreach ($report['low'] as $r): ?>
                        <tr>
                            <td><?= e((string)$r['material_code']) ?></td>
                            <td><?= e((string)$r['material_name']) ?></td>
                            <td class="text-end text-danger"><?= e(number_format((float)$r['stock_qty'], 2)) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['reorder_level'], 2)) ?></td>
                            <td><?= e((string)($r['supplier_name'] ?? '—')) ?></td>
                            <td><?= e((string)$r['storage_location']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($report['low'] === []): ?><tr><td colspan="6" class="text-center text-muted">No low or out-of-stock items.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <?php inv_table_scroll_close(); ?>
            <?php else: ?>
                <?php inv_table_scroll_open('min(52vh, 480px)'); ?>
                <table class="table table-sm inv-table mb-0">
                    <thead><tr><th>Supplier</th><th>Material</th><th class="text-end">Stock</th><th>Unit</th></tr></thead>
                    <tbody>
                    <?php foreach ($report['supplier_stock'] as $r): ?>
                        <tr>
                            <td><?= e((string)($r['supplier_name'] ?? '—')) ?></td>
                            <td><?= e((string)$r['material_name']) ?></td>
                            <td class="text-end"><?= e(number_format((float)$r['stock_qty'], 2)) ?></td>
                            <td><?= e((string)$r['unit']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($report['supplier_stock'] === []): ?><tr><td colspan="4" class="text-center text-muted">No supplier stock data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <?php inv_table_scroll_close(); ?>
            <?php endif; ?>
    </section>
</div>
