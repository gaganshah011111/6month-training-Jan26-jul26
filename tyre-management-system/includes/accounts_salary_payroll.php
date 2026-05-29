<?php
declare(strict_types=1);

require_once __DIR__ . '/payroll_service.php';
require_once __DIR__ . '/sales_service.php';
require_once __DIR__ . '/accounts_finance.php';

const ACC_SALARY_PAYMENT_MODES = ['Cash', 'UPI', 'NEFT', 'RTGS', 'Bank Transfer', 'Bank', 'Cheque'];

function acc_salary_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS accounts_salary_batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            month_year VARCHAR(7) NOT NULL,
            employee_count INT NOT NULL DEFAULT 0,
            total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            status ENUM('pending','partial','paid') NOT NULL DEFAULT 'pending',
            sent_by VARCHAR(120) NULL,
            sent_at DATETIME NULL,
            generated_by_hr VARCHAR(120) NULL,
            payroll_closed_at DATETIME NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_salary_batch_month (month_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS accounts_salary_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batch_id INT NOT NULL,
            salary_id INT NULL,
            employee_id INT NULL,
            payment_date DATE NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            payment_mode VARCHAR(40) NOT NULL DEFAULT 'Bank',
            reference_no VARCHAR(80) NULL,
            remarks TEXT NULL,
            recorded_by VARCHAR(120) NULL,
            expense_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_salary_pay_batch (batch_id),
            KEY idx_salary_pay_salary (salary_id),
            KEY idx_salary_pay_employee (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $cols = [
        'accounts_batch_id' => 'ALTER TABLE salaries ADD COLUMN accounts_batch_id INT NULL',
        'amount_paid' => 'ALTER TABLE salaries ADD COLUMN amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0',
        'hr_payroll_status' => "ALTER TABLE salaries ADD COLUMN hr_payroll_status VARCHAR(24) NOT NULL DEFAULT 'generated'",
        'verified_at' => 'ALTER TABLE salaries ADD COLUMN verified_at DATETIME NULL',
        'verified_by' => 'ALTER TABLE salaries ADD COLUMN verified_by VARCHAR(120) NULL',
    ];
    foreach ($cols as $col => $sql) {
        if (function_exists('dh_column_exists') && !dh_column_exists($pdo, 'salaries', $col)) {
            $pdo->exec($sql);
        }
    }
    if (function_exists('dh_column_exists') && !dh_column_exists($pdo, 'accounts_salary_batches', 'payroll_closed_at')) {
        $pdo->exec('ALTER TABLE accounts_salary_batches ADD COLUMN payroll_closed_at DATETIME NULL');
    }
    foreach (['salary_id', 'employee_id', 'expense_id'] as $col) {
        if (function_exists('dh_column_exists') && !dh_column_exists($pdo, 'accounts_salary_payments', $col)) {
            if ($col === 'salary_id') {
                $pdo->exec('ALTER TABLE accounts_salary_payments ADD COLUMN salary_id INT NULL AFTER batch_id');
            } elseif ($col === 'employee_id') {
                $pdo->exec('ALTER TABLE accounts_salary_payments ADD COLUMN employee_id INT NULL AFTER salary_id');
            } else {
                $pdo->exec('ALTER TABLE accounts_salary_payments ADD COLUMN expense_id INT NULL');
            }
        }
    }

    acc_salary_backfill_columns($pdo);
}

function acc_salary_backfill_columns(PDO $pdo): void
{
    $pdo->exec(
        "UPDATE salaries SET amount_paid = net_salary
         WHERE COALESCE(amount_paid, 0) < 0.01 AND payment_status = 'paid' AND COALESCE(is_draft, 0) = 0"
    );
    $pdo->exec(
        "UPDATE salaries SET hr_payroll_status = 'draft'
         WHERE COALESCE(is_draft, 0) = 1 AND COALESCE(hr_payroll_status, '') = ''"
    );
    $pdo->exec(
        "UPDATE salaries SET hr_payroll_status = 'sent_to_accounts'
         WHERE COALESCE(is_draft, 0) = 0
           AND (payment_status IN ('sent_to_accounts', 'paid', 'partial')
                OR accounts_batch_id IS NOT NULL)
           AND hr_payroll_status NOT IN ('sent_to_accounts')"
    );
    $pdo->exec(
        "UPDATE salaries SET payment_status = 'partial'
         WHERE COALESCE(is_draft, 0) = 0
           AND amount_paid > 0.01
           AND amount_paid < net_salary - 0.02
           AND payment_status NOT IN ('paid')"
    );
}

function acc_salary_normalize_mode(string $mode): string
{
    $mode = trim($mode);
    $map = ['Bank' => 'Bank Transfer', 'Cheque' => 'Bank Transfer'];
    if (isset($map[$mode])) {
        return $map[$mode];
    }
    if (!in_array($mode, ACC_SALARY_PAYMENT_MODES, true)) {
        return 'Bank Transfer';
    }

    return $mode;
}

/** @return 'unpaid'|'partial'|'paid' */
function acc_salary_employee_pay_status(float $net, float $paid): string
{
    $net = round(max(0, $net), 2);
    $paid = round(max(0, $paid), 2);
    if ($net <= 0.02) {
        return 'paid';
    }
    if ($paid >= $net - 0.02) {
        return 'paid';
    }
    if ($paid > 0.02) {
        return 'partial';
    }

    return 'unpaid';
}

function acc_salary_status_meta(string $status): array
{
    return match ($status) {
        'paid' => ['label' => 'Paid', 'badge' => 'paid'],
        'partial' => ['label' => 'Partial', 'badge' => 'partial'],
        'verified' => ['label' => 'Verified', 'badge' => 'partial'],
        'sent_to_accounts' => ['label' => 'Sent to Accounts', 'badge' => 'partial'],
        default => ['label' => 'Pending', 'badge' => 'unpaid'],
    };
}

function acc_salary_payment_receipt_no(int $paymentId, string $date): string
{
    return 'SAL-RCP-' . date('Ym', strtotime($date)) . '-' . str_pad((string)$paymentId, 5, '0', STR_PAD_LEFT);
}

/** KPIs for a payroll month (Accounts dashboard). */
function acc_salary_dashboard_kpis(PDO $pdo, string $monthYear): array
{
    acc_salary_ensure_schema($pdo);
    if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
        $monthYear = date('Y-m');
    }

    $st = $pdo->prepare(
        "SELECT
            COALESCE(SUM(s.net_salary), 0) AS total_payroll,
            COALESCE(SUM(s.amount_paid), 0) AS paid_salary,
            COALESCE(SUM(GREATEST(s.net_salary - s.amount_paid, 0)), 0) AS pending_salary,
            SUM(CASE WHEN s.amount_paid >= s.net_salary - 0.02 AND s.net_salary > 0 THEN 1 ELSE 0 END) AS employees_paid,
            SUM(CASE WHEN s.net_salary - s.amount_paid > 0.02 THEN 1 ELSE 0 END) AS employees_pending
         FROM salaries s
         WHERE s.month_year = :m
           AND COALESCE(s.is_draft, 0) = 0
           AND s.hr_payroll_status = 'sent_to_accounts'"
    );
    $st->execute(['m' => $monthYear]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $deptSt = $pdo->prepare(
        "SELECT COUNT(DISTINCT COALESCE(NULLIF(TRIM(" . erp_dept_label_sql('d', 'e') . "), ''), 'Unassigned')) AS dept_pending
         FROM salaries s
         INNER JOIN employees e ON e.id = s.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE s.month_year = :m
           AND COALESCE(s.is_draft, 0) = 0
           AND s.hr_payroll_status = 'sent_to_accounts'
           AND s.net_salary - s.amount_paid > 0.02"
    );
    $deptSt->execute(['m' => $monthYear]);
    $deptPending = (int)($deptSt->fetchColumn() ?: 0);

    return [
        'month_year' => $monthYear,
        'total_payroll' => round((float)($row['total_payroll'] ?? 0), 2),
        'paid_salary' => round((float)($row['paid_salary'] ?? 0), 2),
        'pending_salary' => round((float)($row['pending_salary'] ?? 0), 2),
        'employees_paid' => (int)($row['employees_paid'] ?? 0),
        'employees_pending' => (int)($row['employees_pending'] ?? 0),
        'departments_pending' => $deptPending,
    ];
}

/** @return list<array<string, mixed>> */
function acc_salary_department_summary(PDO $pdo, string $monthYear): array
{
    acc_salary_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT COALESCE(NULLIF(TRIM(" . erp_dept_label_sql('d', 'e') . "), ''), 'Unassigned') AS department,
                COUNT(*) AS employees,
                COALESCE(SUM(s.net_salary), 0) AS total_payroll,
                COALESCE(SUM(s.amount_paid), 0) AS paid,
                COALESCE(SUM(GREATEST(s.net_salary - s.amount_paid, 0)), 0) AS pending
         FROM salaries s
         INNER JOIN employees e ON e.id = s.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE s.month_year = :m
           AND COALESCE(s.is_draft, 0) = 0
           AND s.hr_payroll_status = 'sent_to_accounts'
         GROUP BY department
         ORDER BY pending DESC, department ASC"
    );
    $st->execute(['m' => $monthYear]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Department-wise batch rows for Accounts table.
 *
 * @param array{month_year?:string,department?:string,status?:string,q?:string} $filters
 * @return list<array<string, mixed>>
 */
function acc_salary_list_department_batches(PDO $pdo, array $filters = []): array
{
    acc_salary_ensure_schema($pdo);
    $sql = "SELECT s.month_year,
            COALESCE(NULLIF(TRIM(" . erp_dept_label_sql('d', 'e') . "), ''), 'Unassigned') AS department,
            COUNT(*) AS employees,
            COALESCE(SUM(s.net_salary), 0) AS total_payroll,
            COALESCE(SUM(s.amount_paid), 0) AS paid,
            COALESCE(SUM(GREATEST(s.net_salary - s.amount_paid, 0)), 0) AS pending,
            MAX(b.generated_by_hr) AS generated_by_hr,
            MAX(b.sent_by) AS sent_by
         FROM salaries s
         INNER JOIN employees e ON e.id = s.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         LEFT JOIN accounts_salary_batches b ON b.month_year = s.month_year
         WHERE COALESCE(s.is_draft, 0) = 0
           AND s.hr_payroll_status = 'sent_to_accounts'";
    $params = [];

    if (!empty($filters['month_year']) && preg_match('/^\d{4}-\d{2}$/', (string)$filters['month_year'])) {
        $sql .= ' AND s.month_year = :m';
        $params['m'] = $filters['month_year'];
    }
    if (!empty($filters['department'])) {
        $sql .= " AND COALESCE(NULLIF(TRIM(" . erp_dept_label_sql('d', 'e') . "), ''), 'Unassigned') = :dept";
        $params['dept'] = $filters['department'];
    }
    if (!empty($filters['q'])) {
        $sql .= ' AND (e.full_name LIKE :q OR e.employee_code LIKE :q)';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sql .= ' GROUP BY s.month_year, department';

    if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'partial', 'paid'], true)) {
        $having = match ($filters['status']) {
            'paid' => ' HAVING pending <= 0.02 AND total_payroll > 0',
            'partial' => ' HAVING paid > 0.02 AND pending > 0.02',
            default => ' HAVING pending > 0.02',
        };
        $sql .= $having;
    }

    $sql .= ' ORDER BY s.month_year DESC, department ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $total = round((float)$row['total_payroll'], 2);
        $paid = round((float)$row['paid'], 2);
        $pending = round((float)$row['pending'], 2);
        $row['total_payroll'] = $total;
        $row['paid'] = $paid;
        $row['pending'] = $pending;
        $row['status'] = acc_salary_derive_batch_status($total, $paid);
        $row['status_meta'] = acc_salary_status_meta($row['status']);
        $row['month_label'] = payroll_format_month_label((string)$row['month_year']);
        $row['progress_pct'] = $total > 0 ? min(100, round(($paid / $total) * 100)) : 0;
    }
    unset($row);

    return $rows;
}

