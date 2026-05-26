<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_service.php';
require_once __DIR__ . '/../../includes/inv_ui.php';

if (!has_role(['Inventory Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$d = inv_dashboard($pdo);
$p = $d['purchase'] ?? [];
$alertCount = (int)($d['low_count'] ?? 0) + (int)($d['out_count'] ?? 0);
$alertRows = array_merge($d['out_rows'] ?? [], $d['low_rows'] ?? []);
$outstandingRows = $p['supplier_outstanding'] ?? [];
$totalPayable = 0.0;
foreach ($outstandingRows as $row) {
    $totalPayable += (float)($row['pending_balance'] ?? 0);
}
$highestDue = $outstandingRows[0] ?? null;
$overdueSuppliers = 0;
try {
    $overdueSuppliers = (int)$pdo->query(
        "SELECT COUNT(DISTINCT i.supplier_id)
         FROM stock_inward i
         WHERE i.supplier_id IS NOT NULL
           AND i.due_date IS NOT NULL
           AND i.due_date <> ''
           AND i.due_date < CURDATE()
           AND GREATEST(i.total_amount - i.paid_amount, 0) > 0.02"
    )->fetchColumn();
} catch (Throwable $e) {
    $overdueSuppliers = 0;
}
?>

<div class="inv-page inv-dash">
<?php inv_page_header(
    'Dashboard',
    'Stock, purchasing, and supplier payables in one place.'
); ?>

    <section class="inv-dash__section">
        <div class="inv-summary-4">
            <article class="inv-summary-card">
                <span class="inv-summary-card__label">Total materials</span>
                <span class="inv-summary-card__value"><?= e((string)(int)($d['total_materials'] ?? 0)) ?></span>
            </article>
            <article class="inv-summary-card inv-summary-card--primary">
                <span class="inv-summary-card__label">Total stock value</span>
                <span class="inv-summary-card__value">₹<?= e(number_format((float)($p['stock_value'] ?? 0), 0)) ?></span>
            </article>
            <article class="inv-summary-card inv-summary-card--warn">
                <span class="inv-summary-card__label">Pending supplier payments</span>
                <span class="inv-summary-card__value">₹<?= e(number_format((float)($p['pending_payables'] ?? 0), 0)) ?></span>
            </article>
            <article class="inv-summary-card inv-summary-card--danger">
                <span class="inv-summary-card__label">Low stock alerts</span>
                <span class="inv-summary-card__value"><?= e((string)$alertCount) ?></span>
            </article>
        </div>
    </section>

    <section class="inv-dash__section">
        <h2 class="inv-section-title">Overview</h2>
        <div class="inv-overview-grid">
            <div class="inv-overview-main">
                <?php if ($alertRows !== []): ?>
                <section class="inv-card">
                    <div class="inv-card__head">
                        <h3 class="inv-card__title mb-0">Low stock alerts</h3>
                    </div>
                    <div class="inv-card__body">
                        <div class="inv-alert-chips inv-alert-chips--dashboard">
                            <?php foreach (array_slice($alertRows, 0, 8) as $r): ?>
                                <?php $meta = inv_stock_status_meta((float)$r['stock_qty'], (float)$r['reorder_level']); ?>
                                <a class="inv-alert-chip inv-alert-chip--<?= e($meta['badge']) ?>" href="<?= e(route_url('inventory/add-stock')) ?>">
                                    <strong>[<?= e(strtoupper($meta['label'])) ?>] <?= e((string)$r['material_name']) ?></strong>
                                    <span><?= e(inv_format_qty((float)$r['stock_qty'], (string)$r['unit'])) ?> left</span>
                                    <span><?= (float)$r['stock_qty'] > 0 ? 'Reorder soon' : 'Out of stock' ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <section class="inv-card">
                    <div class="inv-card__head d-flex justify-content-between align-items-start">
                        <div>
                            <h3 class="inv-card__title mb-0">Recent purchases</h3>
                            <span class="inv-card__note"><?= e(inv_recent_purchases_note(INV_RECENT_PURCHASES_DASHBOARD)) ?></span>
                        </div>
                        <a class="inv-link-sm" href="<?= e(route_url('inventory/purchase-history')) ?>">View all</a>
                    </div>
                    <?php inv_table_scroll_open('260px'); ?>
                    <table class="table table-sm inv-table inv-table--compact mb-0">
                        <thead><tr><th>PINV</th><th>Supplier</th><th class="text-end">Amount</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach (($p['recent_purchases'] ?? []) as $r): ?>
                            <?php $pm = inv_purchase_payment_meta((string)($r['payment_status'] ?? 'Unpaid')); ?>
                            <tr>
                                <td><a href="<?= e(inv_purchase_print_url((int)$r['id'], true)) ?>" target="_blank" rel="noopener"><?= e((string)$r['pinv_no']) ?></a></td>
                                <td><?= e((string)($r['supplier_name'] ?? '—')) ?></td>
                                <td class="text-end">₹<?= e(number_format((float)$r['total_amount'], 0)) ?></td>
                                <td><?= e((string)$r['inward_date']) ?></td>
                                <td><span class="badge inv-pay--<?= e($pm['badge']) ?>"><?= e($pm['label']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($p['recent_purchases'] ?? []) === []): ?>
                            <tr><td colspan="5" class="text-center inv-empty">No purchases yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <?php inv_table_scroll_close(); ?>
                </section>

            </div>

            <div class="inv-overview-side">
                <section class="inv-card">
                    <div class="inv-card__head d-flex justify-content-between align-items-center">
                        <h3 class="inv-card__title mb-0">Supplier outstanding summary</h3>
                        <a class="inv-link-sm" href="<?= e(route_url('inventory/supplier-ledger')) ?>">Ledger</a>
                    </div>
                    <div class="inv-card__body">
                        <div class="inv-summary-mini">
                            <article class="inv-summary-mini__card">
                                <span class="inv-summary-mini__label">Total payable</span>
                                <strong class="inv-summary-mini__value">₹<?= e(number_format($totalPayable, 0)) ?></strong>
                            </article>
                            <article class="inv-summary-mini__card">
                                <span class="inv-summary-mini__label">Overdue suppliers</span>
                                <strong class="inv-summary-mini__value"><?= e((string)$overdueSuppliers) ?></strong>
                            </article>
                            <article class="inv-summary-mini__card">
                                <span class="inv-summary-mini__label">Highest supplier due</span>
                                <strong class="inv-summary-mini__value"><?= $highestDue ? '₹' . e(number_format((float)$highestDue['pending_balance'], 0)) : '₹0' ?></strong>
                                <span class="inv-summary-mini__sub"><?= e((string)($highestDue['name'] ?? '—')) ?></span>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="inv-card">
                    <div class="inv-card__head"><h3 class="inv-card__title mb-0">Quick actions</h3></div>
                    <div class="inv-card__body">
                        <?= inv_quick_actions([
                            ['label' => '+ Purchase Inward', 'url' => route_url('inventory/add-stock'), 'icon' => 'bi-plus-circle', 'primary' => true],
                            ['label' => '+ Add Material', 'url' => route_url('inventory/materials'), 'icon' => 'bi-box-seam'],
                            ['label' => 'Use Stock', 'url' => route_url('inventory/use-stock'), 'icon' => 'bi-dash-circle'],
                            ['label' => 'Reports', 'url' => route_url('reports/inventory'), 'icon' => 'bi-graph-up'],
                        ]) ?>
                    </div>
                </section>

                <section class="inv-card">
                    <div class="inv-card__head"><h3 class="inv-card__title mb-0">Material movement (14 days)</h3></div>
                    <div class="inv-card__body inv-trend-card">
                        <?php $trend = $p['consumption_trend'] ?? []; ?>
                        <?php if ($trend === []): ?>
                            <p class="inv-empty mb-0">No usage in the last two weeks.</p>
                        <?php else: ?>
                            <div class="inv-trend-bars inv-trend-bars--dashboard">
                                <?php
                                $max = max(1.0, ...array_map(static fn($t) => (float)$t['qty'], $trend));
                                foreach ($trend as $t):
                                    $h = max(4, (int)round(((float)$t['qty'] / $max) * 140));
                                ?>
                                    <div class="inv-trend-bars__item" title="<?= e((string)$t['d']) ?>">
                                        <div class="inv-trend-bars__bar" style="height:<?= $h ?>px"></div>
                                        <span class="inv-trend-bars__lbl"><?= e(date('d', strtotime((string)$t['d']))) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </section>
</div>
