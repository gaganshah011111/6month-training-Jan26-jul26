<?php
declare(strict_types=1);

require_once __DIR__ . '/accounts_finance.php';
require_once __DIR__ . '/erp_export.php';

const ACC_TX_TYPES = [
    '' => 'All types',
    'Customer Payment' => 'Customer Payment',
    'Supplier Payment' => 'Supplier Payment',
    'Salary Payment' => 'Salary Payment',
    'Expense' => 'Expense',
    'Loan Received' => 'Loan Received',
    'Loan Repayment' => 'Loan Repayment',
    'Manual Adjustment' => 'Cash Adjustment',
    'Opening Balance' => 'Opening Balance',
];

const ACC_TX_STATUSES = [
    '' => 'All statuses',
    'Completed' => 'Completed',
    'Pending' => 'Pending',
    'Partial' => 'Partial',
    'Paid' => 'Paid',
];

const ACC_TX_PAYMENT_MODES = [
    '' => 'All modes',
    'Cash' => 'Cash',
    'Bank Transfer' => 'Bank Transfer',
    'UPI' => 'UPI',
    'Cheque' => 'Cheque',
    'Card' => 'Card',
    'Online' => 'Online',
];

/** @return array<string, mixed> */
function acc_tx_parse_filters(array $input): array
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

    $txType = trim((string)($input['tx_type'] ?? $input['type'] ?? ''));
    if (!array_key_exists($txType, ACC_TX_TYPES)) {
        $txType = '';
    }

    $status = trim((string)($input['status'] ?? ''));
    if (!array_key_exists($status, ACC_TX_STATUSES)) {
        $status = '';
    }

    $party = trim((string)($input['party'] ?? ''));
    $mode = trim((string)($input['payment_mode'] ?? $input['mode'] ?? ''));
    if (!array_key_exists($mode, ACC_TX_PAYMENT_MODES)) {
        $mode = '';
    }

    $amountMin = trim((string)($input['amount_min'] ?? ''));
    $amountMax = trim((string)($input['amount_max'] ?? ''));
    $amin = $amountMin !== '' && is_numeric($amountMin) ? (float)$amountMin : null;
    $amax = $amountMax !== '' && is_numeric($amountMax) ? (float)$amountMax : null;
    if ($amin !== null && $amax !== null && $amin > $amax) {
        [$amin, $amax] = [$amax, $amin];
    }

    $q = trim((string)($input['search'] ?? $input['q'] ?? ''));

    return [
        'from' => $from,
        'to' => $to,
        'tx_type' => $txType,
        'status' => $status,
        'party' => $party,
        'payment_mode' => $mode,
        'amount_min' => $amin,
        'amount_max' => $amax,
        'search' => $q,
    ];
}

function acc_tx_direction_meta(string $txType, string $direction): array
{
    $inflowTypes = ['Customer Payment', 'Loan Received', 'Opening Balance'];
    $isIn = $direction === 'credit' || in_array($txType, $inflowTypes, true);
    if ($txType === 'Manual Adjustment') {
        $isIn = $direction === 'credit';
    }

    return [
        'direction' => $isIn ? 'in' : 'out',
        'label' => $isIn ? 'Money In' : 'Money Out',
        'icon' => $isIn ? 'bi-arrow-down-circle-fill' : 'bi-arrow-up-circle-fill',
        'cls' => $isIn ? 'acc-tx-dir--in' : 'acc-tx-dir--out',
    ];
}

function acc_tx_row_accent(string $txType): string
{
    return match ($txType) {
        'Customer Payment' => 'acc-tx-row--customer',
        'Supplier Payment' => 'acc-tx-row--supplier',
        'Salary Payment' => 'acc-tx-row--salary',
        'Expense' => 'acc-tx-row--expense',
        'Loan Received', 'Loan Repayment' => 'acc-tx-row--loan',
        default => 'acc-tx-row--other',
    };
}

function acc_tx_status_meta(string $status): array
{
    $s = trim($status) !== '' ? trim($status) : 'Completed';
    $pending = in_array($s, ['Pending', 'Partial', 'Unpaid'], true);

    return [
        'label' => $s,
        'cls' => $pending ? 'acc-tx-status--pending' : 'acc-tx-status--done',
    ];
}

