<?php
declare(strict_types=1);

require_once __DIR__ . '/sales_finance.php';
require_once __DIR__ . '/erp_export.php';
if (!function_exists('acc_payment_meta')) {
    require_once __DIR__ . '/accounts_finance.php';
}

function acc_ledger_ensure_inventory(): void
{
    if (!function_exists('inv_supplier_ledger_list')) {
        require_once __DIR__ . '/inventory_purchase.php';
    }
}

/** Customer ledger list from invoices + payments (not only outstanding). */
function acc_customer_ledger_list(PDO $pdo, array $filters = []): array
{
    if (!dh_table_exists($pdo, 'sales_customers')) {
        return [];
    }
    $hasInv = dh_table_exists($pdo, 'sales_invoices');
    $hasPay = dh_table_exists($pdo, 'sales_payments');

    $sql = 'SELECT c.id, c.company_name, c.customer_code, c.phone, c.email, c.gst_number,
            COALESCE(inv.total_invoiced, 0) AS total_invoiced,
            COALESCE(inv.total_paid_inv, 0) AS total_paid_inv,
            COALESCE(pay.total_paid_pay, 0) AS total_paid_pay,
            pay.last_payment
        FROM sales_customers c';
    if ($hasInv) {
        $sql .= ' LEFT JOIN (
            SELECT customer_id,
                SUM(total_amount) AS total_invoiced,
                SUM(amount_paid) AS total_paid_inv
            FROM sales_invoices GROUP BY customer_id
        ) inv ON inv.customer_id = c.id';
    } else {
        $sql .= ' LEFT JOIN (SELECT NULL AS customer_id, 0 AS total_invoiced, 0 AS total_paid_inv) inv ON 1=0';
    }
    if ($hasPay) {
        $sql .= ' LEFT JOIN (
            SELECT customer_id, SUM(amount) AS total_paid_pay, MAX(payment_date) AS last_payment
            FROM sales_payments GROUP BY customer_id
        ) pay ON pay.customer_id = c.id';
    } else {
        $sql .= ' LEFT JOIN (SELECT NULL AS customer_id, 0 AS total_paid_pay, NULL AS last_payment) pay ON 1=0';
    }

    $sql .= ' WHERE 1=1';
    $params = [];

    $cid = (int)($filters['customer_id'] ?? 0);
    if ($cid > 0) {
        $sql .= ' AND c.id = :cid';
        $params['cid'] = $cid;
    }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $sql .= ' AND (c.company_name LIKE :q OR c.customer_code LIKE :q OR c.phone LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    $sql .= ' ORDER BY c.company_name ASC';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $out = [];
    foreach ($rows as $r) {
        $invoiced = (float)($r['total_invoiced'] ?? 0);
        $paidInv = (float)($r['total_paid_inv'] ?? 0);
        $paidPay = (float)($r['total_paid_pay'] ?? 0);
        $paid = max($paidInv, $paidPay);
        $r['total_paid'] = $paid;
        $pending = max(0, round($invoiced - $paid, 2));
        $r['pending'] = $pending;

        if ($invoiced <= 0.01 && $paidPay <= 0.01) {
            continue;
        }

        $status = 'Unpaid';
        if ($invoiced <= 0.01 && $paidPay > 0.01) {
            $status = 'Paid';
        } elseif ($pending <= 0.01 && $invoiced > 0.01) {
            $status = 'Paid';
        } elseif ($paid > 0.01 && $pending > 0.01) {
            $status = 'Partial';
        }
        $r['status_label'] = $status;
        $r['status_meta'] = acc_payment_meta($status);
        $r['last_payment'] = $r['last_payment'] ? (string)$r['last_payment'] : '—';

        if (!acc_ledger_match_status($status, (string)($filters['status'] ?? ''))) {
            continue;
        }
        if (!acc_ledger_match_dates((string)($r['last_payment'] ?? ''), $filters)) {
            continue;
        }
        $out[] = $r;
    }

    usort($out, static function ($a, $b) {
        $cmp = ((float)$b['pending']) <=> ((float)$a['pending']);
        return $cmp !== 0 ? $cmp : strcmp((string)$a['company_name'], (string)$b['company_name']);
    });

    return $out;
}

