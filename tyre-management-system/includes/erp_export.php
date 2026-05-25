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

/** @param list<string> $headers */
/** @param list<list<string>> $rows */
function erp_print_html_table(string $title, array $headers, array $rows, bool $autoPrint = true): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . e($title) . '</title>';
    echo '<style>body{font-family:system-ui,sans-serif;font-size:11px;margin:16px;color:#0f172a}';
    echo 'h1{font-size:16px;margin:0 0 8px}table{width:100%;border-collapse:collapse}';
    echo 'th,td{border:1px solid #334155;padding:5px 7px}th{background:#1e293b;color:#fff}';
    echo '@media print{body{margin:0}}</style></head><body>';
    if ($autoPrint) {
        echo '<script>window.onload=function(){window.print()}</script>';
    }
    echo '<h1>' . e($title) . '</h1><p style="color:#64748b;margin:0 0 12px">Generated ' . e(date('d M Y H:i')) . '</p>';
    echo '<table><thead><tr>';
    foreach ($headers as $h) {
        echo '<th>' . e($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . e((string)$cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}
