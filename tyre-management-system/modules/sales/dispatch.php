<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();
$loadError = false;
$summary = [];
$rows = [];
$filterQ = trim((string)($_GET['q'] ?? ''));
$filterDelivery = (string)($_GET['delivery_status'] ?? '');

if (isset($_GET['export'])) {
    $rows = sales_crm_dispatch_shipment_rows($pdo, ['q' => $filterQ, 'delivery_status' => $filterDelivery]);
    $export = (string)$_GET['export'];
    $headers = ['Dispatch ID', 'SO', 'Customer', 'Qty', 'Date', 'Delivery', 'Invoice', 'Payment'];
    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            $r['dispatch_code'],
            $r['so_number'],
            $r['company_name'],
            $r['qty'],
            $r['dispatch_date'],
            $r['delivery_status']['label'],
            $r['invoice_status']['label'],
            $r['payment_status']['label'],
        ];
    }
    if ($export === 'csv') {
        erp_send_csv('dispatch-tracking-' . date('Y-m-d') . '.csv', $headers, $data);
    }
    erp_print_html_table('Dispatch Tracking', $headers, $data, $export === 'pdf');
}

try {
    $summary = sales_crm_dispatch_shipment_summary($pdo);
    $rows = sales_crm_dispatch_shipment_rows($pdo, [
        'q' => $filterQ,
        'delivery_status' => $filterDelivery,
    ]);
} catch (Throwable $e) {
    sales_log_exception($e, 'sales_dispatch_tracking');
    $loadError = true;
}
?>

<div class="sales-page crm-layout crm-dispatch-track">
    <?= crm_page_header(
        'Dispatch Tracking',
        'Read-only shipment monitor — dispatches are created in the Dispatch Manager module.'
    ) ?>

    <?php if ($loadError): ?><?= sales_error_alert('Unable to load dispatch tracking.') ?><?php endif; ?>

    <div class="crm-summary-4">
        <article class="sales-kpi"><span class="sales-kpi__label">Total</span><strong><?= e((string)($summary['total'] ?? 0)) ?></strong></article>
        <article class="sales-kpi sales-kpi--warn"><span class="sales-kpi__label">In transit</span><strong><?= e((string)($summary['in_transit'] ?? 0)) ?></strong></article>
        <article class="sales-kpi sales-kpi--ok"><span class="sales-kpi__label">Delivered</span><strong><?= e((string)($summary['delivered'] ?? 0)) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Pending billing</span><strong><?= e((string)($summary['pending_billing'] ?? 0)) ?></strong></article>
    </div>

    <form method="get" class="crm-filter-inline mb-0">
        <input type="hidden" name="page" value="sales/dispatch">
        <div class="crm-filter-inline__field" style="flex:2 1 200px">
            <label>Search</label>
            <input type="search" class="form-control form-control-sm" name="q" id="crm-dispatch-search"
                   value="<?= e($filterQ) ?>" placeholder="Dispatch ID, SO, customer…" autocomplete="off">
        </div>
        <div class="crm-filter-inline__field">
            <label>Delivery</label>
            <select class="form-select form-select-sm" name="delivery_status">
                <option value="">All</option>
                <option value="pending" <?= $filterDelivery === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_transit" <?= $filterDelivery === 'in_transit' ? 'selected' : '' ?>>Dispatched</option>
                <option value="delivered" <?= $filterDelivery === 'delivered' ? 'selected' : '' ?>>Delivered</option>
            </select>
        </div>
        <div class="crm-filter-inline__actions">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/dispatch')) ?>">Reset</a>
        </div>
    </form>

    <section class="crm-section">
        <div class="crm-section__head">
            <h2 class="crm-section__title">Shipments</h2>
            <?= erp_export_toolbar('crm-dispatch-table', 'dispatch-tracking') ?>
        </div>
        <div class="crm-section__body">
            <?= crm_table_open('crm-dispatch-table') ?>
            <thead>
                <tr>
                    <th>Dispatch</th>
                    <th>SO</th>
                    <th>Customer</th>
                    <th class="text-end">Qty</th>
                    <th>Date</th>
                    <th>Delivery</th>
                    <th>Invoice</th>
                    <th>Payment</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                $hay = strtolower(implode(' ', [$r['dispatch_code'], $r['so_number'], $r['company_name']]));
                $invId = $r['invoice_id'] ?? null;
                $slipId = (int)$r['dispatch_id'];
                $actions = [];
                if ($r['order_id'] > 0) {
                    $actions[] = ['label' => 'View SO', 'url' => route_url('sales/order', ['id' => (int)$r['order_id']]), 'icon' => 'bi-eye'];
                }
                if ($invId) {
                    $actions[] = ['label' => 'View invoice', 'url' => route_url('sales/invoice', ['id' => $invId]), 'icon' => 'bi-receipt'];
                }
                $actions[] = ['label' => 'Record payment', 'url' => route_url('sales/payments', ['customer_id' => (int)$r['customer_id']]), 'icon' => 'bi-cash'];
                $actions[] = ['label' => 'Slip PDF', 'url' => dispatch_slip_url($slipId), 'icon' => 'bi-file-pdf', 'attrs' => 'target="_blank" rel="noopener"'];
                $actions[] = ['label' => 'Print slip', 'url' => dispatch_slip_url($slipId, 'print'), 'icon' => 'bi-printer', 'attrs' => 'target="_blank" rel="noopener"'];
                ?>
                <tr class="crm-dispatch-row" data-search="<?= e($hay) ?>">
                    <td><strong><?= e($r['dispatch_code']) ?></strong></td>
                    <td><?= e($r['so_number']) ?></td>
                    <td><?= e($r['company_name']) ?></td>
                    <td class="text-end"><?= e((string)$r['qty']) ?></td>
                    <td><?= e($r['dispatch_date']) ?></td>
                    <td><span class="<?= e($r['delivery_status']['class']) ?>"><?= e($r['delivery_status']['label']) ?></span></td>
                    <td><span class="<?= e($r['invoice_status']['class']) ?>"><?= e($r['invoice_status']['label']) ?></span></td>
                    <td><span class="<?= e($r['payment_status']['class']) ?>"><?= e($r['payment_status']['label']) ?></span></td>
                    <td class="text-end"><?= crm_action_icons($actions) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="9" class="sales-empty text-center py-4">No linked dispatches yet.</td></tr>
            <?php endif; ?>
            </tbody>
            <?= crm_table_close() ?>
        </div>
    </section>
</div>
<script src="assets/js/sales-dispatch-track.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/sales-dispatch-track.js')) ?>"></script>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
