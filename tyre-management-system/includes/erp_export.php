<?php
declare(strict_types=1);

/** Receipt number e.g. PAY-20260525-1021 */
function sales_payment_receipt_no(int $paymentId, string $paymentDate): string
{
    $d = preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate) ? str_replace('-', '', $paymentDate) : date('Ymd');

    return 'PAY-' . $d . '-' . $paymentId;
}

/** Extract dispatch code/id from invoice remarks. */
function sales_invoice_dispatch_ref(array $inv): string
{
    $rm = (string)($inv['remarks'] ?? '');
    if (preg_match('/\(([^)]+)\)\s*$/', $rm, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/dispatch:(\d+)/i', $rm, $m)) {
        return 'DSP-' . $m[1];
    }

    return '—';
}

/**
 * Compact export toolbar for ERP tables (client-side filtered export).
 *
 * @param string $tableId DOM id of table element
 * @param string $filenameBase base name without extension
 */
function erp_export_toolbar(string $tableId, string $filenameBase = 'export'): string
{
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $tableId);
    $base = preg_replace('/[^a-zA-Z0-9_-]/', '', $filenameBase) ?: 'export';

    return '<div class="erp-export-toolbar" data-erp-export-table="' . e($id) . '" data-erp-export-base="' . e($base) . '">'
        . '<button type="button" class="btn btn-sm btn-outline-secondary erp-export-pdf" title="Export PDF"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>'
        . '<button type="button" class="btn btn-sm btn-outline-secondary erp-export-excel" title="Export Excel"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</button>'
        . '<button type="button" class="btn btn-sm btn-outline-secondary erp-export-print" title="Print"><i class="bi bi-printer me-1"></i>Print</button>'
        . '</div>';
}

/** @param list<string> $headers */
/** @param list<list<string|int|float>> $rows */
function erp_send_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

/** @return bool */
function erp_print_col_is_numeric(string $header): bool
{
    $h = strtolower($header);
    foreach (['amount', 'debit', 'credit', 'balance', 'paid', 'pending', 'invoiced', 'purchased', 'total', 'qty', 'price', 'cost'] as $key) {
        if (str_contains($h, $key)) {
            return true;
        }
    }

    return false;
}

/**
 * Professional print/PDF report (matches dispatch slip / ERP document UI).
 *
 * @param list<string> $headers
 * @param list<list<string>> $rows
 * @param array{
 *   subtitle?: string,
 *   doc_title?: string,
 *   tagline?: string,
 *   back_url?: string,
 *   kpis?: list<array{0: string, 1: string, 2?: string}>,
 *   meta?: array<string, string>
 * } $opts
 */
function erp_print_html_table(string $title, array $headers, array $rows, bool $autoPrint = true, array $opts = []): void
{
    require_once __DIR__ . '/erp_document_print.php';

    $pdo = null;
    try {
        if (class_exists('Database')) {
            $pdo = Database::connection();
        }
    } catch (Throwable) {
        $pdo = null;
    }
    $company = $pdo instanceof PDO ? erp_doc_company_name($pdo) : (defined('APP_NAME') ? (string)APP_NAME : 'ERP');

    $docTitle = (string)($opts['doc_title'] ?? $title);
    $subtitle = (string)($opts['subtitle'] ?? ('Generated ' . date('d M Y, H:i')));
    $tagline = (string)($opts['tagline'] ?? 'Accounts & Finance');
    $backUrl = (string)($opts['back_url'] ?? ($_SERVER['HTTP_REFERER'] ?? route_url('dashboard')));

    header('Content-Type: text/html; charset=utf-8');
    erp_doc_print_begin(['title' => $title, 'back_url' => $backUrl, 'auto_print' => $autoPrint]);
    erp_doc_print_header($company, $docTitle, $subtitle, $tagline);

    if (!empty($opts['kpis']) && is_array($opts['kpis'])) {
        echo '<div class="slip__kpis">';
        foreach ($opts['kpis'] as $kpi) {
            $mod = isset($kpi[2]) ? ' slip__kpi--' . preg_replace('/[^a-z]/', '', (string)$kpi[2]) : '';
            echo '<div class="slip__kpi' . $mod . '"><span>' . e((string)$kpi[0]) . '</span><strong>' . e((string)$kpi[1]) . '</strong></div>';
        }
        echo '</div>';
    }

    if (!empty($opts['meta']) && is_array($opts['meta'])) {
        erp_doc_section_open('Report details');
        erp_doc_grid_open();
        foreach ($opts['meta'] as $label => $value) {
            if ((string)$value === '') {
                continue;
            }
            erp_doc_field((string)$label, (string)$value);
        }
        erp_doc_grid_close();
        erp_doc_section_close();
    }

    erp_doc_section_open('Entries (' . count($rows) . ')');
    echo '<table class="slip__material"><thead><tr>';
    $numericCols = [];
    foreach ($headers as $i => $h) {
        $align = erp_print_col_is_numeric((string)$h) ? ' class="text-end"' : '';
        if ($align !== '') {
            $numericCols[$i] = true;
        }
        echo '<th' . $align . '>' . e((string)$h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if ($rows === []) {
        echo '<tr><td colspan="' . count($headers) . '" style="text-align:center;color:#64748b;padding:16px">No records found</td></tr>';
    }
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $i => $cell) {
            $cls = isset($numericCols[$i]) ? ' class="text-end"' : '';
            echo '<td' . $cls . '>' . e((string)$cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    erp_doc_section_close();

    $metaHtml = 'Printed ' . e(date('d M Y, H:i')) . '<br>' . e((string)count($rows)) . ' row(s)';
    erp_doc_print_footer('Accounts — Authorized Signatory', $metaHtml);
    exit;
}
