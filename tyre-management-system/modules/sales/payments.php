<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        sales_save_payment($pdo, $_POST);
        set_flash('success', 'Payment recorded.');
    } catch (Throwable $e) {
        sales_log_exception($e, 'save_payment');
        set_flash('danger', 'Unable to record payment. Please verify the amount and try again.');
    }
    redirect('sales/payments');
}

$loadError = false;
$customers = [];
$openInvoices = [];
$recent = [];
$preCustomer = (int)($_GET['customer_id'] ?? 0);
$preInvoice = (int)($_GET['invoice_id'] ?? 0);

try {
    $customers = sales_list_customers($pdo, ['status' => 'Active']);
    $openInvoices = sales_list_invoices($pdo, ['payment_status' => '']);
    $recent = $pdo->query(
        'SELECT p.*, i.invoice_no, c.company_name FROM sales_payments p
         INNER JOIN sales_invoices i ON i.id = p.invoice_id
         INNER JOIN sales_customers c ON c.id = p.customer_id
         ORDER BY p.payment_date DESC, p.id DESC LIMIT 40'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    sales_log_exception($e, 'sales_payments');
    $loadError = true;
}
?>

<div class="sales-page">
    <header class="prod-page__head"><div><h1 class="prod-page__title">Payment Tracking</h1><p class="prod-page__sub">Record receipts against sales invoices.</p></div></header>
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if ($loadError): ?><?= sales_error_alert('Unable to load payments.') ?><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Payment entry</h2></div>
                <div class="sales-card__body">
                    <form method="post" class="vstack gap-2">
                        <?= csrf_input() ?>
                        <div><label class="form-label">Customer</label><select class="form-select form-select-sm" name="customer_id" id="payCustomer" required><option value="">—</option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $preCustomer === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option><?php endforeach; ?></select></div>
                        <div><label class="form-label">Invoice</label><select class="form-select form-select-sm" name="invoice_id" required><?php foreach ($openInvoices as $inv): if ((float)$inv['total_amount'] - (float)$inv['amount_paid'] < 0.01) continue; ?><option value="<?= (int)$inv['id'] ?>" data-customer="<?= (int)$inv['customer_id'] ?>" <?= $preInvoice === (int)$inv['id'] ? 'selected' : '' ?>><?= e($inv['invoice_no']) ?> — <?= e(sales_format_money((float)$inv['total_amount'] - (float)$inv['amount_paid'])) ?> due</option><?php endforeach; ?></select></div>
                        <div><label class="form-label">Payment date</label><input type="date" class="form-control form-control-sm" name="payment_date" value="<?= e(date('Y-m-d')) ?>" required></div>
                        <div><label class="form-label">Amount</label><input type="number" class="form-control form-control-sm" name="amount" min="0.01" step="0.01" required></div>
                        <div><label class="form-label">Mode</label><select class="form-select form-select-sm" name="payment_mode"><?php foreach (SALES_PAYMENT_MODES as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select></div>
                        <div><label class="form-label">Reference</label><input class="form-control form-control-sm" name="reference_no"></div>
                        <div><label class="form-label">Remarks</label><input class="form-control form-control-sm" name="remarks"></div>
                        <button class="btn btn-primary btn-sm w-100" type="submit">Save payment</button>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <section class="sales-card">
                <div class="sales-card__head"><h2 class="sales-card__title">Recent payments</h2></div>
                <div class="sales-table-wrap">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Date</th><th>Customer</th><th>Invoice</th><th>Mode</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $p): ?>
                            <tr><td><?= e($p['payment_date']) ?></td><td><?= e($p['company_name']) ?></td><td><?= e($p['invoice_no']) ?></td><td><?= e($p['payment_mode']) ?></td><td class="text-end"><?= e(sales_format_money((float)$p['amount'])) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
