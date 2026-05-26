<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_finance.php';
require_once __DIR__ . '/../../includes/erp_export.php';

$pdo = Database::connection();
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$rows = acc_finance_transactions($pdo, ['from' => $from, 'to' => $to]);
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Transactions History</h1>
            <p class="prod-page__sub">Master finance log of customer payments, supplier payments, and expenses.</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('sales/payments')) ?>">Record customer payment</a>
            <a href="<?= e(route_url('inventory/purchase-history')) ?>">Record supplier payment</a>
        </nav>
    </header>

    <form method="get" class="sales-filter-bar mb-3">
        <input type="hidden" name="page" value="accounts/payments">
        <div class="sales-filter-bar__row">
            <div class="sales-filter-bar__field"><label>From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
            <div class="sales-filter-bar__field"><label>To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
            <div class="align-self-end"><button class="btn btn-sm btn-primary">Apply</button></div>
            <div class="ms-auto"><?= erp_export_toolbar('acc-transactions-table', 'transactions-history') ?></div>
        </div>
    </form>

    <section class="sales-card">
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0" id="acc-transactions-table">
                <thead><tr><th>Transaction ID</th><th>Date</th><th>Type</th><th>Party</th><th>Reference</th><th class="text-end">Amount</th><th>Mode</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $p): ?>
                    <?php $meta = acc_payment_meta((string)($p['tx_status'] ?? 'Pending')); ?>
                    <tr>
                        <td><?= e((string)$p['txid']) ?></td>
                        <td><?= e((string)$p['tx_date']) ?></td>
                        <td><?= e((string)$p['tx_type']) ?></td>
                        <td><?= e((string)$p['party']) ?></td>
                        <td><?= e((string)$p['reference_no']) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$p['amount'])) ?></td>
                        <td><?= e((string)$p['payment_mode']) ?></td>
                        <td><span class="badge <?= e($meta['cls']) ?>"><?= e((string)$meta['label']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?><tr><td colspan="8" class="sales-empty">No transactions in selected period.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
