<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';
require_once __DIR__ . '/../../includes/production_service.php';

require_sales_manager();

$pdo = Database::connection();
$id = (int)($_GET['id'] ?? 0);
$preSelectCustomer = (int)($_GET['customer_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    try {
        if ($action === 'cancel' && $id > 0) {
            sales_cancel_order($pdo, $id);
            set_flash('success', 'Order cancelled.');
        } else {
            $newId = sales_save_order($pdo, $_POST, $id > 0 ? $id : null);
            set_flash('success', $id > 0 ? 'Order updated.' : 'Sales order created.');
            header('Location: ' . route_url('sales/order', ['id' => $newId]));
            exit;
        }
    } catch (Throwable $e) {
        sales_log_exception($e, 'save_order');
        set_flash('danger', 'Unable to save order. Please check quantities and try again.');
    }
    if ($id > 0) {
        header('Location: ' . route_url('sales/order', ['id' => $id]));
    } else {
        redirect('sales/orders');
    }
    exit;
}

$order = $id > 0 ? sales_get_order($pdo, $id) : null;
$isView = $order !== null && !isset($_GET['edit']);
$customers = sales_list_customers($pdo, ['status' => 'Active']);
$stockApi = route_url('api/sales-stock');
$invId = $order ? sales_invoice_id_for_order($pdo, $id) : null;
$workflowStep = 'order';
$workflowHelper = 'Approved sales orders appear in the Dispatch Queue automatically. After dispatch, invoice and payment tracking update.';
if ($order && $isView) {
    $workflowStep = match ((string)$order['status']) {
        'Completed' => 'payment',
        'Cancelled' => 'order',
        default => ($invId ? 'invoice' : ((int)array_sum(array_column($order['items'], 'qty_dispatched')) > 0
            ? 'dispatch'
            : (in_array((string)$order['status'], ['Ready', 'In Production', 'Partially Dispatched'], true) ? 'stock' : 'order'))),
    };
    $workflowHelper = 'Linked to Dispatch — pending quantity ships from Dispatch module; invoice and payments follow here.';
}
?>

