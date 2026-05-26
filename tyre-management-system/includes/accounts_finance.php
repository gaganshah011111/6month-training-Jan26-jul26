<?php
declare(strict_types=1);

require_once __DIR__ . '/sales_service.php';
require_once __DIR__ . '/inventory_service.php';

const ACC_EXPENSE_CATEGORIES = ['Electricity', 'Diesel', 'Salary', 'Transport', 'Maintenance', 'Office', 'Misc'];
const ACC_PAYMENT_MODES = ['Cash', 'UPI', 'Bank', 'Cheque'];

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
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS accounts_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(40) NOT NULL,
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            payment_mode VARCHAR(20) NOT NULL DEFAULT 'Cash',
            expense_date DATE NOT NULL,
            remarks VARCHAR(255) NULL,
            attachment VARCHAR(255) NULL,
            created_by VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_acc_expense_date (expense_date),
            INDEX idx_acc_expense_cat (category),
            INDEX idx_acc_expense_mode (payment_mode)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function acc_save_expense(PDO $pdo, array $data): int
{
    acc_ensure_schema($pdo);
    $category = trim((string)($data['category'] ?? ''));
    $amount = max(0, (float)($data['amount'] ?? 0));
    $mode = trim((string)($data['payment_mode'] ?? 'Cash'));
    $date = trim((string)($data['expense_date'] ?? date('Y-m-d')));
    if (!in_array($category, ACC_EXPENSE_CATEGORIES, true)) {
        throw new InvalidArgumentException('Select a valid expense category.');
    }
    if ($amount <= 0) {
        throw new InvalidArgumentException('Expense amount must be greater than zero.');
    }
    if (!in_array($mode, ACC_PAYMENT_MODES, true)) {
        throw new InvalidArgumentException('Invalid payment mode.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid expense date is required.');
    }
    $st = $pdo->prepare(
        'INSERT INTO accounts_expenses (category, amount, payment_mode, expense_date, remarks, attachment, created_by)
         VALUES (:c, :a, :m, :d, :r, :f, :by)'
    );
    $st->execute([
        'c' => $category,
        'a' => round($amount, 2),
        'm' => $mode,
        'd' => $date,
        'r' => trim((string)($data['remarks'] ?? '')) ?: null,
        'f' => trim((string)($data['attachment'] ?? '')) ?: null,
        'by' => (string)((current_user()['full_name'] ?? current_user()['username'] ?? current_user()['email'] ?? 'ERP User')),
    ]);

    return (int)$pdo->lastInsertId();
}

function acc_list_expenses(PDO $pdo, array $filters = []): array
{
    acc_ensure_schema($pdo);
    $sql = 'SELECT * FROM accounts_expenses WHERE 1=1';
    $params = [];
    $from = (string)($filters['from'] ?? '');
    $to = (string)($filters['to'] ?? '');
    $cat = trim((string)($filters['category'] ?? ''));
    $q = trim((string)($filters['q'] ?? ''));
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $sql .= ' AND expense_date >= :f';
        $params['f'] = $from;
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $sql .= ' AND expense_date <= :t';
        $params['t'] = $to;
    }
    if ($cat !== '' && in_array($cat, ACC_EXPENSE_CATEGORIES, true)) {
        $sql .= ' AND category = :c';
        $params['c'] = $cat;
    }
    if ($q !== '') {
        $sql .= ' AND (remarks LIKE :q OR attachment LIKE :q OR created_by LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY expense_date DESC, id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function acc_expense_totals(PDO $pdo, string $from, string $to): array
{
    acc_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0) AS total,
                COALESCE(SUM(CASE WHEN expense_date >= DATE_FORMAT(CURDATE(), "%Y-%m-01") THEN amount ELSE 0 END),0) AS month_total
         FROM accounts_expenses
         WHERE expense_date >= :f AND expense_date <= :t'
    );
    $st->execute(['f' => $from, 't' => $to]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'month_total' => 0];
}

function acc_customer_ledger_summary(PDO $pdo): array
{
    $rows = sales_customer_outstanding_full($pdo);
    foreach ($rows as &$r) {
        $r['pending'] = (float)($r['pending'] ?? 0);
        $r['status_meta'] = acc_payment_meta((string)($r['status_label'] ?? 'Unpaid'));
    }
    unset($r);

    return $rows;
}

function acc_supplier_ledger_summary(PDO $pdo): array
{
    $rows = inv_supplier_ledger_list($pdo);
    foreach ($rows as &$r) {
        $pending = (float)($r['pending_balance'] ?? 0);
        $paid = (float)($r['total_paid'] ?? 0);
        $status = $pending <= inv_purchase_tolerance() ? 'Paid' : ($paid > inv_purchase_tolerance() ? 'Partial' : 'Unpaid');
        $r['status_meta'] = acc_payment_meta($status);
    }
    unset($r);

    return $rows;
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

    $inCash = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM sales_payments WHERE payment_mode = 'Cash'"
    )->fetchColumn();
    $inBank = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM sales_payments WHERE payment_mode IN ('UPI','Bank Transfer','Cheque')"
    )->fetchColumn();
    $outCashSupplier = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE payment_mode = 'Cash'"
    )->fetchColumn();
    $outBankSupplier = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM purchase_payments WHERE payment_mode IN ('UPI','Bank','Cheque')"
    )->fetchColumn();
    $outCashExpense = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM accounts_expenses WHERE payment_mode = 'Cash'"
    )->fetchColumn();
    $outBankExpense = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM accounts_expenses WHERE payment_mode IN ('UPI','Bank','Cheque')"
    )->fetchColumn();
    $cashInHand = $inCash - $outCashSupplier - $outCashExpense;
    $bankBalance = $inBank - $outBankSupplier - $outBankExpense;

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

    usort($rows, static fn($a, $b) => strcmp((string)$b['tx_date'], (string)$a['tx_date']));

    return $rows;
}
