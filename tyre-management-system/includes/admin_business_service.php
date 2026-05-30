<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_control_center.php';

/** @return array<string, mixed> */
function admin_business_overview(PDO $pdo): array
{
    $fin = admin_financial_snapshot($pdo);
    $exec = admin_executive_reports($pdo);
    $loans = 0.0;
    if (dh_table_exists($pdo, 'accounts_loans')) {
        try {
            $loans = (float)$pdo->query('SELECT COALESCE(SUM(outstanding_balance),0) FROM accounts_loans')->fetchColumn();
        } catch (Throwable) {
        }
    }

    return [
        'revenue' => $fin['revenue'],
        'expenses' => $fin['expenses'],
        'profit' => $fin['profit'],
        'receivables' => $fin['receivables'],
        'payables' => $fin['payables'],
        'cash' => $fin['cash'],
        'loans' => $loans,
        'salary_cost' => (float)($exec['payroll_cost'] ?? 0),
        'inventory_value' => (float)($exec['inventory_value'] ?? 0),
        'customers' => (int)($exec['customers'] ?? 0),
        'suppliers' => (int)($exec['suppliers'] ?? 0),
    ];
}

/** @return array{labels: list<string>, revenue: list<float>, expenses: list<float>, profit: list<float>} */
function admin_business_monthly_trends(PDO $pdo, int $months = 6): array
{
    $labels = [];
    $revenue = [];
    $expenses = [];
    $profit = [];
    for ($i = $months - 1; $i >= 0; --$i) {
        $ts = strtotime('-' . $i . ' months');
        $ym = date('Y-m', $ts);
        $labels[] = date('M Y', $ts);
        $rev = 0.0;
        $exp = 0.0;
        if (dh_table_exists($pdo, 'sales_invoices')) {
            $st = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM sales_invoices WHERE DATE_FORMAT(invoice_date, "%Y-%m") = :m');
            $st->execute(['m' => $ym]);
            $rev = (float)$st->fetchColumn();
        }
        if (dh_table_exists($pdo, 'accounts_expenses')) {
            $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM accounts_expenses WHERE DATE_FORMAT(expense_date, "%Y-%m") = :m');
            $st->execute(['m' => $ym]);
            $exp = (float)$st->fetchColumn();
        }
        $revenue[] = round($rev, 2);
        $expenses[] = round($exp, 2);
        $profit[] = round($rev - $exp, 2);
    }

    return compact('labels', 'revenue', 'expenses', 'profit');
}

/** @return list<array<string, mixed>> */
function admin_business_top_customers(PDO $pdo, int $limit = 5): array
{
    if (!dh_table_exists($pdo, 'sales_invoices') || !dh_table_exists($pdo, 'sales_customers')) {
        return [];
    }
    $st = $pdo->query(
        'SELECT c.company_name, COALESCE(SUM(i.total_amount),0) AS total
         FROM sales_customers c
         INNER JOIN sales_invoices i ON i.customer_id = c.id
         WHERE i.invoice_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         GROUP BY c.id, c.company_name
         ORDER BY total DESC LIMIT ' . max(1, min(10, $limit))
    );

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function admin_business_top_suppliers(PDO $pdo, int $limit = 5): array
{
    if (!dh_table_exists($pdo, 'stock_inward') || !dh_table_exists($pdo, 'suppliers')) {
        return [];
    }
    $amt = dh_column_exists($pdo, 'stock_inward', 'total_amount') ? 'si.total_amount' : '(si.quantity * si.rate)';
    $st = $pdo->query(
        "SELECT s.name, COALESCE(SUM({$amt}),0) AS total
         FROM suppliers s
         INNER JOIN stock_inward si ON si.supplier_id = s.id
         WHERE si.inward_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         GROUP BY s.id, s.name
         ORDER BY total DESC LIMIT " . max(1, min(10, $limit))
    );

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
