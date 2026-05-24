<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';
require_once __DIR__ . '/../../includes/production_service.php';

if (!has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$customer = trim((string)($_GET['customer'] ?? ''));
$tyreType = (string)($_GET['tyre_type'] ?? '');
$status = (string)($_GET['status'] ?? '');
$export = (string)($_GET['export'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

$report = dispatch_report($pdo, $from, $to, $customer, $tyreType, $status);
$sum = $report['summary'];
$rows = $report['rows'];

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dispatch-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Dispatch ID', 'Date', 'Customer', 'Tyre Type', 'Qty', 'Invoice', 'Status']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['dispatch_code'] ?? '',
            $r['dispatch_date'] ?? '',
            $r['customer_name'] ?? '',
            $r['tyre_type'] ?? '',
            $r['qty'] ?? '',
            $r['invoice_no'] ?? '',
            $r['status'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

if ($export === 'pdf' || $export === 'print') {
    $reportTitle = 'Dispatch Report';
    require __DIR__ . '/dispatch_print.php';
    exit;
}

$baseQs = 'page=reports/dispatch&from=' . rawurlencode($from) . '&to=' . rawurlencode($to)
    . '&customer=' . rawurlencode($customer) . '&tyre_type=' . rawurlencode($tyreType)
    . '&status=' . rawurlencode($status);
?>

<div class="dsp-page">
    <header class="dsp-page__head">
        <div>
            <h1 class="dsp-page__title">Dispatch Reports</h1>
            <p class="dsp-page__sub">Shipment summary for sales and logistics review.</p>
        </div>
        <nav class="dsp-nav-quick">
            <a href="<?= e(route_url('dispatch/dashboard')) ?>">Dashboard</a>
            <a href="<?= e(route_url('dispatch/history')) ?>">History</a>
        </nav>
    </header>

    <div class="dsp-kpis" style="grid-template-columns: repeat(2, 1fr);">
        <article class="dsp-kpi dsp-kpi--qty">
            <div>
                <span class="dsp-kpi__label">Total dispatch orders</span>
                <span class="dsp-kpi__value"><?= e((string)$sum['total_dispatch']) ?></span>
            </div>
        </article>
        <article class="dsp-kpi dsp-kpi--done">
            <div>
                <span class="dsp-kpi__label">Total tyres dispatched (qty)</span>
                <span class="dsp-kpi__value"><?= e(dispatch_format_qty((int)$sum['total_qty'])) ?></span>
            </div>
        </article>
    </div>

    <form method="get" class="row g-2 mb-3 align-items-end">
        <input type="hidden" name="page" value="reports/dispatch">
        <div class="col-auto"><label class="form-label small">From</label>
            <input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
        <div class="col-auto"><label class="form-label small">To</label>
            <input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
        <div class="col-md-2"><label class="form-label small">Customer</label>
            <input class="form-control form-control-sm" name="customer" value="<?= e($customer) ?>" placeholder="Name contains…"></div>
        <div class="col-md-2"><label class="form-label small">Tyre type</label>
            <select class="form-select form-select-sm" name="tyre_type">
                <option value="">All</option>
                <?php foreach (TYRE_TYPES as $t): ?>
                    <option value="<?= e($t) ?>" <?= $tyreType === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-auto"><label class="form-label small">Status</label>
            <select class="form-select form-select-sm" name="status">
                <option value="">All</option>
                <?php foreach (DISPATCH_STATUSES as $st): ?>
                    <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm">Apply</button></div>
        <div class="col-auto ms-md-auto d-flex gap-1">
            <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= e($baseQs) ?>&export=print" target="_blank">Print</a>
            <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= e($baseQs) ?>&export=pdf" target="_blank">PDF</a>
            <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= e($baseQs) ?>&export=csv">Excel</a>
        </div>
    </form>

    <div class="dsp-table-wrap dsp-table-scroll">
        <table class="dsp-table">
            <thead>
                <tr>
                    <th>Dispatch ID</th><th>Date</th><th>Customer</th><th>Tyre type</th>
                    <th class="text-end">Qty</th><th>Invoice</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e((string)($r['dispatch_code'] ?? '')) ?></td>
                    <td><?= e((string)$r['dispatch_date']) ?></td>
                    <td><?= e((string)$r['customer_name']) ?></td>
                    <td><?= e((string)($r['tyre_type'] ?? '—')) ?></td>
                    <td class="text-end"><?= e(dispatch_format_qty((int)$r['qty'])) ?></td>
                    <td><?= e((string)$r['invoice_no']) ?></td>
                    <td><span class="dsp-badge dsp-badge--<?= e(dispatch_status_badge((string)$r['status'])) ?>"><?= e((string)$r['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="7" class="dsp-empty">No dispatches in this period.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