/** @return list<array<string, mixed>> */
function acc_salary_dashboard_alerts(PDO $pdo, string $monthYear): array
{
    $kpis = acc_salary_dashboard_kpis($pdo, $monthYear);
    $alerts = [];
    if ($kpis['employees_pending'] > 0) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Salary pending',
            'detail' => (int)$kpis['employees_pending'] . ' employee(s) · ' . sales_format_money((float)$kpis['pending_salary']),
        ];
    }
    if ($kpis['total_payroll'] > 0 && $kpis['pending_salary'] > 0.02) {
        $alerts[] = [
            'type' => 'info',
            'title' => 'Payroll awaiting payment',
            'detail' => payroll_format_month_label($monthYear),
        ];
    }

    return $alerts;
}

function acc_salary_derive_batch_status(float $total, float $paid): string
{
    if ($total <= 0.02) {
        return 'pending';
    }
    if ($paid >= $total - 0.02) {
        return 'paid';
    }
    if ($paid > 0.02) {
        return 'partial';
    }

    return 'pending';
}

function acc_salary_refresh_month_batch(PDO $pdo, string $month): ?int
{
    acc_salary_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT COUNT(*) AS employee_count,
                COALESCE(SUM(net_salary), 0) AS total_amount,
                COALESCE(SUM(amount_paid), 0) AS paid_amount
         FROM salaries
         WHERE month_year = :m
           AND COALESCE(is_draft, 0) = 0
           AND hr_payroll_status = 'sent_to_accounts'"
    );
    $st->execute(['m' => $month]);
    $agg = $st->fetch(PDO::FETCH_ASSOC);
    if (!$agg || (int)$agg['employee_count'] < 1) {
        return null;
    }

    $total = round((float)$agg['total_amount'], 2);
    $paid = round((float)$agg['paid_amount'], 2);
    $status = acc_salary_derive_batch_status($total, $paid);
    $closed = $status === 'paid' ? date('Y-m-d H:i:s') : null;

    $pdo->prepare(
        'INSERT INTO accounts_salary_batches (month_year, employee_count, total_amount, paid_amount, status, payroll_closed_at)
         VALUES (:m, :c, :t, :p, :st, :closed)
         ON DUPLICATE KEY UPDATE
            employee_count = VALUES(employee_count),
            total_amount = VALUES(total_amount),
            paid_amount = VALUES(paid_amount),
            status = VALUES(status),
            payroll_closed_at = CASE WHEN VALUES(status) = \'paid\' THEN COALESCE(payroll_closed_at, VALUES(payroll_closed_at)) ELSE NULL END'
    )->execute([
        'm' => $month,
        'c' => (int)$agg['employee_count'],
        't' => $total,
        'p' => $paid,
        'st' => $status,
        'closed' => $closed,
    ]);

    $bid = (int)$pdo->query("SELECT id FROM accounts_salary_batches WHERE month_year = " . $pdo->quote($month) . ' LIMIT 1')->fetchColumn();
    if ($bid > 0) {
        $pdo->prepare('UPDATE salaries SET accounts_batch_id = :b WHERE month_year = :m AND hr_payroll_status = \'sent_to_accounts\'')
            ->execute(['b' => $bid, 'm' => $month]);
    }

    return $bid > 0 ? $bid : null;
}

