<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';
require_once __DIR__ . '/../../includes/sales_service.php';

if (!has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$d = dispatch_dashboard($pdo);
$queueLines = sales_dispatch_queue_list($pdo);
$readyCount = 0;
foreach ($queueLines as $line) {
    if ((int)($line['dispatchable_qty'] ?? 0) > 0) {
        $readyCount++;
    }
}
?>

<div class="dsp-page dsp-page--compact">
    <header class="dsp-page__head">
        <div>
            <h1 class="dsp-page__title">Dispatch Dashboard</h1>
            <p class="dsp-page__sub">Manage tyre dispatches, vehicles, and pending sales deliveries.</p>
        </div>
        <nav class="dsp-nav-quick">
            <a href="<?= e(route_url('dispatch/dashboard')) ?>">Dispatch Queue</a>
            <a href="<?= e(route_url('dispatch/history')) ?>">History</a>
            <a href="<?= e(route_url('dispatch/logistics')) ?>">Logistics</a>
        </nav>
    </header>

    <?php $flowActive = 'dispatch';
    require __DIR__ . '/../dispatch/_dispatch_flow.php'; ?>

    <div class="dsp-kpis dsp-kpis--compact">
        <article class="dsp-kpi">
            <span class="dsp-kpi__label">Shipped Today</span>
            <span class="dsp-kpi__value"><?= e(dispatch_format_qty($d['today_qty'])) ?></span>
        </article>
        <article class="dsp-kpi">
            <span class="dsp-kpi__label">Queue Lines</span>
            <span class="dsp-kpi__value"><?= e((string)count($queueLines)) ?></span>
        </article>
        <article class="dsp-kpi">
            <span class="dsp-kpi__label">Ready Orders</span>
            <span class="dsp-kpi__value"><?= e((string)$readyCount) ?></span>
        </article>
    </div>

    <section class="dsp-panel mb-3">
        <div class="dsp-panel__head">
            <h2 class="dsp-section__heading mb-0">Dispatch Queue</h2>
            <a href="<?= e(route_url('dispatch/new')) ?>" class="btn btn-sm btn-outline-secondary">Open dispatch form</a>
        </div>
        <p class="dsp-section-hint">All pending deliveries — status includes ready, partial, and waiting stock in one list.</p>
        <div class="dsp-table-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" class="form-control form-control-sm dsp-table-search__input" id="dsp-queue-search"
                   placeholder="Search SO number, customer, tyre type, status…" autocomplete="off">
        </div>
        <div class="dsp-panel__body dsp-panel__body--scroll dsp-panel__body--queue dsp-table-scroll">
            <?php
            $crmQueueRows = $queueLines;
            $showActions = true;
            $compact = false;
            require __DIR__ . '/../dispatch/_crm_queue_table.php';
            ?>
        </div>
    </section>

    <section class="dsp-panel">
        <div class="dsp-panel__head">
            <h2 class="dsp-section__heading mb-0">Recent Dispatches</h2>
        </div>
        <div class="dsp-table-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" class="form-control form-control-sm dsp-table-search__input" id="dsp-recent-search"
                   placeholder="Search dispatch ID, customer, date…" autocomplete="off">
        </div>
        <div class="dsp-panel__body dsp-panel__body--scroll dsp-panel__body--recent dsp-table-scroll">
            <table class="dsp-table dsp-table--compact" id="dsp-recent-table">
                <thead>
                    <tr>
                        <th>Dispatch ID</th>
                        <th>Customer</th>
                        <th class="text-end">Qty</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($d['recent_rows'] as $r): ?>
                    <?php
                    $searchRecent = strtolower(implode(' ', [
                        (string)($r['dispatch_code'] ?? ''),
                        (string)($r['customer_name'] ?? ''),
                        (string)($r['dispatch_date'] ?? ''),
                        (string)($r['qty'] ?? ''),
                        'dispatched',
                    ]));
                    ?>
                    <tr class="dsp-recent-row" data-search="<?= e($searchRecent) ?>">
                        <td>
                            <span class="dsp-queue-ref"><?= e((string)($r['dispatch_code'] ?? '—')) ?></span>
                            <?php if (!empty($r['sales_order_id'])): ?>
                                <span class="dsp-so-tag">Sales Order Linked</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string)$r['customer_name']) ?></td>
                        <td class="text-end"><?= e(dispatch_format_qty((int)$r['qty'])) ?></td>
                        <td><?= e((string)($r['dispatch_date'] ?? '—')) ?></td>
                        <td><span class="erp-badge erp-badge--dispatched">DISPATCHED</span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($d['recent_rows'] === []): ?>
                    <tr class="dsp-table-empty-row"><td colspan="5" class="dsp-empty">No dispatches yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script src="assets/js/dispatch-dashboard.js?v=<?= e((string)@filemtime(__DIR__ . '/../../assets/js/dispatch-dashboard.js')) ?>"></script>