<div class="sales-page so-entry-page">
    <header class="so-page-head so-page-head--compact">
        <div class="so-page-head__text">
            <h1 class="so-page-head__title"><?= $order ? e($order['so_number']) : 'Create Sales Order' ?></h1>
            <p class="so-page-head__sub"><?= $order && $isView ? 'Review fulfilment, dispatch progress, and linked invoice.' : 'Enter customer details and tyre lines with live stock verification.' ?></p>
        </div>
        <div class="so-page-head__actions">
            <a href="<?= e(route_url('sales/orders')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> All orders</a>
            <?php if (!$order): ?>
                <a href="<?= e(route_url('sales/order')) ?>" class="btn btn-primary btn-sm so-btn-create"><i class="bi bi-plus-lg me-1"></i> Create Sales Order</a>
            <?php endif; ?>
        </div>
    </header>

    <?php require __DIR__ . '/_order_workflow.php'; ?>

    <?php if ($order && $isView): ?>
        <?php
        sales_refresh_order_fulfillment($pdo, $id);
        $order = sales_get_order($pdo, $id);
        $workflowTimeline = sales_order_workflow_timeline($pdo, $order);
        $workflowStage = sales_order_composite_status($pdo, $order);
        $financial = sales_order_financial_summary($pdo, $order);
        $customerInsights = sales_customer_insights($pdo, (int)$order['customer_id']);
        $customerInsights['customer_id'] = (int)$order['customer_id'];
        $orderSb = $workflowStage;
        $stockSb = sales_order_stock_badge('READY');
        $hasPartial = false;
        $hasProduction = false;
        foreach ($order['items'] as $it) {
            $pending = (int)$it['qty_ordered'] - (int)$it['qty_dispatched'];
            if ($pending < 1) {
                continue;
            }
            $state = sales_line_stock_state($pending, sales_fg_stock((string)$it['tyre_type']));
            if ($state['stock_status'] === 'PRODUCTION REQUIRED') {
                $hasProduction = true;
            } elseif ($state['stock_status'] === 'PARTIAL STOCK') {
                $hasPartial = true;
            }
        }
        if ($hasProduction && !$hasPartial) {
            $stockSb = sales_order_stock_badge('PRODUCTION REQUIRED');
        } elseif ($hasPartial) {
            $stockSb = sales_order_stock_badge('PARTIAL STOCK');
        }
        ?>
        <?php require __DIR__ . '/_order_timeline.php'; ?>

        <div class="so-view-kpis">
            <article class="so-view-kpi"><span class="so-view-kpi__label">Status</span><span class="<?= e($orderSb['class']) ?>"><?= e($orderSb['label']) ?></span></article>
            <article class="so-view-kpi"><span class="so-view-kpi__label">Stock readiness</span><span class="<?= e($stockSb['class']) ?>"><?= e($stockSb['label']) ?></span></article>
            <article class="so-view-kpi"><span class="so-view-kpi__label">Order total</span><strong><?= e(sales_format_money((float)$order['total_amount'])) ?></strong></article>
            <article class="so-view-kpi"><span class="so-view-kpi__label">Payment</span><strong><?= e((string)$financial['payment_status']) ?></strong></article>
            <article class="so-view-kpi"><span class="so-view-kpi__label">Customer</span><strong><?= e($order['company_name']) ?></strong></article>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="so-section-card">
                    <header class="so-section-card__head">
                        <h2 class="so-section-card__title">Order lines</h2>
                        <div class="so-section-card__actions">
                            <?php if (!in_array((string)$order['status'], ['Completed', 'Cancelled'], true)): ?>
                                <a href="<?= e(route_url('sales/order', ['id' => $id, 'edit' => 1])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
                                <a href="<?= e(route_url('sales/dispatch')) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-truck me-1"></i> Dispatch tracking</a>
                                <a href="<?= e(route_url('sales/order-print', ['id' => $id])) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Print / PDF</a>
                                <?php if ($invId): ?>
                                    <a href="<?= e(route_url('sales/invoice', ['id' => $invId])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-receipt me-1"></i> Invoice</a>
                                <?php endif; ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Cancel this order?');"><?= csrf_input() ?><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button></form>
                            <?php endif; ?>
                        </div>
                    </header>
                    <div class="so-table-wrap">
                        <table class="table table-sm so-lines-table mb-0">
                            <thead>
                                <tr>
                                    <th>Tyre type</th>
                                    <th class="text-end">Ordered</th>
                                    <th class="text-end">Dispatched</th>
                                    <th class="text-end">Remaining</th>
                                    <th class="text-end">Rate</th>
                                    <th>Stock / fulfilment</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($order['items'] as $it): ?>
                                <?php
                                $pending = (int)$it['qty_ordered'] - (int)$it['qty_dispatched'];
                                $live = sales_fg_stock((string)$it['tyre_type']);
                                $lineState = sales_line_stock_state($pending, $live);
                                $fb = sales_order_stock_badge($lineState['stock_status']);
                                ?>
                                <tr>
                                    <td><?= e($it['tyre_type']) ?></td>
                                    <td class="text-end"><?= e((string)$it['qty_ordered']) ?></td>
                                    <td class="text-end"><?= e((string)$it['qty_dispatched']) ?></td>
                                    <td class="text-end"><?= e((string)$pending) ?></td>
                                    <td class="text-end"><?= e(sales_format_money((float)$it['rate'])) ?></td>
                                    <td>
                                        <span class="so-stock-pill">FG stock <?= e((string)$live) ?></span>
                                        <?php if ($pending > 0 && $lineState['dispatchable_qty'] > 0): ?>
                                            <span class="so-stock-pill">Can ship <?= e((string)$lineState['dispatchable_qty']) ?></span>
                                        <?php endif; ?>
                                        <span class="<?= e($fb['class']) ?>"><?= e($fb['label']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div class="col-lg-4">
                <?php require __DIR__ . '/_order_financial.php'; ?>
                <?php require __DIR__ . '/_customer_insights.php'; ?>
                <aside class="so-summary-card">
                    <h3 class="so-summary-card__title">Order summary</h3>
                    <dl class="so-summary-dl">
                        <dt>Order date</dt><dd><?= e($order['order_date']) ?></dd>
                        <dt>Delivery</dt><dd><?= e((string)($order['delivery_date'] ?? '—')) ?></dd>
                        <dt>Priority</dt><dd><?php $pb = sales_priority_badge((string)$order['priority']); ?><span class="<?= e($pb['class']) ?>"><?= e($pb['label']) ?></span></dd>
                        <dt>Payment terms</dt><dd><?= e((string)($order['payment_terms'] ?? '—')) ?></dd>
                        <dt>Subtotal</dt><dd><?= e(sales_format_money((float)$order['subtotal'])) ?></dd>
                        <dt>GST</dt><dd><?= e(sales_format_money((float)$order['gst_total'])) ?></dd>
                        <dt>Discount</dt><dd><?= e(sales_format_money((float)$order['discount_amount'])) ?></dd>
                        <dt class="so-summary-dl__total">Final amount</dt><dd class="so-summary-dl__total"><?= e(sales_format_money((float)$order['total_amount'])) ?></dd>
                    </dl>
                    <?php if (trim((string)($order['remarks'] ?? '')) !== ''): ?>
                        <p class="so-summary-notes"><strong>Notes:</strong> <?= e($order['remarks']) ?></p>
                    <?php endif; ?>
                </aside>
                <aside class="so-linkage-card">
                    <h3 class="so-linkage-card__title"><i class="bi bi-diagram-3 me-1"></i> Workflow linkage</h3>
                    <ol class="so-linkage-list">
                        <li><strong>Sales order</strong> — <?= e($order['so_number']) ?></li>
                        <li><strong>Dispatch</strong> — <?= (int)array_sum(array_column($order['items'], 'qty_dispatched')) > 0 ? 'In progress' : 'Pending in Dispatch Manager' ?></li>
                        <li><strong>Invoice</strong> — <?= $invId ? 'Generated' : 'After dispatch' ?></li>
                        <li><strong>Payment</strong> — <?= (string)$order['status'] === 'Completed' ? 'Closed' : 'Track in Accounts Department' ?></li>
                    </ol>
                </aside>
            </div>
        </div>

    <?php else: ?>
        <form method="post" id="salesOrderForm" class="so-entry-form" data-stock-api="<?= e($stockApi) ?>">
            <?= csrf_input() ?>
            <div class="row g-3 align-items-start">
                <div class="col-lg-8 so-entry-main">
                    <section class="so-section-card so-section-card--flow">
                        <header class="so-section-card__head">
                            <span class="so-section-card__num">1</span>
                            <h2 class="so-section-card__title">Customer &amp; order details</h2>
                        </header>
                        <div class="so-section-card__body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="so-field-label" for="so-customer">Customer <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm erp-select-search" name="customer_id" id="so-customer" required data-placeholder="Search customer…">
                                        <option value="">Select customer</option>
                                        <?php foreach ($customers as $c): ?>
                                            <option value="<?= (int)$c['id'] ?>" <?= ($order && (int)$order['customer_id'] === (int)$c['id']) || (!$order && $preSelectCustomer === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <label class="so-field-label" for="so-order-date">Order date</label>
                                    <input type="date" class="form-control form-control-sm" name="order_date" id="so-order-date" value="<?= e((string)($order['order_date'] ?? date('Y-m-d'))) ?>" required>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <label class="so-field-label" for="so-delivery-date">Delivery date</label>
                                    <input type="date" class="form-control form-control-sm" name="delivery_date" id="so-delivery-date" value="<?= e((string)($order['delivery_date'] ?? '')) ?>">
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <label class="so-field-label" for="so-priority">Priority</label>
                                    <select class="form-select form-select-sm" name="priority" id="so-priority">
                                        <?php foreach (SALES_ORDER_PRIORITIES as $p): ?>
                                            <option <?= ($order['priority'] ?? 'Medium') === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="so-field-label" for="so-payment-terms">Payment terms</label>
                                    <input class="form-control form-control-sm" name="payment_terms" id="so-payment-terms" value="<?= e((string)($order['payment_terms'] ?? '')) ?>" placeholder="e.g. Net 30 days">
                                </div>
                                <div class="col-sm-6">
                                    <label class="so-field-label" for="so-order-discount">Order discount (₹)</label>
                                    <input type="number" class="form-control form-control-sm" name="discount_amount" id="so-order-discount" min="0" step="0.01" value="<?= e((string)($order['discount_amount'] ?? '0')) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="so-field-label" for="so-remarks">Order notes</label>
                                    <textarea class="form-control form-control-sm" name="remarks" id="so-remarks" rows="2" placeholder="Delivery instructions, PO reference, etc."><?= e((string)($order['remarks'] ?? '')) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="so-section-card so-section-card--flow so-section-card--items">
                        <header class="so-section-card__head">
                            <span class="so-section-card__num">2</span>
                            <h2 class="so-section-card__title">Order items</h2>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="salesAddLine"><i class="bi bi-plus-lg me-1"></i> Add line</button>
                        </header>
                        <div class="so-section-card__body">
                            <div id="salesOrderLines" class="so-lines-list">
                            <?php
                            $items = $order['items'] ?? [['tyre_type' => '', 'qty_ordered' => '', 'rate' => '', 'gst_percent' => 18, 'discount_amount' => 0]];
                            if ($items === []) {
                                $items = [['tyre_type' => '', 'qty_ordered' => '', 'rate' => '', 'gst_percent' => 18, 'discount_amount' => 0]];
                            }
                            $lineIndex = 0;
                            foreach ($items as $it):
                                $lineIndex++;
                                require __DIR__ . '/_order_line_card.php';
                            endforeach;
                            ?>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <aside class="so-summary-card so-summary-card--sticky">
                        <h3 class="so-summary-card__title">Live order summary</h3>
                        <dl class="so-summary-dl">
                            <dt>Total items</dt><dd id="soSumItems">0</dd>
                            <dt>Total quantity</dt><dd id="soSumQty">0</dd>
                            <dt>Subtotal</dt><dd id="soSumSub">₹0.00</dd>
                            <dt>GST</dt><dd id="soSumGst">₹0.00</dd>
                            <dt>Line discounts</dt><dd id="soSumLineDisc">₹0.00</dd>
                            <dt>Order discount</dt><dd id="soSumOrderDisc">₹0.00</dd>
                            <dt class="so-summary-dl__total">Final amount</dt><dd class="so-summary-dl__total" id="soSumFinal">₹0.00</dd>
                        </dl>
                        <div class="so-readiness">
                            <span class="so-readiness__label">Stock readiness</span>
                            <span id="soSumReadiness" class="so-badge so-badge--pending">Add items</span>
                        </div>
                    </aside>
                    <aside class="so-linkage-card">
                        <h3 class="so-linkage-card__title"><i class="bi bi-diagram-3 me-1"></i> Dispatch linkage</h3>
                        <ol class="so-linkage-list">
                            <li><strong>Create order</strong> — stock checked per line</li>
                            <li><strong>Dispatch</strong> — order appears in Dispatch Manager</li>
                            <li><strong>Invoice</strong> — auto-created after dispatch</li>
                            <li><strong>Payment</strong> — record receipts in CRM</li>
                        </ol>
                    </aside>
                </div>
            </div>

            <footer class="so-form-footer-bar">
                <div class="so-form-footer-bar__inner">
                    <?php if ($order): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/order', ['id' => $id])) ?>">Cancel edit</a>
                    <?php else: ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('sales/orders')) ?>">Back to list</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-sm so-btn-submit">
                        <i class="bi bi-check2-circle me-1"></i> <?= $order ? 'Update Sales Order' : 'Create Sales Order' ?>
                    </button>
                </div>
            </footer>
        </form>
        <script>window.SALES_TYRE_TYPES = <?= json_encode(TYRE_TYPES, JSON_THROW_ON_ERROR) ?>;</script>
    <?php endif; ?>
</div>
