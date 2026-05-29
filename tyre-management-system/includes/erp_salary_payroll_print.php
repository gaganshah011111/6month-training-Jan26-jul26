<?php
declare(strict_types=1);

require_once __DIR__ . '/accounts_salary_payroll.php';
require_once __DIR__ . '/erp_export.php';
require_once __DIR__ . '/erp_document_print.php';

/**
 * Server-side export toolbar (professional PDF, not raw table dump).
 *
 * @param array<string, string|int|null> $queryParams
 */
function erp_salary_export_toolbar(string $pageRoute, array $queryParams, string $filenameBase = 'salary-payroll'): string
{
    $base = $queryParams;
    $base['page'] = $pageRoute;
    $pdfQs = array_merge($base, ['export' => 'pdf']);
    $csvQs = array_merge($base, ['export' => 'csv']);
    $printQs = array_merge($base, ['export' => 'print']);

    return '<div class="erp-export-toolbar erp-export-toolbar--server">'
        . '<a class="btn btn-sm btn-outline-secondary" href="' . e(route_url($pageRoute, $pdfQs)) . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>'
        . '<a class="btn btn-sm btn-outline-secondary" href="' . e(route_url($pageRoute, $csvQs)) . '"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel</a>'
        . '<a class="btn btn-sm btn-outline-secondary" href="' . e(route_url($pageRoute, $printQs)) . '" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>'
        . '</div>';
}

/**
 * Handle ?export=pdf|csv|print on salary payment pages. Exits when export runs.
 */
function erp_salary_payroll_handle_export(PDO $pdo): void
{
    $export = strtolower(trim((string)($_GET['export'] ?? '')));
    if (!in_array($export, ['pdf', 'csv', 'print'], true)) {
        return;
    }

    acc_salary_ensure_schema($pdo);
    $scope = (string)($_GET['export_scope'] ?? 'employees');
    $monthYear = trim((string)($_GET['month_year'] ?? date('Y-m')));
    if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
        $monthYear = date('Y-m');
    }
    $deptFilter = trim((string)($_GET['department'] ?? ''));
    $statusFilter = trim((string)($_GET['status'] ?? ''));
    $search = trim((string)($_GET['search'] ?? $_GET['q'] ?? $_GET['employee_id'] ?? ''));

    $empFilters = array_filter([
        'status' => in_array($statusFilter, ['pending', 'partial', 'paid'], true) ? $statusFilter : null,
        'q' => $search !== '' ? $search : null,
    ], static fn($v) => $v !== null);

    $kpis = acc_salary_dashboard_kpis($pdo, $monthYear);
    $monthLabel = payroll_format_month_label($monthYear);
    $generated = date('d M Y, h:i A');
    $backUrl = $scope === 'detail' && $deptFilter !== ''
        ? route_url('accounts/salary-payment-detail', ['month_year' => $monthYear, 'department' => $deptFilter])
        : route_url('accounts/salary-payments', ['month_year' => $monthYear]);

    $footerTotals = null;
    if ($scope === 'batches') {
        $batchRows = acc_salary_list_department_batches($pdo, array_filter([
            'month_year' => $monthYear,
            'department' => $deptFilter !== '' ? $deptFilter : null,
            'status' => $empFilters['status'] ?? null,
            'q' => $q !== '' ? $q : null,
        ], static fn($v) => $v !== null && $v !== ''));
        $title = 'Payroll Batches — ' . $monthLabel;
        $headers = ['Payroll month', 'Department', 'Employees', 'Total payroll', 'Paid', 'Pending', 'Status'];
        $data = [];
        $tPay = $tPaid = $tPend = 0.0;
        $tEmp = 0;
        foreach ($batchRows as $r) {
            $meta = $r['status_meta'] ?? acc_salary_status_meta((string)$r['status']);
            $tPay += (float)$r['total_payroll'];
            $tPaid += (float)$r['paid'];
            $tPend += (float)$r['pending'];
            $tEmp += (int)$r['employees'];
            $data[] = [
                (string)$r['month_label'],
                (string)$r['department'],
                (string)(int)$r['employees'],
                sales_format_money((float)$r['total_payroll']),
                sales_format_money((float)$r['paid']),
                sales_format_money((float)$r['pending']),
                (string)$meta['label'],
            ];
        }
        $footerTotals = ['label' => 'Grand total', 'cols' => [3 => (string)$tEmp, 4 => sales_format_money($tPay), 5 => sales_format_money($tPaid), 6 => sales_format_money($tPend)]];
    } else {
        $department = $deptFilter;
        $employees = acc_salary_list_employees($pdo, $monthYear, $department, $empFilters);
        $title = $scope === 'detail'
            ? 'Payroll Detail — ' . $deptFilter . ' — ' . $monthLabel
            : 'Employee Salary Payments — ' . $monthLabel;
        $headers = ['Employee ID', 'Employee name', 'Department', 'Designation', 'Salary', 'Paid', 'Pending', 'Status'];
        $data = [];
        $tPay = $tPaid = $tPend = 0.0;
        foreach ($employees as $e) {
            $meta = $e['pay_status_meta'] ?? acc_salary_status_meta((string)$e['pay_status']);
            $net = (float)$e['net_salary'];
            $paid = (float)$e['amount_paid'];
            $pend = (float)$e['pending'];
            $tPay += $net;
            $tPaid += $paid;
            $tPend += $pend;
            $data[] = [
                (string)$e['employee_code'],
                (string)$e['full_name'],
                (string)($e['dept_label'] ?? ''),
                (string)($e['desig_label'] ?? '—'),
                sales_format_money($net),
                sales_format_money($paid),
                sales_format_money($pend),
                (string)$meta['label'],
            ];
        }
        $footerTotals = ['label' => 'Total', 'cols' => [4 => sales_format_money($tPay), 5 => sales_format_money($tPaid), 6 => sales_format_money($tPend)]];
    }

    if ($export === 'csv') {
        $fname = 'salary-payroll-' . $monthYear . '-' . date('Ymd') . '.csv';
        erp_send_csv($fname, $headers, $data);
    }

    erp_salary_payroll_print_document(
        $title,
        $kpis,
        $headers,
        $data,
        [
            'month_label' => $monthLabel,
            'department' => $deptFilter !== '' ? $deptFilter : 'All departments',
            'status' => $statusFilter !== '' ? ucfirst($statusFilter) : 'All',
            'search' => $q !== '' ? $q : ($empIdFilter !== '' ? $empIdFilter : '—'),
            'generated' => $generated,
            'back_url' => $backUrl,
            'footer_totals' => $footerTotals,
        ],
        $export === 'pdf'
    );
    exit;
}

