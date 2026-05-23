<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';

if (!has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$search = trim((string)($_GET['q'] ?? ''));
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$status = (string)($_GET['status'] ?? '');
$export = (string)($_GET['export'] ?? '');

$rows = dispatch_list($pdo, $search, $from, $to, $status);

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dispatch-history.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Dispatch ID', 'Invoice', 'Customer', 'Tyre Type', 'Qty', 'Vehicle', 'Driver', 'Transport', 'Date', 'Status']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['dispatch_code'] ?? '',
            $r['invoice_no'] ?? '',
            $r['customer_name'] ?? '',
            $r['tyre_type'] ?? '',
            $r['qty'] ?? '',
            $r['vehicle_no'] ?? '',
            $r['driver_name'] ?? '',
            $r['transport_company'] ?? '',
            $r['dispatch_date'] ?? '',
            $r['status'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

if ($export === 'pdf' || $export === 'print') {
    $reportTitle = 'Dispatch History';
    require __DIR__ . '/history_print.php';
    exit;
}

$baseQs = 'page=dispatch/history&q=' . rawurlencode($search)
    . '&from=' . rawurlencode($from) . '&to=' . rawurlencode($to)
    . '&status=' . rawurlencode($status);
?>

<div class="dsp-page">
    <header class="dsp-page__head">
        <div>
            <h1 class="dsp-page__title">Dispatch History</h1>
            <p class="dsp-page__sub">Delivered dispatches — search, filter, and export.</p>
        </div>
        <nav class="dsp-nav-quick">
            <a href="<?= e(route_url('dispatch/new')) ?>">New dispatch</a>
            <a href="<?= e(route_url('dispatch/logistics')) ?>">Logistics</a>
            <a href="<?= e(route_url('reports/dispatch')) ?>">Reports</a>
        </nav>
    </header>

    <form method="get" class="row g-2 mb-3 align-items-end">
        <input type="hidden" name="page" value="dispatch/history">
        <div class="col-md-3">
            <label class="form-label small">Search</label>
            <input class="form-control form-control-sm" name="q" value="<?= e($search) ?>" placeholder="ID, invoice, customer…">
        </div>
        <div class="col-auto">
            <label class="form-label small">From</label>
            <input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small">To</label>
            <input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small">Status</label>
            <select class="form-select form-select-sm" name="status">
                <option value="">All</option>
                <?php foreach (DISPATCH_STATUSES as $st): ?>
                    <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </div>
        <div class="col-auto ms-md-auto d-flex gap-1 flex-wrap">
            <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= e($baseQs) ?>&export=print" target="_blank">Print</a>
            <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= e($baseQs) ?>&export=pdf" target="_blank">PDF</a>
            <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= e($baseQs) ?>&export=csv">Excel</a>
        </div>
    </form>

    <div class="dsp-table-wrap">
        <table class="dsp-table">
            <thead>
                <tr>
                    <th>Dispatch ID</th><th>Invoice</th><th>Customer</th><th>Tyre type</th>
                    <th class="text-end">Qty</th><th>Vehicle</th><th>Driver</th><th>Dispatch date</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e((string)($r['dispatch_code'] ?? '—')) ?></td>
                    <td><?= e((string)$r['invoice_no']) ?></td>
                    <td><?= e((string)$r['customer_name']) ?></td>
                    <td><?= e((string)($r['tyre_type'] ?? '—')) ?></td>
                    <td class="text-end"><?= e(dispatch_format_qty((int)$r['qty'])) ?></td>
                    <td><?= e((string)($r['vehicle_no'] ?? '—')) ?></td>
                    <td><?= e((string)($r['driver_name'] ?? '—')) ?><?php if (!empty($r['driver_id']) && empty($r['registered_driver_name'])): ?><span class="text-muted small"> (unregistered)</span><?php endif; ?></td>
                    <td><?= e((string)$r['dispatch_date']) ?></td>
                    <td><span class="dsp-badge dsp-badge--<?= e(dispatch_status_badge((string)$r['status'])) ?>"><?= e((string)$r['status']) ?></span></td>
                    <td class="text-nowrap">
                        <a href="<?= e(dispatch_slip_url((int)$r['id'])) ?>" target="_blank" class="btn btn-link btn-sm p-0">Invoice PDF</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="10" class="dsp-empty">No dispatch records match your filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
