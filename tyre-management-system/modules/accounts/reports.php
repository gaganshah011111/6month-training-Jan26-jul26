<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/accounts_reports.php';

$pdo = Database::connection();
acc_reports_handle_export($pdo);

$filters = acc_reports_parse_filters($_GET);
$bundle = acc_reports_bundle($pdo, $filters);
$report = $filters['report'];
$meta = ACC_REPORT_TYPES[$report];
$kpis = $bundle['kpis'];
$pnl = $bundle['pnl'];
$charts = $bundle['charts'];
$recv = $bundle['receivable'];
$pay = $bundle['payable'];
$insights = $bundle['insights'];
$cash = $charts['cash_flow'];
$legacy = $bundle['legacy'];
$showDashboard = $report === 'overview';

$filterQs = array_filter([
    'page' => 'accounts/reports',
    'from' => $filters['from'],
    'to' => $filters['to'],
    'department' => $filters['department'] !== '' ? $filters['department'] : null,
    'report' => $report !== 'overview' ? $report : null,
]);
$exportBase = 'index.php?' . http_build_query($filterQs);

$chartPayload = [
    'monthly' => $charts['monthly'],
    'expense_breakdown' => $charts['expense_breakdown'],
    'receivable_vs_payable' => $charts['receivable_vs_payable'],
    'cash_flow' => [
        'labels' => $cash['trend_labels'] ?? [],
        'values' => $cash['trend_values'] ?? [],
    ],
];
?>

