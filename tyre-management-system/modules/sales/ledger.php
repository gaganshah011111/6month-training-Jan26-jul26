<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_auth.php';
require_once __DIR__ . '/../../includes/sales_service.php';

require_sales_manager();

$pdo = Database::connection();
$customerId = (int)($_GET['customer_id'] ?? 0);
if ($customerId > 0) {
    header('Location: ' . route_url('sales/customer', ['id' => $customerId]) . '#ledger');
    exit;
}
$customers = sales_list_customers($pdo, ['status' => 'Active']);
?>

<div class="sales-page">
    <header class="prod-page__head"><div><h1 class="prod-page__title">Customer Ledger</h1><p class="prod-page__sub">Select a customer to view invoices, payments, orders, and outstanding balance.</p></div></header>
    <?php require __DIR__ . '/_nav.php'; ?>
    <section class="sales-card">
        <div class="sales-card__body">
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="page" value="sales/ledger">
                <div class="col-md-8"><label class="form-label">Customer</label><select class="form-select erp-select-search" name="customer_id" required><option value="">Search customer…</option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['company_name']) ?> (<?= e($c['customer_code']) ?>)</option><?php endforeach; ?></select></div>
                <div class="col-md-4"><button class="btn btn-primary w-100" type="submit">Open ledger</button></div>
            </form>
        </div>
    </section>
</div>
