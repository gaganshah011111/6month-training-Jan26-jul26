<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';

require_sales_manager();

$pdo = Database::connection();
$id = (int)($_GET['id'] ?? 0);
$inv = sales_get_invoice($pdo, $id);
if (!$inv) {
    echo 'Invoice not found';
    return;
}
$company = dispatch_company_name($pdo);
$disp = sales_invoice_display_status($inv);
$pending = (float)$disp['pending'];
$payments = sales_invoice_payments($pdo, $id);
$dispatches = $inv['order_id'] ? sales_dispatch_for_order($pdo, (int)$inv['order_id']) : [];
$standalone = isset($_GET['print']) || (string)($_GET['page'] ?? '') === 'sales/invoice-print';
if ($standalone) {
    $backUrl = route_url('sales/invoice', ['id' => $id]);
    $autoPrint = isset($_GET['print']);
    require __DIR__ . '/invoice_print.php';
    return;
}
?>

<div class="sales-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Invoice <?= e($inv['invoice_no']) ?></h1>
            <p class="prod-page__sub"><?= e($inv['company_name']) ?> · <?= e($inv['invoice_date']) ?> · <span class="<?= e($disp['class']) ?>"><?= e($disp['label']) ?></span></p>
        </div>
        <nav class="prod-page__links d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('sales/invoice-print', ['id' => $id])) ?>" target="_blank" rel="noopener">Print / PDF</a>
            <a class="btn btn-sm btn-primary" href="<?= e(route_url('sales/payments', ['invoice_id' => $id, 'customer_id' => (int)$inv['customer_id']])) ?>">Record payment</a>
            <?php if ($inv['order_id']): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('sales/order', ['id' => (int)$inv['order_id']])) ?>">Sales order</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-8">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Customer &amp; order</h2></div>
                <div class="sales-card__body row g-3">
                    <div class="col-md-6">
                        <p class="mb-1"><strong><?= e($inv['company_name']) ?></strong></p>
                        <p class="small text-muted mb-0"><?= e((string)($inv['gst_number'] ?? '')) ?><br><?= e((string)($inv['billing_address'] ?? '')) ?></p>
                    </div>
                    <div class="col-md-6">
                        <dl class="so-summary-dl mb-0">
                            <dt>Sales order</dt><dd><?= e((string)($inv['so_number'] ?? '—')) ?></dd>
                            <dt>Due date</dt><dd><?= e((string)($inv['due_date'] ?? '—')) ?></dd>
                            <dt>Remarks</dt><dd><?= e((string)($inv['remarks'] ?? '—')) ?></dd>
                        </dl>
                    </div>
                </div>
            </section>

            <?php if ($dispatches !== []): ?>
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Dispatch linkage</h2></div>
                <div class="sales-table-wrap sales-table-scroll">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Dispatch ID</th><th>Date</th><th>Tyre</th><th class="text-end">Qty</th></tr></thead>
                        <tbody>
                        <?php foreach ($dispatches as $d): ?>
                            <tr>
                                <td><a href="<?= e(route_url('dispatch/slip', ['id' => (int)$d['id']])) ?>" target="_blank"><?= e((string)($d['dispatch_code'] ?? $d['id'])) ?></a></td>
                                <td><?= e((string)$d['dispatch_date']) ?></td>
                                <td><?= e((string)$d['tyre_type']) ?></td>
                                <td class="text-end"><?= e((string)$d['qty']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <section class="sales-card" id="salesInvoicePrint">
                <div class="sales-card__head"><h2 class="sales-card__title">Invoice items &amp; GST</h2></div>
                <div class="sales-card__body">
                    <div class="d-flex justify-content-between mb-3">
                        <div><strong><?= e($company) ?></strong><br><span class="text-muted small">Tax invoice</span></div>
                        <div class="text-end"><strong><?= e($inv['invoice_no']) ?></strong></div>
                    </div>
                    <table class="table table-sm">
                        <thead><tr><th>Tyre</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">GST %</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($inv['items'] as $it): ?>
                            <tr>
                                <td><?= e($it['tyre_type']) ?></td>
                                <td class="text-end"><?= e((string)$it['qty']) ?></td>
                                <td class="text-end"><?= e(sales_format_money((float)$it['rate'])) ?></td>
                                <td class="text-end"><?= e((string)$it['gst_percent']) ?>%</td>
                                <td class="text-end"><?= e(sales_format_money((float)$it['line_total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="text-end">
                        <p class="mb-0">Subtotal: <?= e(sales_format_money((float)$inv['subtotal'])) ?></p>
                        <p class="mb-0">GST: <?= e(sales_format_money((float)$inv['gst_total'])) ?></p>
                        <p><strong>Total: <?= e(sales_format_money((float)$inv['total_amount'])) ?></strong></p>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="sales-card sales-card--balance">
                <div class="sales-card__head"><h2 class="sales-card__title">Balance</h2></div>
                <div class="sales-card__body">
                    <dl class="so-summary-dl">
                        <dt>Invoice total</dt><dd><?= e(sales_format_money((float)$inv['total_amount'])) ?></dd>
                        <dt>Paid</dt><dd class="text-success"><?= e(sales_format_money((float)$inv['amount_paid'])) ?></dd>
                        <dt class="so-summary-dl__total">Pending</dt><dd class="so-summary-dl__total text-danger"><?= e(sales_format_money($pending)) ?></dd>
                    </dl>
                    <span class="<?= e($disp['class']) ?>"><?= e($disp['label']) ?></span>
                </div>
            </section>

            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Payment history</h2></div>
                <div class="sales-table-wrap sales-table-scroll" style="max-height:220px">
                    <?php if ($payments === []): ?>
                        <p class="small text-muted p-3 mb-0">No payments recorded yet.</p>
                    <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Date</th><th class="text-end">Amount</th><th>Mode</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= e($p['payment_date']) ?></td>
                                <td class="text-end"><?= e(sales_format_money((float)$p['amount'])) ?></td>
                                <td><?= e($p['payment_mode']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>
