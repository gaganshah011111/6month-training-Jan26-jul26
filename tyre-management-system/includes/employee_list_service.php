<?php
declare(strict_types=1);

require_once __DIR__ . '/payroll_logic.php';
require_once __DIR__ . '/hr_reports_service.php';

/** @return array<string, string> */
function emp_list_department_filter_options(): array
{
    return [
        '' => 'All departments',
        'DEPT_MIXING' => 'Mixing & Compounding',
        'DEPT_TIRE_BUILD' => 'Tyre Building',
        'DEPT_COMP_PREP' => 'Calendering',
        'DEPT_CURING' => 'Curing & Vulcanization',
        'DEPT_PPC' => 'PPC',
        'DEPT_WH' => 'Warehouse',
        'DEPT_LOG_DISP' => 'Dispatch Operations',
        'DEPT_HR' => 'HR',
        'DEPT_ACC' => 'Accounts',
    ];
}

/** @return list<string> */
function emp_list_shift_options(): array
{
    return ['', 'Morning', 'Evening', 'Night'];
}

function emp_list_per_page_default(): int
{
    return 10;
}

/** @return list<int> */
function emp_list_per_page_allowed(): array
{
    return [5, 10, 12, 25, 50, 100];
}

/** @return array<string, mixed> */
function emp_list_parse_filters(array $input): array
{
    $deptCodes = array_keys(emp_list_department_filter_options());
    $deptCode = trim((string)($input['department'] ?? ''));
    if (!in_array($deptCode, $deptCodes, true)) {
        $deptCode = '';
    }

    $empType = trim((string)($input['employee_type'] ?? ''));
    if (!in_array($empType, ['Staff', 'Worker'], true)) {
        $empType = '';
    }

    $shift = trim((string)($input['shift'] ?? ''));
    if (!in_array($shift, ['Morning', 'Evening', 'Night'], true)) {
        $shift = '';
    }

    $status = strtolower(trim((string)($input['status'] ?? '')));
    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = '';
    }

    $joinFrom = trim((string)($input['join_from'] ?? ''));
    $joinTo = trim((string)($input['join_to'] ?? ''));
    if ($joinFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $joinFrom)) {
        $joinFrom = '';
    }
    if ($joinTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $joinTo)) {
        $joinTo = '';
    }
    if ($joinFrom !== '' && $joinTo !== '' && $joinFrom > $joinTo) {
        [$joinFrom, $joinTo] = [$joinTo, $joinFrom];
    }

    $sort = trim((string)($input['sort'] ?? 'recent'));
    $allowedSort = ['recent', 'name', 'code', 'department', 'designation', 'shift', 'type', 'salary_high', 'salary_low', 'status', 'joining'];
    if (!in_array($sort, $allowedSort, true)) {
        $sort = 'recent';
    }

    $dir = strtolower(trim((string)($input['dir'] ?? '')));
    if (!in_array($dir, ['asc', 'desc'], true)) {
        $dir = match ($sort) {
            'name', 'code', 'department', 'designation', 'shift', 'type', 'status' => 'asc',
            default => 'desc',
        };
    }

    $perPageDefault = emp_list_per_page_default();
    $limit = (int)($input['per_page'] ?? $perPageDefault);
    if (!in_array($limit, emp_list_per_page_allowed(), true)) {
        $limit = $perPageDefault;
    }

    $page = max(1, (int)($input['p'] ?? 1));

    return [
        'q' => trim((string)($input['q'] ?? '')),
        'department' => $deptCode,
        'employee_type' => $empType,
        'shift' => $shift,
        'status' => $status,
        'join_from' => $joinFrom,
        'join_to' => $joinTo,
        'sort' => $sort,
        'dir' => $dir,
        'per_page' => $limit,
        'page' => $page,
    ];
}

function emp_list_has_active_filters(array $filters): bool
{
    return $filters['q'] !== ''
        || $filters['department'] !== ''
        || $filters['employee_type'] !== ''
        || $filters['shift'] !== ''
        || $filters['status'] !== ''
        || $filters['join_from'] !== ''
        || $filters['join_to'] !== '';
}

