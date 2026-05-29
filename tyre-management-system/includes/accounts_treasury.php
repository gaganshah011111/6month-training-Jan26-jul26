<?php
declare(strict_types=1);

require_once __DIR__ . '/sales_service.php';
require_once __DIR__ . '/erp_export.php';

const ACC_TREASURY_TX_TYPES = [
    'Opening Balance',
    'Customer Payment',
    'Supplier Payment',
    'Salary Payment',
    'Expense',
    'Loan Received',
    'Loan Repayment',
    'Manual Adjustment',
];

/** Matches DECIMAL(18,2) column limit (~₹99,99,99,99,99,99,999.99). */
const ACC_TREASURY_MAX_AMOUNT = 9999999999999999.99;

const ACC_LOAN_SOURCES = [
    'Bank Loan',
    'Director Loan',
    'Investor Funding',
    'Business Loan',
    'Personal Loan',
    'Other',
];

function acc_treasury_current_user(): string
{
    $u = current_user();

    return (string)($u['full_name'] ?? $u['username'] ?? $u['email'] ?? 'ERP User');
}

function acc_treasury_can_manage(): bool
{
    return has_role(['Accounts Manager', 'Super Admin', 'Admin']);
}

function acc_treasury_can_adjust(): bool
{
    return has_role(['Accounts Manager', 'Super Admin']);
}

/** @return list<string>|null null = all types */
function acc_treasury_allowed_tx_types(): ?array
{
    if (has_role(['Accounts Manager', 'Super Admin', 'Admin'])) {
        return null;
    }
    if (has_role('HR Manager')) {
        return ['Salary Payment'];
    }
    if (has_role('Sales Manager')) {
        return ['Customer Payment'];
    }

    return [];
}

function acc_treasury_is_cash_mode(string $mode): bool
{
    return strcasecmp(trim($mode), 'Cash') === 0;
}

/** @return array{cash: float, bank: float} */
function acc_treasury_split_amount(string $mode, float $amount): array
{
    if (acc_treasury_is_cash_mode($mode)) {
        return ['cash' => round($amount, 2), 'bank' => 0.0];
    }

    return ['cash' => 0.0, 'bank' => round($amount, 2)];
}