function acc_ledger_match_status(string $actual, string $filter): bool
{
    if ($filter === '' || !in_array($filter, ['Paid', 'Partial', 'Unpaid'], true)) {
        return true;
    }
    return $actual === $filter;
}

/** @param array{from?: string, to?: string} $filters */
function acc_ledger_match_dates(string $dateStr, array $filters): bool
{
    $from = (string)($filters['from'] ?? '');
    $to = (string)($filters['to'] ?? '');
    if ($from === '' && $to === '') {
        return true;
    }
    if ($dateStr === '' || $dateStr === '—' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $dateStr)) {
        return $from === '';
    }
    $d = substr($dateStr, 0, 10);
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && $d < $from) {
        return false;
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) && $d > $to) {
        return false;
    }
    return true;
}

/** Supplier ledger list with filters. */
function acc_supplier_ledger_list(PDO $pdo, array $filters = []): array
{
    acc_ledger_ensure_inventory();
    inv_purchase_ensure_schema($pdo);
    $q = trim((string)($filters['q'] ?? ''));
    $rows = inv_supplier_ledger_list($pdo, $q);
    $sid = (int)($filters['supplier_id'] ?? 0);

    $lastPayMap = [];
    if (inv_table_exists($pdo, 'purchase_payments')) {
        $payRows = $pdo->query(
            "SELECT i.supplier_id, MAX(p.payment_date) AS last_payment
             FROM purchase_payments p
             JOIN stock_inward i ON i.id = p.inward_id
             GROUP BY i.supplier_id"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($payRows as $pr) {
            $lastPayMap[(int)$pr['supplier_id']] = (string)$pr['last_payment'];
        }
    }

    $out = [];
    foreach ($rows as $r) {
        if ($sid > 0 && (int)$r['id'] !== $sid) {
            continue;
        }
        $pending = (float)($r['pending_balance'] ?? 0);
        $paid = (float)($r['total_paid'] ?? 0);
        $purchased = (float)($r['total_purchased'] ?? 0);
        if ($sid === 0 && $purchased <= 0.01 && $paid <= 0.01) {
            continue;
        }
        $status = $pending <= inv_purchase_tolerance() ? 'Paid' : ($paid > inv_purchase_tolerance() ? 'Partial' : 'Unpaid');
        if (!acc_ledger_match_status($status, (string)($filters['status'] ?? ''))) {
            continue;
        }
        $lastPay = $lastPayMap[(int)$r['id']] ?? ($r['last_purchase_date'] ?? '—');
        if (!acc_ledger_match_dates($lastPay, $filters)) {
            continue;
        }
        $r['supplier_code'] = 'SUP-' . str_pad((string)(int)$r['id'], 4, '0', STR_PAD_LEFT);
        $r['last_payment'] = $lastPay ?: '—';
        $r['status_label'] = $status;
        $r['status_meta'] = acc_payment_meta($status);
        $r['pending'] = $pending;
        $out[] = $r;
    }

    if ($sid > 0 && $out === []) {
        $st = $pdo->prepare('SELECT * FROM suppliers WHERE id = :id LIMIT 1');
        $st->execute(['id' => $sid]);
        $sup = $st->fetch(PDO::FETCH_ASSOC);
        if ($sup) {
            $out[] = [
                'id' => (int)$sup['id'],
                'name' => (string)$sup['name'],
                'contact_person' => (string)($sup['contact_person'] ?? ''),
                'phone' => (string)($sup['phone'] ?? ''),
                'gst_number' => (string)($sup['gst_number'] ?? ''),
                'total_purchased' => 0.0,
                'total_paid' => 0.0,
                'pending_balance' => 0.0,
                'pending' => 0.0,
                'last_payment' => '—',
                'supplier_code' => 'SUP-' . str_pad((string)(int)$sup['id'], 4, '0', STR_PAD_LEFT),
                'status_label' => 'Unpaid',
                'status_meta' => acc_payment_meta('Unpaid'),
            ];
        }
    }

    return $out;
}

