<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_entries.php';

if (!has_role(['Super Admin', 'Production Manager', 'Quality Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $_POST['entry_date'] = $_POST['entry_date'] ?? $today;
        prod_save_qc_entry($pdo, $_POST);
        set_flash('success', 'QC entry saved. Passed tyres added to finished goods.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('production/qc');
}

$rows = prod_list_department_entries($pdo, PROD_ENTRY_QC, 40);
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">QC Entry</h1>
            <p class="prod-page__sub">Record inspection results — independent of shop-floor entries.</p>
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
                            <div class="col-6"><label class="form-label">Date</label><input type="date" class="form-control form-control-sm" name="entry_date" value="<?= e($today) ?>" required></div>
                            <div class="col-6"><label class="form-label">Shift</label><select class="form-select form-select-sm" name="shift"><?php foreach (PRODUCTION_SHIFTS as $sh): ?><option><?= e($sh) ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div><label class="form-label">Inspector</label><input class="form-control form-control-sm" name="inspector_name" required></div>
                        <div><label class="form-label">Tyre type</label><select class="form-select form-select-sm" name="tyre_type" required><?php foreach (TYRE_TYPES as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
                        <div class="row g-2">
                            <div class="col-4"><label class="form-label">Checked</label><input type="number" class="form-control form-control-sm" name="checked_qty" min="1" required></div>
                            <div class="col-4"><label class="form-label">Passed</label><input type="number" class="form-control form-control-sm" name="passed_qty" min="0" required></div>
                            <div class="col-4"><label class="form-label">Failed</label><input type="number" class="form-control form-control-sm" name="failed_qty" min="0" value="0"></div>
                        </div>
                        <div><label class="form-label">Defect type</label><input class="form-control form-control-sm" name="defect_type" placeholder="sidewall crack, air bubble…"></div>
                        <div><label class="form-label">Remarks</label><input class="form-control form-control-sm" name="remarks"></div>
                        <button type="submit" class="btn btn-success btn-sm w-100">Save QC entry</button>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <section class="prod-card prod-card--table">
                <div class="prod-card__head"><h2 class="prod-card__title">Recent QC entries</h2></div>
                <div class="table-responsive">
                    <table class="table table-sm prod-table mb-0">
                        <thead><tr><th>Date</th><th>Shift</th><th>Tyre</th><th class="text-end">Checked</th><th class="text-end">Pass</th><th class="text-end">Fail</th><th>Defect</th><th>Inspector</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e($r['entry_date']) ?></td><td><?= e($r['shift']) ?></td><td><?= e($r['tyre_type']) ?></td>
                                <td class="text-end"><?= e((string)$r['checked_qty']) ?></td><td class="text-end"><?= e((string)$r['passed_qty']) ?></td>
                                <td class="text-end"><?= e((string)$r['failed_qty']) ?></td>
                                <td><?= e($r['defect_type'] ?? '—') ?></td><td><?= e($r['inspector_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No production entries found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
