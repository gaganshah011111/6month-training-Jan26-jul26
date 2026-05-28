<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_service.php';
require_once __DIR__ . '/../../includes/inventory_purchase.php';

$pdo = Database::connection();
inv_purchase_ensure_schema($pdo);
$id = (int)($_GET['id'] ?? 0);
$row = inv_purchase_get($pdo, $id);
if (!$row) {
    echo '<div class="alert alert-warning">Purchase invoice not found.</div>';
    return;
}
$payments = inv_purchase_list_payments($pdo, $id);
$pm = inv_purchase_payment_meta((string)($row['payment_status'] ?? 'Unpaid'));
?>
<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Supplier Invoice</h1>
            <p class="prod-page__sub"><?= e((string)$row['pinv_no']) ?> · <?= e((string)($row['supplier_name'] ?? '—')) ?></p>
        </div>
        <nav class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('accounts/payables')) ?>">Back</a>
            <a class="btn btn-sm btn-primary" href="<?= e(inv_purchase_print_url((int)$row['id'], true)) ?>" target="_blank" rel="noopener">PDF</a>
        </nav>
    </header>

    <div class="sales-kpis mb-3">
        <article class="sales-kpi"><span class="sales-kpi__label">Supplier</span><div class="sales-kpi__value"><?= e((string)($row['supplier_name'] ?? '—')) ?></div></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Amount</span><div class="sales-kpi__value"><?= e(sales_format_money((float)$row['total_amount'])) ?></div></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Paid</span><div class="sales-kpi__value text-success"><?= e(sales_format_money((float)$row['paid_amount'])) ?></div></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Remaining</span><div class="sales-kpi__value text-danger"><?= e(sales_format_money((float)$row['pending_amount'])) ?></div></article>
    </div>

    <section class="sales-card">
        <div class="sales-card__head"><h2 class="sales-card__title mb-0">Payment history</h2></div>
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= e((string)$p['payment_date']) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$p['amount'])) ?></td>
                        <td><?= e((string)($p['payment_mode'] ?? '—')) ?></td>
                        <td><?= e((string)($p['payment_ref'] ?? '—')) ?></td>
                        <td><span class="badge inv-pay--<?= e($pm['badge']) ?>"><?= e($pm['label']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($payments === []): ?><tr><td colspan="5" class="sales-empty">No payments yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
