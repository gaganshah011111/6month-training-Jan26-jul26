<?php
declare(strict_types=1);

require_once __DIR__ . '/accounts_finance.php';
require_once __DIR__ . '/accounts_ledger.php';
require_once __DIR__ . '/erp_export.php';

const ACC_REPORT_TYPES = [
    'overview' => ['title' => 'Overview', 'hint' => 'Full financial analytics dashboard'],
    'profit' => ['title' => 'Profit summary', 'hint' => 'Revenue vs expense snapshot'],
    'receivable' => ['title' => 'Receivable report', 'hint' => 'Customer pending and overdue balances'],
    'payable' => ['title' => 'Payable report', 'hint' => 'Supplier pending payable balances'],
    'expense' => ['title' => 'Expense report', 'hint' => 'Expense entries by category and mode'],
    'cashflow' => ['title' => 'Cash flow report', 'hint' => 'Incoming vs outgoing transactions'],
];

const ACC_REPORT_DEPARTMENTS = [
    '' => 'All departments',
    'sales' => 'Sales & Revenue',
    'payroll' => 'Payroll & HR',
    'operations' => 'Operations',
    'procurement' => 'Procurement',
];

/** @return array{from: string, to: string, department: string, report: string} */
function acc_reports_parse_filters(array $input): array
{
    $from = trim((string)($input['from'] ?? date('Y-m-01')));
    $to = trim((string)($input['to'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = date('Y-m-01');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = date('Y-m-d');
    }
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $dept = trim((string)($input['department'] ?? ''));
    if (!array_key_exists($dept, ACC_REPORT_DEPARTMENTS)) {
        $dept = '';
    }
    $report = (string)($input['report'] ?? 'overview');
    if (!isset(ACC_REPORT_TYPES[$report])) {
        $report = 'overview';
    }

    return ['from' => $from, 'to' => $to, 'department' => $dept, 'report' => $report];
}

function acc_reports_revenue(PDO $pdo, string $from, string $to): float
{
    if (!dh_table_exists($pdo, 'sales_invoices')) {
        return 0.0;
    }
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(total_amount),0) FROM sales_invoices WHERE invoice_date >= :f AND invoice_date <= :t'
    );
    $st->execute(['f' => $from, 't' => $to]);

    return (float)$st->fetchColumn();
}

function acc_reports_expense_sql_filter(string $department): array
{
    return match ($department) {
        'payroll' => [" AND (category IN ('Salary','Salary Adjustment') OR category LIKE '%Salary%') ", []],
        'operations' => [" AND category NOT IN ('Salary','Salary Adjustment','Supplier Payment') ", []],
        'procurement' => [" AND category = 'Supplier Payment' ", []],
        'sales' => [' AND 1=0 ', []],
        default => ['', []],
    };
}

function acc_reports_expenses_total(PDO $pdo, string $from, string $to, string $department = ''): float
{
    acc_expense_ensure_schema($pdo);
    [$sqlExtra] = acc_reports_expense_sql_filter($department);
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0) FROM accounts_expenses WHERE expense_date >= :f AND expense_date <= :t' . $sqlExtra
    );
    $st->execute(['f' => $from, 't' => $to]);

    return (float)$st->fetchColumn();
}

function acc_reports_salary_total(PDO $pdo, string $from, string $to): float
{
    $total = 0.0;
    if (function_exists('acc_salary_ensure_schema')) {
        acc_salary_ensure_schema($pdo);
        $st = $pdo->prepare(
            'SELECT COALESCE(SUM(amount),0) FROM accounts_salary_payments WHERE payment_date >= :f AND payment_date <= :t AND salary_id IS NOT NULL'
        );
        $st->execute(['f' => $from, 't' => $to]);
        $total += (float)$st->fetchColumn();
    }
    acc_expense_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM accounts_expenses WHERE expense_date >= :f AND expense_date <= :t AND category IN ('Salary','Salary Adjustment')"
    );
    $st->execute(['f' => $from, 't' => $to]);
    $total += (float)$st->fetchColumn();

    return $total;
}

function acc_reports_supplier_costs(PDO $pdo, string $from, string $to): float
{
    $total = 0.0;
    if (inv_table_exists($pdo, 'purchase_payments')) {
        $st = $pdo->prepare(
            'SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE payment_date >= :f AND payment_date <= :t'
        );
        $st->execute(['f' => $from, 't' => $to]);
        $total += (float)$st->fetchColumn();
    }
    acc_expense_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM accounts_expenses WHERE expense_date >= :f AND expense_date <= :t AND category = 'Supplier Payment'"
    );
    $st->execute(['f' => $from, 't' => $to]);
    $total += (float)$st->fetchColumn();

    return $total;
}

