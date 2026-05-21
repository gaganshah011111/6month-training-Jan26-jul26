<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_service.php';
require_once __DIR__ . '/../../includes/production_departments.php';

if (!has_role(['Super Admin', 'Production Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
production_seed_default_bom($pdo);
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_string('action') === 'create_order') {
    verify_csrf();
    try {
        $id = prod_create_master_order($pdo, $_POST, $userId);
        set_flash('success', 'Master production target created. Departments record output independently.');
        header('Location: ' . route_url('production/order') . '&id=' . $id);
        exit;
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
}

$orders = prod_list_master_orders($pdo, 80);
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Production Orders</h1>
            <p class="prod-page__sub">Master targets only — Mixing, Building, Curing, and QC run in parallel with batch references (CMP → GBT → CUR).</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('production/dashboard')) ?>">Dashboard</a>
            <a href="<?= e(route_url('production/mixing')) ?>">Mixing</a>
            <a href="<?= e(route_url('production/building')) ?>">Building</a>
            <a href="<?= e(route_url('production/curing')) ?>">Curing</a>
            <a href="<?= e(route_url('production/qc')) ?>">QC</a>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="prod-card">
                <div class="prod-card__head"><h2 class="prod-card__title">New master order</h2></div>
                <div class="prod-card__body">
                    <form method="post" class="vstack gap-2">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="create_order">
                        <div>
                            <label class="form-label">Tyre type</label>
                            <select class="form-select form-select-sm" name="tyre_type" required>
                                <?php foreach (TYRE_TYPES as $t): ?>
                                    <option value="<?= e($t) ?>"><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Target quantity</label>
                            <input class="form-control form-control-sm" type="number" name="target_qty" min="1" value="1000" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Deadline</label>
                                <input class="form-control form-control-sm" type="date" name="deadline">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Priority</label>
                                <select class="form-select form-select-sm" name="priority">
                                    <option>Normal</option><option>High</option><option>Urgent</option><option>Low</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control form-control-sm" name="remarks" rows="2"></textarea>
                        </div>
                        <p class="prod-form__hint">Does not start production — use department pages to log output.</p>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Create master order</button>
                    </form>
                </div>
            </section>

            <div class="prod-dept-quick mt-3">
                <a class="prod-dept-quick__item" href="<?= e(route_url('production/mixing')) ?>">Mixing</a>
                <a class="prod-dept-quick__item" href="<?= e(route_url('production/building')) ?>">Building</a>
                <a class="prod-dept-quick__item" href="<?= e(route_url('production/curing')) ?>">Curing</a>
                <a class="prod-dept-quick__item" href="<?= e(route_url('production/qc')) ?>">QC</a>
            </div>
        </div>

        <div class="col-lg-8">
            <section class="prod-card prod-card--table">
                <div class="prod-card__head"><h2 class="prod-card__title">Master orders &amp; department output</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm prod-table mb-0">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Tyre</th>
                                <th class="text-end">Target</th>
                                <th class="text-end">Mixing</th>
                                <th class="text-end">Building</th>
                                <th class="text-end">Curing</th>
                                <th class="text-end">QC pass</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><strong><?= e($o['order_code']) ?></strong></td>
                                <td><?= e($o['tyre_type']) ?></td>
                                <td class="text-end"><?= e((string)$o['target_qty']) ?></td>
                                <td class="text-end"><?= e((string)$o['mixing_output']) ?> kg</td>
                                <td class="text-end"><?= e((string)$o['building_output']) ?></td>
                                <td class="text-end"><?= e((string)$o['curing_output']) ?></td>
                                <td class="text-end"><?= e((string)$o['qc_passed']) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-outline-primary btn-sm" href="<?= e(route_url('production/order')) ?>&id=<?= (int)$o['id'] ?>">Trace</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$orders): ?>
                            <tr><td colspan="8" class="text-muted text-center py-4">No orders yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
