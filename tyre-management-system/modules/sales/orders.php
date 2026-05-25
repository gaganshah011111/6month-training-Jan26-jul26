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
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
    'q' => trim((string)($_GET['q'] ?? '')),
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

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-orders-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['SO Number', 'Customer', 'Order Date', 'Amount', 'Status']);
    foreach ($rows as $o) {
        fputcsv($out, [$o['so_number'], $o['company_name'], $o['order_date'], $o['total_amount'], $o['status']]);
    }
    fclose($out);
    exit;
}
?>

<div class="sales-page crm-layout so-list-page">
    <?= crm_page_header(
        'Sales Orders',
        'Create and track customer orders until dispatch and billing.',
        '<a href="' . e(route_url('sales/order')) . '" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create order</a>'
    ) ?>

    <?php if ($loadError): ?><?= sales_error_alert('Unable to load sales orders.') ?><?php endif; ?>

    <form method="get" class="crm-filter-inline">
        <input type="hidden" name="page" value="sales/orders">
        <div class="crm-filter-inline__field" style="flex:2 1 180px">
            <label for="so-q">Search</label>
            <input type="search" class="form-control form-control-sm" name="q" id="so-q" value="<?= e($filters['q']) ?>" placeholder="SO number or customer">
        </div>
        <div class="crm-filter-inline__field">
            <label for="so-cust">Customer</label>
            <select class="form-select form-select-sm erp-select-search" name="customer_id" id="so-cust" data-placeholder="Search customer…">
                <option value="0">All</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $filters['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="crm-filter-inline__field">
            <label for="so-st">Status</label>
            <select class="form-select form-select-sm" name="status" id="so-st">
                <option value="">All</option>
                <?php foreach (SALES_ORDER_STATUSES as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="crm-filter-inline__field">
            <label for="so-from">From</label>
            <input type="date" class="form-control form-control-sm" name="from" id="so-from" value="<?= e($filters['from']) ?>">
        </div>
        <div class="crm-filter-inline__field">
            <label for="so-to">To</label>
            <input type="date" class="form-control form-control-sm" name="to" id="so-to" value="<?= e($filters['to']) ?>">
        </div>
        <div class="crm-filter-inline__actions">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/orders')) ?>">Reset</a>
        </div>
    </form>

    <section class="crm-section">
        <div class="crm-section__head">
            <h2 class="crm-section__title">Orders <span class="text-muted fw-normal small"><?= count($rows) ?></span></h2>
        </div>
        <div class="crm-section__body">
            <?= crm_table_open('so-orders-table') ?>
            <thead>
                <tr>
                    <th>SO number</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $o):
                $oid = (int)$o['id'];
                $orderSb = sales_order_status_badge((string)$o['status']);
                $invId = sales_invoice_id_for_order($pdo, $oid);
                $canEdit = !in_array((string)$o['status'], ['Completed', 'Cancelled'], true);
                $actions = [
                    ['label' => 'View details', 'url' => route_url('sales/order', ['id' => $oid]), 'icon' => 'bi-eye'],
                ];
                if ($canEdit) {
                    $actions[] = ['label' => 'Edit order', 'url' => route_url('sales/order', ['id' => $oid, 'edit' => 1]), 'icon' => 'bi-pencil'];
                    $actions[] = ['label' => 'Dispatch tracking', 'url' => route_url('sales/dispatch'), 'icon' => 'bi-truck'];
                }
                if ($invId) {
                    $actions[] = ['label' => 'Invoice', 'url' => route_url('sales/invoice', ['id' => $invId]), 'icon' => 'bi-receipt'];
                }
            ?>
                <tr>
                    <td><a href="<?= e(route_url('sales/order', ['id' => $oid])) ?>"><strong><?= e($o['so_number']) ?></strong></a></td>
                    <td><?= e($o['company_name']) ?></td>
                    <td><?= e($o['order_date']) ?></td>
                    <td class="text-end fw-semibold"><?= e(sales_format_money((float)$o['total_amount'])) ?></td>
                    <td><span class="<?= e($orderSb['class']) ?>"><?= e($orderSb['label']) ?></span></td>
                    <td class="text-end"><?= crm_action_icons($actions) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="sales-empty text-center py-4">No orders match your filters.</td></tr>
            <?php endif; ?>
            </tbody>
            <?= crm_table_close() ?>
        </div>
    </section>
</div>
