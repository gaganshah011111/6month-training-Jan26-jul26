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
        inv_save_material($pdo, $_POST, $editId > 0 ? $editId : null);
        set_flash('success', $editId > 0 ? 'Material updated.' : 'Material created. Record purchases via Purchase Inward.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('inventory/materials');
}

$search = trim((string)($_GET['q'] ?? ''));
$filter = (string)($_GET['filter'] ?? 'all');
$export = (string)($_GET['export'] ?? '');
$rows = inv_search_materials_master($pdo, $search, $filter);
$baseQs = 'page=inventory/materials&q=' . rawurlencode($search) . '&filter=' . rawurlencode($filter);

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="materials.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Code', 'Material', 'Unit', 'Storage', 'Min stock', 'Current stock', 'Status']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['material_code'],
            $r['material_name'],
            $r['unit'],
            $r['storage_location'] ?? '',
            $r['reorder_level'],
            $r['stock_qty'],
            $r['status'] ?? 'Active',
        ]);
    }
    fclose($out);
    exit;
}
$edit = null;
if (isset($_GET['edit'])) {
    $edit = inv_get_material_row($pdo, (int)$_GET['edit']);
}
?>

<div class="inv-page">
<?php inv_page_header(
    'Materials',
    'Material master — identity and stock alert levels. Purchases use Purchase Inward.',
    '<a class="btn btn-primary btn-sm" href="' . e(route_url('inventory/add-stock')) . '">Purchase Inward</a>'
); ?>

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
                        <div><label class="form-label small">Storage location</label><input class="form-control form-control-sm" name="storage_location" value="<?= e((string)($edit['storage_location'] ?? '')) ?>" placeholder="e.g. Store-A1"></div>
                        <div><label class="form-label small">Minimum stock level</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="minimum_stock" value="<?= e((string)($edit['reorder_level'] ?? '0')) ?>"></div>
                        <div><label class="form-label small">Maximum stock level</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="maximum_stock" value="<?= e((string)($edit['max_stock_level'] ?? '0')) ?>"></div>
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
                <div class="inv-card__head inv-table-panel__head">
                    <h2 class="inv-card__title mb-0">Material list</h2>
                    <form method="get" class="d-flex flex-wrap gap-1 align-items-end">
                        <input type="hidden" name="page" value="inventory/materials">
                        <div><label class="inv-label d-block">Search</label><input type="search" class="form-control form-control-sm" name="q" value="<?= e($search) ?>" placeholder="Code, name…" style="width:160px"></div>
                        <div><label class="inv-label d-block">Filter</label>
                        <select class="form-select form-select-sm" name="filter" style="width:110px">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Low stock</option>
                            <option value="out" <?= $filter === 'out' ? 'selected' : '' ?>>Out</option>
                            <option value="recent" <?= $filter === 'recent' ? 'selected' : '' ?>>Recent</option>
                        </select></div>
                        <button class="btn btn-sm btn-primary">Apply</button>
                    </form>
                </div>
                <div class="inv-filter-bar border-0 pt-0 pb-2 px-3 mb-0" style="background:transparent;border:none!important">
                    <div class="inv-filter-bar__row justify-content-end"><?= inv_filter_exports($baseQs, true, false, false) ?></div>
                </div>
                <?php inv_table_scroll_open('min(52vh, 480px)'); ?>
                    <table class="table table-sm inv-table mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Material</th>
                                <th>Unit</th>
                                <th>Storage</th>
                                <th class="text-end">Min stock</th>
                                <th class="text-end">Current stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php $meta = inv_stock_status_meta((float)$r['stock_qty'], (float)$r['reorder_level']); ?>
                            <tr>
                                <td><?= e((string)$r['material_code']) ?></td>
                                <td><?= e((string)$r['material_name']) ?></td>
                                <td><?= e((string)$r['unit']) ?></td>
                                <td><?= e((string)($r['storage_location'] ?? '—')) ?></td>
                                <td class="text-end"><?= e(inv_format_qty((float)$r['reorder_level'])) ?></td>
                                <td class="text-end fw-semibold"><?= e(inv_format_qty((float)$r['stock_qty'], (string)$r['unit'])) ?></td>
                                <td>
                                    <span class="badge inv-badge--<?= e($meta['badge']) ?>"><?= e((string)($r['status'] ?? 'Active')) ?></span>
                                </td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-link btn-sm p-0 inv-history-btn" data-id="<?= (int)$r['id'] ?>" data-name="<?= e((string)$r['material_name']) ?>">History</button>
                                    · <a class="small" href="index.php?page=<?= rawurlencode('inventory/materials') ?>&edit=<?= (int)$r['id'] ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="8" class="text-center inv-muted py-3">No materials yet. Create one, then use Purchase Inward.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                <?php inv_table_scroll_close(); ?>
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