/** @return array{0:string,1:array<string,mixed>} */
function emp_list_where_sql(array $filters): array
{
    $sql = ' WHERE 1=1';
    $params = [];

    if ($filters['q'] !== '') {
        $needle = '%' . $filters['q'] . '%';
        $sql .= ' AND (
            e.full_name LIKE :q_name
            OR e.employee_code LIKE :q_code
            OR e.contact_no LIKE :q_phone
            OR COALESCE(u.email, \'\') LIKE :q_email
            OR COALESCE(u.username, \'\') LIKE :q_user
        )';
        $params['q_name'] = $needle;
        $params['q_code'] = $needle;
        $params['q_phone'] = $needle;
        $params['q_email'] = $needle;
        $params['q_user'] = $needle;
    }

    if ($filters['department'] !== '') {
        $sql .= ' AND d.department_code = :dept_code';
        $params['dept_code'] = $filters['department'];
    }

    if ($filters['employee_type'] !== '') {
        $sql .= ' AND e.employee_type = :etype';
        $params['etype'] = $filters['employee_type'];
    }

    if ($filters['shift'] !== '') {
        $sql .= ' AND (e.shift_timing = :shift OR e.shift_timing LIKE :shift_like)';
        $params['shift'] = $filters['shift'];
        $params['shift_like'] = $filters['shift'] . '%';
    }

    if ($filters['status'] !== '') {
        $sql .= ' AND LOWER(e.status) = :status';
        $params['status'] = $filters['status'];
    }

    if ($filters['join_from'] !== '') {
        $sql .= ' AND e.joining_date >= :join_from';
        $params['join_from'] = $filters['join_from'];
    }

    if ($filters['join_to'] !== '') {
        $sql .= ' AND e.joining_date <= :join_to';
        $params['join_to'] = $filters['join_to'];
    }

    return [$sql, $params];
}

function emp_list_join_sql(): string
{
    return ' FROM employees e
        LEFT JOIN users u ON u.id = e.user_id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN designations des ON des.id = e.designation_id
        LEFT JOIN department_categories dc ON dc.id = d.category_id ';
}

function emp_list_select_sql(): string
{
    return 'SELECT e.*, u.email AS user_email, u.username AS login_username,
        d.department_name AS dept_canonical_name, d.department_code AS dept_code,
        des.designation_name AS designation_canonical_name,
        dc.category_name AS dept_category_name, dc.id AS dept_category_id';
}

function emp_list_order_sql(array $filters): string
{
    $dir = strtoupper($filters['dir']) === 'ASC' ? 'ASC' : 'DESC';
    $flip = $dir === 'ASC' ? 'DESC' : 'ASC';

    return match ($filters['sort']) {
        'name' => "e.full_name {$dir}, e.id DESC",
        'code' => "e.employee_code {$dir}, e.id DESC",
        'department' => "COALESCE(d.department_name, e.department) {$dir}, e.full_name ASC",
        'designation' => "COALESCE(des.designation_name, e.designation) {$dir}, e.full_name ASC",
        'shift' => "e.shift_timing {$dir}, e.full_name ASC",
        'type' => "e.employee_type {$dir}, e.full_name ASC",
        'salary_high' => "COALESCE(NULLIF(e.gross_salary,0), e.basic_salary) DESC, e.id DESC",
        'salary_low' => "COALESCE(NULLIF(e.gross_salary,0), e.basic_salary) ASC, e.id DESC",
        'status' => "e.status {$dir}, e.full_name ASC",
        'joining' => "e.joining_date {$dir}, e.id DESC",
        default => "e.id {$flip}",
    };
}

/** @param array<string, mixed> $params */
function emp_list_bind_params(PDOStatement $stmt, array $params): void
{
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
}

/** @return array{total:int,rows:list<array<string,mixed>>} */
function emp_list_fetch(PDO $pdo, array $filters, ?int $limit = null, ?int $offset = null): array
{
    [$where, $params] = emp_list_where_sql($filters);
    $join = emp_list_join_sql();
    $order = emp_list_order_sql($filters);

    $countStmt = $pdo->prepare('SELECT COUNT(*)' . $join . $where);
    emp_list_bind_params($countStmt, $params);
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $sql = emp_list_select_sql() . $join . $where . ' ORDER BY ' . $order;
    if ($limit !== null) {
        $sql .= ' LIMIT :lim OFFSET :off';
    }

    $stmt = $pdo->prepare($sql);
    emp_list_bind_params($stmt, $params);
    if ($limit !== null) {
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', max(0, $offset ?? 0), PDO::PARAM_INT);
    }
    $stmt->execute();

    return ['total' => $total, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

/** @param array<string,mixed> $row */
function emp_list_row_gross(array $row): float
{
    $gross = (float)($row['gross_salary'] ?? 0);
    if ($gross <= 0) {
        $gross = employee_fixed_gross_monthly($row);
    }

    return $gross;
}

/** @param array<string,mixed> $row */
function emp_list_row_department(array $row): string
{
    $dept = trim((string)($row['dept_canonical_name'] ?? $row['department'] ?? ''));

    return $dept !== '' ? $dept : '—';
}

/** @param array<string,mixed> $row */
function emp_list_row_designation(array $row): string
{
    $des = trim((string)($row['designation_canonical_name'] ?? $row['designation'] ?? ''));

    return $des !== '' && $des !== '—' ? $des : '—';
}

/** @return array<string, string|null> */
function emp_list_query_params(array $filters, array $extra = []): array
{
    $default = emp_list_per_page_default();
    $merged = array_merge($filters, $extra);
    $out = [
        'page' => 'employees/list',
        'q' => $merged['q'] !== '' ? $merged['q'] : null,
        'department' => $merged['department'] !== '' ? $merged['department'] : null,
        'employee_type' => $merged['employee_type'] !== '' ? $merged['employee_type'] : null,
        'shift' => $merged['shift'] !== '' ? $merged['shift'] : null,
        'status' => $merged['status'] !== '' ? $merged['status'] : null,
        'join_from' => $merged['join_from'] !== '' ? $merged['join_from'] : null,
        'join_to' => $merged['join_to'] !== '' ? $merged['join_to'] : null,
        'sort' => ($merged['sort'] ?? 'recent') !== 'recent' ? $merged['sort'] : null,
        'dir' => null,
        'per_page' => (int)($merged['per_page'] ?? $default) !== $default ? (string)(int)$merged['per_page'] : null,
        'p' => (int)($merged['page'] ?? 1) > 1 ? (string)(int)$merged['page'] : null,
    ];

    $sort = (string)($merged['sort'] ?? 'recent');
    $dir = (string)($merged['dir'] ?? '');
    $defaultDir = match ($sort) {
        'name', 'code', 'department', 'designation', 'shift', 'type', 'status' => 'asc',
        default => 'desc',
    };
    if ($dir !== '' && $dir !== $defaultDir) {
        $out['dir'] = $dir;
    }

    if (isset($extra['export'])) {
        $out['export'] = $extra['export'];
    }
    if (isset($extra['profile_export'])) {
        $out['profile_export'] = $extra['profile_export'];
        $out['emp_profile'] = isset($extra['emp_profile']) ? (string)$extra['emp_profile'] : null;
    }

    return $out;
}

function emp_list_build_url(array $filters, array $extra = []): string
{
    $q = array_filter(emp_list_query_params($filters, $extra), static fn($v) => $v !== null && $v !== '');

    return 'index.php?' . http_build_query($q);
}

function emp_list_sort_toggle_dir(array $filters, string $column): string
{
    if ($filters['sort'] === $column) {
        return $filters['dir'] === 'asc' ? 'desc' : 'asc';
    }

    return match ($column) {
        'name', 'code', 'department', 'designation', 'shift', 'type', 'status' => 'asc',
        default => 'desc',
    };
}

function emp_list_sort_icon(array $filters, string $column): string
{
    $activeSort = $filters['sort'];
    if ($column === 'salary_high' && $activeSort === 'salary_low') {
        $activeSort = 'salary_high';
    }
    if ($activeSort !== $column) {
        return '<i class="bi bi-arrow-down-up text-muted opacity-50"></i>';
    }

    return $filters['dir'] === 'asc'
        ? '<i class="bi bi-sort-up"></i>'
        : '<i class="bi bi-sort-down"></i>';
}

function emp_list_company_logo_url(): ?string
{
    $candidates = [
        __DIR__ . '/../assets/images/company-logo.png',
        __DIR__ . '/../assets/images/logo.png',
        __DIR__ . '/../assets/img/logo.png',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return 'assets/images/' . basename($path);
        }
    }

    return null;
}

/** @return list<array<string, string>> */
function emp_list_export_rows(PDO $pdo, array $filters): array
{
    $bundle = emp_list_fetch($pdo, $filters);
    $out = [];
    foreach ($bundle['rows'] as $r) {
        $out[] = [
            'employee_code' => (string)($r['employee_code'] ?? ''),
            'full_name' => (string)($r['full_name'] ?? ''),
            'department' => emp_list_row_department($r),
            'designation' => emp_list_row_designation($r),
            'shift' => (string)($r['shift_timing'] ?? '—'),
            'employee_type' => (string)($r['employee_type'] ?? 'Staff'),
            'gross_salary' => (string)number_format(emp_list_row_gross($r), 2, '.', ''),
            'status' => ucfirst((string)($r['status'] ?? 'active')),
            'joining_date' => (string)($r['joining_date'] ?? ''),
            'contact_no' => (string)($r['contact_no'] ?? ''),
            'email' => (string)($r['user_email'] ?? $r['email'] ?? ''),
        ];
    }

    return $out;
}

/** @param list<array<string, string>> $rows */
function emp_list_export_excel(array $rows, array $filters): void
{
    $headers = ['Employee Code', 'Name', 'Department', 'Designation', 'Shift', 'Type', 'Monthly Gross (₹)', 'Status', 'Joining Date', 'Phone', 'Email'];
    $filename = 'employees-' . date('Y-m-d-His') . '.xlsx';

    $sheetRows = [];
    foreach ($rows as $r) {
        $sheetRows[] = [
            $r['employee_code'], $r['full_name'], $r['department'], $r['designation'],
            $r['shift'], $r['employee_type'], $r['gross_salary'], $r['status'],
            $r['joining_date'], $r['contact_no'], $r['email'],
        ];
    }
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
    fputcsv($out, ['Employee Directory']);
    fputcsv($out, ['Generated', date('Y-m-d H:i')]);
    fputcsv($out, []);
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['employee_code'], $r['full_name'], $r['department'], $r['designation'],
            $r['shift'], $r['employee_type'], $r['gross_salary'], $r['status'],
            $r['joining_date'], $r['contact_no'], $r['email'],
        ]);
    }
    fclose($out);
}

