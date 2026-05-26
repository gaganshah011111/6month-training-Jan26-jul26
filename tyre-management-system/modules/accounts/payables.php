<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_finance.php';
require_once __DIR__ . '/../../includes/erp_export.php';

$pdo = Database::connection();
inv_purchase_ensure_schema($pdo);

$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$paymentStatus = (string)($_GET['payment_status'] ?? '');
$filters = ['from' => $from, 'to' => $to, 'supplier_id' => $supplierId, 'payment_status' => $paymentStatus];
$rows = inv_purchase_list($pdo, $filters);
$suppliers = inv_list_suppliers($pdo);

$totalPending = 0.0;
$totalOverdue = 0.0;
$monthPaid = 0.0;
$today = date('Y-m-d');
foreach ($rows as $r) {
    $pending = (float)($r['pending_amount'] ?? 0);
    $totalPending += $pending;
    $due = (string)($r['due_date'] ?? '');
    if ($due !== '' && $due < $today && $pending > inv_purchase_tolerance()) {
        $totalOverdue += $pending;
    }
}
$monthPaid = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
)->fetchColumn();
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Payables</h1>
            <p class="prod-page__sub">Auto-linked with Purchase Inward. Track pending supplier payments and post settlements.</p>
        </div>
    </header>

    <div class="sales-kpis accounts-kpis mb-3">
        <article class="sales-kpi"><span class="sales-kpi__label">Total pending</span><strong class="text-danger"><?= e(sales_format_money($totalPending)) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Overdue amount</span><strong class="text-danger"><?= e(sales_format_money($totalOverdue)) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">This month paid</span><strong class="text-success"><?= e(sales_format_money($monthPaid)) ?></strong></article>
    </div>

    <form method="get" class="sales-filter-bar mb-3">
        <input type="hidden" name="page" value="accounts/payables">
        <div class="sales-filter-bar__row">
            <div class="sales-filter-bar__field"><label>From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
            <div class="sales-filter-bar__field"><label>To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
            <div class="sales-filter-bar__field"><label>Supplier</label>
                <select name="supplier_id" class="form-select form-select-sm">
                    <option value="0">All</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>><?= e((string)$s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sales-filter-bar__field"><label>Status</label>
                <select name="payment_status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['Paid', 'Partial', 'Unpaid'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= $paymentStatus === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="align-self-end"><button class="btn btn-sm btn-primary">Apply</button></div>
            <div class="ms-auto"><?= erp_export_toolbar('acc-payable-table', 'payables') ?></div>
        </div>
    </form>

    <section class="sales-card">
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0" id="acc-payable-table">
                <thead><tr><th>PINV no</th><th>Supplier</th><th class="text-end">Purchase amount</th><th class="text-end">Paid amount</th><th class="text-end">Pending amount</th><th>Due date</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $pm = inv_purchase_payment_meta((string)($r['payment_status'] ?? 'Unpaid')); ?>
                    <tr>
                        <td><?= e((string)$r['pinv_no']) ?></td>
                        <td><?= e((string)($r['supplier_name'] ?? '—')) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$r['total_amount'])) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$r['paid_amount'])) ?></td>
                        <td class="text-end fw-semibold"><?= e(sales_format_money((float)$r['pending_amount'])) ?></td>
                        <td><?= e((string)($r['due_date'] ?? '—')) ?></td>
                        <td><span class="badge inv-pay--<?= e($pm['badge']) ?>"><?= e($pm['label']) ?></span></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-success" href="<?= e(route_url('inventory/purchase-history', ['view' => (int)$r['id'], 'pay' => 1])) ?>">Add payment</a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('inventory/purchase-history', ['view' => (int)$r['id']])) ?>">View</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(inv_purchase_print_url((int)$r['id'], true)) ?>" target="_blank" rel="noopener">PDF</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?><tr><td colspan="8" class="sales-empty">No payables for selected filters.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
