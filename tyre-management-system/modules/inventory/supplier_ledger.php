<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_service.php';
require_once __DIR__ . '/../../includes/inv_ui.php';

if (!has_role(['Inventory Manager', 'Super Admin', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
inv_purchase_ensure_schema($pdo);

$q = trim((string)($_GET['q'] ?? ''));
$export = (string)($_GET['export'] ?? '');
$viewId = (int)($_GET['supplier_id'] ?? 0);
$rows = inv_supplier_ledger_list($pdo, $q);

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="supplier-ledger.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Supplier', 'Total purchased', 'Total paid', 'Pending', 'Last purchase']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['name'],
            $r['total_purchased'],
            $r['total_paid'],
            $r['pending_balance'],
            $r['last_purchase_date'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$detail = [];
if ($viewId > 0) {
    $detail = inv_purchase_list($pdo, ['supplier_id' => $viewId, 'limit' => 50]);
}
$baseQs = 'page=inventory/supplier-ledger&q=' . rawurlencode($q);
?>

<div class="inv-page">
<?php inv_page_header('Supplier Ledger', 'Total purchased, paid, and pending balance per supplier.'); ?>

    <form method="get" class="inv-filter-bar">
        <input type="hidden" name="page" value="inventory/supplier-ledger">
        <?php if ($viewId > 0): ?><input type="hidden" name="supplier_id" value="<?= $viewId ?>"><?php endif; ?>
        <div class="inv-filter-bar__row">
            <div><label class="inv-label">Search supplier</label><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Name, contact, phone…" style="max-width:220px"></div>
            <div class="col-auto align-self-end"><button class="btn btn-primary btn-sm">Search</button></div>
            <?= inv_filter_exports($baseQs, true, false, false) ?>
        </div>
    </form>

    <?php if ($viewId > 0 && $detail !== []): ?>
        <section class="inv-card mb-3">
            <div class="inv-card__head"><h2 class="inv-card__title">Purchase history — supplier #<?= $viewId ?></h2></div>
            <div class="inv-table-scroll" style="max-height:280px">
                <table class="table table-sm inv-table mb-0">
                    <thead><tr><th>PINV</th><th>Date</th><th>Material</th><th class="text-end">Total</th><th class="text-end">Pending</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($detail as $r): ?>
                        <?php $pm = inv_purchase_payment_meta((string)($r['payment_status'] ?? 'Unpaid')); ?>
                        <tr>
                            <td><?= e((string)$r['pinv_no']) ?></td>
                            <td><?= e((string)$r['inward_date']) ?></td>
                            <td><?= e((string)$r['material_name']) ?></td>
                            <td class="text-end">₹<?= e(number_format((float)$r['total_amount'], 2)) ?></td>
                            <td class="text-end">₹<?= e(number_format((float)$r['pending_amount'], 2)) ?></td>
                            <td><span class="badge inv-pay--<?= e($pm['badge']) ?>"><?= e($pm['label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="inv-card__body pt-0"><a href="index.php?page=inventory/supplier-ledger&q=<?= rawurlencode($q) ?>">← Back to ledger</a></div>
        </section>
    <?php endif; ?>

    <section class="inv-card">
        <?php inv_table_scroll_open('min(52vh, 480px)'); ?>
            <table class="table table-sm inv-table mb-0">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th class="text-end">Total purchased</th>
                        <th class="text-end">Total paid</th>
                        <th class="text-end">Pending balance</th>
                        <th>Last purchase</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e((string)$r['name']) ?></strong>
                            <?php if (!empty($r['contact_person'])): ?>
                                <br><span class="inv-muted small"><?= e((string)$r['contact_person']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">₹<?= e(number_format((float)$r['total_purchased'], 2)) ?></td>
                        <td class="text-end">₹<?= e(number_format((float)$r['total_paid'], 2)) ?></td>
                        <td class="text-end fw-semibold <?= (float)$r['pending_balance'] > 0.02 ? 'text-danger' : '' ?>">₹<?= e(number_format((float)$r['pending_balance'], 2)) ?></td>
                        <td><?= e((string)($r['last_purchase_date'] ?? '—')) ?></td>
                        <td><a class="btn btn-outline-primary btn-sm" href="index.php?<?= e($baseQs) ?>&supplier_id=<?= (int)$r['id'] ?>">Details</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="6" class="text-center inv-muted py-4">No suppliers found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        <?php inv_table_scroll_close(); ?>
    </section>
</div>
