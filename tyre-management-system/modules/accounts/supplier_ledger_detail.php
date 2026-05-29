<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_ledger.php';
require_once __DIR__ . '/../../includes/inventory_purchase.php';

if (!has_role(['Accounts Manager', 'Super Admin', 'Admin', 'Sales Manager', 'Inventory Manager'])) {
    echo '<div class="alert alert-warning m-3">Access denied.</div>';
    return;
}

$pdo = Database::connection();
$supplierId = (int)($_GET['supplier_id'] ?? 0);
if ($supplierId < 1) {
    echo '<div class="alert alert-warning m-3">Invalid supplier. <a href="' . e(route_url('accounts/supplier-ledger')) . '">Back to Supplier Ledger</a></div>';
    return;
}

$_GET['ledger_type'] = 'supplier';
$_GET['export_scope'] = 'detail';
acc_ledger_handle_export($pdo, 'detail');

$data = acc_supplier_ledger_detail($pdo, $supplierId);
$supplier = $data['supplier'];
if (!$supplier) {
    echo '<div class="alert alert-warning m-3">Supplier not found. <a href="' . e(route_url('accounts/supplier-ledger')) . '">Back</a></div>';
    return;
}

$summary = $data['summary'];
$statusMeta = acc_payment_meta((string)($summary['status'] ?? 'Unpaid'));
$backUrl = route_url('accounts/supplier-ledger');
?>

<div class="accounts-page acc-ledger-page">
    <div class="mb-3">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e($backUrl) ?>"><i class="bi bi-arrow-left"></i> Supplier Ledger</a>
    </div>

    <header class="prod-page__head d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="prod-page__title h4 mb-1"><?= e((string)$supplier['name']) ?></h1>
            <p class="prod-page__sub mb-0 font-monospace"><?= e((string)($supplier['supplier_code'] ?? '')) ?></p>
        </div>
        <?= acc_ledger_export_toolbar('accounts/supplier-ledger-detail', 'supplier', 'detail', 'supplier-ledger-detail') ?>
    </header>

    <div class="sales-kpis mb-3">
        <article class="sales-kpi col"><span class="sales-kpi__label">Contact</span><strong class="small"><?= e((string)($supplier['contact_person'] ?? '—')) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">Phone</span><strong class="small"><?= e((string)($supplier['phone'] ?? '—')) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">GST</span><strong class="small"><?= e((string)($supplier['gst_number'] ?? '—')) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">Total Purchases</span><strong><?= e(sales_format_money((float)$summary['total_purchased'])) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">Total Paid</span><strong class="text-success"><?= e(sales_format_money((float)$summary['total_paid'])) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">Pending</span><strong class="text-danger"><?= e(sales_format_money((float)$summary['pending'])) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">Last Payment</span><strong class="small"><?= e((string)$summary['last_payment']) ?></strong></article>
        <article class="sales-kpi col"><span class="sales-kpi__label">Status</span><strong><span class="badge <?= e($statusMeta['cls']) ?>"><?= e($statusMeta['label']) ?></span></strong></article>
    </div>

    <ul class="nav nav-tabs acc-ledger-tabs mb-0" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPurchases" type="button">Purchase History</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPayments" type="button">Payment History</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLedger" type="button">Ledger Entries</button></li>
    </ul>

    <div class="tab-content sales-card acc-ledger-tab-panel">
        <div class="tab-pane fade show active" id="tabPurchases">
            <div class="sales-table-wrap">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Purchase Ref</th><th>Date</th><th>Material</th><th class="text-end">Amount</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($data['purchases'] as $p):
                        $pm = inv_purchase_payment_meta((string)($p['payment_status'] ?? 'Unpaid'));
                    ?>
                        <tr>
                            <td class="fw-semibold"><?= e((string)($p['pinv_no'] ?? 'PINV')) ?></td>
                            <td><?= e((string)($p['inward_date'] ?? '')) ?></td>
                            <td><?= e((string)($p['material_name'] ?? '—')) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)($p['total_amount'] ?? 0))) ?></td>
                            <td><span class="badge inv-pay--<?= e($pm['badge']) ?>"><?= e($pm['label']) ?></span></td>
                            <td><a class="btn btn-sm btn-link p-0" href="<?= e(route_url('accounts/payable-invoice', ['id' => (int)($p['id'] ?? 0)])) ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($data['purchases'] === []): ?><tr><td colspan="6" class="sales-empty">No purchases.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="tabPayments">
            <div class="sales-table-wrap">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Date</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th><th>Remarks</th><th>Purchase</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['payments'] as $p): ?>
                        <tr>
                            <td><?= e((string)($p['payment_date'] ?? '')) ?></td>
                            <td class="text-end fw-semibold text-success"><?= e(sales_format_money((float)($p['amount'] ?? 0))) ?></td>
                            <td><?= e((string)($p['payment_mode'] ?? '—')) ?></td>
                            <td><?= e((string)($p['payment_ref'] ?? $p['reference_no'] ?? '—')) ?></td>
                            <td class="small"><?= e((string)($p['notes'] ?? $p['remarks'] ?? '')) ?></td>
                            <td><?= e((string)($p['pinv_no'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($data['payments'] === []): ?><tr><td colspan="6" class="sales-empty">No payments recorded.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="tabLedger">
            <div class="sales-table-wrap">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Date</th><th>Reference</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
                    <tbody>
                    <?php foreach ($data['ledger'] as $r): ?>
                        <tr>
                            <td><?= e((string)$r['date']) ?></td>
                            <td><?= e((string)$r['ref']) ?></td>
                            <td class="text-end"><?= (float)$r['debit'] > 0 ? e(sales_format_money((float)$r['debit'])) : '—' ?></td>
                            <td class="text-end"><?= (float)$r['credit'] > 0 ? e(sales_format_money((float)$r['credit'])) : '—' ?></td>
                            <td class="text-end fw-semibold"><?= e(sales_format_money((float)$r['balance'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($data['ledger'] === []): ?><tr><td colspan="5" class="sales-empty">No ledger entries.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
