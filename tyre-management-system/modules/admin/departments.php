<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/../../includes/admin_control_center.php';

require_once __DIR__ . '/../../includes/admin_departments_service.php';

if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }

$pdo = Database::connection();

admin_departments_handle_post($pdo);

$cards = admin_department_cards($pdo);

$dbRows = admin_departments_list($pdo);

$categories = admin_department_categories($pdo);

$allEmployees = admin_department_employees($pdo);

$editId = (int)($_GET['edit'] ?? 0);

$editDept = $editId > 0 ? admin_department_get($pdo, $editId) : null;

$perf = $editDept ? admin_department_performance($pdo, $editId) : null;

?>

<div class="admin-cc module-shell">

    <?php admin_page_head('Department Management', 'Create, edit, merge departments · assign heads · transfer staff', '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal"><i class="bi bi-plus-lg"></i> Add Department</button>'); ?>



    <div class="admin-dept-cards">

        <?php foreach ($cards as $c): ?>

            <div class="admin-dept-card">

                <div class="admin-dept-card__icon"><i class="bi <?= e($c['icon']) ?>"></i></div>

                <h3><?= e($c['name']) ?></h3>

                <dl>

                    <div><dt>Department Head</dt><dd><?= e($c['head']) ?></dd></div>

                    <div><dt>Employee Count</dt><dd><?= (int)$c['employees'] ?></dd></div>

                    <div><dt>Active Users</dt><dd><?= (int)$c['active_users'] ?></dd></div>

                </dl>

            </div>

        <?php endforeach; ?>

    </div>



    <?php if ($editDept && $perf): ?>

    <section class="admin-card">

        <h2 class="admin-card__title">Edit: <?= e((string)$editDept['department_name']) ?></h2>

        <div class="admin-cc__kpis admin-cc__kpis--4 mb-3">

            <div class="admin-kpi"><span>Employees</span><strong><?= (int)$perf['employees'] ?></strong></div>

            <div class="admin-kpi"><span>Active Users</span><strong><?= (int)$perf['active_users'] ?></strong></div>

            <div class="admin-kpi"><span>Attendance Today</span><strong><?= (int)$perf['attendance_today'] ?></strong></div>

            <div class="admin-kpi"><span>Leave Pending</span><strong><?= (int)$perf['leave_pending'] ?></strong></div>

        </div>

        <form method="post" class="admin-profile-form row g-2">

            <?= csrf_input() ?><input type="hidden" name="action" value="update"><input type="hidden" name="department_id" value="<?= $editId ?>">

            <div class="col-md-4"><label>Department Name</label><input class="form-control form-control-sm" name="department_name" value="<?= e((string)$editDept['department_name']) ?>" required></div>

            <div class="col-md-4"><label>Department Head</label><select class="form-select form-select-sm" name="head_employee_id"><option value="">— None —</option><?php foreach ($allEmployees as $emp): ?><option value="<?= (int)$emp['id'] ?>" <?= (int)($editDept['head_employee_id'] ?? 0)===(int)$emp['id']?'selected':'' ?>><?= e((string)$emp['full_name']) ?></option><?php endforeach; ?></select></div>

            <div class="col-md-2"><label>Status</label><select class="form-select form-select-sm" name="status"><option value="active" <?= ($editDept['status']??'')==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= ($editDept['status']??'')==='inactive'?'selected':'' ?>>Inactive</option></select></div>

            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100">Save</button></div>

        </form>

        <div class="mt-3 admin-cc__grid admin-cc__grid--2">

            <form method="post" class="admin-profile-form admin-confirm-form">

                <?= csrf_input() ?><input type="hidden" name="action" value="transfer_employee">

                <label>Transfer Employee</label>

                <div class="d-flex gap-2"><select class="form-select form-select-sm" name="employee_id" required><option value="">Select employee</option><?php foreach (admin_department_employees($pdo, $editId) as $emp): ?><option value="<?= (int)$emp['id'] ?>"><?= e((string)$emp['full_name']) ?></option><?php endforeach; ?></select>

                <select class="form-select form-select-sm" name="target_department_id" required><option value="">To department</option><?php foreach ($dbRows as $d): if ((int)$d['id']===$editId) continue; ?><option value="<?= (int)$d['id'] ?>"><?= e((string)$d['department_name']) ?></option><?php endforeach; ?></select>

                <button class="btn btn-sm btn-outline-primary" data-confirm="Transfer this employee?">Transfer</button></div>

            </form>

            <form method="post" class="admin-profile-form admin-confirm-form">

                <?= csrf_input() ?><input type="hidden" name="action" value="merge"><input type="hidden" name="source_department_id" value="<?= $editId ?>">

                <label>Merge Into</label>

                <div class="d-flex gap-2"><select class="form-select form-select-sm" name="target_department_id" required><option value="">Target department</option><?php foreach ($dbRows as $d): if ((int)$d['id']===$editId) continue; ?><option value="<?= (int)$d['id'] ?>"><?= e((string)$d['department_name']) ?></option><?php endforeach; ?></select>

                <button class="btn btn-sm btn-outline-danger" data-confirm="Merge this department into the target? Source will be disabled.">Merge</button></div>

            </form>

        </div>

        <?php if (($editDept['status'] ?? '') === 'active'): ?>

        <form method="post" class="mt-2 admin-confirm-form"><?= csrf_input() ?><input type="hidden" name="action" value="disable"><input type="hidden" name="department_id" value="<?= $editId ?>"><button class="btn btn-sm btn-outline-warning" data-confirm="Disable this department?">Disable Department</button></form>

        <?php endif; ?>

    </section>

    <?php endif; ?>



    <?php if ($dbRows !== []): ?>

    <section class="admin-card admin-table-wrap">

        <h2 class="admin-card__title">Department Master</h2>

        <table class="table table-sm admin-table mb-0">

            <thead><tr><th>Department</th><th>Category</th><th>Head</th><th class="text-end">Employees</th><th>Status</th><th></th></tr></thead>

            <tbody>

            <?php foreach ($dbRows as $r): ?>

                <tr>

                    <td><?= e((string)$r['department_name']) ?></td>

                    <td><?= e((string)$r['category_name']) ?></td>

                    <td><?= e((string)($r['head_name'] ?? '—')) ?></td>

                    <td class="text-end"><?= (int)($r['emp_count'] ?? 0) ?></td>

                    <td><span class="admin-badge admin-badge--ok"><?= e((string)$r['status']) ?></span></td>

                    <td><a href="<?= e(route_url('admin/departments', ['edit' => $r['id']])) ?>" class="admin-action-btn admin-action-btn--neutral">Manage</a></td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </section>

    <?php endif; ?>

</div>



<div class="modal fade" id="addDeptModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content">

    <?= csrf_input() ?><input type="hidden" name="action" value="add">

    <div class="modal-header"><h5 class="modal-title">Add Department</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>

    <div class="modal-body row g-2">

        <div class="col-12"><label>Category</label><select class="form-select" name="category_id" required><?php foreach ($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= e((string)$cat['category_name']) ?></option><?php endforeach; ?></select></div>

        <div class="col-8"><label>Department Name</label><input class="form-control" name="department_name" required></div>

        <div class="col-4"><label>Code</label><input class="form-control" name="department_code" required maxlength="10"></div>

    </div>

    <div class="modal-footer"><button class="btn btn-primary btn-sm">Add Department</button></div>

</form></div></div>


