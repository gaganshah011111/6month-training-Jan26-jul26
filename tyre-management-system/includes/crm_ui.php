<?php
declare(strict_types=1);

/**
 * Shared Sales CRM layout helpers (clean ERP UI).
 */

/** @param array{icon?: string, tone?: string} $item */
function crm_action_tone(array $item): string
{
    $tone = (string)($item['tone'] ?? '');
    $allowed = ['view', 'edit', 'dispatch', 'invoice', 'pdf', 'print', 'payment', 'order', 'default'];
    if (in_array($tone, $allowed, true)) {
        return $tone;
    }
    $icon = (string)($item['icon'] ?? '');
    if (str_contains($icon, 'eye')) {
        return 'view';
    }
    if (str_contains($icon, 'pencil')) {
        return 'edit';
    }
    if (str_contains($icon, 'truck')) {
        return 'dispatch';
    }
    if (str_contains($icon, 'receipt')) {
        return 'invoice';
    }
    if (str_contains($icon, 'file-pdf')) {
        return 'pdf';
    }
    if (str_contains($icon, 'printer')) {
        return 'print';
    }
    if (str_contains($icon, 'cash')) {
        return 'payment';
    }
    if (str_contains($icon, 'cart')) {
        return 'order';
    }

    return 'default';
}

/** @param list<array{label: string, url?: string, icon?: string, tone?: string, attrs?: string, divider?: bool, disabled?: bool}> $items */
function crm_action_icons(array $items): string
{
    if ($items === []) {
        return '';
    }
    $html = '<div class="crm-row-actions" role="group" aria-label="Row actions">';
    foreach ($items as $item) {
        if (!empty($item['divider'])) {
            continue;
        }
        $label = (string)($item['label'] ?? '');
        $icon = e((string)($item['icon'] ?? 'bi-three-dots'));
        $title = e($label);
        $tone = crm_action_tone($item);
        $cls = 'crm-row-actions__btn crm-row-actions__btn--' . $tone;
        if (!empty($item['disabled'])) {
            $html .= '<span class="' . $cls . ' is-disabled" title="' . $title . '"><i class="bi ' . $icon . '"></i></span>';
            continue;
        }
        $url = (string)($item['url'] ?? '#');
        $attrs = (string)($item['attrs'] ?? '');
        $html .= '<a class="' . $cls . '" href="' . e($url) . '" title="' . $title . '" ' . $attrs . '>';
        $html .= '<i class="bi ' . $icon . '"></i></a>';
    }
    $html .= '</div>';

    return $html;
}

/** @deprecated Use crm_action_icons() — kept for compatibility */
function crm_action_dropdown(string $id, array $items): string
{
    return crm_action_icons($items);
}

/** @param list<array{label: string, url: string, icon: string, primary?: bool}> $actions */
function crm_quick_actions(array $actions): string
{
    $html = '<nav class="crm-quick-actions" aria-label="Quick actions">';
    foreach ($actions as $a) {
        $cls = !empty($a['primary']) ? 'btn btn-primary btn-sm' : 'btn btn-outline-secondary btn-sm';
        $html .= '<a class="' . $cls . '" href="' . e((string)$a['url']) . '">';
        $html .= '<i class="bi ' . e((string)($a['icon'] ?? 'bi-arrow-right')) . ' me-1"></i>';
        $html .= e((string)$a['label']) . '</a>';
    }
    $html .= '</nav>';

    return $html;
}

function crm_page_header(string $title, string $subtitle = '', string $actionsHtml = ''): string
{
    $html = '<header class="crm-page-head">';
    $html .= '<div class="crm-page-head__text"><h1 class="crm-page-head__title">' . e($title) . '</h1>';
    if ($subtitle !== '') {
        $html .= '<p class="crm-page-head__sub">' . $subtitle . '</p>';
    }
    $html .= '</div>';
    if ($actionsHtml !== '') {
        $html .= '<div class="crm-page-head__actions">' . $actionsHtml . '</div>';
    }
    $html .= '</header>';

    return $html;
}

function crm_table_open(string $tableId = '', bool $compact = true): string
{
    $cls = 'crm-table-scroll' . ($compact ? ' crm-table-scroll--compact' : '');
    $id = $tableId !== '' ? ' id="' . e($tableId) . '"' : '';

    return '<div class="' . $cls . '"><table class="table table-sm mb-0 crm-data-table"' . $id . '>';
}

function crm_table_close(): string
{
    return '</table></div>';
}
