<?php
declare(strict_types=1);

/**
 * Shared print/PDF document shell (matches dispatch slip UI).
 */

function erp_doc_print_styles(): string
{
    return <<<'CSS'
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 20px;
            background: #f1f5f9;
        }
        .slip {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
        }
        .slip__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 24px;
            border-bottom: 3px solid #1a2744;
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
        }
        .slip__logo {
            width: 56px;
            height: 56px;
            background: #1a2744;
            color: #fff;
            font-weight: 800;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            letter-spacing: -1px;
            flex-shrink: 0;
        }
        .slip__company { font-size: 15px; font-weight: 700; color: #1a2744; margin: 0 0 4px; }
        .slip__tagline { font-size: 10px; color: #64748b; margin: 0; }
        .slip__title {
            text-align: right;
            font-size: 18px;
            font-weight: 800;
            color: #1a2744;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin: 0;
        }
        .slip__subtitle { text-align: right; font-size: 10px; color: #64748b; margin: 4px 0 0; }
        .slip__body { padding: 20px 24px; }
        .slip__section { margin-bottom: 18px; }
        .slip__section-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }
        .slip__grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 24px;
        }
        .slip__label { color: #64748b; font-size: 10px; }
        .slip__value { font-weight: 600; color: #0f172a; }
        .slip__material {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .slip__material th {
            background: #1a2744;
            color: #f8fafc;
            text-align: left;
            padding: 8px 10px;
            font-size: 10px;
            text-transform: uppercase;
        }
        .slip__material td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
        }
        .slip__material .text-end { text-align: right; }
        .slip__totals {
            margin-top: 12px;
            text-align: right;
            font-size: 11px;
            line-height: 1.6;
        }
        .slip__totals strong { font-size: 13px; color: #1a2744; }
        .slip__status {
            display: inline-block;
            font-weight: 700;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .slip__status--paid { background: #dcfce7; color: #166534; }
        .slip__status--partial { background: #fef3c7; color: #92400e; }
        .slip__status--unpaid { background: #fee2e2; color: #991b1b; }
        .slip__footer {
            padding: 16px 24px 24px;
            border-top: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .slip__sign {
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
            margin-top: 40px;
            font-size: 10px;
            color: #64748b;
        }
        .slip__meta { font-size: 9px; color: #94a3b8; text-align: right; line-height: 1.5; }
        .no-print {
            text-align: center;
            padding: 12px;
            background: #1a2744;
        }
        .no-print button, .no-print a {
            margin: 0 6px;
            padding: 8px 16px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-print { background: #fff; color: #1a2744; font-weight: 600; }
        .btn-back { background: transparent; color: #e2e8f0; border: 1px solid #64748b !important; }
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .slip { box-shadow: none; border: none; max-width: none; }
        }
CSS;
}

/** @param array{title: string, back_url: string, auto_print?: bool, filename?: string} $opts */
function erp_doc_print_begin(array $opts): void
{
    $title = (string)($opts['title'] ?? 'Document');
    $back = (string)($opts['back_url'] ?? route_url('dashboard'));
    $auto = !empty($opts['auto_print']);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<title>' . e($title) . '</title>';
    echo '<style>' . erp_doc_print_styles() . '</style>';
    if ($auto) {
        echo '<script>window.onload=function(){window.print();};</script>';
    }
    echo '</head><body>';
    echo '<div class="no-print">';
    echo '<button type="button" class="btn-print" onclick="window.print()">Print / Save as PDF</button>';
    echo '<a class="btn-back" href="' . e($back) . '">Back</a>';
    echo '</div>';
    echo '<article class="slip">';
}

function erp_doc_print_header(string $company, string $docTitle, string $subtitle, string $tagline = 'Sales & CRM'): void
{
    echo '<header class="slip__head"><div style="display:flex;gap:14px;align-items:flex-start;">';
    echo '<div class="slip__logo">R</div><div>';
    echo '<h1 class="slip__company">' . e($company) . '</h1>';
    echo '<p class="slip__tagline">' . e($tagline) . '</p></div></div><div>';
    echo '<h2 class="slip__title">' . e($docTitle) . '</h2>';
    echo '<p class="slip__subtitle">' . e($subtitle) . '</p></div></header>';
    echo '<div class="slip__body">';
}

function erp_doc_print_footer(string $leftSign = 'Authorized signature', string $metaHtml = ''): void
{
    echo '</div><footer class="slip__footer"><div>';
    echo '<div class="slip__sign">' . e($leftSign) . '</div></div>';
    echo '<div class="slip__meta">' . $metaHtml . '</div></footer></article></body></html>';
}

function erp_doc_field(string $label, string $value, bool $fullWidth = false): void
{
    $style = $fullWidth ? ' style="grid-column:1/-1"' : '';
    echo '<div' . $style . '><span class="slip__label">' . e($label) . '</span><br>';
    echo '<span class="slip__value">' . e($value) . '</span></div>';
}

function erp_doc_section_open(string $title): void
{
    echo '<section class="slip__section"><div class="slip__section-title">' . e($title) . '</div>';
}

function erp_doc_section_close(): void
{
    echo '</section>';
}

function erp_doc_grid_open(): void
{
    echo '<div class="slip__grid">';
}

function erp_doc_grid_close(): void
{
    echo '</div>';
}

function erp_doc_payment_status_badge(string $label): string
{
    $cls = match (strtoupper($label)) {
        'PAID' => 'slip__status--paid',
        'PARTIAL' => 'slip__status--partial',
        default => 'slip__status--unpaid',
    };

    return '<span class="slip__status ' . $cls . '">' . e($label) . '</span>';
}