/**
 * @param list<string> $headers
 * @param list<list<string>> $rows cell values per row, same order as headers
 */
function emp_list_write_xlsx(array $headers, array $rows, string $filename): void
{
    $esc = static function (string $v): string {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };
    $colLetter = static function (int $i): string {
        $n = $i;
        $s = '';
        do {
            $s = chr(65 + ($n % 26)) . $s;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $s;
    };

    $sheetRows = '';
    $sheetRows .= '<row r="1">';
    foreach ($headers as $i => $h) {
        $sheetRows .= '<c r="' . $colLetter($i) . '1" t="inlineStr"><is><t>' . $esc($h) . '</t></is></c>';
    }
    $sheetRows .= '</row>';

    $r = 2;
    foreach ($rows as $row) {
        $sheetRows .= '<row r="' . $r . '">';
        foreach (array_values($row) as $i => $v) {
            $sheetRows .= '<c r="' . $colLetter($i) . $r . '" t="inlineStr"><is><t>' . $esc((string)$v) . '</t></is></c>';
        }
        $sheetRows .= '</row>';
        $r++;
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'emp_xlsx_');
    if ($tmp === false) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="employees.csv"');
        $out = fopen('php://output', 'w');
        if ($out) {
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        }
        return;
    }
    $zipPath = $tmp . '.xlsx';
    @unlink($tmp);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($zipPath);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="employees.csv"');
        return;
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Employees" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string)filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
}

