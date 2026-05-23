<?php
declare(strict_types=1);

require_once __DIR__ . '/employee_list_service.php';
require_once __DIR__ . '/hr_reports_service.php';

function emp_inc_per_page_default(): int
{
    return 10;
}

/** @return list<int> */
function emp_inc_per_page_allowed(): array
{
    return [5, 10, 12, 25, 50];
}

/** @return array<string, mixed> */
function emp_inc_parse_filters(array $input): array
{
    $from = trim((string)($input['inc_from'] ?? ''));
    $to = trim((string)($input['inc_to'] ?? ''));
    if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = '';
    }
    if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = '';
    }
    if ($from !== '' && $to !== '' && $from > $to) {
        [$from, $to] = [$to, $from];
    }

    $default = emp_inc_per_page_default();
    $perPage = (int)($input['inc_per_page'] ?? $default);
    if (!in_array($perPage, emp_inc_per_page_allowed(), true)) {
        $perPage = $default;
    }

    return [
        'q' => trim((string)($input['inc_q'] ?? '')),
        'from' => $from,
        'to' => $to,
        'per_page' => $perPage,
        'page' => max(1, (int)($input['inc_p'] ?? 1)),
    ];
}

/** @return array{0:string,1:array<string,mixed>} */
function emp_inc_where_sql(array $filters): array
{
    $sql = ' WHERE 1=1';
    $params = [];

    if ($filters['q'] !== '') {
        $needle = '%' . $filters['q'] . '%';
        $sql .= ' AND (
            e.full_name LIKE :iq_name
            OR e.employee_code LIKE :iq_code
            OR COALESCE(si.reason, \'\') LIKE :iq_reason
        )';
        $params['iq_name'] = $needle;
        $params['iq_code'] = $needle;
        $params['iq_reason'] = $needle;
    }

    if ($filters['from'] !== '') {
        $sql .= ' AND si.effective_date >= :iq_from';
        $params['iq_from'] = $filters['from'];
    }

    if ($filters['to'] !== '') {
        $sql .= ' AND si.effective_date <= :iq_to';
        $params['iq_to'] = $filters['to'];
    }

    return [$sql, $params];
}

function emp_inc_from_sql(): string
{
    return ' FROM salary_increments si
        INNER JOIN employees e ON e.id = si.employee_id
        LEFT JOIN departments d ON d.id = e.department_id ';
}

/** @param array<string, mixed> $params */
function emp_inc_bind_params(PDOStatement $stmt, array $params): void
{
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
}

/** @return array{total:int,rows:list<array<string,mixed>>} */
function emp_inc_fetch(PDO $pdo, array $filters, ?int $limit = null, ?int $offset = null): array
{
    [$where, $params] = emp_inc_where_sql($filters);
    $from = emp_inc_from_sql();

    $countStmt = $pdo->prepare('SELECT COUNT(*)' . $from . $where);
    emp_inc_bind_params($countStmt, $params);
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $sql = 'SELECT si.*, e.full_name, e.employee_code,
        COALESCE(d.department_name, e.department, \'—\') AS department_name'
        . $from . $where . ' ORDER BY si.effective_date DESC, si.id DESC';

    if ($limit !== null) {
        $sql .= ' LIMIT :iq_lim OFFSET :iq_off';
    }

    $stmt = $pdo->prepare($sql);
    emp_inc_bind_params($stmt, $params);
    if ($limit !== null) {
        $stmt->bindValue(':iq_lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':iq_off', max(0, $offset ?? 0), PDO::PARAM_INT);
    }
    $stmt->execute();

    return ['total' => $total, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

/** @return array<string, string|null> */
function emp_inc_query_params(array $incFilters, array $extra = []): array
{
    $default = emp_inc_per_page_default();
    $merged = array_merge($incFilters, $extra);

    return [
        'page' => 'employees/list',
        'inc_q' => ($merged['q'] ?? '') !== '' ? $merged['q'] : null,
        'inc_from' => ($merged['from'] ?? '') !== '' ? $merged['from'] : null,
        'inc_to' => ($merged['to'] ?? '') !== '' ? $merged['to'] : null,
        'inc_per_page' => (int)($merged['per_page'] ?? $default) !== $default ? (string)(int)$merged['per_page'] : null,
        'inc_p' => (int)($merged['page'] ?? 1) > 1 ? (string)(int)$merged['page'] : null,
        'inc_export' => isset($merged['inc_export']) ? (string)$merged['inc_export'] : null,
    ];
}

/** @param array<string, mixed> $empFilters */
function emp_inc_build_url(array $empFilters, array $incFilters, array $extra = []): string
{
    $q = array_merge(
        emp_list_query_params($empFilters),
        emp_inc_query_params($incFilters, $extra)
    );

    return 'index.php?' . http_build_query(array_filter($q, static fn($v) => $v !== null && $v !== ''));
}

/** @param list<array<string, mixed>> $rows */
function emp_inc_normalize_export_row(array $row): array
{
    return [
        'employee_code' => (string)($row['employee_code'] ?? ''),
        'full_name' => (string)($row['full_name'] ?? ''),
        'department' => (string)($row['department_name'] ?? '—'),
        'old_salary' => number_format((float)($row['old_salary'] ?? 0), 2, '.', ''),
        'new_salary' => number_format((float)($row['new_salary'] ?? 0), 2, '.', ''),
        'increment_amount' => number_format((float)($row['increment_amount'] ?? 0), 2, '.', ''),
        'increment_percentage' => number_format((float)($row['increment_percentage'] ?? 0), 2, '.', ''),
        'effective_date' => (string)($row['effective_date'] ?? ''),
        'reason' => (string)($row['reason'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
    ];
}

/** @return list<array<string, string>> */
function emp_inc_export_rows(PDO $pdo, array $filters): array
{
    $bundle = emp_inc_fetch($pdo, $filters);
    $out = [];
    foreach ($bundle['rows'] as $row) {
        $out[] = emp_inc_normalize_export_row($row);
    }

    return $out;
}

/** @param list<array<string, string>> $rows */
function emp_inc_export_excel(array $rows): void
{
    $headers = ['Code', 'Employee', 'Department', 'Old (₹)', 'New (₹)', 'Amount (₹)', '%', 'Effective', 'Reason', 'Recorded'];
    $sheetRows = [];
    foreach ($rows as $r) {
        $sheetRows[] = [
            $r['employee_code'], $r['full_name'], $r['department'],
            $r['old_salary'], $r['new_salary'], $r['increment_amount'],
            $r['increment_percentage'], $r['effective_date'], $r['reason'], $r['created_at'],
        ];
    }

    $filename = 'salary-increments-' . date('Y-m-d-His') . '.xlsx';
    if (class_exists('ZipArchive')) {
        emp_list_write_xlsx($headers, $sheetRows, $filename);
        return;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('.xlsx', '.csv', $filename) . '"');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        return;
    }
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Salary Increment History']);
    fputcsv($out, ['Generated', date('Y-m-d H:i')]);
    fputcsv($out, []);
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        fputcsv($out, array_values($r));
    }
    fclose($out);
}

function emp_inc_filter_summary(array $filters): string
{
    $parts = [];
    if ($filters['q'] !== '') {
        $parts[] = 'Search: "' . $filters['q'] . '"';
    }
    if ($filters['from'] !== '' || $filters['to'] !== '') {
        $parts[] = 'Effective: ' . ($filters['from'] ?: '…') . ' – ' . ($filters['to'] ?: '…');
    }

    return $parts !== [] ? implode(' · ', $parts) : 'All increments';
}