function acc_reports_operational_expenses(PDO $pdo, string $from, string $to): float
{
    acc_expense_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM accounts_expenses
         WHERE expense_date >= :f AND expense_date <= :t
         AND category NOT IN ('Salary','Salary Adjustment','Supplier Payment')"
    );
    $st->execute(['f' => $from, 't' => $to]);

    return (float)$st->fetchColumn();
}

function acc_reports_other_expenses(PDO $pdo, string $from, string $to): float
{
    acc_expense_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM accounts_expenses
         WHERE expense_date >= :f AND expense_date <= :t AND category = 'Miscellaneous'"
    );
    $st->execute(['f' => $from, 't' => $to]);

    return (float)$st->fetchColumn();
}

/** @return list<array{ym: string, revenue: float, expense: float, profit: float}> */
function acc_reports_monthly_series(PDO $pdo, string $from, string $to): array
{
    $months = [];
    $start = new DateTime($from);
    $end = new DateTime($to);
    $end->modify('first day of this month');
    $cur = clone $start;
    $cur->modify('first day of this month');
    while ($cur <= $end) {
        $months[$cur->format('Y-m')] = ['ym' => $cur->format('Y-m'), 'revenue' => 0.0, 'expense' => 0.0, 'profit' => 0.0];
        $cur->modify('+1 month');
    }
    if ($months === []) {
        $months[substr($from, 0, 7)] = ['ym' => substr($from, 0, 7), 'revenue' => 0.0, 'expense' => 0.0, 'profit' => 0.0];
    }

    if (dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->prepare(
            "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS ym, COALESCE(SUM(total_amount),0) AS amt
             FROM sales_invoices WHERE invoice_date >= :f AND invoice_date <= :t GROUP BY ym"
        );
        $st->execute(['f' => $from, 't' => $to]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $ym = (string)$r['ym'];
            if (isset($months[$ym])) {
                $months[$ym]['revenue'] = (float)$r['amt'];
            }
        }
    }

    acc_expense_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS amt
         FROM accounts_expenses WHERE expense_date >= :f AND expense_date <= :t GROUP BY ym"
    );
    $st->execute(['f' => $from, 't' => $to]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $ym = (string)$r['ym'];
        if (isset($months[$ym])) {
            $months[$ym]['expense'] += (float)$r['amt'];
        }
    }

    if (function_exists('acc_salary_ensure_schema')) {
        acc_salary_ensure_schema($pdo);
        $st = $pdo->prepare(
            "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS amt
             FROM accounts_salary_payments WHERE payment_date >= :f AND payment_date <= :t AND salary_id IS NOT NULL GROUP BY ym"
        );
        $st->execute(['f' => $from, 't' => $to]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $ym = (string)$r['ym'];
            if (isset($months[$ym])) {
                $months[$ym]['expense'] += (float)$r['amt'];
            }
        }
    }

    foreach ($months as &$m) {
        $m['profit'] = round($m['revenue'] - $m['expense'], 2);
    }
    unset($m);

    return array_values($months);
}

/** @return list<array{category: string, amount: float}> */
function acc_reports_expense_breakdown(PDO $pdo, string $from, string $to): array
{
    acc_expense_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT category, COALESCE(SUM(amount),0) AS amount FROM accounts_expenses
         WHERE expense_date >= :f AND expense_date <= :t GROUP BY category ORDER BY amount DESC'
    );
    $st->execute(['f' => $from, 't' => $to]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (function_exists('acc_salary_ensure_schema')) {
        acc_salary_ensure_schema($pdo);
        $sal = $pdo->prepare(
            'SELECT COALESCE(SUM(amount),0) FROM accounts_salary_payments WHERE payment_date >= :f AND payment_date <= :t AND salary_id IS NOT NULL'
        );
        $sal->execute(['f' => $from, 't' => $to]);
        $salAmt = (float)$sal->fetchColumn();
        if ($salAmt > 0) {
            $rows[] = ['category' => 'Salary (Payroll)', 'amount' => $salAmt];
        }
    }

    usort($rows, static fn($a, $b) => (float)$b['amount'] <=> (float)$a['amount']);

    return $rows;
}

