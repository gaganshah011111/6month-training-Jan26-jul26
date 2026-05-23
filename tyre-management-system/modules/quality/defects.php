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
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$a = qc_defect_analytics($pdo, $from, $to);
$maxDefect = 1;
foreach ($a['top_defects'] as $d) {
    $maxDefect = max($maxDefect, (int)$d['total']);
}
?>

<div class="qc-page">
    <?php qc_render_flow_bar('qc'); ?>

    <header class="qc-page__head">
        <div>
            <h1 class="qc-page__title">Defect Tracking</h1>
            <p class="qc-page__sub">Analytics for reject causes, machines, shifts, and tyre types</p>
        </div>
    </header>

    <form method="get" class="row g-2 mb-3 align-items-end">
        <input type="hidden" name="page" value="quality/defects">
        <div class="col-auto"><label class="form-label small">From</label>
            <input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
        <div class="col-auto"><label class="form-label small">To</label>
            <input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
        <div class="col-auto"><button type="submit" class="btn btn-sm qc-btn-primary">Apply</button></div>
    </form>

    <div class="qc-kpis qc-kpis--compact mb-3">
        <article class="qc-kpi"><span class="qc-kpi__label">Inspections</span><span class="qc-kpi__value"><?= e((string)($a['summary']['inspections'] ?? 0)) ?></span></article>
        <article class="qc-kpi"><span class="qc-kpi__label">Inspected qty</span><span class="qc-kpi__value"><?= e(qc_format_qty((int)($a['summary']['inspected'] ?? 0))) ?></span></article>
        <article class="qc-kpi qc-kpi--reject"><span class="qc-kpi__label">Reject %</span><span class="qc-kpi__value"><?= e((string)$a['reject_pct']) ?>%</span></article>
        <article class="qc-kpi qc-kpi--pass"><span class="qc-kpi__label">Passed qty</span><span class="qc-kpi__value"><?= e(qc_format_qty((int)($a['summary']['passed'] ?? 0))) ?></span></article>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="qc-card">
                <header class="qc-card__head"><h2 class="qc-card__title">Most common defects</h2></header>
                <div class="qc-card__body">
                    <?php foreach ($a['top_defects'] as $d): ?>
                        <?php $pct = round(((int)$d['total'] / $maxDefect) * 100); ?>
                        <div class="qc-bar-row">
                            <span class="qc-bar-row__label"><?= e((string)$d['defect_label']) ?></span>
                            <div class="qc-bar-row__track"><div class="qc-bar-row__fill" style="width:<?= $pct ?>%"></div></div>
                            <span class="qc-bar-row__val"><?= e(qc_format_qty((int)$d['total'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($a['top_defects'] === []): ?><p class="qc-empty">No defect data.</p><?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="qc-card">
                <header class="qc-card__head"><h2 class="qc-card__title">Machine-wise defects</h2></header>
                <table class="qc-table">
                    <thead><tr><th>Machine</th><th class="text-end">Defect qty</th></tr></thead>
                    <tbody>
                    <?php foreach ($a['by_machine'] as $m): ?>
                        <tr><td><?= e((string)$m['machine_code']) ?></td><td class="text-end"><?= e(qc_format_qty((int)$m['defects'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="qc-card">
                <header class="qc-card__head"><h2 class="qc-card__title">Shift-wise performance</h2></header>
                <table class="qc-table">
                    <thead><tr><th>Shift</th><th class="text-end">Inspected</th><th class="text-end">Rejected</th></tr></thead>
                    <tbody>
                    <?php foreach ($a['by_shift'] as $s): ?>
                        <tr>
                            <td><?= e((string)$s['shift']) ?></td>
                            <td class="text-end"><?= e(qc_format_qty((int)$s['inspected'])) ?></td>
                            <td class="text-end"><?= e(qc_format_qty((int)$s['rejected'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="qc-card">
                <header class="qc-card__head"><h2 class="qc-card__title">Tyre-wise quality</h2></header>
                <table class="qc-table">
                    <thead><tr><th>Tyre type</th><th class="text-end">Inspected</th><th class="text-end">Rejected</th></tr></thead>
                    <tbody>
                    <?php foreach ($a['by_tyre'] as $t): ?>
                        <tr>
                            <td><?= e((string)$t['tyre_type']) ?></td>
                            <td class="text-end"><?= e(qc_format_qty((int)$t['inspected'])) ?></td>
                            <td class="text-end"><?= e(qc_format_qty((int)$t['rejected'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</div>
