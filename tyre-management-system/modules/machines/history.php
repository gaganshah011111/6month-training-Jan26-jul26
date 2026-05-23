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
$machineId = (int)($_GET['machine_id'] ?? 0);
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$typeFilter = (string)($_GET['type'] ?? '');

$machines = mach_list_machines($pdo, ['include_inactive' => true]);
$history = mach_combined_history($pdo, [
    'machine_id' => $machineId,
    'from' => $from !== '' ? $from : null,
    'to' => $to !== '' ? $to : null,
    'type' => $typeFilter,
]);

$counts = ['Assignment' => 0, 'Status' => 0, 'Change' => 0];
foreach ($history as $h) {
    $t = (string)($h['type'] ?? '');
    if (isset($counts[$t])) {
        $counts[$t]++;
    }
}
?>

<div class="prod-page mach-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Machine History</h1>
            <p class="prod-page__sub">Assignments, status changes, and master updates — searchable audit trail.</p>
        </div>
    </header>

    <?php require __DIR__ . '/_nav.php'; ?>

    <form method="get" class="mach-filter-bar">
        <input type="hidden" name="page" value="machines/history">
        <div class="mach-filter-bar__grid">
            <div class="mach-filter-bar__field mach-filter-bar__field--wide">
                <label for="hist_machine">Machine</label>
                <select class="form-select form-select-sm erp-select-search" name="machine_id" id="hist_machine" data-placeholder="All machines">
                    <option value="0">All machines</option>
                    <?php foreach ($machines as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= $machineId === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['machine_code']) ?> — <?= e($m['machine_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mach-filter-bar__field">
                <label for="hist_from">From date</label>
                <input type="date" class="form-control form-control-sm" name="from" id="hist_from" value="<?= e($from) ?>">
            </div>
            <div class="mach-filter-bar__field">
                <label for="hist_to">To date</label>
                <input type="date" class="form-control form-control-sm" name="to" id="hist_to" value="<?= e($to) ?>">
            </div>
            <div class="mach-filter-bar__field">
                <label for="hist_type">Event type</label>
                <select class="form-select form-select-sm" name="type" id="hist_type">
                    <option value="">All types</option>
                    <option value="Assignment" <?= $typeFilter === 'Assignment' ? 'selected' : '' ?>>Assignments</option>
                    <option value="Status" <?= $typeFilter === 'Status' ? 'selected' : '' ?>>Status changes</option>
                    <option value="Change" <?= $typeFilter === 'Change' ? 'selected' : '' ?>>Field changes</option>
                </select>
            </div>
        </div>
        <div class="mach-filter-bar__actions">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Search</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('machines/history')) ?>">Reset</a>
        </div>
    </form>

    <div class="mach-history-stats">
        <span class="mach-history-stat"><strong><?= e((string)count($history)) ?></strong> events</span>
        <span class="mach-history-stat">Assignments: <?= e((string)$counts['Assignment']) ?></span>
        <span class="mach-history-stat">Status: <?= e((string)$counts['Status']) ?></span>
        <span class="mach-history-stat">Changes: <?= e((string)$counts['Change']) ?></span>
    </div>

    <section class="prod-card mach-history-panel">
        <?php if (!$history): ?>
            <div class="mach-history-empty">
                <i class="bi bi-clock-history"></i>
                <p>No history records for the selected filters.</p>
                <p class="small text-muted mb-0">Try clearing dates or assign an operator from the Assignments page.</p>
            </div>
        <?php else: ?>
            <ul class="mach-timeline">
                <?php foreach ($history as $h): ?>
                    <?php
                    $meta = mach_history_type_meta((string)$h['type']);
                    $when = (string)($h['event_at'] ?? '');
                    $whenLabel = $when !== '' ? date('d M Y, H:i', strtotime($when)) : '—';
                    ?>
                    <li class="mach-timeline__item <?= e($meta['class']) ?>">
                        <div class="mach-timeline__icon" aria-hidden="true"><i class="bi <?= e($meta['icon']) ?>"></i></div>
                        <div class="mach-timeline__body">
                            <div class="mach-timeline__top">
                                <span class="mach-timeline__type"><?= e((string)$h['type']) ?></span>
                                <time class="mach-timeline__time"><?= e($whenLabel) ?></time>
                            </div>
                            <div class="mach-timeline__machine">
                                <strong><?= e((string)$h['machine_code']) ?></strong>
                                <span class="text-muted"><?= e((string)($h['machine_name'] ?? '')) ?></span>
                            </div>
                            <p class="mach-timeline__detail"><?= e((string)$h['detail']) ?></p>
                            <div class="mach-timeline__meta">
                                <?php if (!empty($h['shift'])): ?>
                                    <span>Shift: <?= e((string)$h['shift']) ?></span>
                                <?php endif; ?>
                                <span>From: <?= e((string)$h['from']) ?></span>
                                <span>Till: <?= e((string)$h['till']) ?></span>
                            </div>
                            <?php if (trim((string)($h['note'] ?? '')) !== ''): ?>
                                <p class="mach-timeline__note"><?= e((string)$h['note']) ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
