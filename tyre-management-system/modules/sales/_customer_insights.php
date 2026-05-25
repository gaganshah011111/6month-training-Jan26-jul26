<?php
declare(strict_types=1);
/** @var array<string, mixed> $insights */
$insights = $insights ?? [];
if ($insights === []) {
    return;
}
?>
<aside class="so-insights-card">
    <h3 class="so-insights-card__title"><i class="bi bi-person-lines-fill me-1"></i> Customer insights</h3>
    <dl class="so-insights-dl">
        <dt>Last order</dt><dd><?= e((string)($insights['last_order'] ?? '—')) ?></dd>
        <dt>Outstanding</dt><dd class="so-insights-dl__warn"><?= e(sales_format_money((float)($insights['outstanding'] ?? 0))) ?></dd>
        <dt>Credit limit</dt><dd><?= e(sales_format_money((float)($insights['credit_limit'] ?? 0))) ?></dd>
        <dt>Total business</dt><dd><?= e(sales_format_money((float)($insights['total_business'] ?? 0))) ?></dd>
        <dt>Avg payment days</dt><dd><?= e((string)($insights['avg_payment_days'] ?? '—')) ?> days</dd>
        <dt>Pending invoices</dt><dd><?= e((string)($insights['pending_invoices'] ?? 0)) ?></dd>
        <dt>Total orders</dt><dd><?= e((string)($insights['total_orders'] ?? 0)) ?></dd>
    </dl>
    <?php if (!empty($insights['customer_id'])): ?>
    <a class="btn btn-sm btn-outline-secondary w-100 mt-2" href="<?= e(route_url('sales/customer', ['id' => (int)$insights['customer_id']])) ?>">View customer</a>
    <?php endif; ?>
</aside>