/** HR verifies all generated payroll for month. */
function payroll_verify_month(PDO $pdo, string $month, ?string $by = null): int
{
    acc_salary_ensure_schema($pdo);
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new InvalidArgumentException('Invalid payroll month.');
    }
    $who = $by ?: (string)(current_user()['full_name'] ?? 'HR');
    $st = $pdo->prepare(
        "UPDATE salaries SET hr_payroll_status = 'verified', verified_at = NOW(), verified_by = :by
         WHERE month_year = :m AND COALESCE(is_draft, 0) = 0 AND hr_payroll_status = 'generated'"
    );
    $st->execute(['m' => $month, 'by' => $who]);

    return $st->rowCount();
}

function payroll_month_counts(PDO $pdo, string $month): array
{
    acc_salary_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT
            SUM(CASE WHEN COALESCE(is_draft,0)=1 THEN 1 ELSE 0 END) AS drafts,
            SUM(CASE WHEN COALESCE(is_draft,0)=0 AND hr_payroll_status='generated' THEN 1 ELSE 0 END) AS generated,
            SUM(CASE WHEN hr_payroll_status='verified' THEN 1 ELSE 0 END) AS verified,
            SUM(CASE WHEN hr_payroll_status='sent_to_accounts' THEN 1 ELSE 0 END) AS sent
         FROM salaries WHERE month_year = :m"
    );
    $st->execute(['m' => $month]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'drafts' => (int)($row['drafts'] ?? 0),
        'generated' => (int)($row['generated'] ?? 0),
        'verified' => (int)($row['verified'] ?? 0),
        'sent' => (int)($row['sent'] ?? 0),
    ];
}