function acc_reports_cash_flow(PDO $pdo, string $from, string $to): array
{
    acc_treasury_ensure_schema($pdo);
    $opening = acc_treasury_get_opening($pdo);
    $openCash = (float)($opening['opening_cash'] ?? 0);
    $openBank = (float)($opening['opening_bank'] ?? 0);
    $openingBal = round($openCash + $openBank, 2);

    $stIn = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0) FROM accounts_treasury_ledger WHERE direction = \'credit\' AND tx_date >= :f AND tx_date <= :t'
    );
    $stIn->execute(['f' => $from, 't' => $to]);
    $inflow = (float)$stIn->fetchColumn();

    $stOut = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0) FROM accounts_treasury_ledger WHERE direction = \'debit\' AND tx_date >= :f AND tx_date <= :t'
    );
    $stOut->execute(['f' => $from, 't' => $to]);
    $outflow = (float)$stOut->fetchColumn();

    $stLoanIn = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM accounts_treasury_ledger WHERE tx_type = 'Loan Received' AND tx_date >= :f AND tx_date <= :t"
    );
    $stLoanIn->execute(['f' => $from, 't' => $to]);
    $loansIn = (float)$stLoanIn->fetchColumn();

    $stLoanOut = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM accounts_treasury_ledger WHERE tx_type = 'Loan Repayment' AND tx_date >= :f AND tx_date <= :t"
    );
    $stLoanOut->execute(['f' => $from, 't' => $to]);
    $loansOut = (float)$stLoanOut->fetchColumn();

    $stClose = $pdo->prepare(
        'SELECT balance_after FROM accounts_treasury_ledger WHERE tx_date <= :t ORDER BY tx_date DESC, id DESC LIMIT 1'
    );
    $stClose->execute(['t' => $to]);
    $closing = $stClose->fetchColumn();
    $closingBal = $closing !== false ? (float)$closing : $openingBal;

    $cashTrend = [];
    if (dh_table_exists($pdo, 'accounts_treasury_ledger')) {
        $trend = $pdo->prepare(
            "SELECT tx_date AS d, balance_after AS bal FROM accounts_treasury_ledger
             WHERE tx_date >= :f AND tx_date <= :t ORDER BY tx_date ASC, id ASC"
        );
        $trend->execute(['f' => $from, 't' => $to]);
        $lastDay = '';
        foreach ($trend->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $lastDay = (string)$r['d'];
            $cashTrend[$lastDay] = (float)$r['bal'];
        }
    }

    return [
        'opening' => $openingBal,
        'inflow' => $inflow,
        'outflow' => $outflow,
        'closing' => $closingBal,
        'loans_received' => $loansIn,
        'loans_repaid' => $loansOut,
        'trend_labels' => array_keys($cashTrend),
        'trend_values' => array_values($cashTrend),
    ];
}

function acc_reports_receivable_analytics(PDO $pdo): array
{
    $ar = sales_payment_dashboard($pdo);
    $customers = sales_customer_outstanding($pdo);
    $top = array_slice($customers, 0, 5);
    $totalInv = (float)($ar['total_receivable'] ?? 0);
    $collected = (float)($ar['collected'] ?? 0);
    $collectionPct = $totalInv > 0 ? round(($collected / $totalInv) * 100, 1) : 0.0;

    return [
        'total_outstanding' => (float)($ar['pending'] ?? 0),
        'overdue' => (float)($ar['overdue'] ?? 0),
        'collection_pct' => $collectionPct,
        'top_customers' => $top,
    ];
}

function acc_reports_payable_analytics(PDO $pdo): array
{
    $suppliers = acc_supplier_ledger_list($pdo, []);
    $total = 0.0;
    $paidTotal = 0.0;
    $purchasedTotal = 0.0;
    foreach ($suppliers as $s) {
        $total += (float)($s['pending_balance'] ?? 0);
        $paidTotal += (float)($s['total_paid'] ?? 0);
        $purchasedTotal += (float)($s['total_purchased'] ?? 0);
    }
    $payKpi = acc_payables_page_kpis($pdo, []);
    $overdue = (float)($payKpi['overdue'] ?? 0);
    usort($suppliers, static fn($a, $b) => (float)($b['pending_balance'] ?? 0) <=> (float)($a['pending_balance'] ?? 0));
    $top = array_slice($suppliers, 0, 5);
    $completion = $purchasedTotal > 0 ? round(($paidTotal / $purchasedTotal) * 100, 1) : 0.0;

    return [
        'total_payables' => $total,
        'overdue' => $overdue,
        'payment_completion_pct' => $completion,
        'top_suppliers' => $top,
    ];
}

