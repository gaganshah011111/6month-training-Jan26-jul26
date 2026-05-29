<?php
declare(strict_types=1);

require_once __DIR__ . '/sales_service.php';
require_once __DIR__ . '/erp_export.php';

const ACC_EXPENSE_CATEGORIES = [
    'Electricity',
    'Diesel',
    'Transport',
    'Maintenance',
    'Office',
    'Salary',
    'Salary Adjustment',
    'Internet',
    'Miscellaneous',
    'Emergency Expense',
    'Supplier Payment',
];

/** Categories shown in manual Add Expense form (auto-only types excluded). */
function acc_expense_manual_categories(): array
{
    return array_values(array_filter(
        ACC_EXPENSE_CATEGORIES,
        static fn(string $c) => !in_array($c, ['Supplier Payment'], true)
    ));
}

const ACC_PAYMENT_MODES = ['Cash', 'UPI', 'Bank', 'Cheque', 'NEFT', 'RTGS', 'Bank Transfer'];

function acc_expense_normalize_category(string $cat): string
{
    $cat = trim($cat);
    if ($cat === 'Misc') {
        return 'Miscellaneous';
    }
    if ($cat === 'Office Expense') {
        return 'Office';
    }
    return $cat;
}

function acc_expense_upload_dir(): string
{
    $dir = dirname(__DIR__) . '/uploads/expenses';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function acc_expense_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS accounts_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(40) NOT NULL,
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            payment_mode VARCHAR(20) NOT NULL DEFAULT 'Cash',
            expense_date DATE NOT NULL,
            reference_no VARCHAR(80) NULL,
            remarks VARCHAR(255) NULL,
            attachment VARCHAR(255) NULL,
            attachment_bill VARCHAR(255) NULL,
            attachment_receipt VARCHAR(255) NULL,
            attachment_invoice VARCHAR(255) NULL,
            source_type VARCHAR(32) NULL,
            source_id INT NULL,
            created_by VARCHAR(120) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_acc_expense_date (expense_date),
            INDEX idx_acc_expense_cat (category),
            INDEX idx_acc_expense_mode (payment_mode),
            INDEX idx_acc_expense_source (source_type, source_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    foreach ([
        'reference_no VARCHAR(80) NULL',
        'attachment_bill VARCHAR(255) NULL',
        'attachment_receipt VARCHAR(255) NULL',
        'attachment_invoice VARCHAR(255) NULL',
        'source_type VARCHAR(32) NULL',
        'source_id INT NULL',
    ] as $colDef) {
        $col = explode(' ', $colDef)[0];
        if (!dh_column_exists($pdo, 'accounts_expenses', $col)) {
            try {
                $pdo->exec('ALTER TABLE accounts_expenses ADD COLUMN ' . $colDef);
            } catch (Throwable) {
                // ignore duplicate migration races
            }
        }
    }
}

function acc_expense_handle_upload(array $files, string $field): ?string
{
    if (empty($files[$field]['name']) || (int)($files[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    $extAllowed = ['pdf', 'jpg', 'jpeg', 'png'];
    $name = (string)$files[$field]['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $extAllowed, true)) {
        throw new InvalidArgumentException('Attachment must be PDF, JPG, or PNG.');
    }
    $mime = (string)mime_content_type((string)$files[$field]['tmp_name']);
    if ($mime !== '' && !in_array($mime, $allowed, true) && !str_starts_with($mime, 'image/')) {
        throw new InvalidArgumentException('Invalid attachment file type.');
    }
    $subdir = date('Y/m');
    $destDir = acc_expense_upload_dir() . '/' . $subdir;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name)) ?: 'file.' . $ext;
    $filename = uniqid('exp_', true) . '_' . $safe;
    $rel = 'uploads/expenses/' . $subdir . '/' . $filename;
    $full = dirname(__DIR__) . '/' . $rel;
    if (!move_uploaded_file((string)$files[$field]['tmp_name'], $full)) {
        throw new RuntimeException('Failed to save attachment.');
    }
    return $rel;
}

function acc_expense_exists_source(PDO $pdo, string $sourceType, int $sourceId): bool
{
    acc_expense_ensure_schema($pdo);
    if ($sourceId < 1 || $sourceType === '') {
        return false;
    }
    $st = $pdo->prepare('SELECT id FROM accounts_expenses WHERE source_type = :t AND source_id = :i LIMIT 1');
    $st->execute(['t' => $sourceType, 'i' => $sourceId]);
    return (bool)$st->fetchColumn();
}

function acc_save_expense(PDO $pdo, array $data, ?array $files = null): int
{
    acc_expense_ensure_schema($pdo);
    $category = acc_expense_normalize_category((string)($data['category'] ?? ''));
    $amount = max(0, (float)($data['amount'] ?? 0));
    $mode = trim((string)($data['payment_mode'] ?? 'Cash'));
    $date = trim((string)($data['expense_date'] ?? date('Y-m-d')));
    if (!in_array($category, ACC_EXPENSE_CATEGORIES, true)) {
        throw new InvalidArgumentException('Select a valid expense category.');
    }
    $isAuto = trim((string)($data['source_type'] ?? '')) !== '';
    if (!$isAuto && in_array($category, ['Supplier Payment'], true)) {
        throw new InvalidArgumentException('Supplier Payment expenses are created automatically when you record a supplier payment.');
    }
    if ($amount <= 0) {
        throw new InvalidArgumentException('Expense amount must be greater than zero.');
    }
    if (!in_array($mode, ACC_PAYMENT_MODES, true)) {
        $mode = 'Cash';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid expense date is required.');
    }

    $bill = $receipt = $invoice = null;
    if ($files !== null) {
        $bill = acc_expense_handle_upload($files, 'attachment_bill');
        $receipt = acc_expense_handle_upload($files, 'attachment_receipt');
        $invoice = acc_expense_handle_upload($files, 'attachment_invoice');
    }
    $attachment = $bill ?: ($receipt ?: ($invoice ?: trim((string)($data['attachment'] ?? '')) ?: null));

    $st = $pdo->prepare(
        'INSERT INTO accounts_expenses (category, amount, payment_mode, expense_date, reference_no, remarks,
            attachment, attachment_bill, attachment_receipt, attachment_invoice, source_type, source_id, created_by)
         VALUES (:c, :a, :m, :d, :ref, :r, :f, :b, :rc, :inv, :st, :sid, :by)'
    );
    $st->execute([
        'c' => $category,
        'a' => round($amount, 2),
        'm' => $mode,
        'd' => $date,
        'ref' => trim((string)($data['reference_no'] ?? '')) ?: null,
        'r' => trim((string)($data['remarks'] ?? '')) ?: null,
        'f' => $attachment,
        'b' => $bill,
        'rc' => $receipt,
        'inv' => $invoice,
        'st' => trim((string)($data['source_type'] ?? '')) ?: null,
        'sid' => (int)($data['source_id'] ?? 0) ?: null,
        'by' => (string)((current_user()['full_name'] ?? current_user()['username'] ?? current_user()['email'] ?? 'ERP User')),
    ]);

    $expenseId = (int)$pdo->lastInsertId();
    if (function_exists('acc_treasury_mirror_expense')) {
        require_once __DIR__ . '/accounts_treasury.php';
        try {
            acc_treasury_mirror_expense($pdo, $expenseId);
        } catch (Throwable) {
            // treasury mirror must not block expense save
        }
    }

    return $expenseId;
}

function acc_expense_create_from_salary(PDO $pdo, int $paymentId, array $paymentRow, array $salaryRow): int
{
    if (!function_exists('payroll_format_month_label')) {
        require_once __DIR__ . '/payroll_service.php';
    }
    if (acc_expense_exists_source($pdo, 'salary_payment', $paymentId)) {
        $st = $pdo->prepare('SELECT id FROM accounts_expenses WHERE source_type = :t AND source_id = :i LIMIT 1');
        $st->execute(['t' => 'salary_payment', 'i' => $paymentId]);
        return (int)$st->fetchColumn();
    }
    $empName = (string)($salaryRow['full_name'] ?? 'Employee');
    $month = (string)($salaryRow['month_year'] ?? '');
    $remarks = trim('Salary — ' . $empName . ' · ' . payroll_format_month_label($month)
        . ($paymentRow['reference_no'] ? ' · Ref ' . $paymentRow['reference_no'] : ''));
    $mode = trim((string)($paymentRow['payment_mode'] ?? 'Bank'));
    if (!in_array($mode, ACC_PAYMENT_MODES, true)) {
        $mode = in_array($mode, ['NEFT', 'RTGS', 'Bank Transfer'], true) ? 'Bank' : 'Cash';
    }
    return acc_save_expense($pdo, [
        'category' => 'Salary',
        'amount' => (float)$paymentRow['amount'],
        'payment_mode' => $mode,
        'expense_date' => (string)$paymentRow['payment_date'],
        'reference_no' => (string)($paymentRow['reference_no'] ?? ''),
        'remarks' => $remarks,
        'source_type' => 'salary_payment',
        'source_id' => $paymentId,
    ]);
}

function acc_expense_create_from_supplier(PDO $pdo, int $paymentId): int
{
    if (acc_expense_exists_source($pdo, 'supplier_payment', $paymentId)) {
        $st = $pdo->prepare('SELECT id FROM accounts_expenses WHERE source_type = :t AND source_id = :i LIMIT 1');
        $st->execute(['t' => 'supplier_payment', 'i' => $paymentId]);
        return (int)$st->fetchColumn();
    }
    require_once __DIR__ . '/inventory_purchase.php';
    $pay = inv_purchase_get_payment($pdo, $paymentId);
    if (!$pay) {
        throw new RuntimeException('Supplier payment not found.');
    }
    $inward = inv_purchase_get($pdo, (int)$pay['inward_id']);
    $pinv = (string)($inward['pinv_no'] ?? 'PINV');
    $supplier = (string)($inward['supplier_name'] ?? 'Supplier');
    $mode = trim((string)($pay['payment_mode'] ?? 'Bank')) ?: 'Cash';
    if (!in_array($mode, ACC_PAYMENT_MODES, true)) {
        $mode = 'Cash';
    }
    return acc_save_expense($pdo, [
        'category' => 'Supplier Payment',
        'amount' => (float)$pay['amount'],
        'payment_mode' => $mode,
        'expense_date' => (string)$pay['payment_date'],
        'reference_no' => (string)($pay['payment_ref'] ?? ''),
        'remarks' => 'Supplier payment — ' . $supplier . ' · ' . $pinv,
        'source_type' => 'supplier_payment',
        'source_id' => $paymentId,
    ]);
}

function acc_get_expense(PDO $pdo, int $id): ?array
{
    acc_expense_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM accounts_expenses WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function acc_update_expense(PDO $pdo, int $id, array $data, ?array $files = null): void
{
    $row = acc_get_expense($pdo, $id);
    if (!$row) {
        throw new InvalidArgumentException('Expense not found.');
    }
    if (!empty($row['source_type'])) {
        throw new InvalidArgumentException('Auto-generated expenses cannot be edited. Edit the source payment instead.');
    }
    $category = acc_expense_normalize_category((string)($data['category'] ?? $row['category']));
    $amount = max(0, (float)($data['amount'] ?? $row['amount']));
    $mode = trim((string)($data['payment_mode'] ?? $row['payment_mode']));
    $date = trim((string)($data['expense_date'] ?? $row['expense_date']));
    if (!in_array($category, ACC_EXPENSE_CATEGORIES, true)) {
        throw new InvalidArgumentException('Invalid category.');
    }
    if ($amount <= 0) {
        throw new InvalidArgumentException('Amount must be greater than zero.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Valid date required.');
    }
    $bill = (string)($row['attachment_bill'] ?? '');
    $receipt = (string)($row['attachment_receipt'] ?? '');
    $invoice = (string)($row['attachment_invoice'] ?? '');
    if ($files !== null) {
        if ($b = acc_expense_handle_upload($files, 'attachment_bill')) {
            $bill = $b;
        }
        if ($r = acc_expense_handle_upload($files, 'attachment_receipt')) {
            $receipt = $r;
        }
        if ($i = acc_expense_handle_upload($files, 'attachment_invoice')) {
            $invoice = $i;
        }
    }
    $attachment = $bill ?: ($receipt ?: ($invoice ?: (string)($row['attachment'] ?? '')));
    $pdo->prepare(
        'UPDATE accounts_expenses SET category=:c, amount=:a, payment_mode=:m, expense_date=:d,
         reference_no=:ref, remarks=:r, attachment=:f, attachment_bill=:b, attachment_receipt=:rc, attachment_invoice=:inv
         WHERE id=:id'
    )->execute([
        'c' => $category, 'a' => round($amount, 2), 'm' => $mode, 'd' => $date,
        'ref' => trim((string)($data['reference_no'] ?? '')) ?: null,
        'r' => trim((string)($data['remarks'] ?? '')) ?: null,
        'f' => $attachment ?: null, 'b' => $bill ?: null, 'rc' => $receipt ?: null, 'inv' => $invoice ?: null,
        'id' => $id,
    ]);
}

function acc_delete_expense(PDO $pdo, int $id): void
{
    $row = acc_get_expense($pdo, $id);
    if (!$row) {
        throw new InvalidArgumentException('Expense not found.');
    }
    if (!empty($row['source_type'])) {
        throw new InvalidArgumentException('Auto-generated expenses cannot be deleted here.');
    }
    foreach (['attachment', 'attachment_bill', 'attachment_receipt', 'attachment_invoice'] as $f) {
        if (!empty($row[$f])) {
            $path = dirname(__DIR__) . '/' . ltrim((string)$row[$f], '/');
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
    $pdo->prepare('DELETE FROM accounts_expenses WHERE id = :id')->execute(['id' => $id]);
}

function acc_list_expenses(PDO $pdo, array $filters = []): array
{
    acc_expense_ensure_schema($pdo);
    $sql = 'SELECT * FROM accounts_expenses WHERE 1=1';
    $params = [];
    $from = (string)($filters['from'] ?? '');
    $to = (string)($filters['to'] ?? '');
    $cat = acc_expense_normalize_category((string)($filters['category'] ?? ''));
    $mode = trim((string)($filters['payment_mode'] ?? ''));
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
    if ($mode !== '' && in_array($mode, ACC_PAYMENT_MODES, true)) {
        $sql .= ' AND payment_mode = :mode';
        $params['mode'] = $mode;
    }
    if ($q !== '') {
        $sql .= ' AND (remarks LIKE :q OR attachment LIKE :q OR reference_no LIKE :q OR created_by LIKE :q OR category LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY expense_date DESC, id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function acc_expense_totals(PDO $pdo, string $from, string $to): array
{
    acc_expense_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0) AS total,
                COALESCE(SUM(CASE WHEN expense_date >= DATE_FORMAT(CURDATE(), "%Y-%m-01") THEN amount ELSE 0 END),0) AS month_total
         FROM accounts_expenses WHERE expense_date >= :f AND expense_date <= :t'
    );
    $st->execute(['f' => $from, 't' => $to]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'month_total' => 0];
}

function acc_expense_dashboard_kpis(PDO $pdo): array
{
    acc_expense_ensure_schema($pdo);
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $row = $pdo->query(
        "SELECT
            COALESCE(SUM(CASE WHEN expense_date >= '{$monthStart}' THEN amount ELSE 0 END), 0) AS month_total,
            COALESCE(SUM(CASE WHEN expense_date = '{$today}' THEN amount ELSE 0 END), 0) AS today_total,
            COALESCE(SUM(CASE WHEN expense_date >= '{$monthStart}' AND category = 'Salary' THEN amount ELSE 0 END), 0) AS salary_month,
            COALESCE(SUM(CASE WHEN expense_date >= '{$monthStart}' AND category NOT IN ('Salary') THEN amount ELSE 0 END), 0) AS operational_month
         FROM accounts_expenses"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'month_total' => (float)($row['month_total'] ?? 0),
        'today_total' => (float)($row['today_total'] ?? 0),
        'salary_month' => (float)($row['salary_month'] ?? 0),
        'operational_month' => (float)($row['operational_month'] ?? 0),
    ];
}

function acc_expense_analytics(PDO $pdo): array
{
    acc_expense_ensure_schema($pdo);
    $monthStart = date('Y-m-01');
    $byCat = $pdo->query(
        "SELECT category, COALESCE(SUM(amount),0) AS amt FROM accounts_expenses
         WHERE expense_date >= " . $pdo->quote($monthStart) . "
         GROUP BY category ORDER BY amt DESC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $trend = $pdo->query(
        "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS amt
         FROM accounts_expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY ym ORDER BY ym ASC"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return ['by_category' => $byCat, 'trend' => $trend];
}

function acc_expense_attachment_label(array $row): string
{
    if (!empty($row['attachment_bill'])) {
        return basename((string)$row['attachment_bill']);
    }
    if (!empty($row['attachment_receipt'])) {
        return basename((string)$row['attachment_receipt']);
    }
    if (!empty($row['attachment_invoice'])) {
        return basename((string)$row['attachment_invoice']);
    }
    return !empty($row['attachment']) ? basename((string)$row['attachment']) : '—';
}

/** @return list<array{which: string, label: string, name: string}> */
function acc_expense_attachment_items(array $row): array
{
    $items = [];
    foreach (['bill' => 'attachment_bill', 'receipt' => 'attachment_receipt', 'invoice' => 'attachment_invoice'] as $which => $col) {
        if (!empty($row[$col])) {
            $items[] = [
                'which' => $which,
                'label' => ucfirst($which),
                'name' => basename((string)$row[$col]),
            ];
        }
    }
    if ($items === [] && !empty($row['attachment'])) {
        $items[] = ['which' => 'bill', 'label' => 'File', 'name' => basename((string)$row['attachment'])];
    }
    return $items;
}

function acc_expense_file_url(int $id, string $which = 'bill'): string
{
    return route_url('accounts/expense-file', ['id' => $id, 'which' => $which]);
}

function acc_expense_handle_export(PDO $pdo): void
{
    $export = strtolower(trim((string)($_GET['export'] ?? '')));
    if (!in_array($export, ['pdf', 'csv', 'print'], true)) {
        return;
    }
    $filters = [
        'from' => (string)($_GET['from'] ?? date('Y-m-01')),
        'to' => (string)($_GET['to'] ?? date('Y-m-d')),
        'category' => (string)($_GET['category'] ?? ''),
        'payment_mode' => (string)($_GET['payment_mode'] ?? ''),
        'q' => trim((string)($_GET['search'] ?? $_GET['q'] ?? '')),
        'id' => (int)($_GET['id'] ?? 0),
    ];
    $rows = acc_list_expenses($pdo, $filters);
    $headers = ['Date', 'Category', 'Amount', 'Mode', 'Reference', 'Remarks', 'Attachment', 'By'];
    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            (string)$r['expense_date'],
            (string)$r['category'],
            sales_format_money((float)$r['amount']),
            (string)$r['payment_mode'],
            (string)($r['reference_no'] ?? ''),
            (string)($r['remarks'] ?? ''),
            acc_expense_attachment_label($r),
            (string)($r['created_by'] ?? ''),
        ];
    }
    $title = 'Expenses Report';
    $totalAmt = 0.0;
    foreach ($rows as $r) {
        $totalAmt += (float)($r['amount'] ?? 0);
    }
    $printOpts = [
        'back_url' => route_url('accounts/expenses'),
        'subtitle' => 'Filtered expense report · ' . date('d M Y, H:i'),
        'kpis' => [
            ['Records', (string)count($rows)],
            ['Total Amount', sales_format_money($totalAmt), 'danger'],
        ],
        'meta' => array_filter([
            'Period' => trim($filters['from'] . ' to ' . $filters['to'], ' to'),
            'Category' => $filters['category'] !== '' ? $filters['category'] : '',
            'Payment mode' => $filters['payment_mode'] !== '' ? $filters['payment_mode'] : '',
            'Search' => $filters['q'] !== '' ? $filters['q'] : '',
        ]),
    ];
    if ($export === 'csv') {
        erp_send_csv('expenses-report.csv', $headers, $data);
    }
    erp_print_html_table($title, $headers, $data, $export === 'print', $printOpts);
    exit;
}

function acc_expense_export_toolbar(array $filters): string
{
    $qs = array_filter([
        'from' => $filters['from'] ?? '',
        'to' => $filters['to'] ?? '',
        'category' => $filters['category'] ?? '',
        'payment_mode' => $filters['payment_mode'] ?? '',
        'search' => $filters['q'] ?? '',
        'export' => 'pdf',
    ], static fn($v) => $v !== '');
    $pdf = route_url('accounts/expenses', array_merge($qs, ['export' => 'pdf']));
    $csv = route_url('accounts/expenses', array_merge($qs, ['export' => 'csv']));
    $print = route_url('accounts/expenses', array_merge($qs, ['export' => 'print']));
    return '<div class="erp-export-toolbar erp-export-toolbar--server">'
        . '<a class="btn btn-sm btn-outline-secondary" href="' . e($pdf) . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>'
        . '<a class="btn btn-sm btn-outline-secondary" href="' . e($csv) . '"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</a>'
        . '<a class="btn btn-sm btn-outline-secondary" href="' . e($print) . '" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>'
        . '</div>';
}