function payroll_send_month_to_accounts(PDO $pdo, string $month, ?string $sentBy = null): array
{
    acc_salary_ensure_schema($pdo);
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new InvalidArgumentException('Invalid payroll month.');
    }

    $counts = payroll_month_counts($pdo, $month);
    if ((int)$counts['verified'] < 1 && (int)$counts['generated'] > 0) {
        throw new RuntimeException('Verify payroll before sending to Accounts.');
    }
    if ((int)$counts['verified'] < 1 && (int)$counts['sent'] < 1 && (int)$counts['generated'] < 1) {
        throw new RuntimeException('No payroll to send. Generate and verify payroll first.');
    }

    $who = $sentBy ?: (string)(current_user()['full_name'] ?? 'HR');

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "UPDATE salaries SET hr_payroll_status = 'sent_to_accounts',
                    payment_status = CASE
                        WHEN amount_paid >= net_salary - 0.02 THEN 'paid'
                        WHEN amount_paid > 0.02 THEN 'partial'
                        ELSE 'unpaid'
                    END
             WHERE month_year = :m AND COALESCE(is_draft, 0) = 0
               AND hr_payroll_status IN ('verified', 'generated', 'sent_to_accounts')"
        )->execute(['m' => $month]);

        $pdo->prepare(
            'INSERT INTO accounts_salary_batches (month_year, employee_count, total_amount, paid_amount, status, sent_by, sent_at, generated_by_hr)
             SELECT :m, COUNT(*), COALESCE(SUM(net_salary),0), COALESCE(SUM(amount_paid),0), \'pending\', :sent_by, NOW(), :gen_by
             FROM salaries WHERE month_year = :m2 AND COALESCE(is_draft,0)=0 AND hr_payroll_status = \'sent_to_accounts\'
             ON DUPLICATE KEY UPDATE sent_by = VALUES(sent_by), sent_at = COALESCE(sent_at, NOW()), generated_by_hr = COALESCE(generated_by_hr, VALUES(generated_by_hr))'
        )->execute(['m' => $month, 'm2' => $month, 'sent_by' => $who, 'gen_by' => $who]);

        acc_salary_refresh_month_batch($pdo, $month);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $batch = payroll_month_accounts_batch($pdo, $month);
    $kpis = acc_salary_dashboard_kpis($pdo, $month);

    return [
        'batch_id' => (int)($batch['id'] ?? 0),
        'employee_count' => (int)($batch['employee_count'] ?? $kpis['employees_paid'] + $kpis['employees_pending']),
        'total_amount' => (float)($batch['total_amount'] ?? $kpis['total_payroll']),
        'month_year' => $month,
    ];
}

