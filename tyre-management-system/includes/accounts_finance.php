<?php
declare(strict_types=1);

require_once __DIR__ . '/sales_service.php';
require_once __DIR__ . '/inventory_service.php';
require_once __DIR__ . '/accounts_expenses.php';
require_once __DIR__ . '/accounts_treasury.php';

function acc_payment_meta(string $status): array
{
    return match ($status) {
        'Paid', 'Completed' => ['label' => 'Paid', 'cls' => 'inv-pay--paid'],
        'Partial', 'Pending' => ['label' => 'Partial', 'cls' => 'inv-pay--partial'],
        default => ['label' => 'Unpaid', 'cls' => 'inv-pay--unpaid'],
    };
}

function acc_ensure_schema(PDO $pdo): void
{
    acc_expense_ensure_schema($pdo);
    acc_treasury_ensure_schema($pdo);
}

function acc_customer_ledger_summary(PDO $pdo): array
{
    require_once __DIR__ . '/accounts_ledger.php';

    return acc_customer_ledger_list($pdo, []);
}

/**
 * KPI strip for Accounts → Payables (respects list filters).
 *
 * @param array{from?: string, to?: string, supplier_id?: int, payment_status?: string} $filters
 * @return array{pending: float, overdue: float, month_paid: float}
 */
function acc_payables_page_kpis(PDO $pdo, array $filters = []): array
{
    inv_purchase_ensure_schema($pdo);
    $rows = inv_purchase_list($pdo, $filters);
    $totalPending = 0.0;
    $totalOverdue = 0.0;
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

    return [
        'pending' => $totalPending,
        'overdue' => $totalOverdue,
        'month_paid' => $monthPaid,
    ];
}

function acc_supplier_ledger_summary(PDO $pdo): array
{
    require_once __DIR__ . '/accounts_ledger.php';

    return acc_supplier_ledger_list($pdo, []);
}

