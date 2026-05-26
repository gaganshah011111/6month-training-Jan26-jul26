<?php
declare(strict_types=1);

/** Sidebar / module display name */
const INV_MODULE_LABEL = 'Procurement & Inventory';

/** Recent purchases table row limits (newest first). */
const INV_RECENT_PURCHASES_DASHBOARD = 8;
const INV_RECENT_PURCHASES_INWARD = 10;

function inv_recent_purchases_note(int $limit): string
{
    return 'Showing latest ' . $limit . ' purchases';
}

function inv_module_label(): string
{
    return INV_MODULE_LABEL;
}

function inv_hint(string $text): string
{
    if ($text === '') {
        return '';
    }

    return '<span class="inv-hint">' . e($text) . '</span>';
}

/** @param list<array{label: string, url: string, icon?: string, primary?: bool}> $actions */
function inv_quick_actions(array $actions): string
{
    $html = '<nav class="inv-quick-actions" aria-label="Quick actions">';
    foreach ($actions as $a) {
        $cls = !empty($a['primary']) ? 'btn btn-primary btn-sm' : 'btn btn-outline-secondary btn-sm';
        $icon = !empty($a['icon']) ? '<i class="bi ' . e((string)$a['icon']) . ' me-1"></i>' : '';
        $html .= '<a class="' . $cls . '" href="' . e((string)$a['url']) . '">' . $icon . e((string)$a['label']) . '</a>';
    }
    $html .= '</nav>';

    return $html;
}

function inv_breadcrumb(string $pageTitle): string
{
    return '<nav class="inv-crumb" aria-label="Breadcrumb">'
        . '<a href="' . e(route_url('inventory/dashboard')) . '">' . e(INV_MODULE_LABEL) . '</a>'
        . '<span class="inv-crumb__sep">/</span>'
        . '<span class="inv-crumb__current">' . e($pageTitle) . '</span></nav>';
}

function inv_page_header(string $title, string $subtitle = '', string $actionsHtml = ''): void
{
    echo '<header class="inv-page__head">';
    echo inv_breadcrumb($title);
    echo '<div class="inv-page__head-row">';
    echo '<div class="inv-page__head-text">';
    echo '<h1 class="inv-page__title">' . e($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p class="inv-page__sub">' . e($subtitle) . '</p>';
    }
    echo '</div>';
    if ($actionsHtml !== '') {
        echo '<div class="inv-page__actions">' . $actionsHtml . '</div>';
    }
    echo '</div></header>';
}

/** Filter row + export buttons (respects current query string). */
function inv_filter_exports(string $baseQueryString, bool $csv = true, bool $pdf = true, bool $print = true): string
{
    $qs = ltrim($baseQueryString, '?&');
    $html = '<div class="inv-export-bar" role="group" aria-label="Export">';
    if ($csv) {
        $html .= '<a class="btn btn-outline-secondary btn-sm" href="index.php?' . e($qs) . '&export=csv"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</a>';
    }
    if ($pdf) {
        $html .= '<a class="btn btn-outline-secondary btn-sm" href="index.php?' . e($qs) . '&export=print" target="_blank" rel="noopener"><i class="bi bi-file-pdf me-1"></i>PDF</a>';
    }
    if ($print) {
        $html .= '<a class="btn btn-outline-secondary btn-sm" href="index.php?' . e($qs) . '&export=print" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>';
    }
    $html .= '</div>';

    return $html;
}

function inv_table_scroll_open(string $height = 'min(52vh, 480px)'): void
{
    echo '<div class="inv-table-scroll" style="--inv-table-max-h: ' . e($height) . '">';
}

function inv_table_scroll_close(): void
{
    echo '</div>';
}
