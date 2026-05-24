<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';
require_once __DIR__ . '/../../includes/production_service.php';

require_sales_manager();

$pdo = Database::connection();
$filters = [
    'customer_id' => (int)($_GET['customer_id'] ?? 0) ?: null,
    'status' => (string)($_GET['status'] ?? ''),
    'priority' => (string)($_GET['priority'] ?? ''),
    'dispatch_status' => (string)($_GET['dispatch_status'] ?? ''),
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
    'tyre_type' => trim((string)($_GET['tyre_type'] ?? '')),
];
$loadError = false;
$rows = [];
$customers = [];

try {
    $rows = sales_list_orders($pdo, $filters);
    $customers = sales_list_customers($pdo, ['status' => 'Active']);
} catch (Throwable $e) {
    sales_log_exception($e, 'sales_orders');
    $loadError = true;
}

$filterQs = array_filter([
    'page' => 'sales/orders',
    'customer_id' => $filters['customer_id'] ? (string)$filters['customer_id'] : '',
    'status' => $filters['status'],
    'priority' => $filters['priority'],
    'dispatch_status' => $filters['dispatch_status'],
    'from' => $filters['from'],
    'to' => $filters['to'],
    'tyre_type' => $filters['tyre_type'],
], static fn($v) => $v !== '' && $v !== '0');

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-orders-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['SO Number', 'Customer', 'Order Date', 'Delivery', 'Tyre Types', 'Total Qty', 'Amount', 'Stock', 'Dispatch', 'Priority']);
    foreach ($rows as $o) {
        fputcsv($out, [
            $o['so_number'],
            $o['company_name'],
            $o['order_date'],
            $o['delivery_date'] ?? '',
            $o['tyre_types'] ?? '',
            $o['total_qty'] ?? 0,
            $o['total_amount'],
            $o['stock_status'] ?? '',
            $o['status'],
            $o['priority'],
        ]);
    }
    fclose($out);
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Sales Orders</title>';
    echo '<style>body{font-family:system-ui,sans-serif;font-size:12px;padding:1rem}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px 8px;text-align:left}th{background:#f1f5f9}</style>';
    echo '</head><body onload="window.print()"><h1>Sales Orders</h1><p>Generated ' . e(date('d M Y H:i')) . '</p><table><thead><tr>';
    echo '<th>SO</th><th>Customer</th><th>Date</th><th>Qty</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $o) {
        echo '<tr><td>' . e($o['so_number']) . '</td><td>' . e($o['company_name']) . '</td><td>' . e($o['order_date']) . '</td>';
        echo '<td>' . e((string)($o['total_qty'] ?? 0)) . '</td><td>' . e(sales_format_money((float)$o['total_amount'])) . '</td><td>' . e($o['status']) . '</td></tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}
?>

