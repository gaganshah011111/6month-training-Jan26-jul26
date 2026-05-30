<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/admin_departments_service.php';
require_once __DIR__ . '/../../includes/admin_control_center.php';
if (!admin_can_access()) { echo '<div class="alert alert-warning m-3">Access denied.</div>'; return; }
$pdo = Database::connection();
admin_departments_handle_post($pdo);
$cards = admin_department_cards($pdo);
$dbRows = admin_departments_list($pdo, true);
$editId = (int)($_GET['edit'] ?? 0);
$editDept = $editId > 0 ? admin_department_get($pdo, $editId) : null;
$allEmployees = admin_department_employees($pdo);
$addBtn = '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal">Add Department</button>';
?>
<div class="sa-console">
    <?php admin_page_head('Departments', 'Organizational structure — heads, staffing, and user assignment', $addBtn); ?>

    <div class="sa-dept-grid">
        <?php foreach ($cards as $c): ?>
            <article class="sa-dept-card">
                <h3><?= e($c['name']) ?></h3>
                <dl>
                    <div><dt>Head</dt><dd><?= e($c['head']) ?></dd></div>
                    <div><dt>Employees</dt><dd><?= (int)$c['employees'] ?></dd></div>
                    <div><dt>Logins</dt><dd><?= (int)$c['active_users'] ?></dd></div>
                    <div><dt>Status</dt><dd><span class="sa-badge sa-badge--ok">Active</span></dd></div>
                </dl>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($editDept): ?>
    <section class="sa-panel mb-3">
        <div class="sa-panel__head"><h2 class="sa-panel__title">Edit: <?= e((string)$editDept['department_name']) ?></h2>
            <a href="<?= e(route_url('admin/departments')) ?>" class="sa-panel__link">Close</a></div>
        <div class="sa-panel__body">
            <form method="post" class="row g-2">
                <?= csrf_input() ?><input type="hidden" name="action" value="update"><input type="hidden" name="department_id" value="<?= $editId ?>">
                <div class="col-md-4"><label class="form-label small">Name</label><input class="form-control form-control-sm" name="department_name" value="<?= e((string)$editDept['department_name']) ?>" required></div>
                <div class="col-md-4"><label class="form-label small">Head</label><select class="form-select form-select-sm" name="head_employee_id"><option value="">— None —</option><?php foreach ($allEmployees as $emp): ?><option value="<?= (int)$emp['id'] ?>" <?= (int)($editDept['head_employee_id'] ?? 0)===(int)$emp['id']?'selected':'' ?>><?= e((string)$emp['full_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="active">Active</option><option value="inactive" <?= ($editDept['status']??'')==='inactive'?'selected':'' ?>>Disabled</option></select></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100">Save</button></div>
            </form>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($dbRows !== []): ?>
    <section class="sa-panel sa-table-wrap">
        <table class="sa-table mb-0">
            <thead><tr><th>Department</th><th>Head</th><th class="text-end">Logins</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($dbRows as $r):
                $did = (int)$r['id'];
                $loginUsers = admin_department_login_user_count($pdo, (string)($r['department_code'] ?? ''), (string)$r['department_name']);
            ?>
                <tr>
                    <td><strong><?= e((string)$r['department_name']) ?></strong></td>
                    <td><?= e((string)($r['head_name'] ?? '—')) ?></td>
                    <td class="text-end"><?= $loginUsers ?></td>
                    <td><span class="sa-badge sa-badge--ok"><?= e((string)$r['status']) ?></span></td>
                    <td class="text-end">
                        <div class="dropdown d-inline-block">
                            <button class="sa-btn sa-btn--ghost dropdown-toggle" data-bs-toggle="dropdown">⋮ Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= e(route_url('admin/departments', ['edit' => $did])) ?>">Edit</a></li>
                                <li><a class="dropdown-item" href="<?= e(route_url('admin/users', ['department' => (string)$r['department_name']])) ?>">Assign Users</a></li>
                            </ul>
                        </div>
                    </td>
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
        <div class="col-12"><label>Category</label><select class="form-select" name="category_id" required><?php foreach (admin_department_categories($pdo) as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= e((string)$cat['category_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-8"><label>Name</label><input class="form-control" name="department_name" required></div>
        <div class="col-4"><label>Code</label><input class="form-control" name="department_code" required maxlength="10"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary btn-sm">Add Department</button></div>
</form></div></div>
