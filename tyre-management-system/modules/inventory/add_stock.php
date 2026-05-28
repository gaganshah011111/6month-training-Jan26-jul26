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
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $id = inv_purchase_save($pdo, $_POST);
        $row = inv_purchase_get($pdo, $id);
        $pinv = (string)($row['pinv_no'] ?? 'PINV-' . $id);
        set_flash('success', $pinv . ' saved. Stock updated.');
        header('Location: ' . route_url('inventory/purchase-history', [
            'view' => $id,
            'print' => '1',
        ]));
        exit;
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('inventory/add-stock');
}

$materials = array_filter(inv_list_materials_master($pdo), static fn($m) => ($m['status'] ?? 'Active') === 'Active');
$suppliers = array_filter(inv_list_suppliers($pdo), static fn($s) => ($s['status'] ?? 'Active') === 'Active');
$recent = inv_purchase_list($pdo, ['limit' => INV_RECENT_PURCHASES_INWARD]);
$nextPinv = inv_purchase_next_pinv($pdo, $today);

$supplierJson = json_encode(array_map(static function ($s) {
    return [
        'id' => (int)$s['id'],
        'name' => (string)$s['name'],
        'gst_number' => (string)($s['gst_number'] ?? ''),
        'contact_person' => (string)($s['contact_person'] ?? ''),
        'phone' => (string)($s['phone'] ?? ''),
    ];
}, $suppliers), JSON_THROW_ON_ERROR);
?>