<div class="sales-page so-list-page">
    <header class="so-page-head">
        <div class="so-page-head__text">
            <h1 class="so-page-head__title">Sales Orders</h1>
            <p class="so-page-head__sub">Manage customer tyre orders, stock status, and dispatch readiness.</p>
        </div>
        <div class="so-page-head__actions">
            <a href="<?= e(route_url('sales/order')) ?>" class="btn btn-primary btn-sm so-btn-create">
                <i class="bi bi-plus-lg me-1"></i> Create Sales Order
            </a>
            <a href="<?= e(route_url('sales/orders', array_merge($filterQs, ['export' => 'csv']))) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Excel
            </a>
            <a href="<?= e(route_url('sales/orders', array_merge($filterQs, ['export' => 'pdf']))) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
            </a>
        </div>
    </header>

    <?php if ($loadError): ?><?= sales_error_alert('Unable to load sales orders.') ?><?php endif; ?>

    <section class="so-filter-card">
        <div class="so-filter-card__head">
            <i class="bi bi-funnel"></i>
            <span>Filter orders</span>
        </div>
        <form method="get" class="so-filter-form">
            <input type="hidden" name="page" value="sales/orders">
            <div class="so-filter-grid">
                <div class="so-filter-field">
                    <label for="so-f-customer">Customer</label>
                    <select class="form-select form-select-sm erp-select-search" name="customer_id" id="so-f-customer">
                        <option value="0">All customers</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $filters['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="so-filter-field">
                    <label for="so-f-status">Order status</label>
                    <select class="form-select form-select-sm" name="status" id="so-f-status">
                        <option value="">All statuses</option>
                        <?php foreach (SALES_ORDER_STATUSES as $s): ?>
                            <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="so-filter-field">
                    <label for="so-f-tyre">Tyre type</label>
                    <select class="form-select form-select-sm erp-select-search" name="tyre_type" id="so-f-tyre" data-placeholder="All tyre types">
                        <option value="">All tyre types</option>
                        <?php foreach (TYRE_TYPES as $t): ?>
                            <option value="<?= e($t) ?>" <?= $filters['tyre_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="so-filter-field">
                    <label for="so-f-priority">Priority</label>
                    <select class="form-select form-select-sm" name="priority" id="so-f-priority">
                        <option value="">All priorities</option>
                        <?php foreach (SALES_ORDER_PRIORITIES as $p): ?>
                            <option value="<?= e($p) ?>" <?= $filters['priority'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="so-filter-field">
                    <label for="so-f-from">From date</label>
                    <input type="date" class="form-control form-control-sm" name="from" id="so-f-from" value="<?= e($filters['from']) ?>">
                </div>
                <div class="so-filter-field">
                    <label for="so-f-to">To date</label>
                    <input type="date" class="form-control form-control-sm" name="to" id="so-f-to" value="<?= e($filters['to']) ?>">
                </div>
                <div class="so-filter-field">
                    <label for="so-f-dispatch">Dispatch status</label>
                    <select class="form-select form-select-sm" name="dispatch_status" id="so-f-dispatch">
                        <option value="">All dispatch states</option>
                        <option value="pending" <?= $filters['dispatch_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="production" <?= $filters['dispatch_status'] === 'production' ? 'selected' : '' ?>>In production</option>
                        <option value="ready" <?= $filters['dispatch_status'] === 'ready' ? 'selected' : '' ?>>Ready</option>
                        <option value="partial" <?= $filters['dispatch_status'] === 'partial' ? 'selected' : '' ?>>Partial dispatch</option>
                        <option value="dispatched" <?= $filters['dispatch_status'] === 'dispatched' ? 'selected' : '' ?>>Dispatched / completed</option>
                    </select>
                </div>
            </div>
            <div class="so-filter-actions">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i> Apply Filters</button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/orders')) ?>">Reset</a>
            </div>
        </form>
    </section>

    <section class="so-table-card">
        <div class="so-table-card__head">
            <h2 class="so-table-card__title">Order register</h2>
            <span class="so-table-card__count"><?= count($rows) ?> order(s)</span>
        </div>
        <div class="so-table-wrap">
            <table class="table table-sm so-orders-table mb-0">
                <thead>
                    <tr>
                        <th>SO Number</th>
                        <th>Customer</th>
                        <th>Order Date</th>
                        <th>Delivery Date</th>
                        <th>Tyre Types</th>
                        <th class="text-end">Total Qty</th>
                        <th class="text-end">Amount</th>
                        <th>Stock Status</th>
                        <th>Dispatch Status</th>
                        <th>Priority</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $o):
                    $oid = (int)$o['id'];
                    $orderSb = sales_order_status_badge((string)$o['status']);
                    $stockSb = sales_order_stock_badge((string)($o['stock_status'] ?? 'Pending'));
                    $prioSb = sales_priority_badge((string)$o['priority']);
                    $invId = sales_invoice_id_for_order($pdo, $oid);
                    $canEdit = !in_array((string)$o['status'], ['Completed', 'Cancelled'], true);
                ?>
                    <tr>
                        <td><a class="so-so-link" href="<?= e(route_url('sales/order', ['id' => $oid])) ?>"><?= e($o['so_number']) ?></a></td>
                        <td><?= e($o['company_name']) ?></td>
                        <td><?= e($o['order_date']) ?></td>
                        <td><?= e((string)($o['delivery_date'] ?? '—')) ?></td>
                        <td class="so-tyre-cell" title="<?= e((string)($o['tyre_types'] ?? '')) ?>"><?= e($o['tyre_types_short'] ?? '—') ?></td>
                        <td class="text-end"><?= e((string)($o['total_qty'] ?? 0)) ?></td>
                        <td class="text-end fw-semibold"><?= e(sales_format_money((float)$o['total_amount'])) ?></td>
                        <td><span class="<?= e($stockSb['class']) ?>"><?= e($stockSb['label']) ?></span></td>
                        <td><span class="<?= e($orderSb['class']) ?>"><?= e($orderSb['label']) ?></span></td>
                        <td><span class="<?= e($prioSb['class']) ?>"><?= e($prioSb['label']) ?></span></td>
                        <td class="text-end">
                            <div class="so-row-actions">
                                <a href="<?= e(route_url('sales/order', ['id' => $oid])) ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                <?php if ($canEdit): ?>
                                    <a href="<?= e(route_url('sales/order', ['id' => $oid, 'edit' => 1])) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="<?= e(route_url('sales/dispatch-entry', ['sales_order_id' => $oid])) ?>" class="btn btn-sm btn-outline-primary" title="Dispatch"><i class="bi bi-truck"></i></a>
                                <?php endif; ?>
                                <?php if ($invId): ?>
                                    <a href="<?= e(route_url('sales/invoice', ['id' => $invId])) ?>" class="btn btn-sm btn-outline-secondary" title="Invoice"><i class="bi bi-receipt"></i></a>
                                <?php else: ?>
                                    <span class="btn btn-sm btn-outline-secondary disabled" title="No invoice yet"><i class="bi bi-receipt"></i></span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="11" class="text-center text-muted py-5">No orders match your filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
