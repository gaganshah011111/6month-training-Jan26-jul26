<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_finance.php';
require_once __DIR__ . '/../../includes/erp_export.php';

$pdo = Database::connection();
$rows = acc_supplier_ledger_summary($pdo);
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$detail = $supplierId > 0 ? inv_supplier_recent_inward($pdo, $supplierId, 80) : [];
$nameMap = [];
foreach ($rows as $r) {
    $nameMap[(int)$r['id']] = (string)$r['name'];
}
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Supplier Ledger</h1>
            <p class="prod-page__sub">Auto-linked with purchase inward and supplier payments from Procurement &amp; Inventory.</p>
        </div>
    </header>

    <section class="sales-card mb-3">
        <div class="sales-card__head d-flex justify-content-between align-items-center">
            <h2 class="sales-card__title mb-0">Supplier payable summary</h2>
            <?= erp_export_toolbar('acc-supplier-ledger-table', 'supplier-ledger') ?>
        </div>
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0" id="acc-supplier-ledger-table">
                <thead><tr><th>Supplier</th><th class="text-end">Purchased amount</th><th class="text-end">Paid amount</th><th class="text-end">Pending payable</th><th>Last payment</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $meta = $r['status_meta'] ?? acc_payment_meta('Unpaid'); ?>
                    <tr>
                        <td><?= e((string)$r['name']) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$r['total_purchased'])) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$r['total_paid'])) ?></td>
                        <td class="text-end fw-semibold"><?= e(sales_format_money((float)$r['pending_balance'])) ?></td>
                        <td><?= e((string)($r['last_purchase_date'] ?? '—')) ?></td>
                        <td><span class="badge <?= e($meta['cls']) ?>"><?= e((string)$meta['label']) ?></span></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('accounts/supplier-ledger', ['supplier_id' => (int)$r['id']])) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?><tr><td colspan="7" class="sales-empty">No supplier ledger data.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($supplierId > 0): ?>
    <section class="sales-card">
        <div class="sales-card__head d-flex justify-content-between align-items-center">
            <h2 class="sales-card__title mb-0"><?= e($nameMap[$supplierId] ?? 'Supplier') ?> — detailed ledger</h2>
            <?= erp_export_toolbar('acc-supplier-detail-table', 'supplier-ledger-detail') ?>
        </div>
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0" id="acc-supplier-detail-table">
                <thead><tr><th>Date</th><th>Ref</th><th class="text-end">Debit (Purchase)</th><th class="text-end">Credit (Paid)</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                <?php
                $bal = 0.0;
                foreach ($detail as $d):
                    $debit = (float)($d['total_amount'] ?? 0);
                    $credit = (float)($d['paid_amount'] ?? 0);
                    $bal += $debit;
                    $bal -= $credit;
                ?>
                    <tr>
                        <td><?= e((string)$d['inward_date']) ?></td>
                        <td><?= e((string)($d['pinv_no'] ?? 'PINV')) ?></td>
                        <td class="text-end"><?= e($debit > 0 ? sales_format_money($debit) : '—') ?></td>
                        <td class="text-end"><?= e($credit > 0 ? sales_format_money($credit) : '—') ?></td>
                        <td class="text-end fw-semibold"><?= e(sales_format_money($bal)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($detail === []): ?><tr><td colspan="5" class="sales-empty">No purchase entries for this supplier.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