<div class="accounts-page acc-reports-page module-shell" id="accReportsRoot">
    <header class="acc-reports__head">
        <div>
            <h1 class="acc-reports__title">Finance Analytics &amp; Reporting Center</h1>
            <p class="acc-reports__sub"><?= e($meta['hint']) ?> · <?= e($filters['from']) ?> to <?= e($filters['to']) ?></p>
        </div>
    </header>

    <form method="get" class="acc-reports__filters" id="accReportsFilterForm">
        <input type="hidden" name="page" value="accounts/reports">
        <div class="acc-reports__field">
            <label for="acc_rpt_from">From Date</label>
            <input type="date" name="from" id="acc_rpt_from" class="form-control form-control-sm" value="<?= e($filters['from']) ?>" required>
        </div>
        <div class="acc-reports__field">
            <label for="acc_rpt_to">To Date</label>
            <input type="date" name="to" id="acc_rpt_to" class="form-control form-control-sm" value="<?= e($filters['to']) ?>" required>
        </div>
        <div class="acc-reports__field">
            <label for="acc_rpt_dept">Department</label>
            <select name="department" id="acc_rpt_dept" class="form-select form-select-sm">
                <?php foreach (ACC_REPORT_DEPARTMENTS as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['department'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="acc-reports__field">
            <label for="acc_rpt_type">Report Type</label>
            <select name="report" id="acc_rpt_type" class="form-select form-select-sm">
                <?php foreach (ACC_REPORT_TYPES as $key => $r): ?>
                    <option value="<?= e($key) ?>" <?= $report === $key ? 'selected' : '' ?>><?= e($r['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="acc-reports__actions">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Apply</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="accReportPrint"><i class="bi bi-printer"></i> Print</button>
            <a href="<?= e($exportBase . '&export=pdf') ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
            <a href="<?= e($exportBase . '&export=csv') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
            <a href="<?= e($exportBase . '&export=csv') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-csv"></i> CSV</a>
        </div>
    </form>

    <nav class="acc-reports__tabs" aria-label="Report views">
        <?php foreach (ACC_REPORT_TYPES as $key => $r): ?>
            <a class="acc-reports__tab <?= $report === $key ? 'is-active' : '' ?>"
               href="<?= e(route_url('accounts/reports', array_filter([
                   'from' => $filters['from'],
                   'to' => $filters['to'],
                   'department' => $filters['department'] ?: null,
                   'report' => $key !== 'overview' ? $key : null,
               ]))) ?>"><?= e($r['title']) ?></a>
        <?php endforeach; ?>
    </nav>

    <section class="acc-reports__kpis" aria-label="Financial KPIs">
        <div class="acc-kpi acc-kpi--revenue">
            <span class="acc-kpi__label">Total Revenue</span>
            <strong class="acc-kpi__value"><?= e(sales_format_money((float)$kpis['revenue'])) ?></strong>
        </div>
        <div class="acc-kpi acc-kpi--expense">
            <span class="acc-kpi__label">Total Expenses</span>
            <strong class="acc-kpi__value"><?= e(sales_format_money((float)$kpis['expenses'])) ?></strong>
        </div>
        <div class="acc-kpi acc-kpi--profit">
            <span class="acc-kpi__label">Net Profit</span>
            <strong class="acc-kpi__value"><?= e(sales_format_money((float)$kpis['profit'])) ?></strong>
        </div>
        <div class="acc-kpi acc-kpi--recv">
            <span class="acc-kpi__label">Total Receivables</span>
            <strong class="acc-kpi__value"><?= e(sales_format_money((float)$kpis['receivables'])) ?></strong>
        </div>
        <div class="acc-kpi acc-kpi--pay">
            <span class="acc-kpi__label">Total Payables</span>
            <strong class="acc-kpi__value"><?= e(sales_format_money((float)$kpis['payables'])) ?></strong>
        </div>
        <div class="acc-kpi acc-kpi--cash">
            <span class="acc-kpi__label">Available Cash</span>
            <strong class="acc-kpi__value"><?= e(sales_format_money((float)$kpis['cash'])) ?></strong>
        </div>
    </section>

    <?php if ($showDashboard || $report === 'profit'): ?>
    <div class="acc-reports__grid acc-reports__grid--2">
        <section class="acc-rpt-card">
            <h2 class="acc-rpt-card__title">Profit &amp; Loss Summary</h2>
            <div class="acc-pnl">
                <div class="acc-pnl__row"><span>Sales Revenue</span><strong><?= e(sales_format_money((float)$pnl['sales_revenue'])) ?></strong></div>
                <div class="acc-pnl__row acc-pnl__row--deduct"><span>Salary Expense</span><strong><?= e(sales_format_money((float)$pnl['salary_expense'])) ?></strong></div>
                <div class="acc-pnl__row acc-pnl__row--deduct"><span>Operational Expenses</span><strong><?= e(sales_format_money((float)$pnl['operational_expenses'])) ?></strong></div>
                <div class="acc-pnl__row acc-pnl__row--deduct"><span>Supplier Costs</span><strong><?= e(sales_format_money((float)$pnl['supplier_costs'])) ?></strong></div>
                <div class="acc-pnl__row acc-pnl__row--deduct"><span>Other Expenses</span><strong><?= e(sales_format_money((float)$pnl['other_expenses'])) ?></strong></div>
                <div class="acc-pnl__row acc-pnl__row--total"><span>Net Profit</span><strong><?= e(sales_format_money((float)$pnl['net_profit'])) ?></strong></div>
            </div>
            <p class="acc-rpt-card__hint">Profit = Revenue − Expenses</p>
        </section>

        <section class="acc-rpt-card">
            <h2 class="acc-rpt-card__title">Financial Insights</h2>
            <ul class="acc-insights">
                <?php foreach ($insights as $line): ?>
                    <li><i class="bi bi-lightbulb"></i> <?= e($line) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
    <?php endif; ?>

    <?php if ($showDashboard || in_array($report, ['profit', 'expense', 'cashflow'], true)): ?>
    <section class="acc-reports__charts" aria-label="Financial charts">
        <?php if ($showDashboard || $report === 'profit'): ?>
        <div class="acc-chart-card">
            <h3 class="acc-chart-card__title">Revenue vs Expense</h3>
            <div class="acc-chart-card__body"><canvas id="accChartRevExp" height="220"></canvas></div>
        </div>
        <div class="acc-chart-card">
            <h3 class="acc-chart-card__title">Monthly Profit Trend</h3>
            <div class="acc-chart-card__body"><canvas id="accChartProfit" height="220"></canvas></div>
        </div>
        <?php endif; ?>
        <?php if ($showDashboard || $report === 'expense'): ?>
        <div class="acc-chart-card">
            <h3 class="acc-chart-card__title">Expense Category Breakdown</h3>
            <div class="acc-chart-card__body"><canvas id="accChartExpense" height="220"></canvas></div>
        </div>
        <?php endif; ?>
        <?php if ($showDashboard): ?>
        <div class="acc-chart-card">
            <h3 class="acc-chart-card__title">Receivable vs Payable</h3>
            <div class="acc-chart-card__body"><canvas id="accChartRecvPay" height="220"></canvas></div>
        </div>
        <?php endif; ?>
        <?php if ($showDashboard || $report === 'cashflow'): ?>
        <div class="acc-chart-card acc-chart-card--wide">
            <h3 class="acc-chart-card__title">Cash Flow Trend</h3>
            <div class="acc-chart-card__body"><canvas id="accChartCash" height="200"></canvas></div>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($showDashboard || in_array($report, ['receivable', 'payable'], true)): ?>
    <div class="acc-reports__grid acc-reports__grid--2">
        <?php if ($showDashboard || $report === 'receivable'): ?>
        <section class="acc-rpt-card">
            <h2 class="acc-rpt-card__title">Receivable Analytics</h2>
            <div class="acc-analytics">
                <div class="acc-analytics__stat"><span>Total Outstanding</span><strong><?= e(sales_format_money((float)$recv['total_outstanding'])) ?></strong></div>
                <div class="acc-analytics__stat"><span>Overdue Amount</span><strong class="text-danger"><?= e(sales_format_money((float)$recv['overdue'])) ?></strong></div>
                <div class="acc-analytics__stat"><span>Collection %</span><strong><?= e((string)$recv['collection_pct']) ?>%</strong></div>
            </div>
            <h3 class="acc-rpt-card__subtitle">Top Customers Pending</h3>
            <ul class="acc-top-list">
                <?php if (empty($recv['top_customers'])): ?>
                    <li class="text-muted">No outstanding receivables.</li>
                <?php else: ?>
                    <?php foreach ($recv['top_customers'] as $c): ?>
                        <li><span><?= e((string)($c['company_name'] ?? '—')) ?></span><strong><?= e(sales_format_money((float)($c['pending'] ?? 0))) ?></strong></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if ($showDashboard || $report === 'payable'): ?>
        <section class="acc-rpt-card">
            <h2 class="acc-rpt-card__title">Payable Analytics</h2>
            <div class="acc-analytics">
                <div class="acc-analytics__stat"><span>Total Payables</span><strong><?= e(sales_format_money((float)$pay['total_payables'])) ?></strong></div>
                <div class="acc-analytics__stat"><span>Overdue Supplier Payments</span><strong class="text-danger"><?= e(sales_format_money((float)$pay['overdue'])) ?></strong></div>
                <div class="acc-analytics__stat"><span>Payment Completion</span><strong><?= e((string)$pay['payment_completion_pct']) ?>%</strong></div>
            </div>
            <h3 class="acc-rpt-card__subtitle">Top Suppliers Pending</h3>
            <ul class="acc-top-list">
                <?php if (empty($pay['top_suppliers'])): ?>
                    <li class="text-muted">No outstanding payables.</li>
                <?php else: ?>
                    <?php foreach ($pay['top_suppliers'] as $s): ?>
                        <li><span><?= e((string)($s['name'] ?? '—')) ?></span><strong><?= e(sales_format_money((float)($s['pending_balance'] ?? 0))) ?></strong></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($showDashboard || $report === 'cashflow'): ?>
    <section class="acc-rpt-card acc-rpt-card--cashflow">
        <h2 class="acc-rpt-card__title">Cash Flow Summary</h2>
        <div class="acc-cashflow-grid">
            <div class="acc-cashflow-item"><span>Opening Balance</span><strong><?= e(sales_format_money((float)$cash['opening'])) ?></strong></div>
            <div class="acc-cashflow-item acc-cashflow-item--in"><span>Cash Inflow</span><strong><?= e(sales_format_money((float)$cash['inflow'])) ?></strong></div>
            <div class="acc-cashflow-item acc-cashflow-item--out"><span>Cash Outflow</span><strong><?= e(sales_format_money((float)$cash['outflow'])) ?></strong></div>
            <div class="acc-cashflow-item"><span>Closing Balance</span><strong><?= e(sales_format_money((float)$cash['closing'])) ?></strong></div>
            <div class="acc-cashflow-item"><span>Loans Received</span><strong><?= e(sales_format_money((float)$cash['loans_received'])) ?></strong></div>
            <div class="acc-cashflow-item"><span>Loan Repayments</span><strong><?= e(sales_format_money((float)$cash['loans_repaid'])) ?></strong></div>
        </div>
    </section>
    <?php endif; ?>

    <section class="acc-rpt-card acc-rpt-card--detail" id="accDetailedReport">
        <div class="acc-rpt-card__head">
            <h2 class="acc-rpt-card__title mb-0"><?= e($meta['title']) ?> — Detailed Data</h2>
            <?= erp_export_toolbar('accounts-report-table', 'accounts-' . ($report === 'overview' ? 'financial' : $report)) ?>
        </div>
        <div class="erp-table-panel">
            <table class="table table-sm erp-data-table" id="accounts-report-table">
                <?php if ($report === 'overview' || $report === 'profit'): ?>
                    <thead><tr><th>Metric</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <tr><td>Sales Revenue</td><td class="text-end"><?= e(sales_format_money((float)$pnl['sales_revenue'])) ?></td></tr>
                        <tr><td>Salary Expense</td><td class="text-end"><?= e(sales_format_money((float)$pnl['salary_expense'])) ?></td></tr>
                        <tr><td>Operational Expenses</td><td class="text-end"><?= e(sales_format_money((float)$pnl['operational_expenses'])) ?></td></tr>
                        <tr><td>Supplier Costs</td><td class="text-end"><?= e(sales_format_money((float)$pnl['supplier_costs'])) ?></td></tr>
                        <tr><td>Other Expenses</td><td class="text-end"><?= e(sales_format_money((float)$pnl['other_expenses'])) ?></td></tr>
                        <tr class="table-active"><td><strong>Net Profit</strong></td><td class="text-end"><strong><?= e(sales_format_money((float)$pnl['net_profit'])) ?></strong></td></tr>
                    </tbody>
                <?php elseif ($report === 'receivable'): ?>
                    <thead><tr><th>Customer</th><th class="text-end">Invoiced</th><th class="text-end">Paid</th><th class="text-end">Pending</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($legacy['recv_rows'] as $r): ?>
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
                    <?php foreach ($legacy['payable_rows'] as $r): ?>
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
                    <?php foreach ($legacy['expense_rows'] as $e): ?>
                        <tr>
                            <td><?= e((string)$e['expense_date']) ?></td>
                            <td><?= e((string)$e['category']) ?></td>
                            <td class="text-end"><?= e(sales_format_money((float)$e['amount'])) ?></td>
                            <td><?= e((string)$e['payment_mode']) ?></td>
                            <td><?= e((string)($e['remarks'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php elseif ($report === 'cashflow'): ?>
                    <thead><tr><th>ID</th><th>Date</th><th>Type</th><th>Party</th><th class="text-end">Amount</th><th>Mode</th></tr></thead>
                    <tbody>
                    <?php foreach ($legacy['cashflow_rows'] as $t): ?>
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
        <p class="small text-muted px-3 pb-3 mb-0">Detailed reports support table export and print from the toolbar above.</p>
    </section>
</div>

<script type="application/json" id="accReportsChartData"><?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="assets/js/erp-table-export.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/erp-table-export.js')) ?>"></script>
