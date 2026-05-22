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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $editId = (int)($_POST['id'] ?? 0);
        inv_save_material($pdo, $_POST, $editId > 0 ? $editId : null);
        set_flash('success', $editId > 0 ? 'Material updated.' : 'Material created. Use Add Stock to receive quantity and set alert levels.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('inventory/materials');
}

$suppliers = inv_list_suppliers($pdo);
$search = trim((string)($_GET['q'] ?? ''));
$filter = (string)($_GET['filter'] ?? 'all');
$rows = inv_search_materials_master($pdo, $search, $filter);
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
    <header class="inv-page__head">
        <div>
            <h1 class="inv-page__title">Materials</h1>
            <p class="inv-page__sub">Material identity only — code, name, unit, supplier. Stock limits are set when you add stock.</p>
        </div>
        <nav class="inv-page__links">
            <a href="<?= e(route_url('inventory/add-stock')) ?>">Add Stock</a>
            <a href="<?= e(route_url('inventory/dashboard')) ?>">Dashboard</a>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-3">
            <section class="inv-card">
                <div class="inv-card__head"><h2 class="inv-card__title"><?= $edit ? 'Edit material' : 'New material' ?></h2></div>
                <div class="inv-card__body">
                    <form method="post" class="vstack gap-2">
                        <?= csrf_input() ?>
                        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
                        <div><label class="form-label small">Material code</label><input class="form-control form-control-sm" name="material_code" value="<?= e((string)($edit['material_code'] ?? '')) ?>" required></div>
                        <div><label class="form-label small">Material name</label><input class="form-control form-control-sm" name="material_name" value="<?= e((string)($edit['material_name'] ?? '')) ?>" required></div>
                        <div><label class="form-label small">Unit</label>
                            <select class="form-select form-select-sm" name="unit" required>
                                <?php foreach (['kg', 'ltr', 'piece'] as $u): ?>
                                    <option value="<?= e($u) ?>" <?= ($edit['unit'] ?? 'kg') === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="form-label small">Supplier</label>
                            <select class="form-select form-select-sm" name="supplier_id">
                                <option value="">—</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>" <?= (int)($edit['supplier_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="form-label small">Storage location <span class="text-muted">(optional)</span></label><input class="form-control form-control-sm" name="storage_location" value="<?= e((string)($edit['storage_location'] ?? '')) ?>" placeholder="e.g. Store-A1"></div>
                        <div><label class="form-label small">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="Active" <?= ($edit['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= ($edit['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <button class="btn btn-primary btn-sm"><?= $edit ? 'Update' : 'Save material' ?></button>
                        <?php if ($edit): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(route_url('inventory/materials')) ?>">Cancel</a><?php endif; ?>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-9">
            <section class="inv-card">
                <div class="inv-card__head d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="inv-card__title mb-0">Material master list</h2>
                    <form method="get" class="d-flex flex-wrap gap-1 align-items-center">
                        <input type="hidden" name="page" value="inventory/materials">
                        <input type="search" class="form-control form-control-sm" name="q" value="<?= e($search) ?>" placeholder="Search name, code, supplier" style="width:180px">
                        <select class="form-select form-select-sm" name="filter" style="width:120px">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Low stock</option>
                            <option value="out" <?= $filter === 'out' ? 'selected' : '' ?>>Out of stock</option>
                            <option value="recent" <?= $filter === 'recent' ? 'selected' : '' ?>>Recently used</option>
                        </select>
                        <button class="btn btn-sm btn-outline-secondary">Filter</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm inv-table mb-0">
                        <thead>
                            <tr>
                                <th>Code</th><th>Name</th><th>Unit</th><th>Supplier</th><th>Storage</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e((string)$r['material_code']) ?></td>
                                <td><?= e((string)$r['material_name']) ?></td>
                                <td><?= e((string)$r['unit']) ?></td>
                                <td><?= e((string)($r['supplier_name'] ?? '—')) ?></td>
                                <td><?= e((string)($r['storage_location'] ?? '—')) ?></td>
                                <td><?= e((string)($r['status'] ?? 'Active')) ?></td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-link btn-sm p-0 inv-history-btn" data-id="<?= (int)$r['id'] ?>" data-name="<?= e((string)$r['material_name']) ?>">History</button>
                                    · <a class="small" href="index.php?page=<?= rawurlencode('inventory/materials') ?>&edit=<?= (int)$r['id'] ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="7" class="text-center text-muted">No materials yet. Create one, then use Add Stock.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal fade" id="invHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="invHistoryTitle">Transaction history</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm mb-0 inv-table">
                    <thead><tr><th>Date</th><th>Type</th><th class="text-end">Qty</th><th>Dept</th><th>Operator</th><th>Remarks</th></tr></thead>
                    <tbody id="invHistoryBody"><tr><td colspan="6" class="text-muted p-3">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.inv-history-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const id = this.dataset.id;
        const name = this.dataset.name;
        document.getElementById('invHistoryTitle').textContent = 'History — ' + name;
        const body = document.getElementById('invHistoryBody');
        body.innerHTML = '<tr><td colspan="6" class="text-muted p-3">Loading…</td></tr>';
        const modal = new bootstrap.Modal(document.getElementById('invHistoryModal'));
        modal.show();
        fetch('index.php?page=api/material-history&material_id=' + encodeURIComponent(id))
            .then(r => r.json())
            .then(function (data) {
                if (data.error) { body.innerHTML = '<tr><td colspan="6" class="text-danger p-3">' + data.error + '</td></tr>'; return; }
                if (!data.history || !data.history.length) {
                    body.innerHTML = '<tr><td colspan="6" class="text-muted p-3">No transactions yet.</td></tr>';
                    return;
                }
                body.innerHTML = data.history.map(function (h) {
                    const sign = h.quantity >= 0 ? '+' : '';
                    return '<tr><td>' + h.date + '</td><td>' + h.type + '</td><td class="text-end">' + sign + h.quantity + '</td><td>' + (h.department || '—') + '</td><td>' + (h.operator || '—') + '</td><td>' + (h.remarks || h.reason || '—') + '</td></tr>';
                }).join('');
            })
            .catch(function () { body.innerHTML = '<tr><td colspan="6" class="text-danger p-3">Failed to load history.</td></tr>'; });
    });
});
</script>
