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
$d = mach_dashboard($pdo);
$c = $d['counts'];
?>

<div class="prod-page mach-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Machine Overview</h1>
            <p class="prod-page__sub">Shop-floor machine register, operator assignments, and status tracking.</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('production/dashboard')) ?>">Production</a>
            <a href="<?= e(route_url('machines/list')) ?>">Add machine</a>
        </nav>
    </header>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="mach-kpis">
        <article class="mach-kpi"><span class="mach-kpi__label">Total machines</span><span class="mach-kpi__value"><?= e((string)$c['total']) ?></span></article>
        <article class="mach-kpi"><span class="mach-kpi__label">Active</span><span class="mach-kpi__value"><?= e((string)$c['active']) ?></span></article>
        <article class="mach-kpi"><span class="mach-kpi__label">Idle</span><span class="mach-kpi__value"><?= e((string)$c['idle']) ?></span></article>
        <article class="mach-kpi"><span class="mach-kpi__label">Under repair</span><span class="mach-kpi__value"><?= e((string)$c['repair']) ?></span></article>
        <article class="mach-kpi"><span class="mach-kpi__label">Scrap / off</span><span class="mach-kpi__value"><?= e((string)$c['scrap']) ?></span></article>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="prod-card prod-card--table">
                <div class="prod-card__head"><h2 class="prod-card__title">Machine-wise operator</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm prod-table mb-0">
                        <thead>
                            <tr><th>Code</th><th>Name</th><th>Dept</th><th>Operator</th><th>Shift</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($d['operator_rows'] as $r): ?>
                            <?php $mb = mach_status_badge((string)$r['status']); ?>
                            <tr>
                                <td><strong><?= e($r['machine_code']) ?></strong></td>
                                <td><?= e($r['machine_name']) ?></td>
                                <td><?= e($r['department']) ?></td>
                                <td><?= e($r['operator']) ?><?php if ($r['operator'] !== '—'): ?><span class="mach-assign-badge ms-1">Assigned</span><?php endif; ?></td>
                                <td><?= e($r['shift']) ?></td>
                                <td><span class="<?= e($mb['class']) ?>"><?= e($mb['label']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$d['operator_rows']): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No machines registered.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="prod-card mb-3">
                <div class="prod-card__head"><h2 class="prod-card__title">Department-wise count</h2></div>
                <div class="prod-card__body">
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach ($d['dept_counts'] as $dep => $cnt): ?>
                            <li class="d-flex justify-content-between py-1 border-bottom"><span><?= e($dep) ?></span><strong><?= e((string)$cnt) ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
            <section class="prod-card prod-card--table">
                <div class="prod-card__head"><h2 class="prod-card__title">Recently reassigned</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm prod-table mb-0">
                        <thead><tr><th>Machine</th><th>Operator</th><th>From</th></tr></thead>
                        <tbody>
                        <?php foreach ($d['recent_assignments'] as $a): ?>
                            <tr>
                                <td><?= e($a['machine_code']) ?></td>
                                <td><?= e($a['operator_name']) ?></td>
                                <td class="text-nowrap"><?= e($a['assigned_from']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$d['recent_assignments']): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No assignments yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