<div class="inv-page inv-purchase-page">
<?php inv_page_header(
    'Purchase Inward',
    'Record supplier purchase — stock, ledger, and payments update automatically.',
    '<a class="btn btn-outline-secondary btn-sm" href="' . e(route_url('inventory/purchase-history')) . '">History</a>'
); ?>

    <form method="post" id="inv-purchase-form" class="inv-purchase-form">
        <?= csrf_input() ?>

        <section class="inv-card inv-purchase-section">
            <div class="inv-card__head"><h2 class="inv-card__title">1. Supplier details</h2></div>
            <div class="inv-card__body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Supplier</label>
                        <select class="form-select form-select-sm erp-select-search" name="supplier_id" id="inv-supplier-select" required data-placeholder="Select supplier…">
                            <option value="">Select supplier…</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"><?= e((string)$s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">GST number</label><input class="form-control form-control-sm" name="supplier_gst_display" id="inv-supplier-gst" readonly placeholder="—"></div>
                    <div class="col-md-4"><label class="form-label">Contact person</label><input class="form-control form-control-sm" id="inv-supplier-contact" readonly placeholder="—"></div>
                    <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control form-control-sm" id="inv-supplier-phone" readonly placeholder="—"></div>
                    <div class="col-md-4">
                        <label class="inv-label">Supplier invoice no.</label>
                        <input class="form-control form-control-sm" name="supplier_invoice_no" maxlength="80">
                        <?= inv_hint("Supplier's original bill number") ?>
                    </div>
                    <div class="col-md-4">
                        <label class="inv-label">Challan number</label>
                        <input class="form-control form-control-sm" name="challan_no" maxlength="80">
                        <?= inv_hint('Delivery / transport slip number') ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="inv-card inv-purchase-section">
            <div class="inv-card__head"><h2 class="inv-card__title">2. Material details</h2></div>
            <div class="inv-card__body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Material</label>
                        <select class="form-select form-select-sm erp-select-search" name="material_id" id="inv-material-select" required data-placeholder="Search material…">
                            <option value="">Search material…</option>
                            <?php foreach ($materials as $m): ?>
                                <?php
                                $first = inv_material_inward_count($pdo, (int)$m['id']) === 0;
                                $needsLimits = $first || inv_material_limits_unset($m);
                                ?>
                                <option value="<?= (int)$m['id'] ?>"
                                    data-unit="<?= e((string)$m['unit']) ?>"
                                    data-first="<?= $first ? '1' : '0' ?>"
                                    data-needs-limits="<?= $needsLimits ? '1' : '0' ?>"
                                    data-min="<?= e((string)($m['reorder_level'] ?? '0')) ?>"
                                    data-max="<?= e((string)($m['max_stock_level'] ?? '0')) ?>"
                                    data-loc="<?= e((string)($m['storage_location'] ?? '')) ?>">
                                    <?= e($m['material_name']) ?> (<?= e($m['unit']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Quantity</label><input type="number" step="0.01" min="0.01" class="form-control form-control-sm inv-calc" name="quantity" id="inv-qty" required></div>
                    <div class="col-md-2"><label class="form-label">Unit</label><input class="form-control form-control-sm" id="inv-unit" readonly></div>
                    <div class="col-md-2">
                        <label class="inv-label">Batch number</label>
                        <input class="form-control form-control-sm" name="batch_no" maxlength="80">
                        <?= inv_hint('Optional lot / shipment ID from supplier') ?>
                    </div>
                    <div class="col-md-2"><label class="inv-label">Expiry date</label><input type="date" class="form-control form-control-sm" name="expiry_date"></div>
                    <div class="col-md-4">
                        <label class="inv-label">Warehouse / storage</label>
                        <input class="form-control form-control-sm" name="warehouse_location" id="inv-warehouse" maxlength="120">
                        <?= inv_hint('Where material is stored in your warehouse') ?>
                    </div>
                    <div class="col-md-4"><label class="inv-label">Purchase date</label><input type="date" class="form-control form-control-sm" name="inward_date" value="<?= e($today) ?>" required></div>
                    <div class="col-md-4">
                        <label class="inv-label">PINV (auto)</label>
                        <input class="form-control form-control-sm bg-light" value="<?= e($nextPinv) ?>" readonly disabled>
                        <?= inv_hint('Auto-generated internal purchase reference') ?>
                    </div>
                </div>

                <div id="inv-limits-panel" class="inv-limits-panel d-none mt-2">
                    <p class="inv-limits-panel__title" id="inv-limits-title">Stock alert settings</p>
                    <div id="inv-limits-optional" class="form-check mb-2 d-none">
                        <input class="form-check-input" type="checkbox" name="update_limits" value="1" id="inv-update-limits">
                        <label class="form-check-label" for="inv-update-limits">Update stock alert limits</label>
                    </div>
                    <div class="row g-2" id="inv-limits-fields">
                        <div class="col-md-3"><label class="form-label">Minimum stock alert</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="minimum_stock" id="inv-min-stock"></div>
                        <div class="col-md-3"><label class="form-label">Maximum storage</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="maximum_stock" id="inv-max-stock"></div>
                    </div>
                </div>
            </div>
        </section>

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="inv-card inv-purchase-section">
                    <div class="inv-card__head"><h2 class="inv-card__title">3. Purchase pricing</h2></div>
                    <div class="inv-card__body">
                        <div class="row g-2">
                            <div class="col-md-3"><label class="form-label">Rate per unit (₹)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm inv-calc" name="purchase_rate" id="inv-rate" value="0"></div>
                            <div class="col-md-3"><label class="form-label">GST %</label><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm inv-calc" name="gst_percent" id="inv-gst-pct" value="0"></div>
                            <div class="col-md-3"><label class="form-label">Transport</label><input type="number" step="0.01" min="0" class="form-control form-control-sm inv-calc" name="transport_charges" value="0"></div>
                            <div class="col-md-3"><label class="form-label">Loading / unloading</label><input type="number" step="0.01" min="0" class="form-control form-control-sm inv-calc" name="loading_charges" value="0"></div>
                            <div class="col-md-3"><label class="form-label">Other charges</label><input type="number" step="0.01" min="0" class="form-control form-control-sm inv-calc" name="other_charges" value="0"></div>
                            <div class="col-md-3"><label class="form-label">Discount</label><input type="number" step="0.01" min="0" class="form-control form-control-sm inv-calc" name="discount_amount" value="0"></div>
                        </div>
                    </div>
                </section>

                <section class="inv-card inv-purchase-section">
                    <div class="inv-card__head"><h2 class="inv-card__title">4. Payable terms (Accounts managed)</h2></div>
                    <div class="inv-card__body">
                        <div class="row g-2">
                            <input type="hidden" name="payment_status" value="Unpaid">
                            <input type="hidden" name="paid_amount" value="0">
                            <div class="col-md-3"><label class="form-label">Due date</label><input type="date" class="form-control form-control-sm" name="due_date"></div>
                            <div class="col-md-9"><label class="form-label">Notes</label><textarea class="form-control form-control-sm" name="notes" rows="2" placeholder="Payment is managed by Accounts in Payables module."></textarea></div>
                            <div class="col-12">
                                <div class="alert alert-info py-2 mb-0 small">
                                    Purchase Inward creates supplier payable automatically with <strong>Unpaid</strong> status. Record supplier payment from <strong>Accounts &amp; Finance &gt; Payables</strong>.
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="inv-card inv-purchase-totals">
                    <div class="inv-card__head"><h2 class="inv-card__title">Amount summary</h2></div>
                    <div class="inv-card__body">
                        <dl class="inv-totals-dl">
                            <div><dt>Subtotal</dt><dd id="sum-subtotal">₹0.00</dd></div>
                            <div><dt>GST</dt><dd id="sum-gst">₹0.00</dd></div>
                            <div><dt>Extra charges</dt><dd id="sum-extra">₹0.00</dd></div>
                            <div><dt>Discount</dt><dd id="sum-discount">₹0.00</dd></div>
                            <div class="inv-totals-dl__final"><dt>Final amount</dt><dd id="sum-total">₹0.00</dd></div>
                        </dl>
                        <button type="submit" class="btn btn-success w-100 mt-2">Save purchase inward</button>
                    </div>
                </section>
            </div>
        </div>
    </form>

    <section class="inv-card mt-3">
        <div class="inv-card__head">
            <div>
                <h2 class="inv-card__title mb-0">Recent purchases</h2>
                <span class="inv-card__note"><?= e(inv_recent_purchases_note(INV_RECENT_PURCHASES_INWARD)) ?></span>
            </div>
        </div>
        <?php inv_table_scroll_open('min(240px, 32vh)'); ?>
            <table class="table table-sm inv-table mb-0">
                <thead><tr><th>PINV</th><th>Date</th><th>Supplier</th><th>Material</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                    <?php $pm = inv_purchase_payment_meta((string)($r['payment_status'] ?? 'Unpaid')); ?>
                    <tr>
                        <td><?= e((string)$r['pinv_no']) ?></td>
                        <td><?= e((string)$r['inward_date']) ?></td>
                        <td><?= e((string)($r['supplier_name'] ?? '—')) ?></td>
                        <td><?= e((string)$r['material_name']) ?></td>
                        <td class="text-end">₹<?= e(number_format((float)$r['total_amount'], 2)) ?></td>
                        <td><span class="badge inv-pay--<?= e($pm['badge']) ?>"><?= e($pm['label']) ?></span></td>
                        <td><a class="btn btn-outline-secondary btn-sm" href="<?= e(inv_purchase_print_url((int)$r['id'], true)) ?>" target="_blank" rel="noopener">PDF</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recent === []): ?>
                    <tr><td colspan="7" class="text-center inv-muted">No purchases yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        <?php inv_table_scroll_close(); ?>
    </section>
</div>
<script>
window.INV_SUPPLIERS = <?= $supplierJson ?>;
</script>
<script src="assets/js/inventory-purchase.js" defer></script>
