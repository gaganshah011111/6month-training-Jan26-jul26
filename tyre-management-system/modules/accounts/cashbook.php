<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_finance.php';
require_once __DIR__ . '/../../includes/erp_export.php';

$pdo = Database::connection();
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$rows = acc_finance_transactions($pdo, ['from' => $from, 'to' => $to]);
$rows = array_values(array_filter($rows, static fn($r) => (string)($r['payment_mode'] ?? '') === 'Cash'));
$incoming = 0.0;
$outgoing = 0.0;
foreach ($rows as $r) {
    $type = (string)$r['tx_type'];
    if ($type === 'Customer Payment') {
        $incoming += (float)$r['amount'];
    } else {
        $outgoing += (float)$r['amount'];
    }
}
?>
<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Cash &amp; Bank — Cashbook</h1>
            <p class="prod-page__sub">Cash in hand register with incoming and outgoing finance transactions.</p>
        </div>
    </header>

    <div class="sales-kpis accounts-kpis mb-3">
        <article class="sales-kpi"><span class="sales-kpi__label">Cash incoming</span><strong class="text-success"><?= e(sales_format_money($incoming)) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Cash outgoing</span><strong class="text-danger"><?= e(sales_format_money($outgoing)) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Cash in hand</span><strong><?= e(sales_format_money($incoming - $outgoing)) ?></strong></article>
    </div>

    <form method="get" class="sales-filter-bar mb-3">
        <input type="hidden" name="page" value="accounts/cashbook">
        <div class="sales-filter-bar__row">
            <div class="sales-filter-bar__field"><label>From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
            <div class="sales-filter-bar__field"><label>To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
            <div class="align-self-end"><button class="btn btn-sm btn-primary">Apply</button></div>
            <div class="ms-auto"><?= erp_export_toolbar('acc-cashbook-table', 'cashbook') ?></div>
        </div>
    </form>

    <section class="sales-card">
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0" id="acc-cashbook-table">
                <thead><tr><th>Transaction ID</th><th>Date</th><th>Type</th><th>Party</th><th>Reference</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $meta = acc_payment_meta((string)($r['tx_status'] ?? 'Pending')); ?>
                    <tr>
                        <td><?= e((string)$r['txid']) ?></td>
                        <td><?= e((string)$r['tx_date']) ?></td>
                        <td><?= e((string)$r['tx_type']) ?></td>
                        <td><?= e((string)$r['party']) ?></td>
                        <td><?= e((string)$r['reference_no']) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$r['amount'])) ?></td>
                        <td><span class="badge <?= e($meta['cls']) ?>"><?= e((string)$meta['label']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?><tr><td colspan="7" class="sales-empty">No cash transactions in selected period.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