function acc_reports_insights(PDO $pdo, string $from, string $to): array
{
    $insights = [];
    $days = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
    $prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
    $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));

    $revNow = acc_reports_revenue($pdo, $from, $to);
    $revPrev = acc_reports_revenue($pdo, $prevFrom, $prevTo);
    $expNow = acc_reports_salary_total($pdo, $from, $to)
        + acc_reports_supplier_costs($pdo, $from, $to)
        + acc_reports_operational_expenses($pdo, $from, $to)
        + acc_reports_other_expenses($pdo, $from, $to);
    $expPrev = acc_reports_salary_total($pdo, $prevFrom, $prevTo)
        + acc_reports_supplier_costs($pdo, $prevFrom, $prevTo)
        + acc_reports_operational_expenses($pdo, $prevFrom, $prevTo)
        + acc_reports_other_expenses($pdo, $prevFrom, $prevTo);

    if ($revPrev > 0) {
        $pct = round((($revNow - $revPrev) / $revPrev) * 100, 1);
        $insights[] = 'Revenue ' . ($pct >= 0 ? 'increased' : 'decreased') . ' ' . abs($pct) . '% vs prior period.';
    }
    if ($expPrev > 0) {
        $pct = round((($expNow - $expPrev) / $expPrev) * 100, 1);
        $insights[] = 'Expenses ' . ($pct >= 0 ? 'increased' : 'decreased') . ' ' . abs($pct) . '% vs prior period.';
    }

    $breakdown = acc_reports_expense_breakdown($pdo, $from, $to);
    if ($breakdown !== []) {
        $insights[] = 'Largest expense category = ' . (string)$breakdown[0]['category'] . ' (' . sales_format_money((float)$breakdown[0]['amount']) . ').';
    }

    $recv = acc_reports_receivable_analytics($pdo);
    if (!empty($recv['top_customers'][0]['company_name'])) {
        $insights[] = 'Highest outstanding customer: ' . (string)$recv['top_customers'][0]['company_name'] . ' (' . sales_format_money((float)$recv['top_customers'][0]['pending']) . ').';
    }

    $pay = acc_reports_payable_analytics($pdo);
    if (!empty($pay['top_suppliers'][0]['name'])) {
        $insights[] = 'Highest payable supplier: ' . (string)$pay['top_suppliers'][0]['name'] . ' (' . sales_format_money((float)$pay['top_suppliers'][0]['pending_balance']) . ').';
    }

    $profit = $revNow - $expNow;
    if ($profit < 0) {
        $insights[] = 'Net profit is negative for this period — review expenses and collections.';
    } elseif ($recv['overdue'] > 0) {
        $insights[] = 'Overdue receivables: ' . sales_format_money((float)$recv['overdue']) . ' — follow up on collections.';
    }

    if ($insights === []) {
        $insights[] = 'No significant variances detected for the selected period.';
    }

    return $insights;
}

