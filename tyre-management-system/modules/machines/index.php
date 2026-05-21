<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_service.php';

if (!has_role(['Super Admin', 'Production Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'create');
    try {
        if ($action === 'create') {
            production_save_machine($pdo, $_POST);
            set_flash('success', 'Machine added to master.');
        } elseif ($action === 'update') {
            production_save_machine($pdo, $_POST, (int)($_POST['id'] ?? 0));
            set_flash('success', 'Machine updated.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('machines/list');
}

$rows = production_list_machines($pdo);
$editId = isset($_GET['edit']) && ctype_digit((string)$_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = null;
foreach ($rows as $r) {
    if ((int)$r['id'] === $editId) {
        $editRow = $r;
        break;
    }
}
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Machines</h1>
            <p class="prod-page__sub">Machines by department (Mixing / Building / Curing) — status only; output logged per department module.</p>
        </div>
        <nav class="prod-page__links">
            <a href="<?= e(route_url('production/dashboard')) ?>">Dashboard</a>
            <a href="<?= e(route_url('production/mixing')) ?>">Mixing</a>
        </nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="prod-card">
                <div class="prod-card__head">
                    <h2 class="prod-card__title"><?= $editRow ? 'Edit machine' : 'Add machine' ?></h2>
                </div>
                <div class="prod-card__body">
                    <form method="post" class="prod-form">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
                        <?php if ($editRow): ?>
                            <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-2">
                            <label class="form-label">Machine code</label>
                            <input class="form-control form-control-sm" name="machine_code" value="<?= e((string)($editRow['machine_code'] ?? '')) ?>" required maxlength="50">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Machine name</label>
                            <input class="form-control form-control-sm" name="machine_name" value="<?= e((string)($editRow['machine_name'] ?? '')) ?>" required maxlength="150">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Department</label>
                            <select class="form-select form-select-sm" name="department">
                                <option value="">—</option>
                                <?php foreach (['Mixing', 'Building', 'Curing'] as $d): ?>
                                    <option value="<?= e($d) ?>" <?= ($editRow['department'] ?? '') === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Machine type</label>
                            <input class="form-control form-control-sm" name="machine_type" value="<?= e((string)($editRow['machine_type'] ?? '')) ?>" placeholder="e.g. Curing press" maxlength="80">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Shift capacity (units)</label>
                            <input type="number" class="form-control form-control-sm" name="shift_capacity" min="0" value="<?= e((string)($editRow['shift_capacity'] ?? 0)) ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Status</label>
                            <select class="form-select form-select-sm" name="status" required>
                                <?php foreach (MACHINE_STATUSES as $st): ?>
                                    <option value="<?= e($st) ?>" <?= ($editRow && ($editRow['status'] ?? '') === $st) ? 'selected' : '' ?>><?= e($st) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Last maintenance</label>
                            <input type="date" class="form-control form-control-sm" name="last_maintenance_date" value="<?= e((string)($editRow['last_maintenance_date'] ?? '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control form-control-sm" name="notes" rows="2" maxlength="2000"><?= e((string)($editRow['notes'] ?? '')) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?= $editRow ? 'Update machine' : 'Add machine' ?></button>
                        <?php if ($editRow): ?>
                            <a class="btn btn-link btn-sm w-100 mt-1" href="<?= e(route_url('machines/list')) ?>">Cancel edit</a>
                        <?php endif; ?>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-lg-8">
            <section class="prod-card prod-card--table">
                <div class="prod-card__head">
                    <h2 class="prod-card__title">Machine register</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover prod-table mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th class="text-end">Capacity</th>
                                <th>Status</th>
                                <th>Maintenance</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No machines registered.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <?php $mb = production_machine_status_badge((string)$r['status']); ?>
                                <tr>
                                    <td><strong><?= e($r['machine_code']) ?></strong></td>
                                    <td><?= e($r['machine_name']) ?></td>
                                    <td><?= e($r['machine_type'] ?? '—') ?></td>
                                    <td class="text-end"><?= e((string)($r['shift_capacity'] ?? 0)) ?></td>
                                    <td><span class="<?= e($mb['class']) ?>"><?= e($mb['label']) ?></span></td>
                                    <td class="text-nowrap"><?= !empty($r['last_maintenance_date']) ? e($r['last_maintenance_date']) : '—' ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('machines/list')) ?>&edit=<?= (int)$r['id'] ?>">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