/**
 * Employee-wise payroll rows for Accounts (primary work queue).
 *
 * @param array{status?:string,q?:string,employee_id?:string} $filters
 * @return list<array<string, mixed>>
 */
function acc_salary_list_employees(PDO $pdo, string $month, string $department = '', array $filters = []): array
{
    acc_salary_ensure_schema($pdo);
    $sql = "SELECT s.*, e.id AS emp_id, e.employee_code, e.full_name, e.designation, e.contact_no AS phone, e.email,
            " . erp_dept_label_sql('d', 'e') . " AS dept_label,
            " . erp_desig_label_sql('des', 'e') . " AS desig_label
         FROM salaries s
         INNER JOIN employees e ON e.id = s.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         LEFT JOIN designations des ON des.id = e.designation_id
         WHERE s.month_year = :m
           AND COALESCE(s.is_draft, 0) = 0
           AND s.hr_payroll_status = 'sent_to_accounts'";
    $params = ['m' => $month];

    if ($department !== '') {
        $sql .= " AND COALESCE(NULLIF(TRIM(" . erp_dept_label_sql('d', 'e') . "), ''), 'Unassigned') = :dept";
        $params['dept'] = $department;
    }

    if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'partial', 'paid'], true)) {
        if ($filters['status'] === 'paid') {
            $sql .= ' AND s.amount_paid >= s.net_salary - 0.02';
        } elseif ($filters['status'] === 'partial') {
            $sql .= ' AND s.amount_paid > 0.02 AND s.amount_paid < s.net_salary - 0.02';
        } else {
            $sql .= ' AND s.amount_paid < 0.02';
        }
    }
    if (!empty($filters['q'])) {
        $sql .= ' AND (e.full_name LIKE :q OR e.employee_code LIKE :q)';
        $params['q'] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['employee_id'])) {
        $sql .= ' AND e.employee_code LIKE :eid';
        $params['eid'] = '%' . trim((string)$filters['employee_id']) . '%';
    }
    $sql .= ' ORDER BY
        CASE
            WHEN s.amount_paid < 0.02 THEN 0
            WHEN s.amount_paid < s.net_salary - 0.02 THEN 1
            ELSE 2
        END,
        (s.net_salary - s.amount_paid) DESC,
        e.full_name ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $net = round((float)($row['net_salary'] ?? 0), 2);
        $paid = round((float)($row['amount_paid'] ?? 0), 2);
        $row['pending'] = round(max(0, $net - $paid), 2);
        $row['pay_status'] = acc_salary_employee_pay_status($net, $paid);
        $row['pay_status_meta'] = acc_salary_status_meta($row['pay_status']);
    }
    unset($row);

    return $rows;
}

