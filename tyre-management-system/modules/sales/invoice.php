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
$pending = (float)$inv['total_amount'] - (float)$inv['amount_paid'];
$standalone = isset($_GET['print']) || (string)($_GET['page'] ?? '') === 'sales/invoice-print';
if ($standalone) {
    require __DIR__ . '/invoice_print.php';
    return;
}
?>

<div class="sales-page">
    <header class="prod-page__head">
        <div><h1 class="prod-page__title">Invoice <?= e($inv['invoice_no']) ?></h1><p class="prod-page__sub"><?= e($inv['company_name']) ?> · <?= e($inv['invoice_date']) ?></p></div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('sales/invoice-print', ['id' => $id])) ?>" target="_blank">Print / PDF</a>
            <a href="<?= e(route_url('sales/payments', ['invoice_id' => $id, 'customer_id' => (int)$inv['customer_id']])) ?>">Record payment</a>
        </nav>
    </header>
    <?php require __DIR__ . '/_nav.php'; ?>
    <section class="sales-card">
        <div class="sales-card__body" id="salesInvoicePrint">
            <div class="d-flex justify-content-between mb-3">
                <div><strong><?= e($company) ?></strong><br><span class="text-muted small">Tyre manufacturing — sales invoice</span></div>
                <div class="text-end"><strong><?= e($inv['invoice_no']) ?></strong><br>Date: <?= e($inv['invoice_date']) ?><br>Due: <?= e((string)($inv['due_date'] ?? '—')) ?></div>
            </div>
            <p class="small"><strong>Bill to:</strong> <?= e($inv['company_name']) ?> · <?= e((string)($inv['gst_number'] ?? '')) ?><br><?= e((string)($inv['billing_address'] ?? '')) ?></p>
            <table class="table table-sm"><thead><tr><th>Tyre</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">GST</th><th class="text-end">Total</th></tr></thead><tbody>
            <?php foreach ($inv['items'] as $it): ?>
                <tr><td><?= e($it['tyre_type']) ?></td><td class="text-end"><?= e((string)$it['qty']) ?></td><td class="text-end"><?= e(sales_format_money((float)$it['rate'])) ?></td><td class="text-end"><?= e((string)$it['gst_percent']) ?>%</td><td class="text-end"><?= e(sales_format_money((float)$it['line_total'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <div class="text-end"><p class="mb-0">Subtotal: <?= e(sales_format_money((float)$inv['subtotal'])) ?></p><p class="mb-0">GST: <?= e(sales_format_money((float)$inv['gst_total'])) ?></p><p><strong>Total: <?= e(sales_format_money((float)$inv['total_amount'])) ?></strong></p><p class="text-muted small">Paid: <?= e(sales_format_money((float)$inv['amount_paid'])) ?> · Pending: <?= e(sales_format_money($pending)) ?></p></div>
        </div>
    </section>
</div>
