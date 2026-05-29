<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_control_center.php';
require_once __DIR__ . '/admin_audit_service.php';
require_once __DIR__ . '/department_hierarchy.php';

function admin_oversight_ensure_schema(PDO $pdo): void
{
    if (dh_table_exists($pdo, 'sales_customers') && !dh_column_exists($pdo, 'sales_customers', 'is_frozen')) {
        try {
            $pdo->exec('ALTER TABLE sales_customers ADD COLUMN is_frozen TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
        } catch (Throwable) {
        }
    }
    if (dh_table_exists($pdo, 'suppliers') && !dh_column_exists($pdo, 'suppliers', 'status')) {
        try {
            $pdo->exec("ALTER TABLE suppliers ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER address");
        } catch (Throwable) {
        }
    }
}

function admin_oversight_handle_post(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_can_access()) {
        return;
    }
    verify_csrf();
    admin_oversight_ensure_schema($pdo);
    $action = (string)($_POST['action'] ?? '');
    $return = trim((string)($_POST['return'] ?? 'admin/sales-oversight'));
    if (!preg_match('/^admin\/[\w-]+$/', $return)) {
        $return = 'admin/sales-oversight';
    }

    try {
        if ($action === 'freeze_customer') {
            $id = (int)($_POST['customer_id'] ?? 0);
            $st = $pdo->prepare('UPDATE sales_customers SET is_frozen = 1, status = "Inactive" WHERE id = :id');
            $st->execute(['id' => $id]);
            admin_audit_log($pdo, 'Froze customer account', 'Sales Oversight', 'warning', '#' . $id, null, 'frozen', 'customer', $id);
            set_flash('success', 'Customer account frozen.');
            $return = 'admin/sales-oversight';
        } elseif ($action === 'unfreeze_customer') {
            $id = (int)($_POST['customer_id'] ?? 0);
            $st = $pdo->prepare('UPDATE sales_customers SET is_frozen = 0, status = "Active" WHERE id = :id');
            $st->execute(['id' => $id]);
            admin_audit_log($pdo, 'Unfroze customer account', 'Sales Oversight', 'success', '#' . $id, 'frozen', 'active', 'customer', $id);
            set_flash('success', 'Customer account reactivated.');
            $return = 'admin/sales-oversight';
        } elseif ($action === 'blacklist_supplier') {
            $id = (int)($_POST['supplier_id'] ?? 0);
            $st = $pdo->prepare("UPDATE suppliers SET status = 'blacklisted' WHERE id = :id");
            $st->execute(['id' => $id]);
            admin_audit_log($pdo, 'Blacklisted supplier', 'Purchase Oversight', 'warning', '#' . $id, 'active', 'blacklisted', 'supplier', $id);
            set_flash('success', 'Supplier blacklisted.');
            $return = 'admin/purchase-oversight';
        } elseif ($action === 'reactivate_supplier') {
            $id = (int)($_POST['supplier_id'] ?? 0);
            $st = $pdo->prepare("UPDATE suppliers SET status = 'active' WHERE id = :id");
            $st->execute(['id' => $id]);
            admin_audit_log($pdo, 'Reactivated supplier', 'Purchase Oversight', 'success', '#' . $id, 'blacklisted', 'active', 'supplier', $id);
            set_flash('success', 'Supplier reactivated.');
            $return = 'admin/purchase-oversight';
        } else {
            throw new InvalidArgumentException('Unknown action.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect($return);
}

/** @return list<array<string, mixed>> */
function admin_oversight_employees(PDO $pdo, string $q = ''): array
{
    if (!dh_table_exists($pdo, 'employees')) {
        return [];
    }
    $sql = "SELECT e.id, e.employee_code, e.full_name, e.department, e.designation, e.status, e.joining_date,
                   u.id AS user_id, u.last_login
            FROM employees e
            LEFT JOIN users u ON u.id = e.user_id
            WHERE 1=1";
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (e.full_name LIKE :q OR e.employee_code LIKE :q OR e.department LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY e.full_name ASC LIMIT 200';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, mixed>|null */
function admin_oversight_employee_detail(PDO $pdo, int $id): ?array
{
    if (!dh_table_exists($pdo, 'employees')) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT e.*, u.id AS user_id, u.email AS user_email, u.role, u.status AS user_status, u.last_login
         FROM employees e LEFT JOIN users u ON u.id = e.user_id WHERE e.id = :id LIMIT 1'
    );
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['attendance_count'] = admin_count($pdo, 'SELECT COUNT(*) FROM attendance WHERE employee_id = ' . $id);
    $row['leave_count'] = admin_count($pdo, 'SELECT COUNT(*) FROM leaves WHERE employee_id = ' . $id);
    $row['payroll_count'] = admin_count($pdo, 'SELECT COUNT(*) FROM salaries WHERE employee_id = ' . $id);

    return $row;
}

/** @return list<array<string, mixed>> */
function admin_oversight_customers(PDO $pdo, string $q = ''): array
{
    admin_oversight_ensure_schema($pdo);
    if (!dh_table_exists($pdo, 'sales_customers')) {
        return [];
    }
    $sql = 'SELECT c.*,
            (SELECT COALESCE(SUM(i.total_amount - i.amount_paid),0) FROM sales_invoices i WHERE i.customer_id = c.id AND i.payment_status != "Paid") AS pending
            FROM sales_customers c WHERE 1=1';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (c.company_name LIKE :q OR c.customer_code LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY c.company_name LIMIT 200';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function admin_oversight_suppliers(PDO $pdo, string $q = ''): array
{
    admin_oversight_ensure_schema($pdo);
    if (!dh_table_exists($pdo, 'suppliers')) {
        return [];
    }
    $sql = 'SELECT s.* FROM suppliers s WHERE 1=1';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND s.name LIKE :q';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY s.name LIMIT 200';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, mixed> */
function admin_oversight_finance_kpis(PDO $pdo): array
{
    $fin = admin_financial_snapshot($pdo);
    $expenseCount = admin_table_count($pdo, 'accounts_expenses');
    $salaryPending = 0;
    if (dh_table_exists($pdo, 'salaries') && dh_column_exists($pdo, 'salaries', 'payment_status')) {
        $salaryPending = admin_count($pdo, "SELECT COUNT(*) FROM salaries WHERE payment_status IN ('pending','Pending','Generated') AND month_year = DATE_FORMAT(CURDATE(), '%Y-%m')");
    }
    $loans = 0.0;
    if (dh_table_exists($pdo, 'accounts_loans')) {
        $loans = (float)admin_count($pdo, 'SELECT COALESCE(SUM(outstanding_balance),0) FROM accounts_loans');
    }

    return [
        'receivables' => $fin['receivables'],
        'payables' => $fin['payables'],
        'expenses_mtd' => $fin['expenses'],
        'expense_count' => $expenseCount,
        'cash' => $fin['cash'],
        'salary_pending' => $salaryPending,
        'loans' => $loans,
        'revenue' => $fin['revenue'],
        'profit' => $fin['profit'],
    ];
}

/** @return list<array<string, mixed>> */
function admin_oversight_recent_orders(PDO $pdo, int $limit = 15): array
{
    if (!dh_table_exists($pdo, 'sales_orders')) {
        return [];
    }
    return $pdo->query(
        'SELECT o.id, o.so_number, o.order_date, o.status, o.total_amount, c.company_name
         FROM sales_orders o
         INNER JOIN sales_customers c ON c.id = o.customer_id
         ORDER BY o.id DESC LIMIT ' . max(1, min(50, $limit))
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function admin_oversight_recent_purchases(PDO $pdo, int $limit = 15): array
{
    if (!dh_table_exists($pdo, 'stock_inward')) {
        return [];
    }
    $payCol = dh_column_exists($pdo, 'stock_inward', 'payment_status') ? 'si.payment_status' : "'—' AS payment_status";
    $amtExpr = dh_column_exists($pdo, 'stock_inward', 'total_amount')
        ? 'si.total_amount'
        : '(si.quantity * si.rate)';

    return $pdo->query(
        "SELECT si.id, si.inward_date, {$amtExpr} AS total_amount, {$payCol}, s.name AS supplier_name
         FROM stock_inward si
         LEFT JOIN suppliers s ON s.id = si.supplier_id
         ORDER BY si.id DESC LIMIT " . max(1, min(50, $limit))
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
