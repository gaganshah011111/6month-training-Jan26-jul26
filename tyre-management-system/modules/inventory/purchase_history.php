<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_service.php';
require_once __DIR__ . '/../../includes/crm_ui.php';
require_once __DIR__ . '/../../includes/inv_ui.php';

if (!has_role(['Inventory Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
inv_purchase_ensure_schema($pdo);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'payments') {
    header('Content-Type: application/json; charset=utf-8');
    $inwardId = (int)($_GET['inward_id'] ?? 0);
    echo json_encode([
        'payments' => $inwardId > 0 ? inv_purchase_list_payments($pdo, $inwardId) : [],
    ], JSON_THROW_ON_ERROR);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_payment') {
    verify_csrf();
    $inwardId = (int)($_POST['inward_id'] ?? 0);
    try {
        inv_purchase_add_payment($pdo, $inwardId, $_POST);
        set_flash('success', 'Payment recorded successfully.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    header('Location: ' . route_url('inventory/purchase-history', [
        'view' => $inwardId,
        'from' => $_POST['return_from'] ?? '',
        'to' => $_POST['return_to'] ?? '',
    ]));
    exit;
}

$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$q = trim((string)($_GET['q'] ?? ''));
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$materialId = (int)($_GET['material_id'] ?? 0);
$paymentStatus = (string)($_GET['payment_status'] ?? '');
$export = (string)($_GET['export'] ?? '');
$openPay = (int)($_GET['pay'] ?? 0);
$openPrint = (int)($_GET['print'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

$filters = [
    'from' => $from,
    'to' => $to,
    'q' => $q,
    'supplier_id' => $supplierId,
    'material_id' => $materialId,
    'payment_status' => $paymentStatus,
];
$rows = inv_purchase_list($pdo, $filters);

if ($export === 'print' || $export === 'pdf') {
    require_once __DIR__ . '/../../includes/erp_document_print.php';
    $company = erp_doc_company_name($pdo);
    erp_doc_print_begin([
        'title' => 'Purchase History',
        'back_url' => route_url('inventory/purchase-history'),
        'auto_print' => $export === 'print',
    ]);
    erp_doc_print_header($company, 'Purchase History', $from . ' to ' . $to, inv_module_label() . ' · Purchases');
    echo '<table class="slip__material"><thead><tr><th>PINV</th><th>Date</th><th>Supplier</th><th>Material</th><th class="text-end">Total</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td>' . e((string)$r['pinv_no']) . '</td><td>' . e((string)$r['inward_date']) . '</td>';
        echo '<td>' . e((string)($r['supplier_name'] ?? '—')) . '</td><td>' . e((string)$r['material_name']) . '</td>';
        echo '<td class="text-end">₹' . e(number_format((float)$r['total_amount'], 2)) . '</td>';
        echo '<td>' . e((string)$r['payment_status']) . '</td></tr>';
    }
    echo '</tbody></table>';
    erp_doc_print_footer('Authorised signature', 'Generated ' . date('d M Y H:i'));
    exit;
}

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="purchase-history.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['PINV', 'Date', 'Supplier', 'Material', 'Qty', 'Rate', 'Total', 'Paid', 'Pending', 'Status']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['pinv_no'],
            $r['inward_date'],
            $r['supplier_name'] ?? '',
            $r['material_name'],
            $r['quantity'],
            $r['rate'],
            $r['total_amount'],
            $r['paid_amount'],
            $r['pending_amount'],
            $r['payment_status'],
        ]);
    }
    fclose($out);
    exit;
}

$viewId = (int)($_GET['view'] ?? 0);
$viewRow = $viewId > 0 ? inv_purchase_get($pdo, $viewId) : null;
$viewPayments = $viewRow ? inv_purchase_list_payments($pdo, $viewId) : [];
$suppliers = inv_list_suppliers($pdo);
$materials = inv_list_materials_master($pdo);
$baseQs = 'page=inventory/purchase-history&from=' . rawurlencode($from) . '&to=' . rawurlencode($to)
    . '&supplier_id=' . $supplierId . '&material_id=' . $materialId
    . '&payment_status=' . rawurlencode($paymentStatus) . '&q=' . rawurlencode($q);
$today = date('Y-m-d');
?>

