<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_entries.php';

if (!has_role(['Super Admin', 'Production Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $_POST['production_date'] = $_POST['production_date'] ?? $today;
        prod_save_curing_entry($pdo, $_POST);
        set_flash('success', 'Curing entry saved.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('production/curing');
}

$machines = prod_machines_for_dept($pdo, PROD_ENTRY_CURING);
$operators = prod_entry_operators($pdo, $today);
$rows = prod_list_department_entries($pdo, PROD_ENTRY_CURING, 40);
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Curing Entry</h1>
            <p class="prod-page__sub">Record tyres cured today — independent daily entry.</p>
        </div>
        <nav class="prod-page__links"><a href="<?= e(route_url('production/dashboard')) ?>">Dashboard</a></nav>
    </header>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="prod-card">
                <div class="prod-card__head"><h2 class="prod-card__title">New entry</h2></div>
                <div class="prod-card__body">
                    <form method="post" class="vstack gap-2 prod-form">
                        <?= csrf_input() ?>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label">Date</label><input type="date" class="form-control form-control-sm" name="production_date" value="<?= e($today) ?>" required></div>
                            <div class="col-6"><label class="form-label">Shift</label><select class="form-select form-select-sm" name="shift"><?php foreach (PRODUCTION_SHIFTS as $sh): ?><option><?= e($sh) ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div><label class="form-label">Curing machine</label><select class="form-select form-select-sm" name="machine_id" required><option value="">Select</option><?php foreach ($machines as $m): ?><?php if (production_machine_can_run((string)$m['status'])): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['machine_code']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
                        <div><label class="form-label">Operator</label><select class="form-select form-select-sm" name="operator_id"><option value="">—</option><?php foreach ($operators as $op): ?><option value="<?= (int)$op['id'] ?>"><?= e($op['full_name']) ?></option><?php endforeach; ?></select></div>
                        <div><label class="form-label">Tyre type</label><select class="form-select form-select-sm" name="tyre_type"><?php foreach (TYRE_TYPES as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
                        <div class="row g-2">
                            <div class="col-4"><label class="form-label">Cured qty</label><input type="number" class="form-control form-control-sm" name="produced_qty" min="1" required></div>
                            <div class="col-4"><label class="form-label">Rejected</label><input type="number" class="form-control form-control-sm" name="rejected_qty" min="0" value="0"></div>
                            <div class="col-4"><label class="form-label">Downtime (min)</label><input type="number" class="form-control form-control-sm" name="downtime_minutes" min="0" value="0"></div>
                        </div>
                        <div><label class="form-label">Remarks</label><input class="form-control form-control-sm" name="remarks"></div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Save curing entry</button>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <section class="prod-card prod-card--table">
                <div class="prod-card__head"><h2 class="prod-card__title">Recent curing entries</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm prod-table mb-0">
                        <thead><tr><th>Date</th><th>Shift</th><th class="text-end">Cured</th><th class="text-end">Rej.</th><th class="text-end">Down</th><th>Machine</th><th>Operator</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e($r['production_date']) ?></td><td><?= e($r['shift']) ?></td>
                                <td class="text-end"><?= e((string)$r['produced_qty']) ?></td><td class="text-end"><?= e((string)$r['rejected_qty']) ?></td>
                                <td class="text-end"><?= e((string)($r['downtime_minutes'] ?? 0)) ?></td>
                                <td><?= e($r['machine_code'] ?? '—') ?></td><td><?= e($r['operator_name'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No production entries found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