/** @return array{active:int,staff:int,present:int,payroll:float} */
function emp_list_dashboard_stats(PDO $pdo): array
{
    return [
        'active' => (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn(),
        'staff' => (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE employee_type='Staff'")->fetchColumn(),
        'present' => (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status IN ('Present','Late','Half Day','Emergency Duty')")->fetchColumn(),
        'payroll' => (float)$pdo->query("SELECT COALESCE(SUM(net_salary),0) FROM salaries WHERE month_year = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn(),
    ];
}

/**
 * Recent salary increments grouped by employee id.
 *
 * @param list<int> $employeeIds
 * @return array<int, list<array<string, mixed>>>
 */
function emp_list_increments_for_employees(PDO $pdo, array $employeeIds, int $perEmployee = 5): array
{
    $employeeIds = array_values(array_filter(array_map('intval', $employeeIds), static fn(int $id) => $id > 0));
    if ($employeeIds === []) {
        return [];
    }

    $ph = implode(',', array_fill(0, count($employeeIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT si.* FROM salary_increments si
         WHERE si.employee_id IN ({$ph})
         ORDER BY si.employee_id ASC, si.effective_date DESC, si.id DESC"
    );
    $stmt->execute($employeeIds);
    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $eid = (int)$row['employee_id'];
        if (!isset($grouped[$eid])) {
            $grouped[$eid] = [];
        }
        if (count($grouped[$eid]) < $perEmployee) {
            $grouped[$eid][] = $row;
        }
    }

    return $grouped;
}

function emp_list_filter_summary_label(array $filters): string
{
    $parts = [];
    if ($filters['q'] !== '') {
        $parts[] = 'Search: "' . $filters['q'] . '"';
    }
    if ($filters['department'] !== '') {
        $opts = emp_list_department_filter_options();
        $parts[] = 'Dept: ' . ($opts[$filters['department']] ?? $filters['department']);
    }
    if ($filters['employee_type'] !== '') {
        $parts[] = 'Type: ' . $filters['employee_type'];
    }
    if ($filters['shift'] !== '') {
        $parts[] = 'Shift: ' . $filters['shift'];
    }
    if ($filters['status'] !== '') {
        $parts[] = 'Status: ' . ucfirst($filters['status']);
    }
    if ($filters['join_from'] !== '' || $filters['join_to'] !== '') {
        $parts[] = 'Joined: ' . ($filters['join_from'] ?: '…') . ' – ' . ($filters['join_to'] ?: '…');
    }

    return $parts !== [] ? implode(' · ', $parts) : 'All employees';
}
