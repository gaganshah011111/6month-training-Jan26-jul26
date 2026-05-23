<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/production_entries.php';
require_once __DIR__ . '/../../includes/inventory_service.php';

if (!has_role(['Super Admin', 'Production Manager', 'Admin'])) {
    echo 'Access denied';
    return;
}

$pdo = Database::connection();
$today = date('Y-m-d');
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $_POST['production_date'] = $_POST['production_date'] ?? $today;
        prod_save_building_entry($pdo, $_POST);
        set_flash('success', 'Building entry saved.');
        redirect('production/building');
    } catch (Throwable $e) {
        $formError = $e->getMessage();
    }
}

$machines = prod_machines_for_dept($pdo, PROD_ENTRY_BUILDING);
$operators = prod_entry_operators($pdo, $today, PROD_ENTRY_BUILDING);
$rows = prod_list_department_entries($pdo, PROD_ENTRY_BUILDING, 40);
?>

<div class="prod-page">
    <header class="prod-page__head">
        <div>
            <h1 class="prod-page__title">Building Entry</h1>
            <p class="prod-page__sub">Record green tyres built today — no dependency on mixing or curing.</p>
        </div>
        <nav class="prod-page__links"><a href="<?= e(route_url('production/dashboard')) ?>">Dashboard</a></nav>
    </header>

    <?php inv_render_low_stock_banner($pdo); ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="prod-card">
                <div class="prod-card__head"><h2 class="prod-card__title">New entry</h2></div>
                <div class="prod-card__body">
                    <?php if ($formError !== ''): ?>
                        <div class="alert alert-danger py-2 small mb-2" role="alert"><?= e($formError) ?></div>
                    <?php endif; ?>
                    <form method="post" class="vstack gap-2 prod-form">
                        <?= csrf_input() ?>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label">Date</label><input type="date" class="form-control form-control-sm" name="production_date" value="<?= e($today) ?>" required></div>
                            <div class="col-6"><label class="form-label">Shift</label><select class="form-select form-select-sm" name="shift"><?php foreach (PRODUCTION_SHIFTS as $sh): ?><option><?= e($sh) ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div><label class="form-label">Machine</label><select class="form-select form-select-sm erp-select-search" name="machine_id" required data-placeholder="Search machine…"><option value="">Search machine…</option><?php foreach ($machines as $m): ?><?php $asg = prod_assigned_operator_for_machine($pdo, (int)$m['id'], $today); ?><option value="<?= (int)$m['id'] ?>" data-sub="<?= e((string)($m['machine_name'] ?? '')) ?>"<?= $asg ? ' data-operator-id="' . (int)$asg['employee_id'] . '"' : '' ?>><?= e($m['machine_code']) ?></option><?php endforeach; ?></select></div>
                        <div><label class="form-label">Operator</label><select class="form-select form-select-sm erp-select-search" name="operator_id" data-placeholder="Search operator..."><option value="">Search operator...</option><?php foreach ($operators as $op): ?><option value="<?= (int)$op['id'] ?>" data-sub="<?= e((string)($op['employee_code'] ?? '')) ?>"><?= e($op['full_name']) ?></option><?php endforeach; ?></select></div>
                        <div><label class="form-label">Tyre type</label><select class="form-select form-select-sm erp-select-search" name="tyre_type" required data-placeholder="Search tyre type…"><option value="">Search tyre type…</option><?php foreach (TYRE_TYPES as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?></select></div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label">Green tyres built</label><input type="number" class="form-control form-control-sm" name="produced_qty" min="1" required></div>
                            <div class="col-6"><label class="form-label">Rejected</label><input type="number" class="form-control form-control-sm" name="rejected_qty" min="0" value="0"></div>
                        </div>
                        <div><label class="form-label">Remarks</label><input class="form-control form-control-sm" name="remarks"></div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Save building entry</button>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <?php
            $recentTitle = 'Recent building entries';
            $recentRows = $rows;
            $recentColumns = [
                ['key' => 'production_date', 'label' => 'Date'],
                ['key' => 'shift', 'label' => 'Shift'],
                ['key' => 'tyre_type', 'label' => 'Tyre'],
                ['key' => 'produced_qty', 'label' => 'Built', 'class' => 'text-end'],
                ['key' => 'rejected_qty', 'label' => 'Rej.', 'class' => 'text-end'],
                ['key' => 'machine_code', 'label' => 'Machine'],
                ['key' => 'operator_name', 'label' => 'Operator'],
            ];
            require __DIR__ . '/_recent_entries_table.php';
            ?>
        </div>
    </div>
</div>
