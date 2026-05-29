<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_transactions.php';

$pdo = Database::connection();
acc_tx_history_handle_export($pdo);

$bundle = acc_tx_history_bundle($pdo, $_GET);
$filters = $bundle['filters'];
$rows = $bundle['rows'];
$kpis = $bundle['kpis'];
?>

<div class="accounts-page acc-tx-page module-shell">
    <header class="acc-tx__head">
        <div>
            <h1 class="acc-tx__title">Payment Transactions</h1>
            <p class="acc-tx__sub">Legacy payment log · <?= e($filters['from']) ?> to <?= e($filters['to']) ?></p>
        </div>
        <a href="<?= e(route_url('accounts/transactions-history')) ?>" class="btn btn-sm btn-outline-primary">Open Audit Log</a>
    </header>

    <section class="acc-tx__table-wrap">
        <div class="acc-tx__table-scroll">
            <table class="table table-sm acc-tx-table mb-0" id="acc-transactions-table">
                <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Party</th>
                    <th>Reference</th>
                    <th class="text-end">Amount</th>
                    <th>Mode</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $st = $r['status_meta']; ?>
                    <tr class="acc-tx-row <?= e((string)$r['row_accent']) ?>">
                        <td class="acc-tx-code"><?= e((string)$r['tx_code']) ?></td>
                        <td><?= e((string)$r['tx_date']) ?></td>
                        <td><?= e((string)$r['tx_type']) ?></td>
                        <td><?= e((string)$r['party']) ?></td>
                        <td><?= e((string)$r['reference_no']) ?></td>
                        <td class="text-end acc-tx-amt"><?= e((string)$r['amount_fmt']) ?></td>
                        <td><?= e((string)$r['payment_mode']) ?></td>
                        <td><span class="acc-tx-status <?= e((string)$st['cls']) ?>"><?= e((string)$st['label']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No transactions in selected period.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