function acc_dashboard_data(PDO $pdo): array
{
    acc_ensure_schema($pdo);
    $ar = sales_payment_dashboard($pdo);
    $purchase = inv_purchase_dashboard_data($pdo);
    $supplier = inv_supplier_ledger_list($pdo);
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');

    $totalPayables = 0.0;
    foreach ($supplier as $s) {
        $totalPayables += (float)($s['pending_balance'] ?? 0);
    }
    $overdueReceivable = (float)($ar['overdue'] ?? 0);
    $overduePayable = 0.0;
    $overdueSuppliers = 0;
    $overduePurchaseCount = 0;
    if (inv_table_exists($pdo, 'stock_inward')) {
        $rows = $pdo->query(
            "SELECT supplier_id, due_date, GREATEST(total_amount - paid_amount, 0) AS pending
             FROM stock_inward
             WHERE due_date IS NOT NULL AND due_date <> ''"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $sup = [];
        foreach ($rows as $r) {
            $pending = (float)$r['pending'];
            if ($pending <= inv_purchase_tolerance()) {
                continue;
            }
            $due = (string)($r['due_date'] ?? '');
            if ($due !== '' && $due < $today) {
                $overduePayable += $pending;
                $overduePurchaseCount++;
                $sid = (int)($r['supplier_id'] ?? 0);
                if ($sid > 0) {
                    $sup[$sid] = true;
                }
            }
        }
        $overdueSuppliers = count($sup);
    }

    $monthlyRevenue = (float)$pdo->query(
        "SELECT COALESCE(SUM(total_amount),0) FROM sales_invoices WHERE invoice_date >= " . $pdo->quote($monthStart)
    )->fetchColumn();
    $monthlyExpense = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM accounts_expenses WHERE expense_date >= " . $pdo->quote($monthStart)
    )->fetchColumn();
    $estimatedProfit = $monthlyRevenue - $monthlyExpense;

    $customerRecent = $pdo->query(
        "SELECT p.id, p.payment_date, p.amount, p.payment_mode, p.reference_no,
                c.company_name, i.invoice_no
         FROM sales_payments p
         JOIN sales_customers c ON c.id = p.customer_id
         JOIN sales_invoices i ON i.id = p.invoice_id
         ORDER BY p.payment_date DESC, p.id DESC LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $supplierRecent = inv_purchase_list_payments($pdo, null, ['from' => date('Y-m-01'), 'to' => $today]);
    $supplierRecent = array_slice($supplierRecent, 0, 8);

    $tk = acc_treasury_kpis($pdo, false);
    $cashInHand = (float)$tk['cash_in_hand'];
    $bankBalance = (float)$tk['bank_balance'];

    $revTrend = $pdo->query(
        "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS ym, COALESCE(SUM(total_amount),0) AS amount
         FROM sales_invoices
         WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY ym ORDER BY ym ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $expTrend = $pdo->query(
        "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS amount
         FROM accounts_expenses
         WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY ym ORDER BY ym ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $alerts = [];
    if ($overduePurchaseCount > 0) {
        $alerts[] = ['level' => 'danger', 'text' => $overduePurchaseCount . ' overdue supplier purchases pending'];
    }
    if ($overdueReceivable > 0) {
        $alerts[] = ['level' => 'warn', 'text' => 'Overdue customer invoices: ' . sales_format_money($overdueReceivable)];
    }
    if ($cashInHand < 0) {
        $alerts[] = ['level' => 'danger', 'text' => 'Low cash balance. Review outgoing payments.'];
    }
    if ($totalPayables > ((float)($ar['pending'] ?? 0) * 1.2)) {
        $alerts[] = ['level' => 'warn', 'text' => 'Supplier payable is higher than customer receivable.'];
    }

    return [
        'total_receivables' => (float)($ar['pending'] ?? 0),
        'total_payables' => $totalPayables,
        'cash_in_hand' => $cashInHand,
        'bank_balance' => $bankBalance,
        'monthly_revenue' => $monthlyRevenue,
        'monthly_expenses' => $monthlyExpense,
        'estimated_profit' => $estimatedProfit,
        'overdue_payments' => $overdueReceivable + $overduePayable,
        'overdue_suppliers' => $overdueSuppliers,
        'recent_customer_payments' => $customerRecent,
        'recent_supplier_payments' => $supplierRecent,
        'alerts' => $alerts,
        'revenue_trend' => $revTrend,
        'expense_trend' => $expTrend,
        'receivable_vs_payable' => [
            'receivable' => (float)($ar['pending'] ?? 0),
            'payable' => $totalPayables,
        ],
        'pending_supplier_payables' => (float)($purchase['pending_payables'] ?? 0),
    ];
}

function acc_finance_transactions(PDO $pdo, array $filters = []): array
{
    acc_ensure_schema($pdo);
    $from = (string)($filters['from'] ?? '2000-01-01');
    $to = (string)($filters['to'] ?? date('Y-m-d'));
    $rows = [];

    $cust = $pdo->prepare(
        "SELECT CONCAT('CP-', p.id) AS txid, p.payment_date AS tx_date, 'Customer Payment' AS tx_type,
                c.company_name AS party, i.invoice_no AS reference_no, p.amount, p.payment_mode, 'Completed' AS tx_status
         FROM sales_payments p
         JOIN sales_customers c ON c.id = p.customer_id
         JOIN sales_invoices i ON i.id = p.invoice_id
         WHERE p.payment_date >= :f AND p.payment_date <= :t"
    );
    $cust->execute(['f' => $from, 't' => $to]);
    $rows = array_merge($rows, $cust->fetchAll(PDO::FETCH_ASSOC) ?: []);

    if (inv_table_exists($pdo, 'purchase_payments')) {
        $sup = $pdo->prepare(
            "SELECT CONCAT('SP-', p.id) AS txid, p.payment_date AS tx_date, 'Supplier Payment' AS tx_type,
                    COALESCE(s.name, 'Supplier') AS party, COALESCE(i.pinv_no, CONCAT('PINV-', i.id)) AS reference_no,
                    p.amount, p.payment_mode, i.payment_status AS tx_status
             FROM purchase_payments p
             JOIN stock_inward i ON i.id = p.inward_id
             LEFT JOIN suppliers s ON s.id = i.supplier_id
             WHERE p.payment_date >= :f AND p.payment_date <= :t"
        );
        $sup->execute(['f' => $from, 't' => $to]);
        $rows = array_merge($rows, $sup->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    $exp = $pdo->prepare(
        "SELECT CONCAT('EX-', e.id) AS txid, e.expense_date AS tx_date, 'Expense' AS tx_type,
                e.category AS party, COALESCE(e.remarks, e.category) AS reference_no, e.amount, e.payment_mode,
                'Completed' AS tx_status
         FROM accounts_expenses e
         WHERE e.expense_date >= :f AND e.expense_date <= :t"
    );
    $exp->execute(['f' => $from, 't' => $to]);
    $rows = array_merge($rows, $exp->fetchAll(PDO::FETCH_ASSOC) ?: []);

    if (function_exists('acc_salary_ensure_schema')) {
        acc_salary_ensure_schema($pdo);
        $sal = $pdo->prepare(
            "SELECT CONCAT('SL-', p.id) AS txid, p.payment_date AS tx_date, 'Salary Payment' AS tx_type,
                    e.full_name AS party,
                    CONCAT('Payroll ', s.month_year, COALESCE(CONCAT(' · ', p.reference_no), '')) AS reference_no,
                    p.amount, p.payment_mode, 'Completed' AS tx_status
             FROM accounts_salary_payments p
             INNER JOIN salaries s ON s.id = p.salary_id
             INNER JOIN employees e ON e.id = p.employee_id
             WHERE p.payment_date >= :f AND p.payment_date <= :t AND p.salary_id IS NOT NULL"
        );
        $sal->execute(['f' => $from, 't' => $to]);
        $rows = array_merge($rows, $sal->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    usort($rows, static fn($a, $b) => strcmp((string)$b['tx_date'], (string)$a['tx_date']));

    return $rows;
}
