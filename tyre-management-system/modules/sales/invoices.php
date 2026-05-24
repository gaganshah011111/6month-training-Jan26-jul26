<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();
$filters = [
    'customer_id' => (int)($_GET['customer_id'] ?? 0) ?: null,
    'payment_status' => (string)($_GET['payment_status'] ?? ''),
    'from' => trim((string)($_GET['from'] ?? '')),
    'to' => trim((string)($_GET['to'] ?? '')),
];
$loadError = false;
$rows = [];
$customers = [];
try {
    $rows = sales_list_invoices($pdo, $filters);
    $customers = sales_list_customers($pdo, []);
} catch (Throwable $e) {
    sales_log_exception($e, 'sales_invoices');
    $loadError = true;
}
?>

<div class="sales-page">
    <header class="prod-page__head"><div><h1 class="prod-page__title">Invoices</h1><p class="prod-page__sub">Auto-generated from dispatch; track payment status.</p></div></header>
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if ($loadError): ?><?= sales_error_alert('Unable to load invoices.') ?><?php endif; ?>
    <form method="get" class="sales-filter-bar">
        <input type="hidden" name="page" value="sales/invoices">
        <div class="sales-filter-bar__grid">
            <div class="sales-filter-bar__field"><label>Customer</label><select class="form-select form-select-sm" name="customer_id"><option value="0">All</option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $filters['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option><?php endforeach; ?></select></div>
            <div class="sales-filter-bar__field"><label>Payment status</label><select class="form-select form-select-sm" name="payment_status"><option value="">All</option><?php foreach (SALES_PAYMENT_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $filters['payment_status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
            <div class="sales-filter-bar__field"><label>From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($filters['from']) ?>"></div>
            <div class="sales-filter-bar__field"><label>To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($filters['to']) ?>"></div>
        </div>
        <div class="sales-filter-bar__actions"><button class="btn btn-primary btn-sm" type="submit">Apply</button></div>
    </form>
    <section class="sales-card">
        <div class="sales-table-wrap">
            <table class="table table-sm mb-0">
                <thead><tr><th>Invoice</th><th>Customer</th><th>SO</th><th>Date</th><th>Due</th><th class="text-end">Total</th><th class="text-end">Paid</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $inv): ?>
                    <?php $sb = sales_status_badge((string)$inv['payment_status']); ?>
                    <tr>
                        <td><strong><?= e($inv['invoice_no']) ?></strong></td>
                        <td><?= e($inv['company_name']) ?></td>
                        <td><?= e((string)($inv['so_number'] ?? '—')) ?></td>
                        <td><?= e($inv['invoice_date']) ?></td>
                        <td><?= e((string)($inv['due_date'] ?? '—')) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$inv['total_amount'])) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$inv['amount_paid'])) ?></td>
                        <td><span class="<?= e($sb['class']) ?>"><?= e($sb['label']) ?></span></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('sales/invoice', ['id' => (int)$inv['id']])) ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
