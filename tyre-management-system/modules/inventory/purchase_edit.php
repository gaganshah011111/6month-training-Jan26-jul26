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
$id = (int)($_GET['id'] ?? 0);
$row = $id > 0 ? inv_purchase_get($pdo, $id) : null;
if (!$row) {
    echo '<div class="alert alert-danger">Purchase entry not found. <a href="' . e(route_url('inventory/purchase-history')) . '">Back to purchase history</a></div>';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        inv_purchase_update_meta($pdo, $id, $_POST);
        set_flash('success', 'Purchase details updated.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    header('Location: ' . route_url('inventory/purchase-edit', ['id' => $id]));
    exit;
}

$pm = inv_purchase_payment_meta((string)$row['payment_status']);
$payments = inv_purchase_list_payments($pdo, $id);
?>

<div class="inv-page">
<?php inv_page_header('Edit Purchase · ' . (string)$row['pinv_no'], 'Update reference details. Stock and pricing cannot be changed here.'); ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="inv-card inv-pay-balance">
                <div class="inv-card__head"><h2 class="inv-card__title">Payment balance</h2></div>
                <div class="inv-card__body">
                    <dl class="inv-pay-balance__dl">
                        <div><dt>Invoice total</dt><dd>₹<?= e(number_format((float)$row['total_amount'], 2)) ?></dd></div>
                        <div><dt>Paid</dt><dd class="text-success">₹<?= e(number_format((float)$row['paid_amount'], 2)) ?></dd></div>
                        <div><dt>Pending</dt><dd class="text-danger">₹<?= e(number_format((float)$row['pending_amount'], 2)) ?></dd></div>
                    </dl>
                    <span class="badge inv-pay--<?= e($pm['badge']) ?>"><?= e($pm['label']) ?></span>
                    <?php if ((float)$row['pending_amount'] > inv_purchase_tolerance()): ?>
                        <a class="btn btn-success btn-sm w-100 mt-2" href="<?= e(route_url('inventory/purchase-history', ['view' => $id, 'pay' => 1])) ?>">Add payment</a>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <section class="inv-card">
                <div class="inv-card__head"><h2 class="inv-card__title">Reference details</h2></div>
                <div class="inv-card__body">
                    <form method="post" class="row g-2">
                        <?= csrf_input() ?>
                        <div class="col-md-6"><label class="inv-label">Supplier invoice no.</label><input class="form-control form-control-sm" name="supplier_invoice_no" value="<?= e((string)($row['invoice_no'] ?? '')) ?>"><?= inv_hint("Supplier's original bill number") ?></div>
                        <div class="col-md-6"><label class="inv-label">Challan number</label><input class="form-control form-control-sm" name="challan_no" value="<?= e((string)($row['challan_no'] ?? '')) ?>"><?= inv_hint('Delivery / transport slip number') ?></div>
                        <div class="col-md-6"><label class="form-label">Due date</label><input type="date" class="form-control form-control-sm" name="due_date" value="<?= e((string)($row['due_date'] ?? '')) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Warehouse</label><input class="form-control form-control-sm" name="warehouse_location" value="<?= e((string)($row['warehouse_location'] ?? '')) ?>"></div>
                        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control form-control-sm" name="notes" rows="2"><?= e((string)($row['remarks'] ?? '')) ?></textarea></div>
                        <div class="col-12"><button class="btn btn-primary btn-sm">Save changes</button></div>
                    </form>
                </div>
            </section>
            <section class="inv-card mt-3">
                <div class="inv-card__head"><h2 class="inv-card__title">Payment history</h2></div>
                <?php inv_table_scroll_open('min(240px, 32vh)'); ?>
                    <table class="table table-sm inv-table mb-0">
                        <thead><tr><th>Date</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th><th>User</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= e((string)$p['payment_date']) ?></td>
                                <td class="text-end">₹<?= e(number_format((float)$p['amount'], 2)) ?></td>
                                <td><?= e((string)($p['payment_mode'] ?? '—')) ?></td>
                                <td><?= e((string)($p['payment_ref'] ?? '—')) ?></td>
                                <td><?= e((string)($p['recorded_by'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($payments === []): ?>
                            <tr><td colspan="5" class="text-center inv-muted py-3">No payments recorded yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                <?php inv_table_scroll_close(); ?>
            </section>
        </div>
    </div>
</div>
