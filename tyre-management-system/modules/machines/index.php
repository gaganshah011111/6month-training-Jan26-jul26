<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/machine_service.php';

if (!has_role(['Super Admin', 'Production Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'create');
    try {
        if ($action === 'deactivate') {
            mach_deactivate_machine($pdo, (int)($_POST['id'] ?? 0), trim((string)($_POST['reason'] ?? '')));
            set_flash('success', 'Machine deactivated (history preserved).');
        } elseif ($action === 'create') {
            mach_save_machine($pdo, $_POST);
            set_flash('success', 'Machine added to master.');
        } elseif ($action === 'update') {
            mach_save_machine($pdo, $_POST, (int)($_POST['id'] ?? 0));
            set_flash('success', 'Machine updated.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('machines/list');
}

$rows = mach_list_machines($pdo, ['include_inactive' => true]);
$editId = isset($_GET['edit']) && ctype_digit((string)$_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId > 0 ? mach_get_machine($pdo, $editId) : null;
?>

<div class="prod-page mach-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Machine Master</h1>
            <p class="prod-page__sub">Register machines by department. Deactivated machines stay in history — never deleted.</p>
        </div>
    </header>

    <?php require __DIR__ . '/_nav.php'; ?>

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

                        <p class="mach-section-title">Identity</p>
                        <div class="mb-2">
                            <label class="form-label">Machine code</label>
                            <input class="form-control form-control-sm" name="machine_code" value="<?= e((string)($editRow['machine_code'] ?? '')) ?>" required maxlength="50">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Machine name</label>
                            <input class="form-control form-control-sm" name="machine_name" value="<?= e((string)($editRow['machine_name'] ?? '')) ?>" required maxlength="150">
                        </div>

                        <p class="mach-section-title mt-2">Location</p>
                        <div class="mb-2">
                            <label class="form-label">Department</label>
                            <select class="form-select form-select-sm" name="department" required>
                                <option value="">—</option>
                                <?php foreach (MACHINE_PRODUCTION_DEPARTMENTS as $d): ?>
                                    <option value="<?= e($d) ?>" <?= ($editRow['department'] ?? '') === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Section</label>
                            <input class="form-control form-control-sm" name="section" value="<?= e((string)($editRow['section'] ?? '')) ?>" maxlength="80" placeholder="e.g. Line A">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Machine type</label>
                            <input class="form-control form-control-sm" name="machine_type" value="<?= e((string)($editRow['machine_type'] ?? '')) ?>" maxlength="80">
                        </div>

                        <p class="mach-section-title mt-2">Status</p>
                        <div class="mb-2">
                            <label class="form-label">Status</label>
                            <select class="form-select form-select-sm" name="status" required>
                                <?php foreach (MACHINE_MASTER_STATUSES as $st): ?>
                                    <option value="<?= e($st) ?>" <?= ($editRow && ($editRow['status'] ?? '') === $st) ? 'selected' : '' ?>><?= e($st) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Installation date</label>
                            <input type="date" class="form-control form-control-sm" name="installation_date" value="<?= e((string)($editRow['installation_date'] ?? '')) ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Last maintenance</label>
                            <input type="date" class="form-control form-control-sm" name="last_maintenance_date" value="<?= e((string)($editRow['last_maintenance_date'] ?? '')) ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Shift capacity</label>
                            <input type="number" class="form-control form-control-sm" name="shift_capacity" min="0" value="<?= e((string)($editRow['shift_capacity'] ?? 0)) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control form-control-sm" name="remarks" rows="2"><?= e((string)($editRow['remarks'] ?? '')) ?></textarea>
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
                <div class="prod-card__head"><h2 class="prod-card__title">Machine register</h2></div>
                <div class="table-responsive">
                    <table class="table table-hover prod-table mb-0">
                        <thead>
                            <tr>
                                <th>Code</th><th>Name</th><th>Dept</th><th>Section</th><th>Type</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No machines registered.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <?php $mb = mach_status_badge((string)$r['status']); ?>
                                <tr class="<?= (int)($r['is_active'] ?? 1) === 0 ? 'table-secondary' : '' ?>">
                                    <td><strong><?= e($r['machine_code']) ?></strong></td>
                                    <td><?= e($r['machine_name']) ?></td>
                                    <td><?= e($r['department'] ?? '—') ?></td>
                                    <td><?= e($r['section'] ?? '—') ?></td>
                                    <td><?= e($r['machine_type'] ?? '—') ?></td>
                                    <td><span class="<?= e($mb['class']) ?>"><?= e($mb['label']) ?></span></td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('machines/list')) ?>?edit=<?= (int)$r['id'] ?>">Edit</a>
                                        <?php if ((int)($r['is_active'] ?? 1) === 1): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Deactivate this machine? History will be kept.');">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                                            </form>
                                        <?php endif; ?>
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