/** @return array{label: string, url: string}|null */
function acc_tx_source_link(array $row): ?array
{
    $module = (string)($row['source_module'] ?? '');
    $type = (string)($row['source_type'] ?? '');
    $sid = (int)($row['source_id'] ?? 0);
    $txType = (string)($row['tx_type'] ?? '');

    if ($module === 'receivables' || $txType === 'Customer Payment') {
        return ['label' => 'Open Receivable', 'url' => route_url('accounts/receivables')];
    }
    if ($module === 'payables' || $txType === 'Supplier Payment') {
        return ['label' => 'Open Payable', 'url' => route_url('accounts/payables')];
    }
    if ($module === 'salary' || $txType === 'Salary Payment') {
        return ['label' => 'Open Salary Record', 'url' => route_url('accounts/salary-payments')];
    }
    if ($module === 'expenses' || $txType === 'Expense') {
        return ['label' => 'Open Expense', 'url' => route_url('accounts/expenses')];
    }
    if ($module === 'loan' || in_array($txType, ['Loan Received', 'Loan Repayment'], true)) {
        return ['label' => 'Open Treasury', 'url' => route_url('accounts/cashbook')];
    }
    if ($module === 'treasury' || in_array($txType, ['Opening Balance', 'Manual Adjustment'], true)) {
        return ['label' => 'Open Cash & Bank', 'url' => route_url('accounts/cashbook')];
    }

    return null;
}

/** @return list<string> */
function acc_tx_audit_trail(PDO $pdo, array $row): array
{
    $trail = [];
    $code = (string)($row['tx_code'] ?? $row['txid'] ?? '');
    $type = (string)($row['tx_type'] ?? '');
    $dir = (string)($row['direction'] ?? '');
    $amt = sales_format_money((float)($row['amount'] ?? 0));
    $trail[] = 'Transaction ' . $code . ' recorded as ' . $type . ' (' . strtoupper($dir ?: 'n/a') . ') for ' . $amt . '.';

    if (!empty($row['balance_before']) || !empty($row['balance_after'])) {
        $trail[] = 'Treasury balance: ' . sales_format_money((float)($row['balance_before'] ?? 0))
            . ' → ' . sales_format_money((float)($row['balance_after'] ?? 0)) . '.';
    }

    $by = trim((string)($row['created_by'] ?? ''));
    if ($by !== '') {
        $trail[] = 'Created by ' . $by . '.';
    }

    $at = (string)($row['created_at'] ?? '');
    if ($at !== '') {
        $trail[] = 'System timestamp: ' . $at . '.';
    }

    $remarks = trim((string)($row['remarks'] ?? ''));
    if ($remarks !== '') {
        $trail[] = 'Note: ' . $remarks . '.';
    }

    $module = (string)($row['source_module'] ?? '');
    if ($module !== '') {
        $trail[] = 'Source module: ' . ucfirst($module) . '.';
    }

    return $trail;
}

