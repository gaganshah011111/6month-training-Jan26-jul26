<?php
declare(strict_types=1);

/**
 * Ralson ERP — unified page shell (HR Command Center visual language).
 * Loaded on every authenticated module page via includes/header.php.
 * New modules: put content inside the auto wrapper in index.php; use .erp-page__* helpers when needed.
 */
require_once __DIR__ . '/functions.php';

/** Canonical wrapper class for module content. */
function erp_ui_page_class(): string
{
    return 'erp-page';
}

/** Page-specific stylesheets (attendance, HR dashboard, leave, reports). */
function erp_ui_extra_stylesheets(): array
{
    $page = (string)($_GET['page'] ?? '');

    $map = [
        'attendance/list' => ['attendance-module.css'],
        'hr/dashboard' => ['hr-dashboard.css'],
        'leave/list' => ['leave-dashboard.css'],
        'reports/hr' => ['hr-reports.css'],
        'employee/leave' => ['leave-dashboard.css', 'employee-module.css'],
        'employee/dashboard' => ['employee-module.css'],
        'employee/profile' => ['employee-module.css'],
        'employee/attendance' => ['employee-module.css'],
        'employee/salary' => ['employee-module.css'],
    ];

    return $map[$page] ?? [];
}

/** Load global ERP shell CSS (+ optional page CSS). */
function erp_ui_enqueue(): void
{
    static $done = false;
    if ($done || !is_logged_in()) {
        return;
    }
    $done = true;

    $base = __DIR__ . '/../assets/css/';
    $files = ['erp-module.css'];

    foreach (erp_ui_extra_stylesheets() as $extra) {
        $files[] = $extra;
    }

    foreach ($files as $file) {
        $path = $base . $file;
        if (!is_file($path)) {
            continue;
        }
        echo '<link href="assets/css/' . e($file) . '?v=' . e((string)filemtime($path)) . '" rel="stylesheet">' . "\n";
    }
}

function erp_ui_flash(): void
{
    $flash = get_flash();
    if (!$flash) {
        return;
    }
    echo '<div class="alert alert-' . e((string)$flash['type']) . ' erp-page__flash py-2 mb-2 alert-dismissible fade show" role="alert">'
        . e((string)$flash['message'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

/**
 * Standard page header block.
 *
 * @param array{title:string,subtitle?:string,actions_html?:string} $opts
 */
function erp_ui_page_head(array $opts): void
{
    $title = (string)($opts['title'] ?? '');
    $subtitle = (string)($opts['subtitle'] ?? '');
    $actions = (string)($opts['actions_html'] ?? '');
    ?>
    <header class="erp-page__top">
        <div>
            <h1 class="erp-page__title"><?= e($title) ?>
                <?php if ($subtitle !== ''): ?><span><?= e($subtitle) ?></span><?php endif; ?>
            </h1>
        </div>
        <?php if ($actions !== ''): ?>
            <div class="erp-page__top-actions"><?= $actions ?></div>
        <?php endif; ?>
    </header>
    <?php
}