/** @param list<string> $headers @param list<list<string>> $rows */
function erp_salary_payroll_print_document(
    string $title,
    array $kpis,
    array $headers,
    array $rows,
    array $meta,
    bool $autoPrint
): void {
    $company = erp_doc_company_name(Database::connection());
    $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $company), 0, 2) ?: 'RP');

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . e($title) . '</title>';
    echo '<style>' . erp_doc_print_styles() . erp_salary_print_extra_styles() . '</style>';
    if ($autoPrint) {
        echo '<script>window.onload=function(){window.print();};</script>';
    }
    echo '</head><body>';
    echo '<div class="no-print">';
    echo '<button type="button" class="btn-print" onclick="window.print()">Print / Save as PDF</button>';
    echo '<a class="btn-back" href="' . e((string)($meta['back_url'] ?? route_url('accounts/salary-payments'))) . '">Back</a>';
    echo '</div>';

    echo '<article class="slip sal-print-doc">';
    echo '<header class="slip__head"><div style="display:flex;gap:14px;align-items:flex-start;">';
    echo '<div class="slip__logo">' . e($initials) . '</div>';
    echo '<div><p class="slip__company">' . e($company) . '</p>';
    echo '<p class="slip__tagline">Accounts &amp; Finance · Salary Payments</p></div>';
    echo '<div style="margin-left:auto;text-align:right">';
    echo '<h1 class="slip__title" style="margin:0">' . e($title) . '</h1>';
    echo '<p class="slip__subtitle">Payroll period: ' . e((string)($meta['month_label'] ?? '')) . '</p>';
    echo '</div></header>';

    echo '<div class="slip__body">';
    echo '<div class="sal-print-kpis">';
    echo '<div class="sal-print-kpi"><span>Total payroll</span><strong>' . e(sales_format_money((float)($kpis['total_payroll'] ?? 0))) . '</strong></div>';
    echo '<div class="sal-print-kpi sal-print-kpi--ok"><span>Paid salary</span><strong>' . e(sales_format_money((float)($kpis['paid_salary'] ?? 0))) . '</strong></div>';
    echo '<div class="sal-print-kpi sal-print-kpi--danger"><span>Pending salary</span><strong>' . e(sales_format_money((float)($kpis['pending_salary'] ?? 0))) . '</strong></div>';
    echo '<div class="sal-print-kpi"><span>Employees paid</span><strong>' . (int)($kpis['employees_paid'] ?? 0) . '</strong></div>';
    echo '<div class="sal-print-kpi sal-print-kpi--danger"><span>Employees pending</span><strong>' . (int)($kpis['employees_pending'] ?? 0) . '</strong></div>';
    echo '<div class="sal-print-kpi"><span>Departments pending</span><strong>' . (int)($kpis['departments_pending'] ?? 0) . '</strong></div>';
    echo '</div>';

    echo '<div class="slip__section"><div class="slip__section-title">Report filters</div><div class="slip__grid">';
    erp_salary_print_field('Payroll month', (string)($meta['month_label'] ?? ''));
    erp_salary_print_field('Department', (string)($meta['department'] ?? ''));
    erp_salary_print_field('Payment status', (string)($meta['status'] ?? ''));
    erp_salary_print_field('Employee search', (string)($meta['search'] ?? ''));
    erp_salary_print_field('Generated on', (string)($meta['generated'] ?? ''));
    echo '</div></div>';

    echo '<div class="slip__section"><div class="slip__section-title">Employee salary details</div>';
    echo '<table class="slip__material"><thead><tr>';
    foreach ($headers as $h) {
        $cls = in_array($h, ['Salary', 'Paid', 'Pending', 'Employees', 'Total payroll'], true) ? ' class="text-end"' : '';
        echo '<th' . $cls . '>' . e($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if ($rows === []) {
        echo '<tr><td colspan="' . count($headers) . '" style="text-align:center;color:#64748b">No records for selected filters.</td></tr>';
    }
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $i => $cell) {
            $cls = '';
            if (count($headers) >= 7 && $i >= count($headers) - 4 && $i <= count($headers) - 2) {
                $cls = ' class="text-end"';
            }
            echo '<td' . $cls . '>' . e((string)$cell) . '</td>';
        }
        echo '</tr>';
    }
    $footer = $meta['footer_totals'] ?? null;
    if (is_array($footer) && $rows !== []) {
        echo '<tr style="font-weight:700;background:#f1f5f9">';
        $colCount = count($headers);
        for ($i = 0; $i < $colCount; $i++) {
            $cls = ($i >= 4 && $i <= 6) ? ' class="text-end"' : '';
            if ($i === 0) {
                echo '<td' . $cls . '>' . e((string)($footer['label'] ?? 'Total')) . '</td>';
            } elseif (isset($footer['cols'][$i])) {
                echo '<td' . $cls . '>' . e((string)$footer['cols'][$i]) . '</td>';
            } else {
                echo '<td' . $cls . '></td>';
            }
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';

    echo '<div class="slip__footer">';
    echo '<div><div class="slip__sign">HR Manager</div></div>';
    echo '<div><div class="slip__sign">Accounts Manager</div></div>';
    echo '<div class="slip__meta">Confidential payroll report<br>' . e((string)($meta['generated'] ?? '')) . '<br>Page 1 of 1</div>';
    echo '</div></article></body></html>';
}

function erp_salary_print_field(string $label, string $value): void
{
    echo '<div><span class="slip__label">' . e($label) . '</span><br><span class="slip__value">' . e($value) . '</span></div>';
}

function erp_salary_print_extra_styles(): string
{
    return <<<'CSS'
        .sal-print-kpis {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }
        .sal-print-kpi {
            border: 1px solid #e2e8f0;
            border-left: 3px solid #1a2744;
            padding: 10px 12px;
            background: #f8fafc;
        }
        .sal-print-kpi span {
            display: block;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 4px;
        }
        .sal-print-kpi strong { font-size: 14px; color: #0f172a; }
        .sal-print-kpi--ok { border-left-color: #16a34a; }
        .sal-print-kpi--ok strong { color: #15803d; }
        .sal-print-kpi--danger { border-left-color: #dc2626; }
        .sal-print-kpi--danger strong { color: #b91c1c; }
        @media print {
            .sal-print-kpis { grid-template-columns: repeat(3, 1fr); }
        }
CSS;
}
