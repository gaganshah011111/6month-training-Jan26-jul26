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
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        inv_save_add_stock($pdo, $_POST);
        set_flash('success', 'Stock added successfully.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('inventory/add-stock');
}

$materials = array_filter(inv_list_materials_master($pdo), static fn($m) => ($m['status'] ?? 'Active') === 'Active');
$recent = inv_list_inward($pdo, 30);
?>

<div class="inv-page">
    <header class="inv-page__head">
        <div>
            <h1 class="inv-page__title">Add Stock</h1>
            <p class="inv-page__sub">Record inward quantity. On first receipt, set minimum alert and maximum storage limits.</p>
        </div>
        <nav class="inv-page__links">
            <a href="<?= e(route_url('inventory/dashboard')) ?>">Dashboard</a>
            <a href="<?= e(route_url('inventory/materials')) ?>">Materials</a>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="inv-card">
                <div class="inv-card__head"><h2 class="inv-card__title">Inward entry</h2></div>
                <div class="inv-card__body">
                    <form method="post" class="vstack gap-2" id="inv-add-stock-form">
                        <?= csrf_input() ?>
                        <div><label class="form-label small">Material</label>
                            <select class="form-select form-select-sm erp-select-search" name="material_id" id="inv-material-select" required data-placeholder="Search material…">
                                <option value="">Search material…</option>
                                <?php foreach ($materials as $m): ?>
                                    <?php
                                    $first = inv_material_inward_count($pdo, (int)$m['id']) === 0;
                                    $needsLimits = $first || inv_material_limits_unset($m);
                                    ?>
                                    <option value="<?= (int)$m['id'] ?>"
                                        data-first="<?= $first ? '1' : '0' ?>"
                                        data-needs-limits="<?= $needsLimits ? '1' : '0' ?>"
                                        data-min="<?= e((string)($m['reorder_level'] ?? '0')) ?>"
                                        data-max="<?= e((string)($m['max_stock_level'] ?? '0')) ?>"
                                        data-sub="<?= e((string)($m['material_code'] ?? $m['unit'] ?? '')) ?>">
                                        <?= e($m['material_name']) ?> (<?= e($m['unit']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="form-label small">Quantity</label><input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="quantity" required></div>
                        <div class="border-top pt-2 mt-1">
                            <p class="small text-muted mb-1">Batch / lot (optional)</p>
                            <div><label class="form-label small">Batch number</label><input class="form-control form-control-sm" name="batch_no" maxlength="80"></div>
                            <div class="mt-1"><label class="form-label small">Expiry date</label><input type="date" class="form-control form-control-sm" name="expiry_date"></div>
                        </div>
                        <div><label class="form-label small">Date</label><input type="date" class="form-control form-control-sm" name="inward_date" value="<?= e($today) ?>" required></div>
                        <div><label class="form-label small">Supplier reference</label><input class="form-control form-control-sm" name="invoice_no" maxlength="80" placeholder="Supplier / invoice no."></div>
                        <div><label class="form-label small">Remarks</label><textarea class="form-control form-control-sm" name="remarks" rows="2"></textarea></div>

                        <div id="inv-limits-panel" class="border rounded p-2 bg-light d-none">
                            <p class="small fw-semibold mb-2" id="inv-limits-title">Stock alert settings</p>
                            <div id="inv-limits-optional" class="form-check mb-2 d-none">
                                <input class="form-check-input" type="checkbox" name="update_limits" value="1" id="inv-update-limits">
                                <label class="form-check-label small" for="inv-update-limits">Update stock alert limits</label>
                            </div>
                            <div id="inv-limits-fields">
                                <div><label class="form-label small">Minimum stock alert</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="minimum_stock" id="inv-min-stock" placeholder="Low stock warning level"></div>
                                <div class="mt-2"><label class="form-label small">Maximum storage limit</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="maximum_stock" id="inv-max-stock" placeholder="Optional max capacity"></div>
                            </div>
                        </div>

                        <button class="btn btn-success btn-sm">Add stock</button>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <section class="inv-card">
                <div class="inv-card__head"><h2 class="inv-card__title">Recent inward entries</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm inv-table mb-0">
                        <thead><tr><th>Date</th><th>Material</th><th class="text-end">Qty</th><th>Reference</th><th>Remarks</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><?= e((string)$r['inward_date']) ?></td>
                                <td><?= e((string)$r['material_name']) ?></td>
                                <td class="text-end text-success">+<?= e(number_format((float)$r['quantity'], 2)) ?> <?= e((string)$r['unit']) ?></td>
                                <td><?= e((string)($r['invoice_no'] ?? '—')) ?></td>
                                <td><?= e((string)($r['remarks'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($recent === []): ?>
                            <tr><td colspan="5" class="text-center text-muted">No inward entries yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
<script>
(function () {
    const sel = document.getElementById('inv-material-select');
    const panel = document.getElementById('inv-limits-panel');
    const title = document.getElementById('inv-limits-title');
    const optional = document.getElementById('inv-limits-optional');
    const updateChk = document.getElementById('inv-update-limits');
    const limitFields = document.getElementById('inv-limits-fields');
    const minInp = document.getElementById('inv-min-stock');
    const maxInp = document.getElementById('inv-max-stock');
    if (!sel) return;

    function syncLimits() {
        const opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) {
            panel?.classList.add('d-none');
            return;
        }
        const isFirst = opt.dataset.first === '1';
        const needsLimits = opt.dataset.needsLimits === '1';
        panel?.classList.toggle('d-none', !isFirst && !needsLimits);
        optional?.classList.toggle('d-none', isFirst);
        if (title) {
            title.textContent = isFirst ? 'Stock alert settings (first receipt)' : 'Stock alert settings';
        }
        if (isFirst) {
            if (updateChk) updateChk.checked = false;
            limitFields?.classList.remove('d-none');
            minInp?.removeAttribute('disabled');
            maxInp?.removeAttribute('disabled');
        } else {
            limitFields?.classList.toggle('d-none', !updateChk?.checked);
            if (minInp) minInp.value = opt.dataset.min || '0';
            if (maxInp) maxInp.value = opt.dataset.max || '0';
        }
    }

    sel.addEventListener('change', syncLimits);
    updateChk?.addEventListener('change', function () {
        limitFields?.classList.toggle('d-none', !this.checked);
        minInp?.toggleAttribute('disabled', !this.checked);
        maxInp?.toggleAttribute('disabled', !this.checked);
    });
    syncLimits();
})();
</script>
