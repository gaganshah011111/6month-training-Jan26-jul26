<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_service.php';

if (!has_role(['Inventory Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$d = inv_dashboard($pdo);
$alertCount = (int)$d['low_count'] + (int)$d['out_count'];
$alertRows = array_merge($d['out_rows'], $d['low_rows']);
?>

<div class="inv-page inv-dash">
    <header class="inv-page__head inv-dash__head">
        <div>
            <h1 class="inv-page__title">Inventory Dashboard</h1>
            <p class="inv-page__sub">Current stock, alerts, and recent warehouse activity.</p>
        </div>
        <nav class="inv-nav-quick inv-dash__nav">
            <a href="<?= e(route_url('inventory/add-stock')) ?>">Add Stock</a>
            <a href="<?= e(route_url('inventory/use-stock')) ?>">Use Stock</a>
            <a href="<?= e(route_url('inventory/materials')) ?>">Materials</a>
            <a href="<?= e(route_url('reports/inventory')) ?>">Reports</a>
        </nav>
    </header>

    <section class="inv-dash__section">
        <div class="inv-dash-kpis">
            <article class="inv-dash-kpi inv-dash-kpi--stock">
                <span class="inv-dash-kpi__icon" aria-hidden="true">▣</span>
                <div>
                    <span class="inv-dash-kpi__label">Current stock</span>
                    <span class="inv-dash-kpi__value"><?= e(inv_format_qty((float)$d['total_remaining'])) ?></span>
                </div>
            </article>
            <article class="inv-dash-kpi inv-dash-kpi--in">
                <span class="inv-dash-kpi__icon" aria-hidden="true">+</span>
                <div>
                    <span class="inv-dash-kpi__label">Today added</span>
                    <span class="inv-dash-kpi__value"><?= e(inv_format_qty((float)$d['today_added'])) ?></span>
                </div>
            </article>
            <article class="inv-dash-kpi inv-dash-kpi--out">
                <span class="inv-dash-kpi__icon" aria-hidden="true">−</span>
                <div>
                    <span class="inv-dash-kpi__label">Today used</span>
                    <span class="inv-dash-kpi__value"><?= e(inv_format_qty((float)$d['today_used'])) ?></span>
                </div>
            </article>
            <article class="inv-dash-kpi inv-dash-kpi--alert">
                <span class="inv-dash-kpi__icon" aria-hidden="true">⚠</span>
                <div>
                    <span class="inv-dash-kpi__label">Low stock alerts</span>
                    <span class="inv-dash-kpi__value"><?= e((string)$alertCount) ?></span>
                </div>
            </article>
        </div>
    </section>

    <?php if (isset($d['fg_dispatch_ready'])): ?>
    <section class="inv-dash__section">
        <h2 class="inv-dash__heading">Finished goods (QC)</h2>
        <div class="inv-dash-kpis" style="grid-template-columns: repeat(3, 1fr);">
            <article class="inv-dash-kpi inv-dash-kpi--stock">
                <span class="inv-dash-kpi__label">QC passed (dispatch)</span>
                <span class="inv-dash-kpi__value"><?= e((string)(int)$d['fg_dispatch_ready']) ?></span>
            </article>
            <article class="inv-dash-kpi">
                <span class="inv-dash-kpi__label">Rework stock</span>
                <span class="inv-dash-kpi__value"><?= e((string)(int)$d['fg_rework']) ?></span>
            </article>
            <article class="inv-dash-kpi inv-dash-kpi--alert">
                <span class="inv-dash-kpi__label">Rejected / scrap</span>
                <span class="inv-dash-kpi__value"><?= e((string)(int)$d['fg_scrap']) ?></span>
            </article>
        </div>
        <p class="small text-muted mb-0">Dispatch module uses QC-passed finished goods only.</p>
    </section>
    <?php endif; ?>

    <?php if (($d['dept_today'] ?? []) !== []): ?>
    <section class="inv-dash__section">
        <h2 class="inv-dash__heading">Today’s consumption by department</h2>
        <div class="inv-dash-dept">
            <?php foreach ($d['dept_today'] as $item): ?>
                <span class="inv-dash-dept__chip"><?= e((string)$item['label']) ?></span>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (($d['expiring'] ?? []) !== []): ?>
    <section class="inv-dash__section">
        <h2 class="inv-dash__heading">Near expiry (batch)</h2>
        <div class="inv-dash-alerts">
            <?php foreach ($d['expiring'] as $ex): ?>
                <div class="inv-dash-alert inv-dash-alert--low">
                    <span class="inv-dash-alert__icon">⏳</span>
                    <span class="inv-dash-alert__body">
                        <strong><?= e((string)$ex['material_name']) ?></strong>
                        <span>Batch <?= e((string)($ex['batch_no'] ?? '—')) ?> · Expires <?= e((string)$ex['expiry_date']) ?></span>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="inv-dash__section">
        <h2 class="inv-dash__heading">Inventory alerts</h2>
        <?php if ($alertRows === []): ?>
            <p class="inv-dash__empty">All materials are above minimum levels.</p>
        <?php else: ?>
            <div class="inv-dash-alerts">
                <?php foreach ($alertRows as $r): ?>
                    <?php
                    $meta = inv_stock_status_meta((float)$r['stock_qty'], (float)$r['reorder_level']);
                    $isOut = $meta['code'] === 'out';
                    ?>
                    <a class="inv-dash-alert inv-dash-alert--<?= $isOut ? 'out' : 'low' ?>" href="<?= e(route_url('inventory/add-stock')) ?>">
                        <span class="inv-dash-alert__icon"><?= $isOut ? '🔴' : '⚠' ?></span>
                        <span class="inv-dash-alert__body">
                            <strong><?= e((string)$r['material_name']) ?></strong>
                            <span><?= $isOut ? 'Out of stock' : 'Low stock' ?> · Remaining: <?= e(inv_format_qty((float)$r['stock_qty'], (string)$r['unit'])) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="inv-dash__section">
        <h2 class="inv-dash__heading">Material stock</h2>
        <div class="inv-dash-table-wrap">
            <table class="inv-dash-table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th class="text-end">Current stock</th>
                        <th>Unit</th>
                        <th class="text-end">Minimum</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($d['stock_rows'] as $r): ?>
                    <?php
                    $current = (float)$r['current_stock'];
                    $meta = inv_stock_status_meta($current, (float)$r['reorder_level']);
                    ?>
                    <tr>
                        <td class="inv-dash-table__name">
                            <?= e((string)$r['material_name']) ?>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 inv-history-btn" data-id="<?= (int)$r['id'] ?>" data-name="<?= e((string)$r['material_name']) ?>">History</button>
                        </td>
                        <td class="text-end fw-semibold"><?= e(inv_format_qty($current)) ?></td>
                        <td><?= e((string)$r['unit']) ?></td>
                        <td class="text-end text-muted"><?= e(inv_format_qty((float)$r['reorder_level'])) ?></td>
                        <td><span class="inv-dash-status inv-dash-status--<?= e($meta['badge']) ?>"><?= e($meta['icon'] !== '' ? $meta['icon'] . ' ' : '') ?><?= e($meta['label']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($d['stock_rows'] === []): ?>
                    <tr><td colspan="5" class="inv-dash-table__empty">No active materials. <a href="<?= e(route_url('inventory/materials')) ?>">Add materials</a></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="inv-dash__section inv-dash__section--last">
        <h2 class="inv-dash__heading">Recent activity</h2>
        <?php if ($d['recent'] === []): ?>
            <p class="inv-dash__empty">No stock activity yet.</p>
        <?php else: ?>
            <ul class="inv-dash-timeline">
                <?php foreach ($d['recent'] as $a): ?>
                    <?php $tm = inv_activity_timeline_meta((string)$a['activity_type']); ?>
                    <li class="inv-dash-timeline__item inv-dash-timeline__item--<?= e($tm['tone']) ?>">
                        <span class="inv-dash-timeline__icon"><?= e($tm['icon']) ?></span>
                        <span class="inv-dash-timeline__text"><?= e((string)$a['message']) ?></span>
                        <time class="inv-dash-timeline__time"><?= e(inv_time_ago((string)$a['activity_at'])) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<div class="modal fade" id="invHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2"><h5 class="modal-title" id="invHistoryTitle">History</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-0"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Type</th><th class="text-end">Qty</th><th>Dept</th><th>Operator</th><th>Remarks</th></tr></thead><tbody id="invHistoryBody"></tbody></table></div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.inv-history-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const id = this.dataset.id, name = this.dataset.name;
        document.getElementById('invHistoryTitle').textContent = 'History — ' + name;
        const body = document.getElementById('invHistoryBody');
        body.innerHTML = '<tr><td colspan="6" class="p-3 text-muted">Loading…</td></tr>';
        new bootstrap.Modal(document.getElementById('invHistoryModal')).show();
        fetch('index.php?page=api/material-history&material_id=' + id).then(r => r.json()).then(function (d) {
            if (!d.history || !d.history.length) { body.innerHTML = '<tr><td colspan="6" class="p-3 text-muted">No transactions.</td></tr>'; return; }
            body.innerHTML = d.history.map(h => '<tr><td>'+h.date+'</td><td>'+h.type+'</td><td class="text-end">'+(h.quantity>=0?'+':'')+h.quantity+'</td><td>'+(h.department||'—')+'</td><td>'+(h.operator||'—')+'</td><td>'+(h.remarks||h.reason||'—')+'</td></tr>').join('');
        });
    });
});
</script>