/** @param array<string, mixed> $row */
function acc_tx_normalize_row(PDO $pdo, array $row, bool $fromTreasury = true): array
{
    $txType = (string)($row['tx_type'] ?? '');
    $direction = (string)($row['direction'] ?? '');
    if (!$fromTreasury) {
        $direction = in_array($txType, ['Customer Payment', 'Loan Received', 'Opening Balance'], true) ? 'credit' : 'debit';
    }
    $dirMeta = acc_tx_direction_meta($txType, $direction);
    $statusMeta = acc_tx_status_meta((string)($row['tx_status'] ?? 'Completed'));
    $source = acc_tx_source_link($row);
    $dt = (string)($row['tx_date'] ?? '');
    $time = (string)($row['created_at'] ?? '');
    $dateTime = $time !== '' ? substr($time, 0, 16) : ($dt !== '' ? $dt . ' 00:00' : '—');

    return [
        'id' => (int)($row['id'] ?? 0),
        'tx_code' => (string)($row['tx_code'] ?? $row['txid'] ?? ''),
        'tx_date' => $dt,
        'tx_datetime' => $dateTime,
        'tx_type' => $txType,
        'direction' => $direction,
        'direction_meta' => $dirMeta,
        'party' => (string)($row['party'] ?? '—'),
        'reference_no' => (string)($row['reference_no'] ?? '—'),
        'amount' => (float)($row['amount'] ?? 0),
        'amount_fmt' => sales_format_money((float)($row['amount'] ?? 0)),
        'payment_mode' => (string)($row['payment_mode'] ?? '—'),
        'created_by' => (string)($row['created_by'] ?? 'System'),
        'tx_status' => (string)($row['tx_status'] ?? 'Completed'),
        'status_meta' => $statusMeta,
        'row_accent' => acc_tx_row_accent($txType),
        'source_module' => (string)($row['source_module'] ?? acc_tx_legacy_module($txType)),
        'source_type' => (string)($row['source_type'] ?? ''),
        'source_id' => (int)($row['source_id'] ?? 0),
        'source_link' => $source,
        'balance_before' => isset($row['balance_before']) ? (float)$row['balance_before'] : null,
        'balance_after' => isset($row['balance_after']) ? (float)$row['balance_after'] : null,
        'remarks' => (string)($row['remarks'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'audit_trail' => acc_tx_audit_trail($pdo, $row),
    ];
}

function acc_tx_legacy_module(string $txType): string
{
    return match ($txType) {
        'Customer Payment' => 'receivables',
        'Supplier Payment' => 'payables',
        'Salary Payment' => 'salary',
        'Expense' => 'expenses',
        default => 'finance',
    };
}

/** @return list<array<string, mixed>> */
function acc_tx_query_treasury(PDO $pdo, array $filters): array
{
    acc_treasury_ensure_schema($pdo);
    $sql = 'SELECT * FROM accounts_treasury_ledger WHERE tx_date >= :f AND tx_date <= :t';
    $params = ['f' => $filters['from'], 't' => $filters['to']];

    if ($filters['tx_type'] !== '') {
        $sql .= ' AND tx_type = :tt';
        $params['tt'] = $filters['tx_type'];
    }
    if ($filters['status'] !== '') {
        $sql .= ' AND tx_status = :st';
        $params['st'] = $filters['status'];
    }
    if ($filters['party'] !== '') {
        $sql .= ' AND party LIKE :party';
        $params['party'] = '%' . $filters['party'] . '%';
    }
    if ($filters['payment_mode'] !== '') {
        $sql .= ' AND payment_mode = :pm';
        $params['pm'] = $filters['payment_mode'];
    }
    if ($filters['amount_min'] !== null) {
        $sql .= ' AND amount >= :amin';
        $params['amin'] = $filters['amount_min'];
    }
    if ($filters['amount_max'] !== null) {
        $sql .= ' AND amount <= :amax';
        $params['amax'] = $filters['amount_max'];
    }
    if ($filters['search'] !== '') {
        $sql .= ' AND (party LIKE :q OR reference_no LIKE :q OR tx_code LIKE :q OR remarks LIKE :q OR tx_type LIKE :q)';
        $params['q'] = '%' . $filters['search'] . '%';
    }

    $sql .= ' ORDER BY tx_date DESC, id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, true> */
function acc_tx_treasury_source_keys(PDO $pdo, string $from, string $to): array
{
    $keys = [];
    $st = $pdo->prepare(
        'SELECT source_module, source_type, source_id FROM accounts_treasury_ledger
         WHERE tx_date >= :f AND tx_date <= :t AND source_module IS NOT NULL AND source_id > 0'
    );
    $st->execute(['f' => $from, 't' => $to]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $keys[acc_tx_source_key((string)$r['source_module'], (string)$r['source_type'], (int)$r['source_id'])] = true;
    }

    return $keys;
}

function acc_tx_source_key(string $module, string $type, int $id): string
{
    return strtolower($module) . '|' . strtolower($type) . '|' . $id;
}

/** @return list<array<string, mixed>> */
function acc_tx_legacy_rows(PDO $pdo, array $filters, array $knownKeys): array
{
    $legacy = acc_finance_transactions($pdo, ['from' => $filters['from'], 'to' => $filters['to']]);
    $rows = [];

    foreach ($legacy as $leg) {
        $txid = (string)($leg['txid'] ?? '');
        if (!preg_match('/^(CP|SP|EX|SL)-(\d+)$/', $txid, $m)) {
            continue;
        }
        $map = [
            'CP' => ['receivables', 'sales_payment', 'Customer Payment'],
            'SP' => ['payables', 'supplier_payment', 'Supplier Payment'],
            'EX' => ['expenses', 'expense', 'Expense'],
            'SL' => ['salary', 'salary_payment', 'Salary Payment'],
        ];
        [$mod, $stype, $txType] = $map[$m[1]];
        $sid = (int)$m[2];
        if (isset($knownKeys[acc_tx_source_key($mod, $stype, $sid)])) {
            continue;
        }

        $row = [
            'tx_code' => $txid,
            'txid' => $txid,
            'tx_date' => (string)$leg['tx_date'],
            'tx_type' => $txType,
            'party' => (string)$leg['party'],
            'reference_no' => (string)$leg['reference_no'],
            'amount' => (float)$leg['amount'],
            'payment_mode' => (string)$leg['payment_mode'],
            'tx_status' => (string)($leg['tx_status'] ?? 'Completed'),
            'source_module' => $mod,
            'source_type' => $stype,
            'source_id' => $sid,
            'created_by' => 'System',
        ];

        if ($filters['tx_type'] !== '' && $filters['tx_type'] !== $txType) {
            continue;
        }
        if ($filters['status'] !== '' && strcasecmp($filters['status'], (string)$row['tx_status']) !== 0) {
            continue;
        }
        if ($filters['party'] !== '' && stripos((string)$row['party'], $filters['party']) === false) {
            continue;
        }
        if ($filters['payment_mode'] !== '' && strcasecmp($filters['payment_mode'], (string)$row['payment_mode']) !== 0) {
            continue;
        }
        if ($filters['amount_min'] !== null && (float)$row['amount'] < $filters['amount_min']) {
            continue;
        }
        if ($filters['amount_max'] !== null && (float)$row['amount'] > $filters['amount_max']) {
            continue;
        }
        if ($filters['search'] !== '') {
            $hay = strtolower(implode(' ', [$txid, $txType, (string)$row['party'], (string)$row['reference_no']]));
            if (strpos($hay, strtolower($filters['search'])) === false) {
                continue;
            }
        }

        $rows[] = $row;
    }

    return $rows;
}

/** @return list<array<string, mixed>> */
function acc_tx_history_list(PDO $pdo, array $filters): array
{
    acc_ensure_schema($pdo);
    $treasury = acc_tx_query_treasury($pdo, $filters);
    $knownKeys = acc_tx_treasury_source_keys($pdo, $filters['from'], $filters['to']);
    $legacy = acc_tx_legacy_rows($pdo, $filters, $knownKeys);

    $merged = array_merge($treasury, $legacy);
    usort($merged, static function (array $a, array $b): int {
        $da = (string)($a['tx_date'] ?? '');
        $db = (string)($b['tx_date'] ?? '');
        $cmp = strcmp($db, $da);
        if ($cmp !== 0) {
            return $cmp;
        }

        return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
    });

    $out = [];
    foreach ($merged as $row) {
        $out[] = acc_tx_normalize_row($pdo, $row, isset($row['direction']));
    }

    return $out;
}

/** @param list<array<string, mixed>> $rows */
function acc_tx_history_kpis(array $rows): array
{
    $inflow = 0.0;
    $outflow = 0.0;
    $largest = 0.0;
    $today = date('Y-m-d');
    $todayCount = 0;
    $pending = 0;

    foreach ($rows as $r) {
        $amt = (float)($r['amount'] ?? 0);
        $largest = max($largest, $amt);
        if (($r['direction_meta']['direction'] ?? '') === 'in') {
            $inflow += $amt;
        } else {
            $outflow += $amt;
        }
        if (($r['tx_date'] ?? '') === $today) {
            ++$todayCount;
        }
        if (in_array((string)($r['tx_status'] ?? ''), ['Pending', 'Partial', 'Unpaid'], true)) {
            ++$pending;
        }
    }

    return [
        'total' => count($rows),
        'inflow' => round($inflow, 2),
        'outflow' => round($outflow, 2),
        'largest' => round($largest, 2),
        'today' => $todayCount,
        'pending' => $pending,
    ];
}

/** @param list<array<string, mixed>> $rows */
function acc_tx_history_analytics(array $rows): array
{
    $byType = [];
    $monthly = [];
    $today = date('Y-m');

    foreach ($rows as $r) {
        $type = (string)($r['tx_type'] ?? 'Other');
        $amt = (float)($r['amount'] ?? 0);
        $byType[$type] = ($byType[$type] ?? 0) + $amt;

        $ym = substr((string)($r['tx_date'] ?? ''), 0, 7);
        if ($ym === '') {
            $ym = $today;
        }
        if (!isset($monthly[$ym])) {
            $monthly[$ym] = ['ym' => $ym, 'count' => 0, 'inflow' => 0.0, 'outflow' => 0.0];
        }
        ++$monthly[$ym]['count'];
        if (($r['direction_meta']['direction'] ?? '') === 'in') {
            $monthly[$ym]['inflow'] += $amt;
        } else {
            $monthly[$ym]['outflow'] += $amt;
        }
    }

    arsort($byType);
    $typeRows = [];
    foreach ($byType as $type => $amount) {
        $typeRows[] = ['type' => $type, 'amount' => round($amount, 2)];
    }

    ksort($monthly);

    return [
        'by_type' => $typeRows,
        'inflow' => array_sum(array_map(static fn($r) => ($r['direction_meta']['direction'] ?? '') === 'in' ? (float)$r['amount'] : 0, $rows)),
        'outflow' => array_sum(array_map(static fn($r) => ($r['direction_meta']['direction'] ?? '') === 'out' ? (float)$r['amount'] : 0, $rows)),
        'monthly' => array_values($monthly),
    ];
}

/** @return array{filters: array, rows: list, kpis: array, analytics: array} */
function acc_tx_history_bundle(PDO $pdo, array $input): array
{
    $filters = acc_tx_parse_filters($input);
    $rows = acc_tx_history_list($pdo, $filters);

    return [
        'filters' => $filters,
        'rows' => $rows,
        'kpis' => acc_tx_history_kpis($rows),
        'analytics' => acc_tx_history_analytics($rows),
    ];
}

function acc_tx_history_handle_export(PDO $pdo): void
{
    $export = strtolower(trim((string)($_GET['export'] ?? '')));
    if (!in_array($export, ['pdf', 'csv', 'print', 'excel'], true)) {
        return;
    }
    if ($export === 'excel') {
        $export = 'csv';
    }

    $bundle = acc_tx_history_bundle($pdo, $_GET);
    $filters = $bundle['filters'];
    $rows = $bundle['rows'];
    $kpis = $bundle['kpis'];

    $singleTx = trim((string)($_GET['tx'] ?? ''));
    if ($singleTx !== '') {
        $rows = array_values(array_filter($rows, static fn($r) => ($r['tx_code'] ?? '') === $singleTx));
    }

    $headers = ['Transaction ID', 'Date & Time', 'Type', 'Direction', 'Party', 'Reference', 'Amount', 'Mode', 'Created By', 'Status'];
    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            (string)$r['tx_code'],
            (string)$r['tx_datetime'],
            (string)$r['tx_type'],
            (string)($r['direction_meta']['label'] ?? ''),
            (string)$r['party'],
            (string)$r['reference_no'],
            (string)$r['amount_fmt'],
            (string)$r['payment_mode'],
            (string)$r['created_by'],
            (string)$r['tx_status'],
        ];
    }

    $title = $singleTx !== '' ? 'Transaction ' . $singleTx : 'Financial Audit Log';
    $printOpts = [
        'back_url' => route_url('accounts/transactions-history', array_filter([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'tx_type' => $filters['tx_type'] ?: null,
            'status' => $filters['status'] ?: null,
            'party' => $filters['party'] ?: null,
            'payment_mode' => $filters['payment_mode'] ?: null,
            'search' => $filters['search'] ?: null,
        ])),
        'subtitle' => 'Master financial audit trail · ' . $filters['from'] . ' to ' . $filters['to'],
        'kpis' => [
            ['Transactions', (string)$kpis['total'], 'primary'],
            ['Inflow', sales_format_money((float)$kpis['inflow']), 'ok'],
            ['Outflow', sales_format_money((float)$kpis['outflow']), 'danger'],
        ],
        'meta' => [
            'Period' => $filters['from'] . ' — ' . $filters['to'],
            'Type' => $filters['tx_type'] !== '' ? $filters['tx_type'] : 'All',
            'Status' => $filters['status'] !== '' ? $filters['status'] : 'All',
        ],
    ];

    if ($export === 'csv') {
        erp_send_csv('financial-audit-log.csv', $headers, $data);
    }
    erp_print_html_table($title, $headers, $data, $export === 'print', $printOpts);
    exit;
}
