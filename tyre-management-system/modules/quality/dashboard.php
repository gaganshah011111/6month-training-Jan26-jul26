<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/qc_service.php';
require_once __DIR__ . '/_flow.php';

if (!has_role(['Quality Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$d = qc_dashboard($pdo);
?>

<div class="qc-page">
    <?php qc_render_flow_bar('qc'); ?>

    <header class="qc-page__head">
        <div>
            <h1 class="qc-page__title">Quality Control Dashboard</h1>
            <p class="qc-page__sub">Production curing batches → inspection → finished goods → dispatch</p>
        </div>
        <nav class="qc-nav-quick">
            <a href="<?= e(route_url('quality/pending')) ?>">Pending inspections</a>
            <a href="<?= e(route_url('quality/reports')) ?>">Reports</a>
            <a href="<?= e(route_url('quality/defects')) ?>">Defect tracking</a>
        </nav>
    </header>

    <div class="qc-kpis">
        <article class="qc-kpi qc-kpi--pending">
            <span class="qc-kpi__label">Pending inspections</span>
            <span class="qc-kpi__value"><?= e((string)$d['pending']) ?></span>
        </article>
        <article class="qc-kpi">
            <span class="qc-kpi__label">Today inspected</span>
            <span class="qc-kpi__value"><?= e((string)$d['today_inspected']) ?></span>
        </article>
        <article class="qc-kpi qc-kpi--pass">
            <span class="qc-kpi__label">Passed tyres (today)</span>
            <span class="qc-kpi__value"><?= e(qc_format_qty((int)$d['today_passed'])) ?></span>
        </article>
        <article class="qc-kpi qc-kpi--reject">
            <span class="qc-kpi__label">Rejected tyres (today)</span>
            <span class="qc-kpi__value"><?= e(qc_format_qty((int)$d['today_rejected'])) ?></span>
        </article>
        <article class="qc-kpi">
            <span class="qc-kpi__label">Rework stock</span>
            <span class="qc-kpi__value"><?= e(qc_format_qty((int)$d['rework_pending'])) ?></span>
        </article>
        <article class="qc-kpi qc-kpi--pass">
            <span class="qc-kpi__label">Pass % (today)</span>
            <span class="qc-kpi__value"><?= e((string)$d['pass_pct']) ?>%</span>
        </article>
        <article class="qc-kpi qc-kpi--reject">
            <span class="qc-kpi__label">Major defect today</span>
            <span class="qc-kpi__value qc-kpi__value--sm"><?= e((string)($d['major_defect']['defect_label'] ?? '—')) ?></span>
        </article>
    </div>

    <div class="qc-stock-strip">
        <span><strong>QC passed (dispatch ready):</strong> <?= e(qc_format_qty((int)$d['fg_dispatch'])) ?></span>
        <span><strong>Rework stock:</strong> <?= e(qc_format_qty((int)$d['fg_rework'])) ?></span>
        <span><strong>Rejected / scrap:</strong> <?= e(qc_format_qty((int)$d['fg_scrap'])) ?></span>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <section class="qc-card">
                <header class="qc-card__head"><h2 class="qc-card__title">Recent inspections</h2></header>
                <div class="table-responsive">
                    <table class="qc-table">
                        <thead>
                            <tr>
                                <th>Batch ID</th><th>Tyre type</th><th>Date</th>
                                <th class="text-end">Inspected</th><th class="text-end">Passed</th>
                                <th class="text-end">Reject</th><th>QC status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($d['recent'] as $r): ?>
                            <tr>
                                <td><?= e((string)$r['batch_code']) ?></td>
                                <td><?= e((string)$r['tyre_type']) ?></td>
                                <td><?= e((string)$r['inspection_date']) ?></td>
                                <td class="text-end"><?= e(qc_format_qty((int)$r['inspected_qty'])) ?></td>
                                <td class="text-end"><?= e(qc_format_qty((int)$r['passed_qty'])) ?></td>
                                <td class="text-end"><?= e(qc_format_qty((int)$r['rejected_qty'])) ?></td>
                                <td><span class="qc-badge qc-badge--<?= e(qc_status_badge((string)$r['qc_status'])) ?>"><?= e((string)$r['qc_status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($d['recent'] === []): ?>
                            <tr><td colspan="7" class="qc-empty">No inspections yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="qc-card mb-3">
                <header class="qc-card__head"><h2 class="qc-card__title">Defect summary (7 days)</h2></header>
                <ul class="qc-list">
                <?php foreach ($d['defect_summary'] as $def): ?>
                    <li><span><?= e((string)$def['defect_label']) ?></span><strong><?= e(qc_format_qty((int)$def['total'])) ?></strong></li>
                <?php endforeach; ?>
                <?php if ($d['defect_summary'] === []): ?>
                    <li class="qc-empty">No defects logged.</li>
                <?php endif; ?>
                </ul>
            </section>
            <section class="qc-card mb-3">
                <header class="qc-card__head"><h2 class="qc-card__title">Shift-wise (7 days)</h2></header>
                <ul class="qc-list">
                <?php foreach ($d['shift_stats'] as $s): ?>
                    <li>
                        <span><?= e((string)$s['shift']) ?></span>
                        <strong><?= e((string)$s['inspections']) ?> insp · <?= e(qc_format_qty((int)$s['passed'])) ?> pass</strong>
                    </li>
                <?php endforeach; ?>
                </ul>
            </section>
            <section class="qc-card">
                <header class="qc-card__head"><h2 class="qc-card__title">Machine reject stats</h2></header>
                <ul class="qc-list">
                <?php foreach ($d['machine_rejects'] as $m): ?>
                    <li><span><?= e((string)$m['machine_code']) ?></span><strong><?= e(qc_format_qty((int)$m['rejected'])) ?></strong></li>
                <?php endforeach; ?>
                <?php if ($d['machine_rejects'] === []): ?>
                    <li class="qc-empty">No rejects in period.</li>
                <?php endif; ?>
                </ul>
            </section>
        </div>
    </div>
</div>
