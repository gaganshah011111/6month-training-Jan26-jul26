<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sales_service.php';
require_once __DIR__ . '/../../includes/erp_export.php';

$pdo = Database::connection();
$filter = (string)($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'overdue', 'week', 'month'], true)) {
    $filter = 'all';
}
$rows = sales_receivables_list($pdo, $filter);
$totalPending = 0.0;
$totalOverdue = 0.0;
foreach ($rows as $r) {
    $bal = (float)($r['balance'] ?? 0);
    $totalPending += $bal;
    $due = (string)($r['due_date'] ?? '');
    if ($due !== '' && $due < date('Y-m-d')) {
        $totalOverdue += $bal;
    }
}
$monthCollection = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM sales_payments WHERE payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
)->fetchColumn();
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Receivables</h1>
            <p class="prod-page__sub">Track unpaid customer invoices with due dates, collection status, and quick payment actions.</p>
        </div>
    </header>

    <div class="sales-kpis accounts-kpis mb-3">
        <article class="sales-kpi"><span class="sales-kpi__label">Total pending</span><strong class="text-warning"><?= e(sales_format_money($totalPending)) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">Overdue amount</span><strong class="text-danger"><?= e(sales_format_money($totalOverdue)) ?></strong></article>
        <article class="sales-kpi"><span class="sales-kpi__label">This month collection</span><strong class="text-success"><?= e(sales_format_money($monthCollection)) ?></strong></article>
    </div>

    <div class="sales-filter-bar mb-3 d-flex flex-wrap gap-2">
        <?php foreach (['all' => 'All pending', 'overdue' => 'Overdue', 'week' => 'Due this week', 'month' => 'Due this month'] as $k => $label): ?>
            <a class="btn btn-sm <?= $filter === $k ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e(route_url('accounts/receivables', ['filter' => $k])) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <span class="ms-auto"><?= erp_export_toolbar('acc-recv-table', 'receivables') ?></span>
    </div>

    <section class="sales-card">
        <div class="sales-table-wrap sales-table-scroll">
            <table class="table table-sm mb-0" id="acc-recv-table">
                <thead>
                    <tr><th>Invoice no</th><th>Customer</th><th class="text-end">Invoice amount</th><th class="text-end">Paid</th><th class="text-end">Remaining</th><th>Due date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $inv): ?>
                    <?php
                    $due = (string)($inv['due_date'] ?? '');
                    $paid = (float)$inv['amount_paid'] >= (float)$inv['total_amount'] - 0.01;
                    $rowClass = 'recv-row';
                    if ($paid) {
                        $rowClass .= ' recv-row--paid';
                        $statusLabel = 'Paid';
                    } elseif ($due !== '' && $due < date('Y-m-d')) {
                        $rowClass .= ' recv-row--overdue';
                        $statusLabel = 'Overdue';
                    } elseif ($due !== '' && $due <= date('Y-m-d', strtotime('+7 days'))) {
                        $rowClass .= ' recv-row--soon';
                        $statusLabel = 'Due soon';
                    } else {
                        $statusLabel = 'Pending';
                    }
                    ?>
                    <tr class="<?= e($rowClass) ?>">
                        <td><?= e($inv['invoice_no']) ?></td>
                        <td><?= e($inv['company_name']) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$inv['total_amount'])) ?></td>
                        <td class="text-end"><?= e(sales_format_money((float)$inv['amount_paid'])) ?></td>
                        <td class="text-end fw-semibold"><?= e(sales_format_money((float)$inv['balance'])) ?></td>
                        <td><?= e($due ?: '—') ?></td>
                        <td><span class="recv-pill"><?= e($statusLabel) ?></span></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('sales/invoice', ['id' => (int)$inv['id']])) ?>">View</a>
                            <a class="btn btn-sm btn-outline-success" href="<?= e(route_url('sales/payments', ['invoice_id' => (int)$inv['id'], 'customer_id' => (int)$inv['customer_id']])) ?>">Record</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(route_url('sales/invoice-print', ['id' => (int)$inv['id']])) ?>" target="_blank" rel="noopener">PDF</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="8" class="sales-empty">No receivables for this filter.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
