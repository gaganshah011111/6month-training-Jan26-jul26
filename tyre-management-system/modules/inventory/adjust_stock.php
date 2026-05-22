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
        inv_save_stock_adjustment($pdo, $_POST);
        set_flash('success', 'Stock adjusted to match physical count.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('inventory/adjust-stock');
}

$materials = array_filter(inv_list_materials_master($pdo), static fn($m) => ($m['status'] ?? 'Active') === 'Active');
$recent = [];
if (inv_table_exists($pdo, 'stock_adjustments')) {
    $recent = $pdo->query(
        "SELECT a.*, rm.material_name, rm.unit FROM stock_adjustments a
         JOIN raw_materials rm ON rm.id = a.material_id ORDER BY a.id DESC LIMIT 25"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>

<div class="inv-page">
    <header class="inv-page__head">
        <div>
            <h1 class="inv-page__title">Adjust Stock</h1>
            <p class="inv-page__sub">Correct physical count differences — system calculates the adjustment automatically.</p>
        </div>
        <nav class="inv-page__links">
            <a href="<?= e(route_url('inventory/dashboard')) ?>">Dashboard</a>
            <a href="<?= e(route_url('inventory/use-stock')) ?>">Use Stock</a>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="inv-card">
                <div class="inv-card__head"><h2 class="inv-card__title">Physical count correction</h2></div>
                <div class="inv-card__body">
                    <form method="post" class="vstack gap-2" id="inv-adjust-form">
                        <?= csrf_input() ?>
                        <div><label class="form-label small">Material</label>
                            <select class="form-select form-select-sm" name="material_id" id="inv-adj-material" required>
                                <option value="">Select</option>
                                <?php foreach ($materials as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>" data-stock="<?= e((string)$m['stock_qty']) ?>">
                                        <?= e($m['material_name']) ?> — system: <?= e(inv_format_qty((float)$m['stock_qty'], $m['unit'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="form-label small">Actual stock (physical count)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="actual_stock" required></div>
                        <p class="small text-muted mb-0" id="inv-adj-diff"></p>
                        <div><label class="form-label small">Date</label><input type="date" class="form-control form-control-sm" name="adjust_date" value="<?= e($today) ?>"></div>
                        <div><label class="form-label small">Reason</label>
                            <select class="form-select form-select-sm" name="reason" required>
                                <?php foreach (INV_ADJUST_REASONS as $k => $label): ?>
                                    <option value="<?= e($k) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="form-label small">Remarks</label><textarea class="form-control form-control-sm" name="remarks" rows="2"></textarea></div>
                        <button class="btn btn-warning btn-sm">Apply adjustment</button>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <section class="inv-card">
                <div class="inv-card__head"><h2 class="inv-card__title">Recent adjustments</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm inv-table mb-0">
                        <thead><tr><th>Date</th><th>Material</th><th class="text-end">Was</th><th class="text-end">Now</th><th class="text-end">Diff</th><th>Reason</th><th>By</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><?= e((string)$r['adjust_date']) ?></td>
                                <td><?= e((string)$r['material_name']) ?></td>
                                <td class="text-end"><?= e(inv_format_qty((float)$r['previous_qty'])) ?></td>
                                <td class="text-end"><?= e(inv_format_qty((float)$r['actual_qty'])) ?></td>
                                <td class="text-end"><?= e(inv_format_qty((float)$r['difference_qty'])) ?></td>
                                <td><?= e(INV_ADJUST_REASONS[$r['reason']] ?? $r['reason']) ?></td>
                                <td><?= e((string)($r['operator_name'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($recent === []): ?><tr><td colspan="7" class="text-center text-muted">No adjustments yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
<script>
(function () {
    const sel = document.getElementById('inv-adj-material');
    const actual = document.querySelector('[name="actual_stock"]');
    const diff = document.getElementById('inv-adj-diff');
    function upd() {
        if (!sel || !actual || !diff) return;
        const opt = sel.options[sel.selectedIndex];
        const sys = parseFloat(opt?.dataset.stock || '0');
        const act = parseFloat(actual.value || '0');
        if (!opt?.value || actual.value === '') { diff.textContent = ''; return; }
        const d = act - sys;
        diff.textContent = 'Adjustment: ' + (d >= 0 ? '+' : '') + d.toFixed(2);
    }
    sel?.addEventListener('change', upd);
    actual?.addEventListener('input', upd);
})();
</script>
