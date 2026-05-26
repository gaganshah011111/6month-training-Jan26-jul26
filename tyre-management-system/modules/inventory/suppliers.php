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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $editId = (int)($_POST['id'] ?? 0);
        inv_save_supplier($pdo, $_POST, $editId > 0 ? $editId : null);
        set_flash('success', $editId > 0 ? 'Supplier updated.' : 'Supplier added.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('inventory/suppliers');
}

$rows = inv_list_suppliers($pdo);
$viewId = (int)($_GET['view'] ?? 0);
$viewInward = $viewId > 0 ? inv_supplier_recent_inward($pdo, $viewId) : [];
$edit = null;
if (isset($_GET['edit'])) {
    foreach ($rows as $r) {
        if ((int)$r['id'] === (int)$_GET['edit']) {
            $edit = $r;
            break;
        }
    }
}
?>

<div class="inv-page">
<?php inv_page_header('Suppliers', 'Vendor master for purchase inward and supplier ledger.'); ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="inv-card">
                <div class="inv-card__head"><h2 class="inv-card__title"><?= $edit ? 'Edit supplier' : 'Add supplier' ?></h2></div>
                <div class="inv-card__body">
                    <form method="post" class="vstack gap-2">
                        <?= csrf_input() ?>
                        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
                        <div><label class="form-label small">Supplier name</label><input class="form-control form-control-sm" name="name" value="<?= e((string)($edit['name'] ?? '')) ?>" required></div>
                        <div><label class="form-label small">Contact person</label><input class="form-control form-control-sm" name="contact_person" value="<?= e((string)($edit['contact_person'] ?? '')) ?>"></div>
                        <div><label class="form-label small">Phone</label><input class="form-control form-control-sm" name="phone" value="<?= e((string)($edit['phone'] ?? '')) ?>"></div>
                        <div><label class="inv-label">GST number</label><input class="form-control form-control-sm" name="gst_number" value="<?= e((string)($edit['gst_number'] ?? '')) ?>" maxlength="40"><?= inv_hint('GSTIN for tax invoices (optional)') ?></div>
                        <div><label class="form-label small">Email</label><input type="email" class="form-control form-control-sm" name="email" value="<?= e((string)($edit['email'] ?? '')) ?>"></div>
                        <div><label class="form-label small">Supplied materials</label><input class="form-control form-control-sm" name="materials_supplied" placeholder="Rubber, Carbon Black…" value="<?= e((string)($edit['materials_supplied'] ?? '')) ?>"></div>
                        <div><label class="form-label small">Address</label><input class="form-control form-control-sm" name="address" value="<?= e((string)($edit['address'] ?? '')) ?>"></div>
                        <div><label class="form-label small">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="Active" <?= ($edit['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= ($edit['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <button class="btn btn-primary btn-sm"><?= $edit ? 'Update' : 'Save supplier' ?></button>
                        <?php if ($edit): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('inventory/suppliers')) ?>">Cancel</a><?php endif; ?>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <section class="inv-card mb-3">
                <div class="inv-card__head"><h2 class="inv-card__title">Suppliers list</h2></div>
                <?php inv_table_scroll_open('min(52vh, 480px)'); ?>
                    <table class="table table-sm inv-table mb-0">
                        <thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Materials</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e($r['name']) ?></td>
                                <td><?= e((string)($r['contact_person'] ?? '—')) ?></td>
                                <td><?= e((string)($r['phone'] ?? '—')) ?></td>
                                <td><?= e((string)($r['materials_supplied'] ?? '—')) ?></td>
                                <td><?= e((string)($r['status'] ?? 'Active')) ?></td>
                                <td>
                                    <a class="small" href="<?= e(route_url('inventory/supplier-ledger', ['supplier_id' => (int)$r['id']])) ?>">Ledger</a>
                                    · <a class="small" href="index.php?page=<?= rawurlencode('inventory/suppliers') ?>&edit=<?= (int)$r['id'] ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php inv_table_scroll_close(); ?>
            </section>
            <?php if ($viewId > 0): ?>
            <section class="inv-card">
                <div class="inv-card__head"><h2 class="inv-card__title">Recent inward from supplier</h2></div>
                <?php inv_table_scroll_open('min(240px, 32vh)'); ?>
                    <table class="table table-sm inv-table mb-0">
                        <thead><tr><th>Date</th><th>Invoice</th><th>Material</th><th class="text-end">Qty</th></tr></thead>
                        <tbody>
                        <?php foreach ($viewInward as $i): ?>
                            <tr>
                                <td><?= e((string)$i['inward_date']) ?></td>
                                <td><?= e((string)($i['invoice_no'] ?? '—')) ?></td>
                                <td><?= e((string)$i['material_name']) ?></td>
                                <td class="text-end"><?= e(number_format((float)$i['quantity'], 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($viewInward === []): ?>
                            <tr><td colspan="4" class="text-muted text-center">No inward entries for this supplier.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                <?php inv_table_scroll_close(); ?>
            </section>
            <?php endif; ?>
        </div>
    </div>
</div>