<div class="inv-page">
<?php inv_page_header(
    'Purchase History',
    'Track purchases, add payments, and export filtered results.',
    '<a class="btn btn-primary btn-sm" href="' . e(route_url('inventory/add-stock')) . '"><i class="bi bi-plus-lg me-1"></i>Inward</a>'
); ?>

    <form method="get" class="inv-filter-bar">
        <input type="hidden" name="page" value="inventory/purchase-history">
        <div class="inv-filter-bar__row">
            <div class="col-auto"><label class="form-label">From</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e($from) ?>"></div>
            <div class="col-auto"><label class="form-label">To</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e($to) ?>"></div>
            <div class="col-md-2"><label class="form-label">Supplier</label>
                <select class="form-select form-select-sm erp-select-search" name="supplier_id" data-placeholder="All">
                    <option value="0">All</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>><?= e((string)$s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Material</label>
                <select class="form-select form-select-sm erp-select-search" name="material_id" data-placeholder="All">
                    <option value="0">All</option>
                    <?php foreach ($materials as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= $materialId === (int)$m['id'] ? 'selected' : '' ?>><?= e((string)$m['material_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><label class="form-label">Payment</label>
                <select class="form-select form-select-sm" name="payment_status">
                    <option value="">All</option>
                    <?php foreach (['Paid', 'Partial', 'Unpaid'] as $ps): ?>
                        <option value="<?= e($ps) ?>" <?= $paymentStatus === $ps ? 'selected' : '' ?>><?= e($ps) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Search</label><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="PINV, supplier…"></div>
            <div class="col-auto"><button class="btn btn-primary btn-sm">Apply filters</button></div>
            <?= inv_filter_exports($baseQs) ?>
        </div>
    </form>

    <?php if ($viewRow): ?>
        <?php $pm = inv_purchase_payment_meta((string)$viewRow['payment_status']); ?>
        <section class="inv-card mb-3">
            <div class="inv-card__head d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="inv-card__title mb-0"><?= e((string)$viewRow['pinv_no']) ?></h2>
                <span class="badge inv-pay--<?= e($pm['badge']) ?>"><?= e($pm['label']) ?></span>
            </div>
            <div class="inv-card__body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="row g-2 small">
                            <div class="col-md-4"><strong>Date</strong><br><?= e((string)$viewRow['inward_date']) ?></div>
                            <div class="col-md-4"><strong>Supplier</strong><br><?= e((string)($viewRow['supplier_name'] ?? '—')) ?></div>
                            <div class="col-md-4"><strong>Material</strong><br><?= e((string)$viewRow['material_name']) ?> · <?= e(number_format((float)$viewRow['quantity'], 2)) ?> <?= e((string)$viewRow['unit']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="inv-pay-balance__dl inv-pay-balance__dl--inline">
                            <div><span>Total</span><strong>₹<?= e(number_format((float)$viewRow['total_amount'], 2)) ?></strong></div>
                            <div><span>Paid</span><strong class="text-success">₹<?= e(number_format((float)$viewRow['paid_amount'], 2)) ?></strong></div>
                            <div><span>Pending</span><strong class="text-danger">₹<?= e(number_format((float)$viewRow['pending_amount'], 2)) ?></strong></div>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e(inv_purchase_print_url((int)$viewRow['id'])) ?>" target="_blank">Download PDF</a>
                    <a class="btn btn-outline-primary btn-sm" href="<?= e(route_url('inventory/purchase-edit', ['id' => (int)$viewRow['id']])) ?>">Edit</a>
                    <?php if ((float)$viewRow['pending_amount'] > inv_purchase_tolerance()): ?>
                        <button type="button" class="btn btn-success btn-sm inv-open-pay-modal"
                            data-id="<?= (int)$viewRow['id'] ?>"
                            data-pinv="<?= e((string)$viewRow['pinv_no']) ?>"
                            data-supplier="<?= e((string)($viewRow['supplier_name'] ?? '')) ?>"
                            data-total="<?= e((string)$viewRow['total_amount']) ?>"
                            data-paid="<?= e((string)$viewRow['paid_amount']) ?>"
                            data-pending="<?= e((string)$viewRow['pending_amount']) ?>">Add payment</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm inv-open-pay-history"
                        data-id="<?= (int)$viewRow['id'] ?>"
                        data-pinv="<?= e((string)$viewRow['pinv_no']) ?>">Payment history</button>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="inv-card">
        <?php inv_table_scroll_open('min(56vh, 520px)'); ?>
            <table class="table table-sm inv-table mb-0">
                <thead>
                    <tr>
                        <th>PINV</th><th>Date</th><th>Supplier</th><th>Material</th>
                        <th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Pending</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $pm = inv_purchase_payment_meta((string)($r['payment_status'] ?? 'Unpaid'));
                    $pending = (float)$r['pending_amount'];
                    $canPay = $pending > inv_purchase_tolerance();
                    $viewUrl = 'index.php?' . $baseQs . '&view=' . (int)$r['id'];
                    $actions = [
                        ['label' => 'View', 'url' => $viewUrl, 'icon' => 'bi-eye', 'tone' => 'view'],
                        ['label' => 'Print / PDF', 'url' => inv_purchase_print_url((int)$r['id'], true), 'icon' => 'bi-file-pdf', 'tone' => 'pdf', 'attrs' => 'target="_blank" rel="noopener"'],
                        ['label' => 'Edit', 'url' => route_url('inventory/purchase-edit', ['id' => (int)$r['id']]), 'icon' => 'bi-pencil', 'tone' => 'edit'],
                    ];
                    if ($canPay) {
                        $actions[] = [
                            'label' => 'Add payment',
                            'url' => '#inv-pay',
                            'icon' => 'bi-cash-coin',
                            'tone' => 'payment',
                            'attrs' => 'data-pay-open="1" data-id="' . (int)$r['id'] . '" data-pinv="' . e((string)$r['pinv_no']) . '" data-supplier="' . e((string)($r['supplier_name'] ?? '')) . '" data-total="' . e((string)$r['total_amount']) . '" data-paid="' . e((string)$r['paid_amount']) . '" data-pending="' . e((string)$pending) . '"',
                        ];
                    } else {
                        $actions[] = ['label' => 'Add payment', 'icon' => 'bi-cash-coin', 'tone' => 'payment', 'disabled' => true];
                    }
                    $actions[] = [
                        'label' => 'Payment history',
                        'url' => '#inv-pay-history',
                        'icon' => 'bi-clock-history',
                        'tone' => 'default',
                        'attrs' => 'data-pay-history="1" data-id="' . (int)$r['id'] . '" data-pinv="' . e((string)$r['pinv_no']) . '"',
                    ];
                    ?>
                    <tr>
                        <td><?= e((string)$r['pinv_no']) ?></td>
                        <td><?= e((string)$r['inward_date']) ?></td>
                        <td><?= e((string)($r['supplier_name'] ?? '—')) ?></td>
                        <td><?= e((string)$r['material_name']) ?></td>
                        <td class="text-end">₹<?= e(number_format((float)$r['total_amount'], 2)) ?></td>
                        <td class="text-end">₹<?= e(number_format((float)$r['paid_amount'], 2)) ?></td>
                        <td class="text-end">₹<?= e(number_format($pending, 2)) ?></td>
                        <td><span class="badge inv-pay--<?= e($pm['badge']) ?>"><?= e($pm['label']) ?></span></td>
                        <td><?= crm_action_icons($actions) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="9" class="text-center inv-muted py-4">No purchases in this period.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        <?php inv_table_scroll_close(); ?>
    </section>
</div>

<!-- Add payment modal -->
<div class="modal fade" id="invPayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="inv-pay-form">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="add_payment">
                <input type="hidden" name="inward_id" id="inv-pay-inward-id" value="">
                <input type="hidden" name="return_from" value="<?= e($from) ?>">
                <input type="hidden" name="return_to" value="<?= e($to) ?>">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Add payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="inv-pay-balance mb-3">
                        <div class="inv-pay-balance__row"><span>PINV</span><strong id="inv-pay-pinv">—</strong></div>
                        <div class="inv-pay-balance__row"><span>Supplier</span><strong id="inv-pay-supplier">—</strong></div>
                        <div class="inv-pay-balance__row"><span>Invoice total</span><strong id="inv-pay-total">₹0.00</strong></div>
                        <div class="inv-pay-balance__row"><span>Already paid</span><strong class="text-success" id="inv-pay-paid">₹0.00</strong></div>
                        <div class="inv-pay-balance__row inv-pay-balance__row--pending"><span>Remaining</span><strong class="text-danger" id="inv-pay-pending">₹0.00</strong></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label">New payment (₹)</label><input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="amount" id="inv-pay-amount" required></div>
                        <div class="col-6"><label class="form-label">Payment date</label><input type="date" class="form-control form-control-sm" name="payment_date" value="<?= e($today) ?>" required></div>
                        <div class="col-6"><label class="form-label">Payment mode</label>
                            <select class="form-select form-select-sm" name="payment_mode" required>
                                <option value="Cash">Cash</option>
                                <option value="UPI">UPI</option>
                                <option value="Bank">Bank</option>
                                <option value="Credit">Credit</option>
                            </select>
                        </div>
                        <div class="col-6"><label class="form-label">Transaction reference</label><input class="form-control form-control-sm" name="payment_ref" maxlength="80"></div>
                        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control form-control-sm" name="notes" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">Record payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment history modal -->
<div class="modal fade" id="invPayHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="inv-pay-history-title">Payment history</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm inv-table mb-0">
                    <thead><tr><th>Date</th><th>PINV</th><th>Supplier</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th><th>User</th></tr></thead>
                    <tbody id="inv-pay-history-body"><tr><td colspan="7" class="text-center inv-muted p-3">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($openPay && $viewRow && (float)$viewRow['pending_amount'] > inv_purchase_tolerance()): ?>
<script>document.addEventListener('DOMContentLoaded', function () {
    const btn = document.querySelector('.inv-open-pay-modal[data-id="<?= (int)$viewRow['id'] ?>"]');
    if (btn) btn.click();
});</script>
<?php endif; ?>
<?php if ($openPrint && $viewRow): ?>
<script>document.addEventListener('DOMContentLoaded', function () {
    window.open(<?= json_encode(inv_purchase_print_url((int)$viewRow['id'], true), JSON_THROW_ON_ERROR) ?>, '_blank', 'noopener');
    try {
        const u = new URL(window.location.href);
        u.searchParams.delete('print');
        window.history.replaceState({}, '', u.toString());
    } catch (e) {}
});</script>
<?php endif; ?>
<script src="assets/js/inventory-purchase-payments.js" defer></script>
