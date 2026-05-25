<?php
declare(strict_types=1);
/** @var array<string, mixed> $financial */
$financial = $financial ?? [];
?>
<section class="so-financial-card">
    <header class="so-financial-card__head">
        <h2 class="so-financial-card__title"><i class="bi bi-currency-rupee me-1"></i> Financial summary</h2>
    </header>
    <div class="so-financial-card__grid">
        <article class="so-fin-kpi">
            <span class="so-fin-kpi__label">Customer orders</span>
            <strong><?= e((string)($financial['customer_total_orders'] ?? 0)) ?></strong>
        </article>
        <article class="so-fin-kpi">
            <span class="so-fin-kpi__label">Customer pending</span>
            <strong class="so-fin-kpi__warn"><?= e(sales_format_money((float)($financial['customer_pending'] ?? 0))) ?></strong>
        </article>
        <article class="so-fin-kpi">
            <span class="so-fin-kpi__label">Customer paid</span>
            <strong class="so-fin-kpi__ok"><?= e(sales_format_money((float)($financial['customer_paid'] ?? 0))) ?></strong>
        </article>
        <article class="so-fin-kpi">
            <span class="so-fin-kpi__label">Invoice value (order)</span>
            <strong><?= e(sales_format_money((float)($financial['order_invoice_total'] ?? 0))) ?></strong>
        </article>
        <article class="so-fin-kpi">
            <span class="so-fin-kpi__label">Dispatch completed</span>
            <strong><?= e((string)($financial['dispatch_pct'] ?? 0)) ?>%</strong>
        </article>
        <article class="so-fin-kpi">
            <span class="so-fin-kpi__label">Payment status</span>
            <strong><?= e((string)($financial['payment_status'] ?? '—')) ?></strong>
        </article>
    </div>
    <?php if ((float)($financial['order_invoice_pending'] ?? 0) > 0.01): ?>
        <p class="so-financial-card__note mb-0">Order balance pending: <strong><?= e(sales_format_money((float)$financial['order_invoice_pending'])) ?></strong></p>
    <?php endif; ?>
</section>
