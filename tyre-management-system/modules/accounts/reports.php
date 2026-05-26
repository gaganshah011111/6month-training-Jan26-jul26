<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_finance.php';
require_once __DIR__ . '/../../includes/erp_export.php';

$pdo = Database::connection();
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$report = (string)($_GET['report'] ?? 'ledger');
$reports = [
    'profit' => ['title' => 'Profit summary', 'hint' => 'Revenue vs expense snapshot'],
    'receivable' => ['title' => 'Receivable report', 'hint' => 'Customer pending and overdue balances'],
    'payable' => ['title' => 'Payable report', 'hint' => 'Supplier pending payable balances'],
    'expense' => ['title' => 'Expense report', 'hint' => 'Expense entries by category and mode'],
    'cashflow' => ['title' => 'Cash flow report', 'hint' => 'Incoming vs outgoing transactions'],
];
if (!isset($reports[$report])) {
    $report = 'profit';
}
$meta = $reports[$report];
$dash = acc_dashboard_data($pdo);
$recv = acc_customer_ledger_summary($pdo);
$payable = acc_supplier_ledger_summary($pdo);
$expenses = acc_list_expenses($pdo, ['from' => $from, 'to' => $to]);
$txRows = acc_finance_transactions($pdo, ['from' => $from, 'to' => $to]);
?>

<div class="accounts-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Financial reports</h1>
            <p class="prod-page__sub"><?= e($meta['hint']) ?> · <?= e($from) ?> to <?= e($to) ?></p>
        </div>
    </header>

    <form method="get" class="sales-filter-bar mb-3">
        <input type="hidden" name="page" value="accounts/reports">
        <input type="hidden" name="report" value="<?= e($report) ?>">
        <div class="sales-filter-bar__row">
            <div class="sales-filter-bar__field"><label>From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
            <div class="sales-filter-bar__field"><label>To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
            <div class="align-self-end"><button class="btn btn-sm btn-primary">Apply</button></div>
        </div>
    </form>

    <div class="sales-filter-bar mb-3 d-flex flex-wrap gap-2">
        <?php foreach ($reports as $key => $r): ?>
            <a class="btn btn-sm <?= $report === $key ? 'btn-primary' : 'btn-outline-secondary' ?>"
               href="<?= e(route_url('accounts/reports', ['report' => $key])) ?>"><?= e($r['title']) ?></a>
        <?php endforeach; ?>
    </div>

    <section class="sales-card">
        <div class="sales-card__head d-flex justify-content-between align-items-center">
            <h2 class="sales-card__title mb-0"><?= e($meta['title']) ?></h2>
            <?= erp_export_toolbar('accounts-report-table', 'accounts-' . $report) ?>
        </div>
        <div class="erp-table-panel">
            <table class="table table-sm erp-data-table" id="accounts-report-table">
                <?php if ($report === 'profit'): ?>
                    <thead><tr><th>Metric</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <tr><td>Monthly revenue</td><td class="text-end"><?= e(sales_format_money((float)$dash['monthly_revenue'])) ?></td></tr>
                        <tr><td>Monthly expenses</td><td class="text-end"><?= e(sales_format_money((float)$dash['monthly_expenses'])) ?></td></tr>
                        <tr><td>Estimated profit</td><td class="text-end"><?= e(sales_format_money((float)$dash['estimated_profit'])) ?></td></tr>
                    </tbody>
                <?php elseif ($report === 'receivable'): ?>
                    <thead><tr><th>Customer</th><th class="text-end">Invoiced</th><th class="text-end">Paid</th><th class="text-end">Pending</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recv as $r): ?>
                        <?php $metaSt = $r['status_meta'] ?? acc_payment_meta('Unpaid'); ?>
                        <tr>
                            <td><?= e((string)$r['company_name']) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$r['total_invoiced'])) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$r['total_paid'])) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$r['pending'])) ?></td>
                            <td><span class="badge <?= e($metaSt['cls']) ?>"><?= e((string)$metaSt['label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php elseif ($report === 'payable'): ?>
                    <thead><tr><th>Supplier</th><th class="text-end">Purchased</th><th class="text-end">Paid</th><th class="text-end">Pending</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($payable as $r): ?>
                        <?php $metaSt = $r['status_meta'] ?? acc_payment_meta('Unpaid'); ?>
                        <tr>
                            <td><?= e((string)$r['name']) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$r['total_purchased'])) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$r['total_paid'])) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$r['pending_balance'])) ?></td>
                            <td><span class="badge <?= e($metaSt['cls']) ?>"><?= e((string)$metaSt['label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php elseif ($report === 'expense'): ?>
                    <thead><tr><th>Date</th><th>Category</th><th class="text-end">Amount</th><th>Mode</th><th>Remarks</th></tr></thead>
                    <tbody>
                    <?php foreach ($expenses as $e): ?>
                        <tr>
                            <td><?= e((string)$e['expense_date']) ?></td>
                            <td><?= e((string)$e['category']) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$e['amount'])) ?></td>
                            <td><?= e((string)$e['payment_mode']) ?></td>
                            <td><?= e((string)($e['remarks'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php else: ?>
                    <thead><tr><th>ID</th><th>Date</th><th>Type</th><th>Party</th><th class="text-end">Amount</th><th>Mode</th></tr></thead>
                    <tbody>
                    <?php foreach ($txRows as $t): ?>
                        <tr>
                            <td><?= e((string)$t['txid']) ?></td>
                            <td><?= e((string)$t['tx_date']) ?></td>
                            <td><?= e((string)$t['tx_type']) ?></td>
                            <td><?= e((string)$t['party']) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$t['amount'])) ?></td>
                            <td><?= e((string)$t['payment_mode']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
        <p class="small text-muted p-3 mb-0">All reports support export and print from current filtered view.</p>
    </section>
</div>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
