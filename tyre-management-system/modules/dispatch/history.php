<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/dispatch_service.php';
require_once __DIR__ . '/../../includes/logistics_service.php';
require_once __DIR__ . '/../../includes/production_service.php';

if (!has_role(['Dispatch Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$search = trim((string)($_GET['q'] ?? ''));
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$status = (string)($_GET['status'] ?? '');
$customer = trim((string)($_GET['customer'] ?? ''));
$tyreType = trim((string)($_GET['tyre_type'] ?? ''));
$driverId = (int)($_GET['driver_id'] ?? 0);
$vehicleNo = trim((string)($_GET['vehicle_no'] ?? ''));
$export = (string)($_GET['export'] ?? '');

$filters = [
    'customer' => $customer,
    'tyre_type' => $tyreType,
    'driver_id' => $driverId,
    'vehicle_no' => $vehicleNo,
];
$rows = dispatch_list($pdo, $search, $from, $to, $status, $filters);
$driversList = logistics_list_drivers($pdo);

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
    . '&status=' . rawurlencode($status)
    . '&customer=' . rawurlencode($customer)
    . '&tyre_type=' . rawurlencode($tyreType)
    . '&driver_id=' . (string)$driverId
    . '&vehicle_no=' . rawurlencode($vehicleNo);
?>

<div class="dsp-page">
    <header class="dsp-page__head">
        <div>
            <h1 class="dsp-page__title">Dispatch History</h1>
            <p class="dsp-page__sub">Delivered dispatches — search, filter, view challan, and export.</p>
        </div>
        <nav class="dsp-nav-quick">
            <a href="<?= e(route_url('dispatch/new')) ?>">New dispatch</a>
            <a href="<?= e(route_url('dispatch/logistics')) ?>">Logistics</a>
            <a href="<?= e(route_url('reports/dispatch')) ?>">Reports</a>
        </nav>
    </header>

    <form method="get" class="dsp-filter-form row g-2 mb-3 align-items-end">
        <input type="hidden" name="page" value="dispatch/history">
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Search</label>
            <input class="form-control form-control-sm" name="q" value="<?= e($search) ?>" placeholder="ID, invoice, customer…">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Customer</label>
            <input class="form-control form-control-sm" name="customer" value="<?= e($customer) ?>" placeholder="Customer name">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Tyre type</label>
            <select class="form-select form-select-sm" name="tyre_type">
                <option value="">All</option>
                <?php foreach (TYRE_TYPES as $t): ?>
                    <option value="<?= e($t) ?>" <?= $tyreType === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Driver</label>
            <select class="form-select form-select-sm" name="driver_id">
                <option value="">All</option>
                <?php foreach ($driversList as $dr): ?>
                    <option value="<?= (int)$dr['id'] ?>" <?= $driverId === (int)$dr['id'] ? 'selected' : '' ?>><?= e((string)$dr['driver_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Vehicle</label>
            <input class="form-control form-control-sm" name="vehicle_no" value="<?= e($vehicleNo) ?>" placeholder="Vehicle no.">
        </div>
        <div class="col-auto">
            <label class="form-label small fw-semibold">From</label>
            <input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small fw-semibold">To</label>
            <input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small fw-semibold">Status</label>
            <select class="form-select form-select-sm" name="status">
                <option value="">All</option>
                <?php foreach (DISPATCH_STATUSES as $st): ?>
                    <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto d-flex gap-1 flex-wrap">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('dispatch/history')) ?>">Reset</a>
        </div>
        <div class="col-12 col-md-auto ms-md-auto d-flex gap-1 flex-wrap">
            <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= e($baseQs) ?>&export=print" target="_blank">Print list</a>
            <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= e($baseQs) ?>&export=pdf" target="_blank">Download PDF</a>
            <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= e($baseQs) ?>&export=csv">Export Excel</a>
        </div>
    </form>

    <div class="dsp-table-wrap dsp-table-scroll">
        <table class="dsp-table">
            <thead>
                <tr>
                    <th>Dispatch ID</th>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Tyre type</th>
                    <th class="text-end">Qty</th>
                    <th>Vehicle</th>
                    <th>Driver</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php $rid = (int)$r['id']; ?>
                <tr>
                    <td><?= e((string)($r['dispatch_code'] ?? '—')) ?></td>
                    <td><?= e((string)$r['invoice_no']) ?></td>
                    <td><?= e((string)$r['customer_name']) ?></td>
                    <td><?= e((string)($r['tyre_type'] ?? '—')) ?></td>
                    <td class="text-end"><?= e(dispatch_format_qty((int)$r['qty'])) ?></td>
                    <td><?= e((string)($r['vehicle_no'] ?? '—')) ?></td>
                    <td><?= e((string)($r['driver_name'] ?? '—')) ?></td>
                    <td><?= e((string)$r['dispatch_date']) ?></td>
                    <td><span class="dsp-badge dsp-badge--<?= e(dispatch_status_badge((string)$r['status'])) ?>"><?= e((string)$r['status']) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(dispatch_slip_url($rid)) ?>" class="btn btn-link btn-sm p-0" target="_blank" title="View dispatch">View</a>
                        <span class="text-muted">|</span>
                        <a href="<?= e(dispatch_slip_url($rid, 'print')) ?>" class="btn btn-link btn-sm p-0" target="_blank" title="Print challan">Print</a>
                        <span class="text-muted">|</span>
                        <a href="<?= e(dispatch_slip_url($rid)) ?>" class="btn btn-link btn-sm p-0" target="_blank" title="Download PDF">PDF</a>
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