/** @return array<string, mixed>|null */
function acc_salary_get_employee_row(PDO $pdo, int $salaryId): ?array
{
    acc_salary_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT s.*, e.employee_code, e.full_name, e.contact_no AS phone, e.email, e.designation,
            " . erp_dept_label_sql('d', 'e') . " AS dept_label,
            " . erp_desig_label_sql('des', 'e') . " AS desig_label
         FROM salaries s
         INNER JOIN employees e ON e.id = s.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         LEFT JOIN designations des ON des.id = e.designation_id
         WHERE s.id = :id LIMIT 1"
    );
    $st->execute(['id' => $salaryId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $net = round((float)($row['net_salary'] ?? 0), 2);
    $paid = round((float)($row['amount_paid'] ?? 0), 2);
    $row['pending'] = round(max(0, $net - $paid), 2);
    $row['pay_status'] = acc_salary_employee_pay_status($net, $paid);

    return $row;
}

/** @return list<array<string, mixed>> */
function acc_salary_employee_payments(PDO $pdo, int $salaryId): array
{
    acc_salary_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM accounts_salary_payments WHERE salary_id = :id ORDER BY payment_date DESC, id DESC'
    );
    $st->execute(['id' => $salaryId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function acc_salary_post_ledger(PDO $pdo, int $paymentId, array $paymentRow, array $salaryRow): int
{
    require_once __DIR__ . '/accounts_expenses.php';
    return acc_expense_create_from_salary($pdo, $paymentId, $paymentRow, $salaryRow);
}

/**
 * Record per-employee salary payment.
 *
 * @return array{payment_id:int,salary:array<string,mixed>,batch:array<string,mixed>|null}
 */
function acc_salary_record_employee_payment(PDO $pdo, int $salaryId, array $data): array
{
    acc_salary_ensure_schema($pdo);
    $salary = acc_salary_get_employee_row($pdo, $salaryId);
    if (!$salary) {
        throw new InvalidArgumentException('Salary record not found.');
    }
    if ((string)($salary['hr_payroll_status'] ?? '') !== 'sent_to_accounts') {
        throw new RuntimeException('Payroll must be sent from HR before payment.');
    }

    $amount = round((float)($data['amount'] ?? 0), 2);
    if ($amount <= 0) {
        throw new InvalidArgumentException('Payment amount must be greater than zero.');
    }
    $remaining = (float)($salary['pending'] ?? 0);
    if ($amount > $remaining + 0.02) {
        throw new InvalidArgumentException('Amount exceeds remaining salary (' . sales_format_money($remaining) . ').');
    }

    $payDate = (string)($data['payment_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payDate)) {
        throw new InvalidArgumentException('Valid payment date is required.');
    }
    $mode = acc_salary_normalize_mode((string)($data['payment_mode'] ?? 'Bank Transfer'));
    $ref = trim((string)($data['reference_no'] ?? '')) ?: null;
    $remarks = trim((string)($data['remarks'] ?? '')) ?: null;
    $by = (string)(current_user()['full_name'] ?? current_user()['username'] ?? 'Accounts');
    $month = (string)$salary['month_year'];
    $batchId = (int)($salary['accounts_batch_id'] ?? 0);
    if ($batchId < 1) {
        $batchId = (int)(acc_salary_refresh_month_batch($pdo, $month) ?? 0);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO accounts_salary_payments (batch_id, salary_id, employee_id, payment_date, amount, payment_mode, reference_no, remarks, recorded_by)
             VALUES (:b,:sid,:eid,:d,:a,:m,:r,:rm,:by)'
        )->execute([
            'b' => $batchId,
            'sid' => $salaryId,
            'eid' => (int)$salary['employee_id'],
            'd' => $payDate,
            'a' => $amount,
            'm' => $mode,
            'r' => $ref,
            'rm' => $remarks,
            'by' => $by,
        ]);
        $paymentId = (int)$pdo->lastInsertId();

        $newPaid = round((float)($salary['amount_paid'] ?? 0) + $amount, 2);
        $net = round((float)($salary['net_salary'] ?? 0), 2);
        $payStatus = acc_salary_employee_pay_status($net, $newPaid);
        $pdo->prepare(
            'UPDATE salaries SET amount_paid = :p, payment_status = :st, paid_at = IF(:st_chk = \'paid\', COALESCE(paid_at, NOW()), paid_at) WHERE id = :id'
        )->execute(['p' => $newPaid, 'st' => $payStatus, 'st_chk' => $payStatus, 'id' => $salaryId]);

        $paymentRow = [
            'amount' => $amount,
            'payment_date' => $payDate,
            'payment_mode' => $mode,
            'reference_no' => $ref,
        ];
        $expenseId = acc_salary_post_ledger($pdo, $paymentId, $paymentRow, $salary);
        $pdo->prepare('UPDATE accounts_salary_payments SET expense_id = :e WHERE id = :id')
            ->execute(['e' => $expenseId, 'id' => $paymentId]);

        acc_salary_refresh_month_batch($pdo, $month);
        $pdo->commit();
        if (function_exists('acc_treasury_mirror_salary_payment')) {
            require_once __DIR__ . '/accounts_treasury.php';
            try {
                acc_treasury_mirror_salary_payment($pdo, $paymentId);
            } catch (Throwable) {
                // treasury mirror must not block payment
            }
        }
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $salary = acc_salary_get_employee_row($pdo, $salaryId);
    $batch = payroll_month_accounts_batch($pdo, $month);

    return [
        'payment_id' => $paymentId,
        'salary' => $salary ?: [],
        'batch' => $batch,
        'receipt_url' => route_url('accounts/salary-payment-receipt', ['id' => $paymentId]),
    ];
}

/** @deprecated Use acc_salary_record_employee_payment */
function acc_salary_record_payment(PDO $pdo, int $batchId, array $data): array
{
    throw new RuntimeException('Use per-employee Pay Salary from payroll details.');
}

function acc_salary_get_payment(PDO $pdo, int $paymentId): ?array
{
    acc_salary_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT p.*, s.month_year, s.net_salary, s.amount_paid AS salary_paid, e.full_name, e.employee_code,
            " . erp_dept_label_sql('d', 'e') . " AS dept_label
         FROM accounts_salary_payments p
         INNER JOIN salaries s ON s.id = p.salary_id
         INNER JOIN employees e ON e.id = p.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE p.id = :id LIMIT 1"
    );
    $st->execute(['id' => $paymentId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** Legacy sync — refresh batches for months already sent to Accounts. */
function acc_salary_sync_batches_from_hr(PDO $pdo, ?string $onlyMonth = null): int
{
    acc_salary_ensure_schema($pdo);
    $sql = "SELECT DISTINCT month_year FROM salaries WHERE hr_payroll_status = 'sent_to_accounts'";
    $params = [];
    if ($onlyMonth !== null && preg_match('/^\d{4}-\d{2}$/', $onlyMonth)) {
        $sql .= ' AND month_year = :m';
        $params['m'] = $onlyMonth;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $month) {
        if (acc_salary_refresh_month_batch($pdo, (string)$month) !== null) {
            $n++;
        }
    }

    return $n;
}

function acc_salary_list_batches(PDO $pdo, array $filters = []): array
{
    return acc_salary_list_department_batches($pdo, $filters);
}

function acc_salary_get_batch(PDO $pdo, int $batchId): ?array
{
    $st = $pdo->prepare('SELECT * FROM accounts_salary_batches WHERE id = :id LIMIT 1');
    $st->execute(['id' => $batchId]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function acc_salary_batch_employees(PDO $pdo, int $batchId): array
{
    $batch = acc_salary_get_batch($pdo, $batchId);
    if (!$batch) {
        return [];
    }

    return acc_salary_list_employees($pdo, (string)$batch['month_year'], '', []);
}

function acc_salary_batch_payments(PDO $pdo, int $batchId): array
{
    $st = $pdo->prepare('SELECT * FROM accounts_salary_payments WHERE batch_id = :id ORDER BY payment_date DESC, id DESC');
    $st->execute(['id' => $batchId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function payroll_month_ready_to_send_count(PDO $pdo, string $month): int
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM salaries
         WHERE month_year = :m AND COALESCE(is_draft,0) = 0 AND hr_payroll_status = 'verified'"
    );
    $st->execute(['m' => $month]);

    return (int)$st->fetchColumn();
}
