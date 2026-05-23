<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/machine_service.php';
require_once __DIR__ . '/../../includes/production_entries.php';

if (!has_role(['Super Admin', 'Production Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$today = date('Y-m-d');
$deptFilter = (string)($_GET['department'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'assign');
    try {
        if ($action === 'remove') {
            mach_remove_assignment($pdo, (int)($_POST['id'] ?? 0), trim((string)($_POST['reason'] ?? '')));
            set_flash('success', 'Assignment closed. History preserved.');
        } elseif ($action === 'assign' || $action === 'update') {
            $id = $action === 'update' ? (int)($_POST['id'] ?? 0) : null;
            mach_save_assignment($pdo, $_POST, $id > 0 ? $id : null);
            set_flash('success', $id ? 'Assignment updated.' : 'Operator assigned to machine.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('machines/assignments' . ($deptFilter !== '' ? '?department=' . rawurlencode($deptFilter) : ''));
}

$filters = [];
if ($deptFilter !== '' && in_array($deptFilter, MACHINE_PRODUCTION_DEPARTMENTS, true)) {
    $filters['department'] = $deptFilter;
}
$assignments = mach_list_assignments($pdo, $filters);
$machines = mach_list_machines($pdo);
$editId = isset($_GET['edit']) && ctype_digit((string)$_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = $editId > 0 ? mach_get_assignment($pdo, $editId) : null;

$formDept = $editRow['department'] ?? ($deptFilter !== '' ? $deptFilter : 'Mixing');
$prodDept = match ($formDept) {
    'Building' => PROD_ENTRY_BUILDING,
    'Curing' => PROD_ENTRY_CURING,
    default => PROD_ENTRY_MIXING,
};
$operators = prod_entry_operators($pdo, $today, $prodDept);
$editOperatorId = $editRow ? (int)$editRow['employee_id'] : 0;
$operatorIds = array_map(static fn(array $op): int => (int)$op['id'], $operators);
if ($editRow && $editOperatorId > 0 && !in_array($editOperatorId, $operatorIds, true)) {
    array_unshift($operators, [
        'id' => $editOperatorId,
        'full_name' => (string)$editRow['operator_name'],
        'employee_code' => (string)($editRow['employee_code'] ?? ''),
    ]);
}
?>

<div class="prod-page mach-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Machine Assignments</h1>
            <p class="prod-page__sub">Assign operators to machines. Reassign when shifts change — past records are never overwritten.</p>
        </div>
    </header>

    <?php require __DIR__ . '/_nav.php'; ?>

    <form method="get" class="row g-2 mb-3 align-items-end">
        <input type="hidden" name="page" value="machines/assignments">
        <div class="col-auto">
            <label class="form-label small">Department</label>
            <select class="form-select form-select-sm" name="department" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach (MACHINE_PRODUCTION_DEPARTMENTS as $d): ?>
                    <option value="<?= e($d) ?>" <?= $deptFilter === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="prod-card<?= $editRow ? ' mach-edit-panel--active' : '' ?>" id="machEditPanel" tabindex="-1">
                <div class="prod-card__head">
                    <h2 class="prod-card__title"><?= $editRow ? 'Edit assignment' : 'New assignment' ?></h2>
                </div>
                <div class="prod-card__body">
                    <form method="post" class="prod-form vstack gap-2">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'assign' ?>">
                        <?php if ($editRow): ?>
                            <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
                        <?php endif; ?>

                        <div>
                            <label class="form-label">Department</label>
                            <select class="form-select form-select-sm" name="department" id="machAssignDept" required>
                                <?php foreach (MACHINE_PRODUCTION_DEPARTMENTS as $d): ?>
                                    <option value="<?= e($d) ?>" <?= $formDept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Machine</label>
                            <select class="form-select form-select-sm erp-select-search" name="machine_id" required data-placeholder="Search machine…">
                                <option value="">—</option>
                                <?php foreach ($machines as $m): ?>
                                    <?php if ((int)($m['is_active'] ?? 1) !== 1) {
                                        continue;
                                    } ?>
                                    <option value="<?= (int)$m['id'] ?>" <?= $editRow && (int)$editRow['machine_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['machine_code']) ?> — <?= e($m['machine_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Operator</label>
                            <select class="form-select form-select-sm erp-select-search" name="employee_id" required data-placeholder="Search operator...">
                                <option value="">Search operator...</option>
                                <?php foreach ($operators as $op): ?>
                                    <option value="<?= (int)$op['id'] ?>" data-sub="<?= e((string)($op['employee_code'] ?? '')) ?>" <?= $editRow && (int)$editRow['employee_id'] === (int)$op['id'] ? 'selected' : '' ?>><?= e($op['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">From date</label>
                                <input type="date" class="form-control form-control-sm" name="assigned_from" value="<?= e((string)($editRow['assigned_from'] ?? $today)) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Till (optional)</label>
                                <input type="date" class="form-control form-control-sm" name="assigned_till" value="<?= e((string)($editRow['assigned_till'] ?? '')) ?>">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Shift</label>
                            <select class="form-select form-select-sm" name="shift">
                                <option value="">Any</option>
                                <?php foreach (MACH_ASSIGNMENT_SHIFTS as $sh): ?>
                                    <option <?= ($editRow['shift'] ?? '') === $sh ? 'selected' : '' ?>><?= e($sh) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Remarks</label>
                            <input class="form-control form-control-sm" name="remarks" value="<?= e((string)($editRow['remarks'] ?? '')) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100"><?= $editRow ? 'Save changes' : 'Assign operator' ?></button>
                        <?php if ($editRow): ?>
                            <a class="btn btn-link btn-sm" href="<?= e(route_url('machines/assignments')) ?>">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-lg-8">
            <section class="prod-card prod-card--table">
                <div class="prod-card__head"><h2 class="prod-card__title">Assignments</h2></div>
                <div class="mach-table-scroll-hint"><i class="bi bi-arrows-expand"></i> Scroll horizontally to view all columns</div>
                <div class="mach-table-wrap" tabindex="0" aria-label="Assignments table">
                    <table class="table table-sm prod-table mb-0">
                        <thead>
                            <tr>
                                <th>Machine</th><th>Operator</th><th>Dept</th><th>Shift</th><th>From</th><th>Till</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($assignments as $a): ?>
                            <tr>
                                <td><strong><?= e($a['machine_code']) ?></strong></td>
                                <td><?= e($a['operator_name']) ?> <span class="text-muted small"><?= e($a['employee_code'] ?? '') ?></span></td>
                                <td><?= e($a['department']) ?></td>
                                <td><?= e($a['shift'] ?? '—') ?></td>
                                <td><?= e($a['assigned_from']) ?></td>
                                <td><?= e($a['assigned_till'] ?? '—') ?></td>
                                <td>
                                    <?php if ((int)$a['is_active'] === 1): ?>
                                        <span class="mach-assign-badge">Active</span>
                                    <?php else: ?>
                                        <span class="text-muted small">Closed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="mach-actions">
                                    <?php
                                    $editQs = ['edit' => (int)$a['id']];
                                    if ($deptFilter !== '') {
                                        $editQs['department'] = $deptFilter;
                                    }
                                    ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(route_url('machines/assignments', $editQs)) ?>">Edit</a>
                                    <?php if ((int)$a['is_active'] === 1): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('End this assignment?');">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">End</button>
                                        </form>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$assignments): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No assignments yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