/** @return array<string, mixed> */
function acc_reports_bundle(PDO $pdo, array $filters): array
{
    acc_ensure_schema($pdo);
    $from = $filters['from'];
    $to = $filters['to'];
    $dept = $filters['department'];

    $revenue = acc_reports_revenue($pdo, $from, $to);
    $salary = acc_reports_salary_total($pdo, $from, $to);
    $supplier = acc_reports_supplier_costs($pdo, $from, $to);
    $operational = acc_reports_operational_expenses($pdo, $from, $to);
    $other = acc_reports_other_expenses($pdo, $from, $to);
    $expenses = acc_reports_expenses_total($pdo, $from, $to, $dept) + $salary + $supplier;
    if ($dept === 'sales') {
        $expenses = 0.0;
    } elseif ($dept === 'payroll') {
        $expenses = $salary;
        $revenue = 0.0;
    } elseif ($dept === 'operations') {
        $expenses = $operational;
    } elseif ($dept === 'procurement') {
        $expenses = $supplier;
    }
    $profit = round($revenue - ($salary + $supplier + $operational + $other), 2);

    $ar = sales_payment_dashboard($pdo);
    $payTotal = 0.0;
    foreach (acc_supplier_ledger_list($pdo, []) as $s) {
        $payTotal += (float)($s['pending_balance'] ?? 0);
    }
    $tk = acc_treasury_kpis($pdo, false);
    $availableCash = (float)($tk['available_funds'] ?? 0);

    $monthly = acc_reports_monthly_series($pdo, $from, $to);
    $expBreakdown = acc_reports_expense_breakdown($pdo, $from, $to);

    return [
        'kpis' => [
            'revenue' => $revenue,
            'expenses' => $salary + $supplier + $operational + $other,
            'profit' => $profit,
            'receivables' => (float)($ar['pending'] ?? 0),
            'payables' => $payTotal,
            'cash' => $availableCash,
        ],
        'pnl' => [
            'sales_revenue' => $revenue,
            'salary_expense' => $salary,
            'operational_expenses' => $operational,
            'supplier_costs' => $supplier,
            'other_expenses' => $other,
            'net_profit' => $profit,
        ],
        'charts' => [
            'monthly' => $monthly,
            'expense_breakdown' => $expBreakdown,
            'receivable_vs_payable' => [
                'receivable' => (float)($ar['pending'] ?? 0),
                'payable' => $payTotal,
            ],
            'cash_flow' => acc_reports_cash_flow($pdo, $from, $to),
        ],
        'receivable' => acc_reports_receivable_analytics($pdo),
        'payable' => acc_reports_payable_analytics($pdo),
        'insights' => acc_reports_insights($pdo, $from, $to),
        'legacy' => [
            'recv_rows' => acc_customer_ledger_list($pdo, []),
            'payable_rows' => acc_supplier_ledger_list($pdo, []),
            'expense_rows' => acc_list_expenses($pdo, ['from' => $from, 'to' => $to]),
            'cashflow_rows' => acc_finance_transactions($pdo, ['from' => $from, 'to' => $to]),
        ],
    ];
}

function acc_reports_handle_export(PDO $pdo): void
{
    $export = strtolower(trim((string)($_GET['export'] ?? '')));
    if (!in_array($export, ['pdf', 'csv', 'print', 'excel'], true)) {
        return;
    }
    if ($export === 'excel') {
        $export = 'csv';
    }
    $filters = acc_reports_parse_filters($_GET);
    $bundle = acc_reports_bundle($pdo, $filters);
    $kpis = $bundle['kpis'];
    $pnl = $bundle['pnl'];

    $headers = ['Metric', 'Amount'];
    $rows = [
        ['Total Revenue', sales_format_money((float)$kpis['revenue'])],
        ['Total Expenses', sales_format_money((float)$kpis['expenses'])],
        ['Net Profit', sales_format_money((float)$kpis['profit'])],
        ['Total Receivables', sales_format_money((float)$kpis['receivables'])],
        ['Total Payables', sales_format_money((float)$kpis['payables'])],
        ['Available Cash', sales_format_money((float)$kpis['cash'])],
        ['—', '—'],
        ['Sales Revenue (P&L)', sales_format_money((float)$pnl['sales_revenue'])],
        ['Salary Expense', sales_format_money((float)$pnl['salary_expense'])],
        ['Operational Expenses', sales_format_money((float)$pnl['operational_expenses'])],
        ['Supplier Costs', sales_format_money((float)$pnl['supplier_costs'])],
        ['Other Expenses', sales_format_money((float)$pnl['other_expenses'])],
        ['Net Profit (P&L)', sales_format_money((float)$pnl['net_profit'])],
    ];

    $title = 'Financial Reports · ' . $filters['from'] . ' to ' . $filters['to'];
    $printOpts = [
        'back_url' => route_url('accounts/reports', array_filter([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'department' => $filters['department'] ?: null,
            'report' => $filters['report'] !== 'overview' ? $filters['report'] : null,
        ])),
        'subtitle' => ACC_REPORT_TYPES[$filters['report']]['hint'] ?? '',
        'kpis' => [
            ['Revenue', sales_format_money((float)$kpis['revenue']), 'ok'],
            ['Expenses', sales_format_money((float)$kpis['expenses']), 'danger'],
            ['Profit', sales_format_money((float)$kpis['profit']), 'primary'],
        ],
        'meta' => [
            'Period' => $filters['from'] . ' — ' . $filters['to'],
            'Department' => ACC_REPORT_DEPARTMENTS[$filters['department']] ?? 'All',
        ],
    ];

    if ($export === 'csv') {
        erp_send_csv('financial-reports.csv', $headers, $rows);
    }
    erp_print_html_table($title, $headers, $rows, $export === 'print', $printOpts);
    exit;
}
