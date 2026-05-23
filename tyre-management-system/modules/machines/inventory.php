<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/machine_service.php';

if (!has_role(['Super Admin', 'Production Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$statusFilter = (string)($_GET['status'] ?? '');
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$activityOnly = isset($_GET['activity_only']) && $_GET['activity_only'] === '1';

$filters = [
    'status' => $statusFilter,
    'from' => $from !== '' ? $from : null,
    'to' => $to !== '' ? $to : null,
    'activity_only' => $activityOnly,
];
$rows = mach_inventory($pdo, $filters);
$dates = mach_parse_optional_dates($from !== '' ? $from : null, $to !== '' ? $to : null);
$hasPeriod = $dates['from'] !== null || $dates['to'] !== null;

$export = (string)($_GET['export'] ?? '');
if ($export === 'excel') {
    mach_export_inventory_excel($rows, $dates['from'], $dates['to']);
    exit;
}
if ($export === 'pdf' || $export === 'print') {
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/inventory_print.php';
    exit;
}

$filterQs = array_filter([
    'page' => 'machines/inventory',
    'status' => $statusFilter !== '' ? $statusFilter : null,
    'from' => $from !== '' ? $from : null,
    'to' => $to !== '' ? $to : null,
    'activity_only' => $activityOnly ? '1' : null,
]);
$exportBase = 'index.php?' . http_build_query($filterQs);
?>

<div class="prod-page mach-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Machine Inventory</h1>
            <p class="prod-page__sub">All machines ever registered. Date range is optional — use it to see production activity in a period.</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('reports/production')) ?>">Production Reports</a>
        </nav>
    </header>

    <?php require __DIR__ . '/_nav.php'; ?>

    <form method="get" class="mach-filter-bar" id="machInventoryFilter">
        <input type="hidden" name="page" value="machines/inventory">
        <div class="mach-filter-bar__grid">
            <div class="mach-filter-bar__field">
                <label for="inv_from">From date</label>
                <input type="date" class="form-control form-control-sm" name="from" id="inv_from" value="<?= e($from) ?>">
            </div>
            <div class="mach-filter-bar__field">
                <label for="inv_to">To date</label>
                <input type="date" class="form-control form-control-sm" name="to" id="inv_to" value="<?= e($to) ?>">
            </div>
            <div class="mach-filter-bar__field">
                <label for="inv_status">Status</label>
                <select class="form-select form-select-sm" name="status" id="inv_status">
                    <option value="">All statuses</option>
                    <?php foreach (MACHINE_MASTER_STATUSES as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mach-filter-bar__field mach-filter-bar__field--check">
                <label class="form-check-label small">
                    <input type="checkbox" class="form-check-input" name="activity_only" value="1" <?= $activityOnly ? 'checked' : '' ?>>
                    Only machines with production in period
                </label>
            </div>
        </div>
        <div class="mach-filter-bar__actions">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Search</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('machines/inventory')) ?>">Reset</a>
            <a href="<?= e($exportBase . '&export=pdf') ?>" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
            <a href="<?= e($exportBase . '&export=excel') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
    </form>

    <?php if ($hasPeriod): ?>
        <p class="mach-filter-hint small text-muted mb-2">
            Showing <?= e((string)count($rows)) ?> machine(s)
            <?php if ($dates['from'] && $dates['to']): ?>
                · period <?= e($dates['from']) ?> to <?= e($dates['to']) ?>
            <?php elseif ($dates['from']): ?>
                · from <?= e($dates['from']) ?>
            <?php else: ?>
                · until <?= e((string)$dates['to']) ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <section class="prod-card prod-card--table mach-print-area">
        <div class="prod-card__head d-flex justify-content-between align-items-center">
            <h2 class="prod-card__title mb-0">Machine register</h2>
            <span class="small text-muted"><?= e((string)count($rows)) ?> row(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm prod-table mb-0">
                <thead>
                    <tr>
                        <th>Code</th><th>Name</th><th>Department</th><th>Section</th>
                        <th>Assigned operator</th><th>Status</th><th>Added</th><th>Last production</th>
                        <?php if ($hasPeriod): ?>
                            <th class="text-end">Entries</th><th>Last in period</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $mb = mach_status_badge((string)$r['status']); ?>
                    <tr class="<?= (int)$r['is_active'] === 0 ? 'table-secondary' : '' ?>">
                        <td><strong><?= e($r['machine_code']) ?></strong></td>
                        <td><?= e($r['machine_name']) ?></td>
                        <td><?= e($r['department']) ?></td>
                        <td><?= e($r['section']) ?></td>
                        <td><?= e($r['operator']) ?></td>
                        <td><span class="<?= e($mb['class']) ?>"><?= e($mb['label']) ?></span></td>
                        <td class="text-nowrap"><?= e($r['added_date']) ?></td>
                        <td class="text-nowrap"><?= e($r['last_production']) ?></td>
                        <?php if ($hasPeriod): ?>
                            <td class="text-end"><?= e((string)($r['period_entries'] ?? 0)) ?></td>
                            <td class="text-nowrap"><?= e((string)($r['last_in_period'] ?? '—')) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?= $hasPeriod ? 10 : 8 ?>" class="text-center text-muted py-4">No machines match your filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