/** @return array{customer: ?array, summary: array, invoices: list, payments: list, ledger: list} */
function acc_customer_ledger_detail(PDO $pdo, int $customerId): array
{
    $customer = sales_get_customer($pdo, $customerId);
    if (!$customer) {
        return ['customer' => null, 'summary' => [], 'invoices' => [], 'payments' => [], 'ledger' => []];
    }

    $invoices = [];
    if (dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->prepare(
            'SELECT i.*, o.so_number FROM sales_invoices i
             LEFT JOIN sales_orders o ON o.id = i.order_id
             WHERE i.customer_id = :cid ORDER BY i.invoice_date DESC, i.id DESC'
        );
        $st->execute(['cid' => $customerId]);
        $invoices = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($invoices as &$inv) {
            $inv['balance'] = max(0, round((float)$inv['total_amount'] - (float)$inv['amount_paid'], 2));
            $inv['status_meta'] = acc_payment_meta(
                (float)$inv['amount_paid'] >= (float)$inv['total_amount'] - 0.01 ? 'Paid'
                    : ((float)$inv['amount_paid'] > 0.01 ? 'Partial' : 'Unpaid')
            );
        }
        unset($inv);
    }

    $payments = [];
    if (dh_table_exists($pdo, 'sales_payments')) {
        $st = $pdo->prepare(
            'SELECT p.*, i.invoice_no FROM sales_payments p
             INNER JOIN sales_invoices i ON i.id = p.invoice_id
             WHERE p.customer_id = :cid ORDER BY p.payment_date DESC, p.id DESC'
        );
        $st->execute(['cid' => $customerId]);
        $payments = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $entries = [];
    $balance = 0.0;
    if (dh_table_exists($pdo, 'sales_invoices')) {
        $st = $pdo->prepare(
            'SELECT invoice_date AS dt, invoice_no AS ref, total_amount AS amt, id
             FROM sales_invoices WHERE customer_id = :cid ORDER BY invoice_date ASC, id ASC'
        );
        $st->execute(['cid' => $customerId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $inv) {
            $debit = (float)$inv['amt'];
            $balance += $debit;
            $entries[] = ['sort' => $inv['dt'] . '-D-' . $inv['id'], 'date' => $inv['dt'], 'ref' => 'INV ' . $inv['ref'], 'debit' => $debit, 'credit' => 0.0, 'balance' => $balance];
        }
    }
    if (dh_table_exists($pdo, 'sales_payments')) {
        $st = $pdo->prepare(
            'SELECT p.payment_date AS dt, p.amount AS amt, p.id, p.reference_no, i.invoice_no
             FROM sales_payments p
             INNER JOIN sales_invoices i ON i.id = p.invoice_id
             WHERE p.customer_id = :cid ORDER BY p.payment_date ASC, p.id ASC'
        );
        $st->execute(['cid' => $customerId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $p) {
            $credit = (float)$p['amt'];
            $balance -= $credit;
            $ref = 'RCP ' . ($p['reference_no'] ?: ('PAY-' . $p['id'])) . ' · ' . $p['invoice_no'];
            $entries[] = ['sort' => $p['dt'] . '-C-' . $p['id'], 'date' => $p['dt'], 'ref' => $ref, 'debit' => 0.0, 'credit' => $credit, 'balance' => $balance];
        }
    }
    usort($entries, static fn($a, $b) => strcmp((string)$a['sort'], (string)$b['sort']));
    $running = 0.0;
    $ledger = [];
    foreach ($entries as $e) {
        $running += (float)$e['debit'];
        $running -= (float)$e['credit'];
        $ledger[] = [
            'date' => $e['date'],
            'ref' => $e['ref'],
            'debit' => (float)$e['debit'],
            'credit' => (float)$e['credit'],
            'balance' => round($running, 2),
        ];
    }

    $totalInvoiced = array_sum(array_map(static fn($i) => (float)$i['total_amount'], $invoices));
    $totalPaid = array_sum(array_map(static fn($p) => (float)$p['amount'], $payments));
    if ($totalPaid < 0.01) {
        $totalPaid = array_sum(array_map(static fn($i) => (float)$i['amount_paid'], $invoices));
    }
    $outstanding = max(0, round($totalInvoiced - $totalPaid, 2));
    $lastPay = $payments[0]['payment_date'] ?? null;

    return [
        'customer' => $customer,
        'summary' => [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'outstanding' => $outstanding,
            'last_payment' => $lastPay ?: '—',
            'status' => $outstanding <= 0.01 && $totalInvoiced > 0.01 ? 'Paid' : ($totalPaid > 0.01 ? ($outstanding > 0.01 ? 'Partial' : 'Paid') : 'Unpaid'),
        ],
        'invoices' => $invoices,
        'payments' => $payments,
        'ledger' => $ledger,
    ];
}

/** @return array{supplier: ?array, summary: array, purchases: list, payments: list, ledger: list} */
function acc_supplier_ledger_detail(PDO $pdo, int $supplierId): array
{
    acc_ledger_ensure_inventory();
    inv_purchase_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM suppliers WHERE id = :id LIMIT 1');
    $st->execute(['id' => $supplierId]);
    $supplier = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$supplier) {
        return ['supplier' => null, 'summary' => [], 'purchases' => [], 'payments' => [], 'ledger' => []];
    }
    $supplier['supplier_code'] = 'SUP-' . str_pad((string)$supplierId, 4, '0', STR_PAD_LEFT);

    $purchases = inv_purchase_list($pdo, ['supplier_id' => $supplierId, 'limit' => 500]);
    $payments = inv_purchase_list_payments($pdo, null, ['supplier_id' => $supplierId, 'limit' => 500]);

    $entries = [];
    foreach ($purchases as $p) {
        $entries[] = [
            'sort' => ($p['inward_date'] ?? '') . '-D-' . ($p['id'] ?? 0),
            'date' => (string)($p['inward_date'] ?? ''),
            'ref' => (string)($p['pinv_no'] ?? 'PINV'),
            'debit' => (float)($p['total_amount'] ?? 0),
            'credit' => 0.0,
        ];
    }
    foreach ($payments as $p) {
        $entries[] = [
            'sort' => ($p['payment_date'] ?? '') . '-C-' . ($p['id'] ?? 0),
            'date' => (string)($p['payment_date'] ?? ''),
            'ref' => 'PAY ' . ($p['payment_ref'] ?? ('#' . ($p['id'] ?? ''))) . ' · ' . ($p['pinv_no'] ?? ''),
            'debit' => 0.0,
            'credit' => (float)($p['amount'] ?? 0),
        ];
    }
    usort($entries, static fn($a, $b) => strcmp((string)$a['sort'], (string)$b['sort']));
    $running = 0.0;
    $ledger = [];
    foreach ($entries as $e) {
        $running += (float)$e['debit'];
        $running -= (float)$e['credit'];
        $ledger[] = [
            'date' => $e['date'],
            'ref' => $e['ref'],
            'debit' => (float)$e['debit'],
            'credit' => (float)$e['credit'],
            'balance' => round($running, 2),
        ];
    }

    $totalPurchased = (float)array_sum(array_map(static fn($p) => (float)($p['total_amount'] ?? 0), $purchases));
    $totalPaid = (float)array_sum(array_map(static fn($p) => (float)($p['amount'] ?? 0), $payments));
    if ($totalPaid < 0.01) {
        $totalPaid = (float)array_sum(array_map(static fn($p) => (float)($p['paid_amount'] ?? 0), $purchases));
    }
    $pending = max(0, round($totalPurchased - $totalPaid, 2));
    $lastPay = $payments[0]['payment_date'] ?? '—';

    return [
        'supplier' => $supplier,
        'summary' => [
            'total_purchased' => $totalPurchased,
            'total_paid' => $totalPaid,
            'pending' => $pending,
            'last_payment' => $lastPay,
            'status' => $pending <= inv_purchase_tolerance() ? 'Paid' : ($totalPaid > inv_purchase_tolerance() ? 'Partial' : 'Unpaid'),
        ],
        'purchases' => $purchases,
        'payments' => $payments,
        'ledger' => $ledger,
    ];
}

function acc_ledger_handle_export(PDO $pdo, string $scope): void
{
    $export = strtolower(trim((string)($_GET['export'] ?? '')));
    if (!in_array($export, ['pdf', 'csv', 'print'], true)) {
        return;
    }

    $type = (string)($_GET['ledger_type'] ?? 'customer');
    $filters = [
        'customer_id' => (int)($_GET['customer_id'] ?? 0),
        'supplier_id' => (int)($_GET['supplier_id'] ?? 0),
        'status' => trim((string)($_GET['status'] ?? '')),
        'from' => trim((string)($_GET['from'] ?? '')),
        'to' => trim((string)($_GET['to'] ?? '')),
        'q' => trim((string)($_GET['search'] ?? $_GET['q'] ?? '')),
    ];

    $printOpts = ['tagline' => 'Accounts & Finance'];

    if ($scope === 'detail') {
        if ($type === 'supplier') {
            $sid = (int)($_GET['supplier_id'] ?? 0);
            $detail = acc_supplier_ledger_detail($pdo, $sid);
            $sup = $detail['supplier'] ?? [];
            $sum = $detail['summary'] ?? [];
            $title = 'Supplier Ledger — ' . (string)($sup['name'] ?? '');
            $headers = ['Date', 'Reference', 'Debit', 'Credit', 'Balance'];
            $data = [];
            foreach ($detail['ledger'] as $r) {
                $data[] = [
                    $r['date'],
                    $r['ref'],
                    (float)$r['debit'] > 0 ? sales_format_money((float)$r['debit']) : '',
                    (float)$r['credit'] > 0 ? sales_format_money((float)$r['credit']) : '',
                    sales_format_money((float)$r['balance']),
                ];
            }
            $printOpts['back_url'] = route_url('accounts/supplier-ledger-detail', ['supplier_id' => $sid]);
            $printOpts['subtitle'] = 'Supplier ledger statement · ' . date('d M Y, H:i');
            $printOpts['kpis'] = [
                ['Total Purchases', sales_format_money((float)($sum['total_purchased'] ?? 0))],
                ['Total Paid', sales_format_money((float)($sum['total_paid'] ?? 0)), 'success'],
                ['Outstanding', sales_format_money((float)($sum['pending'] ?? 0)), 'danger'],
                ['Status', (string)($sum['status'] ?? '—')],
            ];
            $printOpts['meta'] = array_filter([
                'Phone' => (string)($sup['phone'] ?? ''),
                'GST' => (string)($sup['gst_number'] ?? ''),
            ]);
        } else {
            $cid = (int)($_GET['customer_id'] ?? 0);
            $detail = acc_customer_ledger_detail($pdo, $cid);
            $cust = $detail['customer'] ?? [];
            $sum = $detail['summary'] ?? [];
            $title = 'Customer Ledger — ' . (string)($cust['company_name'] ?? '');
            $headers = ['Date', 'Reference', 'Debit', 'Credit', 'Balance'];
            $data = [];
            foreach ($detail['ledger'] as $r) {
                $data[] = [
                    $r['date'],
                    $r['ref'],
                    (float)$r['debit'] > 0 ? sales_format_money((float)$r['debit']) : '',
                    (float)$r['credit'] > 0 ? sales_format_money((float)$r['credit']) : '',
                    sales_format_money((float)$r['balance']),
                ];
            }
            $printOpts['back_url'] = route_url('accounts/customer-ledger-detail', ['customer_id' => $cid]);
            $printOpts['subtitle'] = 'Code ' . (string)($cust['customer_code'] ?? '') . ' · ' . date('d M Y, H:i');
            $printOpts['kpis'] = [
                ['Total Invoiced', sales_format_money((float)($sum['total_invoiced'] ?? 0))],
                ['Total Paid', sales_format_money((float)($sum['total_paid'] ?? 0)), 'success'],
                ['Outstanding', sales_format_money((float)($sum['outstanding'] ?? 0)), 'danger'],
                ['Status', (string)($sum['status'] ?? '—')],
            ];
            $printOpts['meta'] = array_filter([
                'Contact' => (string)($cust['contact_person'] ?? ''),
                'Phone' => (string)($cust['phone'] ?? ''),
                'GST' => (string)($cust['gst_number'] ?? ''),
            ]);
        }
    } elseif ($type === 'supplier') {
        $rows = acc_supplier_ledger_list($pdo, $filters);
        $title = 'Supplier Ledger';
        $headers = ['Supplier', 'Purchased', 'Paid', 'Pending', 'Last Payment', 'Status'];
        $data = [];
        $totPending = 0.0;
        foreach ($rows as $r) {
            $pending = (float)($r['pending_balance'] ?? $r['pending'] ?? 0);
            $totPending += $pending;
            $data[] = [
                (string)$r['name'],
                sales_format_money((float)$r['total_purchased']),
                sales_format_money((float)$r['total_paid']),
                sales_format_money($pending),
                (string)($r['last_payment'] ?? '—'),
                (string)($r['status_label'] ?? ''),
            ];
        }
        $printOpts['back_url'] = route_url('accounts/supplier-ledger');
        $printOpts['subtitle'] = count($rows) . ' suppliers · ' . date('d M Y, H:i');
        $printOpts['kpis'] = [
            ['Suppliers', (string)count($rows)],
            ['Total Pending', sales_format_money($totPending), 'danger'],
        ];
    } else {
        $rows = acc_customer_ledger_list($pdo, $filters);
        $title = 'Customer Ledger';
        $headers = ['Customer', 'Invoiced', 'Paid', 'Pending', 'Last Payment', 'Status'];
        $data = [];
        $totPending = 0.0;
        foreach ($rows as $r) {
            $pending = (float)$r['pending'];
            $totPending += $pending;
            $data[] = [
                (string)$r['company_name'],
                sales_format_money((float)$r['total_invoiced']),
                sales_format_money((float)$r['total_paid']),
                sales_format_money($pending),
                (string)($r['last_payment'] ?? '—'),
                (string)($r['status_label'] ?? ''),
            ];
        }
        $printOpts['back_url'] = route_url('accounts/ledger');
        $printOpts['subtitle'] = count($rows) . ' customers · ' . date('d M Y, H:i');
        $printOpts['kpis'] = [
            ['Customers', (string)count($rows)],
            ['Total Outstanding', sales_format_money($totPending), 'danger'],
        ];
    }

    $meta = [];
    if ($filters['from'] !== '' || $filters['to'] !== '') {
        $meta['Period'] = trim($filters['from'] . ' to ' . $filters['to'], ' to');
    }
    if ($filters['status'] !== '') {
        $meta['Status filter'] = $filters['status'];
    }
    if ($filters['q'] !== '') {
        $meta['Search'] = $filters['q'];
    }
    if ($meta !== []) {
        $printOpts['meta'] = array_merge($printOpts['meta'] ?? [], $meta);
    }

    if ($export === 'csv') {
        erp_send_csv(preg_replace('/[^a-z0-9_-]/i', '-', strtolower($title)) . '.csv', $headers, $data);
    }
    erp_print_html_table($title, $headers, $data, $export === 'print', $printOpts);
    exit;
}

function acc_ledger_export_qs(string $page, array $extra = []): array
{
    $qs = array_filter([
        'customer_id' => (int)($_GET['customer_id'] ?? 0) ?: null,
        'supplier_id' => (int)($_GET['supplier_id'] ?? 0) ?: null,
        'status' => trim((string)($_GET['status'] ?? '')),
        'from' => trim((string)($_GET['from'] ?? '')),
        'to' => trim((string)($_GET['to'] ?? '')),
        'search' => trim((string)($_GET['search'] ?? $_GET['q'] ?? '')),
    ], static fn($v) => $v !== null && $v !== '');
    return array_merge($qs, $extra);
}

function acc_ledger_export_toolbar(string $pageRoute, string $ledgerType, string $scope = 'list', string $filename = 'ledger'): string
{
    $qs = array_merge(acc_ledger_export_qs($pageRoute), [
        'ledger_type' => $ledgerType,
        'export_scope' => $scope,
    ]);
    $pdfQs = array_merge($qs, ['export' => 'pdf']);
    $csvQs = array_merge($qs, ['export' => 'csv']);
    $printQs = array_merge($qs, ['export' => 'print']);

    return '<div class="erp-export-toolbar erp-export-toolbar--server">'
        . '<a class="btn btn-sm btn-outline-secondary" href="' . e(route_url($pageRoute, $pdfQs)) . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>'
        . '<a class="btn btn-sm btn-outline-secondary" href="' . e(route_url($pageRoute, $csvQs)) . '"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</a>'
        . '<a class="btn btn-sm btn-outline-secondary" href="' . e(route_url($pageRoute, $printQs)) . '" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>'
        . '</div>';
}