function acc_treasury_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS accounts_treasury_opening (
            id INT AUTO_INCREMENT PRIMARY KEY,
            opening_cash DECIMAL(18,2) NOT NULL DEFAULT 0,
            opening_bank DECIMAL(18,2) NOT NULL DEFAULT 0,
            effective_date DATE NOT NULL,
            entered_by VARCHAR(120) NULL,
            is_locked TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS accounts_treasury_ledger (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tx_code VARCHAR(32) NOT NULL,
            tx_date DATE NOT NULL,
            tx_type VARCHAR(40) NOT NULL,
            party VARCHAR(150) NULL,
            reference_no VARCHAR(80) NULL,
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            direction ENUM('credit','debit') NOT NULL,
            cash_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            bank_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            payment_mode VARCHAR(30) NULL,
            balance_before DECIMAL(18,2) NOT NULL DEFAULT 0,
            balance_after DECIMAL(18,2) NOT NULL DEFAULT 0,
            cash_balance_after DECIMAL(18,2) NOT NULL DEFAULT 0,
            bank_balance_after DECIMAL(18,2) NOT NULL DEFAULT 0,
            tx_status VARCHAR(20) NOT NULL DEFAULT 'Completed',
            source_module VARCHAR(40) NULL,
            source_type VARCHAR(32) NULL,
            source_id INT NULL,
            loan_id INT NULL,
            remarks VARCHAR(255) NULL,
            created_by VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_treasury_tx_code (tx_code),
            UNIQUE KEY uq_treasury_source (source_module, source_type, source_id),
            INDEX idx_treasury_date (tx_date),
            INDEX idx_treasury_type (tx_type),
            INDEX idx_treasury_loan (loan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS accounts_loans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_code VARCHAR(32) NOT NULL,
            loan_source VARCHAR(60) NOT NULL,
            lender_name VARCHAR(120) NULL,
            principal_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            interest_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
            loan_date DATE NOT NULL,
            due_date DATE NULL,
            remarks TEXT NULL,
            document_path VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Active',
            repaid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            ledger_entry_id INT NULL,
            created_by VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_loan_code (loan_code),
            INDEX idx_loan_status (status),
            INDEX idx_loan_due (due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS accounts_loan_repayments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            amount DECIMAL(18,2) NOT NULL,
            payment_date DATE NOT NULL,
            payment_mode VARCHAR(30) NOT NULL DEFAULT 'Bank',
            reference_no VARCHAR(80) NULL,
            remarks VARCHAR(255) NULL,
            ledger_entry_id INT NULL,
            created_by VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_loan_repay_loan (loan_id),
            INDEX idx_loan_repay_date (payment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    acc_treasury_upgrade_amount_columns($pdo);
}

function acc_treasury_amount_columns_ready(PDO $pdo): bool
{
    if (!function_exists('dh_table_exists') || !dh_table_exists($pdo, 'accounts_treasury_ledger')) {
        return false;
    }
    $row = $pdo->query("SHOW COLUMNS FROM accounts_treasury_ledger WHERE Field = 'amount'")->fetch(PDO::FETCH_ASSOC);

    return $row && stripos((string)($row['Type'] ?? ''), 'decimal(18)') !== false;
}

function acc_treasury_upgrade_amount_columns(PDO $pdo): void
{
    static $done = false;
    if ($done || $pdo->inTransaction()) {
        return;
    }
    if (acc_treasury_amount_columns_ready($pdo)) {
        $done = true;

        return;
    }
    $done = true;

    $alters = [
        'accounts_treasury_opening' => ['opening_cash', 'opening_bank'],
        'accounts_treasury_ledger' => [
            'amount', 'cash_amount', 'bank_amount',
            'balance_before', 'balance_after', 'cash_balance_after', 'bank_balance_after',
        ],
        'accounts_loans' => ['principal_amount', 'repaid_amount'],
        'accounts_loan_repayments' => ['amount'],
    ];

    foreach ($alters as $table => $columns) {
        if (!function_exists('dh_table_exists') || !dh_table_exists($pdo, $table)) {
            continue;
        }
        foreach ($columns as $col) {
            try {
                $pdo->exec(
                    "ALTER TABLE `{$table}` MODIFY `{$col}` DECIMAL(18,2) NOT NULL DEFAULT 0"
                );
            } catch (Throwable) {
                try {
                    $pdo->exec("ALTER TABLE `{$table}` MODIFY `{$col}` DECIMAL(18,2) NOT NULL");
                } catch (Throwable) {
                    // Column may already be DECIMAL(18,2) or table mid-migration.
                }
            }
        }
    }
}

function acc_treasury_get_opening(PDO $pdo): ?array
{
    acc_treasury_ensure_schema($pdo);
    $row = $pdo->query('SELECT * FROM accounts_treasury_opening ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function acc_treasury_save_opening(PDO $pdo, array $data): void
{
    if (!acc_treasury_can_manage()) {
        throw new RuntimeException('You do not have permission to set opening balance.');
    }
    acc_treasury_ensure_schema($pdo);
    $cash = round((float)($data['opening_cash'] ?? 0), 2);
    $bank = round((float)($data['opening_bank'] ?? 0), 2);
    $date = trim((string)($data['effective_date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid effective date is required.');
    }
    $by = acc_treasury_current_user();
    $existing = acc_treasury_get_opening($pdo);

    if ($existing && !empty($existing['is_locked']) && !has_role(['Super Admin'])) {
        throw new RuntimeException('Opening balance is locked. Contact Super Admin to change.');
    }

    if ($existing) {
        $pdo->prepare(
            'UPDATE accounts_treasury_opening SET opening_cash = :c, opening_bank = :b, effective_date = :d, entered_by = :by, is_locked = 1 WHERE id = :id'
        )->execute(['c' => $cash, 'b' => $bank, 'd' => $date, 'by' => $by, 'id' => (int)$existing['id']]);
        $pdo->prepare(
            "DELETE FROM accounts_treasury_ledger WHERE source_module = 'treasury' AND source_type = 'opening'"
        )->execute();
    } else {
        $pdo->prepare(
            'INSERT INTO accounts_treasury_opening (opening_cash, opening_bank, effective_date, entered_by, is_locked) VALUES (:c,:b,:d,:by,1)'
        )->execute(['c' => $cash, 'b' => $bank, 'd' => $date, 'by' => $by]);
    }

    if ($cash > 0) {
        acc_treasury_post_entry($pdo, [
            'tx_date' => $date,
            'tx_type' => 'Opening Balance',
            'party' => 'Company Cash',
            'reference_no' => 'OPEN-CASH',
            'amount' => $cash,
            'direction' => 'credit',
            'payment_mode' => 'Cash',
            'source_module' => 'treasury',
            'source_type' => 'opening_cash',
            'source_id' => 1,
            'remarks' => 'Opening cash balance',
            'created_by' => $by,
        ], true);
    }
    if ($bank > 0) {
        acc_treasury_post_entry($pdo, [
            'tx_date' => $date,
            'tx_type' => 'Opening Balance',
            'party' => 'Company Bank',
            'reference_no' => 'OPEN-BANK',
            'amount' => $bank,
            'direction' => 'credit',
            'payment_mode' => 'Bank',
            'source_module' => 'treasury',
            'source_type' => 'opening_bank',
            'source_id' => 1,
            'remarks' => 'Opening bank balance',
            'created_by' => $by,
        ], true);
    }
    acc_treasury_rebuild_balances($pdo);
}

function acc_treasury_next_tx_code(PDO $pdo): string
{
    $prefix = 'TRX-' . date('Ymd') . '-';
    $st = $pdo->prepare('SELECT COUNT(*) FROM accounts_treasury_ledger WHERE tx_code LIKE :p');
    $st->execute(['p' => $prefix . '%']);
    $n = (int)$st->fetchColumn() + 1;

    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function acc_treasury_next_loan_code(PDO $pdo): string
{
    $prefix = 'LOAN-' . date('Ymd') . '-';
    $st = $pdo->prepare('SELECT COUNT(*) FROM accounts_loans WHERE loan_code LIKE :p');
    $st->execute(['p' => $prefix . '%']);
    $n = (int)$st->fetchColumn() + 1;

    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/**
 * Post treasury ledger entry (idempotent when source_module/type/id set).
 *
 * @param array<string, mixed> $data
 */
function acc_treasury_post_entry(PDO $pdo, array $data, bool $forceReplace = false): int
{
    if (!$pdo->inTransaction()) {
        acc_treasury_ensure_schema($pdo);
    }
    $module = trim((string)($data['source_module'] ?? ''));
    $type = trim((string)($data['source_type'] ?? ''));
    $sourceId = (int)($data['source_id'] ?? 0);

    if ($module !== '' && $type !== '' && $sourceId > 0) {
        $chk = $pdo->prepare(
            'SELECT id FROM accounts_treasury_ledger WHERE source_module = :m AND source_type = :t AND source_id = :i LIMIT 1'
        );
        $chk->execute(['m' => $module, 't' => $type, 'i' => $sourceId]);
        $existingId = (int)$chk->fetchColumn();
        if ($existingId > 0) {
            if (!$forceReplace) {
                return $existingId;
            }
            $pdo->prepare('DELETE FROM accounts_treasury_ledger WHERE id = :id')->execute(['id' => $existingId]);
        }
    }

    $amount = round(max(0, (float)($data['amount'] ?? 0)), 2);
    $direction = strtolower((string)($data['direction'] ?? 'credit')) === 'debit' ? 'debit' : 'credit';
    $mode = trim((string)($data['payment_mode'] ?? 'Bank')) ?: 'Bank';
    $split = acc_treasury_split_amount($mode, $amount);
    if (!empty($data['cash_amount']) || !empty($data['bank_amount'])) {
        $split['cash'] = round((float)($data['cash_amount'] ?? 0), 2);
        $split['bank'] = round((float)($data['bank_amount'] ?? 0), 2);
    }

    $st = $pdo->prepare(
        'INSERT INTO accounts_treasury_ledger (
            tx_code, tx_date, tx_type, party, reference_no, amount, direction,
            cash_amount, bank_amount, payment_mode, tx_status,
            source_module, source_type, source_id, loan_id, remarks, created_by
        ) VALUES (
            :code, :dt, :tt, :party, :ref, :amt, :dir,
            :cash, :bank, :mode, :st,
            :mod, :stype, :sid, :lid, :rm, :by
        )'
    );
    $st->execute([
        'code' => (string)($data['tx_code'] ?? acc_treasury_next_tx_code($pdo)),
        'dt' => (string)($data['tx_date'] ?? date('Y-m-d')),
        'tt' => (string)($data['tx_type'] ?? 'Manual Adjustment'),
        'party' => trim((string)($data['party'] ?? '')) ?: null,
        'ref' => trim((string)($data['reference_no'] ?? '')) ?: null,
        'amt' => $amount,
        'dir' => $direction,
        'cash' => $split['cash'],
        'bank' => $split['bank'],
        'mode' => $mode,
        'st' => (string)($data['tx_status'] ?? 'Completed'),
        'mod' => $module ?: null,
        'stype' => $type ?: null,
        'sid' => $sourceId > 0 ? $sourceId : null,
        'lid' => (int)($data['loan_id'] ?? 0) ?: null,
        'rm' => trim((string)($data['remarks'] ?? '')) ?: null,
        'by' => (string)($data['created_by'] ?? acc_treasury_current_user()),
    ]);

    return (int)$pdo->lastInsertId();
}

function acc_treasury_normalize_amount(float|string|null $amount): float
{
    if (is_string($amount)) {
        $amount = str_replace([',', ' '], '', trim($amount));
    }

    return round((float)$amount, 2);
}

function acc_treasury_validate_amount(float|string|null $amount, string $label = 'Amount'): float
{
    $amount = acc_treasury_normalize_amount($amount);
    if ($amount <= 0) {
        throw new InvalidArgumentException($label . ' must be greater than zero.');
    }
    $max = number_format(ACC_TREASURY_MAX_AMOUNT, 2, '.', '');
    $amt = number_format($amount, 2, '.', '');
    if (function_exists('bccomp')) {
        if (bccomp($amt, $max, 2) === 1) {
            throw new InvalidArgumentException($label . ' exceeds maximum allowed (₹' . number_format((float)$max, 2) . ').');
        }
    } elseif ($amount > ACC_TREASURY_MAX_AMOUNT) {
        throw new InvalidArgumentException($label . ' exceeds maximum allowed (₹' . number_format(ACC_TREASURY_MAX_AMOUNT, 2) . ').');
    }

    return $amount;
}

function acc_treasury_format_money(float $n): string
{
    return sales_format_money(round($n, 2));
}

/** @return array{short: string, full: string} */
function acc_treasury_money_display(float $n): array
{
    $n = round($n, 2);
    $neg = $n < 0;
    $abs = abs($n);
    $full = ($neg ? '−' : '') . '₹' . number_format($abs, 2);

    if ($abs >= 10000000) {
        $short = '₹' . number_format($abs / 10000000, 2) . ' Cr';
    } elseif ($abs >= 100000) {
        $short = '₹' . number_format($abs / 100000, 2) . ' L';
    } elseif ($abs >= 1000) {
        $short = '₹' . number_format($abs / 1000, 2) . ' K';
    } else {
        $short = '₹' . number_format($abs, 2);
    }

    return [
        'short' => ($neg ? '−' : '') . $short,
        'full' => $full,
    ];
}

function acc_treasury_resolve_party(?string $party, ?string $fallback = null): string
{
    $party = trim((string)$party);
    if ($party !== '') {
        return $party;
    }

    return trim((string)$fallback);
}

function acc_treasury_display_party(?string $party, ?string $fallback = null): string
{
    $label = acc_treasury_resolve_party($party, $fallback);

    return $label !== '' ? $label : '—';
}

function acc_treasury_rebuild_balances(PDO $pdo): void
{
    acc_treasury_rebuild_balances_from($pdo, 0);
}

/** Recalculate running balances from a ledger row onward (fast for new tail entries). */
/** Update balance for one new tail ledger row (fast path for loan save). */
function acc_treasury_apply_entry_balance(PDO $pdo, int $entryId): void
{
    if ($entryId < 1) {
        return;
    }
    $maxId = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM accounts_treasury_ledger')->fetchColumn();
    if ($entryId !== $maxId) {
        acc_treasury_rebuild_balances_from($pdo, $entryId);

        return;
    }

    $st = $pdo->prepare('SELECT id, direction, cash_amount, bank_amount FROM accounts_treasury_ledger WHERE id = :id');
    $st->execute(['id' => $entryId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }

    $opening = acc_treasury_get_opening($pdo);
    $cash = (float)($opening['opening_cash'] ?? 0);
    $bank = (float)($opening['opening_bank'] ?? 0);

    $prev = $pdo->prepare('SELECT cash_balance_after, bank_balance_after FROM accounts_treasury_ledger WHERE id < :id ORDER BY id DESC LIMIT 1');
    $prev->execute(['id' => $entryId]);
    $p = $prev->fetch(PDO::FETCH_ASSOC);
    if ($p) {
        $cash = (float)$p['cash_balance_after'];
        $bank = (float)$p['bank_balance_after'];
    }

    $balanceBefore = round($cash + $bank, 2);
    $cAmt = (float)$row['cash_amount'];
    $bAmt = (float)$row['bank_amount'];
    if ((string)$row['direction'] === 'credit') {
        $cash += $cAmt;
        $bank += $bAmt;
    } else {
        $cash -= $cAmt;
        $bank -= $bAmt;
    }
    $cash = round($cash, 2);
    $bank = round($bank, 2);
    $balanceAfter = round($cash + $bank, 2);

    $pdo->prepare(
        'UPDATE accounts_treasury_ledger SET balance_before = :bb, balance_after = :ba, cash_balance_after = :cb, bank_balance_after = :bk WHERE id = :id'
    )->execute([
        'bb' => $balanceBefore,
        'ba' => $balanceAfter,
        'cb' => $cash,
        'bk' => $bank,
        'id' => $entryId,
    ]);
}

function acc_treasury_rebuild_balances_from(PDO $pdo, int $fromEntryId = 0): void
{
    acc_treasury_ensure_schema($pdo);
    $opening = acc_treasury_get_opening($pdo);
    $cash = (float)($opening['opening_cash'] ?? 0);
    $bank = (float)($opening['opening_bank'] ?? 0);

    if ($fromEntryId > 0) {
        $prev = $pdo->prepare(
            'SELECT cash_balance_after, bank_balance_after FROM accounts_treasury_ledger WHERE id < :id ORDER BY id DESC LIMIT 1'
        );
        $prev->execute(['id' => $fromEntryId]);
        $p = $prev->fetch(PDO::FETCH_ASSOC);
        if ($p) {
            $cash = (float)$p['cash_balance_after'];
            $bank = (float)$p['bank_balance_after'];
        }
    }

    if ($fromEntryId > 0) {
        $st = $pdo->prepare(
            'SELECT id, direction, cash_amount, bank_amount FROM accounts_treasury_ledger WHERE id >= :id ORDER BY tx_date ASC, id ASC'
        );
        $st->execute(['id' => $fromEntryId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $rows = $pdo->query(
            'SELECT id, direction, cash_amount, bank_amount FROM accounts_treasury_ledger ORDER BY tx_date ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($rows === []) {
        return;
    }

    $upd = $pdo->prepare(
        'UPDATE accounts_treasury_ledger SET balance_before = :bb, balance_after = :ba, cash_balance_after = :cb, bank_balance_after = :bk WHERE id = :id'
    );

    foreach ($rows as $row) {
        $balanceBefore = round($cash + $bank, 2);
        $cAmt = (float)$row['cash_amount'];
        $bAmt = (float)$row['bank_amount'];
        if ((string)$row['direction'] === 'credit') {
            $cash += $cAmt;
            $bank += $bAmt;
        } else {
            $cash -= $cAmt;
            $bank -= $bAmt;
        }
        $cash = round($cash, 2);
        $bank = round($bank, 2);
        $balanceAfter = round($cash + $bank, 2);
        $upd->execute([
            'bb' => $balanceBefore,
            'ba' => $balanceAfter,
            'cb' => $cash,
            'bk' => $bank,
            'id' => (int)$row['id'],
        ]);
    }
}

function acc_treasury_sync_all(PDO $pdo): void
{
    acc_treasury_ensure_schema($pdo);

    if (dh_table_exists($pdo, 'sales_payments')) {
        $rows = $pdo->query(
            "SELECT p.id, p.payment_date, p.amount, p.payment_mode, p.reference_no, c.company_name, i.invoice_no
             FROM sales_payments p
             JOIN sales_customers c ON c.id = p.customer_id
             JOIN sales_invoices i ON i.id = p.invoice_id"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            acc_treasury_post_entry($pdo, [
                'tx_date' => (string)$r['payment_date'],
                'tx_type' => 'Customer Payment',
                'party' => (string)$r['company_name'],
                'reference_no' => (string)($r['reference_no'] ?: $r['invoice_no']),
                'amount' => (float)$r['amount'],
                'direction' => 'credit',
                'payment_mode' => (string)$r['payment_mode'],
                'source_module' => 'receivables',
                'source_type' => 'sales_payment',
                'source_id' => (int)$r['id'],
                'remarks' => 'Customer collection · ' . (string)$r['invoice_no'],
            ]);
        }
    }

    if (function_exists('inv_table_exists') && inv_table_exists($pdo, 'purchase_payments')) {
        require_once __DIR__ . '/inventory_purchase.php';
        $rows = $pdo->query(
            "SELECT p.id, p.payment_date, p.amount, p.payment_mode, p.payment_ref, COALESCE(s.name,'Supplier') AS supplier_name, COALESCE(i.pinv_no, CONCAT('PINV-',i.id)) AS pinv
             FROM purchase_payments p
             JOIN stock_inward i ON i.id = p.inward_id
             LEFT JOIN suppliers s ON s.id = i.supplier_id"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            acc_treasury_post_entry($pdo, [
                'tx_date' => (string)$r['payment_date'],
                'tx_type' => 'Supplier Payment',
                'party' => (string)$r['supplier_name'],
                'reference_no' => (string)($r['payment_ref'] ?? ''),
                'amount' => (float)$r['amount'],
                'direction' => 'debit',
                'payment_mode' => (string)($r['payment_mode'] ?? 'Bank'),
                'source_module' => 'payables',
                'source_type' => 'supplier_payment',
                'source_id' => (int)$r['id'],
                'remarks' => 'Supplier payment · ' . (string)$r['pinv'],
            ]);
        }
    }

    if (dh_table_exists($pdo, 'accounts_expenses')) {
        $rows = $pdo->query('SELECT id, expense_date, amount, payment_mode, category, reference_no, remarks FROM accounts_expenses')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            acc_treasury_post_entry($pdo, [
                'tx_date' => (string)$r['expense_date'],
                'tx_type' => 'Expense',
                'party' => (string)$r['category'],
                'reference_no' => (string)($r['reference_no'] ?? ''),
                'amount' => (float)$r['amount'],
                'direction' => 'debit',
                'payment_mode' => (string)$r['payment_mode'],
                'source_module' => 'expenses',
                'source_type' => 'expense',
                'source_id' => (int)$r['id'],
                'remarks' => (string)($r['remarks'] ?? $r['category']),
            ]);
        }
    }

    if (dh_table_exists($pdo, 'accounts_salary_payments')) {
        $rows = $pdo->query(
            "SELECT p.id, p.payment_date, p.amount, p.payment_mode, p.reference_no, e.full_name, s.month_year
             FROM accounts_salary_payments p
             INNER JOIN employees e ON e.id = p.employee_id
             INNER JOIN salaries s ON s.id = p.salary_id
             WHERE p.salary_id IS NOT NULL"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            acc_treasury_post_entry($pdo, [
                'tx_date' => (string)$r['payment_date'],
                'tx_type' => 'Salary Payment',
                'party' => (string)$r['full_name'],
                'reference_no' => (string)($r['reference_no'] ?? ''),
                'amount' => (float)$r['amount'],
                'direction' => 'debit',
                'payment_mode' => (string)$r['payment_mode'],
                'source_module' => 'salary',
                'source_type' => 'salary_payment',
                'source_id' => (int)$r['id'],
                'remarks' => 'Salary · ' . (string)$r['month_year'],
            ]);
        }
    }

    $loans = $pdo->query(
        'SELECT l.*, tl.payment_mode AS ledger_mode FROM accounts_loans l
         LEFT JOIN accounts_treasury_ledger tl ON tl.id = l.ledger_entry_id'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($loans as $loan) {
        $lid = (int)$loan['id'];
        acc_treasury_post_entry($pdo, [
            'tx_date' => (string)$loan['loan_date'],
            'tx_type' => 'Loan Received',
            'party' => acc_treasury_resolve_party((string)($loan['lender_name'] ?? ''), (string)$loan['loan_source']),
            'reference_no' => (string)$loan['loan_code'],
            'amount' => (float)$loan['principal_amount'],
            'direction' => 'credit',
            'payment_mode' => trim((string)($loan['ledger_mode'] ?? 'Bank')) ?: 'Bank',
            'source_module' => 'loan',
            'source_type' => 'loan_received',
            'source_id' => $lid,
            'loan_id' => $lid,
            'remarks' => (string)($loan['remarks'] ?? ''),
        ]);
    }

    $repays = $pdo->query('SELECT * FROM accounts_loan_repayments')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($repays as $rp) {
        acc_treasury_post_entry($pdo, [
            'tx_date' => (string)$rp['payment_date'],
            'tx_type' => 'Loan Repayment',
            'party' => 'Loan #' . (int)$rp['loan_id'],
            'reference_no' => (string)($rp['reference_no'] ?? ''),
            'amount' => (float)$rp['amount'],
            'direction' => 'debit',
            'payment_mode' => (string)$rp['payment_mode'],
            'source_module' => 'loan',
            'source_type' => 'loan_repayment',
            'source_id' => (int)$rp['id'],
            'loan_id' => (int)$rp['loan_id'],
            'remarks' => (string)($rp['remarks'] ?? ''),
        ]);
    }

    acc_treasury_rebuild_balances($pdo);
}

/** Mirror a single business payment into treasury (call after save). */
function acc_treasury_mirror_customer_payment(PDO $pdo, int $paymentId): void
{
    if ($paymentId < 1 || !dh_table_exists($pdo, 'sales_payments')) {
        return;
    }
    $st = $pdo->prepare(
        "SELECT p.*, c.company_name, i.invoice_no FROM sales_payments p
         JOIN sales_customers c ON c.id = p.customer_id JOIN sales_invoices i ON i.id = p.invoice_id WHERE p.id = :id"
    );
    $st->execute(['id' => $paymentId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return;
    }
    acc_treasury_post_entry($pdo, [
        'tx_date' => (string)$r['payment_date'],
        'tx_type' => 'Customer Payment',
        'party' => (string)$r['company_name'],
        'reference_no' => (string)($r['reference_no'] ?: $r['invoice_no']),
        'amount' => (float)$r['amount'],
        'direction' => 'credit',
        'payment_mode' => (string)$r['payment_mode'],
        'source_module' => 'receivables',
        'source_type' => 'sales_payment',
        'source_id' => $paymentId,
        'remarks' => 'Customer collection',
    ]);
    acc_treasury_rebuild_balances($pdo);
}

function acc_treasury_mirror_supplier_payment(PDO $pdo, int $paymentId): void
{
    if ($paymentId < 1) {
        return;
    }
    require_once __DIR__ . '/inventory_purchase.php';
    $pay = inv_purchase_get_payment($pdo, $paymentId);
    if (!$pay) {
        return;
    }
    $inward = inv_purchase_get($pdo, (int)$pay['inward_id']);
    acc_treasury_post_entry($pdo, [
        'tx_date' => (string)$pay['payment_date'],
        'tx_type' => 'Supplier Payment',
        'party' => (string)($inward['supplier_name'] ?? 'Supplier'),
        'reference_no' => (string)($pay['payment_ref'] ?? ''),
        'amount' => (float)$pay['amount'],
        'direction' => 'debit',
        'payment_mode' => (string)($pay['payment_mode'] ?? 'Bank'),
        'source_module' => 'payables',
        'source_type' => 'supplier_payment',
        'source_id' => $paymentId,
        'remarks' => 'Supplier payment',
    ]);
    acc_treasury_rebuild_balances($pdo);
}

function acc_treasury_mirror_expense(PDO $pdo, int $expenseId): void
{
    if ($expenseId < 1) {
        return;
    }
    require_once __DIR__ . '/accounts_expenses.php';
    $row = acc_get_expense($pdo, $expenseId);
    if (!$row) {
        return;
    }
    acc_treasury_post_entry($pdo, [
        'tx_date' => (string)$row['expense_date'],
        'tx_type' => 'Expense',
        'party' => (string)$row['category'],
        'reference_no' => (string)($row['reference_no'] ?? ''),
        'amount' => (float)$row['amount'],
        'direction' => 'debit',
        'payment_mode' => (string)$row['payment_mode'],
        'source_module' => 'expenses',
        'source_type' => 'expense',
        'source_id' => $expenseId,
        'remarks' => (string)($row['remarks'] ?? ''),
    ]);
    acc_treasury_rebuild_balances($pdo);
}

function acc_treasury_mirror_salary_payment(PDO $pdo, int $paymentId): void
{
    if ($paymentId < 1 || !dh_table_exists($pdo, 'accounts_salary_payments')) {
        return;
    }
    $st = $pdo->prepare(
        'SELECT p.*, e.full_name, s.month_year FROM accounts_salary_payments p
         INNER JOIN employees e ON e.id = p.employee_id INNER JOIN salaries s ON s.id = p.salary_id WHERE p.id = :id'
    );
    $st->execute(['id' => $paymentId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return;
    }
    acc_treasury_post_entry($pdo, [
        'tx_date' => (string)$r['payment_date'],
        'tx_type' => 'Salary Payment',
        'party' => (string)$r['full_name'],
        'reference_no' => (string)($r['reference_no'] ?? ''),
        'amount' => (float)$r['amount'],
        'direction' => 'debit',
        'payment_mode' => (string)$r['payment_mode'],
        'source_module' => 'salary',
        'source_type' => 'salary_payment',
        'source_id' => $paymentId,
        'remarks' => 'Salary payment',
    ]);
    acc_treasury_rebuild_balances($pdo);
}

function acc_treasury_kpis(PDO $pdo, bool $syncSources = true): array
{
    acc_treasury_ensure_schema($pdo);
    if ($syncSources) {
        acc_treasury_sync_all($pdo);
    }
    $opening = acc_treasury_get_opening($pdo);
    $openCash = (float)($opening['opening_cash'] ?? 0);
    $openBank = (float)($opening['opening_bank'] ?? 0);

    $last = $pdo->query('SELECT cash_balance_after, bank_balance_after, balance_after FROM accounts_treasury_ledger ORDER BY tx_date DESC, id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $cash = $last ? (float)$last['cash_balance_after'] : $openCash;
    $bank = $last ? (float)$last['bank_balance_after'] : $openBank;
    $available = round($cash + $bank, 2);

    $monthStart = date('Y-m-01');
    $inflow = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM accounts_treasury_ledger WHERE direction = 'credit' AND tx_date >= " . $pdo->quote($monthStart)
    )->fetchColumn();
    $outflow = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM accounts_treasury_ledger WHERE direction = 'debit' AND tx_date >= " . $pdo->quote($monthStart)
    )->fetchColumn();

    $loanTaken = (float)$pdo->query('SELECT COALESCE(SUM(principal_amount),0) FROM accounts_loans')->fetchColumn();
    $loanRepaid = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM accounts_loan_repayments')->fetchColumn();
    $outstanding = max(0, round($loanTaken - $loanRepaid, 2));
    $activeLoans = (int)$pdo->query("SELECT COUNT(*) FROM accounts_loans WHERE status = 'Active' AND (principal_amount - repaid_amount) > 0.01")->fetchColumn();

    $nextDue = $pdo->query(
        "SELECT loan_code, due_date, (principal_amount - repaid_amount) AS outstanding FROM accounts_loans
         WHERE status = 'Active' AND due_date IS NOT NULL AND (principal_amount - repaid_amount) > 0.01
         ORDER BY due_date ASC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC) ?: null;

    $interestLiability = 0.0;
    $loanRows = $pdo->query("SELECT principal_amount, repaid_amount, interest_rate FROM accounts_loans WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($loanRows as $lr) {
        $rem = max(0, (float)$lr['principal_amount'] - (float)$lr['repaid_amount']);
        $interestLiability += $rem * ((float)$lr['interest_rate'] / 100);
    }

    $totalReceivable = 0.0;
    $totalPayable = 0.0;
    if (function_exists('sales_payment_dashboard')) {
        $ar = sales_payment_dashboard($pdo);
        $totalReceivable = (float)($ar['pending'] ?? 0);
    }
    if (function_exists('inv_supplier_ledger_list')) {
        require_once __DIR__ . '/inventory_purchase.php';
        foreach (inv_supplier_ledger_list($pdo) as $s) {
            $totalPayable += (float)($s['pending_balance'] ?? 0);
        }
    }

    return [
        'available_funds' => $available,
        'cash_in_hand' => $cash,
        'bank_balance' => $bank,
        'total_inflow' => $inflow,
        'total_outflow' => $outflow,
        'active_loans' => $activeLoans,
        'outstanding_loan' => $outstanding,
        'loans_taken' => $loanTaken,
        'loans_repaid' => $loanRepaid,
        'interest_liability' => round($interestLiability, 2),
        'next_due_loan' => $nextDue,
        'total_receivable' => $totalReceivable,
        'total_payable' => $totalPayable,
        'opening' => $opening,
    ];
}

/** @return list<array<string, mixed>> */
function acc_treasury_list(PDO $pdo, array $filters = []): array
{
    acc_treasury_ensure_schema($pdo);
    $sql = 'SELECT * FROM accounts_treasury_ledger WHERE 1=1';
    $params = [];
    $from = trim((string)($filters['from'] ?? ''));
    $to = trim((string)($filters['to'] ?? date('Y-m-d')));
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $sql .= ' AND tx_date >= :f';
        $params['f'] = $from;
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $sql .= ' AND tx_date <= :t';
        $params['t'] = $to;
    }
    $type = trim((string)($filters['tx_type'] ?? ''));
    if ($type !== '') {
        $sql .= ' AND tx_type = :tt';
        $params['tt'] = $type;
    } elseif (!empty($filters['allowed_types']) && is_array($filters['allowed_types'])) {
        $types = array_values($filters['allowed_types']);
        if ($types === []) {
            return [];
        }
        $ph = [];
        foreach ($types as $i => $t) {
            $k = 'at' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $t;
        }
        $sql .= ' AND tx_type IN (' . implode(',', $ph) . ')';
    }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $sql .= ' AND (party LIKE :q OR reference_no LIKE :q OR tx_code LIKE :q OR remarks LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $loanSource = trim((string)($filters['loan_source'] ?? ''));
    if ($loanSource !== '') {
        $sql .= ' AND loan_id IN (SELECT id FROM accounts_loans WHERE loan_source = :ls)';
        $params['ls'] = $loanSource;
    }
    $party = trim((string)($filters['party'] ?? ''));
    if ($party !== '') {
        $sql .= ' AND party LIKE :party';
        $params['party'] = '%' . $party . '%';
    }
    $status = trim((string)($filters['status'] ?? ''));
    if ($status !== '') {
        $sql .= ' AND tx_status = :st';
        $params['st'] = $status;
    }
    $sql .= ' ORDER BY tx_date DESC, id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function acc_treasury_loans_list(PDO $pdo): array
{
    acc_treasury_ensure_schema($pdo);
    $rows = $pdo->query(
        'SELECT *, (principal_amount - repaid_amount) AS outstanding FROM accounts_loans ORDER BY loan_date DESC, id DESC'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['outstanding'] = max(0, round((float)$r['outstanding'], 2));
        $r['status_label'] = $r['outstanding'] <= 0.01 ? 'Closed' : (string)$r['status'];
    }
    unset($r);

    return $rows;
}

function acc_treasury_add_loan(PDO $pdo, array $data, ?array $files = null): int
{
    if (!acc_treasury_can_manage()) {
        throw new RuntimeException('Permission denied.');
    }
    acc_treasury_ensure_schema($pdo);
    $amount = acc_treasury_validate_amount((float)($data['loan_amount'] ?? $data['principal_amount'] ?? 0), 'Loan amount');
    $source = trim((string)($data['loan_source'] ?? ''));
    if (!in_array($source, ACC_LOAN_SOURCES, true)) {
        throw new InvalidArgumentException('Select a valid loan source.');
    }
    $loanDate = trim((string)($data['loan_date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $loanDate)) {
        throw new InvalidArgumentException('Valid loan date is required.');
    }
    $due = trim((string)($data['due_date'] ?? $data['repayment_due_date'] ?? ''));
    $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null;
    $code = acc_treasury_next_loan_code($pdo);
    $userRef = trim((string)($data['reference_no'] ?? ''));
    $ledgerRef = $userRef !== '' ? $userRef : $code;
    $doc = null;
    if ($files && !empty($files['loan_document']['name'])) {
        $doc = acc_treasury_handle_upload($files, 'loan_document');
    }
    $by = acc_treasury_current_user();
    $lenderLabel = trim((string)($data['lender_name'] ?? '')) ?: $source;
    $remarks = trim((string)($data['remarks'] ?? ''));
    if ($userRef !== '' && $userRef !== $code) {
        $remarks = ($remarks !== '' ? $remarks . ' · ' : '') . 'Loan ID: ' . $code;
    }
    $pdo->prepare(
        'INSERT INTO accounts_loans (loan_code, loan_source, lender_name, principal_amount, interest_rate, loan_date, due_date, remarks, document_path, created_by)
         VALUES (:code,:src,:lender,:amt,:ir,:ld,:due,:rm,:doc,:by)'
    )->execute([
        'code' => $code,
        'src' => $source,
        'lender' => $lenderLabel,
        'amt' => $amount,
        'ir' => round((float)($data['interest_rate'] ?? $data['interest'] ?? 0), 2),
        'ld' => $loanDate,
        'due' => $due,
        'rm' => $remarks !== '' ? $remarks : null,
        'doc' => $doc,
        'by' => $by,
    ]);
    $loanId = (int)$pdo->lastInsertId();
    $ledgerId = acc_treasury_post_entry($pdo, [
        'tx_date' => $loanDate,
        'tx_type' => 'Loan Received',
        'party' => $lenderLabel,
        'reference_no' => $ledgerRef,
        'amount' => $amount,
        'direction' => 'credit',
        'payment_mode' => trim((string)($data['payment_mode'] ?? 'Cash')) ?: 'Cash',
        'source_module' => 'loan',
        'source_type' => 'loan_received',
        'source_id' => $loanId,
        'loan_id' => $loanId,
        'remarks' => $remarks !== '' ? $remarks : ('Loan ' . $code),
        'created_by' => $by,
    ]);
    $pdo->prepare('UPDATE accounts_loans SET ledger_entry_id = :l WHERE id = :id')->execute(['l' => $ledgerId, 'id' => $loanId]);
    acc_treasury_apply_entry_balance($pdo, $ledgerId);

    return $loanId;
}

function acc_treasury_repay_loan(PDO $pdo, array $data): int
{
    if (!acc_treasury_can_manage()) {
        throw new RuntimeException('Permission denied.');
    }
    acc_treasury_ensure_schema($pdo);
    $loanId = (int)($data['loan_id'] ?? 0);
    $st = $pdo->prepare('SELECT * FROM accounts_loans WHERE id = :id');
    $st->execute(['id' => $loanId]);
    $loan = $st->fetch(PDO::FETCH_ASSOC);
    if (!$loan) {
        throw new InvalidArgumentException('Loan not found.');
    }
    $amount = acc_treasury_validate_amount((float)($data['amount'] ?? $data['repayment_amount'] ?? 0), 'Repayment amount');
    $outstanding = max(0, round((float)$loan['principal_amount'] - (float)$loan['repaid_amount'], 2));
    if ($amount > $outstanding + 0.02) {
        throw new InvalidArgumentException('Amount exceeds outstanding loan balance.');
    }
    $payDate = trim((string)($data['payment_date'] ?? $data['date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payDate)) {
        throw new InvalidArgumentException('Valid payment date is required.');
    }
    $mode = trim((string)($data['payment_mode'] ?? 'Bank'));
    $by = acc_treasury_current_user();
    $pdo->prepare(
        'INSERT INTO accounts_loan_repayments (loan_id, amount, payment_date, payment_mode, reference_no, remarks, created_by)
         VALUES (:lid,:a,:d,:m,:r,:rm,:by)'
    )->execute([
        'lid' => $loanId,
        'a' => $amount,
        'd' => $payDate,
        'm' => $mode,
        'r' => trim((string)($data['reference_no'] ?? '')) ?: null,
        'rm' => trim((string)($data['remarks'] ?? '')) ?: null,
        'by' => $by,
    ]);
    $repayId = (int)$pdo->lastInsertId();
    $newRepaid = round((float)$loan['repaid_amount'] + $amount, 2);
    $status = $newRepaid >= (float)$loan['principal_amount'] - 0.02 ? 'Closed' : 'Active';
    $pdo->prepare('UPDATE accounts_loans SET repaid_amount = :r, status = :s WHERE id = :id')
        ->execute(['r' => $newRepaid, 's' => $status, 'id' => $loanId]);
    $ledgerId = acc_treasury_post_entry($pdo, [
        'tx_date' => $payDate,
        'tx_type' => 'Loan Repayment',
        'party' => acc_treasury_resolve_party((string)($loan['lender_name'] ?? ''), (string)$loan['loan_source']),
        'reference_no' => (string)($data['reference_no'] ?? $loan['loan_code']),
        'amount' => $amount,
        'direction' => 'debit',
        'payment_mode' => $mode ?: 'Bank',
        'source_module' => 'loan',
        'source_type' => 'loan_repayment',
        'source_id' => $repayId,
        'loan_id' => $loanId,
        'remarks' => trim((string)($data['remarks'] ?? '')),
        'created_by' => $by,
    ]);
    $pdo->prepare('UPDATE accounts_loan_repayments SET ledger_entry_id = :l WHERE id = :id')->execute(['l' => $ledgerId, 'id' => $repayId]);
    acc_treasury_apply_entry_balance($pdo, $ledgerId);

    return $repayId;
}

function acc_treasury_adjust_funds(PDO $pdo, array $data): int
{
    if (!acc_treasury_can_adjust()) {
        throw new RuntimeException('Only Accounts Manager can adjust funds.');
    }
    acc_treasury_ensure_schema($pdo);
    $amount = acc_treasury_validate_amount((float)($data['amount'] ?? 0), 'Adjustment amount');
    $adj = strtolower(trim((string)($data['adjust_type'] ?? $data['add_deduct'] ?? 'add')));
    $direction = in_array($adj, ['deduct', 'subtract', 'minus', 'debit'], true) ? 'debit' : 'credit';
    $mode = trim((string)($data['payment_mode'] ?? 'Bank'));
    $date = trim((string)($data['adjust_date'] ?? $data['date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid date is required.');
    }
    $reason = trim((string)($data['reason'] ?? $data['remarks'] ?? ''));
    if ($reason === '') {
        throw new InvalidArgumentException('Reason is required for audit trail.');
    }
    $id = acc_treasury_post_entry($pdo, [
        'tx_date' => $date,
        'tx_type' => 'Manual Adjustment',
        'party' => 'Treasury Adjustment',
        'reference_no' => trim((string)($data['reference_no'] ?? $data['reference'] ?? '')),
        'amount' => $amount,
        'direction' => $direction,
        'payment_mode' => $mode,
        'source_module' => 'treasury',
        'source_type' => 'adjustment',
        'source_id' => 0,
        'remarks' => $reason,
        'created_by' => acc_treasury_current_user(),
    ]);
    acc_treasury_apply_entry_balance($pdo, $id);

    return $id;
}

function acc_treasury_handle_upload(array $files, string $field): ?string
{
    if (empty($files[$field]['name']) || (int)($files[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo((string)$files[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
        throw new InvalidArgumentException('Document must be PDF, JPG, or PNG.');
    }
    $dir = dirname(__DIR__) . '/uploads/treasury/' . date('Y/m');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = uniqid('loan_', true) . '.' . $ext;
    $rel = 'uploads/treasury/' . date('Y/m') . '/' . $name;
    if (!move_uploaded_file((string)$files[$field]['tmp_name'], dirname(__DIR__) . '/' . $rel)) {
        throw new RuntimeException('Failed to save document.');
    }

    return $rel;
}

/** Month totals for a single treasury transaction type. */
function acc_treasury_month_type_total(PDO $pdo, string $txType, string $direction = 'debit'): float
{
    acc_treasury_ensure_schema($pdo);
    $monthStart = date('Y-m-01');
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0) FROM accounts_treasury_ledger WHERE tx_type = :tt AND direction = :dir AND tx_date >= :d'
    );
    $st->execute(['tt' => $txType, 'dir' => $direction, 'd' => $monthStart]);

    return (float)$st->fetchColumn();
}

function acc_treasury_handle_export(PDO $pdo): void
{
    $export = strtolower(trim((string)($_GET['export'] ?? '')));
    if (!in_array($export, ['pdf', 'csv', 'print'], true)) {
        return;
    }
    $allowed = acc_treasury_allowed_tx_types();
    if ($allowed === []) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
    $filters = [
        'from' => (string)($_GET['from'] ?? date('Y-m-01')),
        'to' => (string)($_GET['to'] ?? date('Y-m-d')),
        'tx_type' => (string)($_GET['tx_type'] ?? ''),
        'q' => trim((string)($_GET['search'] ?? $_GET['q'] ?? '')),
        'loan_source' => (string)($_GET['loan_source'] ?? ''),
        'party' => trim((string)($_GET['party'] ?? '')),
        'status' => trim((string)($_GET['status'] ?? '')),
        'allowed_types' => $allowed,
    ];
    $rows = acc_treasury_list($pdo, $filters);
    $headers = ['Transaction ID', 'Date', 'Type', 'Party', 'Reference', 'Amount', 'Dr/Cr', 'Balance After', 'Mode', 'Status', 'Created By'];
    $data = [];
    foreach ($rows as $r) {
        $sign = (string)$r['direction'] === 'credit' ? '+' : '-';
        $data[] = [
            (string)$r['tx_code'],
            (string)$r['tx_date'],
            (string)$r['tx_type'],
            (string)($r['party'] ?? ''),
            (string)($r['reference_no'] ?? ''),
            $sign . sales_format_money((float)$r['amount']),
            strtoupper((string)$r['direction']),
            sales_format_money((float)$r['balance_after']),
            (string)($r['payment_mode'] ?? ''),
            (string)$r['tx_status'],
            (string)($r['created_by'] ?? ''),
        ];
    }
    $title = 'Cash & Bank Treasury Report';
    $kpis = acc_treasury_kpis($pdo, false);
    $printOpts = [
        'back_url' => route_url('accounts/cashbook'),
        'subtitle' => 'Treasury ledger · ' . ($filters['from'] ?? '') . ' to ' . ($filters['to'] ?? ''),
        'kpis' => [
            ['Available Funds', sales_format_money((float)$kpis['available_funds'])],
            ['Cash in Hand', sales_format_money((float)$kpis['cash_in_hand'])],
            ['Bank Balance', sales_format_money((float)$kpis['bank_balance'])],
            ['Records', (string)count($rows)],
        ],
        'meta' => [
            'Period' => ($filters['from'] ?? '') . ' — ' . ($filters['to'] ?? ''),
            'Type filter' => (string)($filters['tx_type'] ?? 'All'),
        ],
    ];
    if ($export === 'csv') {
        erp_send_csv('treasury-report.csv', $headers, $data);
    }
    erp_print_html_table($title, $headers, $data, $export === 'print', $printOpts);
    exit;
}
