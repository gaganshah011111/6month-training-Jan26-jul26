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
        inv_save_use_stock($pdo, $_POST);
        set_flash('success', 'Stock usage recorded.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('inventory/use-stock');
}

$materials = array_filter(inv_list_materials_master($pdo), static fn($m) => ($m['status'] ?? 'Active') === 'Active');
$recent = inv_list_usage($pdo, 30);
?>

<div class="inv-page">
    <header class="inv-page__head">
        <div>
            <h1 class="inv-page__title">Use Stock</h1>
            <p class="inv-page__sub">Production consumption only — record material used by department. Production entries also deduct stock automatically.</p>
        </div>
        <nav class="inv-page__links">
            <a href="<?= e(route_url('inventory/dashboard')) ?>">Dashboard</a>
            <a href="<?= e(route_url('inventory/materials')) ?>">Materials</a>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="inv-card">
                <div class="inv-card__head"><h2 class="inv-card__title">Usage entry</h2></div>
                <div class="inv-card__body">
                    <form method="post" class="vstack gap-2">
                        <?= csrf_input() ?>
                        <div><label class="form-label small">Department</label>
                            <select class="form-select form-select-sm" name="department" required>
                                <option>Mixing</option><option>Building</option><option>Curing</option>
                                <option>QC</option><option>Maintenance</option><option>Other</option>
                            </select>
                        </div>
                        <div><label class="form-label small">Issue type</label>
                            <select class="form-select form-select-sm" name="usage_reason" required>
                                <option value="production_use">Production use</option>
                                <?php foreach (INV_ISSUE_REASONS as $k => $label): ?>
                                    <option value="<?= e($k) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="form-label small">Material</label>
                            <select class="form-select form-select-sm" name="material_id" required>
                                <option value="">Select material</option>
                                <?php foreach ($materials as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>"><?= e($m['material_name']) ?> (<?= e($m['unit']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="form-label small">Quantity used</label><input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="quantity" required></div>
                        <div><label class="form-label small">Date</label><input type="date" class="form-control form-control-sm" name="usage_date" value="<?= e($today) ?>" required></div>
                        <div><label class="form-label small">Remarks</label><textarea class="form-control form-control-sm" name="remarks" rows="2"></textarea></div>
                        <button class="btn btn-primary btn-sm">Use stock</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">Blocked if quantity exceeds available stock.</p>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <section class="inv-card">
                <div class="inv-card__head"><h2 class="inv-card__title">Recent usage entries</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm inv-table mb-0">
                        <thead><tr><th>Date</th><th>Department</th><th>Material</th><th class="text-end">Qty</th><th>Remarks</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><?= e((string)$r['usage_date']) ?></td>
                                <td><?= e((string)($r['department'] ?? '—')) ?></td>
                                <td><?= e((string)$r['material_name']) ?></td>
                                <td class="text-end text-danger">−<?= e(number_format((float)$r['quantity'], 2)) ?> <?= e((string)$r['unit']) ?></td>
                                <td><?= e((string)($r['remarks'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($recent === []): ?>
                            <tr><td colspan="5" class="text-center text-muted">No usage entries yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
