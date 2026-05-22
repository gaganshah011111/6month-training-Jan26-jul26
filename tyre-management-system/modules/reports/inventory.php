<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_service.php';

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
$error = '';
try {
    $report = inv_report($pdo, $from, $to, $materialId, $supplierId);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$sum = $report['summary'];
$materials = inv_list_materials_master($pdo);
$suppliers = inv_list_suppliers($pdo);
$baseQs = 'page=reports/inventory&from=' . rawurlencode($from) . '&to=' . rawurlencode($to)
    . '&material_id=' . $materialId . '&supplier_id=' . $supplierId;
?>

<div class="inv-page">
    <header class="inv-page__head">
        <div>
            <h1 class="inv-page__title">Inventory Reports</h1>
            <p class="inv-page__sub">Stock summary, inward history, usage, low stock, and supplier-wise stock.</p>
        </div>
        <nav class="inv-page__links">
            <a href="<?= e(route_url('inventory/dashboard')) ?>">Dashboard</a>
        </nav>
    </header>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="inv-kpi"><span class="inv-kpi__k">Active materials</span><span class="inv-kpi__v"><?= e((string)($sum['materials'] ?? 0)) ?></span></div></div>
        <div class="col-6 col-md-3"><div class="inv-kpi"><span class="inv-kpi__k">Total stock</span><span class="inv-kpi__v"><?= e(number_format((float)($sum['total_stock'] ?? 0), 1)) ?></span></div></div>
        <div class="col-6 col-md-3"><div class="inv-kpi inv-kpi--warn"><span class="inv-kpi__k">Low stock</span><span class="inv-kpi__v"><?= e((string)($sum['low'] ?? 0)) ?></span></div></div>
        <div class="col-6 col-md-3"><div class="inv-kpi inv-kpi--danger"><span class="inv-kpi__k">Out of stock</span><span class="inv-kpi__v"><?= e((string)($sum['out'] ?? 0)) ?></span></div></div>
    </div>

    <form method="get" class="row g-2 mb-3 align-items-end">
        <input type="hidden" name="page" value="reports/inventory">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="col-auto"><label class="form-label small">From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
        <div class="col-auto"><label class="form-label small">To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
        <div class="col-auto"><label class="form-label small">Material</label>
            <select class="form-select form-select-sm" name="material_id">
                <option value="0">All</option>
                <?php foreach ($materials as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= $materialId === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['material_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><label class="form-label small">Supplier</label>
            <select class="form-select form-select-sm" name="supplier_id">
                <option value="0">All</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-primary btn-sm">Apply</button></div>
        <div class="col-auto ms-auto d-flex gap-1">
            <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= e($baseQs) ?>&amp;tab=<?= e($tab) ?>&amp;export=csv">CSV</a>
            <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= e($baseQs) ?>&amp;tab=<?= e($tab) ?>&amp;export=print" target="_blank">Print</a>
            <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= e($baseQs) ?>&amp;tab=<?= e($tab) ?>&amp;export=pdf" target="_blank">PDF</a>
        </div>
    </form>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $tab === 'stock' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=stock">Stock summary</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'inward' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=inward">Add stock history</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'usage' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=usage">Stock usage</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'low' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=low">Low stock</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'supplier' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=supplier">Supplier stock</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'history' ? 'active' : '' ?>" href="index.php?<?= e($baseQs) ?>&tab=history">Transaction history</a></li>
    </ul>

    <section class="inv-card">
        <div class="table-responsive">
            <?php if ($tab === 'stock'): ?>
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
            <?php elseif ($tab === 'inward'): ?>
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
            <?php elseif ($tab === 'usage'): ?>
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
            <?php elseif ($tab === 'history'): ?>
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
            <?php elseif ($tab === 'low'): ?>
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
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </section>
</div>
